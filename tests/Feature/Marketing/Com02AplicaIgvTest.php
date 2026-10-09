<?php

namespace Tests\Feature\Marketing;

use Hyn\Tenancy\Environment;
use Hyn\Tenancy\Models\Website;
use Illuminate\Support\Facades\Schema;
use Modules\Marketing\Models\QuotationProposal;
use Modules\Marketing\Services\ProposalPdfGenerator;
use Tests\TestCase;

/**
 * Com02AplicaIgvTest — Tratamiento de IGV en la Cotización Final.
 *
 * aplica_igv decide si la cotización lleva IGV y AFECTA el total mostrado:
 *   true  → Subtotal + IGV (18%) + TOTAL FACTURADO
 *   false → Subtotal + TOTAL (Sin IGV)
 *
 * El motor de cálculo no cambia: subtotal/igv/total se siguen calculando
 * completos; aplica_igv solo gobierna la presentación (PDF + frontend).
 */
class Com02AplicaIgvTest extends TestCase
{
    /** @var QuotationProposal|null */
    private $proposal;

    /** @var bool|null Valor previo para restaurar */
    private $prevAplica = null;

    protected function setUp(): void
    {
        parent::setUp();

        config(['database.connections.system.database' => 'dberp']);
        config(['database.connections.mysql.database'  => 'dberp']);
        \Illuminate\Support\Facades\DB::purge('system');
        \Illuminate\Support\Facades\DB::purge('mysql');

        $website = Website::first();
        if (!$website) {
            $this->markTestSkipped('Sin tenants registrados');
        }
        app(Environment::class)->tenant($website);

        $this->proposal = QuotationProposal::with('prequote')->orderByDesc('id')->first();
        if (!$this->proposal) {
            $this->markTestSkipped('Sin proposals en el tenant para probar aplica_igv');
        }
        $this->prevAplica = (bool) $this->proposal->aplica_igv;
    }

    protected function tearDown(): void
    {
        try {
            if ($this->proposal && $this->prevAplica !== null) {
                $this->proposal->update(['aplica_igv' => $this->prevAplica]);
            }
        } catch (\Exception $e) {}
        parent::tearDown();
    }

    /** @test */
    public function la_columna_aplica_igv_existe()
    {
        $this->assertTrue(
            Schema::connection('tenant')->hasColumn('quotation_proposals', 'aplica_igv'),
            'La migración 2026_06_04_000001 debe estar aplicada en el tenant de tests.'
        );
    }

    /** @test */
    public function aplica_igv_se_castea_a_boolean()
    {
        $this->proposal->update(['aplica_igv' => 1]);
        $this->assertTrue($this->proposal->fresh()->aplica_igv);

        $this->proposal->update(['aplica_igv' => 0]);
        $this->assertFalse($this->proposal->fresh()->aplica_igv);
    }

    /** @test */
    public function con_aplica_igv_el_pdf_muestra_igv_y_total_facturado()
    {
        $this->proposal->update(['aplica_igv' => true]);

        $html = $this->renderPdfHtml($this->proposal->fresh('prequote'));

        $this->assertStringContainsString('IGV (18%)', $html);
        $this->assertStringContainsString('TOTAL FACTURADO', $html);
        $this->assertStringNotContainsString('TOTAL (Sin IGV)', $html);
    }

    /** @test */
    public function sin_aplica_igv_el_pdf_muestra_total_sin_igv_sin_fila_igv()
    {
        $this->proposal->update(['aplica_igv' => false]);

        $html = $this->renderPdfHtml($this->proposal->fresh('prequote'));

        $this->assertStringContainsString('TOTAL (Sin IGV)', $html);
        $this->assertStringNotContainsString('IGV (18%)', $html);
        $this->assertStringNotContainsString('TOTAL FACTURADO', $html);
    }

    /** @test */
    public function la_columna_de_costo_siempre_es_sin_igv()
    {
        // Los valores unitarios son sin IGV en ambos modos: la cabecera no debe
        // cambiar con aplica_igv.
        $this->proposal->update(['aplica_igv' => true]);
        $htmlCon = $this->renderPdfHtml($this->proposal->fresh('prequote'));
        $this->assertStringContainsString('Costo SIN IGV', $htmlCon);

        $this->proposal->update(['aplica_igv' => false]);
        $htmlSin = $this->renderPdfHtml($this->proposal->fresh('prequote'));
        $this->assertStringContainsString('Costo SIN IGV', $htmlSin);
    }

    /**
     * Renderizar el HTML del PDF (sin pasar por dompdf) usando el buildViewData
     * del generador (privado → reflection).
     */
    private function renderPdfHtml(QuotationProposal $proposal)
    {
        $gen = new ProposalPdfGenerator();
        $ref = new \ReflectionMethod($gen, 'buildViewData');
        $ref->setAccessible(true);
        $data = $ref->invoke($gen, $proposal);

        return view('marketing::quotation.proposal-pdf', $data)->render();
    }
}
