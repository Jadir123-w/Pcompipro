<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * COM-07 — Permisos independientes para las pantallas del agente.
 *
 * Registra "Oportunidades IA" y "Supervisión Comercial" como sub-permisos del
 * módulo Comercial, de modo que puedan habilitarse por separado desde la ficha
 * del usuario: una ejecutiva necesita su panel, pero la vista de supervisión
 * corresponde solo a quien la supervisa.
 *
 * Al vivir bajo Comercial, quien use el COM-07 ya no requiere el módulo
 * Marketing y deja de ver los demás agentes y sus pantallas de configuración.
 */
class AddCom07LevelsToComercialModule extends Migration
{
    /** Sub-permisos que se registran, con la ruta que abre cada uno. */
    private $niveles = [
        [
            'value'       => 'com07_oportunidades',
            'description' => 'Oportunidades IA',
            'route_name'  => 'tenant.comercial.com07.panel',
            'route_path'  => '/comercial/com07/panel',
        ],
        [
            'value'       => 'com07_supervision',
            'description' => 'Supervisión Comercial',
            'route_name'  => 'tenant.comercial.com07.supervision',
            'route_path'  => '/comercial/com07/supervision',
        ],
    ];

    public function up()
    {
        $conexion = DB::connection('tenant');

        $moduloId = $conexion->table('modules')->where('value', 'comercial')->value('id');
        if (!$moduloId) {
            return;   // sin módulo Comercial no hay dónde colgarlos
        }

        foreach ($this->niveles as $nivel) {
            $existe = $conexion->table('module_levels')
                ->where('module_id', $moduloId)
                ->where('value', $nivel['value'])
                ->exists();

            if ($existe) {
                continue;   // idempotente: puede correrse en varios ambientes
            }

            $conexion->table('module_levels')->insert([
                'module_id'   => $moduloId,
                'value'       => $nivel['value'],
                'description' => $nivel['description'],
                'route_name'  => $nivel['route_name'],
                'route_path'  => $nivel['route_path'],
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);
        }
    }

    public function down()
    {
        $conexion = DB::connection('tenant');

        $moduloId = $conexion->table('modules')->where('value', 'comercial')->value('id');
        if (!$moduloId) {
            return;
        }

        $valores = array_column($this->niveles, 'value');

        $niveles = $conexion->table('module_levels')
            ->where('module_id', $moduloId)
            ->whereIn('value', $valores)
            ->pluck('id');

        if ($niveles->isNotEmpty()) {
            $conexion->table('module_level_user')->whereIn('module_level_id', $niveles)->delete();
            $conexion->table('module_levels')->whereIn('id', $niveles)->delete();
        }
    }
}
