<?php

namespace Modules\Payroll\Models;

use App\Models\Tenant\ModelTenant;
use App\Models\Tenant\User;

class PayrollConcepts extends ModelTenant
{
    protected $table = 'payroll_concepts';

    protected $fillable = [
        'planilla_detalle_id',
        'concepto_id',
        'concepto_codigo',
        'concepto_nombre',
        'tipo',
        'monto',
        'observacion',
        'created_by',
    ];

    protected $casts = [
        'monto' => 'decimal:2',
    ];

    public function planillaDetalle()
    {
        return $this->belongsTo(PayrollDetail::class);
    }

    public function concepto()
    {
        return $this->belongsTo(PayrollConcepts::class);
    }

    public function creador()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}