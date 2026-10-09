<?php

namespace Modules\Marketing\Console;

use Hyn\Tenancy\Environment;
use Hyn\Tenancy\Models\Website;
use Illuminate\Console\Command;
use Modules\Marketing\Models\MarketingEmailReceived;
use Modules\Marketing\Services\EmailTriageClassifierService;
use Modules\Marketing\Services\EmailTriageReaderService;

/**
 * Mkt01EmailTriageRunCommand — MKT-01 Email Triage ET4 (orquestador).
 *
 * Combina en una sola pasada los dos pasos del Email Triage que necesitan
 * correrse periódicamente:
 *   1. ET1 — Leer la bandeja IMAP y persistir correos nuevos en
 *            `marketing_emails_received` (status=received).
 *   2. ET2 — Pasar la IA por los correos en estado `received`: clasificar +
 *            generar borrador cuando aplica.
 *
 * Diseñado para ejecutarse desde el scheduler cada N minutos (default: 5).
 * Tener un único comando orquestador es más simple que schedular dos cosas
 * separadas y garantiza que cuando llegan correos nuevos, se clasifican sin
 * esperar al próximo tick del scheduler.
 *
 * Uso:
 *   # Procesar TODOS los tenants (modo cron)
 *   php artisan marketing:email-triage:run
 *
 *   # Procesar solo un tenant
 *   php artisan marketing:email-triage:run --tenant=1
 *
 *   # Límites custom (default: 50 leídos, 20 clasificados)
 *   php artisan marketing:email-triage:run --read-limit=100 --classify-limit=50
 *
 *   # Solo correr la fase de clasificación (saltarse el reader IMAP)
 *   php artisan marketing:email-triage:run --skip-read
 */
class Mkt01EmailTriageRunCommand extends Command
{
    protected $signature = 'marketing:email-triage:run
                            {--tenant= : ID del tenant. Sin esto, procesa TODOS.}
                            {--read-limit=50 : Máximo de correos a leer del IMAP por tenant.}
                            {--classify-limit=20 : Máximo de correos a clasificar con IA por tenant.}
                            {--skip-read : No ejecutar el reader IMAP (solo clasificar pendientes).}
                            {--throttle-seconds=0 : Pausa entre clasificaciones (útil con providers free-tier rate-limited, ej: Gemini Flash free = 5 RPM → usar 13).}';

    protected $description = 'Orquesta el Email Triage: lee IMAP + clasifica con IA + genera borradores. Diseñado para correr via cron cada 5 min.';

    public function handle(EmailTriageReaderService $reader)
    {
        $tenantId        = $this->option('tenant');
        $readLimit       = (int) $this->option('read-limit');
        $classifyLimit   = (int) $this->option('classify-limit');
        $skipRead        = (bool) $this->option('skip-read');
        $throttleSeconds = (int) $this->option('throttle-seconds');

        // Resolver tenants a procesar
        $websites = $tenantId
            ? collect([Website::find($tenantId)])->filter()
            : Website::all();

        if ($websites->isEmpty()) {
            $this->error('No hay tenants registrados o el tenant solicitado no existe.');
            return 1;
        }

        $tenancy   = app(Environment::class);
        $exitCode  = 0;
        $totals    = [
            'tenants_processed'     => 0,
            'imap_persisted'        => 0,
            'imap_duplicates'       => 0,
            'imap_errors'           => 0,
            'classified'            => 0,
            'drafts_created'        => 0,
            'discarded'             => 0,
            'classify_errors'       => 0,
        ];

        foreach ($websites as $website) {
            $tenancy->tenant($website);
            $this->info(">>> Tenant {$website->uuid} (ID {$website->id})");
            $totals['tenants_processed']++;

            // ── Fase 1: lectura IMAP ──
            if (!$skipRead) {
                try {
                    $r = $reader->read($readLimit);

                    if (!$r['enabled']) {
                        $this->warn("    [IMAP] deshabilitado o sin credenciales: {$r['error_message']}");
                    } elseif (!empty($r['error_message'])) {
                        $this->error("    [IMAP] error: {$r['error_message']}");
                        $exitCode = 1;
                        $totals['imap_errors']++;
                    } else {
                        $this->line(sprintf(
                            '    [IMAP] fetched=%d  persisted=%d  duplicates=%d  errors=%d',
                            $r['fetched'], $r['persisted'], $r['duplicates'], $r['errors']
                        ));
                        $totals['imap_persisted']  += $r['persisted'];
                        $totals['imap_duplicates'] += $r['duplicates'];
                        $totals['imap_errors']     += $r['errors'];
                    }
                } catch (\Exception $e) {
                    $this->error("    [IMAP] excepción: {$e->getMessage()}");
                    $exitCode = 1;
                    $totals['imap_errors']++;
                }
            } else {
                $this->line('    [IMAP] (saltado por --skip-read)');
            }

            // ── Fase 2: clasificación + borradores con IA ──
            // Resolvemos el service AHORA que el tenant está activo (lee config de tenant)
            $classifier = app(EmailTriageClassifierService::class);

            $emails = MarketingEmailReceived::pendingClassification()
                ->orderBy('received_at')
                ->limit($classifyLimit)
                ->get();

            if ($emails->isEmpty()) {
                $this->line('    [IA] sin correos pendientes de clasificar.');
                continue;
            }

            $this->line("    [IA] clasificando {$emails->count()} correo(s)..."
                . ($throttleSeconds > 0 ? " (throttle={$throttleSeconds}s entre cada uno)" : ''));

            $first = true;
            foreach ($emails as $email) {
                if (!$first && $throttleSeconds > 0) {
                    sleep($throttleSeconds);
                }
                $first = false;
                try {
                    $r = $classifier->process($email);

                    if (!empty($r['error'])) {
                        $this->error(sprintf('        ✗ Email #%d: %s', $email->id, $r['error']));
                        $exitCode = 1;
                        $totals['classify_errors']++;
                        continue;
                    }

                    $totals['classified']++;
                    if (!empty($r['draft_id'])) {
                        $totals['drafts_created']++;
                        $this->info(sprintf(
                            '        ✓ Email #%d → %s (borrador #%d)',
                            $email->id, $r['classification'], $r['draft_id']
                        ));
                    } else {
                        $totals['discarded']++;
                        $this->line(sprintf(
                            '        ✓ Email #%d → %s (descartado, sin borrador)',
                            $email->id, $r['classification']
                        ));
                    }
                } catch (\Exception $e) {
                    $this->error("        ✗ Email #{$email->id}: excepción {$e->getMessage()}");
                    $exitCode = 1;
                    $totals['classify_errors']++;
                }
            }
        }

        // ── Resumen final ──
        $this->line('');
        $this->info('═══ Resumen Email Triage Run ═══');
        $this->line(sprintf('  Tenants procesados:    %d', $totals['tenants_processed']));
        $this->line(sprintf('  IMAP persistidos:      %d', $totals['imap_persisted']));
        $this->line(sprintf('  IMAP duplicados:       %d', $totals['imap_duplicates']));
        $this->line(sprintf('  IMAP errores:          %d', $totals['imap_errors']));
        $this->line(sprintf('  IA clasificados:       %d', $totals['classified']));
        $this->line(sprintf('  IA borradores creados: %d', $totals['drafts_created']));
        $this->line(sprintf('  IA descartados:        %d', $totals['discarded']));
        $this->line(sprintf('  IA errores:            %d', $totals['classify_errors']));

        return $exitCode;
    }
}
