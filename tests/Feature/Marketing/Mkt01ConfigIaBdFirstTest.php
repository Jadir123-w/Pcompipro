<?php

namespace Tests\Feature\Marketing;

use Hyn\Tenancy\Environment;
use Hyn\Tenancy\Models\Website;
use Modules\Marketing\Contracts\AIProviderInterface;
use Modules\Marketing\Models\ChatbotConfig;
use Modules\Marketing\Services\AI\ClaudeProvider;
use Modules\Marketing\Services\AI\GeminiProvider;
use Modules\Marketing\Services\AI\OpenAIProvider;
use Tests\TestCase;

/**
 * Mkt01ConfigIaBdFirstTest — refactor "configs operativas a BD" (IA / modelos).
 *
 * Análogo a Mkt03ConfigBdFirstTest pero para los settings de TEXTO global:
 *
 *   - chatbot_ai_provider  (provider de IA por defecto: claude/openai/gemini)
 *   - chatbot_claude_model (modelo Claude)
 *   - chatbot_openai_model (modelo OpenAI)
 *   - chatbot_gemini_model (modelo Gemini)
 *   - chatbot_gemini_max_tokens (int, output limit)
 *
 * Cubre:
 *   1. Las 5 keys del grupo `ia` están sembradas tras la migración
 *   2. AIProviderInterface se resuelve con el valor de chatbot_ai_provider
 *   3. ClaudeProvider toma su modelo desde BD
 *   4. OpenAIProvider toma su modelo desde BD
 *   5. GeminiProvider toma modelo + max_tokens desde BD
 */
class Mkt01ConfigIaBdFirstTest extends TestCase
{
    private $originalValues = [];

    private $keys = [
        'chatbot_ai_provider',
        'chatbot_claude_model',
        'chatbot_openai_model',
        'chatbot_gemini_model',
        'chatbot_gemini_max_tokens',
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

        // API keys obligatorias para que los providers no lancen
        config(['marketing.chatbot.ai.claude.api_key' => 'fake-claude-key-for-test']);
        config(['marketing.chatbot.ai.openai.api_key' => 'fake-openai-key-for-test']);
        config(['marketing.chatbot.ai.gemini.api_key' => 'fake-gemini-key-for-test']);

        // Backup
        foreach ($this->keys as $key) {
            $row = ChatbotConfig::byKey($key);
            $this->originalValues[$key] = $row ? $row->config_value : null;
        }
    }

    protected function tearDown(): void
    {
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
    public function group_ia_tiene_5_keys_sembradas()
    {
        $rows = ChatbotConfig::ofGroup('ia')->get();
        $this->assertGreaterThanOrEqual(5, $rows->count());

        $keys = $rows->pluck('config_key')->toArray();
        $this->assertContains('chatbot_ai_provider',       $keys);
        $this->assertContains('chatbot_claude_model',      $keys);
        $this->assertContains('chatbot_openai_model',      $keys);
        $this->assertContains('chatbot_gemini_model',      $keys);
        $this->assertContains('chatbot_gemini_max_tokens', $keys);
    }

    /** @test */
    public function ai_provider_se_resuelve_segun_bd_dice_claude()
    {
        ChatbotConfig::set('chatbot_ai_provider', 'claude');
        $provider = app(AIProviderInterface::class);
        $this->assertInstanceOf(ClaudeProvider::class, $provider);
    }

    /** @test */
    public function ai_provider_se_resuelve_segun_bd_dice_openai()
    {
        ChatbotConfig::set('chatbot_ai_provider', 'openai');
        $provider = app(AIProviderInterface::class);
        $this->assertInstanceOf(OpenAIProvider::class, $provider);
    }

    /** @test */
    public function ai_provider_se_resuelve_segun_bd_dice_gemini()
    {
        ChatbotConfig::set('chatbot_ai_provider', 'gemini');
        $provider = app(AIProviderInterface::class);
        $this->assertInstanceOf(GeminiProvider::class, $provider);
    }

    /** @test */
    public function claude_provider_toma_modelo_desde_bd()
    {
        ChatbotConfig::set('chatbot_claude_model', 'claude-test-model-123');
        $provider = new ClaudeProvider();

        $reflection = new \ReflectionClass($provider);
        $modelProp = $reflection->getProperty('model');
        $modelProp->setAccessible(true);

        $this->assertEquals('claude-test-model-123', $modelProp->getValue($provider));
    }

    /** @test */
    public function openai_provider_toma_modelo_desde_bd()
    {
        ChatbotConfig::set('chatbot_openai_model', 'gpt-test-model-xyz');
        $provider = new OpenAIProvider();

        $reflection = new \ReflectionClass($provider);
        $modelProp = $reflection->getProperty('model');
        $modelProp->setAccessible(true);

        $this->assertEquals('gpt-test-model-xyz', $modelProp->getValue($provider));
    }

    /** @test */
    public function gemini_provider_toma_modelo_y_max_tokens_desde_bd()
    {
        ChatbotConfig::set('chatbot_gemini_model',      'gemini-test-3');
        ChatbotConfig::set('chatbot_gemini_max_tokens', '8000');

        $provider = new GeminiProvider();

        $reflection  = new \ReflectionClass($provider);
        $modelProp   = $reflection->getProperty('model');     $modelProp->setAccessible(true);
        $tokensProp  = $reflection->getProperty('maxTokens'); $tokensProp->setAccessible(true);

        $this->assertEquals('gemini-test-3', $modelProp->getValue($provider));
        $this->assertEquals(8000,            $tokensProp->getValue($provider));
    }

    /** @test */
    public function override_constructor_gana_sobre_bd()
    {
        ChatbotConfig::set('chatbot_claude_model', 'from-db');
        $provider = new ClaudeProvider(['model' => 'override']);

        $reflection = new \ReflectionClass($provider);
        $modelProp = $reflection->getProperty('model');
        $modelProp->setAccessible(true);

        $this->assertEquals('override', $modelProp->getValue($provider));
    }
}
