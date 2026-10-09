<?php

namespace Modules\Payroll\Models;

use App\Models\Tenant\ModelTenant;
use Modules\Payroll\Traits\PayrollAuditable;
use App\Models\Tenant\User;


class PayrollDetail extends ModelTenant
{
    use PayrollAuditable;

    protected $table = 'payroll_detail';

    protected $fillable = [
        'planilla_id',
        'empleado_id',
        'dni',
        'nombres_completos',
        'cargo',
        'sueldo_basico',
        'dias_laborados',
        'dias_mes',
        'asignacion_familiar',
        'horas_extras',
        'bonificaciones',
        'movilidad',
        'otros_ingresos',
        'total_ingresos',
        'total_ingresos_afectos',
        'total_ingresos_no_afectos',
        'tipo_regimen',
        'afp_nombre',
        'aporte_afp_onp',
        'comision_afp',
        'seguro_afp',
        'renta_quinta',
        'otros_descuentos',
        'total_descuentos',
        'essalud',
        'total_aportaciones',
        'neto_pagar',
        'boleta_enviada',
        'boleta_enviada_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'sueldo_basico' => 'decimal:2',
        'asignacion_familiar' => 'decimal:2',
        'horas_extras' => 'decimal:2',
        'bonificaciones' => 'decimal:2',
        'movilidad' => 'decimal:2',
        'otros_ingresos' => 'decimal:2',
        'total_ingresos' => 'decimal:2',
        'total_ingresos_afectos' => 'decimal:2',
        'total_ingresos_no_afectos' => 'decimal:2',
        'aporte_afp_onp' => 'decimal:2',
        'comision_afp' => 'decimal:2',
        'seguro_afp' => 'decimal:2',
        'renta_quinta' => 'decimal:2',
        'otros_descuentos' => 'decimal:2',
        'total_descuentos' => 'decimal:2',
        'essalud' => 'decimal:2',
        'total_aportaciones' => 'decimal:2',
        'neto_pagar' => 'decimal:2',
        'boleta_enviada' => 'boolean',
        'boleta_enviada_at' => 'datetime',
    ];

    // Relaciones
    public function planilla()
    {
        return $this->belongsTo(Payroll::class, 'planilla_id');
    }

    public function empleado()
    {
        return $this->belongsTo(PayrollEmployee::class);
    }

    public function conceptos()
    {
        return $this->hasMany(PayrollConcepts::class, 'planilla_detalle_id');
    }

    public function creador()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function editor()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // Métodos de cálculo
    public function calcularIngresos()
    {
        $sueldoPorDias = ($this->sueldo_basico / $this->dias_mes) * $this->dias_laborados;
        
        $this->total_ingresos = $sueldoPorDias + 
                                $this->asignacion_familiar + 
                                $this->horas_extras + 
                                $this->bonificaciones + 
                                $this->movilidad + 
                                $this->otros_ingresos;

        // Ingresos afectos (sin movilidad si es menor a 1 RMV)
        $rmv = PayrollConfiguration::obtenerRMV();
        $movilidadAfecta = $this->movilidad > $rmv ? $this->movilidad : 0;
        $movilidadNoAfecta = $this->movilidad > $rmv ? 0 : $this->movilidad;

        $this->total_ingresos_afectos = $sueldoPorDias + 
                                        $this->asignacion_familiar + 
                                        $this->horas_extras + 
                                        $this->bonificaciones + 
                                        $movilidadAfecta;

        $this->total_ingresos_no_afectos = $movilidadNoAfecta + $this->otros_ingresos;
    }

    public function calcularDescuentos()
    {
        $baseAfecta = $this->total_ingresos_afectos;

        if ($this->tipo_regimen === 'AFP') {
            $afp = PayrollAfp::where('nombre', $this->afp_nombre)->first();
            
            if ($afp) {
                $this->aporte_afp_onp = ($baseAfecta * $afp->aporte_obligatorio) / 100;
                $this->comision_afp = ($baseAfecta * $afp->comision_flujo) / 100; // Asumir flujo
                $this->seguro_afp = ($baseAfecta * $afp->seguro_invalidez) / 100;
            }
        } else {
            $tasaOnp = PayrollConfiguration::obtenerTasaONP();
            $this->aporte_afp_onp = ($baseAfecta * $tasaOnp) / 100;
            $this->comision_afp = 0;
            $this->seguro_afp = 0;
        }

        // Calcular renta quinta (simplificado - debería ser proyectado anual)
        $this->renta_quinta = $this->calcularRentaQuinta($baseAfecta);

        $this->total_descuentos = $this->aporte_afp_onp + 
                                  $this->comision_afp + 
                                  $this->seguro_afp + 
                                  $this->renta_quinta + 
                                  $this->otros_descuentos;
    }

    public function calcularAportaciones()
    {
        $tasaEssalud = PayrollConfiguration::obtenerTasaEsSalud();
        $this->essalud = ($this->total_ingresos_afectos * $tasaEssalud) / 100;
        $this->total_aportaciones = $this->essalud;
    }

    public function calcularNeto()
    {
        $this->neto_pagar = $this->total_ingresos - $this->total_descuentos;
    }

    public function procesarCalculo()
    {
        $this->calcularIngresos();
        $this->calcularDescuentos();
        $this->calcularAportaciones();
        $this->calcularNeto();
        $this->save();
    }

    private function calcularRentaQuinta($ingresoMensual)
    {
        // Proyección anual
        $ingresoAnual = $ingresoMensual * 12;
        
        // Deducción 7 UIT
        $uit = PayrollConfiguration::obtenerUIT();
        
        if ($uit <= 0) {
            return 0;
        }

        $deduccion = $uit * 7;
        
        $baseImponible = $ingresoAnual - $deduccion;
        
        if ($baseImponible <= 0) {
            return 0;
        }

        // Tramos
        $impuesto = 0;
        $uitRestante = $baseImponible / $uit;

        // Hasta 5 UIT - 8%
        if ($uitRestante > 0) {
            $montroTramo = min($uitRestante, 5) * $uit;
            $impuesto += $montroTramo * 0.08;
            $uitRestante -= 5;
        }

        // Hasta 20 UIT (5+15) - 14%
        if ($uitRestante > 0) {
            $montroTramo = min($uitRestante, 15) * $uit;
            $impuesto += $montroTramo * 0.14;
            $uitRestante -= 15;
        }

        // Hasta 45 UIT (20+25) - 17%
        if ($uitRestante > 0) {
            $montroTramo = min($uitRestante, 25) * $uit;
            $impuesto += $montroTramo * 0.17;
            $uitRestante -= 25;
        }

        // Hasta 70 UIT (45+25) - 20%
        if ($uitRestante > 0) {
            $montroTramo = min($uitRestante, 25) * $uit;
            $impuesto += $montroTramo * 0.20;
            $uitRestante -= 25;
        }

        // Exceso - 30%
        if ($uitRestante > 0) {
            $impuesto += ($uitRestante * $uit) * 0.30;
        }

        // Retorno mensual
        return $impuesto / 12;
    }
}