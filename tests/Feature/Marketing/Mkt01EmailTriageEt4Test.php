<?php

namespace Tests\Feature\Marketing;

use Hyn\Tenancy\Environment;
use Hyn\Tenancy\Models\Website;
use Illuminate\Support\Facades\Artisan;
use Mockery;
use Modules\Marketing\Contracts\AIProviderInterface;
use Modules\Marketing\Models\MarketingEmailDraft;
use Modules\Marketing\Models\MarketingEmailReceived;
use Modules\Marketing\Services\EmailTriageClassifierService;
use Modules\Marketing\Services\EmailTriageSenderService;
use Tests\TestCase;

/**
 * Mkt01EmailTriageEt4Test — E2E del Email Triage completo.
 *
 * Cubre el bucle completo del MKT-01 Email Triage tal como va a ocurrir en
 * producción cuando el cron dispare el orquestador cada 5 min:
 *
 *   1. ET1 — Correo entra a `marketing_emails_received` (simulado: el reader
 *            IMAP requiere un servidor real, así que persistimos directo)
 *   2. ET2 — IA clasifica + genera borrador (mockeamos el provider IA)
 *   3. ET3 — Ejecutiva edita el borrador, lo aprueba, lo envía
 *            (mockeamos el sender SMTP)
 *
 * Casos cubiertos:
 *   1. Bucle feliz: RFQ → clasifica → draft → edita → aprueba → envía → replied
 *   2. Bucle rechazo: RFQ → clasifica → draft → rechaza → email pasa a discarded
 *   3. Bucle reintento: aprobado → falla SMTP → send_error → reintenta → sent
 *   4. Comando orquestador con --skip-read clasifica los pendientes
 *   5. Discard automático: spam → no genera draft, email pasa a discarded
 */
