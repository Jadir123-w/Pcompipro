<?php

namespace Modules\Marketing\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
use Modules\Marketing\Models\PreQuote;
use Modules\Marketing\Services\MarginService;
use Modules\Marketing\Services\QuotationAIEngine;
use Modules\Marketing\Services\QuotationOrchestrator;
use Modules\Marketing\Services\QuotationCalculationEngine;
use Modules\Marketing\Services\VolumePricingService;

/**
 * QuotationPreQuoteController — API del cotizador COM-02
 *
 * Endpoints para generar, listar, editar y aprobar precotizaciones.
 */
class QuotationPreQuoteController extends Controller
{
    // Validación + persistencia de imágenes de referencia (MIME real, tamaño,
    // path), compartida con QuotationImageController — para el alta de ítems por
    // referencia (interpretItem).
    use \Modules\Marketing\Http\Controllers\Api\Concerns\InteractsWithQuotationImages;

    /** @var QuotationOrchestrator */
    private $orchestrator;

    /** @var QuotationCalculationEngine */
    private $calcEngine;

    /** @var QuotationAIEngine */
    private $aiEngine;

    /** @var MarginService */
    private $marginService;

    /** @var VolumePricingService */
    private $volumePricing;

    public function __construct(
        QuotationOrchestrator $orchestrator,
        QuotationCalculationEngine $calcEngine,
        QuotationAIEngine $aiEngine,
        MarginService $marginService,
        VolumePricingService $volumePricing
    ) {
        $this->orchestrator  = $orchestrator;
        $this->calcEngine    = $calcEngine;
        $this->aiEngine      = $aiEngine;
        $this->marginService = $marginService;
        $this->volumePricing = $volumePricing;
    }

    /**
     * Enriquecer cada ítem con campos virtuales que dependen del estado actual
     * del catálogo / reglas (no se persisten):
     *
     *   - min_margin_aplicable          (Fase B): alerta de margen mínimo
     *   - precios_volumen_disponibles   (Fase C): escalones del item_unit_types
     *   - tier_aplicado                 (Fase C): escalón actualmente aplicado
     *
     * Se calculan on-the-fly porque dependen del estado vivo de las tablas;
     * cachearlos en BD los dejaría desactualizados al modificar una regla o
     * agregar un escalón nuevo.
     *
     * @param  PreQuote $prequote
     * @return PreQuote
     */
    private function enrichItemsWithMinMargin(PreQuote $prequote)
    {
        if (!$prequote->relationLoaded('items')) {
            $prequote->load('items');
        }

        // COM-02 — SCTR unitario. Resolvemos la cascada aquí (sin N+1): el global
        // se consulta una sola vez; el % de la cotización se deriva de él. Cada
        // ítem expone su % efectivo (ítem → cotización → global) para que la UI lo
        // muestre directo sin recalcular.
        $sctrGlobal = (float) \Modules\Marketing\Models\QuotationConfig::getValue('sctr_porcentaje', 1.5);
        $sctrCotizacion = ($prequote->sctr_porcentaje !== null && $prequote->sctr_porcentaje !== '')
            ? (float) $prequote->sctr_porcentaje
            : $sctrGlobal;

        foreach ($prequote->items as $item) {
            // PreQuoteItem no tiene un campo `category` propio: derivamos la
            // categoría desde el catalogItem si existe (cuando hubo matching).
            // En caso contrario usamos 'General' (es el bucket fallback que
            // MarginRule::findBestMatch entiende como regla por defecto).
            $category = 'General';
            if ($item->relationLoaded('catalogItem') && $item->catalogItem) {
                $catCategory = $item->catalogItem->category ?? null;
                if (!empty($catCategory)) {
                    $category = $catCategory;
                }
            }

            $productName = $item->product_name ?: null;
            $quantity    = (int) ($item->quantity ?: 1);

            // % de SCTR realmente aplicado a este ítem (cascada).
            $item->sctr_porcentaje_efectivo = ($item->sctr_porcentaje !== null && $item->sctr_porcentaje !== '')
                ? (float) $item->sctr_porcentaje
                : $sctrCotizacion;

            // Fase B — margen mínimo aplicable
            $item->min_margin_aplicable = $this->marginService
                ->getMinMarginForItem($category, $productName, $quantity);

            // Fase C — escalones de precio por volumen (solo si hay match catálogo)
            if ($item->item_id) {
                $item->precios_volumen_disponibles = $this->volumePricing
                    ->getAvailableTiers($item->item_id);

                $match = $this->volumePricing->getCostForVolume($item->item_id, $quantity);
                $item->tier_aplicado = $match ? $match['tier'] : null;
            } else {
                $item->precios_volumen_disponibles = [];
                $item->tier_aplicado = null;
            }
        }

        // R4 — total de la ganancia (markup) en soles: suma de los ítems. Se
        // calcula aquí porque los items ya están cargados (sin N+1); no aparece
        // en el listado de precotizaciones, donde los items no se cargan.
        $prequote->ganancia_total = round($prequote->items->sum('ganancia_absoluta'), 2);

        // COM-02 — default de SCTR del config, para que la UI precargue el valor
        // real configurado cuando la cotización aún no tiene un SCTR propio
        // (sctr_porcentaje = NULL). El SCTR propio va en $prequote->sctr_porcentaje.
        $prequote->sctr_config_default = $sctrGlobal;

        return $prequote;
    }

