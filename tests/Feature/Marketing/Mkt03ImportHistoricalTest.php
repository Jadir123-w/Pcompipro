<?php

namespace Tests\Feature\Marketing;

use Hyn\Tenancy\Environment;
use Hyn\Tenancy\Models\Website;
use Mockery;
use Modules\Marketing\Contracts\AIProviderInterface;
use Modules\Marketing\Models\HistoricalGrid;
use Modules\Marketing\Models\LearningPattern;
use Modules\Marketing\Services\ExcelHistoricalImporter;
use Modules\Marketing\Services\GridPatternAnalyzer;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;
use Tests\TestCase;

/**
 * Mkt03ImportHistoricalTest — MKT-03 M1
 *
 * Cubre el pipeline de import + análisis de planners editoriales históricos:
 *   1. Importer parsea hoja FB/IG estándar (label FECHA en col A, fechas en cols B+)
 *   2. Importer parsea hoja estilo TikTok 2023 (sin label FECHA, fechas en fila 1
 *      con texto tipo "Lunes: 02/01") — fallback de detección
 *   3. parseFecha maneja "DD/MM", "DD de MES", "Lunes: DD/MM", numero de Excel
 *   4. SHEET_NETWORK_MAP detecta correctamente la red por nombre de hoja
 *   5. GridPatternAnalyzer parsea respuesta JSON envuelta en code fences ```json
 *   6. GridPatternAnalyzer parsea respuesta con prefacio textual + JSON al final
 *   7. GridPatternAnalyzer entra en fallback cuando el provider retorna basura
 *   8. savePatterns persiste correctamente en mkt_learning_patterns
 *
 * Estrategia: usa la BD del tenant existente. Crea archivos Excel sintéticos
 * en runtime con PhpSpreadsheet (no requiere fixture en disco). Mockea el
 * AIProviderInterface con respuestas controladas.
 */
class Mkt03ImportHistoricalTest extends TestCase
{
    /** @var array  IDs de HistoricalGrid creados durante el test */
    private $createdGridIds = [];

