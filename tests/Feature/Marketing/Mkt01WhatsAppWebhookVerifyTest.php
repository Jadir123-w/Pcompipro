<?php

namespace Tests\Feature\Marketing;

use Hyn\Tenancy\Environment;
use Hyn\Tenancy\Models\Website;
use Modules\Marketing\Models\ChatbotConfig;
use Tests\TestCase;

/**
 * Mkt01WhatsAppWebhookVerifyTest — Verificación del webhook de WhatsApp.
 *
 * Bug que estos tests previenen
 * =============================
 * El controller leía verify_token con `config('marketing.chatbot.whatsapp.
 * verify_token')` (archivo Laravel / .env), pero el sistema migró a BD
 * (marketing_chatbot_config.wa_verify_token). Resultado: cuando el cliente
 * configuraba el token en la UI del ERP, el webhook seguía devolviendo 403
 * Forbidden, y Meta mostraba "No se ha podido validar la URL de devolución
 * de llamada ni el identificador de verificación".
 *
 * Casos cubiertos:
 *   1. Token correcto + mode=subscribe → 200 con challenge en plain text
 *   2. Token incorrecto → 403
 *   3. Mode distinto a subscribe → 403
 *   4. Sin verify_token configurado → 403 con mensaje específico
 *   5. Acepta query params con underscore (hub_mode) — formato típico PHP
 *   6. Acepta query params con dot (hub.mode) — formato literal de Meta
 *   7. La respuesta exitosa devuelve text/plain (no JSON)
 */
class Mkt01WhatsAppWebhookVerifyTest extends TestCase
{
    /** @var string Valor original del verify_token, para restaurar al final */
    private $originalVerifyToken;

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

        // Snapshot del valor actual para restaurar en tearDown
        $row = ChatbotConfig::byKey('wa_verify_token');
        $this->originalVerifyToken = $row ? $row->config_value : null;

        // Setear un token conocido para los tests
        ChatbotConfig::set('wa_verify_token', 'test-verify-token-12345');
    }

    protected function tearDown(): void
    {
        try {
            $row = ChatbotConfig::byKey('wa_verify_token');
            if ($row) {
                $row->config_value = $this->originalVerifyToken;
                $row->save();
            }
        } catch (\Exception $e) {}
        parent::tearDown();
    }

    /** @test */
    public function verifica_correctamente_con_token_y_mode_validos()
    {
        $response = $this->get('/api/marketing/chatbot/whatsapp/webhook?hub_mode=subscribe&hub_verify_token=test-verify-token-12345&hub_challenge=CHALLENGE_RANDOM_XYZ');

        $response->assertStatus(200);
        $this->assertEquals('CHALLENGE_RANDOM_XYZ', $response->getContent());
        $this->assertStringContainsString('text/plain', $response->headers->get('Content-Type'));
    }

    /** @test */
    public function rechaza_con_token_incorrecto()
    {
        $response = $this->get('/api/marketing/chatbot/whatsapp/webhook?hub_mode=subscribe&hub_verify_token=TOKEN_EQUIVOCADO&hub_challenge=ABC');

        $response->assertStatus(403);
    }

    /** @test */
    public function rechaza_con_mode_distinto_a_subscribe()
    {
        $response = $this->get('/api/marketing/chatbot/whatsapp/webhook?hub_mode=other&hub_verify_token=test-verify-token-12345&hub_challenge=ABC');

        $response->assertStatus(403);
    }

    /** @test */
    public function rechaza_con_mensaje_especifico_si_verify_token_no_esta_configurado()
    {
        // Vaciar el token
        ChatbotConfig::set('wa_verify_token', null);

        $response = $this->get('/api/marketing/chatbot/whatsapp/webhook?hub_mode=subscribe&hub_verify_token=cualquiercosa&hub_challenge=ABC');

        $response->assertStatus(403);
        $this->assertStringContainsString('verify_token no configurado', $response->getContent());
    }

    /** @test */
    public function acepta_query_params_con_dot_estilo_meta_literal()
    {
        // Meta envía los params como hub.mode, hub.verify_token, hub.challenge.
        // PHP normalmente convierte los dots a underscores, pero si por algún
        // proxy intermedio llegan literales, también deberían funcionar.
        $url = '/api/marketing/chatbot/whatsapp/webhook?' . http_build_query([
            'hub.mode'         => 'subscribe',
            'hub.verify_token' => 'test-verify-token-12345',
            'hub.challenge'    => 'DOT_CHALLENGE',
        ]);
        $response = $this->get($url);

        // PHP convierte dots a underscores en $_GET, así que esto va a llegar
        // como hub_mode/hub_verify_token. Nuestro controller acepta ambos
        // (por defensa), así que debería responder 200 igual.
        $response->assertStatus(200);
        $this->assertEquals('DOT_CHALLENGE', $response->getContent());
    }

    /** @test */
    public function el_challenge_se_devuelve_exacto_como_lo_envio_meta()
    {
        // Meta puede enviar challenges con caracteres especiales (UUIDs,
        // números largos). El controller no debe transformar ni hashear nada.
        $challenge = '1234567890-abcdef-XYZ_test';
        $response = $this->get('/api/marketing/chatbot/whatsapp/webhook?hub_mode=subscribe&hub_verify_token=test-verify-token-12345&hub_challenge=' . urlencode($challenge));

        $response->assertStatus(200);
        $this->assertEquals($challenge, $response->getContent());
    }

    /** @test */
    public function lee_token_de_bd_no_de_env_para_compatibilidad_con_ui_config()
    {
        // Si el cliente cambia el token desde la UI del ERP, ese cambio se
        // guarda en BD (marketing_chatbot_config.wa_verify_token). El webhook
        // debe leerlo de BD, NO del .env. Este test es el guard de regresión
        // del bug original que afectó a Dante.
        ChatbotConfig::set('wa_verify_token', 'token-cambiado-desde-ui-del-erp');

        // Aunque el .env tuviera otro valor, este test usa el token de BD
        $response = $this->get('/api/marketing/chatbot/whatsapp/webhook?hub_mode=subscribe&hub_verify_token=token-cambiado-desde-ui-del-erp&hub_challenge=OK123');

        $response->assertStatus(200);
        $this->assertEquals('OK123', $response->getContent());
    }
}
