<?php

namespace Modules\Payroll\Models;

use App\Models\Tenant\ModelTenant;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Payroll\Traits\PayrollAuditable;
use App\Models\Tenant\User;

class Payroll extends ModelTenant
{
    use SoftDeletes, PayrollAuditable;

    protected $table = 'payroll';

    protected $fillable = [
        'codigo',
        'periodo',
        'anio',
        'mes',
        'fecha_inicio',
        'fecha_fin',
        'fecha_pago',
        'estado',
        'total_ingresos',
        'total_descuentos',
        'total_aportaciones',
        'total_neto',
        'total_empleados',
        'observaciones',
        'procesada_by',
        'procesada_at',
        'aprobada_by',
        'aprobada_at',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'fecha_inicio' => 'date',
        'fecha_fin' => 'date',
        'fecha_pago' => 'date',
        'total_ingresos' => 'decimal:2',
        'total_descuentos' => 'decimal:2',
        'total_aportaciones' => 'decimal:2',
        'total_neto' => 'decimal:2',
        'procesada_at' => 'datetime',
        'aprobada_at' => 'datetime',
    ];

    // Relaciones
    public function detalles()
    {
        return $this->hasMany(PayrollDetail::class, 'planilla_id');
    }

    public function procesador()
    {
        return $this->belongsTo(User::class, 'procesada_by');
    }

    public function aprobador()
    {
        return $this->belongsTo(User::class, 'aprobada_by');
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
    public function scopePorEstado($query, $estado)
    {
        return $query->where('estado', $estado);
    }

    public function scopePorPeriodo($query, $anio, $mes)
    {
        return $query->where('anio', $anio)->where('mes', $mes);
    }

    // Métodos
    public function generarCodigo()
    {
        return "PLN-{$this->anio}-" . str_pad($this->mes, 2, '0', STR_PAD_LEFT);
    }

    public function puedeEditarse()
    {
        return in_array($this->estado, ['BORRADOR', 'PROCESADA']);
    }

    public function puedeEliminarse()
    {
        return $this->estado === 'BORRADOR';
    }

    public function puedeProcesarse()
    {
        return $this->estado === 'BORRADOR';
    }

    public function puedeAprobarse()
    {
        return $this->estado === 'PROCESADA';
    }

    public function puedePagarse()
    {
        return $this->estado === 'APROBADA';
    }

    public function recalcularTotales()
    {
        $totales = $this->detalles()
            ->selectRaw('
                SUM(total_ingresos) as total_ingresos,
                SUM(total_descuentos) as total_descuentos,
                SUM(total_aportaciones) as total_aportaciones,
                SUM(neto_pagar) as total_neto,
                COUNT(*) as total_empleados
            ')
            ->first();

        $this->update([
            'total_ingresos' => $totales->total_ingresos ?? 0,
            'total_descuentos' => $totales->total_descuentos ?? 0,
            'total_aportaciones' => $totales->total_aportaciones ?? 0,
            'total_neto' => $totales->total_neto ?? 0,
            'total_empleados' => $totales->total_empleados ?? 0,
        ]);
    }

    public function getCollectionData()
    {
        return [
            'id' => $this->id,
            'codigo' => $this->codigo,
            'periodo' => $this->periodo,
            'fecha_inicio' => $this->fecha_inicio->format('Y-m-d'),
            'fecha_fin' => $this->fecha_fin->format('Y-m-d'),
            'fecha_pago' => $this->fecha_pago ? $this->fecha_pago->format('Y-m-d') : '-',
            'estado' => $this->estado,
            'total_ingresos' => $this->total_ingresos,
            'total_descuentos' => $this->total_descuentos,
            'total_aportaciones' => $this->total_aportaciones,
            'total_neto' => $this->total_neto,
            'total_empleados' => $this->total_empleados,
        ];
    }
}