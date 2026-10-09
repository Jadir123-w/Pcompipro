<?php

namespace Modules\Marketing\Console;

use Carbon\Carbon;
use Hyn\Tenancy\Environment;
use Hyn\Tenancy\Models\Website;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Modules\Marketing\Models\ChatbotConfig;
use Modules\Marketing\Models\ChatbotSession;

/**
 * Comando de cierre automático de sesiones del chatbot MKT-01.
 *
 * Itera todas las sesiones con status=active cuyo last_activity_at sea más
 * antiguo que el timeout configurado, y las cierra distinguiendo:
 *
 *   - 'timeout_inactivity'  → la sesión tuvo al menos 1 mensaje del usuario
 *                             pero después dejó de responder.
 *   - 'abandoned'           → la sesión nunca recibió mensaje del usuario tras
 *                             la apertura (bot saludó pero nadie escribió).
 *
 * Configurable vía marketing_chatbot_config:
 *   - general_timeout_2 (segundos): umbral para cierre por inactividad.
 *
 * Pensado para correr cada minuto via Kernel\schedule (hourly/everyMinute) o
 * Tasks del ERP (App\Models\Tenant\Task).
 *
 * Uso:
 *   php artisan marketing:chatbot:close-expired              → todos los tenants
 *   php artisan marketing:chatbot:close-expired --tenant=1   → un tenant
 *   php artisan marketing:chatbot:close-expired --dry-run    → no escribe, solo reporta
 */
class ChatbotCloseExpiredCommand extends Command
{
    protected $signature = 'marketing:chatbot:close-expired
                            {--tenant= : ID del tenant (Website) a procesar. Sin este parámetro, procesa todos.}
                            {--dry-run : No cierra nada; solo reporta cuántas sesiones se cerrarían.}';

    protected $description = 'Cierra sesiones del chatbot MKT-01 inactivas (timeout) o abandonadas (sin respuesta del usuario).';

    public function handle()
    {
        $tenantId = $this->option('tenant');
        $dryRun   = (bool) $this->option('dry-run');

        if ($tenantId) {
            $website = Website::find($tenantId);
            if (!$website) {
                $this->error("Tenant con ID {$tenantId} no encontrado.");
                return 1;
            }
            $websites = collect([$website]);
        } else {
            $websites = Website::all();
            if ($websites->isEmpty()) {
                $this->info('No hay tenants registrados.');
                return 0;
            }
        }

        if ($dryRun) {
            $this->warn('[DRY-RUN] No se cerrarán sesiones; solo se reportará.');
        }

        $tenancy = app(Environment::class);

        $totalsByTenant = [];

        foreach ($websites as $website) {
            $tenancy->tenant($website);

            $stats = $this->processTenant($website, $dryRun);
            $totalsByTenant[$website->id] = $stats;

            $this->line(sprintf(
                'Tenant #%d (%s): timeout=%d, abandoned=%d, ya_cerradas_por_otro=%d',
                $website->id,
                $website->uuid,
                $stats['timeout'],
                $stats['abandoned'],
                $stats['skipped']
            ));
        }

        $this->info('Hecho.');
        return 0;
    }

    /**
     * Procesa el cierre de sesiones expiradas para un tenant.
     *
     * @param  Website  $website
     * @param  bool     $dryRun
     * @return array{timeout:int, abandoned:int, skipped:int}
     */
    protected function processTenant(Website $website, $dryRun)
    {
        $stats = ['timeout' => 0, 'abandoned' => 0, 'skipped' => 0];

        // Resolver timeout desde config (default 150 seg = 2.5 min).
        $timeoutSeconds = $this->resolveTimeoutSeconds();

        $threshold = Carbon::now()->subSeconds($timeoutSeconds);

        $expired = ChatbotSession::where('status', ChatbotSession::STATUS_ACTIVE)
            ->where('last_activity_at', '<', $threshold)
            ->get();

        if ($expired->isEmpty()) {
            return $stats;
        }

        foreach ($expired as $session) {
            // Re-chequear que sigue activa (race condition)
            if ($session->status !== ChatbotSession::STATUS_ACTIVE) {
                $stats['skipped']++;
                continue;
            }

            $reason = $this->detectCloseReason($session);

            if ($dryRun) {
                if ($reason === 'abandoned') {
                    $stats['abandoned']++;
                } else {
                    $stats['timeout']++;
                }
                continue;
            }

            try {
                $session->update([
                    'status'        => ChatbotSession::STATUS_CLOSED,
                    'closed_reason' => $reason,
                    'closed_at'     => Carbon::now(),
                ]);

                if ($reason === 'abandoned') {
                    $stats['abandoned']++;
                } else {
                    $stats['timeout']++;
                }
            } catch (\Exception $e) {
                Log::error('ChatbotCloseExpiredCommand: error cerrando sesión', [
                    'tenant_id'  => $website->id,
                    'session_id' => $session->session_id,
                    'error'      => $e->getMessage(),
                ]);
            }
        }

        return $stats;
    }

    /**
     * Determina si la sesión se cierra como 'abandoned' (bot saludó y nadie
     * respondió) o 'timeout_inactivity' (hubo conversación pero se cortó).
     *
     * Heurística: si hay un lead asociado o si el contador not_understood es
     * > 0, hubo interacción real. En caso contrario, miramos el cache de
     * mensajes via SessionManager — pero para no acoplar al cache (que tiene
     * TTL), usamos un proxy: si la sesión tiene <60s de antigüedad y nunca
     * fue escalada, probablemente sea abandoned.
     *
     * Para mantenerlo simple y robusto a falta de columna message_count en BD,
     * usamos:
     *   - lead_id no nulo  → timeout_inactivity (el usuario llegó a registrarse)
     *   - not_understood_count > 0 → timeout_inactivity (hubo intercambio)
     *   - en otro caso → abandoned
     *
     * @param  ChatbotSession  $session
     * @return string  'timeout_inactivity' | 'abandoned'
     */
    protected function detectCloseReason(ChatbotSession $session)
    {
        if (!empty($session->lead_id)) {
            return 'timeout_inactivity';
        }
        if ((int) $session->not_understood_count > 0) {
            return 'timeout_inactivity';
        }
        return 'abandoned';
    }

    /**
     * Resuelve el timeout (en segundos) desde la config del chatbot.
     *
     * Orden de búsqueda:
     *   1) config key 'general_timeout_2' en marketing_chatbot_config
     *   2) config Laravel marketing.chatbot.session_ttl
     *   3) Default: 150 (2.5 min)
     *
     * @return int
     */
    protected function resolveTimeoutSeconds()
    {
        try {
            $configured = ChatbotConfig::get('general_timeout_2');
            if ($configured !== null) {
                $val = (int) $configured;
                if ($val > 0) {
                    return $val;
                }
            }
        } catch (\Exception $e) {
            // tabla puede no existir todavía en este tenant — fallback abajo
        }

        $fallback = (int) config('marketing.chatbot.session_ttl', 150);
        return $fallback > 0 ? $fallback : 150;
    }
}
