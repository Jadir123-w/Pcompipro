<?php

namespace Modules\Marketing\Console;

use Hyn\Tenancy\Environment;
use Hyn\Tenancy\Models\Website;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Modules\Marketing\Services\QuotationPatternAnalyzer;

/**
 * COM-02 S4 — Análisis de patrones del histórico de cotizaciones.
 *
 * Itera tenants y ejecuta QuotationPatternAnalyzer en cada uno.  Genera
 * sugerencias de mejora de configuración en quotation_system_suggestions
 * que la ejecutiva revisa desde la UI.
 *
 * Pensado para crontab semanal (1x/semana es suficiente — los patrones
 * cambian lentamente). También se puede invocar on-demand desde la UI
 * con el botón "Analizar patrones ahora" del tab Sugerencias.
 *
 * Uso:
 *   php artisan marketing:quotation:analyze-patterns                # todos los tenants
 *   php artisan marketing:quotation:analyze-patterns --tenant=1     # un tenant
 *   php artisan marketing:quotation:analyze-patterns --dry-run      # no escribe BD
 */
class QuotationAnalyzePatternsCommand extends Command
{
    protected $signature = 'marketing:quotation:analyze-patterns
                            {--tenant= : ID del tenant (Website) a procesar. Sin este parámetro, procesa todos.}
                            {--dry-run : No escribe sugerencias en BD; solo reporta cuáles se crearían}';

    protected $description = 'Analiza patrones recurrentes en cotizaciones validadas y genera sugerencias de mejora de configuración (COM-02 S4).';

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
            $this->warn('[DRY-RUN] No se escribirán sugerencias.');
        }

        $tenancy = app(Environment::class);
        $totals = ['detected' => 0, 'created' => 0, 'updated' => 0, 'patterns_examined' => 0];

        foreach ($websites as $website) {
            $tenancy->tenant($website);

            try {
                if ($dryRun) {
                    // En modo dry-run no llamamos al analyzer real (escribiría)
                    // — solo informamos que se ejecutaría
                    $this->line(sprintf(
                        'Tenant #%d (%s): se ejecutaría el analyzer (dry-run, no se escribió nada).',
                        $website->id, $website->uuid
                    ));
                    continue;
                }

                $analyzer = app(QuotationPatternAnalyzer::class);
                $stats = $analyzer->analyze();

                foreach ($stats as $k => $v) {
                    if (isset($totals[$k])) $totals[$k] += $v;
                }

                $this->line(sprintf(
                    'Tenant #%d (%s): patterns=%d detected=%d created=%d updated=%d',
                    $website->id, $website->uuid,
                    $stats['patterns_examined'], $stats['detected'],
                    $stats['created'], $stats['updated']
                ));

            } catch (\Exception $e) {
                $this->error(sprintf('Tenant #%d falló: %s', $website->id, $e->getMessage()));
                Log::error('QuotationAnalyzePatterns: error en tenant', [
                    'tenant_id' => $website->id,
                    'error'     => $e->getMessage(),
                ]);
            }
        }

        if (!$dryRun) {
            $this->info(sprintf(
                'Total → patterns_examined=%d detected=%d created=%d updated=%d',
                $totals['patterns_examined'], $totals['detected'],
                $totals['created'], $totals['updated']
            ));
        }

        return 0;
    }
}
