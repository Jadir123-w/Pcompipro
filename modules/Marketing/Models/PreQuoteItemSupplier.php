<?php

namespace Modules\Marketing\Models;

use App\Models\Tenant\ModelTenant;

/**
 * PreQuoteItemSupplier — Cotización de un proveedor para un ítem (COM-02).
 *
 * Comparativa de proveedores del mockup: N cotizaciones por ítem, el
 * ganador (es_ganador) define supplier_id/name + costo_base del ítem.
 */
class PreQuoteItemSupplier extends ModelTenant
{
    public const SOURCE_MANUAL  = 'manual';
    public const SOURCE_CATALOG = 'catalog';

    public const DEFAULT_SUPPLIER_NAME = 'Proveedor por definir';

    protected $table = 'quotation_prequote_item_suppliers';

    protected $fillable = [
        'prequote_item_id',
        'supplier_id',
        'supplier_name',
        'source',
        'product_reference',
        'costo_base',
        'tiempo_entrega',
        'observacion',
        'es_ganador',
        'orden',
        'user_id',
    ];

    protected $casts = [
        'prequote_item_id' => 'integer',
        'supplier_id'      => 'integer',
        'costo_base'       => 'decimal:2',
        'es_ganador'       => 'boolean',
        'orden'            => 'integer',
        'user_id'          => 'integer',
    ];

    public function item()
    {
        return $this->belongsTo(PreQuoteItem::class, 'prequote_item_id');
    }
}
