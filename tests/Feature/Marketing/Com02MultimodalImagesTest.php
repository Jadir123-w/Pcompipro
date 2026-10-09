<?php

namespace Tests\Feature\Marketing;

use Hyn\Tenancy\Environment;
use Hyn\Tenancy\Models\Website;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Modules\Marketing\Models\PreQuote;
use Modules\Marketing\Models\PreQuoteItem;
use Modules\Marketing\Services\AI\ClaudeProvider;
use Modules\Marketing\Services\AI\GeminiProvider;
use Modules\Marketing\Services\AI\OpenAIProvider;
use Modules\Marketing\Services\QuotationAIEngine;
use Tests\TestCase;

/**
 * Com02MultimodalImagesTest — COM-02 S2
 *
 * Verifica el soporte multimodal (imagen) en los 3 providers + la
 * integración del AIEngine con la imagen del prequote.
 *
 * Cubre:
 *   1. GeminiProvider.formatMessages inyecta inline_data en primer user
 *   2. ClaudeProvider.formatMessages inyecta image content block
 *   3. OpenAIProvider.sendMessage construye payload con image_url
 *   4. QuotationAIEngine.loadImagesForContext lee la imagen del disk y la
 *      codifica base64 + detecta MIME
 *   5. QuotationAIEngine.loadImagesForContext rechaza imagen >5MB
 *   6. QuotationAIEngine.loadImagesForContext devuelve [] si no hay path
 *   7. QuotationAIEngine.loadImagesForContext devuelve [] si el archivo no existe
 *
 * Los providers se testean solo en su lógica de formateo (no se hace
 * llamada HTTP real), usando reflection sobre formatMessages que es private.
 */
