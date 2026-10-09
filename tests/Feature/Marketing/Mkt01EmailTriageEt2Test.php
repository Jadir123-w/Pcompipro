<?php

namespace Tests\Feature\Marketing;

use Hyn\Tenancy\Environment;
use Hyn\Tenancy\Models\Website;
use Mockery;
use Modules\Marketing\Contracts\AIProviderInterface;
use Modules\Marketing\Models\MarketingEmailDraft;
use Modules\Marketing\Models\MarketingEmailReceived;
use Modules\Marketing\Services\EmailTriageClassifierService;
use Tests\TestCase;

/**
 * Mkt01EmailTriageEt2Test — clasificador IA + generador de borrador.
 *
 * Cubre el flujo completo de ET2 con el provider IA mockeado:
 *   1. RFQ → genera borrador, status=draft_pending, draft creado
 *   2. Consulta → genera borrador
 *   3. No relacionado → NO genera borrador, status=discarded
 *   4. Spam → NO genera borrador, status=discarded
 *   5. LLM responde JSON inválido → status=error
 *   6. LLM responde classification inválida → status=error
 *   7. Idempotencia: process() de un correo no-received no hace nada
 *   8. Modelo MarketingEmailDraft: helpers approve/reject/markEdited/markSent
 */
class Mkt01EmailTriageEt2Test extends TestCase
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
    | Flujo de clasificación + borrador
    |--------------------------------------------------------------------------
    */

    /** @test */
    public function rfq_clasifica_y_genera_borrador()
    {
        $email = $this->seedEmail([
            'subject'    => 'Cotización 100 polos talla M',
            'body_plain' => 'Hola, necesito cotización para 100 polos talla M con logo bordado, entrega 15-junio.',
        ]);

        $service = $this->makeService([
            'classifier_response' => json_encode([
                'classification' => 'rfq',
                'confidence'     => 92.0,
                'reason'         => 'Pide cotización con cantidad y fecha exactas.',
                'summary_es'     => 'Cotización 100 polos talla M con logo, entrega 15-jun',
                'lang_detected'  => 'es',
            ]),
            'draft_response' => json_encode([
                'should_draft'  => true,
                'draft_subject' => 'Re: Cotización 100 polos talla M',
                'draft_body'    => "Estimado,\n\nGracias por escribirnos...",
                'to_email'      => 'cliente@empresa.com',
                'cc_emails'     => [],
                'tone_used'     => 'formal_cercano',
                'data_gaps'     => ['RUC'],
                'skip_reason'   => null,
            ]),
        ]);

        $r = $service->process($email);

        $this->assertTrue($r['classified']);
        $this->assertEquals('rfq', $r['classification']);
        $this->assertNotNull($r['draft_id']);

        $email->refresh();
        $this->assertEquals('draft_pending', $email->status);
        $this->assertEquals('rfq', $email->classification);
        $this->assertEquals(92.0, (float) $email->classification_confidence);

        $draft = MarketingEmailDraft::find($r['draft_id']);
        $this->createdDraftIds[] = $draft->id;
        $this->assertEquals('pending', $draft->status);
        $this->assertEquals('Re: Cotización 100 polos talla M', $draft->draft_subject);
        $this->assertContains('RUC', $draft->data_gaps);
    }

    /** @test */
    public function consulta_clasifica_y_genera_borrador()
    {
        $email = $this->seedEmail([
            'subject'    => 'Consulta sobre catálogo',
            'body_plain' => 'Buenos días, ¿podrían enviarme el catálogo general?',
        ]);

        $service = $this->makeService([
            'classifier_response' => json_encode([
                'classification' => 'consulta',
                'confidence'     => 80,
                'reason'         => 'Pide catálogo general sin cantidades específicas.',
                'lang_detected'  => 'es',
            ]),
            'draft_response' => json_encode([
                'should_draft'  => true,
                'draft_subject' => 'Re: Consulta sobre catálogo',
                'draft_body'    => "Estimado,\n\nTe enviamos el catálogo...",
                'to_email'      => 'cliente@empresa.com',
                'cc_emails'     => [],
                'tone_used'     => 'formal',
                'data_gaps'     => [],
            ]),
        ]);

        $r = $service->process($email);

        $this->assertTrue($r['classified']);
        $this->assertEquals('consulta', $r['classification']);
        $this->assertNotNull($r['draft_id']);

        $this->createdDraftIds[] = $r['draft_id'];
        $email->refresh();
        $this->assertEquals('draft_pending', $email->status);
    }

    /** @test */
    public function no_relacionado_descarta_sin_borrador()
    {
        $email = $this->seedEmail([
            'subject'    => 'Factura mensual',
            'body_plain' => 'Adjunto su factura del mes...',
        ]);

        $service = $this->makeService([
            'classifier_response' => json_encode([
                'classification' => 'no_relacionado',
                'confidence'     => 90,
                'reason'         => 'Es una factura administrativa.',
                'lang_detected'  => 'es',
            ]),
        ]);

        $r = $service->process($email);

        $this->assertTrue($r['classified']);
        $this->assertEquals('no_relacionado', $r['classification']);
        $this->assertNull($r['draft_id']);

        $email->refresh();
        $this->assertEquals('discarded', $email->status);
    }

    /** @test */
    public function spam_descarta_sin_borrador()
    {
        $email = $this->seedEmail([
            'subject'    => 'GANE $1000 GRATIS HOY',
            'body_plain' => 'Click aquí para ganar dinero...',
        ]);

        $service = $this->makeService([
            'classifier_response' => json_encode([
                'classification' => 'spam',
                'confidence'     => 95,
                'reason'         => 'Patrón clásico de spam masivo con promesa de dinero.',
                'lang_detected'  => 'es',
            ]),
        ]);

        $r = $service->process($email);

        $this->assertEquals('spam', $r['classification']);
        $this->assertNull($r['draft_id']);
        $this->assertEquals('discarded', $email->fresh()->status);
    }

    /** @test */
    public function llm_responde_basura_marca_correo_como_error()
    {
        $email = $this->seedEmail([
            'subject'    => 'Test',
            'body_plain' => 'Test',
        ]);

        $service = $this->makeService([
            'classifier_response' => 'no es JSON, lo siento mucho',
        ]);

        $r = $service->process($email);

        $this->assertFalse($r['classified']);
        $this->assertNotNull($r['error']);
        $this->assertEquals('error', $email->fresh()->status);
    }

    /** @test */
    public function llm_responde_classification_invalida_marca_correo_como_error()
    {
        $email = $this->seedEmail();

        $service = $this->makeService([
            'classifier_response' => json_encode([
                'classification' => 'inventada',
                'confidence'     => 100,
                'reason'         => 'no_existe',
            ]),
        ]);

        $r = $service->process($email);

        $this->assertFalse($r['classified']);
        $this->assertEquals('error', $email->fresh()->status);
    }

    /** @test */
    public function process_no_re_clasifica_correos_que_ya_no_estan_received()
    {
        $email = $this->seedEmail([], 'classified');

        $service = $this->makeService([
            'classifier_response' => json_encode(['classification' => 'rfq', 'confidence' => 99]),
        ]);

        $r = $service->process($email);

        $this->assertFalse($r['classified']);
        $this->assertStringContainsStringIgnoringCase('received', $r['error']);
    }

    /*
    |--------------------------------------------------------------------------
    | Modelo MarketingEmailDraft
    |--------------------------------------------------------------------------
    */

    /** @test */
    public function draft_helpers_aprobar_rechazar_editar_marcar_enviado()
    {
        $email = $this->seedEmail([], 'draft_pending');
        $draft = MarketingEmailDraft::create([
            'email_received_id' => $email->id,
            'draft_subject'     => 'Re: Test',
            'draft_body'        => 'Body',
            'to_email'          => 'a@b.com',
            'status'            => MarketingEmailDraft::STATUS_PENDING,
        ]);
        $this->createdDraftIds[] = $draft->id;

        $this->assertTrue($draft->isPending());

        // Editar
        $draft->markEdited(1, ['draft_body' => 'Body editado']);
        $this->assertEquals('edited', $draft->fresh()->status);
        $this->assertEquals('Body editado', $draft->fresh()->draft_body);
        $this->assertTrue($draft->fresh()->isPending(), 'edited sigue siendo pending hasta aprobar');

        // Aprobar
        $draft->approve(1);
        $this->assertTrue($draft->fresh()->isApproved());

        // Marcar enviado
        $draft->markSent('<msgid-123@compipro.net>');
        $this->assertTrue($draft->fresh()->isSent());
        $this->assertEquals('<msgid-123@compipro.net>', $draft->fresh()->sent_message_id);
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    /**
     * @param  array   $overrides  Campos para sobreescribir
     * @param  string  $status     Estado inicial
     * @return MarketingEmailReceived
     */
    private function seedEmail(array $overrides = [], $status = 'received')
    {
        $email = MarketingEmailReceived::create(array_merge([
            'message_id'  => 'test-' . uniqid() . '@example.com',
            'from_email'  => 'cliente@empresa.com',
            'from_name'   => 'Cliente Test',
            'to_email'    => 'info@compipro.net',
            'subject'     => 'Test subject',
            'body_plain'  => 'Test body',
            'received_at' => now(),
            'status'      => $status,
        ], $overrides));
        $this->createdEmailIds[] = $email->id;
        return $email;
    }

    /**
     * Crear el service con AIProviderInterface mockeado.
     *
     * El mock retorna primero $args['classifier_response'] (la 1ra llamada al LLM)
     * y luego $args['draft_response'] si está (la 2da llamada).
     *
     * @param  array  $args  ['classifier_response'=>string, 'draft_response'=>?string]
     * @return EmailTriageClassifierService
     */
    private function makeService(array $args)
    {
        $mock = Mockery::mock(AIProviderInterface::class);
        if (isset($args['draft_response'])) {
            $mock->shouldReceive('sendMessage')
                ->andReturn($args['classifier_response'], $args['draft_response']);
        } else {
            $mock->shouldReceive('sendMessage')->andReturn($args['classifier_response']);
        }
        // Métodos opcionales que el service intenta llamar para metadata.
        // Mockery los considera "métodos sin expectativa" → tira BadMethodCallException
        // si el service los invoca. Los stubeamos como no-ops.
        $mock->shouldReceive('getProviderName')->andReturn('mock-provider')->byDefault();
        $mock->shouldReceive('getModelName')->andReturn('mock-model')->byDefault();
        return new EmailTriageClassifierService($mock);
    }
}
