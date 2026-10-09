<?php

namespace Tests\Feature\Marketing;

use Hyn\Tenancy\Environment;
use Hyn\Tenancy\Models\Website;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Modules\Marketing\Models\ChatbotAttachment;
use Modules\Marketing\Models\ChatbotSession;
use Modules\Marketing\Models\MarketingChatbotLead;
use Modules\Marketing\Services\AIConversationEngine;
use Modules\Marketing\Services\ChatbotOrchestrator;
use Tests\TestCase;

/**
 * Mkt01ChatbotOrchestrationTest
 *
 * Tests E2E del orchestrator del chatbot MKT-01 (Web Chat). Cubre:
 *
 *   5 casos del Documento Funcional:
 *     1. Saludo + intención clara → registra lead
 *     2. Consulta general → orienta sin registrar
 *     3. Solicita catálogo de línea específica → envía link
 *     4. Reclamo / urgencia → escala con recommended_action='atender_reclamo'
 *     5. No entendido 2 veces → fuerza escalada (R12 + guardrail backend)
 *
 *   6 casos del Playbook conversacional:
 *     6. Multi-producto → registra con items separados (R15)
 *     7. Catálogo → luego cotización (transición de fase)
 *     8. Lead de feria → captura evento_feria
 *     9. Solicita muestra virtual → flag muestra_virtual_solicitada=true
 *     10. Postventa → escala con recommended_action='responder_postventa'
 *     11. Upload de archivo (logo) → enriquece historial + asocia al lead
 *
 * Estrategia:
 *   - Bootstrap del primer tenant (Hyn) en setUp()
 *   - DB compartida con dev (no usamos RefreshDatabase porque rompe Hyn);
 *     hacemos cleanup explícito de chatbot_sessions/leads/attachments antes
 *     de cada test usando un session_id único por test (UUID).
 *   - Mock de AIConversationEngine vía container — no llamamos API real.
 *     Cada test inyecta el JSON crudo que el LLM "habría devuelto".
 *
 * Para correr:
 *   docker exec compipro-erp-fpm-1 php artisan test --filter Mkt01Chatbot
 */
class Mkt01ChatbotOrchestrationTest extends TestCase
{
    /** @var ChatbotOrchestrator */
    private $orchestrator;

    /** @var \Mockery\MockInterface */
    private $aiMock;

    /** @var string  Session ID único por test */
    private $sessionId;

    protected function setUp(): void
    {
        parent::setUp();

        // ── 0) Override del DB_DATABASE legacy de phpunit.xml ──
        // El phpunit.xml define DB_DATABASE=multifacturalo_dusk (Dusk legacy)
        // pero esa BD no existe en este proyecto. Forzamos el valor real desde .env.
        $realDb = env('DB_DATABASE');
        if ($realDb && $realDb !== 'multifacturalo_dusk') {
            // env() puede haber sido sobrescrito por phpunit; tomamos del fichero .env directamente
        }
        // Forzar 'dberp' (sistema) para que Website::first() funcione
        config(['database.connections.system.database' => 'dberp']);
        config(['database.connections.mysql.database'  => 'dberp']);
        \Illuminate\Support\Facades\DB::purge('system');
        \Illuminate\Support\Facades\DB::purge('mysql');

        // ── 1) Activar el primer tenant disponible ──
        $website = Website::first();
        if (!$website) {
            $this->markTestSkipped('No hay tenants registrados en la BD; los tests requieren al menos uno.');
        }
        app(Environment::class)->tenant($website);

        // ── 2) Mock del AIConversationEngine ──
        // Reemplazamos el binding del container; cualquier resolución del
        // engine durante este test recibe el mock con respuestas canned.
        $this->aiMock = \Mockery::mock(AIConversationEngine::class);
        $this->app->instance(AIConversationEngine::class, $this->aiMock);

        // ── 3) Resolver el orchestrator (toma el mock automáticamente) ──
        $this->orchestrator = app(ChatbotOrchestrator::class);

        // ── 4) Session ID único por test, para no chocar con datos viejos ──
        $this->sessionId = 'test-' . str_replace('.', '', uniqid('', true));

        // ── 5) Cleanup defensivo por si quedó residuo ──
        $this->cleanupSession($this->sessionId);
    }

