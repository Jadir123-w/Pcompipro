<?php

namespace Modules\Payroll\Http\Controllers;

use Illuminate\Http\Request;
use Modules\Payroll\Models\PayrollConfiguration;


class ConfigurationController extends BaseController
{
    public function index()
    {
        $settings = (object) [
            'uit' => PayrollConfiguration::obtenerValor('uit') ?? 4950,
            'dias_mes_laboral' => PayrollConfiguration::obtenerValor('dias_mes_laboral') ?? 30,
            'horas_dia_laboral' => PayrollConfiguration::obtenerValor('horas_dia_laboral') ?? 8,
            'tasa_hora_extra' => PayrollConfiguration::obtenerValor('tasa_hora_extra') ?? 1.25,
            'porcentaje_essalud' => PayrollConfiguration::obtenerValor('porcentaje_essalud') ?? 9.00,
            'porcentaje_onp' => PayrollConfiguration::obtenerValor('porcentaje_onp') ?? 13.00,
        ];

        $afps = \Modules\Payroll\Models\PayrollAfp::all();

        return view('payroll::configuration.index', compact('settings', 'afps'));
    }

    public function update(Request $request)
    {
        $request->validate([
            'config.uit' => 'required|numeric|min:0',
            'config.porcentaje_essalud' => 'required|numeric|min:0|max:100',
            'config.porcentaje_onp' => 'required|numeric|min:0|max:100',
            'config.tasa_hora_extra' => 'required|numeric|min:1|max:2',
            'config.dias_mes_laboral' => 'required|integer|min:1|max:31',
            'config.horas_dia_laboral' => 'required|integer|min:1|max:24',
            
            'afps' => 'array',
            'afps.*.comision_flujo' => 'required|numeric|min:0|max:100',
            'afps.*.comision_mixta' => 'required|numeric|min:0|max:100',
            'afps.*.aporte_obligatorio' => 'required|numeric|min:0|max:100',
            'afps.*.seguro_invalidez' => 'required|numeric|min:0|max:100',
        ]);

        // Update Global Settings
        $configData = $request->input('config', []);
        foreach ($configData as $key => $value) {
            $config = PayrollConfiguration::where('clave', $key)
                ->where('anio', date('Y'))
                ->first();

            if ($config) {
                $config->update(['valor' => $value]);
            } else {
                PayrollConfiguration::create([
                    'clave' => $key,
                    'valor' => $value,
                    'anio' => date('Y'),
                    'nombre' => str_replace('_', ' ', ucfirst($key)),
                    'tipo' => is_numeric($value) ? (strpos($value, '.') !== false ? 'decimal' : 'entero') : 'string'
                ]);
            }
        }

        // Update AFPs
        $afpsData = $request->input('afps', []);
        foreach ($afpsData as $afpId => $afpValues) {
            $afp = \Modules\Payroll\Models\PayrollAfp::find($afpId);
            if ($afp) {
                $afp->update([
                    'comision_flujo' => $afpValues['comision_flujo'],
                    'comision_mixta' => $afpValues['comision_mixta'],
                    'aporte_obligatorio' => $afpValues['aporte_obligatorio'],
                    'seguro_invalidez' => $afpValues['seguro_invalidez'],
                ]);
            }
        }

        return redirect()->route('tenant.payroll.configuration.index')
            ->with('success', 'Configuración actualizada correctamente');
    }
}
