<?php

namespace Modules\Marketing\Console;

use Hyn\Tenancy\Environment;
use Hyn\Tenancy\Models\Website;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Modules\Comercial\Models\Requerimiento;
use Modules\Marketing\Models\PreQuote;
use Modules\Marketing\Models\QuotationConfig;
use Modules\Marketing\Services\QuotationOrchestrator;

/**
 * COM-02 S6 — Modo autónomo del cotizador.
 *
 * Procesa requerimientos del ERP que aún no tienen precotización generada y
 * deja borradores listos para revisión de la ejecutiva.
 *
 * Sec. 7 doc funcional: "El sistema revisa requerimientos del ERP que aún no
 * tienen cotización y genera borradores de precotización para dejarlos listos
 * en estado de revisión."
 *
 * Solo corre si la flag `auto_prequote_enabled` está activa en
 * quotation_config (default 0). Pensado para crontab cada N minutos.
 *
 * Uso:
 *   php artisan marketing:quotation:auto-prequote                  # todos los tenants, hasta 10 reqs
 *   php artisan marketing:quotation:auto-prequote --tenant=1       # un solo tenant
 *   php artisan marketing:quotation:auto-prequote --limit=5        # máx 5 requerimientos por tenant
 *   php artisan marketing:quotation:auto-prequote --dry-run        # no crea, solo reporta
 *   php artisan marketing:quotation:auto-prequote --force          # ignora flag enabled (uso manual)
 */
class QuotationAutoPrequoteCommand extends Command
{
    protected $signature = 'marketing:quotation:auto-prequote
                            {--tenant= : ID del tenant (Website) a procesar. Sin este parámetro, procesa todos.}
                            {--limit=10 : Máximo de requerimientos a procesar por tenant}
                            {--dry-run : No crea prequotes; solo lista candidatos}
                            {--force : Ignora la flag auto_prequote_enabled (útil para corrida manual)}';

    protected $description = 'Genera precotizaciones automáticas para requerimientos pendientes (modo autónomo COM-02).';

    public function handle()
    {
        $tenantId = $this->option('tenant');
        $limit    = max(1, (int) $this->option('limit'));
        $dryRun   = (bool) $this->option('dry-run');
        $force    = (bool) $this->option('force');

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
            $this->warn('[DRY-RUN] No se crearán prequotes; solo se reportará.');
        }

        $tenancy = app(Environment::class);

        $totals = ['processed' => 0, 'created' => 0, 'skipped' => 0, 'failed' => 0];

        foreach ($websites as $website) {
            $tenancy->tenant($website);

            $stats = $this->processTenant($website, $limit, $dryRun, $force);
            foreach ($stats as $k => $v) {
                $totals[$k] += $v;
            }

            $this->line(sprintf(
                'Tenant #%d (%s): procesados=%d · creados=%d · saltados=%d · fallidos=%d',
                $website->id,
                $website->uuid,
                $stats['processed'],
                $stats['created'],
                $stats['skipped'],
                $stats['failed']
            ));
        }

        $this->info(sprintf(
            'Total → procesados=%d, creados=%d, saltados=%d, fallidos=%d',
            $totals['processed'], $totals['created'], $totals['skipped'], $totals['failed']
        ));
        return 0;
    }

    /**
     * Procesar un tenant.
     *
     * @return array{processed:int, created:int, skipped:int, failed:int}
     */
    protected function processTenant(Website $website, $limit, $dryRun, $force)
    {
        $stats = ['processed' => 0, 'created' => 0, 'skipped' => 0, 'failed' => 0];

        // Verificar flag enabled (a menos que --force)
        if (!$force) {
            try {
                $enabled = QuotationConfig::getValue('auto_prequote_enabled', '0');
                if ((string) $enabled !== '1' && $enabled !== true) {
                    return $stats;
                }
            } catch (\Exception $e) {
                return $stats;
            }
        }

        // Buscar requerimientos pendientes (sin prequote asociada)
        try {
            $candidates = Requerimiento::query()
                ->whereIn('estado', ['abierto', 'pendiente', 'nuevo'])
                ->whereDoesntHave('precotizaciones')
                ->orderBy('created_at', 'asc')
                ->limit($limit)
                ->get();
        } catch (\Exception $e) {
            // El scope/relación puede no existir; fallback simple sin la relación
            try {
                $existingReqIds = PreQuote::whereNotNull('requerimiento_id')->pluck('requerimiento_id')->all();
                $candidates = Requerimiento::query()
                    ->whereIn('estado', ['abierto', 'pendiente', 'nuevo'])
                    ->whereNotIn('id', $existingReqIds)
                    ->orderBy('created_at', 'asc')
                    ->limit($limit)
                    ->get();
            } catch (\Exception $e2) {
                Log::error('QuotationAutoPrequote: no se pudo consultar requerimientos', [
                    'tenant' => $website->id,
                    'error'  => $e2->getMessage(),
                ]);
                return $stats;
            }
        }

        if ($candidates->isEmpty()) {
            return $stats;
        }

        $orchestrator = app(QuotationOrchestrator::class);

        foreach ($candidates as $req) {
            $stats['processed']++;

            $text = trim((string) ($req->descripcion ?? '')) ?: trim((string) ($req->titulo ?? ''));
            if ($text === '') {
                $stats['skipped']++;
                continue;
            }

            if ($dryRun) {
                $this->line(sprintf('  [DRY-RUN] Crearía prequote para Req #%d (%s…)',
                    $req->id, mb_substr($text, 0, 60)));
                continue;
            }

            try {
                $prequote = $orchestrator->generateFromRequerimiento($req->id, null);
                $stats['created']++;
                Log::info('QuotationAutoPrequote: prequote creada', [
                    'tenant'           => $website->id,
                    'requerimiento_id' => $req->id,
                    'prequote_id'      => $prequote->id,
                    'items'            => $prequote->items()->count(),
                ]);
            } catch (\Exception $e) {
                $stats['failed']++;
                Log::error('QuotationAutoPrequote: error creando prequote', [
                    'tenant'           => $website->id,
                    'requerimiento_id' => $req->id,
                    'error'            => $e->getMessage(),
                ]);
            }
        }

        return $stats;
    }
}
