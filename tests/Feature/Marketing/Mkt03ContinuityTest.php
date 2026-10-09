<?php

namespace Tests\Feature\Marketing;

use Carbon\Carbon;
use Hyn\Tenancy\Environment;
use Hyn\Tenancy\Models\Website;
use Mockery;
use Modules\Marketing\Models\Grid;
use Modules\Marketing\Models\GridItem;
use Modules\Marketing\Services\GridContinuityService;
use Modules\Marketing\Services\GridGeneratorEngine;
use Modules\Marketing\Services\GridPatternAnalyzer;
use Modules\Marketing\Services\MKT03Orchestrator;
use Modules\Marketing\Services\PieceProducerEngine;
use Tests\TestCase;

/**
 * Mkt03ContinuityTest — MKT-03 M2 (Modo C — §11.5 doc funcional)
 *
 * Cubre las 5 sub-reglas de continuidad automática editorial:
 *   §11.5    Si la grilla parcial está por agotarse, ampliar tramo siguiente.
 *   §11.5.1  Proactividad editorial inteligente.
 *   §11.5.2  Expansión progresiva (trimestre por defecto).
 *   §11.5.3  Orden de construcción por red social: FB → IG → LinkedIn → TikTok.
 *   §11.5.4  Criterio cuánto ampliar: trimestre por defecto.
 *   §11.5.5  No contradicción con configuración antigua.
 */
class Mkt03ContinuityTest extends TestCase
{
    /** @var array  IDs de grids creados durante el test */
    private $createdGridIds = [];

    protected function setUp(): void
    {
        parent::setUp();

        // ── DB override (phpunit.xml hardcodea multifacturalo_dusk) ──
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
        if (!empty($this->createdGridIds)) {
            try {
                GridItem::whereIn('grid_id', $this->createdGridIds)->delete();
                Grid::whereIn('id', $this->createdGridIds)->delete();
            } catch (\Exception $e) {}
        }
        Mockery::close();
        parent::tearDown();
    }

    /*
    |--------------------------------------------------------------------------
    | Tests del GridContinuityService
    |--------------------------------------------------------------------------
    */

    /** @test */
    public function evaluate_grid_marca_agotada_si_ultimo_item_es_anterior_a_hoy()
    {
        $grid = $this->createGridWithItems('facebook', [
            Carbon::today()->subDays(30)->format('Y-m-d'),
            Carbon::today()->subDays(15)->format('Y-m-d'),
            Carbon::today()->subDays(5)->format('Y-m-d'),
        ]);

        $svc = new GridContinuityService();
        $eval = $svc->evaluateGrid($grid);

        $this->assertTrue($eval['agotada']);
        $this->assertTrue($eval['needs_extension']);
        $this->assertNotNull($eval['tramo_sugerido']);
        $this->assertStringStartsWith('agotada_hace_', $eval['razon']);
    }

    /** @test */
    public function evaluate_grid_marca_por_agotarse_si_quedan_pocos_dias()
    {
        // Última fecha = hoy + 5 días (umbral default 14 → cae como candidata)
        $grid = $this->createGridWithItems('facebook', [
            Carbon::today()->addDays(5)->format('Y-m-d'),
        ]);

        $svc = new GridContinuityService();
        $eval = $svc->evaluateGrid($grid);

        $this->assertFalse($eval['agotada']);
        $this->assertTrue($eval['needs_extension']);
        $this->assertEquals(5, $eval['dias_restantes']);
        $this->assertStringStartsWith('por_agotarse_', $eval['razon']);
    }

    /** @test */
    public function evaluate_grid_no_marca_si_cobertura_es_amplia()
    {
        // Última fecha = hoy + 60 días → suficiente cobertura
        $grid = $this->createGridWithItems('facebook', [
            Carbon::today()->addDays(60)->format('Y-m-d'),
        ]);

        $svc = new GridContinuityService();
        $eval = $svc->evaluateGrid($grid);

        $this->assertFalse($eval['needs_extension']);
        $this->assertEquals('cobertura_suficiente', $eval['razon']);
        $this->assertNull($eval['tramo_sugerido']);
    }

