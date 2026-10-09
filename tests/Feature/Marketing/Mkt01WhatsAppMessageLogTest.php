<?php

namespace Tests\Feature\Marketing;

use Hyn\Tenancy\Environment;
use Hyn\Tenancy\Models\Website;
use Modules\Marketing\Models\WhatsAppMessageLog;
use Tests\TestCase;

/**
 * Mkt01WhatsAppMessageLogTest — Log temporal de mensajes WhatsApp.
 *
 * Esta tabla se creó para diagnosticar (via DBeaver/SQL) si los mensajes de
 * WhatsApp están llegando al webhook durante la fase de validación del canal.
 *
 * Tests:
 *   1. Tabla existe
 *   2. logInbound() persiste con direction=inbound + timestamp
 *   3. logOutbound() persiste con direction=outbound + meta deserializado
 *   4. Scopes bySession, byWaId, chronological funcionan
 *   5. Modelo NO usa updated_at (logs inmutables)
 */
class Mkt01WhatsAppMessageLogTest extends TestCase
{
    /** @var int[] */
    private $createdIds = [];

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
        try {
            if (!empty($this->createdIds)) {
                WhatsAppMessageLog::whereIn('id', $this->createdIds)->delete();
            }
        } catch (\Exception $e) {}
        parent::tearDown();
    }

    /** @test */
    public function la_tabla_existe_y_tiene_las_columnas_esperadas()
    {
        $exists = \Illuminate\Support\Facades\Schema::connection('tenant')
            ->hasTable('chatbot_whatsapp_message_log');
        $this->assertTrue($exists, 'La tabla chatbot_whatsapp_message_log debería existir');

        foreach (['session_id', 'wa_id', 'direction', 'message_text', 'ai_intent', 'meta', 'created_at'] as $col) {
            $this->assertTrue(
                \Illuminate\Support\Facades\Schema::connection('tenant')
                    ->hasColumn('chatbot_whatsapp_message_log', $col),
                "Falta la columna {$col}"
            );
        }
    }

    /** @test */
    public function log_inbound_persiste_correctamente()
    {
        $log = WhatsAppMessageLog::logInbound([
            'session_id'      => 'wa_51999111111',
            'wa_id'           => '51999111111',
            'contact_name'    => 'Test User',
            'message_text'    => 'Hola, quisiera cotizar',
            'meta_message_id' => 'wamid.HBgLNTE5OTkxMTExMTEVAgARGBI=',
        ]);
        $this->createdIds[] = $log->id;

        $fresh = WhatsAppMessageLog::find($log->id);

        $this->assertEquals('inbound', $fresh->direction);
        $this->assertEquals('51999111111', $fresh->wa_id);
        $this->assertEquals('Hola, quisiera cotizar', $fresh->message_text);
        $this->assertNotNull($fresh->created_at);
        $this->assertNull($fresh->ai_intent, 'Los inbounds no tienen intent (lo asigna el bot, no el usuario)');
    }

    /** @test */
    public function log_outbound_persiste_con_meta_como_array_deserializado()
    {
        $log = WhatsAppMessageLog::logOutbound([
            'session_id'   => 'wa_51999222222',
            'wa_id'        => '51999222222',
            'contact_name' => 'Otro User',
            'message_text' => '¡Hola! Soy el bot. ¿En qué puedo ayudarte?',
            'ai_intent'    => 'saludo',
            'ai_provider'  => 'claude',
            'ai_model'     => 'claude-sonnet-4-6',
            'meta'         => [
                'confidence' => 0.95,
                'entities_extracted' => ['nombre_contacto' => null],
                'actions' => ['should_register_lead' => false],
            ],
        ]);
        $this->createdIds[] = $log->id;

        $fresh = WhatsAppMessageLog::find($log->id);

        $this->assertEquals('outbound', $fresh->direction);
        $this->assertEquals('saludo', $fresh->ai_intent);
        $this->assertEquals('claude', $fresh->ai_provider);
        $this->assertIsArray($fresh->meta);
        $this->assertEquals(0.95, $fresh->meta['confidence']);
        $this->assertEquals(false, $fresh->meta['actions']['should_register_lead']);
    }

    /** @test */
    public function scope_by_session_filtra_correctamente()
    {
        $sessionA = 'wa_test_a_' . uniqid();
        $sessionB = 'wa_test_b_' . uniqid();

        $a1 = WhatsAppMessageLog::logInbound(['session_id' => $sessionA, 'wa_id' => 'A', 'message_text' => 'hola A']);
        $a2 = WhatsAppMessageLog::logOutbound(['session_id' => $sessionA, 'wa_id' => 'A', 'message_text' => 'resp A']);
        $b1 = WhatsAppMessageLog::logInbound(['session_id' => $sessionB, 'wa_id' => 'B', 'message_text' => 'hola B']);
        $this->createdIds = array_merge($this->createdIds, [$a1->id, $a2->id, $b1->id]);

        $logsA = WhatsAppMessageLog::bySession($sessionA)->chronological()->get();
        $this->assertCount(2, $logsA);
        $this->assertEquals('hola A', $logsA->first()->message_text);
        $this->assertEquals('resp A', $logsA->last()->message_text);
    }

    /** @test */
    public function el_modelo_no_usa_updated_at()
    {
        $log = WhatsAppMessageLog::logInbound([
            'session_id'   => 'wa_test_immutable',
            'wa_id'        => '999',
            'message_text' => 'inmutable',
        ]);
        $this->createdIds[] = $log->id;

        $this->assertFalse($log->timestamps,
            'El modelo debe tener $timestamps=false porque los logs son inmutables');

        // Verificar que la tabla NO tiene updated_at
        $this->assertFalse(
            \Illuminate\Support\Facades\Schema::connection('tenant')
                ->hasColumn('chatbot_whatsapp_message_log', 'updated_at')
        );
    }
}
