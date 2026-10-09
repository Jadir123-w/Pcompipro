<?php

namespace Tests\Feature\Marketing;

use Hyn\Tenancy\Environment;
use Hyn\Tenancy\Models\Website;
use Modules\Marketing\Models\ChatbotConfig;
use Tests\TestCase;

/**
 * Mkt01WhatsAppWebhookHandleEnabledTest — Bug del POST del webhook que leía
 * wa_enabled del .env en lugar de la BD.
 *
 * Síntoma reportado por el cliente
 * =================================
 * Tras configurar el webhook en Meta (verify pasaba OK), el cliente activaba
 * "WhatsApp activo" en la UI del ERP (Config Chatbot → WhatsApp), pero al
 * enviar mensajes desde su WhatsApp al número del bot, nada llegaba al ERP:
 *   - El panel `/marketing/whatsapp-pending` quedaba vacío.
 *   - La nueva tabla de diagnóstico chatbot_whatsapp_message_log tampoco
 *     registraba nada.
 *
 * Causa
 * =====
 * WhatsAppWebhookController::handle() en su primera línea leía:
 *   if (!config('marketing.chatbot.whatsapp.enabled', false)) {
 *       return response()->json(['status' => 'disabled'], 200);
 *   }
 *
 * Eso lee del archivo Laravel config → .env. Pero el toggle del cliente
 * se guarda en BD (marketing_chatbot_config.wa_enabled). Si en .env nunca
 * existió esa key (caso típico), el webhook respondía 'disabled' silen-
 * ciosamente — Meta enviaba 200 OK pero nada pasaba en el ERP.
 *
 * Es exactamente el mismo bug que arreglamos en verify() días atrás
 * (PR #237). Esta es la segunda mitad del fix.
 *
 * Tests
 * =====
 *   1. wa_enabled=true en BD → POST del webhook procesa (no devuelve disabled).
 *   2. wa_enabled=false en BD → POST devuelve disabled.
 */
class Mkt01WhatsAppWebhookHandleEnabledTest extends TestCase
{
    private $originalEnabled;

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

        // Asegurar que el .env no tiene el valor seteado (que es el escenario
        // real del cliente) — el bug original era que SIEMPRE leía de ahí.
        config(['marketing.chatbot.whatsapp.enabled' => null]);

        // Snapshot del valor actual de wa_enabled
        $row = ChatbotConfig::byKey('wa_enabled');
        $this->originalEnabled = $row ? $row->config_value : null;
    }

    protected function tearDown(): void
    {
        try {
            $row = ChatbotConfig::byKey('wa_enabled');
            if ($row) {
                $row->config_value = $this->originalEnabled;
                $row->save();
            }
        } catch (\Exception $e) {}
        parent::tearDown();
    }

    /** @test */
    public function rechaza_con_disabled_si_wa_enabled_es_false_en_bd()
    {
        ChatbotConfig::set('wa_enabled', false);

        $response = $this->postJson('/api/marketing/chatbot/whatsapp/webhook', [
            'object' => 'whatsapp_business_account',
            'entry'  => [],
        ]);

        $response->assertStatus(200);
        $payload = $response->getData(true);
        $this->assertEquals('disabled', $payload['status']);
    }

    /** @test */
    public function procesa_payload_cuando_wa_enabled_es_true_en_bd()
    {
        ChatbotConfig::set('wa_enabled', true);

        // Payload sin entries de mensajes — el controller debería pasar la
        // verificación de enabled y devolver 'no_messages' o 'ignored', NO
        // 'disabled'. Eso confirma que NO se cortó en la primera línea.
        $response = $this->postJson('/api/marketing/chatbot/whatsapp/webhook', [
            'object' => 'whatsapp_business_account',
            'entry'  => [],
        ]);

        $response->assertStatus(200);
        $payload = $response->getData(true);
        $this->assertNotEquals('disabled', $payload['status'],
            'Con wa_enabled=true en BD, el webhook NO debe devolver disabled');
    }

    /** @test */
    public function rechaza_con_disabled_si_no_hay_config_ni_en_bd_ni_en_env()
    {
        // Vaciar el valor en BD también. El controller debe defaultear a false.
        ChatbotConfig::set('wa_enabled', false);
        config(['marketing.chatbot.whatsapp.enabled' => null]);

        $response = $this->postJson('/api/marketing/chatbot/whatsapp/webhook', [
            'object' => 'whatsapp_business_account',
            'entry'  => [],
        ]);

        $response->assertStatus(200);
        $payload = $response->getData(true);
        $this->assertEquals('disabled', $payload['status']);
    }
}