    /** @var array  Paths de archivos temporales a borrar */
    private $tempFiles = [];

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
        // Limpiar HistoricalGrids + sus patterns
        if (!empty($this->createdGridIds)) {
            try {
                LearningPattern::whereIn('historical_grid_id', $this->createdGridIds)->delete();
                HistoricalGrid::whereIn('id', $this->createdGridIds)->delete();
            } catch (\Exception $e) {}
        }
        // Limpiar archivos temporales
        foreach ($this->tempFiles as $f) {
            if (file_exists($f)) {
                @unlink($f);
            }
        }
        Mockery::close();
        parent::tearDown();
    }

    /*
    |--------------------------------------------------------------------------
    | Tests del ExcelHistoricalImporter
    |--------------------------------------------------------------------------
    */

    /** @test */
    public function importer_parsea_hoja_fb_ig_con_fila_fecha_estandar()
    {
        $xlsxPath = $this->buildExcelEstandar();

        $hg = $this->createHistoricalGridFromFile(2024, $xlsxPath);

        $importer = new ExcelHistoricalImporter();
        $result = $importer->import($hg);

        $this->assertTrue($result['success'], 'Import debería tener success=true');
        $this->assertGreaterThanOrEqual(2, $result['total_items'], 'Debería detectar ≥ 2 ítems');
        $this->assertContains('facebook', $result['redes']);
        $this->assertContains('instagram', $result['redes']);

        $hg->refresh();
        $this->assertEquals(HistoricalGrid::ESTADO_IMPORTADO, $hg->estado);
        $contenido = $hg->contenido_normalizado;
        $this->assertIsArray($contenido);
        $this->assertNotEmpty($contenido[0]['fecha']);
        $this->assertEquals('Promo', $contenido[0]['tematica'] ?? null);
    }

    /** @test */
    public function importer_detecta_hoja_tiktok_sin_fila_fecha_via_fallback()
    {
        $xlsxPath = $this->buildExcelTikTokStyle();

        $hg = $this->createHistoricalGridFromFile(2023, $xlsxPath);

        $importer = new ExcelHistoricalImporter();
        $result = $importer->import($hg);

        $this->assertTrue($result['success']);
        $this->assertGreaterThanOrEqual(3, $result['total_items'],
            'Fallback debe detectar fechas en fila 1 estilo "Lunes: 02/01" y producir ≥ 3 ítems');
        $this->assertContains('tiktok', $result['redes']);

        $contenido = $hg->fresh()->contenido_normalizado;
        // El campo CONTENIDO debe haberse mapeado a copy
        $tieneCopy = false;
        foreach ($contenido as $item) {
            if (!empty($item['copy'])) {
                $tieneCopy = true;
                break;
            }
        }
        $this->assertTrue($tieneCopy, 'Al menos un ítem debe tener copy desde campo CONTENIDO');
    }

    /** @test */
    public function importer_normaliza_fechas_en_varios_formatos()
    {
        $xlsxPath = $this->buildExcelConFechasMixtas();
        $hg = $this->createHistoricalGridFromFile(2024, $xlsxPath);

        $importer = new ExcelHistoricalImporter();
        $result = $importer->import($hg);

        $this->assertTrue($result['success']);
        $contenido = $hg->fresh()->contenido_normalizado;
        $fechas = array_column($contenido, 'fecha');

        // Debe haber parseado al menos las 3 variantes que metimos
        $this->assertContains('2024-03-01', $fechas, 'Formato DD/MM debe parsear a YYYY-MM-DD');
        $this->assertContains('2024-04-15', $fechas, 'Formato "DD de MES" debe parsear a YYYY-MM-DD');
        $this->assertContains('2024-05-10', $fechas, 'Formato "Día: DD/MM" debe parsear a YYYY-MM-DD');
    }

    /** @test */
    public function importer_ignora_hojas_sin_red_reconocible()
    {
        $xlsxPath = $this->buildExcelConHojaDesconocida();
        $hg = $this->createHistoricalGridFromFile(2024, $xlsxPath);

        $importer = new ExcelHistoricalImporter();
        $result = $importer->import($hg);

        $this->assertTrue($result['success']);
        // Solo debe tener LinkedIn (la hoja "RESUMEN" se ignora)
        $this->assertEquals(['linkedin'], $result['redes']);
    }

    /*
    |--------------------------------------------------------------------------
    | Tests del GridPatternAnalyzer
    |--------------------------------------------------------------------------
    */

    /** @test */
    public function analyzer_parsea_respuesta_envuelta_en_code_fences()
    {
        $hg = $this->createHistoricalGridConContenido(2024);

        $jsonPayload = json_encode([
            'resumen_analisis'    => 'Test analysis',
            'patrones_detectados' => [
                [
                    'tipo_patron' => 'frecuencia',
                    'red_social'  => 'facebook',
                    'titulo'      => 'Patrón fence-wrapped',
                    'ocurrencias' => 10,
                    'confianza'   => 90,
                ],
            ],
        ]);
        $wrapped = "```json\n{$jsonPayload}\n```";

        $provider = $this->mockProvider($wrapped);
        $analyzer = new GridPatternAnalyzer($provider);

        $result = $analyzer->analyzeHistoricalGrid($hg);

        $this->assertEmpty($result['_fallback'] ?? null, 'No debería caer en fallback');
        $this->assertCount(1, $result['patrones_detectados']);
        $this->assertEquals('Patrón fence-wrapped', $result['patrones_detectados'][0]['titulo']);
    }

    /** @test */
    public function analyzer_parsea_respuesta_con_prefacio_textual()
    {
        $hg = $this->createHistoricalGridConContenido(2024);

        $jsonPayload = json_encode([
            'resumen_analisis'    => 'Test',
            'patrones_detectados' => [
                ['tipo_patron' => 'tono', 'titulo' => 'Tono formal', 'ocurrencias' => 5, 'confianza' => 80],
            ],
        ]);
        // El LLM a veces escribe "Aquí está el análisis:" antes del JSON real
        $withPreface = "Aquí está el análisis:\n\n{$jsonPayload}\n\nFin.";

        $provider = $this->mockProvider($withPreface);
        $analyzer = new GridPatternAnalyzer($provider);

        $result = $analyzer->analyzeHistoricalGrid($hg);

        $this->assertEmpty($result['_fallback'] ?? null);
        $this->assertCount(1, $result['patrones_detectados']);
        $this->assertEquals('Tono formal', $result['patrones_detectados'][0]['titulo']);
    }

    /** @test */
    public function analyzer_entra_en_fallback_si_respuesta_no_es_json()
    {
        $hg = $this->createHistoricalGridConContenido(2024);

        $provider = $this->mockProvider('No tengo nada que decir, lo siento.');
        $analyzer = new GridPatternAnalyzer($provider);

        $result = $analyzer->analyzeHistoricalGrid($hg);

        $this->assertTrue($result['_fallback'] ?? false);
        $this->assertEmpty($result['patrones_detectados']);
    }

    /** @test */
    public function analyzer_save_patterns_persiste_en_bd()
    {
        $hg = $this->createHistoricalGridConContenido(2024);

        $provider = $this->mockProvider('{}'); // no usado en savePatterns
        $analyzer = new GridPatternAnalyzer($provider);

        $analysisResult = [
            'patrones_detectados' => [
                [
                    'tipo_patron'  => 'frecuencia',
                    'red_social'   => 'linkedin',
                    'titulo'       => 'Test pattern A',
                    'descripcion'  => 'A description',
                    'datos_patron' => ['posts_dia' => 1],
                    'ocurrencias'  => 50,
                    'confianza'    => 85,
                ],
                [
                    'tipo_patron' => 'temporada',
                    'titulo'      => 'Test pattern B',
                    'ocurrencias' => 12,
                    'confianza'   => 70,
                ],
            ],
        ];

        $saved = $analyzer->savePatterns($analysisResult, $hg);

        $this->assertEquals(2, $saved);
        $patterns = LearningPattern::where('historical_grid_id', $hg->id)->get();
        $this->assertCount(2, $patterns);
        $titulos = $patterns->pluck('titulo')->toArray();
        $this->assertContains('Test pattern A', $titulos);
        $this->assertContains('Test pattern B', $titulos);
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers para fabricar archivos Excel sintéticos
    |--------------------------------------------------------------------------
    */

    /**
     * Genera un planner estándar con FECHA en col A:
     *   - Hoja "FB IG - TEST"
     *   - Fila 1: TÍTULO de la hoja (no se usa)
     *   - Fila 2: FECHA | 01/05 | 02/05 | 03/05
     *   - Fila 3: TEMÁTICA | Promo | Trivia | Valor
     *   - Fila 4: COPY | Texto1 | Texto2 | Texto3
     */
    private function buildExcelEstandar()
    {
        $sp = new Spreadsheet();
        $sheet = $sp->getActiveSheet();
        $sheet->setTitle('FB IG - TEST');
        $sheet->setCellValue('A1', '');
        $sheet->setCellValue('A2', 'FECHA');
        $sheet->setCellValue('B2', '01/05');
        $sheet->setCellValue('C2', '02/05');
        $sheet->setCellValue('D2', '03/05');
        $sheet->setCellValue('A3', 'TEMÁTICA');
        $sheet->setCellValue('B3', 'Promo');
        $sheet->setCellValue('C3', 'Trivia');
        $sheet->setCellValue('D3', 'Valor');
        $sheet->setCellValue('A4', 'COPY');
        $sheet->setCellValue('B4', 'Texto1');
        $sheet->setCellValue('C4', 'Texto2');
        $sheet->setCellValue('D4', 'Texto3');

        return $this->saveTempXlsx($sp);
    }

    /**
     * Genera un planner estilo TikTok 2023 sin label FECHA:
     *   - Hoja "TIK TOK - TEST"
     *   - Fila 1: (vacío) | Lunes: 02/01 | Martes: 03/01 | Miercoles: 04/01
     *   - Fila 2: HORA DE PUBLICACIÓN
     *   - Fila 3: TITULO | Titulo1 | Titulo2 | Titulo3
     *   - Fila 4: CONTENIDO | Cuerpo1 | Cuerpo2 | Cuerpo3
     */
    private function buildExcelTikTokStyle()
    {
        $sp = new Spreadsheet();
        $sheet = $sp->getActiveSheet();
        $sheet->setTitle('TIK TOK - TEST');
        $sheet->setCellValue('A1', '');
        $sheet->setCellValue('B1', 'Lunes: 02/01');
        $sheet->setCellValue('C1', 'Martes: 03/01');
        $sheet->setCellValue('D1', 'Miercoles: 04/01');
        $sheet->setCellValue('E1', 'Jueves: 05/01');
        $sheet->setCellValue('F1', 'Viernes: 06/01');
        $sheet->setCellValue('A2', 'HORA DE PUBLICACIÓN');
        $sheet->setCellValue('A3', 'TITULO');
        $sheet->setCellValue('B3', 'Titulo1');
        $sheet->setCellValue('C3', 'Titulo2');
        $sheet->setCellValue('D3', 'Titulo3');
        $sheet->setCellValue('E3', 'Titulo4');
        $sheet->setCellValue('F3', 'Titulo5');
        $sheet->setCellValue('A4', 'CONTENIDO');
        $sheet->setCellValue('B4', 'Cuerpo1');
        $sheet->setCellValue('C4', 'Cuerpo2');
        $sheet->setCellValue('D4', 'Cuerpo3');
        $sheet->setCellValue('E4', 'Cuerpo4');
        $sheet->setCellValue('F4', 'Cuerpo5');

        return $this->saveTempXlsx($sp);
    }

    /**
     * Genera un planner con fechas en 3 formatos distintos en la misma hoja.
     */
    private function buildExcelConFechasMixtas()
    {
        $sp = new Spreadsheet();
        $sheet = $sp->getActiveSheet();
        $sheet->setTitle('LINKEDIN - TEST');
        $sheet->setCellValue('A1', 'FECHA');
        $sheet->setCellValue('B1', '01/03');                // DD/MM
        $sheet->setCellValue('C1', '15 de abril');          // DD de MES
        $sheet->setCellValue('D1', 'Viernes: 10/05');       // Día: DD/MM
        $sheet->setCellValue('A2', 'TEMÁTICA');
        $sheet->setCellValue('B2', 'Tema1');
        $sheet->setCellValue('C2', 'Tema2');
        $sheet->setCellValue('D2', 'Tema3');

        return $this->saveTempXlsx($sp);
    }

    /**
     * Genera un Excel con 2 hojas: una LINKEDIN reconocible + una "RESUMEN" no editorial.
     */
    private function buildExcelConHojaDesconocida()
    {
        $sp = new Spreadsheet();

        // Hoja 1: LINKEDIN
        $sheet1 = $sp->getActiveSheet();
        $sheet1->setTitle('LINKEDIN - TEST');
        $sheet1->setCellValue('A1', 'FECHA');
        $sheet1->setCellValue('B1', '01/05');
        $sheet1->setCellValue('A2', 'TEMÁTICA');
        $sheet1->setCellValue('B2', 'Tema');

        // Hoja 2: RESUMEN (no debería ser parseada)
        $sheet2 = $sp->createSheet();
        $sheet2->setTitle('RESUMEN');
        $sheet2->setCellValue('A1', 'Total posts');
        $sheet2->setCellValue('B1', 100);

        return $this->saveTempXlsx($sp);
    }

    /**
     * Guarda el spreadsheet a un archivo temporal y registra el path para cleanup.
     *
     * @param  Spreadsheet  $sp
     * @return string                Path absoluto del .xlsx generado
     */
    private function saveTempXlsx(Spreadsheet $sp)
    {
        $tmpDir = storage_path('app/mkt03/test_temp');
        if (!is_dir($tmpDir)) {
            @mkdir($tmpDir, 0775, true);
        }
        $path = $tmpDir . DIRECTORY_SEPARATOR . 'test_' . uniqid() . '.xlsx';
        $writer = new XlsxWriter($sp);
        $writer->save($path);
        $this->tempFiles[] = $path;
        return $path;
    }

    /**
     * Crea un HistoricalGrid apuntando a un archivo del filesystem (uses absolute
     * path, not Storage facade).
     *
     * @param  int     $anio
     * @param  string  $absolutePath
     * @return HistoricalGrid
     */
    private function createHistoricalGridFromFile($anio, $absolutePath)
    {
        // Mover al storage del tenant para que ExcelHistoricalImporter lo encuentre via Storage::disk()
        $relativeStorage = 'mkt03/test_temp/' . basename($absolutePath);
        $storageFull = storage_path('app/' . $relativeStorage);

        // Si el archivo todavía no está en la ubicación esperada, copiarlo
        if (realpath($absolutePath) !== realpath($storageFull)) {
            $dir = dirname($storageFull);
            if (!is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
            @copy($absolutePath, $storageFull);
            $this->tempFiles[] = $storageFull;
        }

        $hg = HistoricalGrid::create([
            'anio'                 => $anio,
            'nombre'               => 'TEST ' . $anio,
            'archivo_original'     => basename($absolutePath),
            'archivo_storage_path' => $relativeStorage,
            'estado'               => HistoricalGrid::ESTADO_IMPORTADO,
            'user_id'              => null,
        ]);

        // El config disk default es 'public' pero usamos 'local' (storage/app/) en los tests.
        // Forzamos local config para que Storage::disk()->path() resuelva bien el path arriba.
        config(['marketing.chatbot.mkt03.image_storage_disk' => 'local']);

        $this->createdGridIds[] = $hg->id;
        return $hg;
    }

    /**
     * Crea un HistoricalGrid con contenido_normalizado precargado (sin Excel real).
     *
     * @param  int  $anio
     * @return HistoricalGrid
     */
    private function createHistoricalGridConContenido($anio)
    {
        $hg = HistoricalGrid::create([
            'anio'                  => $anio,
            'nombre'                => 'TEST ' . $anio,
            'archivo_original'      => 'fake.xlsx',
            'archivo_storage_path'  => 'mkt03/test_fake.xlsx',
            'contenido_normalizado' => [
                ['fecha' => "{$anio}-05-01", 'red_social' => 'facebook', 'tematica' => 'Promo', 'tipo_contenido' => 'Promoción'],
                ['fecha' => "{$anio}-05-02", 'red_social' => 'facebook', 'tematica' => 'Valor',  'tipo_contenido' => 'Contenido de valor'],
            ],
            'redes_detectadas'      => ['facebook'],
            'total_items'           => 2,
            'estado'                => HistoricalGrid::ESTADO_IMPORTADO,
            'user_id'               => null,
        ]);

        $this->createdGridIds[] = $hg->id;
        return $hg;
    }

    /**
     * Crea un mock del AIProviderInterface que retorna `$response` ante cualquier llamada.
     *
     * @param  string  $response
     * @return AIProviderInterface
     */
    private function mockProvider($response)
    {
        $mock = Mockery::mock(AIProviderInterface::class);
        $mock->shouldReceive('sendMessage')
             ->andReturn($response);
        return $mock;
    }
}
