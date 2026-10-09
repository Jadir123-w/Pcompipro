<?php

namespace Tests\Feature\Marketing;

use Hyn\Tenancy\Environment;
use Hyn\Tenancy\Models\Website;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Modules\Marketing\Http\Controllers\Api\WhatsAppWebhookController;
use Modules\Marketing\Models\ChatbotAttachment;
use Modules\Marketing\Models\ChatbotPendingMessage;
use Modules\Marketing\Models\ChatbotSession;
use Modules\Marketing\Models\MarketingChatbotLead;
use Modules\Marketing\Services\AIConversationEngine;
use Modules\Marketing\Services\ChatbotOrchestrator;
use Modules\Marketing\Services\ConversationSessionManager;
use Modules\Marketing\Services\WhatsAppApprovalService;
use Modules\Marketing\Services\WhatsAppService;
use Tests\TestCase;

/**
 * Mkt01WhatsAppApprovalTest
 *
 * Tests E2E del flujo de aprobación humana de WhatsApp (Nivel 1) del MKT-01.
 *
 * Cubre:
 *   1. Encolar respuesta del bot al recibir mensaje (no se envía directo)
 *   2. Aprobar tal cual → dispara envío via WhatsAppService mockeado
 *   3. Editar antes de aprobar → texto enviado es el editado, no el del LLM
 *   4. Rechazar → no se envía nada
 *   5. Reintentar tras fallo → estado vuelve a sending
 *   6. Anti-saturación: si bot envió >=N seguidos, no encola más
 *   7. Lock optimista: dos approves simultáneos → solo uno gana
 *   8. Estado terminal: aprobar un ya-enviado falla
 *
 * Estrategia: mockeamos AIConversationEngine y WhatsAppService.
 * Bootstrappeamos tenant Hyn en setUp + cleanup explícito.
 */
class Mkt01WhatsAppApprovalTest extends TestCase
{
    /** @var ChatbotOrchestrator */
    private $orchestrator;

    /** @var \Mockery\MockInterface */
    private $aiMock;

    /** @var \Mockery\MockInterface */
    private $whatsAppMock;

    /** @var WhatsAppApprovalService */
    private $approvalService;

    /** @var string */
    private $sessionId;

    /** @var string */
    private $waId;

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

        // ── Mock AI ──
        $this->aiMock = \Mockery::mock(AIConversationEngine::class);
        $this->app->instance(AIConversationEngine::class, $this->aiMock);

        // ── Mock WhatsApp service ──
        $this->whatsAppMock = \Mockery::mock(WhatsAppService::class);
        $this->app->instance(WhatsAppService::class, $this->whatsAppMock);

        $this->orchestrator    = app(ChatbotOrchestrator::class);
        $this->approvalService = app(WhatsAppApprovalService::class);

        $this->waId      = '5198877' . substr((string) microtime(true) * 10000, -5);
        $this->sessionId = 'wa_' . $this->waId;

