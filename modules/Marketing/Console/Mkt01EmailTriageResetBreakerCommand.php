<?php

namespace Modules\Marketing\Console;

use Hyn\Tenancy\Environment;
use Hyn\Tenancy\Models\Website;
use Illuminate\Console\Command;
use Modules\Marketing\Models\ChatbotConfig;

/**
 * Mkt01EmailTriageResetBreakerCommand — MKT-01 Email Triage.
 *
 * Resetea el circuit breaker del Email Triage para reactivar el polling IMAP
 * después de que el sistema lo pausó automáticamente por N fallos consecutivos
 * de login.
 *
 * El uso típico es:
 *   1. El bot detecta credenciales mal configuradas → recordFailure() acumula
 *      3 fallos consecutivos → setea email_enabled=0 + deja un mensaje en
 *      email_imap_last_failure_reason.
 *   2. La ejecutiva diagnostica el problema (credenciales viejas, password
 *      rotado, hosting con problemas, etc.) y corrige las credenciales en
 *      Config Chatbot.
 *   3. Corre este comando para resetear el contador a 0 y re-activar
 *      email_enabled.
 *   4. El siguiente cron de marketing:email-triage:read va a intentar de nuevo.
 *
 * Uso:
 *   # Reset del tenant 1
 *   php artisan marketing:email-triage:reset-breaker --tenant=1
 *
 *   # Reset SIN re-activar el switch (solo limpia el contador). Útil para
 *   # probar con --dry-run primero o si querés activarlo a mano desde la UI.
 *   php artisan marketing:email-triage:reset-breaker --tenant=1 --no-enable
 *
 *   # Solo mostrar el estado, sin modificar nada
 *   php artisan marketing:email-triage:reset-breaker --tenant=1 --status
 */
class Mkt01EmailTriageResetBreakerCommand extends Command
{
    protected $signature = 'marketing:email-triage:reset-breaker
                            {--tenant= : ID del tenant (Website) a resetear. Sin esto, todos los tenants.}
                            {--no-enable : NO re-activar email_enabled. Solo limpia el contador.}
                            {--status : Mostrar el estado del breaker sin modificar nada.}';

    protected $description = 'Resetea el circuit breaker del Email Triage (contador de fallos consecutivos de IMAP) y opcionalmente re-activa email_enabled.';

    public function handle()
    {
        $tenantId = $this->option('tenant');
        $statusOnly = (bool) $this->option('status');
        $skipEnable = (bool) $this->option('no-enable');

        // Resolver tenants a procesar
        if ($tenantId) {
            $website = Website::find($tenantId);
            if (!$website) {
                $this->error("Tenant {$tenantId} no encontrado.");
                return 1;
            }
            $websites = collect([$website]);
        } else {
            $websites = Website::all();
            if ($websites->isEmpty()) {
                $this->error('No hay tenants registrados.');
                return 1;
            }
        }

        $tenancy = app(Environment::class);
        $exitCode = 0;

        foreach ($websites as $website) {
            $tenancy->tenant($website);
            $this->line('');
            $this->info(">>> Tenant {$website->uuid} (ID {$website->id})");

            try {
                $current     = (int)    ChatbotConfig::get('email_imap_consecutive_failures', 0);
                $max         = (int)    ChatbotConfig::get('email_imap_circuit_breaker_max_failures', 3);
                $lastAt      = (string) ChatbotConfig::get('email_imap_last_failure_at', '');
                $lastReason  = (string) ChatbotConfig::get('email_imap_last_failure_reason', '');
                $enabled     = (bool)   ChatbotConfig::get('email_enabled', false);

                $this->line('    Estado actual:');
                $this->line("      email_enabled                = " . ($enabled ? 'true' : 'false'));
                $this->line("      consecutive_failures         = {$current}");
                $this->line("      max_failures (límite)        = {$max}");
                $this->line("      last_failure_at              = " . ($lastAt ?: '(ninguno)'));
                $this->line("      last_failure_reason          = " . ($lastReason ?: '(ninguno)'));
                $this->line("      breaker tripped?             = " . ($current >= $max ? 'SÍ — bot pausado' : 'no'));

                if ($statusOnly) {
                    continue;
                }

                if ($current === 0 && !$lastAt && !$lastReason) {
                    $this->line('    Nada que resetear (contador ya en 0).');
                    if ($skipEnable) continue;
                    if ($enabled)    continue;
                }

                // Reset
                ChatbotConfig::set('email_imap_consecutive_failures', 0);
                ChatbotConfig::set('email_imap_last_failure_at', null);
                ChatbotConfig::set('email_imap_last_failure_reason', null);
                $this->info('    ✓ Contador del breaker reseteado a 0.');

                if (!$skipEnable) {
                    ChatbotConfig::set('email_enabled', true);
                    $this->info('    ✓ email_enabled reactivado.');
                } else {
                    $this->warn('    --no-enable: email_enabled queda como está (' . ($enabled ? 'true' : 'false') . ').');
                }

                $this->line('    Listo. El próximo cron de marketing:email-triage:read va a intentar conectar al IMAP.');

            } catch (\Exception $e) {
                $this->error("    Excepción: {$e->getMessage()}");
                $exitCode = 1;
            }
        }

        $this->line('');
        return $exitCode;
    }
}
