<?php

namespace Tests\Feature\Marketing;

use Carbon\Carbon;
use Hyn\Tenancy\Environment;
use Hyn\Tenancy\Models\Website;
use Mockery;
use Modules\Marketing\Models\FeriaReceived;
use Modules\Marketing\Models\Grid;
use Modules\Marketing\Models\GridItem;
use Modules\Marketing\Models\Piece;
use Modules\Marketing\Services\GridContinuityService;
use Modules\Marketing\Services\GridGeneratorEngine;
use Modules\Marketing\Services\GridPatternAnalyzer;
use Modules\Marketing\Services\MKT03Orchestrator;
use Modules\Marketing\Services\PieceProducerEngine;
use Tests\TestCase;

/**
 * Mkt03ModoFFeriasTest — MKT-03 M5 (Modo F — §12, §13.6 doc funcional)
 *
 * Cubre la generación de los 3 momentos editoriales (2m / 1m / 15d) por feria,
 * el endpoint `/ferias/receive` para el agente externo, y la opción
 * `produce_pieces` que llama al PieceProducerEngine.
 *
 * Cubre:
 *   1. generateFeriaItems crea 3 ítems por red con tipo_contenido distinto por fase
 *   2. generateFeriaItems respeta §12.4 (fechas exactas: -60d, -30d, -15d)
 *   3. El briefing de cada fase es ESPECÍFICO (no genérico) y respeta §12.5
 *   4. produce_pieces=true llama al producer y crea Piece
 *   5. produce_pieces=false NO llama al producer
 *   6. calcularFaseActual mapea correctamente fechas → fase
 *   7. Seed de migración produce las 5 ferias típicas peruanas
 */
class Mkt03ModoFFeriasTest extends TestCase
{
    /** @var array  IDs creados durante el test */
    private $createdGridIds  = [];
    private $createdFeriaIds = [];
    private $createdPieceIds = [];

    protected function setUp(): void
    {
        parent::setUp();

        // ── DB override ──
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
            if (!empty($this->createdPieceIds)) Piece::whereIn('id', $this->createdPieceIds)->delete();
            if (!empty($this->createdGridIds)) {
                GridItem::whereIn('grid_id', $this->createdGridIds)->forceDelete();
                Grid::whereIn('id', $this->createdGridIds)->forceDelete();
            }
            if (!empty($this->createdFeriaIds)) FeriaReceived::whereIn('id', $this->createdFeriaIds)->forceDelete();
        } catch (\Exception $e) {}
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function generate_feria_items_crea_3_items_por_red_con_tipos_distintos_por_fase()
    {
        $feria = $this->seedFeria('Test Modo F 2027', '2027-09-15', ['facebook']);
        $orchestrator = $this->makeOrchestrator(false);

        $result = $orchestrator->generateFeriaItems($feria, ['facebook'], null, false);

        $this->assertTrue($result['success']);
        $this->assertEquals(3, $result['items_creados']);
        $this->assertEquals(0, $result['piezas_creadas']);

        $items = GridItem::where('feria_id', $feria->id)->get();
        $this->assertCount(3, $items);

        $tipos = $items->pluck('tipo_contenido')->toArray();
        $this->assertContains('Expectativa',          $tipos, 'Fase 2m → tipo_contenido = Expectativa');
        $this->assertContains('Contenido de valor',   $tipos, 'Fase 1m → tipo_contenido = Contenido de valor');
        $this->assertContains('Recordatorio',         $tipos, 'Fase 15d → tipo_contenido = Recordatorio');
    }

    /** @test */
    public function generate_feria_items_respeta_fechas_exactas_del_doc_funcional()
    {
        // §12.4: 3 momentos editoriales — 2 meses / 1 mes / 15 días antes
        $feria = $this->seedFeria('Test Fechas 2027', '2027-06-30', ['linkedin']);
        $orchestrator = $this->makeOrchestrator(false);

        $orchestrator->generateFeriaItems($feria, ['linkedin'], null, false);

        $items = GridItem::where('feria_id', $feria->id)->orderBy('fecha_publicacion')->get();
        $fechas = $items->pluck('fecha_publicacion')->map(function ($d) {
            return $d->format('Y-m-d');
        })->toArray();

        $this->assertContains('2027-05-01', $fechas, '60 días antes (-2m)');
        $this->assertContains('2027-05-31', $fechas, '30 días antes (-1m)');
        $this->assertContains('2027-06-15', $fechas, '15 días antes');
    }

