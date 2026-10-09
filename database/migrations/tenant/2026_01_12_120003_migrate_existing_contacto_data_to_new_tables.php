<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MigrateExistingContactoDataToNewTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Verificar que las tablas destino existen
        if (!Schema::hasTable('contacto_telefonos') || !Schema::hasTable('contacto_emails')) {
            return;
        }

        // Las columnas `es_principal` y `estado` se agregan en una migración posterior.
        // En una base nueva esta migración corre antes de que existan y, como además
        // no hay datos antiguos que trasladar, corresponde salir sin hacer nada en
        // lugar de intentar los INSERT y registrar advertencias en el log.
        if (!Schema::hasColumn('contacto_telefonos', 'estado') ||
            !Schema::hasColumn('contacto_emails', 'estado')) {
            return;
        }

        // Migrar teléfonos principales desde tabla contacto
        try {
            DB::statement("
                INSERT INTO `contacto_telefonos` (`contacto_id`, `telefono`, `tipo`, `es_principal`, `estado`, `created_at`, `updated_at`)
                SELECT 
                    `id` as contacto_id,
                    `telefono`,
                    'fijo' as tipo,
                    1 as es_principal,
                    1 as estado,
                    `created_at`,
                    `updated_at`
                FROM `contacto`
                WHERE `telefono` IS NOT NULL 
                  AND `telefono` != ''
                  AND NOT EXISTS (
                    SELECT 1 FROM `contacto_telefonos` ct 
                    WHERE ct.contacto_id = contacto.id 
                      AND ct.telefono = contacto.telefono
                      AND ct.estado = 1
                  )
            ");
        } catch (\Exception $e) {
            \Log::warning('Error migrando teléfonos: ' . $e->getMessage());
        }

        // Migrar celulares desde tabla contacto
        try {
            DB::statement("
                INSERT INTO `contacto_telefonos` (`contacto_id`, `telefono`, `tipo`, `es_principal`, `estado`, `created_at`, `updated_at`)
                SELECT 
                    `id` as contacto_id,
                    `celular`,
                    'movil' as tipo,
                    0 as es_principal,
                    1 as estado,
                    `created_at`,
                    `updated_at`
                FROM `contacto`
                WHERE `celular` IS NOT NULL 
                  AND `celular` != ''
                  AND NOT EXISTS (
                    SELECT 1 FROM `contacto_telefonos` ct 
                    WHERE ct.contacto_id = contacto.id 
                      AND ct.telefono = contacto.celular
                      AND ct.estado = 1
                  )
            ");
        } catch (\Exception $e) {
            \Log::warning('Error migrando celulares: ' . $e->getMessage());
        }

        // Migrar emails principales desde tabla contacto
        try {
            DB::statement("
                INSERT INTO `contacto_emails` (`contacto_id`, `email`, `es_principal`, `estado`, `created_at`, `updated_at`)
                SELECT 
                    `id` as contacto_id,
                    `email`,
                    1 as es_principal,
                    1 as estado,
                    `created_at`,
                    `updated_at`
                FROM `contacto`
                WHERE `email` IS NOT NULL 
                  AND `email` != ''
                  AND NOT EXISTS (
                    SELECT 1 FROM `contacto_emails` ce 
                    WHERE ce.contacto_id = contacto.id 
                      AND ce.email = contacto.email
                      AND ce.estado = 1
                  )
            ");
        } catch (\Exception $e) {
            \Log::warning('Error migrando emails: ' . $e->getMessage());
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // No hacer rollback de la migración de datos
    }
}
