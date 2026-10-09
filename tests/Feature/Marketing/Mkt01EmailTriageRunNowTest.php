<?php

namespace Tests\Feature\Marketing;

use Hyn\Tenancy\Environment;
use Hyn\Tenancy\Models\Website;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Modules\Marketing\Contracts\AIProviderInterface;
use Modules\Marketing\Http\Controllers\Api\EmailTriagePendingController;
use Modules\Marketing\Models\MarketingEmailDraft;
use Modules\Marketing\Models\MarketingEmailReceived;
use Modules\Marketing\Services\EmailTriageClassifierService;
use Modules\Marketing\Services\EmailTriageReaderService;
use Modules\Marketing\Services\EmailTriageSenderService;
use Tests\TestCase;

/**
 * Mkt01EmailTriageRunNowTest — endpoint "Revisar bandeja ahora".
 *
 * El endpoint POST /marketing/email-triage/run-now dispara el reader IMAP +
 * clasificador IA sin esperar al scheduler. Útil para casos donde la
 * ejecutiva quiere ver correos nuevos al instante.
 *
 * Tests:
 *   1. run-now con --skip-read clasifica los `received` pendientes
 *   2. run-now con reader que devuelve error_message lo expone en stats
 *   3. throttle: una 2da llamada inmediata devuelve 429 con seconds_left
 *   4. tras pasar el throttle, vuelve a permitir la llamada
 *   5. mensaje legible: contiene un resumen útil del run
 */
class Mkt01EmailTriageRunNowTest extends TestCase
{
    private $createdEmailIds = [];
    private $createdDraftIds = [];
    private $throttleCacheKey;

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

