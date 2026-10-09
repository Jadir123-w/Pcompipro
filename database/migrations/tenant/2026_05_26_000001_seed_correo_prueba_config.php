<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * COM-02 — Correo de prueba de la Cotización Final (Req #14).
 *
 * Siembra la clave `correo_prueba_interno` en quotation_config. Es la
 * dirección a la que el botón "Enviar correo de prueba" envía la propuesta
 * (típicamente una casilla interna de Compipro, para que la ejecutiva vea
 * cómo le llegará al cliente antes de emitir de verdad).
 *
 * Se siembra vacía: la ejecutiva la configura desde la UI (tab Empresa de
 * "Configuración del Cotizador"). El endpoint configUpdate usa firstOrFail,
 * por eso la clave DEBE existir antes de poder editarse — de ahí este seed.
 *
 * Migración aditiva e idempotente: solo inserta si la key no existe.
 */
class SeedCorreoPruebaConfig extends Migration
{
    public function up()
    {
        if (!Schema::connection('tenant')->hasTable('quotation_config')) {
            return;
        }

        $exists = DB::connection('tenant')
            ->table('quotation_config')
            ->where('config_key', 'correo_prueba_interno')
            ->exists();

        if ($exists) {
            return;
        }

        $now = date('Y-m-d H:i:s');

        DB::connection('tenant')->table('quotation_config')->insert([
            'config_key'   => 'correo_prueba_interno',
            'config_value' => '',
            'config_type'  => 'string',
            'description'  => 'Dirección de correo interna a la que se envía la propuesta al pulsar "Enviar correo de prueba" en la Cotización Final. No afecta el envío al cliente.',
            'created_at'   => $now,
            'updated_at'   => $now,
        ]);
    }

    public function down()
    {
        if (!Schema::connection('tenant')->hasTable('quotation_config')) {
            return;
        }

        DB::connection('tenant')
            ->table('quotation_config')
            ->where('config_key', 'correo_prueba_interno')
            ->delete();
    }
}
