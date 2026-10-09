<?php
/**
 * ============================================================================
 * ARCHIVO: ProyectoProducto.php (MODELO)
 * ============================================================================
 * Ubicación: Modules/Comercial/Models/ProyectoProducto.php
 *
 * Modelo para los productos/items asociados a un proyecto.
 * Cada producto puede tener varios proveedores (M2, ver proveedores()).
 * ============================================================================
 */

namespace Modules\Comercial\Models;

use App\Models\Tenant\ModelTenant;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditableTrait;

class ProyectoProducto extends ModelTenant implements Auditable
{
    use AuditableTrait;

    protected $table = 'proyecto_productos';

    /**
     * Cuando el subtotal se gestiona por proveedores (M2), el controller pone
     * $producto->autoSubtotal = false y fija el subtotal como la suma de los
     * totales de los proveedores activos. En el resto de casos (carga simple con
     * precio_unitario) se mantiene el cálculo clásico cantidad × precio_unitario.
     *
     * @var bool
     */
    public $autoSubtotal = true;

    protected $fillable = [
        'proyecto_id',
        'item_id',
        'supplier_id',
        'supplier_name',
        'descripcion',
        'material',
        'medida',
        'tecnica_impresion',
        'cantidad_colores',
        'cantidad_lados',
        'empaque',
        'personalizacion',
        'costo_empaque',
        'costo_movilidad',
        'costo_sctr',
        'imagen',
        'cantidad',
        'muestras',
        'precio_unitario',
        'subtotal',
        'observaciones',
    ];

    protected $casts = [
        'proyecto_id'      => 'integer',
        'item_id'          => 'integer',
        'supplier_id'      => 'integer',
        'cantidad_colores' => 'integer',
        'cantidad_lados'   => 'integer',
        'costo_empaque'    => 'decimal:2',
        'costo_movilidad'  => 'decimal:2',
        'costo_sctr'       => 'decimal:2',
        'cantidad'         => 'decimal:2',
        'muestras'         => 'integer',
        'precio_unitario'  => 'decimal:2',
        'subtotal'         => 'decimal:2',
    ];

    protected static function boot()
    {
        parent::boot();

        // Calcular subtotal automáticamente (solo carga simple; con proveedores
        // el controller fija el subtotal y desactiva este cálculo).
        static::saving(function ($producto) {
            if ($producto->autoSubtotal && $producto->cantidad && $producto->precio_unitario) {
                $producto->subtotal = $producto->cantidad * $producto->precio_unitario;
            }
        });
    }

    /* ========== RELACIONES ========== */

    public function proyecto()
    {
        return $this->belongsTo(Proyecto::class, 'proyecto_id');
    }

    public function supplier()
    {
        return $this->belongsTo(\App\Models\Tenant\Person::class, 'supplier_id');
    }

    /**
     * Todos los proveedores del producto (activos primero, luego los
     * reemplazados = historial), ordenados por `orden` e `id`.
     */
    public function proveedores()
    {
        return $this->hasMany(ProyectoProductoProveedor::class, 'proyecto_producto_id')
                    ->orderBy('activo', 'desc')
                    ->orderBy('orden')
                    ->orderBy('id');
    }

    /**
     * Solo los proveedores activos (los que suman al costo del producto).
     */
    public function proveedoresActivos()
    {
        return $this->hasMany(ProyectoProductoProveedor::class, 'proyecto_producto_id')
                    ->where('activo', 1)
                    ->orderBy('orden')
                    ->orderBy('id');
    }

    /* ========== ACCESSORS ========== */

    public function getSubtotalFormatAttribute()
    {
        return $this->subtotal ? 'S/ ' . number_format($this->subtotal, 2) : null;
    }

    public function getPrecioUnitarioFormatAttribute()
    {
        return $this->precio_unitario ? 'S/ ' . number_format($this->precio_unitario, 2) : null;
    }
}