        // Cache key del throttle: depende del tenant. Limpiamos antes de cada test.
        $this->throttleCacheKey = 'email_triage:run_now:tenant_' . $website->id;
        Cache::forget($this->throttleCacheKey);
    }

    protected function tearDown(): void
    {
        try {
            if (!empty($this->createdDraftIds)) MarketingEmailDraft::whereIn('id', $this->createdDraftIds)->delete();
            if (!empty($this->createdEmailIds)) MarketingEmailReceived::whereIn('id', $this->createdEmailIds)->delete();
        } catch (\Exception $e) {}
        Cache::forget($this->throttleCacheKey);
        Mockery::close();
        parent::tearDown();
    }

    /*
    |--------------------------------------------------------------------------
    | Tests
    |--------------------------------------------------------------------------
    */

    /** @test */
    public function run_now_con_skip_read_clasifica_pendientes()
    {
        $email = $this->seedEmail([
            'subject'    => 'Solicitud cotización',
            'body_plain' => 'Necesito 100 polos',
        ]);

        $aiMock = $this->stubAi(
            json_encode([
                'classification' => 'rfq', 'confidence' => 95,
                'reason' => 'pide cot', 'lang_detected' => 'es',
            ]),
            json_encode([
                'should_draft' => true,
                'draft_subject' => 'Re: Solicitud cotización',
                'draft_body'    => 'Estimado, gracias por escribirnos...',
                'to_email' => 'cliente@empresa.com',
                'cc_emails' => [], 'tone_used' => 'formal', 'data_gaps' => [],
            ])
        );
        $this->app->bind(EmailTriageClassifierService::class, function () use ($aiMock) {
            return new EmailTriageClassifierService($aiMock);
        });

        $controller = $this->controller();
        $req = Request::create('/marketing/email-triage/run-now', 'POST', ['skip_read' => true]);
        $resp = $controller->runNow($req, $this->emptyReader());
        $data = $resp->getData(true);

        $this->assertTrue($data['ok']);
        $this->assertEquals(1, $data['stats']['classified']);
        $this->assertEquals(1, $data['stats']['drafts_created']);
        $this->assertEquals('draft_pending', $email->fresh()->status);

        // Tracking para cleanup
        $draft = MarketingEmailDraft::where('email_received_id', $email->id)->first();
        if ($draft) $this->createdDraftIds[] = $draft->id;
    }

    /** @test */
    public function run_now_con_reader_que_da_error_lo_expone_en_stats()
    {
        $readerMock = Mockery::mock(EmailTriageReaderService::class);
        $readerMock->shouldReceive('read')->once()->andReturn([
            'fetched' => 0, 'persisted' => 0, 'duplicates' => 0, 'errors' => 1,
            'error_message' => 'Extensión ext-imap no disponible',
            'enabled' => true,
        ]);

        $controller = $this->controller();
        $req = Request::create('/marketing/email-triage/run-now', 'POST');
        $resp = $controller->runNow($req, $readerMock);
        $data = $resp->getData(true);

        $this->assertTrue($data['ok']); // El endpoint igual responde OK aunque el reader haya fallado
        $this->assertStringContainsString('ext-imap', $data['stats']['imap_error_msg']);
        $this->assertEquals(1, $data['stats']['imap_errors']);
    }

    /** @test */
    public function throttle_segunda_llamada_inmediata_devuelve_429()
    {
        $controller = $this->controller();

        // 1ra llamada — OK
        $req1 = Request::create('/marketing/email-triage/run-now', 'POST', ['skip_read' => true]);
        $resp1 = $controller->runNow($req1, $this->emptyReader());
        $this->assertEquals(200, $resp1->getStatusCode());

        // 2da llamada inmediata — debe dar 429
        $req2 = Request::create('/marketing/email-triage/run-now', 'POST', ['skip_read' => true]);
        $resp2 = $controller->runNow($req2, $this->emptyReader());
        $data2 = $resp2->getData(true);

        $this->assertEquals(429, $resp2->getStatusCode());
        $this->assertFalse($data2['ok']);
        $this->assertTrue($data2['throttled']);
        $this->assertGreaterThan(0, $data2['seconds_left']);
        $this->assertLessThanOrEqual(60, $data2['seconds_left']);
    }

    /** @test */
    public function throttle_tras_expirar_permite_nueva_llamada()
    {
        // Simulamos que el throttle se setea con timestamp viejo (hace 2 min)
        Cache::put($this->throttleCacheKey, time() - 120, 90);

        $controller = $this->controller();
        $req = Request::create('/marketing/email-triage/run-now', 'POST', ['skip_read' => true]);
        $resp = $controller->runNow($req, $this->emptyReader());

        $this->assertEquals(200, $resp->getStatusCode());
    }

    /** @test */
    public function mensaje_resumen_es_descriptivo()
    {
        // Con --skip-read y sin pendientes, el mensaje debe ser explícito (no vacío)
        $controller = $this->controller();
        $req = Request::create('/marketing/email-triage/run-now', 'POST', ['skip_read' => true]);
        $resp = $controller->runNow($req, $this->emptyReader());
        $data = $resp->getData(true);

        $this->assertNotEmpty($data['message']);
        $this->assertStringContainsStringIgnoringCase('sin novedades', $data['message']);
    }

    /** @test */
    public function mensaje_resumen_con_correos_nuevos_y_clasificacion()
    {
        // Reader devuelve 2 nuevos
        $readerMock = Mockery::mock(EmailTriageReaderService::class);
        $readerMock->shouldReceive('read')->once()->andReturn([
            'fetched' => 2, 'persisted' => 2, 'duplicates' => 0, 'errors' => 0,
            'error_message' => null, 'enabled' => true,
        ]);

        $controller = $this->controller();
        $req = Request::create('/marketing/email-triage/run-now', 'POST');
        $resp = $controller->runNow($req, $readerMock);
        $data = $resp->getData(true);

        $this->assertTrue($data['ok']);
        $this->assertStringContainsString('2 correo', $data['message']);
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    private function controller()
    {
        // Mock vacío del sender (no se usa en runNow pero el constructor lo pide)
        $sender = Mockery::mock(EmailTriageSenderService::class);
        return new EmailTriagePendingController($sender);
    }

    private function emptyReader()
    {
        $r = Mockery::mock(EmailTriageReaderService::class);
        $r->shouldReceive('read')->andReturn([
            'fetched' => 0, 'persisted' => 0, 'duplicates' => 0, 'errors' => 0,
            'error_message' => null, 'enabled' => true,
        ])->byDefault();
        return $r;
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

    private function seedEmail(array $overrides = [], $status = 'received')
    {
        $email = MarketingEmailReceived::create(array_merge([
            'message_id'  => 'runnow-' . uniqid() . '@example.com',
            'from_email'  => 'cliente@empresa.com',
            'from_name'   => 'Cliente Test',
            'to_email'    => 'info@compipro.net',
            'subject'     => 'Test',
            'body_plain'  => 'Test body',
            'received_at' => now(),
            'status'      => $status,
        ], $overrides));
        $this->createdEmailIds[] = $email->id;
        return $email;
    }
}
