<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditableTrait;

/**
 * Maestro de Categorías de Proveedor.
 * Una categoría agrupa varios proveedores y tiene N subcategorías.
 */
class SupplierCategory extends ModelTenant implements Auditable
{
    use SoftDeletes, AuditableTrait;

    protected $table = 'supplier_categories';

    protected $fillable = [
        'nombre',
        'descripcion',
        'orden',
        'estado',
    ];

    protected $casts = [
        'orden' => 'int',
        'estado' => 'int',
    ];

    public function subcategories()
    {
        return $this->hasMany(SupplierSubcategory::class);
    }

    public function suppliers()
    {
        return $this->hasMany(Person::class, 'supplier_category_id')
                    ->where('type', 'suppliers');
    }

    public function scopeActive($query)
    {
        return $query->where('estado', 1);
    }
}
