<?php

namespace App\Console\Commands;

use App\Events\Comercial\EmpresaSyncRequested;
use App\Models\Tenant\Person;
use Hyn\Tenancy\Environment;
use Hyn\Tenancy\Models\Website;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\Comercial\Models\Empresa;

class SyncEmpresasToPersonsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'empresas:sync-to-persons
                            {--empresa-id= : Sync a specific empresa by ID}
                            {--force : Force re-sync even if person already exists}
                            {--tenant= : Specific tenant ID to sync (optional)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Synchronize empresas (companies) to the persons table for all tenants';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $tenantId = $this->option('tenant');
        
        if ($tenantId) {
            // Sync specific tenant
            $website = Website::find($tenantId);
            if (!$website) {
                $this->error("Tenant with ID {$tenantId} not found.");
                return 1;
            }
            $this->syncTenant($website);
        } else {
            // Sync all tenants
            $websites = Website::all();
            $totalTenants = $websites->count();
            
            $this->info("Found {$totalTenants} tenant(s) to process.");
            $this->line('');
            
            foreach ($websites as $index => $website) {
                $this->info("Processing tenant " . ($index + 1) . " of {$totalTenants} (ID: {$website->id})...");
                $this->syncTenant($website);
                $this->line('');
            }
            
            $this->info("✓ All tenants processed successfully!");
        }

        return 0;
    }

    /**
     * Sync empresas for a specific tenant.
     *
     * @param Website $website
     * @return void
     */
    protected function syncTenant(Website $website)
    {
        // Set tenant context
        $tenancy = app(Environment::class);
        $tenancy->tenant($website);

        $this->line("Tenant set: {$website->uuid}");

        DB::connection('tenant')->transaction(function () {
            $empresaId = $this->option('empresa-id');
            $force = $this->option('force');

            if ($empresaId) {
                // Sync specific empresa
                $this->syncSingleEmpresa($empresaId, $force);
            } else {
                // Bulk sync all empresas
                $this->syncAllEmpresas($force);
            }
        });
    }

    /**
     * Sync a single empresa to persons table.
     *
     * @param int $empresaId
     * @param bool $force
     * @return void
     */
    protected function syncSingleEmpresa($empresaId, $force = false)
    {
        $this->line("  → Syncing Empresa ID: {$empresaId}...");

        $empresa = Empresa::find($empresaId);

        if (!$empresa) {
            $this->error("  ✗ Empresa with ID {$empresaId} not found.");
            return;
        }

        // Dispatch event
        event(new EmpresaSyncRequested($empresa, $force));

        $this->info("  ✓ Empresa '{$empresa->razon_social}' (RUC: {$empresa->ruc}) synced successfully.");
    }

    /**
     * Sync all empresas to persons table.
     *
     * @param bool $force
     * @return void
     */
    protected function syncAllEmpresas($force = false)
    {
        $empresas = Empresa::whereNull('deleted_at')
            ->where('estado', 1)
            ->get();

        $totalEmpresas = $empresas->count();

        if ($totalEmpresas === 0) {
            $this->line("  No active empresas found to sync.");
            return;
        }

        $this->line("  Found {$totalEmpresas} active empresa(s) to process.");

        if ($force) {
            $this->warn("  ⚠ Force mode enabled: Will re-sync all empresas.");
        }

        $bar = $this->output->createProgressBar($totalEmpresas);
        $bar->setFormat('  %current%/%max% [%bar%] %percent:3s%%');
        $bar->start();

        $stats = [
            'total' => $totalEmpresas,
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => 0,
        ];

        foreach ($empresas as $empresa) {
            try {
                // Check if person already exists
                $person = Person::query()
                    ->where('number', $empresa->ruc)
                    ->where('type', 'customers')
                    ->first();

                $existedBefore = $person !== null;

                // Dispatch event to sync
                event(new EmpresaSyncRequested($empresa, $force));

                // Check again after sync
                $personAfter = Person::query()
                    ->where('number', $empresa->ruc)
                    ->where('type', 'customers')
                    ->first();

                if (!$existedBefore && $personAfter) {
                    $stats['created']++;
                } elseif ($existedBefore && $force) {
                    $stats['updated']++;
                } elseif ($existedBefore && !$force) {
                    $stats['skipped']++;
                }

            } catch (\Exception $e) {
                $stats['errors']++;
                // Log error but continue processing
            }

            $bar->advance();
        }

        $bar->finish();
        $this->line('');

        // Display statistics
        $this->line("  Results:");
        $this->table(
            ['Metric', 'Count'],
            [
                ['Total Empresas', $stats['total']],
                ['Created', $stats['created']],
                ['Updated', $stats['updated']],
                ['Skipped', $stats['skipped']],
                ['Errors', $stats['errors']],
            ]
        );

        if ($stats['errors'] > 0) {
            $this->warn("  ⚠ {$stats['errors']} error(s) occurred. Check logs for details.");
        }
    }
}