    /** @test */
    public function evaluate_grid_marca_sin_items_aun_si_grilla_vacia()
    {
        $grid = $this->createGridWithItems('linkedin', []);

        $svc = new GridContinuityService();
        $eval = $svc->evaluateGrid($grid);

        $this->assertNull($eval['ultima_fecha']);
        $this->assertFalse($eval['needs_extension']);
        $this->assertEquals('sin_items_aun', $eval['razon']);
    }

    /** @test */
    public function suggest_next_tramo_arranca_desde_hoy_si_grilla_esta_agotada()
    {
        // §11.5.4: para una grilla agotada (última = pasado), el tramo NO debe
        // arrancar en pasado — debe arrancar HOY para no llenar el pasado.
        $grid = $this->createGrid('facebook');
        $ultima = Carbon::today()->subDays(30)->format('Y-m-d');

        $svc = new GridContinuityService();
        $tramo = $svc->suggestNextTramo($grid, $ultima);

        $this->assertEquals(Carbon::today()->format('Y-m-d'), $tramo['fecha_desde']);
        $this->assertEquals(GridContinuityService::TRAMO_TRIMESTRE, $tramo['tipo']);
        $this->assertEquals(3, $tramo['meses']);
    }

    /** @test */
    public function suggest_next_tramo_arranca_desde_dia_siguiente_si_cobertura_es_futura()
    {
        // §11.5: si la grilla todavía cubre futuro, el siguiente tramo arranca
        // el día después del último cubierto (no se solapa).
        $grid = $this->createGrid('facebook');
        $ultima = Carbon::today()->addDays(10)->format('Y-m-d');

        $svc = new GridContinuityService();
        $tramo = $svc->suggestNextTramo($grid, $ultima);

        $this->assertEquals(Carbon::today()->addDays(11)->format('Y-m-d'), $tramo['fecha_desde']);
    }

    /** @test */
    public function suggest_next_tramo_default_trimestre_3_meses()
    {
        // §11.5.2 — defecto: trimestre, no año completo.
        $grid = $this->createGrid('facebook');
        $ultima = Carbon::today()->addDays(5)->format('Y-m-d');

        $svc = new GridContinuityService();
        $tramo = $svc->suggestNextTramo($grid, $ultima);

        $diff = Carbon::parse($tramo['fecha_hasta'])->diffInDays(Carbon::parse($tramo['fecha_desde']));
        $this->assertGreaterThanOrEqual(85, $diff, 'Tramo trimestre debe ser ~90 días');
        $this->assertLessThanOrEqual(95, $diff);
        $this->assertEquals('trimestral', $tramo['tipo']);
    }

    /** @test */
    public function detect_candidates_respeta_orden_redes_fb_ig_li_tt()
    {
        // §11.5.3: el orden DEBE ser Facebook → Instagram → LinkedIn → TikTok.
        // Crear las 4 grillas DESORDENADAS y verificar el orden de salida.
        $tiktok    = $this->createGridWithItems('tiktok',    [Carbon::today()->subDays(5)->format('Y-m-d')]);
        $linkedin  = $this->createGridWithItems('linkedin',  [Carbon::today()->subDays(5)->format('Y-m-d')]);
        $instagram = $this->createGridWithItems('instagram', [Carbon::today()->subDays(5)->format('Y-m-d')]);
        $facebook  = $this->createGridWithItems('facebook',  [Carbon::today()->subDays(5)->format('Y-m-d')]);

        $svc = new GridContinuityService();
        $candidates = $svc->detectCandidates();

        // Filtrar a solo nuestras 4 grids del test (puede haber otras de runs previos)
        $ourCandidates = array_filter($candidates, function ($c) use ($facebook, $instagram, $linkedin, $tiktok) {
            return in_array($c['grid']->id, [$facebook->id, $instagram->id, $linkedin->id, $tiktok->id]);
        });
        $ourCandidates = array_values($ourCandidates);

        $this->assertCount(4, $ourCandidates);
        $this->assertEquals('facebook',  $ourCandidates[0]['grid']->red_social);
        $this->assertEquals('instagram', $ourCandidates[1]['grid']->red_social);
        $this->assertEquals('linkedin',  $ourCandidates[2]['grid']->red_social);
        $this->assertEquals('tiktok',    $ourCandidates[3]['grid']->red_social);
    }

