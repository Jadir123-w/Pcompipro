<?php

namespace Tests\Feature\Marketing;

use Hyn\Tenancy\Environment;
use Hyn\Tenancy\Models\Website;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Modules\Marketing\Http\Controllers\Api\QuotationProposalController;
use Modules\Marketing\Mail\ProposalTestMail;
use Modules\Marketing\Models\QuotationConfig;
use Modules\Marketing\Models\QuotationProposal;
use Tests\TestCase;

/**
 * Com02CorreoPruebaTest — Correo de prueba de la Cotización Final (Req #14).
 *
 * Valida el contrato del endpoint sendTestEmail:
 *   1. Sin correo (ni en body ni en config) → success:false (no intenta enviar).
 *   2. NUNCA modifica el estado de la proposal ni de la prequote.
 *   3. Con correo configurado (sin body) → success:true y se envía el Mailable.
 *   4. COM10 — el correo del body tiene prioridad sobre el de config.
 *
 * El correo destino prioriza el del body (`email`); si no llega, usa la config
 * `correo_prueba_interno` como respaldo.
 */
class Com02CorreoPruebaTest extends TestCase
{
    /** @var QuotationProposalController */
    private $controller;

    /** @var QuotationProposal|null */
    private $proposal;

    /** @var string Valor previo de la config, para restaurar en tearDown */
    private $prevCorreo = '';

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

        // Necesitamos al menos una proposal para probar el endpoint.
        $this->proposal = QuotationProposal::query()->orderByDesc('id')->first();
        if (!$this->proposal) {
            $this->markTestSkipped('Sin proposals en el tenant para probar el correo de prueba');
        }

        $this->prevCorreo = (string) QuotationConfig::getValue('correo_prueba_interno', '');
        $this->controller = app(QuotationProposalController::class);
    }

    protected function tearDown(): void
    {
        try {
            QuotationConfig::setValue('correo_prueba_interno', $this->prevCorreo);
        } catch (\Exception $e) {}
        parent::tearDown();
    }

    /** @test */
    public function sin_correo_configurado_responde_success_false()
    {
        QuotationConfig::setValue('correo_prueba_interno', '');

        $estadoAntes = $this->proposal->estado;

        $resp = $this->controller->sendTestEmail(Request::create('/', 'POST'), $this->proposal->id);
        $data = $resp->getData(true);

        $this->assertEquals(422, $resp->getStatusCode());
        $this->assertFalse($data['success']);
        $this->assertStringContainsString('correo de prueba', strtolower($data['message']));

        // Estado intacto
        $this->proposal->refresh();
        $this->assertEquals($estadoAntes, $this->proposal->estado,
            'sendTestEmail no debe modificar el estado de la proposal');
    }

    /** @test */
    public function correo_invalido_en_config_responde_success_false()
    {
        QuotationConfig::setValue('correo_prueba_interno', 'esto-no-es-un-email');

        $resp = $this->controller->sendTestEmail(Request::create('/', 'POST'), $this->proposal->id);
        $data = $resp->getData(true);

        $this->assertEquals(422, $resp->getStatusCode());
        $this->assertFalse($data['success']);
    }

    /** @test */
    public function con_correo_configurado_envia_y_no_cambia_estado()
    {
        Mail::fake();
        QuotationConfig::setValue('correo_prueba_interno', 'interno.pruebas@compipro.net');

        $estadoAntes = $this->proposal->estado;

        $resp = $this->controller->sendTestEmail(Request::create('/', 'POST'), $this->proposal->id);
        $data = $resp->getData(true);

        // Puede fallar si la proposal no tiene prequest/items para el PDF;
        // en ese caso success:false con mensaje de PDF, pero el estado SIEMPRE
        // debe permanecer intacto.
        $this->proposal->refresh();
        $this->assertEquals($estadoAntes, $this->proposal->estado,
            'sendTestEmail NUNCA debe modificar el estado, ni en el happy path');

        if ($data['success']) {
            Mail::assertSent(ProposalTestMail::class, function ($mail) {
                return $mail->hasTo('interno.pruebas@compipro.net');
            });
            $this->assertStringContainsString('interno.pruebas@compipro.net', $data['message']);
        } else {
            // Si falló, debe ser por el PDF, no por un cambio de estado.
            $this->assertStringContainsStringIgnoringCase('pdf', $data['message']);
        }
    }

    /** @test */
    public function correo_del_body_tiene_prioridad_sobre_la_config()
    {
        // COM10 — la config tiene un correo, pero el usuario envía OTRO en el
        // body: debe ganar el del body.
        Mail::fake();
        QuotationConfig::setValue('correo_prueba_interno', 'interno.pruebas@compipro.net');

        $estadoAntes = $this->proposal->estado;

        $request = Request::create('/', 'POST', ['email' => 'elegido@cliente.com']);
        $resp = $this->controller->sendTestEmail($request, $this->proposal->id);
        $data = $resp->getData(true);

        $this->proposal->refresh();
        $this->assertEquals($estadoAntes, $this->proposal->estado,
            'sendTestEmail NUNCA debe modificar el estado');

        if ($data['success']) {
            Mail::assertSent(ProposalTestMail::class, function ($mail) {
                return $mail->hasTo('elegido@cliente.com');
            });
            $this->assertStringContainsString('elegido@cliente.com', $data['message']);
        } else {
            // Si falló, debe ser por el PDF, no por el correo elegido.
            $this->assertStringContainsStringIgnoringCase('pdf', $data['message']);
        }
    }

    /** @test */
    public function el_pdf_de_la_cotizacion_se_genera_sin_errores_de_plantilla()
    {
        // R6/R7 — verifica que la plantilla del PDF (firma + tabla) renderiza
        // sin errores tras los cambios. Requiere una proposal con ítems; si no
        // hay, se salta.
        $proposal = QuotationProposal::with(['prequote.items'])->orderByDesc('id')->first();
        if (!$proposal || !$proposal->prequote || $proposal->prequote->items->isEmpty()) {
            $this->markTestSkipped('Sin proposal con ítems para renderizar el PDF');
        }

        $generator = app(\Modules\Marketing\Services\ProposalPdfGenerator::class);
        $r = $generator->generate($proposal);

        $this->assertTrue($r['success'],
            'El PDF debe generarse sin errores de plantilla (msg: ' . ($r['message'] ?? '') . ')');
    }
}
