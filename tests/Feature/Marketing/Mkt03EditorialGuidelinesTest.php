<?php

namespace Tests\Feature\Marketing;

use Hyn\Tenancy\Environment;
use Hyn\Tenancy\Models\Website;
use Mockery;
use Modules\Marketing\Contracts\AIProviderInterface;
use Modules\Marketing\Services\EditorialGuidelinesService;
use Modules\Marketing\Services\GridGeneratorEngine;
use Tests\TestCase;

/**
 * Mkt03EditorialGuidelinesTest — MKT-03 M4
 *
 * Cubre el EditorialGuidelinesService y su integración con los engines.
 *
 * Cubre:
 *   1. El service expone las 10 líneas comerciales de COMPIPRO
 *   2. El service expone las 7 historias destacadas de Instagram
 *   3. El service expone los 3 tipos de contenido del histórico
 *   4. buildSystemPromptAddendum sin red devuelve el bloque global
 *   5. buildSystemPromptAddendum con red devuelve también la sección por red
 *   6. Optimizaciones específicas por red (FB/IG/LI/TT) coinciden con los PDFs
 *   7. GridGeneratorEngine inyecta el addendum al system prompt al llamar IA
 */
class Mkt03EditorialGuidelinesTest extends TestCase
{
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
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function expone_las_10_lineas_comerciales_compipro()
    {
        $svc = new EditorialGuidelinesService();
        $lineas = $svc->getLineasComerciales();

        $this->assertIsArray($lineas);
        $this->assertCount(10, $lineas, 'Debe haber exactamente 10 líneas comerciales');

        // Spot-check de líneas críticas mencionadas por el cliente
        $this->assertContains('Artículos Promocionales', $lineas);
        $this->assertContains('Línea Textil', $lineas);
        $this->assertContains('Kits Corporativos', $lineas);
        $this->assertContains('Artículos Ecológicos', $lineas);
    }

    /** @test */
    public function expone_las_7_historias_destacadas_instagram()
    {
        $svc = new EditorialGuidelinesService();
        $destacadas = $svc->getHistoriasDestacadasInstagram();

        $this->assertIsArray($destacadas);
        $this->assertCount(7, $destacadas, 'IG @compipro tiene 7 destacadas definidas');

        // Spot-check de las que están en el feed real
        $this->assertContains('Artículos Promocionales', $destacadas);
        $this->assertContains('Kits Corporativos', $destacadas);
        $this->assertContains('Línea Textil', $destacadas);
    }

    /** @test */
    public function expone_los_3_tipos_de_contenido_del_historico()
    {
        $svc = new EditorialGuidelinesService();
        $tipos = $svc->getTiposContenidoHistorico();

        $this->assertCount(3, $tipos);
        $this->assertContains('Promoción',         $tipos);
        $this->assertContains('Trivia',            $tipos);
        $this->assertContains('Contenido de valor',$tipos);
    }

    /** @test */
    public function build_addendum_sin_red_incluye_bloques_globales()
    {
        $svc = new EditorialGuidelinesService();
        $addendum = $svc->buildSystemPromptAddendum(null);

        $this->assertStringContainsString('HALLAZGOS EDITORIALES COMPIPRO', $addendum);
        $this->assertStringContainsString('Líneas comerciales reconocidas (10)', $addendum);
        $this->assertStringContainsString('7 historias destacadas de Instagram', $addendum);
        $this->assertStringContainsString('Tipos de contenido del histórico', $addendum);
        $this->assertStringContainsString('Identidad visual a PRESERVAR', $addendum);
        $this->assertStringContainsString('Vicios a CORREGIR', $addendum);
        $this->assertStringContainsString('storytelling', strtolower($addendum));

        // No debe haber sección por red cuando red=null
        $this->assertStringNotContainsString('Optimizaciones específicas para FACEBOOK', $addendum);
        $this->assertStringNotContainsString('Optimizaciones específicas para INSTAGRAM', $addendum);
    }

