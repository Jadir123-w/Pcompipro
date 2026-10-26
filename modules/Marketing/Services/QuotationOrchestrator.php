<?php

namespace Modules\Marketing\Services;

use Illuminate\Support\Facades\Log;
use Modules\Marketing\Models\PreQuote;
use Modules\Marketing\Models\PreQuoteItem;
use Modules\Marketing\Models\PreQuoteHistory;
use Modules\Marketing\Models\QuotationConfig;

/**
 * QuotationOrchestrator — Coordinador central del cotizador COM-02
 *
 * Flujo:
 * 1. Recibe requerimiento (texto libre o desde ERP)
 * 2. Llama a QuotationAIEngine para interpretar
 * 3. Crea PreQuote + PreQuoteItems
 * 4. Para cada ítem: busca margen, asigna costo base
 * 5. Llama a CalculationEngine para calcular
 * 6. Retorna la precotización completa
 */
class QuotationOrchestrator
{
    /** @var QuotationAIEngine */
    private $aiEngine;

    /** @var QuotationCalculationEngine */
    private $calcEngine;

    /** @var MarginService */
    private $marginService;

    /** @var ProductMatchingService */
    private $productMatcher;

    /** @var VolumePricingService */
    private $volumePricing;
    /** @var CotizacionAnteriorService */
    protected $cotizacionAnterior;

    public function __construct(
        QuotationAIEngine $aiEngine,
        QuotationCalculationEngine $calcEngine,
        MarginService $marginService,
        ProductMatchingService $productMatcher,
        VolumePricingService $volumePricing,
        CotizacionAnteriorService $cotizacionAnterior
    ) {
        $this->aiEngine       = $aiEngine;
        $this->calcEngine     = $calcEngine;
        $this->marginService  = $marginService;
        $this->productMatcher = $productMatcher;
        $this->volumePricing  = $volumePricing;
        $this->cotizacionAnterior = $cotizacionAnterior;
    }