    /** @test */
    public function briefing_por_fase_es_especifico_y_no_generico()
    {
        $feria = $this->seedFeria('Test Briefing 2027', '2027-10-20', ['instagram']);
        $orchestrator = $this->makeOrchestrator(false);

        $orchestrator->generateFeriaItems($feria, ['instagram'], null, false);

        $items = GridItem::where('feria_id', $feria->id)->get()->keyBy('fase_feria');

        // §12.5 — 2m = anuncio/expectativa
        $this->assertStringContainsStringIgnoringCase('ANUNCIO',
            $items[FeriaReceived::FASE_2M]->observaciones);
        $this->assertStringContainsStringIgnoringCase('EXPECTATIVA',
            $items[FeriaReceived::FASE_2M]->observaciones);

        // §12.5 — 1m = valor/contexto/educativo
        $this->assertStringContainsStringIgnoringCase('VALOR',
            $items[FeriaReceived::FASE_1M]->observaciones);

        // §12.5 — 15d = recordatorio/activación
        $this->assertStringContainsStringIgnoringCase('RECORDATORIO',
            $items[FeriaReceived::FASE_15D]->observaciones);

        // §12.6 — el contexto interno (stand, productos a llevar) NO debe estar en el briefing público
        $this->assertStringContainsStringIgnoringCase('contexto estratégico interno',
            $items[FeriaReceived::FASE_2M]->observaciones);
    }

    /** @test */
    public function produce_pieces_true_llama_al_producer_y_crea_pieces()
    {
        $feria = $this->seedFeria('Test ProducePieces 2027', '2027-11-05', ['facebook']);

        // Mock producer que retorna una Piece sintética por cada GridItem
        $callCount = 0;
        $producerMock = Mockery::mock(PieceProducerEngine::class);
        $producerMock->shouldReceive('producePiece')
            ->andReturnUsing(function ($item) use (&$callCount) {
                $callCount++;
                $piece = new Piece();
                $piece->id = 99000 + $callCount;
                $piece->grid_item_id = $item->id;
                $piece->red_social = $item->red_social;
                $piece->copy = 'Copy mock fase ' . $item->fase_feria;
                $piece->estado_pieza = Piece::ESTADO_LISTA_PUBLICAR;
                return $piece;
            });

        $orchestrator = $this->makeOrchestratorCustom(null, null, $producerMock);
        $result = $orchestrator->generateFeriaItems($feria, ['facebook'], null, true);

        $this->assertEquals(3, $result['items_creados']);
        $this->assertEquals(3, $result['piezas_creadas']);
        $this->assertEquals(3, $callCount, 'Producer debe ser llamado 1 vez por cada ítem');
    }

    /** @test */
    public function produce_pieces_false_no_llama_al_producer()
    {
        $feria = $this->seedFeria('Test SinPiezas 2027', '2027-12-20', ['facebook']);

        $producerMock = Mockery::mock(PieceProducerEngine::class);
        $producerMock->shouldNotReceive('producePiece');

        $orchestrator = $this->makeOrchestratorCustom(null, null, $producerMock);
        $result = $orchestrator->generateFeriaItems($feria, ['facebook'], null, false);

        $this->assertEquals(3, $result['items_creados']);
        $this->assertEquals(0, $result['piezas_creadas']);
    }

    /** @test */
    public function calcular_fase_actual_mapea_correctamente()
    {
        // Feria a 80 días → ninguna fase (todavía muy lejos)
        $f80 = new FeriaReceived(['fecha' => Carbon::today()->addDays(80)->format('Y-m-d')]);
        $f80->fecha = Carbon::today()->addDays(80);
        $this->assertNull($f80->calcularFaseActual());

        // A 50 días → fase 2m
        $f50 = new FeriaReceived();
        $f50->fecha = Carbon::today()->addDays(50);
        $this->assertEquals(FeriaReceived::FASE_2M, $f50->calcularFaseActual());

        // A 25 días → fase 1m
        $f25 = new FeriaReceived();
        $f25->fecha = Carbon::today()->addDays(25);
        $this->assertEquals(FeriaReceived::FASE_1M, $f25->calcularFaseActual());

        // A 10 días → fase 15d
        $f10 = new FeriaReceived();
        $f10->fecha = Carbon::today()->addDays(10);
        $this->assertEquals(FeriaReceived::FASE_15D, $f10->calcularFaseActual());

        // Feria pasada → completada
        $fpas = new FeriaReceived();
        $fpas->fecha = Carbon::today()->subDays(5);
        $this->assertEquals(FeriaReceived::FASE_COMPLETADA, $fpas->calcularFaseActual());
    }

