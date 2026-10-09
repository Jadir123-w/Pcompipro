<?php

namespace Modules\Payroll\Models;

use App\Models\Tenant\ModelTenant;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Payroll\Traits\PayrollAuditable;
use App\Models\Tenant\User;

class PayrollAfp extends ModelTenant
{
    use SoftDeletes, PayrollAuditable;

    protected $table = 'payroll_afps';

    protected $fillable = [
        'nombre',
        'codigo',
        'comision_flujo',
        'comision_mixta',
        'aporte_obligatorio',
        'seguro_invalidez',
        'activo',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'comision_flujo' => 'decimal:2',
        'comision_mixta' => 'decimal:2',
        'aporte_obligatorio' => 'decimal:2',
        'seguro_invalidez' => 'decimal:2',
        'activo' => 'boolean',
    ];

    // Relaciones
    public function empleados()
    {
        return $this->hasMany(PayrollEmployee::class);
    }

    public function creador()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function editor()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function eliminador()
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

    // Scopes
    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }
}