    /**
     * Generar una precotización desde texto libre.
     *
     * Si la IA no devuelve productos no se guarda nada (antes quedaba una pre-cotización
     * vacía por cada intento fallido). Con $existente, sus ítems se reemplazan por los
     * nuevos en una sola transacción: si algo falla, los ítems actuales quedan intactos.
     *
     * @param  string    $text       Texto del requerimiento
     * @param  array     $context    Contexto: empresa_id, contacto_id, requerimiento_id, user_id, etc.
     * @param  PreQuote|null  $existente  Pre-cotización a regenerar en el mismo registro
     * @return PreQuote
     */
    public function generateFromText($text, array $context = [], PreQuote $existente = null)
    {
        Log::info('QuotationOrchestrator: generando precotización', [
            'text_length'      => strlen($text),
            'requerimiento_id' => isset($context['requerimiento_id']) ? $context['requerimiento_id'] : null,
        ]);

        // 1. Interpretar el texto con IA
        // S2 — si el contexto trae reference_image_path, el AIEngine la
        // pasa al provider como input multimodal junto con el texto.
        $interpretation = $this->aiEngine->interpretRequirement($text, $context);

        // Verificar si hay ítems: sin productos no se crea ni se modifica nada.
        if (empty($interpretation['items'])) {
            Log::warning('QuotationOrchestrator: no se detectaron ítems', [
                'warnings' => isset($interpretation['warnings']) ? $interpretation['warnings'] : [],
            ]);
            throw new \DomainException(
                'La IA no devolvió productos. Causa más probable: el proveedor de IA del cotizador (COM-02) no respondió '
                . '(sin saldo o sin conexión). Revisa Marketing → Configuración de Agentes IA → COM-02 Cotizador, '
                . 'o ajusta la descripción del requerimiento e inténtalo de nuevo.'
            );
        }

        // 2. Crear la precotización (o actualizar la existente)
        $datos = [
            'requerimiento_id'   => isset($context['requerimiento_id']) ? $context['requerimiento_id'] : null,
            'empresa_id'         => isset($context['empresa_id']) ? $context['empresa_id'] : null,
            'contacto_id'        => isset($context['contacto_id']) ? $context['contacto_id'] : null,
            'version'            => 1,
            'estado'             => PreQuote::ESTADO_BORRADOR,
            'titulo'             => isset($context['titulo']) ? $context['titulo'] : $this->generateTitle($interpretation),
            'descripcion_original' => $text,
            'texto_interpretado' => json_encode($interpretation),
            // S2 — persistir el path de la imagen multimodal si vino en el contexto
            'reference_image_path' => isset($context['reference_image_path']) ? $context['reference_image_path'] : null,
            'reference_image_mime' => isset($context['reference_image_mime']) ? $context['reference_image_mime'] : null,
            'user_id'            => isset($context['user_id']) ? $context['user_id'] : null,
            'ai_provider'        => config('marketing.chatbot.ai_provider', 'claude'),
            'ai_model'           => config('marketing.chatbot.ai.' . config('marketing.chatbot.ai_provider', 'claude') . '.model'),
            'subtotal'           => 0,
            'total_igv'          => 0,
            'total'              => 0,
        ];
        $items = $interpretation['items'];
        $imagenesAnteriores = [];

        // 3. Crear ítems y 4. calcular totales, todo junto
        $prequote = \Illuminate\Support\Facades\DB::connection('tenant')->transaction(function () use ($existente, $datos, $items, &$imagenesAnteriores) {
            if ($existente) {
                $imagenesAnteriores = $existente->items()->whereNotNull('product_reference_image')
                    ->pluck('product_reference_image')->all();
                // Borra los ítems anteriores; sus proveedores y componentes de kit caen en cascada.
                $existente->items()->delete();
                // Se conservan el creador y la imagen de referencia de la pre-cotización.
                unset($datos['user_id']);
                if (empty($datos['reference_image_path'])) {
                    unset($datos['reference_image_path'], $datos['reference_image_mime']);
                }
                // La versión no cambia: aún no se guardan versiones por separado.
                unset($datos['version']);
                $existente->update($datos);
                $prequote = $existente;
            } else {
                $prequote = PreQuote::create($datos);
            }

            foreach ($items as $itemData) {
                $this->createPreQuoteItem($prequote, $itemData);
            }

            return $this->calcEngine->recalculate($prequote);
        });

        // Fotos de los ítems anteriores que ya nadie usa (best-effort, fuera de la transacción).
        foreach (array_unique($imagenesAnteriores) as $ruta) {
            try {
                if (!PreQuoteItem::where('product_reference_image', $ruta)->exists()
                    && \Illuminate\Support\Facades\Storage::disk('local')->exists($ruta)) {
                    \Illuminate\Support\Facades\Storage::disk('local')->delete($ruta);
                }
            } catch (\Exception $e) {
                // Si el archivo no se puede borrar, la regeneración igual queda hecha.
            }
        }

        // 5. Registrar en historial
        PreQuoteHistory::log(
            $prequote->id,
            $prequote->version,
            PreQuoteHistory::ACTION_CREATED,
            [
                'items_detected' => count($items),
                'regenerada'     => (bool) $existente,
                'ai_provider'    => $prequote->ai_provider,
                'warnings'       => isset($interpretation['warnings']) ? $interpretation['warnings'] : [],
            ],
            isset($context['user_id']) ? $context['user_id'] : null
        );

        Log::info('QuotationOrchestrator: precotización generada', [
            'prequote_id' => $prequote->id,
            'items_count' => count($items),
            'total'       => $prequote->total,
        ]);

        return $prequote->load('items');
    }

    /**
     * Generar precotización desde un requerimiento del ERP.
     *
     * @param  int       $requerimientoId
     * @param  int|null  $userId
     * @return PreQuote
     */
    public function generateFromRequerimiento($requerimientoId, $userId = null)
    {
        $requerimiento = \Modules\Comercial\Models\Requerimiento::findOrFail($requerimientoId);

        $text = $requerimiento->descripcion;

        if (empty(trim($text))) {
            $text = $requerimiento->titulo;
        }

        $context = [
            'requerimiento_id'   => $requerimiento->id,
            'empresa_id'         => $requerimiento->empresa_id,
            'contacto_id'        => $requerimiento->contacto_id,
            'user_id'            => $userId,
            'titulo'             => $requerimiento->titulo,
            'categoria_productos' => $requerimiento->categoria_productos,
        ];

        // Una pre-cotización por requerimiento: si ya existe, "Regenerar" actualiza ese
        // mismo registro (antes cada clic creaba otra). Solo en borrador y sin convertir.
        $existente = PreQuote::where('requerimiento_id', $requerimiento->id)->orderByDesc('id')->first();
        if ($existente && ($existente->quotation_id || $existente->estado !== PreQuote::ESTADO_BORRADOR)) {
            throw new \DomainException('Solo se puede regenerar una pre-cotización en borrador.');
        }
        // Regenerar borra los ítems: después de emitir al cliente solo se edita.
        if ($existente && $existente->fueEmitida()) {
            throw new \DomainException('Esta pre-cotización ya se emitió al cliente: edita sus ítems en vez de regenerarla.');
        }

        return $this->generateFromText($text, $context, $existente);
    }

