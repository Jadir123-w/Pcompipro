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

    public function __construct(
        QuotationAIEngine $aiEngine,
        QuotationCalculationEngine $calcEngine,
        MarginService $marginService,
        ProductMatchingService $productMatcher,
        VolumePricingService $volumePricing
    ) {
        $this->aiEngine       = $aiEngine;
        $this->calcEngine     = $calcEngine;
        $this->marginService  = $marginService;
        $this->productMatcher = $productMatcher;
        $this->volumePricing  = $volumePricing;
    }

    /**
     * Generar una precotización desde texto libre.
     *
     * @param  string    $text       Texto del requerimiento
     * @param  array     $context    Contexto: empresa_id, contacto_id, requerimiento_id, user_id, etc.
     * @return PreQuote
     */
    public function generateFromText($text, array $context = [])
    {
        Log::info('QuotationOrchestrator: generando precotización', [
            'text_length'      => strlen($text),
            'requerimiento_id' => isset($context['requerimiento_id']) ? $context['requerimiento_id'] : null,
        ]);

        // 1. Interpretar el texto con IA
        // S2 — si el contexto trae reference_image_path, el AIEngine la
        // pasa al provider como input multimodal junto con el texto.
        $interpretation = $this->aiEngine->interpretRequirement($text, $context);

        // Verificar si hay ítems
        if (empty($interpretation['items'])) {
            Log::warning('QuotationOrchestrator: no se detectaron ítems', [
                'warnings' => isset($interpretation['warnings']) ? $interpretation['warnings'] : [],
            ]);
        }

        // 2. Crear la precotización
        $prequote = PreQuote::create([
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
        ]);

        // 3. Crear ítems
        $items = isset($interpretation['items']) ? $interpretation['items'] : [];

        foreach ($items as $itemData) {
            $this->createPreQuoteItem($prequote, $itemData);
        }

        // 4. Calcular totales
        $prequote = $this->calcEngine->recalculate($prequote);

        // 5. Registrar en historial
        PreQuoteHistory::log(
            $prequote->id,
            $prequote->version,
            PreQuoteHistory::ACTION_CREATED,
            [
                'items_detected' => count($items),
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

        return $this->generateFromText($text, $context);
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