        $this->cleanup();
    }

    protected function tearDown(): void
    {
        $this->cleanup();
        \Mockery::close();
        parent::tearDown();
    }

    /*
    |--------------------------------------------------------------------------
    | Tests
    |--------------------------------------------------------------------------
    */

    /**
     * Caso 1 — Al simular mensaje entrante via Orchestrator+Approval (Nivel 1
     * activo), la respuesta del LLM se ENCOLA en chatbot_pending_messages, no
     * se envía a WhatsAppService.
     */
    public function test_caso1_respuesta_se_encola_en_nivel_1()
    {
        $this->ensureNivel1();

        // Mock: el LLM responde algo
        $this->aiMock->shouldReceive('processMessage')->once()->andReturn(
            $this->llmResponse('Hola, ¿qué buscas?', 'saludo')
        );

        // El WhatsAppService NO debe ser llamado durante el handle del mensaje
        $this->whatsAppMock->shouldReceive('sendTextMessage')->never();

        // Simulamos lo que hace el webhook: llama al orchestrator y luego encola
        $aiResponse = $this->orchestrator->handleMessage($this->sessionId, 'Hola', 'whatsapp', [
            'channel_identifier' => $this->waId,
        ]);

        $pending = $this->approvalService->enqueue([
            'session_id'   => $this->sessionId,
            'wa_id'        => $this->waId,
            'contact_name' => 'Ana',
            'user_message' => 'Hola',
            'ai_response'  => $aiResponse,
        ]);

        $this->assertNotNull($pending);
        $this->assertEquals('pending', $pending->status);
        $this->assertEquals($this->waId, $pending->wa_id);
        $this->assertEquals('Hola, ¿qué buscas?', $pending->ai_reply);
        $this->assertEquals('saludo', $pending->ai_intent);
    }

    /**
     * Caso 2 — Aprobar tal cual: dispara WhatsAppService.sendTextMessage con
     * el texto del LLM, queda en sent.
     */
    public function test_caso2_aprobar_dispara_envio()
    {
        $this->ensureNivel1();

        $pending = $this->createPending('Mensaje sugerido por el bot');

        // Esperar exactamente 1 envío exitoso
        $this->whatsAppMock->shouldReceive('sendTextMessage')
            ->once()
            ->with($this->waId, 'Mensaje sugerido por el bot')
            ->andReturn(['success' => true, 'message_id' => 'wamid.123', 'error' => null]);

        $result = $this->approvalService->approve($pending->id, 99);

        $this->assertTrue($result['ok']);
        $fresh = $pending->fresh();
        $this->assertEquals('sent', $fresh->status);
        $this->assertEquals(99, $fresh->approved_by_user_id);
        $this->assertEquals('wamid.123', $fresh->sent_message_id);
        $this->assertNotNull($fresh->approved_at);
        $this->assertNotNull($fresh->sent_at);
    }

    /**
     * Caso 3 — Editar y aprobar: el texto enviado es el editado, no el del LLM.
     */
    public function test_caso3_editar_envia_texto_editado()
    {
        $this->ensureNivel1();

        $pending = $this->createPending('Texto original del LLM');
        $textoEditado = 'Texto editado por la ejecutiva';

        $this->whatsAppMock->shouldReceive('sendTextMessage')
            ->once()
            ->with($this->waId, $textoEditado)
            ->andReturn(['success' => true, 'message_id' => 'wamid.456', 'error' => null]);

        $result = $this->approvalService->approveWithEdit($pending->id, 7, $textoEditado);

        $this->assertTrue($result['ok']);
        $fresh = $pending->fresh();
        $this->assertEquals('sent', $fresh->status);
        $this->assertEquals('Texto original del LLM', $fresh->ai_reply);
        $this->assertEquals($textoEditado, $fresh->final_reply);
        $this->assertEquals($textoEditado, $fresh->effectiveText());
    }

    /**
     * Caso 4 — Rechazar: NO se llama a WhatsAppService, queda en rejected.
     */
    public function test_caso4_rechazar_no_envia()
    {
        $this->ensureNivel1();

        $pending = $this->createPending('Cualquier texto');

        $this->whatsAppMock->shouldReceive('sendTextMessage')->never();

        $result = $this->approvalService->reject($pending->id, 7, 'Off-topic');

        $this->assertTrue($result['ok']);
        $fresh = $pending->fresh();
        $this->assertEquals('rejected', $fresh->status);
        $this->assertEquals('Off-topic', $fresh->rejection_reason);
        $this->assertEquals(7, $fresh->approved_by_user_id);
    }

    /**
     * Caso 5 — Si el envío falla, marca failed con error_message. El retry
     * vuelve a aprobado y reintenta.
     */
    public function test_caso5_fallo_envio_marca_failed_y_retry_funciona()
    {
        $this->ensureNivel1();

        $pending = $this->createPending('Mensaje a enviar');

        // Primer intento: falla
        $this->whatsAppMock->shouldReceive('sendTextMessage')
            ->once()
            ->andReturn(['success' => false, 'message_id' => null, 'error' => 'Timeout']);

        $result = $this->approvalService->approve($pending->id, 99);
        $this->assertFalse($result['ok']);
        $this->assertEquals('failed', $pending->fresh()->status);
        $this->assertEquals('Timeout', $pending->fresh()->error_message);

        // Retry: éxito
        $this->whatsAppMock->shouldReceive('sendTextMessage')
            ->once()
            ->andReturn(['success' => true, 'message_id' => 'wamid.789', 'error' => null]);

        $retry = $this->approvalService->retrySend($pending->id);
        $this->assertTrue($retry['ok']);
        $this->assertEquals('sent', $pending->fresh()->status);
    }

    /**
     * Caso 6 — Estado terminal: una vez sent, no se puede aprobar de nuevo.
     */
    public function test_caso6_estado_terminal_no_reaprobable()
    {
        $this->ensureNivel1();

        $pending = $this->createPending('Mensaje');
        $pending->markSent('wamid.000');

        $this->whatsAppMock->shouldReceive('sendTextMessage')->never();

        $result = $this->approvalService->approve($pending->id, 99);
        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('procesado', $result['message']);
    }

    /**
     * Caso 7 — Lock optimista: dos approves simultáneos. El primero llega a
     * sent; el segundo (que ve el estado ya en sending o sent) falla limpiamente.
     */
    public function test_caso7_lock_optimista_evita_doble_envio()
    {
        $this->ensureNivel1();

        $pending = $this->createPending('Mensaje');
        // Primero forzamos approve manual para tener estado approved
        $pending->markApproved(99);

        // Mock: el envío real ocurre 1 sola vez
        $this->whatsAppMock->shouldReceive('sendTextMessage')
            ->once()
            ->andReturn(['success' => true, 'message_id' => 'wamid.lock', 'error' => null]);

        // Disparamos dos veces el send: el segundo debe fallar (status ya sent)
        $r1 = $this->approvalService->dispatchSend($pending->fresh());
        $r2 = $this->approvalService->dispatchSend($pending->fresh());

        $this->assertTrue($r1['ok']);
        $this->assertFalse($r2['ok']);
        $this->assertEquals('sent', $pending->fresh()->status);
    }

    /**
     * Caso 9 — Recepción de archivo: cuando llega un mensaje type=image/document,
     * el WhatsAppService.downloadMedia se llama, se persiste en chatbot_attachments
     * con session_id correcto y el attachment_ids se pasa al orchestrator.
     */
    public function test_caso9_recepcion_archivo_persiste_attachment()
    {
        $this->ensureNivel1();

        // Mock downloadMedia: devuelve bytes válidos de PNG (1x1 transparente)
        $pngBytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=');
        $this->whatsAppMock->shouldReceive('downloadMedia')
            ->once()
            ->with('media-id-fake')
            ->andReturn([
                'success'    => true,
                'mime_type'  => 'image/png',
                'sha256'     => 'fakehash',
                'size_bytes' => strlen($pngBytes),
                'bytes'      => $pngBytes,
                'error'      => null,
            ]);

        // Mock de lo demás (orchestrator devuelve respuesta mínima vía AI mock)
        $this->aiMock->shouldReceive('processMessage')->andReturn(
            $this->llmResponse('Recibí tu logo, gracias.', 'solicitar_muestra_virtual')
        );

        // Simulamos el flujo del webhook llamando processIncomingMessage
        // a través de reflection (es private) o via el método público handle.
        // Más simple: instanciamos el controller y llamamos via reflection.
        $controller = app(WhatsAppWebhookController::class);
        $reflection = new \ReflectionMethod($controller, 'processIncomingMessage');
        $reflection->setAccessible(true);

        $reflection->invoke($controller, [
            'wa_id'      => $this->waId,
            'text'       => 'Te paso mi logo',
            'name'       => 'Diego',
            'message_id' => 'wamid.fake',
            'media'      => [
                'kind'      => 'image',
                'media_id'  => 'media-id-fake',
                'mime_type' => 'image/png',
                'filename'  => 'logo.png',
            ],
        ]);

        // Verificar que el attachment quedó persistido
        $att = ChatbotAttachment::where('session_id', $this->sessionId)->first();
        $this->assertNotNull($att, 'Se debió persistir un attachment');
        $this->assertEquals('image/png', $att->mime_type);
        $this->assertEquals('logo.png', $att->original_name);
        $this->assertEquals('reference', $att->kind);
        $this->assertTrue($att->size_bytes > 0);

        // Cleanup file físico
        Storage::disk('local')->delete($att->stored_path);
    }

    /**
     * Caso 10 — Si downloadMedia falla, NO se persiste attachment ni se rompe
     * el flujo (el orchestrator igual procesa el texto del usuario).
     */
    public function test_caso10_recepcion_archivo_falla_no_rompe_flujo()
    {
        $this->ensureNivel1();

        // Pre-crear la sesión para que NO se dispare flujo de primer mensaje
        // (que encolaría también el saludo y se confunden los conteos)
        ChatbotSession::create([
            'session_id'       => $this->sessionId,
            'channel'          => 'whatsapp',
            'status'           => 'active',
            'started_at'       => now(),
            'last_activity_at' => now(),
        ]);

        $this->whatsAppMock->shouldReceive('downloadMedia')
            ->once()
            ->andReturn([
                'success'    => false,
                'mime_type'  => null,
                'sha256'     => null,
                'size_bytes' => null,
                'bytes'      => null,
                'error'      => 'Network timeout',
            ]);

        $this->aiMock->shouldReceive('processMessage')->andReturn(
            $this->llmResponse('Vi tu mensaje', 'consulta_general')
        );

        $controller = app(WhatsAppWebhookController::class);
        $reflection = new \ReflectionMethod($controller, 'processIncomingMessage');
        $reflection->setAccessible(true);

        $reflection->invoke($controller, [
            'wa_id'      => $this->waId,
            'text'       => 'Hola',
            'name'       => 'Diego',
            'message_id' => 'wamid.fail',
            'media'      => [
                'kind'      => 'image',
                'media_id'  => 'broken-id',
                'mime_type' => 'image/jpeg',
                'filename'  => 'foto.jpg',
            ],
        ]);

        // No debe haberse persistido attachment (la descarga falló)
        $this->assertEquals(0, ChatbotAttachment::where('session_id', $this->sessionId)->count());

        // Pero el flujo debe haber encolado el pending (el texto sí llegó)
        $this->assertEquals(1, ChatbotPendingMessage::where('session_id', $this->sessionId)->count());
    }

    /**
     * Caso 8 — La flag wa_approval_required controla si el flujo es Nivel 1.
     */
    public function test_caso8_flag_aproval_required_se_lee_correctamente()
    {
        DB::connection('tenant')->table('marketing_chatbot_config')
            ->where('config_key', 'wa_approval_required')
            ->update(['config_value' => '1']);

        $this->assertTrue($this->approvalService->isApprovalRequired());

        DB::connection('tenant')->table('marketing_chatbot_config')
            ->where('config_key', 'wa_approval_required')
            ->update(['config_value' => '0']);

        // Nuevo instance porque ChatbotConfig usa cache si lo hubiera
        $svc = app(WhatsAppApprovalService::class);
        $this->assertFalse($svc->isApprovalRequired());

        // Restaurar para no romper otros tests
        DB::connection('tenant')->table('marketing_chatbot_config')
            ->where('config_key', 'wa_approval_required')
            ->update(['config_value' => '1']);
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    private function ensureNivel1()
    {
        DB::connection('tenant')->table('marketing_chatbot_config')
            ->where('config_key', 'wa_approval_required')
            ->update(['config_value' => '1']);
    }

    private function createPending($aiReply)
    {
        return ChatbotPendingMessage::create([
            'session_id'   => $this->sessionId,
            'wa_id'        => $this->waId,
            'contact_name' => 'Ana Test',
            'user_message' => 'mensaje del usuario',
            'ai_reply'     => $aiReply,
            'ai_intent'    => 'saludo',
            'status'       => 'pending',
        ]);
    }

    /**
     * Construye una respuesta del LLM completa con defaults.
     */
    private function llmResponse($reply, $intent = 'consulta_general', array $overrides = [])
    {
        return array_replace_recursive([
            'reply'      => $reply,
            'intent'     => $intent,
            'confidence' => 0.9,
            'entities_extracted' => [
                'nombre_contacto' => null, 'empresa' => null, 'rubro' => null,
                'correo' => null, 'celular' => null, 'ciudad_entrega' => null,
                'fecha_objetivo' => null, 'evento_feria' => null,
                'presupuesto_mencionado' => null, 'muestra_virtual_solicitada' => false,
                'items_solicitados' => [], 'interes_resumen' => null,
            ],
            'conversation_state' => [
                'phase' => 'exploracion', 'missing_fields' => [],
                'has_contact' => false, 'has_commercial_intent' => false,
                'has_useful_data' => false,
            ],
            'actions' => [
                'should_register_lead' => false,
                'ready_to_convert' => false,
                'send_catalog' => null,
                'escalate_to_human' => false,
                'close_conversation' => false,
            ],
            'escalation' => ['recommended_action' => null, 'reason' => null],
            'lead_summary' => null,
        ], $overrides);
    }

    private function cleanup()
    {
        try {
            ChatbotPendingMessage::where('session_id', $this->sessionId)->delete();
            MarketingChatbotLead::where('session_id', $this->sessionId)->delete();
            ChatbotSession::where('session_id', $this->sessionId)->delete();
        } catch (\Exception $e) {
            // tablas pueden no existir
        }
    }
}
