<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * COM-02 S6 — Actualiza la descripción de auto_prequote_enabled.
 *
 * La key auto_prequote_enabled ya existía desde el seed inicial pero su
 * descripción no documentaba cómo activarla ni qué comando la consume.
 * Esta migración actualiza la descripción para que la ejecutiva entienda
 * el ciclo: editar config → flag=1 → cron `marketing:quotation:auto-prequote`
 * procesa requerimientos pendientes.
 *
 * Migración idempotente.
 */
class UpdateAutoPrequoteDescription extends Migration
{
    public function up()
    {
        if (!Schema::connection('tenant')->hasTable('quotation_config')) {
            return;
        }

        DB::connection('tenant')
            ->table('quotation_config')
            ->where('config_key', 'auto_prequote_enabled')
            ->update([
                'description' => 'Modo autónomo (sec 7 doc funcional): si está activo, el comando '
                    . '`marketing:quotation:auto-prequote` (programable vía cron) procesa los '
                    . 'requerimientos pendientes del ERP y deja borradores de precotización en '
                    . 'estado "borrador" listos para revisión humana. La ejecutiva no necesita '
                    . 'iniciar manualmente cada cotización.',
                'updated_at'  => now(),
            ]);
    }

    public function down()
    {
        // No-op: la key sigue existiendo
    }
}
