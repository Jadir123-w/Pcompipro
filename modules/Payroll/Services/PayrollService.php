<?php

namespace Modules\Payroll\Services;

use Modules\Payroll\Models\Employee;
use Modules\Payroll\Models\PayrollConfiguration;

class PayrollService
{
    public function calculatePayroll(Employee $employee, $periodo, $data)
    {
        // Obtener configuración
        // Obtener configuración
        // No values to fetch as object, we fetch individually or we could fetch all at once
        // For now, let's keep the logic but use the static helper in subsequent steps or here directly.
        
        // No checks needed here, individual values will return null if missing/default

        $sueldoBasico = $employee->sueldo_basico;
        $diasTrabajados = $data['dias_trabajados'];

        // Cálculo de ingresos
        $sueldoProporcional = ($sueldoBasico / 30) * $diasTrabajados;
        $horasExtras = $data['horas_extras'] * (($sueldoBasico / 30) / 8) * 1.25;
        $bonificaciones = $data['bonificaciones'];
        $otrosIngresos = $data['otros_ingresos'];

        $totalIngresos = $sueldoProporcional + $horasExtras + $bonificaciones + $otrosIngresos;

        // Cálculo de descuentos (legislación peruana)
        // Cálculo de descuentos (legislación peruana)
        $tasaEssalud = PayrollConfiguration::obtenerValor('porcentaje_essalud') ?? 9.00;
        $tasaAfpFondo = PayrollConfiguration::obtenerValor('porcentaje_afp_fondo') ?? 10.00;
        $tasaAfpSeguro = PayrollConfiguration::obtenerValor('porcentaje_afp_seguro') ?? 1.37; // Default or fetch from employee AFP
        $tasaAfpComision = PayrollConfiguration::obtenerValor('porcentaje_afp_comision') ?? 0.77;

        $essalud = $totalIngresos * ($tasaEssalud / 100);
        $afpFondo = $totalIngresos * ($tasaAfpFondo / 100);
        $afpSeguro = $totalIngresos * ($tasaAfpSeguro / 100);
        $afpComision = $totalIngresos * ($tasaAfpComision / 100);

        $quintaCategoria = $this->calculateQuintaCategoria($totalIngresos);

        $adelantos = $data['adelantos'];
        $otrosDescuentos = $data['otros_descuentos'];

        $totalDescuentos = $essalud + $afpFondo + $afpSeguro + $afpComision +
            $quintaCategoria + $adelantos + $otrosDescuentos;

        $netoPagable = $totalIngresos - $totalDescuentos;

        return [
            'employee_id' => $employee->id,
            'periodo' => $periodo,
            'dias_trabajados' => $diasTrabajados,
            'horas_extras' => $data['horas_extras'],
            'bonificaciones' => $bonificaciones,
            'otros_ingresos' => $otrosIngresos,
            'adelantos' => $adelantos,
            'otros_descuentos' => $otrosDescuentos,
            'essalud' => $essalud,
            'afp_fondo' => $afpFondo,
            'afp_seguro' => $afpSeguro,
            'afp_comision' => $afpComision,
            'quinta_categoria' => $quintaCategoria,
            'total_ingresos' => $totalIngresos,
            'total_descuentos' => $totalDescuentos,
            'neto_pagable' => $netoPagable
        ];
    }

    private function calculateQuintaCategoria($ingresos)
    {
        $uit = PayrollConfiguration::obtenerValor('uit') ?? 4950;

        if ($ingresos <= (7 * $uit)) {
            return 0;
        }

        $baseImponible = $ingresos - (7 * $uit);

        if ($baseImponible <= 5 * $uit) {
            return $baseImponible * 0.08;
        } elseif ($baseImponible <= 20 * $uit) {
            return $baseImponible * 0.14;
        } else {
            return $baseImponible * 0.17;
        }
    }
}

