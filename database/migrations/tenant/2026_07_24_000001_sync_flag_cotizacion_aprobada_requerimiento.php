<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Repara los requerimientos cuya etapa dice "Cotización aprobada" pero cuyo flag
 * `cotizacion_aprobada` quedó en 0.
 *
 * El botón "Crear proyecto desde requerimiento" se muestra según el FLAG, no según
 * la etapa (RequerimientosController::show → `puede_crear_proyecto`), así que esos
 * requerimientos quedaban sin manera de convertirse en proyecto aunque en pantalla
 * se vieran aprobados.
 *
 * Origen del desfase (ambos corregidos en el mismo cambio):
 *   1. Al CREAR un requerimiento eligiendo ya la etapa "Cotización aprobada", el
 *      flag no se sincronizaba y nacía en 0.
 *   2. Al guardar, la sincronización estaba condicionada a que la etapa CAMBIARA
 *      (isDirty), y como el formulario reenvía la etapa sin cambios, las filas
 *      rotas nunca se reparaban solas.
 *
 * Solo toca filas que todavía NO fueron escaladas a proyecto: si ya se escaló, el
 * estado correcto lo define `escalado_a_proyecto` y no hay nada que reparar.
 *
 * `fecha_aprobacion` se completa con la fecha de última modificación cuando está
 * vacía (es la mejor aproximación disponible); si ya tenía valor, se respeta.
 */
class SyncFlagCotizacionAprobadaRequerimiento extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('requerimiento')) {
            return;
        }

        try {
            DB::table('requerimiento')
                ->where('etapa', 'Cotización aprobada')
                ->where('cotizacion_aprobada', 0)
                ->where('escalado_a_proyecto', 0)
                ->update([
                    'cotizacion_aprobada' => 1,
                    'fecha_aprobacion'    => DB::raw('COALESCE(fecha_aprobacion, updated_at)'),
                ]);
        } catch (\Exception $e) {
            \Log::warning('Sync flag cotizacion_aprobada en requerimiento: ' . $e->getMessage());
        }
    }

    public function down()
    {
        // No se revierte: es una reparación de datos, no un cambio de esquema.
    }
}