class Mkt01EmailTriageEt4Test extends TestCase
{
    private $createdEmailIds = [];
    private $createdDraftIds = [];

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
            if (!empty($this->createdDraftIds)) MarketingEmailDraft::whereIn('id', $this->createdDraftIds)->delete();
            if (!empty($this->createdEmailIds)) MarketingEmailReceived::whereIn('id', $this->createdEmailIds)->delete();
        } catch (\Exception $e) {}
        Mockery::close();
        parent::tearDown();
    }

    /*
    |--------------------------------------------------------------------------
    | E2E completos
    |--------------------------------------------------------------------------
    */

    /**
     * Caso 1 — Bucle feliz completo.
     *
     * received → classifier IA → draft pending → editar → aprobar → enviar SMTP
     *          → draft sent + email replied.
     */
    public function test_e2e_rfq_completo_received_a_replied()
    {
        // ── (1) ET1 — Correo entra ──
        $email = $this->seedEmail([
            'subject'    => 'Cotización urgente 200 polos talla L',
            'body_plain' => 'Hola, necesito 200 polos talla L con logo bordado, entrega 1-julio.',
        ]);
        $this->assertEquals('received', $email->status);

        // ── (2) ET2 — Classifier IA mockeado ──
        $aiMock = Mockery::mock(AIProviderInterface::class);
        $aiMock->shouldReceive('sendMessage')->andReturn(
            // Llamada 1: classifier
            json_encode([
                'classification' => 'rfq',
                'confidence'     => 95,
                'reason'         => 'Pide cotización con cantidad y fecha exactas.',
                'summary_es'     => 'RFQ 200 polos talla L con logo, entrega 01-jul',
                'lang_detected'  => 'es',
            ]),
            // Llamada 2: generador de borrador
            json_encode([
                'should_draft'  => true,
                'draft_subject' => 'Re: Cotización urgente 200 polos talla L',
                'draft_body'    => "Estimado,\n\nGracias por contactarnos. Estamos preparando su cotización.\n\nSaludos cordiales.",
                'to_email'      => 'cliente@empresa.com',
                'cc_emails'     => [],
                'tone_used'     => 'formal_cercano',
                'data_gaps'     => ['RUC del cliente'],
            ])
        );
        $aiMock->shouldReceive('getProviderName')->andReturn('mock-provider')->byDefault();
        $aiMock->shouldReceive('getModelName')->andReturn('mock-model')->byDefault();

        $classifier = new EmailTriageClassifierService($aiMock);
        $r = $classifier->process($email);

        $this->assertTrue($r['classified']);
        $this->assertEquals('rfq', $r['classification']);
        $this->assertNotNull($r['draft_id']);

        $draft = MarketingEmailDraft::find($r['draft_id']);
        $this->createdDraftIds[] = $draft->id;

        $this->assertEquals('draft_pending', $email->fresh()->status);
        $this->assertEquals('pending', $draft->status);
        $this->assertEquals('Re: Cotización urgente 200 polos talla L', $draft->draft_subject);
        $this->assertContains('RUC del cliente', $draft->data_gaps);

        // ── (3) ET3 — Ejecutiva edita ──
        $draft->markEdited(7, [
            'draft_body' => "Estimado,\n\nGracias por contactarnos. Adjunto la cotización con descuento.\n\nSaludos.",
        ]);
        $this->assertEquals('edited', $draft->fresh()->status);
        $this->assertStringContainsString('descuento', $draft->fresh()->draft_body);

        // ── (4) ET3 — Ejecutiva aprueba ──
        $draft->approve(7);
        $this->assertEquals('approved', $draft->fresh()->status);
        $this->assertNotNull($draft->fresh()->approved_at);

        // ── (5) ET3 — Sender SMTP mockeado simula envío exitoso ──
        $senderMock = Mockery::mock(EmailTriageSenderService::class);
        $senderMock->shouldReceive('send')->once()
            ->andReturnUsing(function ($d) {
                $d->markSent('<msgid-e2e-001@compipro.net>');
                return ['ok' => true, 'message_id' => '<msgid-e2e-001@compipro.net>', 'error' => null];
            });

        $result = $senderMock->send($draft);
        $this->assertTrue($result['ok']);

        // El controller (no testeado acá, ya cubierto por ET3Test) marca el
        // received como replied después del envío exitoso. Lo simulamos:
        $email->update(['status' => MarketingEmailReceived::STATUS_REPLIED]);

        // ── Verificar estado terminal ──
        $this->assertEquals('sent', $draft->fresh()->status);
        $this->assertEquals('<msgid-e2e-001@compipro.net>', $draft->fresh()->sent_message_id);
        $this->assertEquals('replied', $email->fresh()->status);
    }

    /**
     * Caso 2 — Rechazo: el correo recibido pasa a `discarded`.
     */
    public function test_e2e_rechazo_email_pasa_a_discarded()
    {
        $email = $this->seedEmail([
            'subject'    => 'Consulta',
            'body_plain' => 'Necesito catálogo',
        ]);

        $aiMock = $this->stubAi(
            json_encode([
                'classification' => 'consulta',
                'confidence'     => 75,
                'reason'         => 'Pide catálogo',
                'lang_detected'  => 'es',
            ]),
            json_encode([
                'should_draft'  => true,
                'draft_subject' => 'Re: Consulta',
                'draft_body'    => 'Estimado,...',
                'to_email'      => 'cliente@empresa.com',
                'cc_emails'     => [],
                'tone_used'     => 'formal',
                'data_gaps'     => [],
            ])
        );

        $classifier = new EmailTriageClassifierService($aiMock);
        $r = $classifier->process($email);
        $this->createdDraftIds[] = $r['draft_id'];
        $draft = MarketingEmailDraft::find($r['draft_id']);

        // Ejecutiva rechaza
        $draft->reject(7, 'Cliente conocido, ya tiene catálogo');
        // Y el controller (probado en ET3Test) descarta también el received:
        $email->update(['status' => MarketingEmailReceived::STATUS_DISCARDED]);

        $this->assertEquals('rejected', $draft->fresh()->status);
        $this->assertEquals('Cliente conocido, ya tiene catálogo', $draft->fresh()->rejected_reason);
        $this->assertEquals('discarded', $email->fresh()->status);
    }

    /**
     * Caso 3 — Reintento de envío después de un fallo SMTP.
     */
    public function test_e2e_send_error_y_reintento_ok()
    {
        $email = $this->seedEmail([], 'draft_pending');
        $draft = MarketingEmailDraft::create([
            'email_received_id' => $email->id,
            'draft_subject'     => 'Re: Test',
            'draft_body'        => 'Cuerpo',
            'to_email'          => 'a@b.com',
            'status'            => MarketingEmailDraft::STATUS_APPROVED,
        ]);
        $this->createdDraftIds[] = $draft->id;

        // 1er intento: falla SMTP
        $senderMock1 = Mockery::mock(EmailTriageSenderService::class);
        $senderMock1->shouldReceive('send')->once()
            ->andReturnUsing(function ($d) {
                $d->markSendError('SMTP connection refused');
                return ['ok' => false, 'message_id' => null, 'error' => 'SMTP connection refused'];
            });

        $r1 = $senderMock1->send($draft);
        $this->assertFalse($r1['ok']);
        $this->assertEquals('send_error', $draft->fresh()->status);
        $this->assertNotEmpty($draft->fresh()->send_error_message);

        // 2do intento (reintento): éxito
        $senderMock2 = Mockery::mock(EmailTriageSenderService::class);
        $senderMock2->shouldReceive('send')->once()
            ->andReturnUsing(function ($d) {
                $d->markSent('<msgid-retry-001@compipro.net>');
                return ['ok' => true, 'message_id' => '<msgid-retry-001@compipro.net>', 'error' => null];
            });

        $r2 = $senderMock2->send($draft);
        $this->assertTrue($r2['ok']);
        $this->assertEquals('sent', $draft->fresh()->status);
    }

    /**
     * Caso 4 — Spam: no se genera borrador, el correo pasa directo a discarded.
     */
    public function test_e2e_spam_no_genera_borrador_pasa_a_discarded()
    {
        $email = $this->seedEmail([
            'subject'    => 'GANE $1000 GRATIS',
            'body_plain' => 'Click aquí para ganar dinero ya',
        ]);

        $aiMock = $this->stubAi(
            json_encode([
                'classification' => 'spam',
                'confidence'     => 99,
                'reason'         => 'Patrón clásico de spam masivo',
                'lang_detected'  => 'es',
            ]),
            null  // no llamada a generador de draft
        );

        $classifier = new EmailTriageClassifierService($aiMock);
        $r = $classifier->process($email);

        $this->assertEquals('spam', $r['classification']);
        $this->assertNull($r['draft_id']);
        $this->assertEquals('discarded', $email->fresh()->status);
    }

    /**
     * Caso 5 — El comando orquestador marketing:email-triage:run procesa los
     * received pendientes con --skip-read y reporta los conteos esperados.
     */
    public function test_e2e_comando_orquestador_clasifica_pendientes()
    {
        // Crear 3 correos pendientes de clasificar (uno por categoría útil)
        $rfq      = $this->seedEmail(['subject' => 'Cotización X', 'body_plain' => 'Necesito 100 polos']);
        $consulta = $this->seedEmail(['subject' => 'Catálogo',     'body_plain' => 'Pueden enviar catálogo?']);
        $spam     = $this->seedEmail(['subject' => 'WIN MONEY',    'body_plain' => 'Click here']);

        // Mock IA: 3 clasificaciones distintas + 2 borradores (rfq y consulta)
        $aiMock = Mockery::mock(AIProviderInterface::class);
        $aiMock->shouldReceive('sendMessage')->andReturn(
            json_encode(['classification'=>'rfq',     'confidence'=>90, 'reason'=>'pide cot', 'lang_detected'=>'es']),
            json_encode(['should_draft'=>true, 'draft_subject'=>'Re: Cot X', 'draft_body'=>'...',  'to_email'=>'a@b.com', 'cc_emails'=>[], 'tone_used'=>'f', 'data_gaps'=>[]]),
            json_encode(['classification'=>'consulta','confidence'=>80, 'reason'=>'pide cat', 'lang_detected'=>'es']),
            json_encode(['should_draft'=>true, 'draft_subject'=>'Re: Cat',   'draft_body'=>'...',  'to_email'=>'a@b.com', 'cc_emails'=>[], 'tone_used'=>'f', 'data_gaps'=>[]]),
            json_encode(['classification'=>'spam',    'confidence'=>99, 'reason'=>'spam',    'lang_detected'=>'es'])
        );
        $aiMock->shouldReceive('getProviderName')->andReturn('mock-provider')->byDefault();
        $aiMock->shouldReceive('getModelName')->andReturn('mock-model')->byDefault();
        $this->app->instance(AIProviderInterface::class, $aiMock);
        // El classifier resuelve el AIProviderInterface vía bind, así que
        // sustituyendo la instance global es suficiente para la fase IA.
        $this->app->bind(EmailTriageClassifierService::class, function ($app) use ($aiMock) {
            return new EmailTriageClassifierService($aiMock);
        });

        // Ejecutar el orquestador con --skip-read (no necesitamos IMAP)
        $tenantId = Website::first()->id;
        $exitCode = Artisan::call('marketing:email-triage:run', [
            '--tenant'         => $tenantId,
            '--skip-read'      => true,
            '--classify-limit' => 10,
        ]);
        $output = Artisan::output();

        $this->assertEquals(0, $exitCode);
        $this->assertStringContainsString('Tenants procesados:    1', $output);
        $this->assertStringContainsString('IA clasificados:       3', $output);
        $this->assertStringContainsString('IA borradores creados: 2', $output);
        $this->assertStringContainsString('IA descartados:        1', $output);

        // Verificar estados finales
        $this->assertEquals('draft_pending', $rfq->fresh()->status);
        $this->assertEquals('draft_pending', $consulta->fresh()->status);
        $this->assertEquals('discarded',     $spam->fresh()->status);

        // Tracking para cleanup
        $rfqDraft = MarketingEmailDraft::where('email_received_id', $rfq->id)->first();
        $consDraft = MarketingEmailDraft::where('email_received_id', $consulta->id)->first();
        if ($rfqDraft)  $this->createdDraftIds[] = $rfqDraft->id;
        if ($consDraft) $this->createdDraftIds[] = $consDraft->id;
    }

    /**
     * Caso 6 — El comando con --skip-read y sin pendientes no falla.
     */
    public function test_e2e_comando_sin_pendientes_devuelve_exit_zero()
    {
        $tenantId = Website::first()->id;
        $exitCode = Artisan::call('marketing:email-triage:run', [
            '--tenant'    => $tenantId,
            '--skip-read' => true,
        ]);
        $this->assertEquals(0, $exitCode);
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    private function seedEmail(array $overrides = [], $status = 'received')
    {
        $email = MarketingEmailReceived::create(array_merge([
            'message_id'  => 'et4-' . uniqid() . '@example.com',
            'from_email'  => 'cliente@empresa.com',
            'from_name'   => 'Cliente E2E',
            'to_email'    => 'info@compipro.net',
            'subject'     => 'Asunto E2E',
            'body_plain'  => 'Body E2E',
            'received_at' => now(),
            'status'      => $status,
        ], $overrides));
        $this->createdEmailIds[] = $email->id;
        return $email;
    }

    private function stubAi($classifierResponse, $draftResponse = null)
    {
        $mock = Mockery::mock(AIProviderInterface::class);
        if ($draftResponse !== null) {
            $mock->shouldReceive('sendMessage')->andReturn($classifierResponse, $draftResponse);
        } else {
            $mock->shouldReceive('sendMessage')->andReturn($classifierResponse);
        }
        $mock->shouldReceive('getProviderName')->andReturn('mock-provider')->byDefault();
        $mock->shouldReceive('getModelName')->andReturn('mock-model')->byDefault();
        return $mock;
    }
}
