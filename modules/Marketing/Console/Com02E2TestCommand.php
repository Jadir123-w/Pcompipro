<?php

namespace Modules\Marketing\Console;

use Hyn\Tenancy\Environment;
use Hyn\Tenancy\Models\Website;
use Illuminate\Console\Command;
use Modules\Marketing\Models\PreQuote;
use Modules\Marketing\Models\QuotationProposal;
use Modules\Marketing\Services\ProposalPdfGenerator;
use Modules\Marketing\Services\QuotationAIEngine;

/**
 * Smoke test de E2 (COM-02 Backend IA + PDF).
 *
 * Uso:
 *   php artisan com02:e2-test {prequoteId} --tenant=1 --skip-ai
 *
 * El flag --skip-ai usa directamente el fallback sin llamar al LLM
 * (útil cuando no hay API key configurada o se quiere un test rápido).
 */
class Com02E2TestCommand extends Command
{
    protected $signature = 'com02:e2-test
                            {prequoteId : ID de la prequote a usar}
                            {--tenant=1 : Website ID del tenant}
                            {--skip-ai : Omitir llamada al LLM y usar fallback de config}';

    protected $description = 'Smoke test de la proposal: crear con defaults + generar PDF';

    public function handle()
    {
        // 1) Setear tenant
        $tenantId = (int) $this->option('tenant');
        $website  = Website::find($tenantId);
        if (!$website) {
            $this->error("Tenant {$tenantId} no encontrado.");
            return 1;
        }
        app(Environment::class)->tenant($website);
        $this->info(">>> Tenant: {$website->uuid}");

        // 2) Cargar prequote
        $prequoteId = (int) $this->argument('prequoteId');
        $prequote = PreQuote::with('items', 'empresa', 'contacto')->find($prequoteId);
        if (!$prequote) {
            $this->error("PreQuote #{$prequoteId} no encontrada.");
            return 1;
        }
        $this->info("PreQuote #{$prequote->id}: {$prequote->titulo}");
        $this->line("  Total: S/ " . number_format((float) $prequote->total, 2));
        $this->line("  Ítems: " . $prequote->items->count());

        // 3) Generar content: AI o fallback
        if ($this->option('skip-ai')) {
            $this->warn('Modo --skip-ai: se invoca a AIEngine pero si falla se usa el fallback de config');
        }

        try {
            $aiEngine = app(QuotationAIEngine::class);
            $content = $aiEngine->generateProposalContent($prequote);
        } catch (\Exception $e) {
            $this->error("Error invocando AIEngine: " . $e->getMessage());
            return 1;
        }

        $this->info('');
        $this->info('✓ Content generado (preview):');
        $this->line('  Título: ' . $content['titulo_descriptivo']);
        $this->line('  Destinatario.empresa: ' . ($content['destinatario']['empresa'] ?: '—'));
        $this->line('  Notas encabezado (3): ');
        foreach ($content['notas_encabezado'] as $n) {
            $this->line('    - ' . $n);
        }
        $this->line('  Precios incluyen IGV: ' . ($content['precios_incluyen_igv'] ? 'SI' : 'NO'));
        $this->line('  Incluye datos empresa: ' . ($content['incluye_datos_empresa'] ? 'SI' : 'NO'));
        $this->line('  Incluye datos bancarios: ' . ($content['incluye_datos_bancarios'] ? 'SI' : 'NO'));
        if (isset($content['_fallback']) && $content['_fallback']) {
            $this->warn('  ⚠ Usó FALLBACK (IA no disponible). Razón: ' . $content['interpretation_summary']);
        }

        // 4) Crear o actualizar la proposal
        $proposal = QuotationProposal::where('prequote_id', $prequote->id)->first();
        $isNew = !$proposal;

        if (!$proposal) {
            $proposal = new QuotationProposal();
            $proposal->prequote_id        = $prequote->id;
            $proposal->prefijo_numeracion = 'MAP';
            $proposal->fecha_emision      = date('Y-m-d');
            $proposal->secuencial_dia     = 1;
            $proposal->version_externa    = 1;
            $proposal->numero_cotizacion  = $proposal->buildNumeroCotizacion();
            $proposal->estado             = QuotationProposal::ESTADO_BORRADOR;
            $proposal->moneda             = 'PEN';

            // Cuenta BCP (default de seed)
            $bank = \Illuminate\Support\Facades\DB::connection('tenant')
                ->table('bank_accounts')->orderBy('id')->first();
            if ($bank) {
                $proposal->bank_account_id = $bank->id;
            }
        }

        // Aplicar content AI al modelo
        $dest = $content['destinatario'];
        $proposal->destinatario_nombre     = $dest['nombre'];
        $proposal->destinatario_cargo      = $dest['cargo'];
        $proposal->destinatario_sede       = $dest['sede'];
        $proposal->destinatario_empresa    = $dest['empresa'];
        $proposal->titulo_descriptivo      = $content['titulo_descriptivo'];
        $proposal->notas_encabezado        = json_encode($content['notas_encabezado']);
        $proposal->precios_incluyen_igv    = $content['precios_incluyen_igv'];
        $proposal->moneda                  = $content['moneda'];
        $proposal->condiciones             = json_encode($content['condiciones']);
        $proposal->incluye_datos_empresa   = $content['incluye_datos_empresa'];
        $proposal->incluye_datos_bancarios = $content['incluye_datos_bancarios'];
        $proposal->save();

        $this->info('');
        $this->info('✓ Proposal ' . ($isNew ? 'creada' : 'actualizada') . ': ID #' . $proposal->id);
        $this->line('  N° Cotización: ' . $proposal->numero_cotizacion);
        $this->line('  Estado: ' . $proposal->estado);

        // 5) Generar PDF
        $this->info('');
        $this->info('→ Generando PDF...');
        try {
            $pdfGen = app(ProposalPdfGenerator::class);
            $result = $pdfGen->generate($proposal);
            if ($result['success']) {
                $this->info('✓ PDF generado');
                $this->line('  Path: ' . $result['pdf_path']);
                $this->line('  URL: ' . $result['pdf_url']);
            } else {
                $this->error('✗ PDF falló: ' . $result['message']);
            }
        } catch (\Exception $e) {
            $this->error('✗ Excepción generando PDF: ' . $e->getMessage());
            return 1;
        }

        return 0;
    }
}
