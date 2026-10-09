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
 * Mkt01EmailTriageUrgencyDetectionTest — Detección de urgencia en ET2.
 *
 * El prompt R7 le pide al LLM evaluar el nivel de urgencia (low | normal |
 * high | critical) independientemente de la clasificación. Estos tests
 * validan que el service:
 *
 *   1. Persiste el urgency_level retornado por el LLM (4 niveles válidos).
 *   2. Cuando el LLM no devuelve urgency_level → default 'normal' (defensa).
 *   3. Cuando el LLM devuelve un valor inválido → default 'normal' (defensa).
 *   4. Scope withHighUrgency() filtra high + critical.
 *   5. Helper isHighUrgency() reconoce high y critical pero NO normal/low.
 *
 * Mockeamos el provider IA — no testeamos contra una llamada real al LLM.
 */
class Mkt01EmailTriageUrgencyDetectionTest extends TestCase
{
    /** @var int[] */
    private $createdEmailIds = [];

    /** @var int[] */
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
    | Persistencia del urgency_level retornado por el LLM
    |--------------------------------------------------------------------------
    */

    /** @test */
    public function urgency_high_se_persiste_en_la_bd()
    {
        $email = $this->seedEmail([
            'subject'    => 'URGENTE - Necesito cotización polos',
            'body_plain' => 'Hola, necesito esto urgente para mañana. 100 polos talla M.',
        ]);

        $service = $this->makeService([
            'classifier_response' => json_encode([
                'classification' => 'rfq',
                'confidence'     => 90.0,
                'reason'         => 'Pide cotización con cantidad y fecha.',
                'summary_es'     => 'Cotización urgente 100 polos M',
                'lang_detected'  => 'es',
                'urgency_level'  => 'high',
                'urgency_reason' => 'El asunto dice URGENTE + entrega para mañana.',
            ]),
            'draft_response' => $this->validDraftJson(),
        ]);

        $service->process($email);
        $email->refresh();

        $this->assertEquals('high', $email->urgency_level);
        $this->assertTrue($email->isHighUrgency());
    }

    /** @test */
    public function urgency_critical_se_persiste_y_se_marca_high_urgency()
    {
        $email = $this->seedEmail();

        $service = $this->makeService([
            'classifier_response' => json_encode([
                'classification' => 'rfq',
                'confidence'     => 85.0,
                'reason'         => 'Cliente importante con compromiso roto.',
                'summary_es'     => 'Reclamo entrega vencida',
                'lang_detected'  => 'es',
                'urgency_level'  => 'critical',
                'urgency_reason' => 'Compromiso comercial existente que se está rompiendo.',
            ]),
            'draft_response' => $this->validDraftJson(),
        ]);

        $service->process($email);
        $email->refresh();

        $this->assertEquals('critical', $email->urgency_level);
        $this->assertTrue($email->isHighUrgency());
    }

    /** @test */
    public function urgency_low_se_persiste_y_no_es_high_urgency()
    {
        $email = $this->seedEmail();

        $service = $this->makeService([
            'classifier_response' => json_encode([
                'classification' => 'consulta',
                'confidence'     => 70.0,
                'reason'         => 'Pide info general sin apuro.',
                'summary_es'     => 'Consulta general',
                'lang_detected'  => 'es',
                'urgency_level'  => 'low',
                'urgency_reason' => 'Dice "cuando puedan" textualmente.',
            ]),
            'draft_response' => $this->validDraftJson(),
        ]);

        $service->process($email);
        $email->refresh();

        $this->assertEquals('low', $email->urgency_level);
        $this->assertFalse($email->isHighUrgency());
    }

    /** @test */
    public function urgency_normal_se_persiste_y_no_es_high_urgency()
    {
        $email = $this->seedEmail();

        $service = $this->makeService([
            'classifier_response' => json_encode([
                'classification' => 'consulta',
                'confidence'     => 75.0,
                'reason'         => 'Sin señales de urgencia.',
                'summary_es'     => 'Consulta neutra',
                'lang_detected'  => 'es',
                'urgency_level'  => 'normal',
                'urgency_reason' => 'Sin señales de urgencia ni de anti-urgencia.',
            ]),
            'draft_response' => $this->validDraftJson(),
        ]);

        $service->process($email);
        $email->refresh();

        $this->assertEquals('normal', $email->urgency_level);
        $this->assertFalse($email->isHighUrgency());
    }

