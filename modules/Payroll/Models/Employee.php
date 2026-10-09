<?php

namespace Modules\Payroll\Models;

use App\Models\Tenant\ModelTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Payroll\Traits\TenantScope;

class Employee extends ModelTenant
{
    use SoftDeletes;

    protected $table = 'payroll_employees';

    protected $fillable = [
        'dni', 'nombres', 'apellidos', 'fecha_nacimiento',
        'direccion', 'telefono', 'email', 'fecha_ingreso', 'tipo_contrato',
        'sueldo_basico', 'banco', 'numero_cuenta', 'activo'
    ];

    protected $dates = [
        'fecha_nacimiento',
        'fecha_ingreso',
        'deleted_at'
    ];

    protected static function boot()
    {
        parent::boot();
    }

    public function payrolls()
    {
        return $this->belongsToMany(Payroll::class, 'payroll_detail', 'empleado_id', 'planilla_id');
    }
}
