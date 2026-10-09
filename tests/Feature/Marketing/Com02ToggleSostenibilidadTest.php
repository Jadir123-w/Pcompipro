<?php

namespace Tests\Feature\Marketing;

use Hyn\Tenancy\Environment;
use Hyn\Tenancy\Models\Website;
use Modules\Marketing\Models\PreQuote;
use Modules\Marketing\Models\QuotationProposal;
use Tests\TestCase;

/**
 * Com02ToggleSostenibilidadTest — Toggle por cotización del bloque de sostenibilidad.
 *
 * El PDF muestra el bloque verde de sostenibilidad según `incluye_sostenibilidad`
 * de la propuesta (default true = retro-compatible). Este test cubre el contrato
 * backend: el flag se persiste como boolean, nace en true, y se puede apagar.
 * (El efecto en el PDF se verificó renderizando: flag ON = bloque presente; OFF =
 * bloque oculto y el resto del PDF intacto.)
 */
class Com02ToggleSostenibilidadTest extends TestCase
{
    /** @var array */
    private $cleanupPrequoteIds = [];

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
    }

    protected function tearDown(): void
    {
        try {
            foreach ($this->cleanupPrequoteIds as $pid) {
                QuotationProposal::where('prequote_id', $pid)->forceDelete();
                PreQuote::where('id', $pid)->forceDelete();
            }
        } catch (\Exception $e) {}
        parent::tearDown();
    }

    private function makeProposal()
    {
        $pq = PreQuote::create([
            'version' => 1, 'estado' => PreQuote::ESTADO_BORRADOR, 'titulo' => 'TEST sostenibilidad',
        ]);
        $this->cleanupPrequoteIds[] = $pq->id;

        return QuotationProposal::create([
            'prequote_id'       => $pq->id,
            'numero_cotizacion' => 'TEST-SOST-' . $pq->id,
            'fecha_emision'     => '2026-07-15',
            'estado'            => QuotationProposal::ESTADO_BORRADOR,
        ]);
    }

    /** @test */
    public function el_flag_nace_en_true_por_defecto()
    {
        // Retro-compat: una propuesta nueva (sin setear el flag) muestra el bloque.
        $prop = $this->makeProposal();

        $this->assertTrue((bool) $prop->fresh()->incluye_sostenibilidad,
            'incluye_sostenibilidad debe nacer en true (default de BD)');
    }

    /** @test */
    public function el_flag_se_puede_apagar_y_persiste_como_boolean()
    {
        $prop = $this->makeProposal();

        $prop->update(['incluye_sostenibilidad' => false]);
        $fresh = $prop->fresh();

        $this->assertFalse((bool) $fresh->incluye_sostenibilidad,
            'Apagar el flag lo persiste en false');
        $this->assertInternalType('boolean', $fresh->incluye_sostenibilidad,
            'El cast debe exponerlo como boolean');
    }
}
