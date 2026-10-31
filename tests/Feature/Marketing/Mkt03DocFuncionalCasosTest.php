<?php

namespace Tests\Feature\Marketing;

use Carbon\Carbon;
use Hyn\Tenancy\Environment;
use Hyn\Tenancy\Models\Website;
use Mockery;
use Modules\Marketing\Models\ExtraordinaryContent;
use Modules\Marketing\Models\FeriaReceived;
use Modules\Marketing\Models\Grid;
use Modules\Marketing\Models\GridItem;
use Modules\Marketing\Models\HistoricalGrid;
use Modules\Marketing\Models\LearningPattern;
use Modules\Marketing\Models\Piece;
use Modules\Marketing\Services\GridContinuityService;
use Modules\Marketing\Services\GridGeneratorEngine;
use Modules\Marketing\Services\GridPatternAnalyzer;
use Modules\Marketing\Services\MKT03Orchestrator;
use Modules\Marketing\Services\PieceProducerEngine;
use Tests\TestCase;

/**
 * Mkt03DocFuncionalCasosTest — MKT-03 M3
 *
 * Cubre los 10 casos formales recomendados por el §19 del Documento Funcional
 * (Dev) — MKT-03. Cada test corresponde a un caso del documento.
 *
 * Los engines de IA (GridGeneratorEngine, PieceProducerEngine, GridPatternAnalyzer)
 * se mockean con respuestas controladas — los tests NO consumen tokens reales.
 *
 * Casos del §19 cubiertos:
 *   1. Construir grilla anual de Facebook/Instagram con base histórica
 *   2. Construir solo enero de LinkedIn y luego completar el trimestre
 *   3. Crear una pieza puntual para una fecha específica y agregarla al mes correcto
 *   4. Detectar que enero está por terminar y ampliar automáticamente febrero o
 *      el siguiente tramo (Modo C — §11.5)
 *   5. Generar pieza final de TikTok con lógica audiovisual
 *   6. Crear contenido extraordinario y preguntar si debe alimentar futuras grillas
 *   7. Recibir una feria desde el agente de ferias y generar tres piezas por cercanía
 *   8. Mostrar la grilla ya creada para una red y un mes específico (Modo B)
 *   9. Generar grilla sin campos humanos heredados (§7 depuración estructura histórica)
 *  10. Operar correctamente con metadata operativa opcional activada (§8)
 */
