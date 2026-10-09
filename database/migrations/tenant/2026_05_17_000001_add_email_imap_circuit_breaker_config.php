<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Agregar configuración del Circuit Breaker para Email Triage (MKT-01).
 *
 * Contexto
 * ========
 * El 11-12 de mayo de 2026 nuestro IP fue baneado por el proveedor de hosting
 * porque el bot del Email Triage seguía intentando login IMAP cada 15 minutos
 * con credenciales que estaban mal configuradas. La regla LF_IMAPD del CSF
 * banea cualquier IP que falle 12 logins IMAP en 1 hora.
 *
 * Cálculo del incidente:
 *   - Polling cada 15 min => 4 polls/hora
 *   - ext-imap reintenta 3 veces por sesión ante auth error
 *   - 4 × 3 = 12 fallos/hora = exactamente el límite que dispara el ban
 *
 * Fix
 * ===
 * Este circuit breaker pausa el bot tras N fallos consecutivos de login IMAP
 * (default: 3). Cuando salta, deshabilita automáticamente `email_enabled`
 * para que el cron deje de polear, y registra el motivo del último fallo.
 * La ejecutiva debe corregir las credenciales y resetear el breaker
 * manualmente (vía artisan o futura UI).
 *
 * Idempotente: corre múltiples veces sin efectos secundarios.
 */
class AddEmailImapCircuitBreakerConfig extends Migration
{
    public function up()
    {
        if (!Schema::connection('tenant')->hasTable('marketing_chatbot_config')) {
            return;
        }

        $now = date('Y-m-d H:i:s');

        $rows = [
            // ── Configurable: máximo de fallos consecutivos antes de pausar ──
            [
                'config_key'    => 'email_imap_circuit_breaker_max_failures',
                'config_value'  => '3',
                'value_type'    => 'int',
                'group'         => 'email',
                'display_label' => 'Circuit Breaker: máx. fallos consecutivos',
                'description'   => 'Cuántos fallos consecutivos de login IMAP se toleran antes de pausar el bot automáticamente. Default: 3 (suficiente para detectar credenciales mal configuradas y evitar bans del hosting). Subir a 5-7 si el server IMAP es inestable.',
                'sort_order'    => 200,
            ],

            // ── State: contador automático ──
            [
                'config_key'    => 'email_imap_consecutive_failures',
                'config_value'  => '0',
                'value_type'    => 'int',
                'group'         => 'email',
                'display_label' => '[Auto] Fallos consecutivos del breaker',
                'description'   => 'Contador automático. Se incrementa con cada fallo de login IMAP y se resetea con cada login exitoso. NO editar a mano (usar el comando "php artisan marketing:email-triage:reset-breaker" para resetearlo).',
                'sort_order'    => 210,
            ],

            // ── State: timestamp del último fallo ──
            [
                'config_key'    => 'email_imap_last_failure_at',
                'config_value'  => null,
                'value_type'    => 'string',
                'group'         => 'email',
                'display_label' => '[Auto] Último fallo de IMAP (timestamp)',
                'description'   => 'Fecha y hora del último fallo de login IMAP. Automático.',
                'sort_order'    => 220,
            ],

            // ── State: motivo del último fallo ──
            [
                'config_key'    => 'email_imap_last_failure_reason',
                'config_value'  => null,
                'value_type'    => 'text',
                'group'         => 'email',
                'display_label' => '[Auto] Último fallo de IMAP (motivo)',
                'description'   => 'Mensaje de error del último fallo de login IMAP. Útil para diagnosticar credenciales incorrectas, problemas de red, o bans del firewall del proveedor. Automático.',
                'sort_order'    => 230,
            ],
        ];

        foreach ($rows as $row) {
            $exists = DB::connection('tenant')
                ->table('marketing_chatbot_config')
                ->where('config_key', $row['config_key'])
                ->exists();

            if ($exists) {
                continue;
            }

            // Completar campos con defaults
            $row['secret_env_var'] = null;
            $row['is_sensitive']   = 0;
            $row['is_encrypted']   = 0;
            $row['created_at']     = $now;
            $row['updated_at']     = $now;

            DB::connection('tenant')->table('marketing_chatbot_config')->insert($row);
        }
    }

    public function down()
    {
        if (!Schema::connection('tenant')->hasTable('marketing_chatbot_config')) {
            return;
        }

        DB::connection('tenant')
            ->table('marketing_chatbot_config')
            ->whereIn('config_key', [
                'email_imap_circuit_breaker_max_failures',
                'email_imap_consecutive_failures',
                'email_imap_last_failure_at',
                'email_imap_last_failure_reason',
            ])
            ->delete();
    }
}
