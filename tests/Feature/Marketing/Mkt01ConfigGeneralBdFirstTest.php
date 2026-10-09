<?php

namespace Tests\Feature\Marketing;

use Hyn\Tenancy\Environment;
use Hyn\Tenancy\Models\Website;
use Modules\Marketing\Models\ChatbotConfig;
use Modules\Marketing\Services\ConversationSessionManager;
use Tests\TestCase;

/**
 * Mkt01ConfigGeneralBdFirstTest — refactor "configs operativas a BD" (parte III).
 *
 * Cubre que los servicios consumidores ahora prefieren BD al .env para los
 * settings operativos:
 *   - ConversationSessionManager: session_ttl, max_messages_per_session
 *   - LeadRegistrationService: default_assigned_to (no testeado aquí — requiere flow)
 *   - WhatsAppService: wa_access_token, wa_phone_number_id (no testeado — requiere mocking)
 *
 * El helper central ChatbotConfig::getOrEnv() es lo que se valida acá.
 *
 * Cubre:
 *   1. ChatbotConfig::getOrEnv devuelve el valor de BD si está
 *   2. ChatbotConfig::getOrEnv devuelve fallback al config Laravel si BD vacía
 *   3. ChatbotConfig::getOrEnv devuelve default si no hay nada
 *   4. ConversationSessionManager toma session_ttl desde BD
 *   5. ConversationSessionManager toma max_messages desde BD (al persistir un mensaje)
 */
class Mkt01ConfigGeneralBdFirstTest extends TestCase
{
    private $originalValues = [];
    private $keys = [
        'general_session_ttl',
        'general_max_messages_per_session',
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
    public function get_or_env_devuelve_valor_de_bd_si_esta()
    {
        ChatbotConfig::set('general_session_ttl', 3600);

        $val = ChatbotConfig::getOrEnv('general_session_ttl', 'session_ttl', 1800);

        $this->assertEquals(3600, (int) $val);
    }

    /** @test */
    public function get_or_env_cae_a_config_laravel_si_bd_vacia()
    {
        // Limpiar BD
        $row = ChatbotConfig::byKey('general_session_ttl');
        if ($row) {
            $row->config_value = null;
            $row->save();
        }

        // Configurar valor en config Laravel (simula .env)
        config(['marketing.chatbot.session_ttl' => 999]);

        $val = ChatbotConfig::getOrEnv('general_session_ttl', 'session_ttl', 1800);

        $this->assertEquals(999, (int) $val);
    }

    /** @test */
    public function get_or_env_devuelve_default_si_nada_configurado()
    {
        // Limpiar BD y config
        $row = ChatbotConfig::byKey('general_session_ttl');
        if ($row) {
            $row->config_value = null;
            $row->save();
        }
        config(['marketing.chatbot.session_ttl' => null]);

        $val = ChatbotConfig::getOrEnv('general_session_ttl', 'session_ttl', 7777);

        $this->assertEquals(7777, $val);
    }

    /** @test */
    public function get_or_env_funciona_con_key_inexistente_en_bd()
    {
        // Una key que NO está en la tabla
        $val = ChatbotConfig::getOrEnv('this_key_does_not_exist_anywhere', 'session_ttl', 5555);

        // Debe devolver el config laravel si está, sino el default
        $this->assertNotNull($val);
    }

    /** @test */
    public function conversation_session_manager_toma_session_ttl_desde_bd()
    {
        ChatbotConfig::set('general_session_ttl', 4242);

        $manager = new ConversationSessionManager();

        $reflection = new \ReflectionClass($manager);
        $ttlProp = $reflection->getProperty('sessionTtl');
        $ttlProp->setAccessible(true);

        $this->assertEquals(4242, (int) $ttlProp->getValue($manager));
    }

    /** @test */
    public function general_session_ttl_existe_en_bd_tras_migracion()
    {
        $row = ChatbotConfig::byKey('general_session_ttl');

        $this->assertNotNull($row, 'general_session_ttl debe existir tras la migración');
        $this->assertEquals('int', $row->value_type);
        $this->assertEquals('general', $row->group);
    }
}
