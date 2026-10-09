<?php

namespace Tests\Feature\Marketing;

use Hyn\Tenancy\Environment;
use Hyn\Tenancy\Models\Website;
use Mockery;
use Modules\Marketing\Http\Controllers\Api\ChatbotConnectionTestController;
use Modules\Marketing\Models\ChatbotConfig;
use Tests\TestCase;

/**
 * Mkt01ConnectionTestTest — botones "Probar conexión"
 *
 * Cubre el ChatbotConnectionTestController para WhatsApp y Email Triage.
 * Como el controller hace llamadas HTTP reales (Meta Cloud API, IMAP, SMTP),
 * los tests se enfocan en:
 *   1. Validación de pre-condiciones (credenciales mínimas presentes)
 *   2. Mensajes de error informativos cuando faltan credenciales
 *   3. Estructura del response (ok, message, checks[], details)
 *
 * NO testeamos la llamada HTTP real a Meta o IMAP — eso requeriría mocks de
 * Guzzle/socket complejos. La validación contra Meta real se hace en el
 * smoke manual desde la UI.
 */
class Mkt01ConnectionTestTest extends TestCase
{
    /** @var array  config keys creadas durante el test, para limpieza */
    private $createdConfigKeys = [];

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
        foreach ($this->createdConfigKeys as $key) {
            try { ChatbotConfig::where('config_key', $key)->delete(); } catch (\Exception $e) {}
        }
        Mockery::close();
        parent::tearDown();
    }

    /*
    |--------------------------------------------------------------------------
    | WhatsApp test-connection
    |--------------------------------------------------------------------------
    */

    /** @test */
    public function whatsapp_falla_con_mensaje_claro_si_no_hay_phone_number_id()
    {
        $this->ensureConfigKey('wa_phone_number_id', null);
        $this->ensureConfigKey('wa_access_token',    null);
        $this->ensureConfigKey('wa_verify_token',    null);
        $this->ensureConfigKey('wa_app_secret',      null);

        $controller = new ChatbotConnectionTestController();
        $response = $controller->whatsapp(request());
        $data = $response->getData(true);

        $this->assertFalse($data['ok']);
        $this->assertStringContainsString('credenciales mínimas', $data['message']);
        $this->assertNotEmpty($data['checks']);

        // Verificar que el check de Phone Number ID está marcado en false
        $check = collect($data['checks'])->firstWhere('titulo', 'Phone Number ID configurado');
        $this->assertNotNull($check);
        $this->assertFalse($check['ok']);
    }

    /** @test */
    public function whatsapp_check_lista_los_4_campos_requeridos()
    {
        $controller = new ChatbotConnectionTestController();
        $response = $controller->whatsapp(request());
        $data = $response->getData(true);

        $titulos = collect($data['checks'])->pluck('titulo')->toArray();

        $this->assertContains('Phone Number ID configurado', $titulos);
        $this->assertContains('Access Token configurado',    $titulos);
        $this->assertContains('Verify Token configurado',    $titulos);
        $this->assertContains('App Secret configurado',      $titulos);
    }

    /** @test */
    public function whatsapp_marca_credenciales_como_ok_cuando_estan_configuradas()
    {
        $this->ensureConfigKey('wa_phone_number_id', '999999999999');
        $this->ensureConfigKey('wa_access_token',    'fake_token_for_test');
        $this->ensureConfigKey('wa_verify_token',    'fake_verify');
        $this->ensureConfigKey('wa_app_secret',      'fake_secret');

        $controller = new ChatbotConnectionTestController();
        $response = $controller->whatsapp(request());
        $data = $response->getData(true);

        // Los 4 checks de credenciales deben ser true (la 5ta — conexión real a Meta — fallará
        // porque el token es fake y eso es esperado en tests; lo importante es que llegue a esa etapa)
        $checksMap = collect($data['checks'])->keyBy('titulo');
        $this->assertTrue($checksMap['Phone Number ID configurado']['ok']);
        $this->assertTrue($checksMap['Access Token configurado']['ok']);
        $this->assertTrue($checksMap['Verify Token configurado']['ok']);
        $this->assertTrue($checksMap['App Secret configurado']['ok']);

        // El response debe incluir el check de conexión a Meta (que falló por token fake)
        $this->assertStringContainsString('Conexión a Meta Cloud API', collect($data['checks'])->pluck('titulo')->implode(' '));
    }

    /*
    |--------------------------------------------------------------------------
    | Email Triage test-connection
    |--------------------------------------------------------------------------
    */

    /** @test */
    public function email_triage_falla_si_no_hay_imap_host()
    {
        $this->ensureConfigKey('email_imap_host', null);
        $this->ensureConfigKey('email_user',      null);
        $this->ensureConfigKey('email_password',  null);

        $controller = new ChatbotConnectionTestController();
        $response = $controller->emailTriage(request());
        $data = $response->getData(true);

        $this->assertFalse($data['ok']);
        $this->assertStringContainsString('credenciales mínimas', $data['message']);

        $titulos = collect($data['checks'])->pluck('titulo')->toArray();
        $this->assertContains('IMAP host configurado',     $titulos);
        $this->assertContains('Usuario configurado',       $titulos);
        $this->assertContains('Contraseña configurada',    $titulos);
    }

    /** @test */
    public function email_triage_check_estructura_response_es_consistente()
    {
        // Sin credenciales: response debe ser válido y tener estructura consistente
        $controller = new ChatbotConnectionTestController();
        $response = $controller->emailTriage(request());
        $data = $response->getData(true);

        $this->assertArrayHasKey('ok',      $data);
        $this->assertArrayHasKey('message', $data);
        $this->assertArrayHasKey('checks',  $data);
        $this->assertIsArray($data['checks']);

        foreach ($data['checks'] as $c) {
            $this->assertArrayHasKey('titulo',  $c);
            $this->assertArrayHasKey('ok',      $c);
            $this->assertArrayHasKey('detalle', $c);
        }
    }

    /** @test */
    public function whatsapp_check_estructura_response_es_consistente()
    {
        $controller = new ChatbotConnectionTestController();
        $response = $controller->whatsapp(request());
        $data = $response->getData(true);

        $this->assertArrayHasKey('ok',      $data);
        $this->assertArrayHasKey('message', $data);
        $this->assertArrayHasKey('checks',  $data);

        foreach ($data['checks'] as $c) {
            $this->assertArrayHasKey('titulo',  $c);
            $this->assertArrayHasKey('ok',      $c);
            $this->assertArrayHasKey('detalle', $c);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    /**
     * Crear o limpiar una entrada de chatbot_config para el test.
     *
     * ChatbotConfig::set() solo actualiza rows existentes. Si la key todavía
     * no fue creada por seed, hacemos updateOrCreate directo con un esqueleto
     * mínimo para que las llamadas a get() la encuentren.
     *
     * @param  string       $key
     * @param  string|null  $value  null borra el registro
     * @return void
     */
    private function ensureConfigKey($key, $value)
    {
        if ($value === null) {
            ChatbotConfig::where('config_key', $key)->delete();
        } else {
            ChatbotConfig::updateOrCreate(
                ['config_key' => $key],
                [
                    'config_value' => (string) $value,
                    'value_type'   => 'string',
                    'is_encrypted' => 0,
                    'is_sensitive' => 0,
                    'group'        => 'whatsapp',
                ]
            );
        }
        $this->createdConfigKeys[] = $key;
    }
}
