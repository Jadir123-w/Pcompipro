<?php

namespace Modules\Marketing\Console;

use Hyn\Tenancy\Environment;
use Hyn\Tenancy\Models\Website;
use Illuminate\Console\Command;
use Modules\Marketing\Services\EmailTriageReaderService;

/**
 * Mkt01EmailTriageReadCommand — MKT-01 Email Triage ET1.
 *
 * Lee la bandeja IMAP configurada y persiste los correos no leídos en
 * marketing_emails_received. Diseñado para ejecutarse via cron / scheduler
 * cada N minutos (configurable en `email_polling_minutes`).
 *
 * Uso:
 *   # Procesar el tenant 1, hasta 50 correos
 *   php artisan marketing:email-triage:read --tenant=1
 *
 *   # Override del límite
 *   php artisan marketing:email-triage:read --tenant=1 --limit=100
 *
 *   # Procesar TODOS los tenants registrados (uno por uno)
 *   php artisan marketing:email-triage:read --limit=50
 */
class Mkt01EmailTriageReadCommand extends Command
{
    protected $signature = 'marketing:email-triage:read
                            {--tenant= : ID del tenant (Website) a procesar. Sin esto, procesa TODOS.}
                            {--limit=50 : Máximo de correos a procesar por tenant.}';

    protected $description = 'Lee correos no leídos de la bandeja IMAP del tenant y los persiste en marketing_emails_received para que el clasificador IA (ET2) los procese.';

    public function handle(EmailTriageReaderService $reader)
    {
        $tenantId = $this->option('tenant');
        $limit    = (int) $this->option('limit');

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
        $totalPersisted = 0;

        foreach ($websites as $website) {
            $tenancy->tenant($website);
            $this->info(">>> Tenant {$website->uuid} (ID {$website->id})");

            try {
                $r = $reader->read($limit);

                if (!$r['enabled']) {
                    $this->warn("    (Email Triage deshabilitado o sin credenciales: {$r['error_message']})");
                    continue;
                }

                // Circuit breaker activo: NO es un error sino una protección
                if (!empty($r['circuit_breaker_tripped'])) {
                    $this->warn('    [BREAKER ACTIVO] El bot está pausado para evitar bans del hosting.');
                    $this->line('    ' . $r['error_message']);
                    $exitCode = 2;   // exit code distinto para que el cron / monitoring lo trate diferente
                    continue;
                }

                if (!empty($r['error_message'])) {
                    $this->error("    Error: {$r['error_message']}");
                    $exitCode = 1;
                    continue;
                }

                $this->line(sprintf(
                    '    Fetched: %d | Persisted: %d | Duplicates: %d | Errors: %d',
                    $r['fetched'], $r['persisted'], $r['duplicates'], $r['errors']
                ));
                $totalPersisted += $r['persisted'];

            } catch (\Exception $e) {
                $this->error("    Excepción: {$e->getMessage()}");
                $exitCode = 1;
            }
        }

        $this->line('');
        $this->info("Resumen: {$totalPersisted} correos persistidos en total.");
        return $exitCode;
    }
}
