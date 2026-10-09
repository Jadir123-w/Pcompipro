<?php

namespace Modules\Marketing\Console;

use Hyn\Tenancy\Environment;
use Hyn\Tenancy\Models\Website;
use Illuminate\Console\Command;
use Modules\Marketing\Models\MarketingEmailReceived;
use Modules\Marketing\Services\EmailTriageClassifierService;

/**
 * Mkt01EmailTriageClassifyCommand — MKT-01 Email Triage ET2.
 *
 * Toma correos en estado `received` (los que dejó el reader IMAP de ET1)
 * y los procesa con la IA: clasifica + genera borrador si aplica.
 *
 * Diseñado para correr via cron justo después del reader. Procesa hasta
 * --limit correos en cada ejecución para acotar consumo de tokens.
 *
 * Uso:
 *   php artisan marketing:email-triage:classify --tenant=1
 *   php artisan marketing:email-triage:classify --tenant=1 --limit=20
 *   php artisan marketing:email-triage:classify --limit=10  (todos los tenants)
 */
class Mkt01EmailTriageClassifyCommand extends Command
{
    protected $signature = 'marketing:email-triage:classify
                            {--tenant= : ID del tenant. Sin esto, procesa TODOS.}
                            {--limit=20 : Máximo de correos a procesar por tenant en este run.}';

    protected $description = 'Clasifica correos pendientes (status=received) con IA y genera borrador de respuesta cuando aplica.';

    public function handle()
    {
        $tenantId = $this->option('tenant');
        $limit    = (int) $this->option('limit');

        $websites = $tenantId
            ? collect([Website::find($tenantId)])->filter()
            : Website::all();

        if ($websites->isEmpty()) {
            $this->error('No hay tenants registrados o el tenant solicitado no existe.');
            return 1;
        }

        $tenancy = app(Environment::class);
        $totalProcessed = 0;
        $exitCode = 0;

        foreach ($websites as $website) {
            $tenancy->tenant($website);
            $this->info(">>> Tenant {$website->uuid} (ID {$website->id})");

            // Resolver service AHORA que el tenant está activo (lee config de tenant)
            $service = app(EmailTriageClassifierService::class);

            $emails = MarketingEmailReceived::pendingClassification()
                ->orderBy('received_at')
                ->limit($limit)
                ->get();

            if ($emails->isEmpty()) {
                $this->line('    Sin correos pendientes.');
                continue;
            }

            $this->line("    Procesando {$emails->count()} correo(s)...");

            foreach ($emails as $email) {
                try {
                    $r = $service->process($email);
                    if (!empty($r['error'])) {
                        $this->error(sprintf(
                            '    ✗ Email #%d: %s', $email->id, $r['error']
                        ));
                        $exitCode = 1;
                        continue;
                    }
                    $this->info(sprintf(
                        '    ✓ Email #%d → %s%s',
                        $email->id,
                        $r['classification'],
                        $r['draft_id'] ? " (borrador #{$r['draft_id']})" : ' (sin borrador)'
                    ));
                    $totalProcessed++;
                } catch (\Exception $e) {
                    $this->error("    ✗ Email #{$email->id}: excepción {$e->getMessage()}");
                    $exitCode = 1;
                }
            }
        }

        $this->line('');
        $this->info("Resumen: {$totalProcessed} correo(s) procesados con éxito.");
        return $exitCode;
    }
}
