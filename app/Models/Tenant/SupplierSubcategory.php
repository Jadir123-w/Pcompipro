<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditableTrait;

/**
 * Maestro de Subcategorías de Proveedor.
 * Pertenece a una SupplierCategory.
 */
class SupplierSubcategory extends ModelTenant implements Auditable
{
    use SoftDeletes, AuditableTrait;

    protected $table = 'supplier_subcategories';

    protected $fillable = [
        'supplier_category_id',
        'nombre',
        'descripcion',
        'orden',
        'estado',
    ];

    protected $casts = [
        'supplier_category_id' => 'int',
        'orden' => 'int',
        'estado' => 'int',
    ];

    public function category()
    {
        return $this->belongsTo(SupplierCategory::class, 'supplier_category_id');
    }

    public function suppliers()
    {
        return $this->hasMany(Person::class, 'supplier_subcategory_id')
                    ->where('type', 'suppliers');
    }

    public function scopeActive($query)
    {
        return $query->where('estado', 1);
    }
}
