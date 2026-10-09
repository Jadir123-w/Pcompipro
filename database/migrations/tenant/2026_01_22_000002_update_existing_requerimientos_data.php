<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class UpdateExistingRequerimientosData extends Migration
{
    /**
     * Run the migrations.
     *
     * Esta migración actualiza los registros existentes de requerimientos
     * con valores predeterminados para los nuevos campos.
     */
    public function up()
    {
        // Actualizar fecha_ingreso_etapa_actual para registros existentes
        try {
            DB::table('requerimiento')
                ->whereNull('fecha_ingreso_etapa_actual')
                ->update([
                    'fecha_ingreso_etapa_actual' => DB::raw('created_at')
                ]);
        } catch (\Exception $e) {
            \Log::warning('Error actualizando fecha_ingreso_etapa_actual: ' . $e->getMessage());
        }

        // Establecer origen como 'web' para registros provenientes de leads
        try {
            DB::table('requerimiento')
                ->whereNotNull('lead_id')
                ->update(['origen' => 'web']);
        } catch (\Exception $e) {
            \Log::warning('Error actualizando origen: ' . $e->getMessage());
        }

        // Actualizar fecha_ultima_interaccion desde la tabla requerimiento_interaccion
        try {
            DB::statement("
                UPDATE requerimiento r
                SET fecha_ultima_interaccion = (
                    SELECT MAX(created_at)
                    FROM requerimiento_interaccion ri
                    WHERE ri.requerimiento_id = r.id
                )
                WHERE EXISTS (
                    SELECT 1
                    FROM requerimiento_interaccion ri
                    WHERE ri.requerimiento_id = r.id
                )
            ");
        } catch (\Exception $e) {
            \Log::warning('Error actualizando fecha_ultima_interaccion: ' . $e->getMessage());
        }

        // Actualizar usuario_ultima_interaccion desde la última interacción
        try {
            DB::statement("
                UPDATE requerimiento r
                SET usuario_ultima_interaccion = (
                    SELECT ri.user_id
                    FROM requerimiento_interaccion ri
                    WHERE ri.requerimiento_id = r.id
                    ORDER BY ri.created_at DESC
                    LIMIT 1
                )
                WHERE EXISTS (
                    SELECT 1
                    FROM requerimiento_interaccion ri
                    WHERE ri.requerimiento_id = r.id
                )
            ");
        } catch (\Exception $e) {
            \Log::warning('Error actualizando usuario_ultima_interaccion: ' . $e->getMessage());
        }

        // Marcar como cotizacion_aprobada si existe cotización en estado 'Aprobada'
        try {
            DB::statement("
                UPDATE requerimiento r
                SET cotizacion_aprobada = 1,
                    fecha_aprobacion = (
                        SELECT created_at
                        FROM cotizaciones_requerimiento cr
                        WHERE cr.requerimiento_id = r.id
                        AND cr.estado = 'Aprobada'
                        ORDER BY cr.created_at DESC
                        LIMIT 1
                    )
                WHERE EXISTS (
                    SELECT 1
                    FROM cotizaciones_requerimiento cr
                    WHERE cr.requerimiento_id = r.id
                    AND cr.estado = 'Aprobada'
                )
            ");
        } catch (\Exception $e) {
            \Log::warning('Error actualizando cotizacion_aprobada: ' . $e->getMessage());
        }

        // Marcar como escalado_a_proyecto si existe proyecto relacionado
        if (Schema::hasTable('proyecto')) {
            try {
                DB::statement("
                    UPDATE requerimiento r
                    SET escalado_a_proyecto = 1,
                        fecha_escalado_proyecto = (
                            SELECT p.created_at
                            FROM proyecto p
                            WHERE p.requerimiento_id = r.id
                            ORDER BY p.created_at ASC
                            LIMIT 1
                        ),
                        activo_en_crm = 0
                    WHERE EXISTS (
                        SELECT 1
                        FROM proyecto p
                        WHERE p.requerimiento_id = r.id
                    )
                ");
            } catch (\Exception $e) {
                \Log::warning('Error actualizando escalado_a_proyecto: ' . $e->getMessage());
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        // No es necesario revertir las actualizaciones de datos
        // ya que la migración anterior eliminará las columnas
    }
}