    /**
     * Crear un ítem de precotización a partir de los datos del LLM.
     *
     * @param  PreQuote  $prequote
     * @param  array     $itemData
     * @return PreQuoteItem
     */
    public function createPreQuoteItem(PreQuote $prequote, array $itemData)
    {
        $productName = isset($itemData['product_name']) ? $itemData['product_name'] : 'Producto sin nombre';
        $productDesc = isset($itemData['product_description']) ? $itemData['product_description'] : null;
        $category    = isset($itemData['category']) ? $itemData['category'] : 'Otros';
        $quantity    = isset($itemData['quantity']) ? (int) $itemData['quantity'] : 1;

        // Producto en el catálogo del ERP — matching fuzzy con scoring + sugerencias top-5
        $matchResult = $this->productMatcher->match($productName, $productDesc);

        $catalogItem = null;
        $itemId      = null;
        $costoBase   = 0;
        $matchConfidence  = $matchResult['confidence'];
        $needsReview      = $matchResult['needs_review'];
        $suggestions      = $matchResult['suggestions'];

        if (!empty($matchResult['best_match']) && isset($matchResult['best_match']['item_id'])) {
            $itemId = (int) $matchResult['best_match']['item_id'];
            try {
                $catalogItem = \App\Models\Tenant\Item::find($itemId);
            } catch (\Exception $e) {
                $catalogItem = null;
            }

            if ($catalogItem) {
                // Fase C — Precios por volumen: si el ítem tiene escalones en
                // item_unit_types (quantity_unit + price1), aplicar el escalón
                // mayorista alcanzado para esta cantidad.
                $volumeMatch = $this->volumePricing->getCostForVolume($itemId, $quantity);

                if ($volumeMatch !== null) {
                    $costoBase = $volumeMatch['cost'];
                    Log::info('QuotationOrchestrator: aplicando precio por volumen', [
                        'item_id'        => $itemId,
                        'quantity'       => $quantity,
                        'tier_qty'       => $volumeMatch['tier']['quantity_unit'],
                        'tier_price1'    => $volumeMatch['tier']['price1'],
                    ]);
                } else {
                    // Sin escalón aplicable (o cantidad por debajo del menor escalón):
                    // fallback al costo base del catálogo.
                    $costoBase = (float) $catalogItem->purchase_unit_price > 0
                        ? (float) $catalogItem->purchase_unit_price
                        : (float) $catalogItem->sale_unit_price * 0.6;
                }
            }
        }

        // Buscar margen
        $margen = $this->marginService->getMarginForItem($category, $productName, $quantity);

        // Valores por defecto desde configuración (S3: defaults operativos
        // por categoría según el doc §21). Se usa el normalizado de la
        // categoría para elegir el bucket correcto (textil, impresos, eco,
        // packs u otros).
        $catKey         = $this->normalizeCategoryKey($category);
        $defaultColores = QuotationConfig::getValue('default_colores', 1);
        $defaultLados   = QuotationConfig::getValue('default_lados', 1);
        $defaultEmpaque = QuotationConfig::getValue('defop_empaque_' . $catKey, QuotationConfig::getValue('default_empaque', 'bolsa individual'));
        $defaultTecnica = QuotationConfig::getValue('defop_tecnica_' . $catKey, QuotationConfig::getValue('default_tecnica_impresion', null));
        $defaultTiempo  = (int) QuotationConfig::getValue('defop_tiempo_' . $catKey . '_dias', QuotationConfig::getValue('default_tiempo_dias', 15));
        $defaultMaterial = QuotationConfig::getValue('defop_default_material', null);
        $defaultUnidad   = QuotationConfig::getValue('defop_default_unidad', null);

        $item = PreQuoteItem::create([
            'prequote_id'              => $prequote->id,
            'item_order'               => isset($itemData['item_order']) ? $itemData['item_order'] : 0,
            'item_id'                  => $itemId,
            'product_name'             => $productName,
            'product_description'      => $productDesc,
            'product_match_confidence' => $matchConfidence,
            'needs_product_review'     => $needsReview,
            'product_suggestions'      => !empty($suggestions) ? json_encode($suggestions) : null,
            'quantity'                 => $quantity,
            'unit_type_id'          => isset($itemData['unit_type_id']) ? $itemData['unit_type_id'] : $defaultUnidad,
            'material'              => isset($itemData['material']) && $itemData['material'] !== '' ? $itemData['material'] : $defaultMaterial,
            'medida'                => isset($itemData['medida']) ? $itemData['medida'] : null,
            'tecnica_impresion'     => isset($itemData['tecnica_impresion']) && $itemData['tecnica_impresion'] !== '' ? $itemData['tecnica_impresion'] : $defaultTecnica,
            'cantidad_colores'      => isset($itemData['cantidad_colores']) ? (int) $itemData['cantidad_colores'] : $defaultColores,
            'cantidad_lados'        => isset($itemData['cantidad_lados']) ? (int) $itemData['cantidad_lados'] : $defaultLados,
            'empaque'               => isset($itemData['empaque']) && $itemData['empaque'] !== '' ? $itemData['empaque'] : $defaultEmpaque,
            'personalizacion'       => isset($itemData['personalizacion']) ? $itemData['personalizacion'] : null,
            'costo_base'            => $costoBase,
            'costos_adicionales'    => null,
            'margen_porcentaje'     => $margen,
            'valor_unitario'        => 0,
            'valor_total'           => 0,
            'base_igv'              => 0,
            'igv'                   => 0,
            'total_item'            => 0,
            // COM08 — tiempo de entrega es texto libre. Se parte del default por
            // categoría (defop_tiempo_{cat}_dias) renderizado como texto editable;
            // la ejecutiva lo sobreescribe con la fecha/frase real de la propuesta
            // (ej. "Mié 02 Abril *", "1 día útil").
            'tiempo_estimado_dias'  => $defaultTiempo > 0 ? ($defaultTiempo . ' días útiles') : '',
            'datos_explicitos'      => json_encode(isset($itemData['datos_explicitos']) ? $itemData['datos_explicitos'] : []),
            'datos_inferidos'       => json_encode(isset($itemData['datos_inferidos']) ? $itemData['datos_inferidos'] : []),
            'observaciones'         => isset($itemData['observaciones']) ? $itemData['observaciones'] : null,
            'is_inferred'           => true,
        ]);

        // Si el producto ya se cotizó en otro requerimiento, trae su margen y su
        // proveedor ganador (con costo y tiempo de entrega).
        $this->cotizacionAnterior->aplicar($item);

        return $item;
    }