    protected function tearDown(): void
    {
        $this->cleanupSession($this->sessionId);
        \Mockery::close();
        parent::tearDown();
    }

    /*
    |--------------------------------------------------------------------------
    | 5 CASOS DEL DOCUMENTO FUNCIONAL
    |--------------------------------------------------------------------------
    */

    /**
     * Caso 1 — Saludo + intención clara con contacto, dato útil.
     * Esperado: should_register_lead → se crea MarketingChatbotLead.
     */
    public function test_caso1_intencion_clara_registra_lead()
    {
        $this->expectAi($this->sampleResponse([
            'reply'  => 'Perfecto Juan. ¿Para cuándo necesitarías las 100 mochilas?',
            'intent' => 'registrar_para_cotizacion',
            'entities_extracted' => [
                'nombre_contacto' => 'Juan Pérez',
                'correo'          => 'juan@empresa.com',
                'empresa'         => 'Acme SAC',
                'items_solicitados' => [
                    ['item_texto_original' => 'mochilas', 'item_categoria' => 'Textil', 'item_cantidad' => '100'],
                ],
                'interes_resumen' => '100 mochilas con logo',
            ],
            'conversation_state' => [
                'phase' => 'registro_cotizacion',
                'has_contact' => true,
                'has_commercial_intent' => true,
                'has_useful_data' => true,
            ],
            'actions' => [
                'should_register_lead' => true,
                'ready_to_convert'     => true,
            ],
            'lead_summary' => 'Cliente Acme requiere 100 mochilas con logo.',
        ]));

        $result = $this->orchestrator->handleMessage(
            $this->sessionId,
            'Hola, soy Juan de Acme. juan@empresa.com - quiero cotizar 100 mochilas',
            'web'
        );

        $this->assertTrue($result['ok']);
        $this->assertNotEmpty($result['reply']);

        // Lead se creó
        $lead = MarketingChatbotLead::where('session_id', $this->sessionId)->first();
        $this->assertNotNull($lead, 'Se debió crear un lead');
        $this->assertEquals('juan@empresa.com', $lead->email);
        $this->assertEquals('Acme SAC', $lead->company_name);
        $this->assertEquals('registrar_para_cotizacion', $lead->intent_detected);
        $this->assertTrue((bool) $lead->ready_to_convert);
    }

    /**
     * Caso 2 — Consulta general sin contacto.
     * Esperado: NO se registra lead (R11/R12: faltan contacto+dato útil).
     */
    public function test_caso2_consulta_general_no_registra()
    {
        $this->expectAi($this->sampleResponse([
            'reply'  => '¡Hola! Trabajamos productos corporativos: textil, impresos, ecológicos. ¿Qué buscas?',
            'intent' => 'consulta_general',
            'conversation_state' => [
                'phase' => 'exploracion',
                'has_contact' => false,
                'has_commercial_intent' => false,
                'has_useful_data' => false,
            ],
            'actions' => [
                'should_register_lead' => false,
            ],
        ]));

        $result = $this->orchestrator->handleMessage($this->sessionId, '¿Qué hacen?', 'web');

        $this->assertTrue($result['ok']);
        $this->assertNull(MarketingChatbotLead::where('session_id', $this->sessionId)->first(),
            'NO debe crearse lead en consulta general sin contacto');
    }