    /**
     * Generar precotización desde texto libre o requerimiento.
     *
     * POST /api/marketing/quotation/generate
     */
    public function generate(Request $request)
    {
        $v = Validator::make($request->all(), [
            'text'              => ['required_without:requerimiento_id', 'nullable', 'string', 'max:5000'],
            'requerimiento_id'  => ['required_without:text', 'nullable', 'integer'],
            'empresa_id'        => ['nullable', 'integer'],
            'contacto_id'       => ['nullable', 'integer'],
            'titulo'            => ['nullable', 'string', 'max:255'],
        ]);

        if ($v->fails()) {
            return response()->json(['ok' => false, 'errors' => $v->errors()], 422);
        }

        try {
            $requerimientoId = $request->input('requerimiento_id');

            if ($requerimientoId) {
                // Generar desde requerimiento del ERP
                $userId = $request->user() ? $request->user()->id : null;
                $prequote = $this->orchestrator->generateFromRequerimiento($requerimientoId, $userId);
            } else {
                // Generar desde texto libre
                $context = [
                    'empresa_id'  => $request->input('empresa_id'),
                    'contacto_id' => $request->input('contacto_id'),
                    'titulo'      => $request->input('titulo'),
                    'user_id'     => $request->user() ? $request->user()->id : null,
                ];
                $prequote = $this->orchestrator->generateFromText($request->input('text'), $context);
            }

            return response()->json([
                'ok'        => true,
                'prequote'  => $this->enrichItemsWithMinMargin($prequote->load('items')),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'ok'      => false,
                'message' => 'Error generando precotización: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Listar precotizaciones con filtros.
     *
     * GET /api/marketing/quotation/prequotes
     */
    public function index(Request $request)
    {
        $query = PreQuote::query()
            ->with('empresa:id,razon_social', 'user:id,name')
            ->orderByDesc('created_at');

        if ($request->filled('estado')) {
            $query->byEstado($request->input('estado'));
        }

        if ($request->filled('empresa_id')) {
            $query->byEmpresa($request->input('empresa_id'));
        }

        if ($request->filled('requerimiento_id')) {
            $query->where('requerimiento_id', $request->input('requerimiento_id'));
        }

        $prequotes = $query->paginate($request->input('per_page', 20));

        return response()->json(['ok' => true, 'prequotes' => $prequotes]);
    }

    /**
     * Ver detalle de una precotización con ítems.
     *
     * GET /api/marketing/quotation/prequotes/{id}
     */
    public function show($id)
    {
        $prequote = PreQuote::with([
                'items',
                'items.catalogItem:id,name,description,sale_unit_price,internal_id,item_code',
                'empresa',
                'contacto',
                'user',
                'reviewer',
                'history',
            ])
            ->findOrFail($id);

        return response()->json([
            'ok'       => true,
            'prequote' => $this->enrichItemsWithMinMargin($prequote),
        ]);
    }

    /**
     * Editar un ítem y recalcular.
     *
     * PUT /api/marketing/quotation/prequotes/{id}/item/{itemId}
     */
    public function updateItem(Request $request, $id, $itemId)
    {
        $prequote = PreQuote::findOrFail($id);

        if (!$prequote->isEditable()) {
            return response()->json([
                'ok'      => false,
                'message' => 'La precotización no es editable en estado: ' . $prequote->estado,
            ], 422);
        }

        $item = $prequote->items()->where('id', $itemId)->firstOrFail();

        // Campos editables
        $editableFields = [
            'product_name', 'product_description', 'quantity', 'material', 'medida',
            'tecnica_impresion', 'cantidad_colores', 'cantidad_lados', 'empaque',
            'personalizacion', 'supplier_id', 'supplier_name', 'costo_base',
            'costos_adicionales', 'margen_porcentaje', 'tiempo_estimado_dias', 'observaciones',
            // Agosto 2026 (punto 9): plazo ofrecido al cliente, editable desde el
            // Preview. Distinto de tiempo_estimado_dias, que es el de producción.
            'tiempo_entrega',
            // Componentes de costo (Fase A — motor de costeo ampliado).
            // COM09 — costo_muestra ya NO se ingresa: se deriva en el motor
            // (numero_muestras × costo_base). La ejecutiva ingresa numero_muestras.
            'numero_muestras', 'costo_movilidad', 'costo_impresion', 'costo_empaque',
            // Agosto 2026 (punto 4): precio unitario de la muestra. NULL = costo base.
            'precio_muestra',
            'impresion_por_unidad', 'empaque_por_unidad',
            // COM-02 — "Cantidad por Millar": 4º factor del Costo Total de Impresión.
            'cantidad_millar',
            // COM-02 — SCTR unitario: override de SCTR% por ítem (nullable = hereda
            // el de la cotización; y este, el global).
            'sctr_porcentaje',
            // Agosto 2026 (punto 2) — kit o servicio de armado: no se crea un
            // producto propio en el catálogo al convertir.
            'excluir_catalogo',
        ];

        $updates = $request->only($editableFields);

        // COM-02 — SCTR unitario: validar/normalizar el override por ítem.
        // '' o null → NULL (hereda de la cotización). Mismo rango que el de cotización.
        if (array_key_exists('sctr_porcentaje', $updates)) {
            $vs = Validator::make($request->all(), [
                'sctr_porcentaje' => ['nullable', 'numeric', 'min:0', 'max:10'],
            ]);
            if ($vs->fails()) {
                return response()->json(['ok' => false, 'errors' => $vs->errors()], 422);
            }
            $updates['sctr_porcentaje'] = ($updates['sctr_porcentaje'] === '' || $updates['sctr_porcentaje'] === null)
                ? null
                : (float) $updates['sctr_porcentaje'];
        }

        // COM-02 — "Cantidad por Millar": factor del Costo Total de Impresión.
        // '' / null / <= 0 → 1 (neutro): siempre hay un factor (no es opcional como
        // el SCTR), y lo almacenado refleja lo cobrado (el motor también trata
        // millar <= 0 como 1). max acotado al tope del DECIMAL(8,2) para no reventar.
        if (array_key_exists('cantidad_millar', $updates)) {
            $vm = Validator::make($request->all(), [
                'cantidad_millar' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            ]);
            if ($vm->fails()) {
                return response()->json(['ok' => false, 'errors' => $vm->errors()], 422);
            }
            $mv = $updates['cantidad_millar'];
            $updates['cantidad_millar'] = ($mv === '' || $mv === null || (float) $mv <= 0)
                ? 1
                : (float) $mv;
        }

        // COM-02 — colores y lados ahora MULTIPLICAN la impresión (antes eran
        // informativos): validarlos para no romper el UPDATE (columna INT) con
        // texto ni cobrar factores absurdos.
        $vcl = Validator::make($request->all(), [
            'cantidad_colores' => ['nullable', 'integer', 'min:0', 'max:99'],
            'cantidad_lados'   => ['nullable', 'integer', 'min:0', 'max:99'],
        ]);
        if ($vcl->fails()) {
            return response()->json(['ok' => false, 'errors' => $vcl->errors()], 422);
        }

        // Snapshot ANTES del update — necesario para detectar qué campos cambiaron
        // y poder remover del array datos_inferidos los que la ejecutiva confirmó.
        $previousValues   = $item->only($editableFields);
        $previousInferred = is_array($item->datos_inferidos) ? $item->datos_inferidos : [];

        // Fase C — Precios por volumen: si cambió la cantidad y el ítem está
        // matcheado al catálogo, recalcular costo_base con el escalón aplicable.
        //
        // El frontend SIEMPRE envía costo_base en el payload (es un input
        // controlado), así que no nos sirve `array_key_exists` para detectar
        // edición manual: comparamos contra el valor previo del ítem. Solo
        // consideramos "edición manual" cuando el costo_base del request difiere
        // del actual del item (con tolerancia de 0.001 por imprecisión decimal).
        $quantityChanged = array_key_exists('quantity', $updates)
            && (int) $updates['quantity'] !== (int) $item->quantity;
        $manualCostoBase = false;
        if (array_key_exists('costo_base', $updates)) {
            $reqCosto = (float) $updates['costo_base'];
            $curCosto = (float) $item->costo_base;
            $manualCostoBase = abs($reqCosto - $curCosto) > 0.001;
        }

        if ($quantityChanged && !$manualCostoBase && $item->item_id) {
            $newQty = (int) $updates['quantity'];
            $volumeMatch = $this->volumePricing->getCostForVolume($item->item_id, $newQty);
            if ($volumeMatch !== null) {
                $updates['costo_base'] = $volumeMatch['cost'];
                \Illuminate\Support\Facades\Log::info(
                    'QuotationPreQuoteController: costo_base recalculado por cambio de cantidad',
                    [
                        'item_id'     => $item->id,
                        'catalog_id'  => $item->item_id,
                        'old_qty'     => (int) $item->quantity,
                        'new_qty'     => $newQty,
                        'tier_qty'    => $volumeMatch['tier']['quantity_unit'],
                        'new_cost'    => $volumeMatch['cost'],
                    ]
                );
            }
        }

        // COM-02 — "Cód. Artíc.": el override manual lleva las iniciales del
        // proveedor, así que queda atado al proveedor con el que se cotizó. Si
        // la ejecutiva CAMBIA de proveedor, el código deja de ser válido y se
        // limpia (el ítem vuelve a heredar el del catálogo).
        // Al asignar el PRIMER proveedor NO se limpia: lo habitual es completar
        // el preview (y escribir el código) antes de elegir proveedor, y
        // borrarlo ahí sería perder trabajo en silencio.
        // Es una de las 2 únicas escrituras de supplier_id sobre un ítem; la
        // otra está en applySupplier().
        if (array_key_exists('supplier_id', $updates)
            && $item->supplier_id !== null
            && (int) $updates['supplier_id'] !== (int) $item->supplier_id
            && $this->tieneColumnaCodigoArticulo()) {
            $updates['codigo_articulo_manual'] = null;
        }

        $item->update($updates);
        $item->update(['is_inferred' => false]);

        // Mantener sincronizado datos_inferidos con la edición humana:
        // Si la ejecutiva editó un campo que estaba marcado como "inferido por IA",
        // al confirmarlo deja de serlo y lo removemos del array. Los campos que
        // NO se editaron (no vinieron en el payload o vinieron con el mismo valor)
        // siguen marcados como inferidos.
        // Comparamos como string para tolerar diferencias de tipo (ej: "5" vs 5).
        if (!empty($previousInferred)) {
            $stillInferred = [];
            foreach ($previousInferred as $field) {
                if (!array_key_exists($field, $updates)) {
                    $stillInferred[] = $field;
                    continue;
                }
                $oldVal = isset($previousValues[$field]) ? $previousValues[$field] : null;
                $newVal = $updates[$field];
                if ((string) $oldVal === (string) $newVal) {
                    $stillInferred[] = $field;
                }
            }
            if (count($stillInferred) !== count($previousInferred)) {
                $item->update(['datos_inferidos' => $stillInferred]);
            }
        }

        // Recalcular todo
        $prequote = $this->calcEngine->recalculate($prequote);

        // Registrar en historial
        \Modules\Marketing\Models\PreQuoteHistory::log(
            $prequote->id,
            $prequote->version,
            \Modules\Marketing\Models\PreQuoteHistory::ACTION_ITEM_EDITED,
            ['item_id' => $itemId, 'changes' => $updates],
            $request->user() ? $request->user()->id : null
        );

        return response()->json([
            'ok'       => true,
            'prequote' => $this->enrichItemsWithMinMargin($prequote->load('items')),
        ]);
    }

    /**
     * ¿Ya existe la columna `codigo_articulo_manual` en este tenant?
     *
     * Se consulta una sola vez por request. Sirve para la ventana entre el
     * deploy del código y el `tenancy:migrate` (que el CI corre en el mismo
     * despliegue, pero en local es manual): sin este guard, limpiar el código al
     * cambiar de proveedor intentaría escribir una columna inexistente y
     * devolvería un 500 en dos endpoints centrales del cotizador.
     *
     * @return bool
     */
    private function tieneColumnaCodigoArticulo()
    {
        static $existe = null;

        if ($existe === null) {
            try {
                $existe = \Illuminate\Support\Facades\Schema::connection('tenant')
                    ->hasColumn('quotation_prequote_items', 'codigo_articulo_manual');
            } catch (\Exception $e) {
                $existe = false;
            }
        }

        return $existe;
    }

    /**
     * Editar SOLO el "Cód. Artíc." de un ítem (override manual).
     *
     * PUT /api/marketing/quotation/prequotes/{id}/item/{itemId}/codigo
     * Body: { codigo_articulo_manual }   — vacío o null => vuelve al del catálogo
     *
     * Endpoint dedicado a propósito, NO parte de updateItem():
     *   - SIN candado de estado: el código es un dato de presentación (no entra
     *     en ningún cálculo) y lleva las iniciales del proveedor, que puede
     *     cambiar en cualquier momento. El área comercial debe poder corregirlo
     *     incluso en una cotización ya validada, emitida o aprobada, sin
     *     reabrirla.
     *   - SIN recalculate(): no afecta costos ni totales. Recalcular acá movería
     *     los totales de una cotización ya aprobada por corregir una etiqueta.
     *   - SIN tocar is_inferred ni datos_inferidos: no es un campo que infiera la IA.
     *
     * ⚠️ Consecuencias conocidas y aceptadas:
     *   - Editar el código de una cotización cuya propuesta ya fue EMITIDA y
     *     luego regenerar el PDF reescribe el archivo almacenado (se guarda
     *     siempre en la misma ruta), o sea cambia el documento que el cliente ya
     *     recibió. Por eso el historial registra el valor anterior.
     *   - Si la precotización ya fue CONVERTIDA, este cambio afecta al PDF pero
     *     NO al `internal_id` del ítem de catálogo que creó la conversión.
     */
    public function updateItemCodigo(Request $request, $id, $itemId)
    {
        $prequote = PreQuote::findOrFail($id);
        $item = $prequote->items()->where('id', $itemId)->firstOrFail();

        $v = Validator::make($request->all(), [
            // 30 es lo que aguanta la columna (espeja items.internal_id), pero el
            // tope real lo pone la columna "Cód. Artíc." del PDF: un código más
            // largo la ensancha y le roba ancho a "Detalles".
            'codigo_articulo_manual' => ['nullable', 'string', 'max:12'],
        ]);

        if ($v->fails()) {
            return response()->json(['ok' => false, 'errors' => $v->errors()], 422);
        }

        // Normalizar: sin espacios sobrantes y en mayúsculas (los códigos del
        // catálogo son alfanuméricos en mayúscula). Vacío => NULL, para que el
        // ítem vuelva a heredar el código del catálogo.
        $codigo = trim((string) $request->input('codigo_articulo_manual'));
        $codigo = ($codigo === '') ? null : strtoupper($codigo);

        $anterior = $item->codigo_articulo_manual;

        $item->update(['codigo_articulo_manual' => $codigo]);

        \Modules\Marketing\Models\PreQuoteHistory::log(
            $prequote->id,
            $prequote->version,
            \Modules\Marketing\Models\PreQuoteHistory::ACTION_ITEM_EDITED,
            [
                'item_id' => (int) $itemId,
                'action'  => 'codigo_articulo_manual',
                'changes' => [
                    'codigo_articulo_manual' => $codigo,
                    // El valor anterior importa: este campo se puede editar en
                    // cotizaciones ya aprobadas o emitidas, donde la trazabilidad
                    // es justamente lo que hace falta.
                    'previous'               => $anterior,
                ],
            ],
            $request->user() ? $request->user()->id : null
        );

        // Se devuelve solo el ítem (no la prequote): es la evidencia de que no
        // se recalculó nada. El fresh() arrastra los $appends, así que trae las
        // tres claves: manual, catálogo y efectivo.
        return response()->json([
            'ok'      => true,
            'item'    => $item->fresh(),
            'message' => 'Código de artículo actualizado.',
        ]);
    }

    /**
     * Eliminar un ítem de la precotización y recalcular.
     *
     * DELETE /api/marketing/quotation/prequotes/{id}/item/{itemId}
     *
     * - Valida que el ítem pertenezca a la prequote y que la prequote sea
     *   editable.
     * - Limpia la imagen propia del ítem (best-effort) para no dejar archivos
     *   huérfanos.
     * - Recalcula los totales. Si se borra el último ítem, la prequote queda
     *   sin productos y el motor deja subtotal/IGV/total en 0.
     */
    public function deleteItem(Request $request, $id, $itemId)
    {
        $prequote = PreQuote::findOrFail($id);

        if (!$prequote->isEditable()) {
            return response()->json([
                'ok'      => false,
                'message' => 'La precotización no es editable en estado: ' . $prequote->estado,
            ], 422);
        }

        // Validar que el ítem pertenece a ESTA prequote (404 si no).
        $item = $prequote->items()->where('id', $itemId)->firstOrFail();

        // Limpiar la imagen propia del ítem si tiene una (evita archivos huérfanos).
        //
        // Agosto 2026 (punto 5) — Solo si NINGÚN otro ítem la está usando. Al
        // duplicar un producto, la copia apunta al MISMO archivo en vez de
        // generar uno nuevo (así no se multiplican los archivos en disco al
        // cotizar por escala). Sin esta comprobación, borrar cualquiera de los
        // dos dejaría al otro con la imagen rota.
        if (!empty($item->product_reference_image)) {
            $otrosQueLaUsan = \Modules\Marketing\Models\PreQuoteItem::where('product_reference_image', $item->product_reference_image)
                ->where('id', '!=', $item->id)
                ->count();

            if ($otrosQueLaUsan === 0) {
                try {
                    if (\Illuminate\Support\Facades\Storage::disk('local')->exists($item->product_reference_image)) {
                        \Illuminate\Support\Facades\Storage::disk('local')->delete($item->product_reference_image);
                    }
                } catch (\Exception $e) {
                    // best-effort: si el archivo no se puede borrar, igual eliminamos el ítem.
                }
            }
        }

        $productName = $item->product_name;
        $item->delete();

        // Recalcular todo. Con la prequote sin ítems, recalculate() deja los
        // totales en 0 (el foreach sobre una colección vacía no acumula nada).
        $prequote = $this->calcEngine->recalculate($prequote);

        // Registrar en historial.
        \Modules\Marketing\Models\PreQuoteHistory::log(
            $prequote->id,
            $prequote->version,
            \Modules\Marketing\Models\PreQuoteHistory::ACTION_ITEM_REMOVED,
            ['item_id' => (int) $itemId, 'product_name' => $productName],
            $request->user() ? $request->user()->id : null
        );

        return response()->json([
            'ok'       => true,
            'prequote' => $this->enrichItemsWithMinMargin($prequote->load('items')),
        ]);
    }

    /**
     * Agosto 2026 (punto 5) · Duplicar un producto completo.
     *
     * POST /marketing/quotation/prequotes/{id}/item/{itemId}/duplicate
     *
     * Texto del cliente: "Botón para duplicar un producto completo, con imagen,
     * descripción, personalización y costos. Sirve para cotizaciones por escala,
     * donde el mismo producto se cotiza en 50, 100 y 300 unidades y hoy hay que
     * cargarlo de cero cada vez."
     *
     * Se copia TODO el ítem y la copia queda justo debajo del original, para que
     * la ejecutiva solo tenga que cambiarle la cantidad.
     *
     * Sobre la imagen: la copia apunta al MISMO archivo, no se genera uno nuevo.
     * Cotizar por escala significa 3 o 4 copias del mismo producto; duplicar el
     * archivo cada vez llenaría el disco de imágenes idénticas. La contrapartida
     * está cubierta en deleteItem(), que ahora solo borra el archivo cuando ya no
     * queda ningún ítem apuntando a él.
     *
     * Lo que NO se copia:
     *   · el historial de match de la IA (`product_suggestions`), porque es la
     *     traza de una búsqueda puntual y no un atributo del producto;
     *   · `needs_product_review`: si la ejecutiva ya resolvió el match del
     *     original, la copia no tiene por qué volver a pedir revisión.
     *
     * @param  Request $request
     * @param  int     $id      prequote
     * @param  int     $itemId  ítem a duplicar
     * @return \Illuminate\Http\JsonResponse
     */
    public function duplicateItem(Request $request, $id, $itemId)
    {
        $prequote = PreQuote::findOrFail($id);

        if (!$prequote->isEditable()) {
            return response()->json([
                'ok'      => false,
                'message' => 'La precotización no es editable en estado: ' . $prequote->estado,
            ], 422);
        }

        // Validar que el ítem pertenece a ESTA prequote (404 si no).
        $original = $prequote->items()->where('id', $itemId)->firstOrFail();

        try {
            \Illuminate\Support\Facades\DB::beginTransaction();

            $copia = $original->replicate([
                // Se excluyen para que la copia arranque con su propia traza.
                'product_suggestions',
                'needs_product_review',
            ]);

            // La copia va inmediatamente después del original y los que venían
            // detrás se corren uno. Así el orden de la propuesta sigue teniendo
            // sentido: las variantes de escala quedan juntas.
            $ordenOriginal = (int) $original->item_order;
            $prequote->items()
                ->where('item_order', '>', $ordenOriginal)
                ->increment('item_order');

            $copia->item_order          = $ordenOriginal + 1;
            $copia->needs_product_review = false;
            $copia->product_suggestions  = null;
            $copia->save();

            \Illuminate\Support\Facades\DB::commit();
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\DB::rollBack();
            \Log::error('Error duplicando ítem de precotización', [
                'prequote_id' => $prequote->id,
                'item_id'     => $itemId,
                'error'       => $e->getMessage(),
            ]);
            return response()->json([
                'ok'      => false,
                'message' => 'No se pudo duplicar el producto. Intenta nuevamente.',
            ], 500);
        }

        // Recalcular: la copia suma su propio subtotal al total de la cotización.
        $prequote = $this->calcEngine->recalculate($prequote);

        \Modules\Marketing\Models\PreQuoteHistory::log(
            $prequote->id,
            $prequote->version,
            \Modules\Marketing\Models\PreQuoteHistory::ACTION_ITEM_ADDED,
            [
                'item_id'      => (int) $copia->id,
                'duplicado_de' => (int) $original->id,
                'product_name' => $copia->product_name,
            ],
            $request->user() ? $request->user()->id : null
        );

        return response()->json([
            'ok'       => true,
            'item_id'  => $copia->id,
            'message'  => 'Producto duplicado. Ajusta la cantidad de la copia.',
            'prequote' => $this->enrichItemsWithMinMargin($prequote->load('items')),
        ]);
    }

    /**
     * Cambiar estado de la precotización.
     *
     * PUT /api/marketing/quotation/prequotes/{id}/status
     */
    public function updateStatus(Request $request, $id)
    {
        $prequote = PreQuote::findOrFail($id);

        $v = Validator::make($request->all(), [
            'estado' => ['required', 'string', 'in:' . implode(',', PreQuote::ESTADOS)],
        ]);

        if ($v->fails()) {
            return response()->json(['ok' => false, 'errors' => $v->errors()], 422);
        }

        $oldEstado = $prequote->estado;
        $newEstado = $request->input('estado');

        // No se puede validar/aprobar una pre-cotización sin productos: esos
        // estados habilitan la conversión a cotización formal, que no tiene
        // sentido vacía.
        $requierenItems = [PreQuote::ESTADO_VALIDADA, PreQuote::ESTADO_APROBADA];
        if (in_array($newEstado, $requierenItems) && $prequote->items->isEmpty()) {
            return response()->json([
                'ok'      => false,
                'message' => 'La pre-cotización no tiene productos. Agrega al menos uno antes de validar o aprobar.',
            ], 422);
        }

        $prequote->update([
            'estado'      => $newEstado,
            'reviewer_id' => $request->user() ? $request->user()->id : $prequote->reviewer_id,
        ]);

        \Modules\Marketing\Models\PreQuoteHistory::log(
            $prequote->id,
            $prequote->version,
            \Modules\Marketing\Models\PreQuoteHistory::ACTION_STATUS_CHANGED,
            ['from' => $oldEstado, 'to' => $newEstado],
            $request->user() ? $request->user()->id : null
        );

        return response()->json([
            'ok'       => true,
            'prequote' => $prequote->fresh(),
        ]);
    }

    /**
     * COM-02 — Fijar (o resetear) el % de SCTR de esta cotización y recalcular.
     *
     * El SCTR es por cotización (depende de la condición de pago). Por defecto
     * cada cotización usa el global del config; con este endpoint la ejecutiva
     * lo ajusta. `null` (o vacío) vuelve al global.
     *
     * PUT /api/marketing/quotation/prequotes/{id}/sctr
     * Body: { "sctr_porcentaje": 2.0 }   ó   { "sctr_porcentaje": null }
     */
    public function updateSctr(Request $request, $id)
    {
        $prequote = PreQuote::findOrFail($id);

        if (!$prequote->isEditable()) {
            return response()->json([
                'ok'      => false,
                'message' => 'La precotización no es editable en estado: ' . $prequote->estado,
            ], 422);
        }

        $v = Validator::make($request->all(), [
            'sctr_porcentaje' => ['nullable', 'numeric', 'min:0', 'max:10'],
        ]);

        if ($v->fails()) {
            return response()->json(['ok' => false, 'errors' => $v->errors()], 422);
        }

        // '' o ausente → NULL (volver al SCTR global del config).
        $sctr = $request->input('sctr_porcentaje');
        $sctr = ($sctr === '' || $sctr === null) ? null : (float) $sctr;

        $sctrAnterior = $prequote->sctr_porcentaje;

        // Guardar el % + recalcular en una sola transacción del tenant: el SCTR
        // afecta a TODOS los ítems, así que un fallo a mitad no debe dejar unos
        // ítems con el % nuevo y otros con el viejo. recalculate() devuelve la
        // precotización fresca (sin necesidad de un fresh() extra).
        $prequote = \Illuminate\Support\Facades\DB::connection('tenant')->transaction(function () use ($prequote, $sctr) {
            $prequote->update(['sctr_porcentaje' => $sctr]);
            return $this->calcEngine->recalculate($prequote);
        });

        // Auditoría: dejar rastro del cambio de SCTR (igual que los demás
        // mutadores: updateItem, updateStatus, applySupplier…).
        \Modules\Marketing\Models\PreQuoteHistory::log(
            $prequote->id,
            $prequote->version,
            \Modules\Marketing\Models\PreQuoteHistory::ACTION_RECALCULATED,
            [
                'field' => 'sctr_porcentaje',
                'from'  => $sctrAnterior !== null ? (float) $sctrAnterior : null,
                'to'    => $sctr,
            ],
            $request->user() ? $request->user()->id : null
        );

        return response()->json([
            'ok'       => true,
            'prequote' => $this->enrichItemsWithMinMargin($prequote),
            'message'  => 'SCTR actualizado. Totales recalculados.',
        ]);
    }

    /**
     * Recalcular precotización.
     *
     * POST /api/marketing/quotation/prequotes/{id}/recalculate
     */
    public function recalculate($id)
    {
        $prequote = PreQuote::findOrFail($id);

        // Solo se recalcula una precotización editable (borrador/en_revisión/
        // ajustada). Las validadas/aprobadas/convertidas quedan congeladas, igual
        // que en updateItem/updateSctr/deleteItem.
        if (!$prequote->isEditable()) {
            return response()->json([
                'ok'      => false,
                'message' => 'La precotización no es editable en estado: ' . $prequote->estado,
            ], 422);
        }

        $prequote = $this->calcEngine->recalculate($prequote);

        return response()->json([
            'ok'       => true,
            'prequote' => $this->enrichItemsWithMinMargin($prequote->load('items')),
        ]);
    }

    /**
     * COM-02 S2 — Re-interpretar la prequote usando la imagen de referencia
     * actual (multimodal).
     *
     * Usa la `descripcion_original` y `reference_image_path` ya guardados.
     * Borra los items existentes y los reemplaza con los nuevos detectados
     * por el LLM multimodal.
     *
     * Solo permitido si la prequote es editable (estado=borrador/en_revision/ajustada).
     *
     * POST /api/marketing/quotation/prequotes/{id}/reinterpret-with-image
     */
    public function reinterpretWithImage($id)
    {
        $prequote = PreQuote::findOrFail($id);

        if (!$prequote->isEditable()) {
            return response()->json([
                'ok'      => false,
                'message' => 'No se puede re-interpretar una prequote en estado: ' . $prequote->estado,
            ], 422);
        }

        if (empty($prequote->reference_image_path)) {
            return response()->json([
                'ok'      => false,
                'message' => 'Esta prequote no tiene imagen de referencia. Subila primero.',
            ], 422);
        }

        if (empty($prequote->descripcion_original)) {
            return response()->json([
                'ok'      => false,
                'message' => 'La prequote no tiene texto original para re-interpretar.',
            ], 422);
        }

        try {
            // Borrar items existentes (las cotizaciones formales convertidas
            // no se permiten editar — bloqueado por isEditable arriba)
            $prequote->items()->delete();

            // Re-interpretar con la imagen
            $context = [
                'requerimiento_id'      => $prequote->requerimiento_id,
                'empresa_id'            => $prequote->empresa_id,
                'contacto_id'           => $prequote->contacto_id,
                'reference_image_path'  => $prequote->reference_image_path,
                'reference_image_mime'  => $prequote->reference_image_mime,
                'user_id'               => auth()->id(),
            ];

            $interpretation = $this->aiEngine->interpretRequirement($prequote->descripcion_original, $context);

            $prequote->update([
                'texto_interpretado' => json_encode($interpretation),
                'version'            => $prequote->version + 1,
            ]);

            // Crear los nuevos items via el orchestrator (reusa la lógica
            // categoría-aware + matching de productos)
            $items = isset($interpretation['items']) ? $interpretation['items'] : [];
            foreach ($items as $itemData) {
                $this->orchestrator->createPreQuoteItem($prequote, $itemData);
            }

            // Recalcular totales
            $prequote = $this->calcEngine->recalculate($prequote);

            return response()->json([
                'ok'       => true,
                'message'  => 'Re-interpretado con imagen.  Nuevos items: ' . count($items),
                'prequote' => $this->enrichItemsWithMinMargin($prequote->load('items', 'items.catalogItem')),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'ok'      => false,
                'message' => 'Error re-interpretando: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * "Referencia del producto": interpreta una referencia (TEXTO XOR IMAGEN) con
     * IA y AGREGA 1 ítem a la pre-cotización, SIN borrar/regenerar los existentes.
     * Bootstrapea una prequote vacía si el requerimiento aún no tiene ninguna.
     *
     * POST /marketing/quotation/requerimientos/{requerimientoId}/prequote/items/interpret
     * multipart/form-data: exactamente uno de { text | image }; opcional prequote_id.
     */
    public function interpretItem(Request $request, $requerimientoId)
    {
        // ── 1) XOR estricto text|image (cubre "ambos" y "ninguno") ──────────────
        $text     = trim((string) $request->input('text', ''));
        $hasText  = $text !== '';
        $hasImage = $request->hasFile('image');

        // Si el cliente adjuntó una imagen pero PHP la descartó por límite del
        // servidor (upload_max_filesize), avisamos claro en vez del error de XOR.
        if (!$hasImage
            && isset($_FILES['image']['error'])
            && !in_array($_FILES['image']['error'], [UPLOAD_ERR_OK, UPLOAD_ERR_NO_FILE], true)) {
            return response()->json([
                'ok'      => false,
                'message' => 'La imagen no se subió completa o es demasiado grande. Redúcela e inténtalo de nuevo.',
            ], 413);
        }

        if ($hasText === $hasImage) {
            return response()->json([
                'ok'      => false,
                'message' => 'Envía exactamente uno: texto o imagen (no ambos, ni ninguno).',
            ], 422);
        }

        // Descripción de la referencia para trazabilidad de la prequote (caso E).
        $referenciaDesc = $hasText ? $text : 'Referencia por imagen';

        // ── 2) Resolver / bootstrapear la prequote ──────────────────────────────
        $requerimiento = \Modules\Comercial\Models\Requerimiento::findOrFail($requerimientoId);

        $bootstrapped = false;
        $prequote = $this->resolveEditablePreQuote($request, $requerimiento, $referenciaDesc, $bootstrapped);
        if ($prequote instanceof \Illuminate\Http\JsonResponse) {
            return $prequote; // 422 propagado (no pertenece / no editable / falta prequote_id)
        }

        // ── 3) Persistir la imagen ANTES de interpretar (el motor la lee de disco) ─
        $imagePath = null;
        $imageMime = null;
        if ($hasImage) {
            $stored = $this->validateAndStoreImage($request, 'item-references', $prequote->id);
            if (empty($stored['ok'])) {
                $this->cleanupFailedInterpret($bootstrapped ? $prequote : null, null);
                return response()->json([
                    'ok'      => false,
                    'message' => isset($stored['message']) ? $stored['message'] : 'Imagen inválida.',
                    'errors'  => isset($stored['errors']) ? $stored['errors'] : null,
                ], isset($stored['_status']) ? $stored['_status'] : 422);
            }
            // El motor multimodal descarta EN SILENCIO imágenes > 5 MB (y la IA
            // "inventaría" el producto sin verla). Cortamos antes de gastar el LLM.
            if ((int) $stored['size_bytes'] > 5 * 1024 * 1024) {
                $this->cleanupFailedInterpret($bootstrapped ? $prequote : null, $stored['stored_path']);
                return response()->json([
                    'ok'      => false,
                    'message' => 'La imagen supera los 5 MB permitidos para interpretarla. Redúcela e inténtalo de nuevo.',
                ], 413);
            }
            $imagePath = $stored['stored_path'];
            $imageMime = $stored['mime_type'];
        }

        try {
            // ── 4) Insumo para el motor + imagen a NIVEL ÍTEM (no toca la prequote) ─
            $promptText = $hasText
                ? $text
                : 'Interpreta el producto de la imagen adjunta y genera 1 ítem cotizable, '
                . 'infiriendo material, técnica de impresión y empaque. '
                . 'Si la imagen sugiere una cantidad, indícala; si no, deja la cantidad en 1.';

            $context = [
                'requerimiento_id'    => $requerimiento->id,
                'empresa_id'          => $prequote->empresa_id,
                'contacto_id'         => $prequote->contacto_id,
                'categoria_productos' => $requerimiento->categoria_productos,
                'user_id'             => $request->user() ? $request->user()->id : null,
            ];
            if ($imagePath) {
                // Transitorio SOLO para esta interpretación; NO se persiste en la
                // prequote (reference_image_path). La imagen es del ítem (decisión B).
                $context['reference_image_path'] = $imagePath;
                $context['reference_image_mime'] = $imageMime;
            }

            // ── 5) Interpretar ──────────────────────────────────────────────────
            $interpretation = $this->aiEngine->interpretRequirement($promptText, $context);
            $items = isset($interpretation['items']) ? $interpretation['items'] : [];

            // ── 6) Sin ítems / fallback → error claro, sin crear basura ─────────
            if (!empty($interpretation['_fallback']) || count($items) === 0) {
                $this->cleanupFailedInterpret($bootstrapped ? $prequote : null, $imagePath);
                return response()->json([
                    'ok'      => false,
                    'message' => 'No se pudo interpretar la referencia. Prueba con más detalle o una imagen más clara.',
                ], 422);
            }

            // ── 7) Tomar solo el primero (decisión D) + warning si hay varios ───
            $itemData = $items[0];
            if (empty($itemData['product_name']) || $itemData['product_name'] === 'Producto sin nombre') {
                $this->cleanupFailedInterpret($bootstrapped ? $prequote : null, $imagePath);
                return response()->json([
                    'ok'      => false,
                    'message' => 'No se pudo identificar el producto de la referencia. Agrega más detalle.',
                ], 422);
            }

            $warning = null;
            if (count($items) > 1) {
                $warning = 'La referencia menciona varios productos; se agregó solo el primero ("'
                    . $itemData['product_name'] . '"). Agrega los demás con nuevas referencias.';
            }

            // ── 8) Cantidad (decisión C: 1 si la IA no la indicó) + append ──────
            if (!isset($itemData['quantity']) || (int) $itemData['quantity'] <= 0) {
                $itemData['quantity'] = 1;
            }
            // item_order = max(existentes) + 1 (el 1 que numera el motor colisionaría).
            $itemData['item_order'] = (int) $prequote->items()->max('item_order') + 1;

            // ── 9-10) Crear el ítem (APPEND) + imagen + recalcular + historial en
            //          UNA transacción del tenant: si algo falla, se revierte todo
            //          (no queda ítem con imagen rota ni historial huérfano). El
            //          catch limpia la imagen y, si se bootstrapeó, la prequote.
            $item = null;
            $enriched = null;
            \Illuminate\Support\Facades\DB::connection('tenant')->transaction(function () use (
                &$item, &$enriched, $prequote, $itemData, $imagePath, $hasImage, $request
            ) {
                $item = $this->orchestrator->createPreQuoteItem($prequote, $itemData);
                if ($imagePath) {
                    $item->update(['product_reference_image' => $imagePath]);
                }

                $this->calcEngine->recalculate($prequote);

                \Modules\Marketing\Models\PreQuoteHistory::log(
                    $prequote->id,
                    $prequote->version,
                    \Modules\Marketing\Models\PreQuoteHistory::ACTION_ITEM_ADDED,
                    [
                        'item_id'      => $item->id,
                        'product_name' => $item->product_name,
                        'quantity'     => $item->quantity,
                        'source'       => $hasImage ? 'referencia:imagen' : 'referencia:texto',
                    ],
                    $request->user() ? $request->user()->id : null
                );

                $enriched = $this->enrichItemsWithMinMargin(
                    $prequote->fresh()->load('items', 'items.catalogItem')
                );
            });

            return response()->json([
                'ok'       => true,
                'item_id'  => $item->id,
                'warning'  => $warning,
                'item'     => $item->fresh(),
                'prequote' => $enriched,
            ]);

        } catch (\Throwable $e) {
            $this->cleanupFailedInterpret($bootstrapped ? $prequote : null, $imagePath);
            \Illuminate\Support\Facades\Log::error('QuotationPreQuoteController::interpretItem — error', [
                'requerimiento_id' => $requerimientoId,
                'prequote_id'      => isset($prequote->id) ? $prequote->id : null,
                'error'            => $e->getMessage(),
            ]);
            return response()->json([
                'ok'      => false,
                'message' => 'Error interpretando la referencia: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Resolver la PreQuote editable a la que agregar el ítem, o bootstrapear una
     * vacía (caso "sin pre-cotización"). Devuelve JsonResponse(422) ante error.
     *
     * Regla (para no escribir en una cotización que el usuario no está viendo):
     *  - con `prequote_id` → validar pertenencia + editabilidad;
     *  - sin `prequote_id` pero el requerimiento YA tiene alguna prequote → 422 (falta el id);
     *  - sin ninguna prequote → crear una vacía en borrador ($bootstrapped = true).
     *
     * @param  bool  $bootstrapped  (out) true si se creó una prequote nueva.
     * @return PreQuote|\Illuminate\Http\JsonResponse
     */
    private function resolveEditablePreQuote(Request $request, $requerimiento, $referenciaDesc, &$bootstrapped)
    {
        $bootstrapped = false;

        if ($request->filled('prequote_id')) {
            $pq = PreQuote::where('id', $request->input('prequote_id'))
                ->where('requerimiento_id', $requerimiento->id)
                ->first();
            if (!$pq) {
                return response()->json([
                    'ok'      => false,
                    'message' => 'La precotización indicada no pertenece a este requerimiento.',
                ], 422);
            }
            if (!$pq->isEditable()) {
                return response()->json([
                    'ok'      => false,
                    'message' => 'La precotización no es editable en estado: ' . $pq->estado,
                ], 422);
            }
            return $pq;
        }

        // Sin prequote_id: si ya existe alguna prequote del requerimiento, exigir el id.
        if (PreQuote::where('requerimiento_id', $requerimiento->id)->exists()) {
            return response()->json([
                'ok'      => false,
                'message' => 'Indica la precotización (prequote_id) a la cual agregar el ítem.',
            ], 422);
        }

        // Caso "sin pre-cotización": bootstrapear una vacía en borrador.
        $bootstrapped = true;
        return PreQuote::create([
            'requerimiento_id'     => $requerimiento->id,
            'empresa_id'           => $requerimiento->empresa_id,
            'contacto_id'          => $requerimiento->contacto_id,
            'version'              => 1,
            'estado'               => PreQuote::ESTADO_BORRADOR,
            'titulo'               => $requerimiento->titulo ?: ('Precotización req. #' . $requerimiento->id),
            'descripcion_original' => $referenciaDesc,   // trazabilidad + permite reinterpret futuro
            'texto_interpretado'   => null,
            'user_id'              => $request->user() ? $request->user()->id : null,
            'ai_provider'          => config('marketing.chatbot.ai_provider', 'claude'),
            'ai_model'             => config('marketing.chatbot.ai.' . config('marketing.chatbot.ai_provider', 'claude') . '.model'),
            'subtotal'             => 0,
            'total_igv'            => 0,
            'total'                => 0,
        ]);
    }

    /**
     * Limpieza ante un alta por referencia que falló DESPUÉS de haber persistido
     * cosas: borra la imagen guardada y, si la prequote se creó en ESTA request
     * (bootstrap), la elimina para no dejar una precotización vacía huérfana.
     *
     * @param  PreQuote|null  $bootstrappedPrequote  la prequote a borrar, o null.
     * @param  string|null    $imagePath             el path de la imagen a borrar, o null.
     */
    private function cleanupFailedInterpret($bootstrappedPrequote, $imagePath)
    {
        if ($imagePath) {
            try {
                \Illuminate\Support\Facades\Storage::disk('local')->delete($imagePath);
            } catch (\Exception $e) { /* best-effort */ }
        }
        if ($bootstrappedPrequote) {
            try {
                $bootstrappedPrequote->history()->delete();      // no dejar historial huérfano
                $bootstrappedPrequote->items()->forceDelete();
                $bootstrappedPrequote->forceDelete();
            } catch (\Exception $e) { /* best-effort */ }
        }
    }

    /**
     * Ver historial de cambios de una precotización.
     *
     * GET /api/marketing/quotation/prequotes/{id}/history
     */
    public function history($id)
    {
        $prequote = PreQuote::findOrFail($id);

        $history = $prequote->history()
            ->with('user:id,name')
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'ok'      => true,
            'history' => $history,
        ]);
    }

    /**
     * Convertir precotización validada a Quotation formal del ERP.
     *
     * POST /api/marketing/quotation/prequotes/{id}/convert
     *
     * Solo se permite si estado = 'validada' o 'aprobada'.
     * Crea un Quotation + QuotationItem[] en el sistema de Preventa.
     */
    public function convert(Request $request, $id)
    {
        $prequote = PreQuote::with('items', 'empresa')->findOrFail($id);

        // Validar estado
        $allowedStates = [PreQuote::ESTADO_VALIDADA, PreQuote::ESTADO_APROBADA];
        if (!in_array($prequote->estado, $allowedStates)) {
            return response()->json([
                'ok'      => false,
                'message' => 'Solo se pueden convertir precotizaciones validadas o aprobadas. Estado actual: ' . $prequote->estado,
            ], 422);
        }

        // Verificar que no fue convertida ya
        if ($prequote->isConverted()) {
            return response()->json([
                'ok'      => false,
                'message' => 'Esta precotización ya fue convertida a Quotation ID: ' . $prequote->quotation_id,
            ], 422);
        }

        // No se puede convertir una pre-cotización vacía (generaría una
        // Quotation sin ítems y total 0).
        if ($prequote->items->isEmpty()) {
            return response()->json([
                'ok'      => false,
                'message' => 'La pre-cotización no tiene productos. Agrega al menos uno antes de generar la cotización formal.',
            ], 422);
        }

        try {
            \Illuminate\Support\Facades\DB::beginTransaction();

            // RF — Productos NO registrados en el catálogo: la falta de asociación
            // NO bloquea la conversión. Cada ítem sin `item_id` se crea
            // automáticamente en la tabla `items` de Facturador (con los datos ya
            // ingresados + defaults SUNAT) y se asocia, dentro de la transacción
            // (si el convert falla luego, estos ítems se revierten con el rollback).
            foreach ($prequote->items as $it) {
                // Agosto 2026 (punto 2) — kits y servicio de armado: la ejecutiva
                // los marcó como "no es un producto del catálogo". No se les crea
                // un ítem propio; más abajo, al armar las líneas de la cotización,
                // se los apunta al genérico compartido.
                if ($it->excluir_catalogo) {
                    continue;
                }

                if (empty($it->item_id)) {
                    $newItemId = $this->createAdHocCatalogItem($it);
                    if (!$newItemId) {
                        \Illuminate\Support\Facades\DB::rollBack();
                        return response()->json([
                            'ok'      => false,
                            'message' => 'No se pudo registrar en el catálogo el producto "'
                                       . ($it->product_name ?: 'sin nombre') . '". Intenta nuevamente.',
                        ], 500);
                    }
                    $it->item_id = $newItemId;
                    $it->save();
                }
            }

            // Buscar customer_id desde la empresa del requerimiento
            $customerId = null;
            $customerData = [];

            // Primero intentar desde la empresa asociada al requerimiento/precotización
            $empresa = $prequote->empresa;
            if (!$empresa && $prequote->requerimiento) {
                $empresa = $prequote->requerimiento->empresa;
            }

            $errorCliente = null;

            if ($empresa) {
                $person = \App\Models\Tenant\Person::where('number', $empresa->ruc)
                    ->where('type', 'customers')
                    ->first();

                // Autorreparación: si la empresa todavía no tiene su cliente
                // asociado (caso típico de las empresas dadas de alta antes de
                // que el sistema lo creara solo), se genera en el momento con el
                // mismo listener que usa el módulo Comercial al guardar una
                // empresa. Así el usuario no queda bloqueado.
                if (!$person && trim((string) $empresa->ruc) !== '') {
                    try {
                        $person = app(\App\Listeners\Comercial\SyncEmpresaToPersons::class)->syncOne($empresa);
                    } catch (\Exception $e) {
                        $errorCliente = $e->getMessage();
                        \Illuminate\Support\Facades\Log::error(
                            'Convertir a Cotización Formal: no se pudo crear el cliente de la empresa',
                            [
                                'empresa_id' => $empresa->id,
                                'ruc'        => $empresa->ruc,
                                'error'      => $e->getMessage(),
                            ]
                        );
                    }
                }

                if ($person) {
                    $customerId = $person->id;
                    $customerData = [
                        'name'            => $person->name,
                        'number'          => $person->number,
                        'address'         => isset($person->address) ? $person->address : '',
                        'email'           => isset($person->email) ? $person->email : '',
                        'identity_document_type_id' => isset($person->identity_document_type_id) ? $person->identity_document_type_id : '6',
                    ];
                }
            }

            // Si no se encontró cliente, NO continuar. El mensaje distingue el
            // motivo real para que la ejecutiva sepa qué corregir.
            if (!$customerId) {
                \Illuminate\Support\Facades\DB::rollBack();

                if (!$empresa) {
                    $mensaje = 'El requerimiento no tiene una empresa asociada. Asigne la empresa en la pestaña Datos y vuelva a intentar.';
                } elseif (trim((string) $empresa->ruc) === '') {
                    $mensaje = 'La empresa "' . $empresa->razon_social . '" no tiene RUC registrado. Complete el RUC en Comercial → Empresas y vuelva a intentar.';
                } elseif ($errorCliente) {
                    $mensaje = 'No se pudo crear automáticamente el cliente de la empresa "' . $empresa->razon_social . '" (RUC ' . $empresa->ruc . '): ' . $errorCliente;
                } else {
                    $mensaje = 'No se encontró ni se pudo crear el cliente de la empresa "' . $empresa->razon_social . '" (RUC ' . $empresa->ruc . '). Abra la empresa en Comercial → Empresas, guárdela y vuelva a intentar.';
                }

                return response()->json([
                    'ok'      => false,
                    'message' => $mensaje,
                ], 422);
            }

            // Crear Quotation formal
            $userId = $request->user() ? $request->user()->id : $prequote->user_id;

            // Obtener soap_type de la empresa
            $company = \App\Models\Tenant\Company::first();
            $soapTypeId = $company ? $company->soap_type_id : '01';

            // Obtener establishment
            $establishment = \App\Models\Tenant\Establishment::find(1);
            $establishmentData = $establishment ? [
                'description'   => $establishment->description,
                'country_id'    => $establishment->country_id,
                'department_id' => $establishment->department_id,
                'province_id'   => $establishment->province_id,
                'district_id'   => $establishment->district_id,
                'address'       => $establishment->address,
                'email'         => $establishment->email,
                'telephone'     => $establishment->telephone,
            ] : [];

            $quotation = \App\Models\Tenant\Quotation::create([
                'user_id'              => $userId,
                'external_id'          => \Illuminate\Support\Str::uuid()->toString(),
                'establishment_id'     => 1,
                'establishment'        => json_encode($establishmentData),
                'soap_type_id'         => $soapTypeId,
                'state_type_id'        => '01',
                'prefix'               => 'COT',
                'date_of_issue'        => now()->format('Y-m-d'),
                'time_of_issue'        => now()->format('H:i:s'),
                'customer_id'          => $customerId,
                'customer'             => json_encode($customerData),
                'currency_type_id'     => 'PEN',
                'exchange_rate_sale'    => 1,
                'total_prepayment'     => 0,
                'total_charge'         => 0,
                'total_discount'       => 0,
                'total_exportation'    => 0,
                'total_free'           => 0,
                'total_taxed'          => $prequote->subtotal,
                'total_unaffected'     => 0,
                'total_exonerated'     => 0,
                'total_igv'            => $prequote->total_igv,
                'total_igv_free'       => 0,
                'total_base_isc'       => 0,
                'total_isc'            => 0,
                'total_base_other_taxes' => 0,
                'total_other_taxes'    => 0,
                'total_taxes'          => $prequote->total_igv,
                'total_value'          => $prequote->subtotal,
                'total'                => $prequote->total,
                'subtotal'             => $prequote->subtotal,
                'changed'              => 0,
                'description'          => 'Generada desde COM-02 #' . $prequote->id,
                'seller_id'            => $userId,
            ]);

            // Crear QuotationItems
            //
            // Agosto 2026 (punto 2) — los ítems marcados como kit o servicio no
            // tienen producto propio en el catálogo, pero `quotation_items.item_id`
            // es NOT NULL con llave foránea contra `items`: toda línea necesita uno
            // detrás. Se los apunta al genérico compartido, que se resuelve una
            // sola vez y solo si hace falta.
            //
            // Esto NO altera lo que ve el cliente: la línea guarda su propia copia
            // del nombre —el JSON de `item` y `name_product_pdf`, ambos armados
            // desde `product_name` de la pre-cotización—, así que el kit se imprime
            // con su nombre real y no con el del genérico.
            $genericItemId = null;

            foreach ($prequote->items as $preItem) {
                $catalogItemId = $preItem->item_id;

                if (empty($catalogItemId)) {
                    if ($genericItemId === null) {
                        $genericItemId = $this->getOrCreateGenericCatalogItem();
                    }
                    $catalogItemId = $genericItemId;
                }

                $quotation->items()->create([
                    'item_id'    => $catalogItemId,
                    'item'       => json_encode([
                        'id'          => $preItem->item_id,
                        'description' => $preItem->product_name,
                        'name'        => $preItem->product_name,
                    ]),
                    'quantity'   => $preItem->quantity,
                    'unit_value' => $preItem->valor_unitario,
                    'unit_price' => $preItem->valor_unitario * (1 + 0.18),
                    // Campos NOT NULL del modelo de Facturador que faltaban y
                    // hacían fallar el insert (SQLSTATE 1364):
                    'affectation_igv_type_id' => '10',  // SUNAT cat.07: Gravado - Operación Onerosa
                    'price_type_id'           => '01',  // SUNAT cat.16: Precio unitario (incluye IGV)
                    'total_base_igv'   => $preItem->base_igv,
                    'percentage_igv'   => 18,
                    'total_igv'        => $preItem->igv,
                    'total_taxes'      => $preItem->igv,  // único impuesto del ítem = IGV
                    'total_value'      => $preItem->valor_total,
                    'total'            => $preItem->total_item,
                    'name_product_pdf' => $preItem->product_name,
                ]);
            }

            // Actualizar precotización
            $prequote->update([
                'quotation_id' => $quotation->id,
                'estado'       => PreQuote::ESTADO_APROBADA,
            ]);

            // Si tiene requerimiento, actualizar CotizacionRequerimiento
            if ($prequote->requerimiento_id) {
                \Modules\Comercial\Models\CotizacionRequerimiento::create([
                    'requerimiento_id' => $prequote->requerimiento_id,
                    'version'          => (string) $prequote->version,
                    'monto'            => $prequote->total,
                    'estado'           => 'Aprobada',
                    'observaciones'    => 'Generada automáticamente desde COM-02',
                    'usuario_id'       => $userId,
                ]);
            }

            // Registrar en historial
            \Modules\Marketing\Models\PreQuoteHistory::log(
                $prequote->id,
                $prequote->version,
                \Modules\Marketing\Models\PreQuoteHistory::ACTION_CONVERTED,
                ['quotation_id' => $quotation->id],
                $userId
            );

            \Illuminate\Support\Facades\DB::commit();

            return response()->json([
                'ok'           => true,
                'message'      => 'Precotización convertida a Quotation exitosamente.',
                'quotation_id' => $quotation->id,
                'prequote'     => $prequote->fresh(),
            ]);

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\DB::rollBack();

            return response()->json([
                'ok'      => false,
                'message' => 'Error al convertir: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Producto genérico del catálogo al que se apuntan los kits y servicios.
     *
     * Agosto 2026 (punto 2). Un kit son varios productos agrupados solo para esa
     * cotización, y el armado es un servicio puntual: ninguno de los dos es un
     * producto del catálogo. Pero `quotation_items.item_id` es NOT NULL con llave
     * foránea contra `items`, así que la línea igual necesita apuntar a algo.
     *
     * En vez de crear un ítem por cada kit —que es justo lo que ensucia el
     * catálogo—, todos comparten este único registro. Se crea la primera vez que
     * se necesita y se reutiliza siempre; el catálogo crece un solo registro en
     * total, no uno por cotización.
     *
     * Queda inactivo (`active = 0`) para que no aparezca entre los productos
     * seleccionables, y con un `internal_id` reconocible para poder auditarlo.
     *
     * @return int  id del ítem genérico.
     * @throws \RuntimeException  si no se pudo obtener ni crear.
     */
    private function getOrCreateGenericCatalogItem()
    {
        $internalId = 'COM02-GENERICO';

        $existente = \Illuminate\Support\Facades\DB::connection('tenant')
            ->table('items')
            ->where('internal_id', $internalId)
            ->value('id');

        if ($existente) {
            return (int) $existente;
        }

        try {
            $now = now();

            // INSERT directo, por el mismo motivo que en createAdHocCatalogItem:
            // los observers de Facturador asumen contexto de inventario y de
            // usuario autenticado, que acá no aplica.
            $itemId = \Illuminate\Support\Facades\DB::connection('tenant')->table('items')->insertGetId([
                'item_type_id'                     => '01',
                'unit_type_id'                     => 'NIU',
                'currency_type_id'                 => 'PEN',
                'sale_affectation_igv_type_id'     => '10',
                'purchase_affectation_igv_type_id' => '10',
                'sale_unit_price'                  => 0,
                'name'                             => 'Ítem de cotización (kits y servicios)',
                'description'                      => 'Ítem de cotización (kits y servicios)',
                'internal_id'                      => $internalId,
                'active'                           => 0,
                'stock'                            => 0,
                'created_at'                       => $now,
                'updated_at'                       => $now,
            ]);

            \Illuminate\Support\Facades\Log::info('convert: creado el ítem genérico de kits/servicios', [
                'item_id' => $itemId,
            ]);

            return (int) $itemId;
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('convert: falló crear el ítem genérico', [
                'error' => $e->getMessage(),
            ]);

            throw new \RuntimeException(
                'No se pudo preparar el ítem de catálogo para los kits y servicios de esta cotización.'
            );
        }
    }

    /**
     * Crear un ítem "ad-hoc" en el catálogo de Facturador (tabla `items`) a
     * partir de un ítem de pre-cotización que no matcheó con el catálogo, para
     * poder asociarlo y convertir sin bloquear (RF: productos no registrados).
     *
     * Es un INSERT en la tabla existente `items`: NO toca el esquema core de
     * Facturador. Solo llena los 6 campos NOT NULL sin default con defaults
     * SUNAT + los datos ya ingresados. Se marca con internal_id "COM02-ADHOC-…"
     * para poder identificarlos/depurarlos luego.
     *
     * @param  \Modules\Marketing\Models\PreQuoteItem  $preItem
     * @return int|null  id del ítem creado, o null si falló.
     */
    private function createAdHocCatalogItem($preItem)
    {
        try {
            $nombre = $preItem->product_name ?: 'Producto sin nombre';
            $precio = (float) $preItem->valor_unitario > 0
                ? (float) $preItem->valor_unitario
                : (float) $preItem->costo_base;

            // COM-02 — si la ejecutiva escribió un "Cód. Artíc." manual, ese es
            // el código con el que el cliente vio el producto en la propuesta:
            // se usa como internal_id del ítem de catálogo, para que el código
            // de la propuesta y el del catálogo coincidan. Sin código manual se
            // mantiene la marca interna 'COM02-ADHOC-…'.
            // OJO: items.internal_id NO tiene índice único, así que un código
            // repetido no falla; queda registrado en el log para poder auditarlo.
            $codigoManual = trim((string) $preItem->codigo_articulo_manual);
            $internalId = $codigoManual !== ''
                ? $codigoManual
                : 'COM02-ADHOC-' . $preItem->prequote_id . '-' . $preItem->id;

            $now = now();

            // INSERT directo en `items` (sin pasar por Item::create). Los observers
            // de Facturador al crear un ítem asumen contexto de inventario y de
            // usuario autenticado: registran weighted_average_costs con el stock del
            // ítem y leen el establishment del usuario. Eso NO aplica a un producto
            // ad-hoc de cotización y falla fuera de ese contexto. El INSERT directo
            // crea el registro válido en el catálogo (satisface el FK, se ve en el
            // listado) sin esos efectos. Es la tabla core existente: NO se toca el
            // esquema de Facturador — solo se llenan sus campos.
            $itemId = \Illuminate\Support\Facades\DB::connection('tenant')->table('items')->insertGetId([
                'item_type_id'                     => '01',   // SUNAT cat.01: producto/bien
                'unit_type_id'                     => 'NIU',  // unidad (bienes)
                'currency_type_id'                 => 'PEN',
                'sale_affectation_igv_type_id'     => '10',   // SUNAT cat.07: gravado
                'purchase_affectation_igv_type_id' => '10',
                'sale_unit_price'                  => $precio,
                'name'                             => $nombre,
                // Agosto 2026 (punto 2) — al catálogo va SOLO el nombre.
                //
                // Antes acá iba `product_description`, que es el texto largo de
                // la propuesta (material, medidas, presentación, colores…). Esa
                // columna es VARCHAR(255), así que una descripción normal de
                // cotización la desbordaba y la conversión fallaba con "Data too
                // long for column 'description'".
                //
                // Además ese texto describe la propuesta, no el producto base:
                // el mismo artículo cotizado dos veces con distinta
                // personalización generaba descripciones de catálogo distintas y
                // ninguna servía como ficha del producto.
                'description'                      => $nombre,
                'internal_id'                      => $internalId,
                'active'                           => 1,
                'stock'                            => 0,
                'created_at'                       => $now,
                'updated_at'                       => $now,
            ]);

            \Illuminate\Support\Facades\Log::info('convert: ítem ad-hoc creado en catálogo', [
                'item_id'          => $itemId,
                'prequote_item_id' => $preItem->id,
                'name'             => $nombre,
                // Para auditar códigos repetidos: internal_id no es único.
                'internal_id'      => $internalId,
                'codigo_manual'    => $codigoManual !== '',
            ]);

            return $itemId;
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('convert: falló crear ítem ad-hoc en catálogo', [
                'prequote_item_id' => $preItem->id,
                'error'            => $e->getMessage(),
            ]);
            return null;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | E4: Sugerencia de Proveedores con IA
    |--------------------------------------------------------------------------
    */

    /**
     * Obtener sugerencias de proveedores para un ítem específico.
     *
     * La IA analiza el ítem + catálogo de proveedores del tenant + histórico
     * de compras, y retorna top-3 candidatos con costo estimado, tiempo y
     * nivel de confianza.
     *
     * GET /marketing/quotation/prequotes/{id}/items/{itemId}/supplier-suggestions
     */
    public function supplierSuggestions($id, $itemId)
    {
        $prequote = PreQuote::findOrFail($id);
        $item = $prequote->items()->where('id', $itemId)->firstOrFail();

        try {
            $result = $this->aiEngine->suggestSuppliersForItem($item);

            return response()->json([
                'ok'          => true,
                'item'        => $item,
                'summary'     => isset($result['summary']) ? $result['summary'] : '',
                'rationale'   => isset($result['best_match_rationale']) ? $result['best_match_rationale'] : '',
                'suggestions' => isset($result['suggestions']) ? $result['suggestions'] : [],
                'ai_fallback' => isset($result['_fallback']) && $result['_fallback'],
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('supplierSuggestions error', [
                'prequote_id' => $id,
                'item_id'     => $itemId,
                'error'       => $e->getMessage(),
            ]);
            return response()->json([
                'ok'      => false,
                'message' => 'Error obteniendo sugerencias: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Aplicar un proveedor sugerido al ítem: actualiza supplier_id,
     * supplier_name, costo_base, tiempo_estimado_dias y recalcula totales.
     *
     * POST /marketing/quotation/prequotes/{id}/items/{itemId}/apply-supplier
     *
     * Body: { supplier_id, supplier_name, costo_unitario_estimado, tiempo_estimado_dias }
     */
    public function applySupplier(Request $request, $id, $itemId)
    {
        $prequote = PreQuote::findOrFail($id);

        if (!$prequote->isEditable()) {
            return response()->json([
                'ok'      => false,
                'message' => 'La precotización no es editable en estado: ' . $prequote->estado,
            ], 422);
        }

        $v = Validator::make($request->all(), [
            'supplier_id'             => ['required', 'integer'],
            'supplier_name'           => ['required', 'string', 'max:191'],
            'costo_unitario_estimado' => ['required', 'numeric', 'min:0'],
            // Texto libre (ej. "Mié 02 Abril *", "1 día útil"). Ver COM08.
            'tiempo_estimado_dias'    => ['nullable', 'string', 'max:150'],
        ]);

        if ($v->fails()) {
            return response()->json(['ok' => false, 'errors' => $v->errors()], 422);
        }

        $item = $prequote->items()->where('id', $itemId)->firstOrFail();

        $updates = [
            'supplier_id'          => (int) $request->input('supplier_id'),
            'supplier_name'        => $request->input('supplier_name'),
            'costo_base'           => (float) $request->input('costo_unitario_estimado'),
            'tiempo_estimado_dias' => $request->input('tiempo_estimado_dias'),
            'is_inferred'          => false,
        ];

        // COM-02 — mismo criterio que updateItem(): al CAMBIAR de proveedor, el
        // "Cód. Artíc." manual (que lleva sus iniciales) deja de ser válido y el
        // ítem vuelve a heredar el del catálogo. Al asignar el PRIMER proveedor
        // se conserva lo que la ejecutiva ya había escrito.
        if ($item->supplier_id !== null
            && $updates['supplier_id'] !== (int) $item->supplier_id
            && $this->tieneColumnaCodigoArticulo()) {
            $updates['codigo_articulo_manual'] = null;
        }

        $item->update($updates);

        // Recalcular toda la precotización
        $prequote = $this->calcEngine->recalculate($prequote);

        // Registrar en historial
        \Modules\Marketing\Models\PreQuoteHistory::log(
            $prequote->id,
            $prequote->version,
            \Modules\Marketing\Models\PreQuoteHistory::ACTION_ITEM_EDITED,
            [
                'item_id'     => $itemId,
                'action'      => 'supplier_applied_from_ai',
                'supplier_id' => $updates['supplier_id'],
                'costo_base'  => $updates['costo_base'],
            ],
            $request->user() ? $request->user()->id : null
        );

        return response()->json([
            'ok'       => true,
            'prequote' => $this->enrichItemsWithMinMargin($prequote->fresh()->load('items')),
            'item'     => $item->fresh(),
            'message'  => 'Proveedor aplicado. Totales recalculados.',
        ]);
    }

}