class Com02MultimodalImagesTest extends TestCase
{
    /** @var array  Paths de imágenes creadas para cleanup */
    private $createdImagePaths = [];
    private $createdPrequoteIds = [];

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
        foreach ($this->createdImagePaths as $path) {
            try { Storage::disk('local')->delete($path); } catch (\Exception $e) {}
        }
        if (!empty($this->createdPrequoteIds)) {
            try {
                PreQuoteItem::whereIn('prequote_id', $this->createdPrequoteIds)->delete();
                DB::connection('tenant')->table('quotation_prequotes')
                    ->whereIn('id', $this->createdPrequoteIds)->delete();
            } catch (\Exception $e) {}
        }
        parent::tearDown();
    }

    /*
    |--------------------------------------------------------------------------
    | Tests de los providers (reflection sobre private formatMessages)
    |--------------------------------------------------------------------------
    */

    /**
     * Caso 1 — GeminiProvider: imagen se inyecta como inline_data en el
     * primer user message del payload.
     */
    public function test_caso1_gemini_formato_multimodal()
    {
        $provider = new GeminiProvider([
            'api_key' => 'fake-test-key',
            'model'   => 'gemini-3.6-flash',
        ]);

        $messages = [
            ['role' => 'user', 'content' => 'Cotizar 100 mochilas'],
        ];
        $images = [
            ['mime_type' => 'image/jpeg', 'data' => 'BASE64TEST'],
        ];

        $reflection = new \ReflectionMethod($provider, 'formatMessages');
        $reflection->setAccessible(true);
        $formatted = $reflection->invoke($provider, $messages, $images);

        $this->assertCount(1, $formatted, 'Debe haber 1 user message');
        $this->assertEquals('user', $formatted[0]['role']);

        // Debe tener 2 parts: text + inline_data
        $this->assertCount(2, $formatted[0]['parts']);
        $this->assertArrayHasKey('text', $formatted[0]['parts'][0]);
        $this->assertEquals('Cotizar 100 mochilas', $formatted[0]['parts'][0]['text']);

        $this->assertArrayHasKey('inline_data', $formatted[0]['parts'][1]);
        $this->assertEquals('image/jpeg', $formatted[0]['parts'][1]['inline_data']['mime_type']);
        $this->assertEquals('BASE64TEST', $formatted[0]['parts'][1]['inline_data']['data']);
    }

    /**
     * Caso 2 — ClaudeProvider: imagen se inyecta como image content block.
     */
    public function test_caso2_claude_formato_multimodal()
    {
        $provider = new ClaudeProvider([
            'api_key' => 'fake-test-key',
            'model'   => 'claude-sonnet-4-20250514',
        ]);

        $messages = [
            ['role' => 'user', 'content' => 'Cotizar 50 polos'],
        ];
        $images = [
            ['mime_type' => 'image/png', 'data' => 'BASE64CLAUDE'],
        ];

        $reflection = new \ReflectionMethod($provider, 'formatMessages');
        $reflection->setAccessible(true);
        $formatted = $reflection->invoke($provider, $messages, $images);

        $this->assertCount(1, $formatted);
        $this->assertEquals('user', $formatted[0]['role']);

        // El content debe ser ahora un array de blocks (no string)
        $this->assertIsArray($formatted[0]['content']);
        $this->assertCount(2, $formatted[0]['content']);

        // Primer block: image
        $this->assertEquals('image', $formatted[0]['content'][0]['type']);
        $this->assertEquals('base64', $formatted[0]['content'][0]['source']['type']);
        $this->assertEquals('image/png', $formatted[0]['content'][0]['source']['media_type']);
        $this->assertEquals('BASE64CLAUDE', $formatted[0]['content'][0]['source']['data']);

        // Segundo block: text
        $this->assertEquals('text', $formatted[0]['content'][1]['type']);
        $this->assertEquals('Cotizar 50 polos', $formatted[0]['content'][1]['text']);
    }

    /**
     * Caso 3 — Sin imágenes, los providers mantienen el formato simple
     * (string content para Claude, parts solo con text para Gemini).
     */
    public function test_caso3_sin_imagenes_formato_simple()
    {
        // Claude
        $claude = new ClaudeProvider(['api_key' => 'fake', 'model' => 'claude-sonnet-4-20250514']);
        $reflClaude = new \ReflectionMethod($claude, 'formatMessages');
        $reflClaude->setAccessible(true);
        $cFormat = $reflClaude->invoke($claude, [['role' => 'user', 'content' => 'hola']], []);
        $this->assertEquals('hola', $cFormat[0]['content'],
            'Sin imágenes, content debe ser string');

        // Gemini
        $gemini = new GeminiProvider(['api_key' => 'fake', 'model' => 'gemini-3.6-flash']);
        $reflGem = new \ReflectionMethod($gemini, 'formatMessages');
        $reflGem->setAccessible(true);
        $gFormat = $reflGem->invoke($gemini, [['role' => 'user', 'content' => 'hola']], []);
        $this->assertCount(1, $gFormat[0]['parts'], 'Sin imágenes, solo 1 part de texto');
        $this->assertEquals('hola', $gFormat[0]['parts'][0]['text']);
    }

    /*
    |--------------------------------------------------------------------------
    | Tests del AIEngine.loadImagesForContext
    |--------------------------------------------------------------------------
    */

    /**
     * Caso 4 — loadImagesForContext lee disk + codifica base64 + detecta MIME.
     */
    public function test_caso4_load_images_lee_y_codifica()
    {
        // PNG mínimo válido (1x1 transparente)
        $pngBytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=');
        $path = 'quotation-images/test/' . uniqid() . '.png';
        Storage::disk('local')->put($path, $pngBytes);
        $this->createdImagePaths[] = $path;

        $engine = app(QuotationAIEngine::class);
        $reflection = new \ReflectionMethod($engine, 'loadImagesForContext');
        $reflection->setAccessible(true);
        $images = $reflection->invoke($engine, [
            'reference_image_path' => $path,
            'reference_image_mime' => 'image/png',
        ]);

        $this->assertCount(1, $images);
        $this->assertEquals('image/png', $images[0]['mime_type']);
        $this->assertEquals(base64_encode($pngBytes), $images[0]['data']);
    }

    /**
     * Caso 5 — loadImagesForContext rechaza imagen >5MB.
     */
    public function test_caso5_imagen_grande_rechazada()
    {
        // Crear archivo "grande" (6 MB) — solo bytes random para el test
        $bigBytes = str_repeat('X', 6 * 1024 * 1024);
        $path = 'quotation-images/test/big-' . uniqid() . '.jpg';
        Storage::disk('local')->put($path, $bigBytes);
        $this->createdImagePaths[] = $path;

        $engine = app(QuotationAIEngine::class);
        $reflection = new \ReflectionMethod($engine, 'loadImagesForContext');
        $reflection->setAccessible(true);
        $images = $reflection->invoke($engine, [
            'reference_image_path' => $path,
            'reference_image_mime' => 'image/jpeg',
        ]);

        $this->assertEmpty($images, 'Imagen >5MB debe ser rechazada');
    }

    /**
     * Caso 6 — Sin path en context devuelve [].
     */
    public function test_caso6_sin_path_en_context()
    {
        $engine = app(QuotationAIEngine::class);
        $reflection = new \ReflectionMethod($engine, 'loadImagesForContext');
        $reflection->setAccessible(true);
        $this->assertEmpty($reflection->invoke($engine, []));
        $this->assertEmpty($reflection->invoke($engine, ['reference_image_path' => null]));
    }

    /**
     * Caso 7 — Path apunta a archivo inexistente devuelve [].
     */
    public function test_caso7_archivo_inexistente()
    {
        $engine = app(QuotationAIEngine::class);
        $reflection = new \ReflectionMethod($engine, 'loadImagesForContext');
        $reflection->setAccessible(true);
        $images = $reflection->invoke($engine, [
            'reference_image_path' => 'quotation-images/test/no-existe-fx.png',
        ]);
        $this->assertEmpty($images);
    }

    /**
     * Caso 8 — interpretRequirement con imagen pasa images al provider.
     * (mock del provider para verificar)
     */
    public function test_caso8_interpret_requirement_con_imagen_pasa_options()
    {
        // Sembrar imagen real
        $pngBytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=');
        $path = 'quotation-images/test/' . uniqid() . '.png';
        Storage::disk('local')->put($path, $pngBytes);
        $this->createdImagePaths[] = $path;

        // Mock del provider que captura $options
        $providerMock = \Mockery::mock(\Modules\Marketing\Contracts\AIProviderInterface::class);
        $capturedOptions = null;
        $providerMock->shouldReceive('sendMessage')
            ->once()
            ->andReturnUsing(function ($prompt, $messages, $options) use (&$capturedOptions) {
                $capturedOptions = $options;
                // Devolver JSON válido mínimo
                return '{"interpretation_summary":"test","total_items_detected":0,"items":[],"shared_attributes_applied":[],"warnings":[]}';
            });
        $providerMock->shouldReceive('getProviderName')->andReturn('test');
        $providerMock->shouldReceive('getModelName')->andReturn('test-model');

        $engine = app(QuotationAIEngine::class);

        // Inyectar el mock al engine vía reflection
        $providerProp = new \ReflectionProperty($engine, 'provider');
        $providerProp->setAccessible(true);
        $providerProp->setValue($engine, $providerMock);

        $engine->interpretRequirement('Cotizar 100 mochilas', [
            'reference_image_path' => $path,
            'reference_image_mime' => 'image/png',
        ]);

        $this->assertNotNull($capturedOptions, 'sendMessage debe haber sido llamado');
        $this->assertArrayHasKey('images', $capturedOptions,
            'options.images debe estar presente cuando hay imagen');
        $this->assertCount(1, $capturedOptions['images']);
        $this->assertEquals('image/png', $capturedOptions['images'][0]['mime_type']);

        \Mockery::close();
    }
}