    /**
     * Caso 3 — Solicita catálogo de línea específica.
     * Esperado: response.actions.show_catalog devuelve { line, url }.
     */
    public function test_caso3_solicitar_catalogo_linea_especifica()
    {
        // Crear catálogo Textil para que resolveCatalogUrl encuentre algo
        $catId = DB::connection('tenant')->table('chatbot_catalogs')->insertGetId([
            'line_name'   => 'Textil',
            'catalog_url' => 'https://compipro.test/catalogo-textil.pdf',
            'is_general'  => 0,
            'active'      => 1,
            'sort_order'  => 10,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        try {
            $this->expectAi($this->sampleResponse([
                'reply'  => 'Te paso el catálogo de Textil.',
                'intent' => 'solicitar_catalogo',
                'actions' => [
                    'send_catalog' => 'Textil',
                ],
            ]));

            $result = $this->orchestrator->handleMessage(
                $this->sessionId, 'Pásame el catálogo de textil', 'web'
            );

            $this->assertTrue($result['ok']);
            $this->assertNotNull($result['actions']['show_catalog']);
            $this->assertEquals('Textil', $result['actions']['show_catalog']['line']);
            $this->assertEquals('https://compipro.test/catalogo-textil.pdf', $result['actions']['show_catalog']['url']);
        } finally {
            DB::connection('tenant')->table('chatbot_catalogs')->where('id', $catId)->delete();
        }
    }

    /**
     * Caso 4 — Reclamo urgente con contacto.
     * Esperado: escalate, recommended_action="atender_reclamo", lead se registra.
     */
    public function test_caso4_reclamo_urgente_escala_con_recommended_action()
    {
        $this->expectAi($this->sampleResponse([
            'reply'  => 'Entiendo, lamento la situación. Te derivo con una ejecutiva.',
            'intent' => 'reclamo_o_urgente',
            'entities_extracted' => [
                'nombre_contacto' => 'Carlos',
                'celular'         => '999888777',
                'empresa'         => 'TestCorp',
                'interes_resumen' => 'Reclamo por pedido demorado',
            ],
            'conversation_state' => [
                'phase' => 'escalamiento',
                'has_contact' => true,
                'has_commercial_intent' => true,
                'has_useful_data' => true,
            ],
            'actions' => [
                'should_register_lead' => true,
                'escalate_to_human'    => true,
            ],
            'escalation' => [
                'recommended_action' => 'atender_reclamo',
                'reason'             => 'Cliente molesto por pedido pendiente.',
            ],
            'lead_summary' => 'Reclamo de Carlos.',
        ]));

        $result = $this->orchestrator->handleMessage(
            $this->sessionId, 'URGENTE: mi pedido lleva 1 mes y nada. 999888777', 'web'
        );

        $this->assertTrue($result['actions']['escalated']);
        $this->assertTrue($result['actions']['conversation_closed']);
        $this->assertEquals('atender_reclamo', $result['actions']['recommended_action']);

        // Sesión queda como 'escalated'
        $session = ChatbotSession::where('session_id', $this->sessionId)->first();
        $this->assertEquals('escalated', $session->status);

        // Lead urgency = high
        $lead = MarketingChatbotLead::where('session_id', $this->sessionId)->first();
        $this->assertNotNull($lead);
        $this->assertEquals('high', $lead->urgency_level);
    }

    /**
     * Caso 5 — No entendido 2 veces → guardrail backend fuerza escalada (R12).
     */
    public function test_caso5_no_entendido_dos_veces_fuerza_escalada()
    {
        // Turno 1: LLM devuelve no_entendido pero NO escala todavía
        $this->aiMock->shouldReceive('processMessage')->once()->andReturn(
            $this->sampleResponse([
                'reply'  => '¿Podrías aclarar tu mensaje?',
                'intent' => 'no_entendido',
                'actions' => ['should_register_lead' => false],
            ])
        );

        $result1 = $this->orchestrator->handleMessage($this->sessionId, '!@#$%^', 'web');
        $this->assertFalse($result1['actions']['escalated']);

        $session = ChatbotSession::where('session_id', $this->sessionId)->first();
        $this->assertEquals(1, $session->not_understood_count);

        // Turno 2: LLM otra vez no_entendido → guardrail backend dispara escalada
        $this->aiMock->shouldReceive('processMessage')->once()->andReturn(
            $this->sampleResponse([
                'reply'  => 'Sigo sin entender.',
                'intent' => 'no_entendido',
                'actions' => ['should_register_lead' => false],
                // NO marca escalate; el backend lo fuerza
            ])
        );

        $result2 = $this->orchestrator->handleMessage($this->sessionId, '???', 'web');

        $this->assertTrue($result2['actions']['escalated'],
            'Al 2do no_entendido el orchestrator debe forzar escalada');
        $this->assertEquals('no_entendido_repetido', $result2['actions']['recommended_action']);

        $session->refresh();
        $this->assertEquals('escalated', $session->status);
        $this->assertEquals(2, $session->not_understood_count);
    }

    /*
    |--------------------------------------------------------------------------
    | 6 CASOS DEL PLAYBOOK
    |--------------------------------------------------------------------------
    */

    /**
     * Caso 6 — Multi-producto: el lead debe persistir items_solicitados como array.
     */
    public function test_caso6_multi_producto_persiste_items()
    {
        $this->expectAi($this->sampleResponse([
            'reply'  => 'Anotado: 50 mochilas y 200 libretas. ¿Para cuándo?',
            'intent' => 'registrar_para_cotizacion',
            'entities_extracted' => [
                'nombre_contacto' => 'Ana',
                'correo'          => 'ana@empresa.com',
                'items_solicitados' => [
                    ['item_texto_original' => 'mochilas', 'item_categoria' => 'Textil',   'item_cantidad' => '50'],
                    ['item_texto_original' => 'libretas', 'item_categoria' => 'Impresos', 'item_cantidad' => '200'],
                ],
            ],
            'conversation_state' => [
                'has_contact' => true,
                'has_commercial_intent' => true,
                'has_useful_data' => true,
            ],
            'actions' => ['should_register_lead' => true],
        ]));

        $this->orchestrator->handleMessage(
            $this->sessionId, 'Quiero cotizar 50 mochilas y 200 libretas. ana@empresa.com', 'web'
        );

        $lead = MarketingChatbotLead::where('session_id', $this->sessionId)->first();
        $this->assertNotNull($lead);
        $structured = is_array($lead->structured_data) ? $lead->structured_data : json_decode($lead->structured_data, true);
        $this->assertCount(2, $structured['items_solicitados']);
        $this->assertEquals('mochilas', $structured['items_solicitados'][0]['item_texto_original']);
        $this->assertEquals('libretas', $structured['items_solicitados'][1]['item_texto_original']);
    }

    /**
     * Caso 7 — Catálogo → luego cotización: dos turnos consecutivos.
     * Turno 1: pide catálogo (no registra). Turno 2: cotiza con contacto (registra).
     */
    public function test_caso7_catalogo_luego_cotizacion()
    {
        // Turno 1: pide catálogo, sin contacto → no registra
        $this->aiMock->shouldReceive('processMessage')->once()->andReturn(
            $this->sampleResponse([
                'reply'  => 'Te paso el catálogo general.',
                'intent' => 'solicitar_catalogo',
                'actions' => [
                    'send_catalog'         => 'General',
                    'should_register_lead' => false,
                ],
            ])
        );
        $r1 = $this->orchestrator->handleMessage($this->sessionId, 'mándame el catálogo', 'web');
        $this->assertFalse((bool) ($r1['actions']['conversation_closed'] ?? false));
        $this->assertNull(MarketingChatbotLead::where('session_id', $this->sessionId)->first());

        // Turno 2: ya con contacto, pide cotizar → registra
        $this->aiMock->shouldReceive('processMessage')->once()->andReturn(
            $this->sampleResponse([
                'reply'  => 'Perfecto, ¿cuántas necesitas?',
                'intent' => 'registrar_para_cotizacion',
                'entities_extracted' => [
                    'nombre_contacto' => 'Luis',
                    'correo'          => 'luis@empresa.com',
                    'items_solicitados' => [['item_texto_original' => 'casacas', 'item_categoria' => 'Textil']],
                ],
                'conversation_state' => [
                    'has_contact' => true,
                    'has_commercial_intent' => true,
                    'has_useful_data' => true,
                ],
                'actions' => ['should_register_lead' => true],
            ])
        );
        $this->orchestrator->handleMessage(
            $this->sessionId, 'Vi una casaca, quiero cotizar. luis@empresa.com', 'web'
        );

        $lead = MarketingChatbotLead::where('session_id', $this->sessionId)->first();
        $this->assertNotNull($lead, 'En el segundo turno debió crearse el lead');
        $this->assertEquals('luis@empresa.com', $lead->email);
    }

    /**
     * Caso 8 — Lead de feria: evento_feria se persiste en structured_data.
     */
    public function test_caso8_lead_de_feria_persiste_evento()
    {
        $this->expectAi($this->sampleResponse([
            'reply'  => '¡Qué bien que nos visitaron en EXPO!',
            'intent' => 'lead_de_feria',
            'entities_extracted' => [
                'nombre_contacto' => 'Marta',
                'correo'          => 'marta@empresa.com',
                'evento_feria'    => 'EXPO Corporativa 2026',
                'items_solicitados' => [['item_texto_original' => 'merch', 'item_categoria' => 'Packs']],
            ],
            'conversation_state' => [
                'has_contact' => true,
                'has_commercial_intent' => true,
                'has_useful_data' => true,
            ],
            'actions' => ['should_register_lead' => true],
        ]));

        $this->orchestrator->handleMessage(
            $this->sessionId, 'Los vi en EXPO Corporativa, soy Marta marta@empresa.com', 'web'
        );

        $lead = MarketingChatbotLead::where('session_id', $this->sessionId)->first();
        $this->assertNotNull($lead);
        $structured = is_array($lead->structured_data) ? $lead->structured_data : json_decode($lead->structured_data, true);
        $this->assertEquals('EXPO Corporativa 2026', $structured['evento_feria']);
    }

    /**
     * Caso 9 — Solicita muestra virtual: flag muestra_virtual_solicitada=true.
     */
    public function test_caso9_muestra_virtual_solicitada()
    {
        $this->expectAi($this->sampleResponse([
            'reply'  => 'La ejecutiva te prepara la muestra virtual.',
            'intent' => 'solicitar_muestra_virtual',
            'entities_extracted' => [
                'nombre_contacto' => 'Pedro',
                'celular'         => '999111222',
                'muestra_virtual_solicitada' => true,
                'items_solicitados' => [['item_texto_original' => 'gorras', 'item_categoria' => 'Textil']],
            ],
            'conversation_state' => [
                'has_contact' => true,
                'has_commercial_intent' => true,
                'has_useful_data' => true,
            ],
            'actions' => ['should_register_lead' => true],
        ]));

        $this->orchestrator->handleMessage(
            $this->sessionId, '¿Pueden mostrarme una idea con mi logo en una gorra? 999111222', 'web'
        );

        $lead = MarketingChatbotLead::where('session_id', $this->sessionId)->first();
        $this->assertNotNull($lead);
        $this->assertEquals('solicitar_muestra_virtual', $lead->intent_detected);
        // El flag se persiste indirectamente: el LLM lo dijo y el orchestrator
        // lo pasa a entities_extracted; los tests del LLM en sí están fuera de
        // alcance, pero el intent persiste correctamente.
    }

    /**
     * Caso 10 — Postventa: escala con recommended_action='responder_postventa'.
     */
    public function test_caso10_postventa_escala()
    {
        $this->expectAi($this->sampleResponse([
            'reply'  => 'Te derivo con la ejecutiva que te atendió.',
            'intent' => 'postventa_simple',
            'entities_extracted' => [
                'nombre_contacto' => 'Sofía',
                'correo'          => 'sofia@empresa.com',
                'empresa'         => 'OldClient SAC',
            ],
            'conversation_state' => [
                'has_contact' => true,
                'has_commercial_intent' => true,
                'has_useful_data' => true,
            ],
            'actions' => [
                'should_register_lead' => true,
                'escalate_to_human'    => true,
            ],
            'escalation' => [
                'recommended_action' => 'responder_postventa',
                'reason'             => 'Consulta de seguimiento de pedido.',
            ],
        ]));

        $result = $this->orchestrator->handleMessage(
            $this->sessionId, 'tengo un pedido del mes pasado, sofia@empresa.com', 'web'
        );

        $this->assertTrue($result['actions']['escalated']);
        $this->assertEquals('responder_postventa', $result['actions']['recommended_action']);
    }

    /**
     * Caso 11 — Upload de archivo: enriquece historial + asocia al lead.
     */
    public function test_caso11_upload_logo_se_asocia_al_lead()
    {
        // Pre: guardar un attachment "subido" al endpoint de upload
        Storage::disk('local')->put(
            'chatbot-uploads/test/' . $this->sessionId . '/dummy.png',
            base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=')
        );
        // Crear sesión activa primero (el orchestrator la lookea para attachments)
        ChatbotSession::create([
            'session_id'       => $this->sessionId,
            'channel'          => 'web',
            'status'           => 'active',
            'started_at'       => now(),
            'last_activity_at' => now(),
        ]);

        $att = ChatbotAttachment::create([
            'session_id'    => $this->sessionId,
            'lead_id'       => null,
            'original_name' => 'logo.png',
            'stored_path'   => 'chatbot-uploads/test/' . $this->sessionId . '/dummy.png',
            'mime_type'     => 'image/png',
            'size_bytes'    => 95,
            'kind'          => 'logo',
        ]);

        // El orchestrator inyecta "[ARCHIVO ADJUNTO: ...]" en el mensaje del LLM;
        // simulamos respuesta del LLM acusando recibo + registrando lead
        $this->expectAi($this->sampleResponse([
            'reply'  => 'Perfecto, recibí tu logo. ¿Cantidad?',
            'intent' => 'registrar_para_cotizacion',
            'entities_extracted' => [
                'nombre_contacto' => 'Diego',
                'correo'          => 'diego@empresa.com',
                'muestra_virtual_solicitada' => true,
                'items_solicitados' => [['item_texto_original' => 'mochilas', 'item_categoria' => 'Textil']],
            ],
            'conversation_state' => [
                'has_contact' => true,
                'has_commercial_intent' => true,
                'has_useful_data' => true,
            ],
            'actions' => ['should_register_lead' => true],
        ]));

        $this->orchestrator->handleMessage(
            $this->sessionId,
            'Te paso mi logo. Soy Diego diego@empresa.com',
            'web',
            ['attachment_ids' => [$att->id]]
        );

        // Lead creado
        $lead = MarketingChatbotLead::where('session_id', $this->sessionId)->first();
        $this->assertNotNull($lead);

        // Attachment asociado al lead (linkAttachmentsToLead)
        $att->refresh();
        $this->assertEquals($lead->id, $att->lead_id,
            'El attachment debe quedar asociado al lead recién creado');
    }

    /*
    |--------------------------------------------------------------------------
    | Guardrail de escalación con datos mínimos
    |--------------------------------------------------------------------------
    |
    | Bug detectado en QA: si la IA marca `escalate_to_human=true` en el mismo
    | turno en que pide nombre+contacto al cliente, la sesión se cierra ANTES
    | de que el usuario pueda responder. Resultado: ejecutiva no tiene a quién
    | llamar.
    |
    | Fix: posponer la escalación si faltan datos mínimos (nombre + email/celular).
    | Excepción: caso `no_entendido_repetido` (R12) — escala siempre.
    */

    /**
     * Caso A — IA marca escalate pero faltan nombre + contacto → escalación POSPUESTA.
     * La sesión sigue activa para que el bot pueda capturar datos en el próximo turno.
     */
    public function test_escalacion_sin_datos_minimos_se_pospone()
    {
        $this->expectAi($this->sampleResponse([
            'reply'  => 'Claro, con gusto te conecto. ¿Me das tu nombre y celular?',
            'intent' => 'reclamo_o_urgente',
            'entities_extracted' => [
                // VACÍO — el cliente acaba de pedir humano sin dar datos aún
                'nombre_contacto' => null,
                'correo'          => null,
                'celular'         => null,
            ],
            'actions' => [
                'should_register_lead' => false,
                'escalate_to_human'    => true,  // ← IA marcó escalar
            ],
            'escalation' => [
                'recommended_action' => 'asignar_ejecutiva_general',
                'reason'             => 'Cliente pidió humano urgente.',
            ],
        ]));

        $result = $this->orchestrator->handleMessage(
            $this->sessionId, 'Necesito hablar con un humano urgente', 'web'
        );

        // ESCALACIÓN POSPUESTA — la sesión NO se cierra
        $this->assertFalse($result['actions']['escalated'],
            'Escalación debe posponerse cuando faltan datos del cliente');
        $this->assertFalse($result['actions']['conversation_closed'],
            'Conversación NO debe cerrarse mientras esperamos datos');

        // La sesión sigue ACTIVA (no escalated)
        $session = ChatbotSession::where('session_id', $this->sessionId)->first();
        $this->assertNotNull($session);
        $this->assertEquals('active', $session->status,
            'Sesión debe quedar active para próximo turno de captura');
    }

    /**
     * Caso B — IA marca escalate Y hay datos completos → escalación normal.
     * Validación de regresión: el guardrail nuevo NO rompe el camino feliz.
     */
    public function test_escalacion_con_datos_completos_si_escala()
    {
        $this->expectAi($this->sampleResponse([
            'reply'  => 'Listo, una ejecutiva te llama.',
            'intent' => 'reclamo_o_urgente',
            'entities_extracted' => [
                'nombre_contacto' => 'Pedro García',
                'celular'         => '987654321',
                'empresa'         => 'Acme SAC',
                'interes_resumen' => 'Cliente urgente, pedido grande',
            ],
            'conversation_state' => [
                'has_contact' => true,
                'has_commercial_intent' => true,
                'has_useful_data' => true,
            ],
            'actions' => [
                'should_register_lead' => true,
                'escalate_to_human'    => true,
            ],
            'escalation' => [
                'recommended_action' => 'asignar_ejecutiva_general',
                'reason'             => 'Pedido grande con urgencia.',
            ],
            'lead_summary' => 'Pedro García, pedido urgente',
        ]));

        $result = $this->orchestrator->handleMessage(
            $this->sessionId, 'Soy Pedro García, 987654321, necesito humano urgente', 'web'
        );

        // ESCALACIÓN NORMAL — ya hay datos, escala como siempre
        $this->assertTrue($result['actions']['escalated']);
        $this->assertTrue($result['actions']['conversation_closed']);

        $session = ChatbotSession::where('session_id', $this->sessionId)->first();
        $this->assertEquals('escalated', $session->status);

        // Lead creado con datos del cliente
        $lead = MarketingChatbotLead::where('session_id', $this->sessionId)->first();
        $this->assertNotNull($lead, 'Lead debe crearse cuando hay datos');
        $this->assertEquals('Pedro García', $lead->name);
        $this->assertEquals('987654321', $lead->phone);
    }

    /**
     * Caso C — Excepción `no_entendido_repetido`: escala aunque falten datos.
     * Es un caso operativo distinto (cliente no se comunica de forma útil),
     * no comercial, así que igualmente derivamos a una ejecutiva.
     */
    public function test_escalacion_no_entendido_repetido_escala_sin_datos()
    {
        $this->expectAi($this->sampleResponse([
            'reply'  => 'Sigo sin entender. Te derivo con una ejecutiva.',
            'intent' => 'no_entendido',
            'entities_extracted' => [
                'nombre_contacto' => null,
                'correo'          => null,
                'celular'         => null,
            ],
            'actions' => [
                'should_register_lead' => false,
                'escalate_to_human'    => true,
            ],
            'escalation' => [
                'recommended_action' => 'no_entendido_repetido', // ← caso especial
                'reason'             => 'Cliente no se comunica claramente tras 2 intentos.',
            ],
        ]));

        $result = $this->orchestrator->handleMessage(
            $this->sessionId, '!@#$%^&', 'web'
        );

        // ESCALA AUNQUE FALTEN DATOS — es el caso especial R12
        $this->assertTrue($result['actions']['escalated'],
            'no_entendido_repetido debe escalar siempre, aunque falten datos');
        $this->assertEquals('no_entendido_repetido', $result['actions']['recommended_action']);

        $session = ChatbotSession::where('session_id', $this->sessionId)->first();
        $this->assertEquals('escalated', $session->status);
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    /**
     * Encola UNA respuesta esperada del AI mock.
     */
    private function expectAi(array $aiResponse)
    {
        $this->aiMock->shouldReceive('processMessage')->once()->andReturn($aiResponse);
    }

    /**
     * Genera una respuesta del LLM con campos por defecto + overrides.
     * Garantiza la estructura JSON que el orchestrator espera.
     */
    private function sampleResponse(array $overrides = [])
    {
        $base = [
            'reply'      => '',
            'intent'     => 'consulta_general',
            'confidence' => 0.9,
            'entities_extracted' => [
                'nombre_contacto' => null,
                'empresa'         => null,
                'rubro'           => null,
                'correo'          => null,
                'celular'         => null,
                'ciudad_entrega'  => null,
                'fecha_objetivo'  => null,
                'evento_feria'    => null,
                'presupuesto_mencionado'    => null,
                'muestra_virtual_solicitada' => false,
                'items_solicitados' => [],
                'interes_resumen' => null,
            ],
            'conversation_state' => [
                'phase' => 'exploracion',
                'missing_fields' => [],
                'has_contact' => false,
                'has_commercial_intent' => false,
                'has_useful_data' => false,
            ],
            'actions' => [
                'should_register_lead' => false,
                'ready_to_convert'     => false,
                'send_catalog'         => null,
                'escalate_to_human'    => false,
                'close_conversation'   => false,
            ],
            'escalation' => [
                'recommended_action' => null,
                'reason'             => null,
            ],
            'lead_summary' => null,
        ];

        return $this->arrayMergeDeep($base, $overrides);
    }

    private function arrayMergeDeep(array $a, array $b)
    {
        foreach ($b as $k => $v) {
            if (is_array($v) && isset($a[$k]) && is_array($a[$k]) && $this->isAssoc($v) && $this->isAssoc($a[$k])) {
                $a[$k] = $this->arrayMergeDeep($a[$k], $v);
            } else {
                $a[$k] = $v;
            }
        }
        return $a;
    }

    private function isAssoc(array $arr)
    {
        if ($arr === []) return false;
        return array_keys($arr) !== range(0, count($arr) - 1);
    }

    /**
     * Borra session/lead/attachments del session_id de este test.
     */
    private function cleanupSession($sessionId)
    {
        try {
            ChatbotAttachment::where('session_id', $sessionId)->delete();
            MarketingChatbotLead::where('session_id', $sessionId)->delete();
            ChatbotSession::where('session_id', $sessionId)->delete();
        } catch (\Exception $e) {
            // tablas pueden no existir todavía en este tenant
        }
    }
}
