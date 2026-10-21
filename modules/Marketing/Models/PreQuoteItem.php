<?php

namespace Modules\Marketing\Models;

use App\Models\Tenant\ModelTenant;

/**
 * PreQuoteItem — Ítem de precotización (COM-02)
 *
 * Unidad funcional mínima. Cada ítem representa un producto solicitado
 * con su propia lógica de cálculo, inferencia y proveedor.
 */
class PreQuoteItem extends ModelTenant
{
    protected $table = 'quotation_prequote_items';

    protected $fillable = [
        'prequote_id',
        'item_order',
        'item_id',
        // Agosto 2026 (punto 2) — kits y servicio de armado: no se crea un
        // producto propio en el catálogo para ellos. Ver la migración
        // 2026_08_12_000001 para el motivo y para por qué igual se apunta a un
        // producto genérico en lugar de dejar item_id vacío.
        'excluir_catalogo',
        // Tipo de línea (columna sin nombre en el Tab): product|service|comision.
        // Lo usa el combo de la tabla; el backend ajusta item_id/review según el tipo.
        'tipo_linea',
        // COM-02 — override manual del "Cód. Artíc." de la propuesta.
        // NULL = el ítem hereda el código del catálogo (items.internal_id).
        // Sin cast a propósito: un cast 'string' volvería el NULL en '' y se
        // perdería la distinción "sin override" vs "override vacío".
        'codigo_articulo_manual',
        'product_name',
        'product_description',
        'product_reference_image',
        'product_match_confidence',
        'needs_product_review',
        'product_suggestions',
        'quantity',
        'unit_type_id',
        'material',
        'medida',
        'tecnica_impresion',
        'cantidad_colores',
        'cantidad_lados',
        'cantidad_millar',
        'empaque',
        'personalizacion',
        'supplier_id',
        'supplier_name',
        'costo_base',
        'costos_adicionales',
        'costo_muestra',
        'numero_muestras',
        // Agosto 2026 (punto 4): precio unitario de la muestra cuando difiere del
        // costo base. NULL = usa el costo base, como siempre. Sin cast decimal a
        // propósito: el cast volvería el NULL en "0.00" y se perdería la
        // distinción entre "no definido" y "muestras gratis".
        'precio_muestra',
        'costo_movilidad',
        'costo_impresion',
        'costo_empaque',
        'impresion_por_unidad',
        'empaque_por_unidad',
        'margen_porcentaje',
        'sctr_porcentaje',
        'valor_unitario',
        'valor_total',
        'base_igv',
        'igv',
        'total_item',
        'tiempo_estimado_dias',
        // Agosto 2026 (punto 9): plazo OFRECIDO AL CLIENTE, distinto del de
        // producción de arriba. Vacío = todavía no tiene uno propio y se muestra
        // el de producción como heredado. Ver tiempoEntregaEfectivo().
        'tiempo_entrega',
        'datos_explicitos',
        'datos_inferidos',
        'observaciones',
        'is_inferred',
    ];

    /**
     * Atributos virtuales agregados a la serialización JSON.
     *
     * has_image: el frontend (columna "Foto") lo usa para decidir si pinta la
     * miniatura. Es true si el ítem tiene imagen propia O si matcheó con un
     * producto del catálogo que tiene imagen — así los productos del catálogo
     * traen su foto automáticamente (la sirve QuotationImageController::showItemImage
     * con su fallback), sin duplicar archivos ni importar qué tipo de match fue.
     */
    protected $appends = [
        'has_image',
        'ganancia_absoluta',
        // COM-02 — "Cód. Artíc.": el preview editable y el PDF consumen
        // `codigo_articulo` (el efectivo). `codigo_articulo_catalogo` se expone
        // aparte para que la UI pueda mostrar, como placeholder, qué código
        // queda si se vacía el override. Van como $appends (y no inyectados en
        // un controlador) para que salgan por TODOS los endpoints que devuelven
        // ítems, sin depender de que la relación `catalogItem` esté cargada.
        'codigo_articulo_catalogo',
        'codigo_articulo',
    ];

    /**
     * Cache de `items.internal_id` por ítem del catálogo.
     *
     * Evita el N+1 del accessor al serializar 20 ítems: el mismo producto del
     * catálogo se consulta una sola vez. La clave incluye la BD del tenant
     * activo porque dos tenants distintos comparten ids de `items`.
     *
     * ⚠️ Es estático: vive lo que dura el PROCESO, no el request. En PHP-FPM
     * (1 request = 1 tenant) es seguro. En procesos largos que cambien de
     * tenant, o entre tests de una misma corrida, usar flushInternalIdCache().
     *
     * @var array
     */
    private static $internalIdCache = [];

