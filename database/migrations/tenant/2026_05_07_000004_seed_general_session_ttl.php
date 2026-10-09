<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Refactor "configs operativas a BD", parte III: timeouts/limits.
 *
 * Casi todas las claves operativas ya existían en marketing_chatbot_config
 * (general_timeout_1, general_timeout_2, general_max_messages_per_session,
 * general_default_assigned_to, etc.) — el problema era que el código
 * consumidor las ignoraba y leía del .env. La parte III arregla los
 * lugares de lectura para que prioricen BD.
 *
 * Esta migración solo agrega la única key que faltaba: general_session_ttl
 * (TTL del cache de sesión del chatbot). Las demás keys ya estaban sembradas
 * en migraciones anteriores.
 */
class SeedGeneralSessionTtl extends Migration
{
    public function up()
    {
        $exists = DB::connection('tenant')
            ->table('marketing_chatbot_config')
            ->where('config_key', 'general_session_ttl')
            ->exists();
        if ($exists) return;

        $now = date('Y-m-d H:i:s');

        DB::connection('tenant')->table('marketing_chatbot_config')->insert([
            'config_key'    => 'general_session_ttl',
            'config_value'  => '1800',
            'value_type'    => 'int',
            'options'       => null,
            'group'         => 'general',
            'display_label' => 'TTL de sesión del chatbot (segundos)',
            'description'   => 'Cuánto tiempo permanece una sesión de chat en el cache antes de expirar por inactividad. Default 1800 = 30 min.',
            'is_sensitive'  => 0,
            'is_encrypted'  => 0,
            'sort_order'    => 110,
            'created_at'    => $now,
            'updated_at'    => $now,
        ]);
    }

    public function down()
    {
        DB::connection('tenant')->table('marketing_chatbot_config')
            ->where('config_key', 'general_session_ttl')
            ->delete();
    }
}