    /** @test */
    public function is_continuity_forbidden_siempre_es_false_en_e1()
    {
        // §11.5.5: la configuración antigua "solo enero" NUNCA es prohibición
        // permanente. En E1 nuestra implementación responde siempre false.
        $grid = $this->createGrid('facebook');
        $svc = new GridContinuityService();
        $this->assertFalse($svc->isContinuityForbidden($grid));
    }

    /*
    |--------------------------------------------------------------------------
    | Tests del MKT03Orchestrator integrado con GridContinuityService
    |--------------------------------------------------------------------------
    */

    /** @test */
    public function orchestrator_extend_grid_if_needed_skip_si_cobertura_es_amplia()
    {
        $grid = $this->createGridWithItems('facebook', [
            Carbon::today()->addDays(60)->format('Y-m-d'),
        ]);

        $orchestrator = $this->makeOrchestrator(); // Usa mocks para los engines

        $r = $orchestrator->extendGridIfNeeded($grid, false);

        $this->assertFalse($r['success']);
        $this->assertTrue($r['skipped']);
        $this->assertEquals('cobertura_suficiente', $r['reason']);
    }

    /** @test */
    public function orchestrator_extend_all_dry_run_no_escribe_items()
    {
        $grid = $this->createGridWithItems('linkedin', [
            Carbon::today()->subDays(2)->format('Y-m-d'),
        ]);

        $orchestrator = $this->makeOrchestrator();

        $itemsBefore = GridItem::where('grid_id', $grid->id)->count();
        $result = $orchestrator->extendAllNeedingContinuity(null, true);
        $itemsAfter = GridItem::where('grid_id', $grid->id)->count();

        $this->assertGreaterThanOrEqual(1, $result['total_candidates']);
        $this->assertEquals($itemsBefore, $itemsAfter, 'dry_run no debe haber agregado items');

        // Verificar que la grilla aparece en el plan
        $found = false;
        foreach ($result['results'] as $r) {
            if ($r['grid_id'] === $grid->id) {
                $found = true;
                $this->assertTrue($r['dry_run'] ?? false);
                $this->assertNotNull($r['tramo']);
            }
        }
        $this->assertTrue($found);
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers de fabricación de Grid + items
    |--------------------------------------------------------------------------
    */

    /**
     * Crea un Grid con la red dada y lo registra para cleanup.
     *
     * @param  string  $red
     * @return Grid
     */
    private function createGrid($red)
    {
        $g = Grid::create([
            'anio'         => date('Y'),
            'periodo'      => 'TEST_' . substr(uniqid(), -6),
            'tipo_periodo' => Grid::TIPO_MENSUAL,
            'red_social'   => $red,
            'version'      => 1,
            'estado'       => Grid::ESTADO_ACTIVA,
            'titulo'       => 'Test ' . $red,
        ]);
        $this->createdGridIds[] = $g->id;
        return $g;
    }

    /**
     * Crea un Grid con N items en las fechas dadas.
     *
     * @param  string  $red
     * @param  array   $fechas
     * @return Grid
     */
    private function createGridWithItems($red, array $fechas)
    {
        $g = $this->createGrid($red);
        $order = 1;
        foreach ($fechas as $f) {
            GridItem::create([
                'grid_id'           => $g->id,
                'fecha_publicacion' => $f,
                'red_social'        => $red,
                'tematica'          => 'Test',
                'tipo_contenido'    => 'Promoción',
                'item_order'        => $order++,
                'origen'            => GridItem::ORIGEN_GRILLA,
            ]);
        }
        return $g->fresh();
    }

    /**
     * Crea un Orchestrator con engines mockeados para no gastar IA en tests.
     *
     * El GridGeneratorEngine se mockea con generateGrid() retornando un
     * payload fake y saveGeneratedItems() retornando 1 ítem agregado.
     *
     * @return MKT03Orchestrator
     */
    private function makeOrchestrator()
    {
        $patternAnalyzer = Mockery::mock(GridPatternAnalyzer::class);
        $producer        = Mockery::mock(PieceProducerEngine::class);

        $generator = Mockery::mock(GridGeneratorEngine::class);
        $generator->shouldReceive('generateGrid')->andReturn([
            'items_generados' => [],
            'meta' => ['mock' => true],
        ]);
        $generator->shouldReceive('saveGeneratedItems')->andReturn(1);

        $continuity = new GridContinuityService();

        return new MKT03Orchestrator($patternAnalyzer, $generator, $producer, $continuity);
    }
}
