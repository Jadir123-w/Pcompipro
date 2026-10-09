<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * M5 — Remapea las etapas viejas de `proyecto` a la lista nueva:
 *   Nuevo, En curso / Pre-producción, En producción, Final producción,
 *   En reproceso, Entregado, Pendiente de pago, Terminado.
 *
 * Las etapas que queden fuera de la lista nueva caen a 'Nuevo' para que ningún
 * proyecto conserve una etapa que el select ya no reconoce.
 */
class RemapProyectoEtapas extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('proyecto')) {
            return;
        }

        $map = [
            'Inicio'                => 'Nuevo',
            'Arte en proceso'       => 'En curso / Pre-producción',
            'Arte aprobado'         => 'En curso / Pre-producción',
            'En curso / Producción' => 'En producción',
            'En produccinnn'        => 'En producción',
            'Control de calidad'    => 'Final producción',
            'Listo para despacho'   => 'Final producción',
            'Despachado'            => 'Entregado',
            'Facturado'             => 'Pendiente de pago',
            'Pendiente de Pago'     => 'Pendiente de pago',
            'Cobrado'               => 'Terminado',
        ];

        foreach ($map as $old => $new) {
            try {
                DB::table('proyecto')->where('etapa', $old)->update(['etapa' => $new]);
            } catch (\Exception $e) {
                \Log::warning('M5 remap etapa "' . $old . '": ' . $e->getMessage());
            }
        }

        // Catch-all: cualquier etapa fuera de la lista nueva → 'Nuevo'.
        $validas = [
            'Nuevo', 'En curso / Pre-producción', 'En producción', 'Final producción',
            'En reproceso', 'Entregado', 'Pendiente de pago', 'Terminado',
        ];
        try {
            DB::table('proyecto')
                ->whereNotNull('etapa')
                ->whereNotIn('etapa', $validas)
                ->update(['etapa' => 'Nuevo']);
        } catch (\Exception $e) {
            \Log::warning('M5 remap etapa catch-all: ' . $e->getMessage());
        }
    }

    public function down()
    {
        // No se revierte (remapeo de datos).
    }
}
