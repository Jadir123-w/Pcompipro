<?php

namespace Modules\Payroll\Models;

use App\Models\Tenant\ModelTenant;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Payroll\Traits\PayrollAuditable;
use App\Models\Tenant\User;

class PayrollConceptsPayroll extends ModelTenant
{
    use SoftDeletes, PayrollAuditable;

    protected $table = 'payroll_concepts_payroll';

    protected $fillable = [
        'codigo',
        'nombre',
        'descripcion',
        'tipo',
        'naturaleza',
        'afecto_essalud',
        'afecto_renta',
        'afecto_afp_onp',
        'sistema',
        'orden',
        'activo',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'afecto_essalud' => 'boolean',
        'afecto_renta' => 'boolean',
        'afecto_afp_onp' => 'boolean',
        'sistema' => 'boolean',
        'activo' => 'boolean',
    ];

    // Relaciones
    public function planillaConceptos()
    {
        return $this->hasMany(PayrollConceptsPayroll::class, 'concepto_id');
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

    public function scopeIngresos($query)
    {
        return $query->where('tipo', 'INGRESO');
    }

    public function scopeDescuentos($query)
    {
        return $query->where('tipo', 'DESCUENTO');
    }

    public function scopeFijos($query)
    {
        return $query->where('naturaleza', 'FIJO');
    }

    public function scopeVariables($query)
    {
        return $query->where('naturaleza', 'VARIABLE');
    }

    public function scopeNoSistema($query)
    {
        return $query->where('sistema', false);
    }

    public function scopeOrdenados($query)
    {
        return $query->orderBy('orden');
    }
}