    /**
     * Vaciar el cache de códigos del catálogo.
     *
     * Pensado para los tests (que comparten proceso) y para cualquier proceso
     * de larga vida que itere tenants.
     *
     * @return void
     */
    public static function flushInternalIdCache()
    {
        self::$internalIdCache = [];
    }

    protected $casts = [
        'excluir_catalogo'         => 'boolean',
        'quantity'                 => 'integer',
        'cantidad_colores'         => 'integer',
        'cantidad_lados'           => 'integer',
        // COM-02 — factor "Cantidad por Millar" del Costo Total de Impresión.
        'cantidad_millar'          => 'decimal:2',
        'costo_base'               => 'decimal:2',
        'costo_muestra'            => 'decimal:2',
        'numero_muestras'          => 'integer',
        'costo_movilidad'          => 'decimal:2',
        'costo_impresion'          => 'decimal:2',
        'costo_empaque'            => 'decimal:2',
        'impresion_por_unidad'     => 'boolean',
        'empaque_por_unidad'       => 'boolean',
        'margen_porcentaje'        => 'decimal:2',
        // COM-02 — SCTR unitario (override por ítem). NULL = hereda el de la
        // cotización. El cast de Laravel preserva el NULL (no lo vuelve 0).
        'sctr_porcentaje'          => 'decimal:2',
        'valor_unitario'           => 'decimal:2',
        'valor_total'              => 'decimal:2',
        'base_igv'                 => 'decimal:2',
        'igv'                      => 'decimal:2',
        'total_item'               => 'decimal:2',
        // tiempo_estimado_dias es TEXTO LIBRE (ej. "Mié 02 Abril *", "1 día útil",
        // "15 días útiles"). NO se castea a entero. Ver COM08.
        //
        // Agosto 2026 (punto 9): pasó a significar el tiempo de PRODUCCIÓN del
        // proveedor. El plazo que se le comunica al cliente vive ahora en
        // `tiempo_entrega`, que también es texto libre y tampoco se castea.
        'is_inferred'              => 'boolean',
        'product_match_confidence' => 'decimal:2',
        'needs_product_review'     => 'boolean',
        'product_suggestions'      => 'array',
        // El JSON de trazabilidad de la IA queda deserializado al exponerse en la API.
        // La UI usa estos arrays para mostrar el badge "✨ Inferido por IA" en los
        // campos del ítem que la IA infirió (material, medida, técnica, empaque, etc.).
        'datos_explicitos'         => 'array',
        'datos_inferidos'          => 'array',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relaciones
    |--------------------------------------------------------------------------
    */

    public function prequote()
    {
        return $this->belongsTo(PreQuote::class, 'prequote_id');
    }

    public function catalogItem()
    {
        return $this->belongsTo(\App\Models\Tenant\Item::class, 'item_id');
    }

    public function supplier()
    {
        return $this->belongsTo(\App\Models\Tenant\Person::class, 'supplier_id');
    }

    /**
     * Comparativa de proveedores del ítem: N cotizaciones por ítem; el
     * proveedor elegido define supplier_id/name + costo_base de la línea.
     *
     * [NUEVO FLUJO 2026-10-21] Apuntaba a `quotation_prequote_item_suppliers`
     * (tabla que no existe). Ahora usa la tabla vigente
     * `quotation_items_supplier` (misma relación que itemSuppliers()).
     */
    public function supplierQuotes()
    {
        return $this->itemSuppliers();
    }

    /**
     * [NUEVO FLUJO 2026-10-21] Apuntaba a la tabla vieja (inexistente).
     * Ahora es el proveedor ELEGIDO de `quotation_items_supplier`.
     */
    public function winningQuote()
    {
        return $this->winningItemSupplier();
    }

    /**
     * [NUEVO FLUJO 2026-10-20] Comparativa de proveedores con la tabla nueva
     * `quotation_items_supplier` (solución momentánea).
     *
     * A diferencia de supplierQuotes() (tabla vieja), esta relación:
     *   - Usa `quotation_items_id` como FK
     *   - Origen siempre 'manual' (sin match con catálogo)
     *   - El front la usa al abrir el acordeón del ítem para listar proveedores
     *
     * [NUEVO FLUJO 2026-10-21] Ya no hay columna `orden`: el orden es por id.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function itemSuppliers()
    {
        return $this->hasMany(QuotationItemSupplier::class, 'quotation_items_id')
            ->orderBy('id');
    }

    /**
     * [NUEVO FLUJO 2026-10-20] El proveedor elegido de la tabla nueva.
     * Define supplier_id/supplier_name/costo_base del ítem.
     *
     * [NUEVO FLUJO 2026-10-21] `es_ganador` → `seleccionado`.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function winningItemSupplier()
    {
        return $this->hasOne(QuotationItemSupplier::class, 'quotation_items_id')
            ->where('seleccionado', 1);
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    /**
     * Agosto 2026 (punto 9) · Plazo que se le comunica al CLIENTE.
     *
     * Devuelve el propio del ítem y, si todavía no tiene uno, el de producción
     * del proveedor como valor heredado. Es la única regla de herencia: la usan
     * el Preview y el PDF de la propuesta, así que la pantalla y el documento no
     * pueden mostrar cosas distintas.
     *
     * Que el heredado sea el de producción y no un vacío es deliberado: las
     * cotizaciones que ya existían siguen mostrando exactamente lo mismo que
     * antes de separar los campos, y el plazo propio aparece recién cuando
     * alguien lo escribe.
     *
     * @return string  '' si no hay ninguno de los dos
     */
    public function tiempoEntregaEfectivo()
    {
        $propio = trim((string) $this->tiempo_entrega);
        if ($propio !== '') {
            return $propio;
        }

        return trim((string) $this->tiempo_estimado_dias);
    }

    /**
     * Obtener costos adicionales como array.
     *
     * @return array  [['concepto' => '...', 'monto' => 0.00], ...]
     */
    public function getCostosAdicionalesArray()
    {
        if (empty($this->costos_adicionales)) {
            return [];
        }
        $decoded = json_decode($this->costos_adicionales, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Suma total de costos adicionales.
     *
     * @return float
     */
    public function getTotalCostosAdicionales()
    {
        $costos = $this->getCostosAdicionalesArray();
        $total = 0;
        foreach ($costos as $c) {
            $total += isset($c['monto']) ? (float) $c['monto'] : 0;
        }
        return $total;
    }

    /**
     * Obtener datos explícitos como array.
     *
     * @return array
     */
    public function getDatosExplicitosArray()
    {
        if (empty($this->datos_explicitos)) {
            return [];
        }
        $decoded = json_decode($this->datos_explicitos, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Obtener datos inferidos como array.
     *
     * @return array
     */
    public function getDatosInferidosArray()
    {
        if (empty($this->datos_inferidos)) {
            return [];
        }
        $decoded = json_decode($this->datos_inferidos, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Accessor del atributo virtual `has_image`.
     *
     * Indica si el ítem tiene una imagen que mostrar, ya sea:
     *   1. Imagen propia subida por la ejecutiva (product_reference_image), o
     *   2. Imagen heredada del catálogo: el ítem matcheó con un Item
     *      (item_id) que tiene una imagen real (no el centinela
     *      'imagen-no-disponible.jpg').
     *
     * El frontend usa este flag para pintar la miniatura; la imagen en sí la
     * sirve QuotationImageController::showItemImage, que resuelve el mismo
     * fallback al catálogo. No se duplican archivos.
     *
     * @return bool
     */
    public function getHasImageAttribute()
    {
        if (!empty($this->product_reference_image)) {
            return true;
        }

        if (!empty($this->item_id)) {
            try {
                // Consulta liviana de un solo campo (no dependemos de que la
                // relación catalogItem esté cargada con la columna image).
                $img = \App\Models\Tenant\Item::where('id', $this->item_id)->value('image');
                if (!empty($img) && $img !== 'imagen-no-disponible.jpg') {
                    return true;
                }
            } catch (\Exception $e) {
                // Catálogo no disponible — sin imagen.
            }
        }

        return false;
    }

    /**
     * Margen de ganancia del ítem en soles (derivado, solo lectura).
     *
     * R4 — mismo valor que QuotationCalculationEngine::computeItem expone como
     * `ganancia_absoluta`: la utilidad que aporta el markup del producto.
     *
     *   ganancia = Q × (costo_base × markup%) = Q × costo_base × (margen% / 100)
     *
     * Es la ganancia del producto por el markup; NO incluye los costos fijos
     * (muestra, movilidad, impresión, empaque) ni el SCTR. No se persiste ni
     * requiere migración: se recalcula al vuelo desde los campos del ítem, así
     * que siempre queda consistente con el costo_base y el margen actuales.
     *
     * @return float
     */
    public function getGananciaAbsolutaAttribute()
    {
        $q = max(1, (int) $this->quantity);
        return round($q * (float) $this->costo_base * ((float) $this->margen_porcentaje / 100), 2);
    }

    /**
     * Atributo virtual `codigo_articulo_catalogo`.
     *
     * Código del artículo tal como está en el catálogo del ERP
     * (`items.internal_id`) cuando el ítem matcheó con un producto. Cadena
     * vacía si no hay match o si el producto no tiene código.
     *
     * Los ítems ad-hoc que crea la conversión a cotización formal llevan un
     * `internal_id` interno ('COM02-ADHOC-{prequote}-{item}'). Ese string NO es
     * un código de artículo: es una marca de trazabilidad interna y no debe
     * verse ni en el preview ni en la propuesta que recibe el cliente.
     *
     * @return string
     */
    public function getCodigoArticuloCatalogoAttribute()
    {
        if (empty($this->item_id)) {
            return '';
        }

        $key = $this->internalIdCacheKey((int) $this->item_id);

        if (!array_key_exists($key, self::$internalIdCache)) {
            $internal = '';
            try {
                if ($this->relationLoaded('catalogItem') && $this->catalogItem) {
                    $internal = $this->catalogItem->internal_id;
                } else {
                    // Query builder directo, NO el modelo Item: consultarlo por
                    // Eloquent arrastra sus relaciones ($with) y dispara ~7
                    // consultas extra por cada lectura. Acá solo hace falta un
                    // campo, así que se lee la tabla directamente.
                    $internal = \Illuminate\Support\Facades\DB::connection('tenant')
                        ->table('items')
                        ->where('id', (int) $this->item_id)
                        ->value('internal_id');
                }
            } catch (\Exception $e) {
                $internal = '';
            }

            $internal = trim((string) $internal);

            if ($internal !== '' && strpos($internal, 'COM02-ADHOC-') === 0) {
                $internal = '';
            }

            self::$internalIdCache[$key] = $internal;
        }

        return self::$internalIdCache[$key];
    }

    /**
     * Atributo virtual `codigo_articulo` — el código EFECTIVO.
     *
     * Cascada: override manual del ítem → código del catálogo → ''.
     * Es el valor que pinta la columna "Cód. Artíc." del preview editable y del
     * PDF de la propuesta (`ProposalPdfGenerator::inferCodigoArticulo` delega
     * acá, para que preview y PDF no se desincronicen).
     *
     * @return string
     */
    public function getCodigoArticuloAttribute()
    {
        $manual = trim((string) $this->codigo_articulo_manual);
        if ($manual !== '') {
            return $manual;
        }

        return $this->codigo_articulo_catalogo;
    }

    /**
     * Clave del cache de códigos, con el tenant activo incluido: dos tenants
     * distintos comparten ids de `items` y no deben pisarse entre sí.
     *
     * @param  int  $itemId
     * @return string
     */
    private function internalIdCacheKey($itemId)
    {
        $tenant = '';
        try {
            $tenant = (string) \Illuminate\Support\Facades\DB::connection('tenant')->getDatabaseName();
        } catch (\Exception $e) {
            $tenant = '';
        }

        return $tenant . '|' . $itemId;
    }

    /**
     * ¿Tiene referencia mínima del producto? (RN-03)
     *
     * @return bool
     */
    public function hasMinimumReference()
    {
        return !empty($this->product_name)
            || !empty($this->product_description)
            || !empty($this->product_reference_image);
    }

    /**
     * ¿Es un ítem válido para cotizar? (RN-02 + RN-03)
     *
     * @return bool
     */
    public function isValid()
    {
        return $this->quantity > 0 && $this->hasMinimumReference();
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers de matching de producto
    |--------------------------------------------------------------------------
    */

    /**
     * Scope: ítems que la ejecutiva necesita revisar (matching ambiguo o sin match).
     */
    public function scopeNeedsReview($query)
    {
        return $query->where('needs_product_review', true);
    }

    /**
     * Aplicar el resultado del ProductMatchingService a este ítem.
     *
     * @param  array  $result  Estructura del ProductMatchingService::match():
     *                        [
     *                          'best_match'   => array|null  (item_id, name, ...),
     *                          'confidence'   => float       0-1,
     *                          'needs_review' => bool,
     *                          'suggestions'  => array       top-5,
     *                        ]
     * @return void
     */
    public function applyMatchResult(array $result)
    {
        $best       = isset($result['best_match'])   ? $result['best_match']   : null;
        $confidence = isset($result['confidence'])   ? (float) $result['confidence'] : null;
        $needsRev   = isset($result['needs_review']) ? (bool) $result['needs_review'] : false;
        $sugg       = isset($result['suggestions'])  ? $result['suggestions']  : [];

        $this->item_id                  = $best && isset($best['item_id']) ? (int) $best['item_id'] : null;
        $this->product_match_confidence = $confidence;
        $this->needs_product_review     = $needsRev;
        $this->product_suggestions      = !empty($sugg) ? $sugg : null;

        $this->save();
    }

    /**
     * Asociar manualmente este ítem a un producto del catálogo (lo que hace la
     * ejecutiva al elegir desde la UI).
     *
     * @param  int|null  $itemId       NULL = "ninguna corresponde"
     * @return void
     */
    public function manualMatchTo($itemId)
    {
        $this->item_id                  = $itemId !== null ? (int) $itemId : null;
        $this->product_match_confidence = $itemId !== null ? 1.00 : null;
        $this->needs_product_review     = false;
        $this->save();
    }
}
