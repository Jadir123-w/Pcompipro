<?php

namespace Modules\Marketing\Models;

use App\Models\Tenant\ModelTenant;

/**
 * QuotationItemSupplier — Comparativa de proveedores por ítem (solución momentánea).
 *
 * [NUEVO FLUJO 2026-10-20] Tabla nueva `quotation_items_supplier` que reemplaza
 * temporalmente a PreQuoteItemSupplier (`quotation_prequote_item_suppliers`,
 * tabla que no existe).
 *
 * [NUEVO FLUJO 2026-10-21] Cambios de estructura:
 *   - `es_ganador` → `seleccionado` (0 = respaldo, 1 = el elegido)
 *   - se eliminó la columna `orden` (ya no se maneja)
 *   - `tiempo_entrega` → `tiempo_estimado_dias`
 *
 * Reglas de negocio:
 *   - Cada ítem de pre-cotización tiene N cotizaciones de proveedores
 *   - Solo una tiene `seleccionado = 1` (define el Costo Base de la línea)
 *   - Las demás son respaldos (el usuario elige cuál manda con el radio)
 *
 * Esta clase NO escribe en `quotation_prequote_items` las columnas de
 * proveedor, costo base ni tiempo estimado: esos datos viven acá. El ítem los
 * recibe por el sincronizador del controller (syncWinningQuoteToItem), que es
 * lo que usa el motor de cálculo.
 */
class QuotationItemSupplier extends ModelTenant
{
    /** Origen siempre manual: este flujo no mapea productos del catálogo. */
    public const ORIGEN_MANUAL = 'manual';

    /** Nombre por defecto cuando aún no se elige proveedor. */
    public const DEFAULT_SUPPLIER_NAME = 'Proveedor por definir';

    /** Tabla nueva en sql/quotation_items_supplier.sql (sin migración Laravel). */
    protected $table = 'quotation_items_supplier';

    /** Campos que se pueden asignar masivamente. */
    protected $fillable = [
        'quotation_items_id',   // FK al ítem de pre-cotización
        'supplier_id',          // persons.id (type=suppliers), NULL = por definir
        'supplier_name',        // nombre desnormalizado del proveedor para mostrar en front
        'origen',               // siempre 'manual'
        'product_reference',    // referencia cotizada (texto libre)
        'costo_base',           // costo cotizado por este proveedor
        'tiempo_estimado_dias', // tiempo de entrega del proveedor, ej: 15 dias utiles
        'observaciones',        // notas de esta cotizacion del proveedor
        'seleccionado',         // 1 = el proveedor elegido; 0 = respaldo
        'user_id',              // usuario que creo el registro
    ];

    /** Tipos de datos para casts de Laravel. */
    protected $casts = [
        'quotation_items_id' => 'integer',
        'supplier_id'        => 'integer',
        'costo_base'         => 'decimal:2',
        'seleccionado'       => 'integer',
        'user_id'            => 'integer',
    ];

    /**
     * Relación inversa: este registro pertenece a un ítem de pre-cotización.
     * Desde acá se manejan las dos tablas (comparativa + ítem).
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function item()
    {
        return $this->belongsTo(PreQuoteItem::class, 'quotation_items_id');
    }

    /**
     * Solo la cotización elegida (seleccionado = 1) de un ítem.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeSeleccionado($query)
    {
        return $query->where('seleccionado', 1);
    }
}
