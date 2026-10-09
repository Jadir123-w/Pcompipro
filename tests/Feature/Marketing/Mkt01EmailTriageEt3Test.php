<?php

namespace Tests\Feature\Marketing;

use Hyn\Tenancy\Environment;
use Hyn\Tenancy\Models\Website;
use Illuminate\Http\Request;
use Mockery;
use Modules\Marketing\Http\Controllers\Api\EmailTriagePendingController;
use Modules\Marketing\Models\ChatbotConfig;
use Modules\Marketing\Models\MarketingEmailDraft;
use Modules\Marketing\Models\MarketingEmailReceived;
use Modules\Marketing\Services\EmailTriageSenderService;
use Tests\TestCase;

/**
 * Mkt01EmailTriageEt3Test — UI cola pendientes + approve/edit/reject + SMTP sender.
 *
 * Cubre:
 *
 *  Sender service (sin SMTP real):
 *   - falla si el draft no está en estado approved/send_error
 *   - sin credenciales SMTP en BD → marca send_error con mensaje claro
 *
 *  Controller (llamado directo, sin HTTP):
 *   - countPending devuelve el conteo de pending+edited
 *   - index pagina con filtros de status/classification/search
 *   - show devuelve el draft con sus relaciones
 *   - update edita un draft pendiente
 *   - update rechaza editar un draft no-pendiente
 *   - approve marca como approved
 *   - reject marca como rejected + descarta el correo recibido
 *   - send rechaza si el draft no está approved/send_error
 *   - send con sender mockeado: marca como sent y replied
 */