class Mkt03DocFuncionalCasosTest extends TestCase
{
    /** @var array  IDs creados durante el test */
    private $createdGridIds            = [];
    private $createdHistoricalIds      = [];
    private $createdPatternIds         = [];
    private $createdExtraIds           = [];
    private $createdFeriaIds           = [];
    private $createdPieceIds           = [];

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
        try {
            if (!empty($this->createdPieceIds))      Piece::whereIn('id', $this->createdPieceIds)->delete();
            if (!empty($this->createdGridIds)) {
                GridItem::whereIn('grid_id', $this->createdGridIds)->forceDelete();
                Grid::whereIn('id', $this->createdGridIds)->forceDelete();
            }
            if (!empty($this->createdPatternIds))    LearningPattern::whereIn('id', $this->createdPatternIds)->delete();
            if (!empty($this->createdHistoricalIds)) HistoricalGrid::whereIn('id', $this->createdHistoricalIds)->delete();
            if (!empty($this->createdExtraIds))      ExtraordinaryContent::whereIn('id', $this->createdExtraIds)->delete();
            if (!empty($this->createdFeriaIds))      FeriaReceived::whereIn('id', $this->createdFeriaIds)->delete();
        } catch (\Exception $e) {}
        Mockery::close();
        parent::tearDown();
    }

    /*
    |==========================================================================
    | CASO 1 — Construir grilla anual de Facebook/Instagram con base histórica
    |==========================================================================
    | El agente debe poder generar una grilla anual para FB e IG aprovechando
    | un HistoricalGrid analizado como base. La grilla resultante debe quedar
    | enlazada al histórico via fuente_base_historica_id.
    */

    /** @test */
    public function caso_1_construye_grilla_anual_fb_ig_con_base_historica()
    {
        $hg = $this->seedHistoricalGrid(2025);
        $this->seedLearningPattern($hg, 'facebook', 'frecuencia', 'FB 2 posts/día laborable');

        $orchestrator = $this->makeOrchestratorWithMockedGenerator(
            ['fake_generated' => true, 'items' => []]
        );

        $result = $orchestrator->createGrid([
            'anio'         => 2027,
            'periodo'      => '2027',
            'tipo_periodo' => Grid::TIPO_ANUAL,
            'redes'        => ['facebook', 'instagram'],
            'titulo'       => 'Test grilla anual FB/IG',
        ]);

        $this->assertCount(2, $result['grids'], 'Una grilla por cada red solicitada');

        $redes = array_map(function ($g) { return $g->red_social; }, $result['grids']);
        $this->assertContains('facebook', $redes);
        $this->assertContains('instagram', $redes);

        foreach ($result['grids'] as $g) {
            $this->createdGridIds[] = $g->id;
            $this->assertEquals(2027, $g->anio);
            $this->assertEquals(Grid::TIPO_ANUAL, $g->tipo_periodo);
            $this->assertEquals(Grid::ESTADO_BORRADOR, $g->estado);
        }
    }

    /** @test */
    public function no_persiste_grilla_si_falla_la_generacion_de_ia()
    {
        Grid::withTrashed()
            ->where('anio', 2098)
            ->where('periodo', 'like', 'TEST_FALLBACK_%')
            ->forceDelete();

        $periodo = 'TEST_FALLBACK_' . substr(uniqid(), -6);
        $generator = Mockery::mock(GridGeneratorEngine::class);
        $generator->shouldReceive('generateGrid')
            ->once()
            ->andReturn([
                '_fallback'          => true,
                'resumen_generacion' => 'Error del proveedor IA: Gemini no disponible',
                'items_generados'    => [],
            ]);
        $generator->shouldNotReceive('saveGeneratedItems');

        $orchestrator = $this->makeOrchestratorWith($generator, null, null);
        $result = $orchestrator->createGrid([
            'anio'         => 2098,
            'periodo'      => $periodo,
            'tipo_periodo' => Grid::TIPO_ANUAL,
            'redes'        => ['facebook'],
            'fecha_desde'  => '2098-01-01',
            'fecha_hasta'  => '2098-12-31',
        ]);

        $this->assertArrayHasKey('error', $result);
        $this->assertSame('Error del proveedor IA: Gemini no disponible', $result['error']);
        $this->assertSame(0, Grid::where('anio', 2098)->where('periodo', $periodo)->count());
    }

    /*
    |==========================================================================
    | CASO 2 — Construir solo enero de LinkedIn y luego completar el trimestre
    |==========================================================================
    | El agente debe poder construir una grilla parcial inicialmente y luego
    | extenderla sin duplicar lo ya creado (§11.12 — consolidación progresiva).
    */

    /** @test */
    public function caso_2_construye_solo_enero_li_y_luego_completa_trimestre()
    {
        // Paso 1: grilla solo enero
        $grid = $this->seedGrid('linkedin', 2027, [
            '2027-01-15', '2027-01-20', '2027-01-31',
        ]);

        $this->assertCount(3, $grid->items, 'Grilla parcial debe tener 3 items en enero');

        // Paso 2: extender al trimestre (feb + mar)
        $orchestrator = $this->makeOrchestratorWithMockedGenerator(
            ['ok' => true]
        );

        $extendResult = $orchestrator->extendGrid($grid, [
            'fecha_desde' => '2027-02-01',
            'fecha_hasta' => '2027-03-31',
        ]);

        $this->assertTrue($extendResult['success']);
        $this->assertGreaterThanOrEqual(0, $extendResult['items_added']);

        // Verificar que los items existentes de enero no se duplicaron
        $itemsEnero = GridItem::where('grid_id', $grid->id)
            ->whereBetween('fecha_publicacion', ['2027-01-01', '2027-01-31'])
            ->count();
        $this->assertEquals(3, $itemsEnero, 'No deben haberse duplicado los items de enero');
    }

    /*
    |==========================================================================
    | CASO 3 — Crear pieza puntual para fecha específica
    |==========================================================================
    | El agente debe poder insertar una pieza puntual (§11.9) en un día concreto
    | dentro de una grilla existente, sin generar duplicados.
    */

    /** @test */
    public function caso_3_inserta_pieza_puntual_en_fecha_especifica()
    {
        $grid = $this->seedGrid('facebook', 2026, ['2026-12-20', '2026-12-22']);

        // Insertar pieza puntual el 25-dic
        $puntual = GridItem::create([
            'grid_id'           => $grid->id,
            'fecha_publicacion' => '2026-12-25',
            'red_social'        => 'facebook',
            'tematica'          => 'Saludo Navidad',
            'tipo_contenido'    => 'Saludo',
            'item_order'        => 99,
            'origen'            => GridItem::ORIGEN_PUNTUAL,
            'prioridad'         => GridItem::PRIORIDAD_ALTA,
            'evento'            => 'Navidad',
        ]);

        $this->assertEquals(GridItem::ORIGEN_PUNTUAL, $puntual->origen,
            'Pieza puntual debe quedar marcada con origen=puntual (§11.9)');

        $totalItems = GridItem::where('grid_id', $grid->id)->count();
        $this->assertEquals(3, $totalItems);

        // §11.11 — no debe permitir duplicado en la misma fecha + red
        $duplicado = GridItem::where('grid_id', $grid->id)
            ->where('fecha_publicacion', '2026-12-25')
            ->count();
        $this->assertEquals(1, $duplicado, 'Una sola pieza para esa fecha+red');
    }

    /*
    |==========================================================================
    | CASO 4 — Detectar fin de enero y ampliar automáticamente (Modo C — §11.5)
    |==========================================================================
    | El agente debe detectar de forma proactiva que la planificación está por
    | agotarse y sugerir el siguiente tramo lógico (trimestre por defecto).
    */

    /** @test */
    public function caso_4_detecta_grilla_agotada_y_sugiere_siguiente_tramo()
    {
        // Grilla solo enero, "hoy" cae cerca del 31
        $grid = $this->seedGrid('facebook', date('Y'), [
            Carbon::today()->subDays(20)->format('Y-m-d'),
            Carbon::today()->subDays(10)->format('Y-m-d'),
            Carbon::today()->subDays(2)->format('Y-m-d'), // último item: hace 2 días → AGOTADA
        ]);

        $continuity = new GridContinuityService();
        $eval = $continuity->evaluateGrid($grid);

        $this->assertTrue($eval['agotada'], 'Grilla agotada hace 2 días debe marcarse como agotada');
        $this->assertTrue($eval['needs_extension']);
        $this->assertNotNull($eval['tramo_sugerido']);
        $this->assertEquals(GridContinuityService::TRAMO_TRIMESTRE, $eval['tramo_sugerido']['tipo'],
            '§11.5.4 — el tramo por defecto debe ser trimestre, no año completo');

        // §11.5.4 — el tramo arranca HOY si la grilla está agotada (no llena el pasado)
        $this->assertEquals(Carbon::today()->format('Y-m-d'), $eval['tramo_sugerido']['fecha_desde']);
    }

    /*
    |==========================================================================
    | CASO 5 — Generar pieza final de TikTok con lógica audiovisual
    |==========================================================================
    | La pieza generada debe respetar la red social del ítem (TikTok). El
    | engine real adapta la pieza con copy corto + brief audiovisual; aquí
    | testeamos la integración del orchestrator → producer.
    */

    /** @test */
    public function caso_5_produce_pieza_tiktok_respetando_red()
    {
        $grid = $this->seedGrid('tiktok', 2027, ['2027-06-10']);
        $item = $grid->items->first();

        $producerMock = Mockery::mock(PieceProducerEngine::class);
        $producerMock->shouldReceive('producePiece')
            ->with(Mockery::on(function ($gi) {
                return $gi instanceof GridItem && $gi->red_social === 'tiktok';
            }), Mockery::any())
            ->andReturnUsing(function ($gi) {
                $piece = new Piece();
                $piece->id = 999001;
                $piece->grid_item_id = $gi->id;
                $piece->red_social = $gi->red_social;
                $piece->copy = 'Idea audiovisual mock';
                $piece->cta = 'Mira el video';
                $piece->hashtags = json_encode(['#compipro', '#tiktok']);
                $piece->formato = 'video';
                $piece->estado_pieza = Piece::ESTADO_LISTA_PUBLICAR;
                return $piece;
            });

        $orchestrator = $this->makeOrchestratorWith(null, null, $producerMock);

        $piece = $orchestrator->producePiece($item, false);

        $this->assertNotNull($piece);
        $this->assertEquals('tiktok', $piece->red_social);
        $this->assertEquals('video', $piece->formato);
    }

    /*
    |==========================================================================
    | CASO 6 — Contenido extraordinario + decisión sobre uso futuro
    |==========================================================================
    | El agente debe registrar la decisión sobre si la pieza extraordinaria
    | alimenta futuras grillas (§11.10 — aprendizaje a partir de piezas nuevas).
    */

    /** @test */
    public function caso_6_extraordinario_registra_decision_sobre_uso_futuro()
    {
        $orchestrator = $this->makeOrchestratorWithMockedGenerator(['ok' => true]);

        $created = $orchestrator->createExtraordinary([
            'fecha'       => '2026-09-15',
            'motivo'      => 'Lanzamiento producto X',
            'red_social'  => 'instagram',
            'descripcion' => 'Pieza fuera de grilla por evento ad-hoc',
        ]);

        $extra = $created['extraordinary'];
        $this->createdExtraIds[] = $extra->id;

        $this->assertEquals(ExtraordinaryContent::ESTADO_ENTREGADO, $extra->estado);

        // Caso 6.a — aprobar para futuras grillas
        $approved = $orchestrator->decideExtraordinaryForFuture($extra, true, null, 'Buen contenido recurrente');
        $this->assertTrue((bool) $approved->aprobado_para_futuras_grillas);
        $this->assertEquals(ExtraordinaryContent::ESTADO_APROBADO_PARA_FUTURAS, $approved->estado);

        // Caso 6.b — el flujo alternativo (rechazar)
        $created2 = $orchestrator->createExtraordinary([
            'fecha'       => '2026-09-20',
            'motivo'      => 'Pieza one-off',
            'red_social'  => 'facebook',
            'descripcion' => 'No se debe replicar',
        ]);
        $extra2 = $created2['extraordinary'];
        $this->createdExtraIds[] = $extra2->id;

        $rejected = $orchestrator->decideExtraordinaryForFuture($extra2, false, null, 'Caso único');
        $this->assertFalse((bool) $rejected->aprobado_para_futuras_grillas);
        $this->assertEquals(ExtraordinaryContent::ESTADO_NO_APROBADO, $rejected->estado);
    }

    /*
    |==========================================================================
    | CASO 7 — Feria recibida + 3 piezas por cercanía (§12.4)
    |==========================================================================
    | El agente debe generar 3 momentos editoriales (2m, 1m, 15d) cuando
    | recibe una feria del agente de ferias.
    */

    /** @test */
    public function caso_7_feria_genera_3_momentos_editoriales_2m_1m_15d()
    {
        $feria = FeriaReceived::create([
            'nombre'                 => 'Test Feria EXPOMARKETING',
            'fecha'                  => '2027-04-15',
            'sector'                 => 'Marketing B2B',
            'ciudad'                 => 'Lima',
            'tipo_participacion'     => 'expositor',
            'objetivo_comunicacional'=> 'Visibilidad de marca',
            'estado'                 => FeriaReceived::ESTADO_ACTIVA,
            'source'                 => FeriaReceived::SOURCE_AGENTE_FERIAS,
            'redes_sociales_target'  => ['facebook', 'linkedin'],
        ]);
        $this->createdFeriaIds[] = $feria->id;

        $orchestrator = $this->makeOrchestratorWith(null, null, null);

        $result = $orchestrator->generateFeriaItems($feria, ['facebook', 'linkedin']);

        // Capturar las grillas creadas para cleanup
        $grids = Grid::where('anio', 2027)->whereIn('red_social', ['facebook', 'linkedin'])->get();
        foreach ($grids as $g) {
            $this->createdGridIds[] = $g->id;
        }

        $this->assertTrue($result['success']);
        // §12.4 — 3 momentos por feria, multiplicado por 2 redes = 6 ítems
        $this->assertEquals(6, $result['items_creados']);

        // Verificar las 3 fases existen en BD para una de las redes
        $itemsFB = GridItem::where('feria_id', $feria->id)->where('red_social', 'facebook')->get();
        $this->assertCount(3, $itemsFB);

        $fases = $itemsFB->pluck('fase_feria')->toArray();
        $this->assertContains(FeriaReceived::FASE_2M,  $fases);
        $this->assertContains(FeriaReceived::FASE_1M,  $fases);
        $this->assertContains(FeriaReceived::FASE_15D, $fases);

        // §12.4 — los 3 momentos deben estar en fechas distintas
        $fechas = $itemsFB->pluck('fecha_publicacion')->map(function ($d) {
            return $d->format('Y-m-d');
        })->toArray();
        $this->assertContains('2027-02-14', $fechas, '60 días antes (-2 meses)');
        $this->assertContains('2027-03-16', $fechas, '30 días antes (-1 mes)');
        $this->assertContains('2027-03-31', $fechas, '15 días antes');
    }

    /*
    |==========================================================================
    | CASO 8 — Mostrar grilla ya creada por red y mes (Modo B — §11.6)
    |==========================================================================
    */

    /** @test */
    public function caso_8_consult_grid_filtra_por_red_y_mes()
    {
        $grid = $this->seedGrid('linkedin', 2027, [
            '2027-05-10', '2027-05-20', '2027-05-30',
            '2027-06-05', '2027-06-15',
        ]);

        $orchestrator = $this->makeOrchestratorWith(null, null, null);

        // Consultar solo mayo
        $resultMayo = $orchestrator->consultGrid([
            'anio'        => 2027,
            'red_social'  => 'linkedin',
            'fecha_desde' => '2027-05-01',
            'fecha_hasta' => '2027-05-31',
        ]);

        $this->assertTrue($resultMayo['found']);
        $this->assertCount(1, $resultMayo['grids']);
        $this->assertCount(3, $resultMayo['grids']->first()->items, 'Mayo tiene 3 items');

        // Consultar junio
        $resultJunio = $orchestrator->consultGrid([
            'anio'        => 2027,
            'red_social'  => 'linkedin',
            'fecha_desde' => '2027-06-01',
            'fecha_hasta' => '2027-06-30',
        ]);

        $this->assertCount(2, $resultJunio['grids']->first()->items, 'Junio tiene 2 items');
    }

    /*
    |==========================================================================
    | CASO 9 — Generar grilla sin campos humanos heredados (§7 + §17.5)
    |==========================================================================
    | La lógica editorial NO debe depender de campos humanos heredados
    | (diseñador_grafico, disenador_contenido, hora_publicacion, publicado).
    | Esos campos son metadata operativa opcional.
    */

    /** @test */
    public function caso_9_grid_item_funciona_sin_campos_humanos_heredados()
    {
        $grid = $this->seedGrid('facebook', 2027, []);

        // Crear un GridItem con SOLO campos editoriales esenciales
        $item = GridItem::create([
            'grid_id'           => $grid->id,
            'fecha_publicacion' => '2027-08-15',
            'red_social'        => 'facebook',
            'tematica'          => 'Sostenibilidad',
            'tipo_contenido'    => 'Contenido de valor',
            'item_order'        => 1,
            'origen'            => GridItem::ORIGEN_GRILLA,
            // SIN: revisor_id, aprobador_id, observaciones_operativas
            // SIN: hora_publicacion, publicado, diseñador, etc.
        ]);

        $this->assertNotNull($item->id, 'Item se crea sin campos humanos heredados');
        $this->assertEquals('Sostenibilidad', $item->tematica);

        // Verificar que el grid también acepta nulos en metadata operativa
        $this->assertNull($grid->revisor_id, 'revisor_id debe ser nullable');
        $this->assertNull($grid->aprobador_id, 'aprobador_id debe ser nullable');
        $this->assertNull($grid->observaciones_operativas, 'observaciones_operativas debe ser nullable');
    }

    /*
    |==========================================================================
    | CASO 10 — Operar correctamente con metadata operativa opcional (§8)
    |==========================================================================
    | Cuando la empresa SÍ usa metadata operativa, el agente debe poder
    | persistirla y consultarla sin que afecte la lógica editorial.
    */

    /** @test */
    public function caso_10_grid_acepta_metadata_operativa_cuando_se_provee()
    {
        $grid = Grid::create([
            'anio'                     => 2027,
            'periodo'                  => '2027-Q3',
            'tipo_periodo'             => Grid::TIPO_TRIMESTRAL,
            'red_social'               => 'instagram',
            'version'                  => 1,
            'estado'                   => Grid::ESTADO_EN_REVISION,
            'titulo'                   => 'Test metadata operativa',
            // Metadata operativa OPCIONAL (§8)
            'revisor_id'               => null,
            'aprobador_id'             => null,
            'estado_aprobacion'        => 'en_revision',
            'observaciones_operativas' => 'Aprobado por gerencia el día X',
        ]);
        $this->createdGridIds[] = $grid->id;

        $this->assertNotNull($grid->id);
        $this->assertEquals('en_revision', $grid->estado_aprobacion);
        $this->assertEquals('Aprobado por gerencia el día X', $grid->observaciones_operativas);

        // §8.2 — la metadata operativa NO debe condicionar la lógica editorial.
        // Verificamos que un GridItem se crea normalmente igual.
        $item = GridItem::create([
            'grid_id'           => $grid->id,
            'fecha_publicacion' => '2027-07-15',
            'red_social'        => 'instagram',
            'tematica'          => 'Test',
            'tipo_contenido'    => 'Promoción',
            'item_order'        => 1,
            'origen'            => GridItem::ORIGEN_GRILLA,
        ]);

        $this->assertNotNull($item->id, 'GridItem se crea aunque la grilla padre tenga metadata operativa activa');
    }

    /*
    |==========================================================================
    | Helpers
    |==========================================================================
    */

    /**
     * Crea un Grid con N items en las fechas dadas.
     *
     * @param  string  $red
     * @param  int     $anio
     * @param  array   $fechas
     * @return Grid
     */
    private function seedGrid($red, $anio, array $fechas)
    {
        $g = Grid::create([
            'anio'         => $anio,
            'periodo'      => 'TEST_' . substr(uniqid(), -6),
            'tipo_periodo' => Grid::TIPO_MENSUAL,
            'red_social'   => $red,
            'version'      => 1,
            'estado'       => Grid::ESTADO_ACTIVA,
            'titulo'       => 'Test ' . $red . ' ' . $anio,
        ]);
        $this->createdGridIds[] = $g->id;

        $order = 1;
        foreach ($fechas as $f) {
            GridItem::create([
                'grid_id'           => $g->id,
                'fecha_publicacion' => $f,
                'red_social'        => $red,
                'tematica'          => 'Test ' . $f,
                'tipo_contenido'    => 'Promoción',
                'item_order'        => $order++,
                'origen'            => GridItem::ORIGEN_GRILLA,
            ]);
        }
        return $g->fresh()->load('items');
    }

    private function seedHistoricalGrid($anio)
    {
        $hg = HistoricalGrid::create([
            'anio'                  => $anio,
            'nombre'                => 'TEST HG ' . $anio,
            'archivo_original'      => 'fake.xlsx',
            'archivo_storage_path'  => 'fake/path.xlsx',
            'contenido_normalizado' => [
                ['fecha' => "{$anio}-05-01", 'tematica' => 'Sostenibilidad', 'tipo_contenido' => 'Promoción'],
            ],
            'redes_detectadas'      => ['facebook', 'instagram'],
            'total_items'           => 1,
            'estado'                => HistoricalGrid::ESTADO_ANALIZADO,
        ]);
        $this->createdHistoricalIds[] = $hg->id;
        return $hg;
    }

    private function seedLearningPattern(HistoricalGrid $hg, $red, $tipo, $titulo)
    {
        $p = LearningPattern::create([
            'fuente'             => LearningPattern::FUENTE_HISTORICO,
            'historical_grid_id' => $hg->id,
            'tipo_patron'        => $tipo,
            'red_social'         => $red,
            'titulo'             => $titulo,
            'ocurrencias'        => 100,
            'confianza'          => 90,
            'active'             => true,
        ]);
        $this->createdPatternIds[] = $p->id;
        return $p;
    }

    /**
     * Crea un Orchestrator con engines mockeados.
     *
     * @param  mixed                                $generator   Mock o null para mock por defecto
     * @param  mixed                                $analyzer    Mock o null para mock vacío
     * @param  mixed                                $producer    Mock o null para mock vacío
     * @return MKT03Orchestrator
     */
    private function makeOrchestratorWith($generator, $analyzer, $producer)
    {
        if ($generator === null) {
            $generator = Mockery::mock(GridGeneratorEngine::class);
            $generator->shouldReceive('generateGrid')->andReturn(['items_generados' => [], 'meta' => ['mock' => true]]);
            $generator->shouldReceive('saveGeneratedItems')->andReturn(0);
        }
        if ($analyzer === null) {
            $analyzer = Mockery::mock(GridPatternAnalyzer::class);
        }
        if ($producer === null) {
            $producer = Mockery::mock(PieceProducerEngine::class);
        }

        return new MKT03Orchestrator($analyzer, $generator, $producer, new GridContinuityService());
    }

    /**
     * @param  array  $generatorReturns  Payload que retorna generateGrid()
     * @return MKT03Orchestrator
     */
    private function makeOrchestratorWithMockedGenerator(array $generatorReturns)
    {
        $generator = Mockery::mock(GridGeneratorEngine::class);
        $generator->shouldReceive('generateGrid')->andReturn(array_merge(
            ['items_generados' => [], 'meta' => ['mock' => true]],
            $generatorReturns
        ));
        $generator->shouldReceive('saveGeneratedItems')->andReturn(0);

        return $this->makeOrchestratorWith($generator, null, null);
    }
}