    /*
    |--------------------------------------------------------------------------
    | Defensa: LLM no envía urgency_level o envía valor inválido
    |--------------------------------------------------------------------------
    */

    /** @test */
    public function urgency_ausente_cae_a_normal_por_default()
    {
        $email = $this->seedEmail();

        $service = $this->makeService([
            'classifier_response' => json_encode([
                'classification' => 'rfq',
                'confidence'     => 80.0,
                'reason'         => 'Pide cotización.',
                'summary_es'     => 'RFQ standard',
                'lang_detected'  => 'es',
                // urgency_level no enviado intencionalmente (LLM viejo o falló)
            ]),
            'draft_response' => $this->validDraftJson(),
        ]);

        $service->process($email);
        $email->refresh();

        $this->assertEquals('normal', $email->urgency_level,
            'Si el LLM no devuelve urgency_level, debe defaultear a normal');
    }

    /** @test */
    public function urgency_invalido_cae_a_normal_por_default()
    {
        $email = $this->seedEmail();

        $service = $this->makeService([
            'classifier_response' => json_encode([
                'classification' => 'rfq',
                'confidence'     => 80.0,
                'reason'         => 'Pide cotización.',
                'summary_es'     => 'RFQ standard',
                'lang_detected'  => 'es',
                'urgency_level'  => 'SUPER_URGENTE',  // valor inválido
                'urgency_reason' => 'Test de valor inválido',
            ]),
            'draft_response' => $this->validDraftJson(),
        ]);

        $service->process($email);
        $email->refresh();

        $this->assertEquals('normal', $email->urgency_level,
            'Si el LLM devuelve un urgency_level fuera de [low,normal,high,critical], debe defaultear a normal');
    }

    /*
    |--------------------------------------------------------------------------
    | Scope withHighUrgency()
    |--------------------------------------------------------------------------
    */

    /** @test */
    public function scope_with_high_urgency_filtra_high_y_critical()
    {
        $low      = $this->seedEmail(['urgency_level' => 'low']);
        $normal   = $this->seedEmail(['urgency_level' => 'normal']);
        $high     = $this->seedEmail(['urgency_level' => 'high']);
        $critical = $this->seedEmail(['urgency_level' => 'critical']);

        $highUrgencyIds = MarketingEmailReceived::withHighUrgency()
            ->whereIn('id', [$low->id, $normal->id, $high->id, $critical->id])
            ->pluck('id')
            ->toArray();

        $this->assertCount(2, $highUrgencyIds);
        $this->assertContains($high->id, $highUrgencyIds);
        $this->assertContains($critical->id, $highUrgencyIds);
        $this->assertNotContains($low->id, $highUrgencyIds);
        $this->assertNotContains($normal->id, $highUrgencyIds);
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
            'message_id'  => 'urgency-test-' . uniqid() . '@example.com',
            'from_email'  => 'cliente@empresa.com',
            'from_name'   => 'Cliente Test',
            'to_email'    => 'info@compipro.net',
            'subject'     => 'Test urgency',
            'body_plain'  => 'Test body',
            'received_at' => now(),
            'status'      => $status,
        ], $overrides));
        $this->createdEmailIds[] = $email->id;
        return $email;
    }

    /**
     * JSON de borrador válido (para que ET2 no falle en la 2da llamada al LLM).
     */
    private function validDraftJson()
    {
        return json_encode([
            'should_draft'  => true,
            'draft_subject' => 'Re: Test',
            'draft_body'    => "Estimado,\n\nTest",
            'to_email'      => 'cliente@empresa.com',
            'cc_emails'     => [],
            'tone_used'     => 'formal_cercano',
            'data_gaps'     => [],
            'skip_reason'   => null,
        ]);
    }

    /**
     * Crear el service con AIProviderInterface mockeado.
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
            $mock->shouldReceive('sendMessage')
                ->andReturn($args['classifier_response']);
        }
        $mock->shouldReceive('getProviderName')->andReturn('claude');
        $mock->shouldReceive('getModelName')->andReturn('claude-sonnet-4-6');

        return new EmailTriageClassifierService($mock);
    }
}
