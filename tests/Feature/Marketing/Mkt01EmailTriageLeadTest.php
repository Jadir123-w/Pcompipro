<?php

namespace Tests\Feature\Marketing;

use Hyn\Tenancy\Environment;
use Hyn\Tenancy\Models\Website;
use Modules\Comercial\Models\Lead;
use Modules\Marketing\Services\LeadRegistrationService;
use Tests\TestCase;

/**
 * Mkt01EmailTriageLeadTest — registro de lead en Comercial (Tarea 1).
 *
 * El Email Triage y el chatbot comparten el mismo destino:
 * LeadRegistrationService::createComercialLeadFromData(), que crea el Lead en
 * la tabla `lead` (el que ve la ejecutiva en el ERP). Aquí validamos ese
 * destino común y el mapeo de prioridad.
 */
class Mkt01EmailTriageLeadTest extends TestCase
{
    /** @var array IDs de leads creados para limpiar */
    private $cleanupIds = [];

    /** @var LeadRegistrationService */
    private $service;

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

        $this->service = app(LeadRegistrationService::class);
    }

    protected function tearDown(): void
    {
        try {
            if (!empty($this->cleanupIds)) {
                Lead::whereIn('id', $this->cleanupIds)->forceDelete();
            }
        } catch (\Exception $e) {}
        parent::tearDown();
    }

    /** @test */
    public function crea_el_lead_en_comercial_con_los_datos_del_correo()
    {
        $lead = $this->service->createComercialLeadFromData([
            'origen'       => 'email_triage_mkt01',
            'descripcion'  => "Lead por correo.\nAsunto: Cotización polos\nEtiquetas: textil, polos",
            'contacto'     => 'Juan Pérez',
            'telefono'     => '999888777',
            'email'        => 'juan@willis.test',
            'razon_social' => 'WILLIS S.A.C.',
            'ruc'          => '20123456789',
            'asunto_email' => 'Cotización 100 polos',
            'prioridad'    => 'high',
        ]);

        $this->assertNotNull($lead, 'Debe crear el Lead');
        $this->cleanupIds[] = $lead->id;

        $fresh = Lead::find($lead->id);
        $this->assertEquals('email_triage_mkt01', $fresh->origen);
        $this->assertEquals('Juan Pérez', $fresh->contacto);
        $this->assertEquals('juan@willis.test', $fresh->email);
        $this->assertEquals('WILLIS S.A.C.', $fresh->razon_social);
        $this->assertEquals('Cotización 100 polos', $fresh->asunto_email);
        $this->assertEquals('alta', $fresh->prioridad, 'urgency high → prioridad alta');
        $this->assertEquals('nuevo', $fresh->etapa);
        $this->assertEquals('pendiente', $fresh->estado_revision);
        $this->assertStringContainsString('textil', (string) $fresh->descripcion);
    }

    /** @test */
    public function mapea_la_prioridad_desde_los_niveles_del_llm()
    {
        $casos = [
            'critical' => 'alta',
            'high'     => 'alta',
            'normal'   => 'media',
            'low'      => 'baja',
            'alta'     => 'alta',   // ya normalizado, se respeta
            'xxx'      => 'media',  // inválido → default media
        ];

        foreach ($casos as $in => $esperado) {
            $lead = $this->service->createComercialLeadFromData([
                'origen'    => 'email_triage_mkt01',
                'email'     => 'p_' . $in . '@test.local',
                'prioridad' => $in,
            ]);
            $this->assertNotNull($lead);
            $this->cleanupIds[] = $lead->id;
            $this->assertEquals($esperado, $lead->fresh()->prioridad,
                "prioridad '$in' debe mapear a '$esperado'");
        }
    }
}
