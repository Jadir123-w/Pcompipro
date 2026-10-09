<?php

namespace Modules\Marketing\Console;

use Hyn\Tenancy\Environment;
use Hyn\Tenancy\Models\Website;
use Illuminate\Console\Command;
use Modules\Marketing\Models\PreQuote;
use Modules\Marketing\Services\QuotationAIEngine;

/**
 * Smoke test E4 — Sugerencia IA de proveedores por ítem.
 *
 * Uso:
 *   php artisan com02:e4-test {prequoteId} --item={itemId} --tenant=1
 *
 *   Si no pasa --item, usa el primer ítem de la prequote.
 */
class Com02E4TestCommand extends Command
{
    protected $signature = 'com02:e4-test
                            {prequoteId : ID de la prequote}
                            {--item= : ID del item a analizar (default: primero de la prequote)}
                            {--tenant=1 : Website ID}';

    protected $description = 'Smoke test: pide sugerencias IA de proveedores para un ítem de una prequote';

    public function handle()
    {
        // 1) Tenant
        $tenantId = (int) $this->option('tenant');
        $website  = Website::find($tenantId);
        if (!$website) {
            $this->error("Tenant {$tenantId} no encontrado.");
            return 1;
        }
        app(Environment::class)->tenant($website);
        $this->info(">>> Tenant: {$website->uuid}");

        // 2) Prequote
        $prequoteId = (int) $this->argument('prequoteId');
        $prequote = PreQuote::with('items')->find($prequoteId);
        if (!$prequote) {
            $this->error("PreQuote #{$prequoteId} no encontrada.");
            return 1;
        }

        $this->info("PreQuote #{$prequote->id}: {$prequote->titulo}");
        $this->line('  Items en la prequote: ' . $prequote->items->count());

        if ($prequote->items->count() === 0) {
            $this->error('La prequote no tiene items. Nada que analizar.');
            return 1;
        }

        // 3) Item
        $itemId = $this->option('item');
        $item = $itemId
            ? $prequote->items->firstWhere('id', (int) $itemId)
            : $prequote->items->first();

        if (!$item) {
            $this->error('No se encontró el ítem solicitado.');
            return 1;
        }

        $this->line('');
        $this->info('Ítem a analizar:');
        $this->line("  ID: #{$item->id}");
        $this->line("  Producto: {$item->product_name}");
        $this->line("  Cantidad: {$item->quantity}");
        if ($item->material)         $this->line("  Material: {$item->material}");
        if ($item->personalizacion)  $this->line("  Personalización: {$item->personalizacion}");
        if ($item->supplier_name)    $this->line("  Proveedor actual: {$item->supplier_name}");

        // 4) Invocar IA
        $this->line('');
        $this->info('→ Consultando IA...');
        try {
            $engine = app(QuotationAIEngine::class);
            $result = $engine->suggestSuppliersForItem($item);
        } catch (\Exception $e) {
            $this->error('Excepción: ' . $e->getMessage());
            return 1;
        }

        // 5) Mostrar resultado
        $this->line('');
        if (isset($result['_fallback']) && $result['_fallback']) {
            $this->warn('⚠ Fallback (IA no disponible): ' . ($result['summary'] ?? ''));
        } else {
            $this->info('✓ IA respondió');
            $this->line('  Summary: ' . ($result['summary'] ?? ''));
            $this->line('  Best match: ' . ($result['best_match_rationale'] ?? ''));
        }

        $this->line('');
        $suggestions = $result['suggestions'] ?? [];
        $this->info('Sugerencias (' . count($suggestions) . '):');
        $this->line('');

        foreach ($suggestions as $i => $s) {
            $icon = $i === 0 ? '⭐' : '  ';
            $this->line("{$icon} #" . ($i + 1) . ' — ' . ($s['supplier_name'] ?? 'Sin nombre') .
                        ' (' . ($s['supplier_category'] ?? '—') . ')');
            $this->line("     Costo unit est: S/ " . number_format((float) ($s['costo_unitario_estimado'] ?? 0), 2));
            $this->line("     Tiempo est: " . ($s['tiempo_estimado_dias'] ?? '—'));
            $this->line("     Confianza: {$s['confianza']}%");
            $this->line("     Justificación: " . substr($s['justificacion'] ?? '', 0, 120));
            if (!empty($s['alertas'])) {
                foreach ($s['alertas'] as $a) {
                    $this->line("     ⚠ {$a}");
                }
            }
            $this->line('');
        }

        return 0;
    }
}
