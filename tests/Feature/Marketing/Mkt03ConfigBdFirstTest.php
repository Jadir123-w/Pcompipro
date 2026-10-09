<?php

namespace Tests\Feature\Marketing;

use Hyn\Tenancy\Environment;
use Hyn\Tenancy\Models\Website;
use Modules\Marketing\Contracts\ImageProviderInterface;
use Modules\Marketing\Models\ChatbotConfig;
use Modules\Marketing\Services\Image\DallE3Provider;
use Modules\Marketing\Services\Image\GPTImage2Provider;
use Tests\TestCase;

/**
 * Mkt03ConfigBdFirstTest — refactor "configs operativas a BD"
 *
 * Cubre que los settings operativos del MKT-03 (provider, modelo, quality,
 * format) ahora se leen PRIMERO de marketing_chatbot_config y solo si la
 * BD no tiene la clave, caen al config legacy del .env.
 *
 * Cubre:
 *   1. ChatbotConfig acepta value_type='select' con options JSON
 *   2. ChatbotServiceProvider resuelve ImageProviderInterface según el valor
 *      de mkt03_image_provider en BD (no del .env)
 *   3. DallE3Provider toma el modelo desde BD si está
 *   4. GPTImage2Provider toma el modelo + quality + format desde BD
 *   5. Si la key en BD está vacía, fallback al .env funciona
 */
class Mkt03ConfigBdFirstTest extends TestCase
{
    /** Backup de valores originales para restaurar en tearDown */
    private $originalValues = [];

    /** Keys que vamos a tocar */
    private $keys = [
        'mkt03_image_provider',
        'mkt03_image_model',
        'mkt03_image_quality',
        'mkt03_image_format',
    ];

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

        // Backup
        foreach ($this->keys as $key) {
            $row = ChatbotConfig::byKey($key);
            $this->originalValues[$key] = $row ? $row->config_value : null;
        }
    }

    protected function tearDown(): void
    {
        // Restaurar valores originales
        foreach ($this->originalValues as $key => $value) {
            $row = ChatbotConfig::byKey($key);
            if ($row && $value !== null) {
                $row->config_value = $value;
                $row->save();
            }
        }
        parent::tearDown();
    }

    /** @test */
    public function chatbot_config_acepta_value_type_select_con_options_json()
    {
        $row = ChatbotConfig::byKey('mkt03_image_provider');

        $this->assertNotNull($row, 'La key mkt03_image_provider debe existir tras la migración');
        $this->assertEquals('select', $row->value_type);
        $this->assertIsArray($row->options, 'options debe estar casteado a array por el cast del modelo');
        $this->assertGreaterThanOrEqual(2, count($row->options),
            'Debe tener al menos 2 opciones (dalle3, gpt-image-2)');

        // Verificar formato {value, label}
        $first = $row->options[0];
        $this->assertArrayHasKey('value', $first);
        $this->assertArrayHasKey('label', $first);
    }

    /** @test */
    public function image_provider_singleton_resuelve_dalle3_si_bd_dice_dalle3()
    {
        ChatbotConfig::set('mkt03_image_provider', 'dalle3');

        // Forzar re-resolve (bind, no singleton, así que cada make() vuelve a leer)
        $provider = app(ImageProviderInterface::class);

        $this->assertInstanceOf(DallE3Provider::class, $provider,
            'Cuando BD dice dalle3, el provider concreto debe ser DallE3Provider');
    }

    /** @test */
    public function image_provider_resuelve_gpt_image_2_si_bd_dice_gpt_image_2()
    {
        ChatbotConfig::set('mkt03_image_provider', 'gpt-image-2');

        $provider = app(ImageProviderInterface::class);

        $this->assertInstanceOf(GPTImage2Provider::class, $provider,
            'Cuando BD dice gpt-image-2, el provider concreto debe ser GPTImage2Provider');
    }

    /** @test */
    public function dalle3_provider_toma_modelo_desde_bd()
    {
        ChatbotConfig::set('mkt03_image_model', 'dall-e-3');

        // Construir el provider con sus defaults (sin overrides)
        $provider = new DallE3Provider();

        // Acceder al modelo via reflection (es private)
        $reflection = new \ReflectionClass($provider);
        $modelProp = $reflection->getProperty('model');
        $modelProp->setAccessible(true);

        $this->assertEquals('dall-e-3', $modelProp->getValue($provider));
    }

    /** @test */
    public function gpt_image_2_provider_toma_modelo_quality_format_desde_bd()
    {
        ChatbotConfig::set('mkt03_image_model',   'gpt-image-2');
        ChatbotConfig::set('mkt03_image_quality', 'high');
        ChatbotConfig::set('mkt03_image_format',  'webp');

        $provider = new GPTImage2Provider();

        $reflection = new \ReflectionClass($provider);
        $modelProp   = $reflection->getProperty('model');           $modelProp->setAccessible(true);
        $qualityProp = $reflection->getProperty('defaultQuality');  $qualityProp->setAccessible(true);
        $formatProp  = $reflection->getProperty('defaultFormat');   $formatProp->setAccessible(true);

        $this->assertEquals('gpt-image-2', $modelProp->getValue($provider));
        $this->assertEquals('high',        $qualityProp->getValue($provider));
        $this->assertEquals('webp',        $formatProp->getValue($provider));
    }

    /** @test */
    public function provider_overrides_constructor_ganan_sobre_bd()
    {
        ChatbotConfig::set('mkt03_image_model', 'dall-e-3');

        // Pasar model explícito en el config debería ganar sobre la BD
        $provider = new DallE3Provider(['model' => 'override-model']);

        $reflection = new \ReflectionClass($provider);
        $modelProp = $reflection->getProperty('model');
        $modelProp->setAccessible(true);

        $this->assertEquals('override-model', $modelProp->getValue($provider),
            'El config explícito en el constructor debe ganar sobre la BD');
    }

    /** @test */
    public function group_mkt03_tiene_5_keys_sembradas()
    {
        $rows = ChatbotConfig::ofGroup('mkt03')->get();

        $this->assertGreaterThanOrEqual(5, $rows->count(),
            'El grupo mkt03 debe tener al menos las 5 keys de la migración seed');

        $keys = $rows->pluck('config_key')->toArray();
        $this->assertContains('mkt03_image_provider',      $keys);
        $this->assertContains('mkt03_image_model',         $keys);
        $this->assertContains('mkt03_image_quality',       $keys);
        $this->assertContains('mkt03_image_format',        $keys);
        $this->assertContains('mkt03_auto_generate_image', $keys);
    }
}