    /** @test */
    public function build_addendum_con_red_agrega_seccion_especifica()
    {
        $svc = new EditorialGuidelinesService();

        $addendumIG = $svc->buildSystemPromptAddendum('instagram');
        $this->assertStringContainsString('Optimizaciones específicas para INSTAGRAM', $addendumIG);
        $this->assertStringContainsString('Compartidos por DM', $addendumIG,
            'IG: la señal más fuerte del algoritmo (compartidos por DM) debe estar reforzada');
        $this->assertStringContainsString('15', $addendumIG, 'IG: max_hashtags 15');

        $addendumLI = $svc->buildSystemPromptAddendum('linkedin');
        $this->assertStringContainsString('Optimizaciones específicas para LINKEDIN', $addendumLI);
        $this->assertStringContainsString('800-1500', $addendumLI, 'LI: longitud óptima 800-1500 chars');
        $this->assertStringContainsString('REDUCIR', $addendumLI, 'LI: regla de reducir contenido promocional');

        $addendumTT = $svc->buildSystemPromptAddendum('tiktok');
        $this->assertStringContainsString('Optimizaciones específicas para TIKTOK', $addendumTT);
        $this->assertStringContainsString('IMAGEN ESTÁTICA', $addendumTT,
            'TT: en E1 NO se generan videos, solo imagen + brief');
    }

    /** @test */
    public function optimizaciones_por_red_facebook_define_objetivo_y_max_hashtags()
    {
        $svc = new EditorialGuidelinesService();
        $opt = $svc->getOptimizacionesPorRed('facebook');

        $this->assertNotNull($opt);
        $this->assertEquals(5, $opt['max_hashtags']);
        $this->assertStringContainsString('Cercanía', $opt['objetivo']);
    }

    /** @test */
    public function red_invalida_devuelve_null_en_optimizaciones()
    {
        $svc = new EditorialGuidelinesService();
        $this->assertNull($svc->getOptimizacionesPorRed('youtube'),
            'YouTube fue descartado del alcance, no debe tener optimizaciones');
        $this->assertNull($svc->getOptimizacionesPorRed('twitter'));
    }

    /** @test */
    public function grid_generator_engine_inyecta_addendum_al_llamar_ia()
    {
        // Mock del provider que captura el system prompt enviado
        $capturedSystemPrompt = '';
        $providerMock = Mockery::mock(AIProviderInterface::class);
        $providerMock->shouldReceive('sendMessage')
            ->andReturnUsing(function ($systemPrompt, $messages, $options) use (&$capturedSystemPrompt) {
                $capturedSystemPrompt = $systemPrompt;
                // Devolver un JSON válido mínimo para que el engine no falle
                return json_encode([
                    'resumen_generacion' => 'mock',
                    'parametros_usados'  => [],
                    'items_generados'    => [],
                ]);
            });

        $engine = new GridGeneratorEngine($providerMock, new EditorialGuidelinesService());

        $engine->generateGrid([
            'fecha_desde' => '2027-08-01',
            'fecha_hasta' => '2027-08-31',
            'redes'       => ['linkedin'],
            'tipo_periodo'=> 'mensual',
        ]);

        $this->assertNotEmpty($capturedSystemPrompt);
        $this->assertStringContainsString('HALLAZGOS EDITORIALES COMPIPRO', $capturedSystemPrompt,
            'El system prompt enviado al LLM debe incluir el addendum de guidelines');
        $this->assertStringContainsString('Optimizaciones específicas para LINKEDIN', $capturedSystemPrompt,
            'Cuando se solicita 1 sola red, el addendum debe incluir su sección específica');
    }

    /** @test */
    public function grid_generator_retry_reutiliza_system_prompt_y_limite_de_tokens()
    {
        $capturedOptions = [];
        $capturedPrompts = [];
        $responses = [
            'respuesta que no es JSON',
            json_encode([
                'resumen_generacion' => 'mock',
                'parametros_usados'  => [],
                'items_generados'    => [],
            ]),
        ];
        $providerMock = Mockery::mock(AIProviderInterface::class);
        $providerMock->shouldReceive('sendMessage')
            ->twice()
            ->andReturnUsing(function ($systemPrompt, $messages, $options) use (&$capturedOptions, &$capturedPrompts, &$responses) {
                $capturedPrompts[] = $systemPrompt;
                $capturedOptions[] = $options;
                return array_shift($responses);
            });

        $engine = new GridGeneratorEngine($providerMock, new EditorialGuidelinesService());
        $result = $engine->generateGrid([
            'fecha_desde' => '2027-08-01',
            'fecha_hasta' => '2027-08-31',
            'redes'       => ['linkedin'],
            'tipo_periodo'=> 'mensual',
        ]);

        $this->assertArrayHasKey('items_generados', $result);
        $this->assertSame($capturedPrompts[0], $capturedPrompts[1]);
        $this->assertSame(16000, $capturedOptions[1]['max_tokens']);
    }
}