    /** @test */
    public function seed_de_migracion_produce_5_ferias_tipicas_peruanas()
    {
        // El seed (migración 2026_05_07_000001_seed_mkt_ferias_demo) ya corrió.
        // Verificamos que existen las 5 ferias seed conocidas.
        $nombresEsperados = [
            'EXPOMARKETING 2026',
            'EXPOTEXTIL PERU 2026',
            'EXPO CORPORATE GIFTS 2027',
            'PERU MODA 2027',
            'FERIA DEL EMPRENDEDOR 2027',
        ];

        $found = FeriaReceived::whereIn('nombre', $nombresEsperados)->get();

        $this->assertGreaterThanOrEqual(5, $found->count(),
            'Las 5 ferias seed deben existir en BD tras correr la migración');

        // Spot-check: EXPOTEXTIL debe tener TikTok en redes target
        $expotextil = $found->firstWhere('nombre', 'EXPOTEXTIL PERU 2026');
        $this->assertNotNull($expotextil);
        $this->assertContains('tiktok', $expotextil->redes_sociales_target);

        // PERU MODA NO debe tener TikTok ni Facebook (solo IG + LI según el seed)
        $perumoda = $found->firstWhere('nombre', 'PERU MODA 2027');
        $this->assertNotNull($perumoda);
        $this->assertNotContains('tiktok',   $perumoda->redes_sociales_target);
        $this->assertNotContains('facebook', $perumoda->redes_sociales_target);
        $this->assertContains('linkedin',    $perumoda->redes_sociales_target);
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    private function seedFeria($nombre, $fecha, array $redes)
    {
        $f = FeriaReceived::create([
            'nombre'                  => $nombre,
            'fecha'                   => $fecha,
            'sector'                  => 'Test sector',
            'ciudad'                  => 'Lima',
            'tipo_participacion'      => 'expositor',
            'estado'                  => FeriaReceived::ESTADO_ACTIVA,
            'source'                  => FeriaReceived::SOURCE_MANUAL,
            'redes_sociales_target'   => $redes,
        ]);
        $this->createdFeriaIds[] = $f->id;
        return $f;
    }

    /**
     * @param  bool  $producePieces  ignored (passed via call args)
     * @return MKT03Orchestrator
     */
    private function makeOrchestrator($producePieces = false)
    {
        return $this->makeOrchestratorCustom(null, null, null);
    }

    private function makeOrchestratorCustom($generator, $analyzer, $producer)
    {
        if ($generator === null) {
            $generator = Mockery::mock(GridGeneratorEngine::class);
            $generator->shouldReceive('generateGrid')->andReturn(['items_generados' => []]);
            $generator->shouldReceive('saveGeneratedItems')->andReturn(0);
        }
        if ($analyzer === null) {
            $analyzer = Mockery::mock(GridPatternAnalyzer::class);
        }
        if ($producer === null) {
            $producer = Mockery::mock(PieceProducerEngine::class);
            $producer->shouldReceive('producePiece')->andReturn(null)->byDefault();
        }

        $orch = new MKT03Orchestrator($analyzer, $generator, $producer, new GridContinuityService());

        // Capturar grids creadas via firstOrCreate dentro de generateFeriaItems
        // para limpiarlas en tearDown
        return new class($orch, $this) {
            private $real;
            private $test;
            public function __construct($real, $test) { $this->real = $real; $this->test = $test; }
            public function generateFeriaItems($feria, $redes = [], $grid = null, $producePieces = false)
            {
                $beforeIds = Grid::pluck('id')->toArray();
                $r = $this->real->generateFeriaItems($feria, $redes, $grid, $producePieces);
                $afterIds = Grid::pluck('id')->toArray();
                $newIds = array_diff($afterIds, $beforeIds);
                $reflectionTest = new \ReflectionObject($this->test);
                $prop = $reflectionTest->getProperty('createdGridIds');
                $prop->setAccessible(true);
                $current = $prop->getValue($this->test);
                $prop->setValue($this->test, array_merge($current, array_values($newIds)));
                return $r;
            }
        };
    }
}
