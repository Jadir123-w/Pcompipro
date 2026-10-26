<?php
/**
 * ============================================================================
 * ARCHIVO: ProyectoProductoProveedor.php (MODELO)
 * ============================================================================
 * Ubicación: Modules/Comercial/Models/ProyectoProductoProveedor.php
 *
 * Proveedor asignado a un producto del proyecto (M2). Un producto puede tener
 * varios proveedores. Cuando un proveedor se reemplaza, el anterior NO se borra:
 * queda con `activo = 0` para conservar el historial de cambios.
 *
 * Respaldos (`es_respaldo = 1`, también con `activo = 0`): los demás proveedores
 * cotizados en la comparativa. No suman al costo ni son historial; al usarse
 * reemplazan al proveedor `respalda_a_id` con el precio que se confirme.
 *
 * Los montos derivados (neto, IGV, total a pagar) se calculan en el backend a
 * partir de `precio_unitario_informado`, `cantidad` y `condicion_igv`.
 * ============================================================================
 */

namespace Modules\Comercial\Models;

use App\Models\Tenant\ModelTenant;

class ProyectoProductoProveedor extends ModelTenant
{
    protected $table = 'proyecto_producto_proveedores';

    protected $fillable = [
        'proyecto_producto_id',
        'supplier_id',
        'supplier_name',
        'concepto',
        // Agosto 2026 (punto 20): tiempo que tarda el proveedor en entregarle el
        // producto a COMPIPRO. Hereda el de la pre-cotización y es editable.
        // OJO: no confundir con `quotation_prequote_items.tiempo_entrega`, que es
        // el plazo que se le promete al CLIENTE (punto 9) y vive en la cotización.
        'tiempo_produccion',
        'cantidad',
        'precio_unitario_informado',
        'condicion_igv',
        'precio_unitario_neto',
        'subtotal_neto',
        'igv_monto',
        'total_pagar',
        'activo',
        'es_respaldo',      // 1 = respaldo de la comparativa (no suma al costo)
        'respalda_a_id',    // fila del proveedor al que reemplazaría
        'cotizado_el',      // fecha en que el proveedor cotizó
        'reemplaza_a_id',
        'motivo_reemplazo',
        'orden',
    ];

    protected $casts = [
        'proyecto_producto_id'      => 'integer',
        'supplier_id'               => 'integer',
        'cantidad'                  => 'decimal:2',
        'precio_unitario_informado' => 'decimal:2',
        'precio_unitario_neto'      => 'decimal:2',
        'subtotal_neto'             => 'decimal:2',
        'igv_monto'                 => 'decimal:2',
        'total_pagar'               => 'decimal:2',
        'activo'                    => 'boolean',
        'es_respaldo'               => 'boolean',
        'respalda_a_id'             => 'integer',
        'reemplaza_a_id'            => 'integer',
        'orden'                     => 'integer',
    ];

    /* ========== RELACIONES ========== */

    public function producto()
    {
        return $this->belongsTo(ProyectoProducto::class, 'proyecto_producto_id');
    }

    public function supplier()
    {
        return $this->belongsTo(\App\Models\Tenant\Person::class, 'supplier_id');
    }
}