class Mkt01EmailTriageEt3Test extends TestCase
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
    | Sender service
    |--------------------------------------------------------------------------
    */

    /** @test */
    public function sender_falla_si_draft_no_esta_aprobado()
    {
        $email = $this->seedEmail();
        $draft = $this->seedDraft($email, MarketingEmailDraft::STATUS_PENDING);

        $svc = new EmailTriageSenderService();
        $r = $svc->send($draft);

        $this->assertFalse($r['ok']);
        $this->assertNotEmpty($r['error']);
        $this->assertStringContainsStringIgnoringCase('aprobado', $r['error']);
    }

    /** @test */
    public function sender_falla_con_send_error_si_faltan_credenciales_smtp()
    {
        $email = $this->seedEmail();
        $draft = $this->seedDraft($email, MarketingEmailDraft::STATUS_APPROVED);

        // Vaciar credenciales SMTP
        $this->setConfig('email_smtp_host', null);
        $this->setConfig('email_user', null);
        $this->setConfig('email_password', null);

        $svc = new EmailTriageSenderService();
        $r = $svc->send($draft);

        $this->assertFalse($r['ok']);
        $this->assertNotEmpty($r['error']);
        $this->assertStringContainsStringIgnoringCase('credenciales', $r['error']);

        $fresh = $draft->fresh();
        $this->assertEquals(MarketingEmailDraft::STATUS_SEND_ERROR, $fresh->status);
        $this->assertNotNull($fresh->send_error_message);
    }

    /*
    |--------------------------------------------------------------------------
    | Controller
    |--------------------------------------------------------------------------
    */

    /** @test */
    public function count_pending_devuelve_conteo_correcto()
    {
        // Limpiar conteo previo para test estable
        $previo = MarketingEmailDraft::pending()->count();

        $email1 = $this->seedEmail();
        $email2 = $this->seedEmail();
        $email3 = $this->seedEmail();

        $this->seedDraft($email1, MarketingEmailDraft::STATUS_PENDING);
        $this->seedDraft($email2, MarketingEmailDraft::STATUS_EDITED);
        $this->seedDraft($email3, MarketingEmailDraft::STATUS_APPROVED); // no cuenta

        $resp = $this->controller()->countPending();
        $data = $resp->getData(true);

        $this->assertTrue($data['ok']);
        $this->assertEquals($previo + 2, $data['count']);
    }

    /** @test */
    public function index_pagina_y_filtra_por_status()
    {
        $email1 = $this->seedEmail();
        $email2 = $this->seedEmail();
        $d1 = $this->seedDraft($email1, MarketingEmailDraft::STATUS_PENDING);
        $d2 = $this->seedDraft($email2, MarketingEmailDraft::STATUS_APPROVED);

        $req = Request::create('/marketing/email-triage/drafts', 'GET', [
            'status' => 'approved',
            'per_page' => 50,
        ]);

        $resp = $this->controller()->index($req);
        $data = $resp->getData(true);

        $this->assertTrue($data['ok']);
        $ids = array_map(function ($r) { return $r['id']; }, $data['drafts']['data']);
        $this->assertContains($d2->id, $ids);
        $this->assertNotContains($d1->id, $ids);
    }

    /** @test */
    public function index_filtra_por_classification()
    {
        $email1 = $this->seedEmail(['classification' => 'rfq']);
        $email2 = $this->seedEmail(['classification' => 'consulta']);
        $d1 = $this->seedDraft($email1, MarketingEmailDraft::STATUS_PENDING);
        $d2 = $this->seedDraft($email2, MarketingEmailDraft::STATUS_PENDING);

        $req = Request::create('/marketing/email-triage/drafts', 'GET', [
            'classification' => 'rfq',
            'status'         => 'pending',
            'per_page'       => 50,
        ]);

        $resp = $this->controller()->index($req);
        $data = $resp->getData(true);

        $ids = array_map(function ($r) { return $r['id']; }, $data['drafts']['data']);
        $this->assertContains($d1->id, $ids);
        $this->assertNotContains($d2->id, $ids);
    }

    /** @test */
    public function index_busca_por_term_en_subject_o_remitente()
    {
        $token = 'TKN' . uniqid();

        $email1 = $this->seedEmail(['subject' => 'Cotización ' . $token]);
        $email2 = $this->seedEmail(['subject' => 'Otro asunto']);
        $d1 = $this->seedDraft($email1, MarketingEmailDraft::STATUS_PENDING);
        $d2 = $this->seedDraft($email2, MarketingEmailDraft::STATUS_PENDING);

        $req = Request::create('/marketing/email-triage/drafts', 'GET', [
            'search'   => $token,
            'status'   => 'pending',
            'per_page' => 50,
        ]);

        $resp = $this->controller()->index($req);
        $data = $resp->getData(true);

        $ids = array_map(function ($r) { return $r['id']; }, $data['drafts']['data']);
        $this->assertContains($d1->id, $ids);
        $this->assertNotContains($d2->id, $ids);
    }

    /** @test */
    public function show_devuelve_draft_con_email_received()
    {
        $email = $this->seedEmail();
        $draft = $this->seedDraft($email, MarketingEmailDraft::STATUS_PENDING);

        $resp = $this->controller()->show($draft->id);
        $data = $resp->getData(true);

        $this->assertTrue($data['ok']);
        $this->assertEquals($draft->id, $data['draft']['id']);
        $this->assertNotNull($data['draft']['email_received']);
        $this->assertEquals($email->id, $data['draft']['email_received']['id']);
    }

    /** @test */
    public function update_edita_draft_pendiente_y_marca_como_edited()
    {
        $email = $this->seedEmail();
        $draft = $this->seedDraft($email, MarketingEmailDraft::STATUS_PENDING);

        $req = Request::create('/marketing/email-triage/drafts/' . $draft->id, 'PUT', [
            'draft_subject' => 'Asunto editado',
            'draft_body'    => 'Cuerpo editado',
            'to_email'      => 'nuevo@destino.com',
            'cc_emails'     => ['cc1@x.com'],
        ]);

        $resp = $this->controller()->update($req, $draft->id);
        $data = $resp->getData(true);

        $this->assertTrue($data['ok']);
        $fresh = $draft->fresh();
        $this->assertEquals('edited', $fresh->status);
        $this->assertEquals('Asunto editado', $fresh->draft_subject);
        $this->assertEquals('Cuerpo editado', $fresh->draft_body);
        $this->assertEquals('nuevo@destino.com', $fresh->to_email);
        $this->assertEquals(['cc1@x.com'], $fresh->cc_emails);
    }

    /** @test */
    public function update_rechaza_editar_draft_no_pendiente()
    {
        $email = $this->seedEmail();
        $draft = $this->seedDraft($email, MarketingEmailDraft::STATUS_APPROVED);

        $req = Request::create('/marketing/email-triage/drafts/' . $draft->id, 'PUT', [
            'draft_subject' => 'no debería aplicar',
        ]);

        $resp = $this->controller()->update($req, $draft->id);
        $data = $resp->getData(true);

        $this->assertFalse($data['ok']);
        $this->assertEquals(422, $resp->getStatusCode());
        $this->assertEquals('approved', $draft->fresh()->status);
    }

    /** @test */
    public function update_valida_email_format_y_falla_con_422()
    {
        $email = $this->seedEmail();
        $draft = $this->seedDraft($email, MarketingEmailDraft::STATUS_PENDING);

        $req = Request::create('/marketing/email-triage/drafts/' . $draft->id, 'PUT', [
            'to_email' => 'no-es-email-valido',
        ]);

        $resp = $this->controller()->update($req, $draft->id);
        $data = $resp->getData(true);

        $this->assertFalse($data['ok']);
        $this->assertEquals(422, $resp->getStatusCode());
        $this->assertArrayHasKey('errors', $data);
    }

    /** @test */
    public function approve_marca_como_approved()
    {
        $email = $this->seedEmail();
        $draft = $this->seedDraft($email, MarketingEmailDraft::STATUS_PENDING);

        $resp = $this->controller()->approve($draft->id);
        $data = $resp->getData(true);

        $this->assertTrue($data['ok']);
        $this->assertEquals('approved', $draft->fresh()->status);
        $this->assertNotNull($draft->fresh()->approved_at);
    }

    /** @test */
    public function approve_rechaza_si_no_esta_pending()
    {
        $email = $this->seedEmail();
        $draft = $this->seedDraft($email, MarketingEmailDraft::STATUS_SENT);

        $resp = $this->controller()->approve($draft->id);
        $data = $resp->getData(true);

        $this->assertFalse($data['ok']);
        $this->assertEquals(422, $resp->getStatusCode());
        $this->assertEquals('sent', $draft->fresh()->status);
    }

    /** @test */
    public function reject_marca_draft_y_descarta_email_received()
    {
        $email = $this->seedEmail([], 'draft_pending');
        $draft = $this->seedDraft($email, MarketingEmailDraft::STATUS_PENDING);

        $req = Request::create('/marketing/email-triage/drafts/' . $draft->id . '/reject', 'POST', [
            'reason' => 'fuera de scope',
        ]);

        $resp = $this->controller()->reject($req, $draft->id);
        $data = $resp->getData(true);

        $this->assertTrue($data['ok']);

        $freshDraft = $draft->fresh();
        $this->assertEquals('rejected', $freshDraft->status);
        $this->assertEquals('fuera de scope', $freshDraft->rejected_reason);

        $freshEmail = $email->fresh();
        $this->assertEquals('discarded', $freshEmail->status);
    }

    /** @test */
    public function send_rechaza_si_draft_no_esta_approved()
    {
        $email = $this->seedEmail();
        $draft = $this->seedDraft($email, MarketingEmailDraft::STATUS_PENDING);

        $resp = $this->controller()->send($draft->id);
        $data = $resp->getData(true);

        $this->assertFalse($data['ok']);
        $this->assertEquals(422, $resp->getStatusCode());
        $this->assertEquals('pending', $draft->fresh()->status);
    }

    /** @test */
    public function send_con_sender_mock_marca_como_sent_y_email_replied()
    {
        $email = $this->seedEmail([], 'draft_pending');
        $draft = $this->seedDraft($email, MarketingEmailDraft::STATUS_APPROVED);

        // Mock del sender: simulamos envío exitoso y mutamos el draft como
        // lo haría el sender real (markSent es responsabilidad del service).
        $senderMock = Mockery::mock(EmailTriageSenderService::class);
        $senderMock->shouldReceive('send')->once()
            ->andReturnUsing(function ($d) {
                $d->markSent('<msg-test-001@compipro.net>');
                return ['ok' => true, 'message_id' => '<msg-test-001@compipro.net>', 'error' => null];
            });
        $this->app->instance(EmailTriageSenderService::class, $senderMock);

        $resp = $this->controllerWith($senderMock)->send($draft->id);
        $data = $resp->getData(true);

        $this->assertTrue($data['ok']);
        $this->assertEquals('sent', $draft->fresh()->status);
        $this->assertEquals('<msg-test-001@compipro.net>', $draft->fresh()->sent_message_id);
        $this->assertEquals('replied', $email->fresh()->status);
    }

    /** @test */
    public function send_con_sender_mock_que_falla_no_marca_email_replied()
    {
        $email = $this->seedEmail([], 'draft_pending');
        $draft = $this->seedDraft($email, MarketingEmailDraft::STATUS_APPROVED);

        $senderMock = Mockery::mock(EmailTriageSenderService::class);
        $senderMock->shouldReceive('send')->once()
            ->andReturnUsing(function ($d) {
                $d->markSendError('SMTP timeout');
                return ['ok' => false, 'message_id' => null, 'error' => 'SMTP timeout'];
            });

        $resp = $this->controllerWith($senderMock)->send($draft->id);
        $data = $resp->getData(true);

        $this->assertFalse($data['ok']);
        $this->assertEquals(500, $resp->getStatusCode());
        $this->assertEquals('send_error', $draft->fresh()->status);
        // El correo recibido NO debe pasar a 'replied' si falló el envío
        $this->assertEquals('draft_pending', $email->fresh()->status);
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    private function controller()
    {
        // Para los métodos que no llaman al sender, basta con instanciarlo
        // con un mock vacío (no se va a usar).
        $sender = Mockery::mock(EmailTriageSenderService::class);
        return new EmailTriagePendingController($sender);
    }

    private function controllerWith($senderMock)
    {
        return new EmailTriagePendingController($senderMock);
    }

    private function seedEmail(array $overrides = [], $status = 'draft_pending')
    {
        $email = MarketingEmailReceived::create(array_merge([
            'message_id'  => 'et3-' . uniqid() . '@example.com',
            'from_email'  => 'cliente@empresa.com',
            'from_name'   => 'Cliente Test',
            'to_email'    => 'info@compipro.net',
            'subject'     => 'Asunto test',
            'body_plain'  => 'Body test',
            'received_at' => now(),
            'status'      => $status,
        ], $overrides));
        $this->createdEmailIds[] = $email->id;
        return $email;
    }

    private function seedDraft($email, $status)
    {
        $draft = MarketingEmailDraft::create([
            'email_received_id' => $email->id,
            'draft_subject'     => 'Re: ' . $email->subject,
            'draft_body'        => 'Cuerpo del borrador IA',
            'to_email'          => $email->from_email,
            'cc_emails'         => [],
            'status'            => $status,
        ]);
        $this->createdDraftIds[] = $draft->id;
        return $draft;
    }

    private function setConfig($key, $value)
    {
        $row = ChatbotConfig::byKey($key);
        if ($row) {
            $row->config_value = $value;
            $row->save();
        }
    }
}