    /**
     * Normalizar el nombre de la categoría a una clave corta para los
     * defaults operativos: textil | impresos | eco | packs | otros.
     *
     * Se hace string-matching tolerante a mayúsculas/tildes/sinónimos
     * comunes que el LLM puede devolver.
     *
     * @param  string|null  $category
     * @return string
     */
    private function normalizeCategoryKey($category)
    {
        $cat = trim(mb_strtolower((string) $category, 'UTF-8'));
        if ($cat === '') return 'otros';

        // strip tildes
        $map = ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ñ'=>'n'];
        $cat = strtr($cat, $map);

        if (strpos($cat, 'textil') !== false || strpos($cat, 'ropa') !== false || strpos($cat, 'mochila') !== false || strpos($cat, 'polo') !== false || strpos($cat, 'casaca') !== false) {
            return 'textil';
        }
        if (strpos($cat, 'impreso') !== false || strpos($cat, 'libreta') !== false || strpos($cat, 'agenda') !== false || strpos($cat, 'cuaderno') !== false || strpos($cat, 'volante') !== false) {
            return 'impresos';
        }
        if (strpos($cat, 'eco') !== false || strpos($cat, 'ecologic') !== false || strpos($cat, 'reciclad') !== false || strpos($cat, 'kraft') !== false || strpos($cat, 'bambu') !== false) {
            return 'eco';
        }
        if (strpos($cat, 'pack') !== false || strpos($cat, 'kit') !== false) {
            return 'packs';
        }
        return 'otros';
    }

    /**
     * @deprecated Usar ProductMatchingService::match() — esta función queda
     * solo para compatibilidad. El matching nuevo soporta sugerencias top-5
     * y confidence score.
     *
     * @param  string  $productName
     * @return \App\Models\Tenant\Item|null
     */
    private function findCatalogItem($productName)
    {
        try {
            return \App\Models\Tenant\Item::query()
                ->where('active', true)
                ->where(function ($q) use ($productName) {
                    $q->where('name', 'LIKE', '%' . $productName . '%')
                      ->orWhere('description', 'LIKE', '%' . $productName . '%');
                })
                ->first();
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Generar título automático para la precotización.
     *
     * @param  array  $interpretation
     * @return string
     */
    private function generateTitle(array $interpretation)
    {
        $items = isset($interpretation['items']) ? $interpretation['items'] : [];
        $count = count($items);

        if ($count === 0) {
            return 'Precotización (sin ítems detectados)';
        }

        if ($count === 1) {
            $name = isset($items[0]['product_name']) ? $items[0]['product_name'] : 'producto';
            $qty = isset($items[0]['quantity']) ? $items[0]['quantity'] : '';
            return 'Precotización: ' . ($qty ? $qty . ' ' : '') . $name;
        }

        // Multi-producto: usar los primeros 2 nombres
        $names = [];
        foreach (array_slice($items, 0, 2) as $item) {
            $names[] = isset($item['product_name']) ? $item['product_name'] : 'producto';
        }

        $title = 'Precotización: ' . implode(', ', $names);
        if ($count > 2) {
            $title .= ' (+' . ($count - 2) . ' más)';
        }

        return $title;
    }
}
