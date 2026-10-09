<?php

namespace Tests\Feature\Marketing;

use Hyn\Tenancy\Environment;
use Hyn\Tenancy\Models\Website;
use Modules\Comercial\Models\Lead;
use Modules\Marketing\Models\MarketingChatbotLead;
use Modules\Marketing\Services\LeadRegistrationService;
use Tests\TestCase;

/**
 * Mkt01ChatbotLeadDescriptionTest — Bug del lead que descartaba solicitudes.
 *
 * Contexto del bug
 * ================
 * Dante (cliente COMPIPRO) hizo una prueba con el chatbot:
 *   - Cliente: "200 casacas corporativas con logo"
 *   - Cliente: "enviar catalogo a josegevara@senter.com"
 * Lead creado en BD con descripción: "200 casacas corporativas con logo"
 * Se perdió la solicitud explícita de envío de catálogo.
 *
 * Causa: LeadRegistrationService::createComercialLead tenía lógica EXCLUYENTE
 *   $descripcion = !empty($itemTexts) ? items : conversation_summary
 * Cuando había items, descartaba el resumen.
 *
 * Fix: buildLeadDescripcion() COMBINA todas las fuentes:
 *   items_solicitados + acciones_solicitadas + interes_resumen + summary
 *
 * Estos tests cubren:
 *   1. Combina items + acciones_solicitadas
 *   2. Solo acciones sin items
 *   3. Items + acciones + interes_resumen (sin duplicar)
 *   4. Fallback a conversation_summary cuando no hay nada más
 *   5. Caso vacío
 *   6. structured_data incluye acciones_solicitadas
 *   7. syncComercialLead actualiza la descripción
 */
class Mkt01ChatbotLeadDescriptionTest extends TestCase
{
    /** @var int[] */
    private $createdLeadIds = [];

    /** @var int[] */
    private $createdComercialLeadIds = [];

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

        $this->service = new LeadRegistrationService();
    }

    protected function tearDown(): void
    {
        try {
            if (!empty($this->createdComercialLeadIds)) {
                Lead::whereIn('id', $this->createdComercialLeadIds)->delete();
            }
            if (!empty($this->createdLeadIds)) {
                MarketingChatbotLead::whereIn('id', $this->createdLeadIds)->delete();
            }
        } catch (\Exception $e) {}
        parent::tearDown();
    }

    /*
    |--------------------------------------------------------------------------
    | buildLeadDescripcion — la corrección del bug original
    |--------------------------------------------------------------------------
    */

    /** @test */
    public function descripcion_combina_items_y_acciones_solicitadas()
    {
        $lead = $this->seedChatbotLead();
        $entities = [
            'items_solicitados' => [
                ['item_texto_original' => 'casacas corporativas con logo', 'item_cantidad' => '200'],
            ],
            'acciones_solicitadas' => [
                'Enviar catálogo de Textil al correo del cliente',
            ],
        ];

        $descripcion = $this->service->buildLeadDescripcion($lead, $entities);

        $this->assertStringContainsString('200 casacas corporativas con logo', $descripcion);
        $this->assertStringContainsString('Enviar catálogo de Textil', $descripcion);
        $this->assertStringContainsString('Solicita:', $descripcion);
    }

    /** @test */
    public function descripcion_solo_con_acciones_sin_items()
    {
        $lead = $this->seedChatbotLead();
        $entities = [
            'items_solicitados'   => [],
            'acciones_solicitadas'=> ['Agendar llamada con la ejecutiva'],
        ];

        $descripcion = $this->service->buildLeadDescripcion($lead, $entities);

        $this->assertNotNull($descripcion);
        $this->assertStringContainsString('Agendar llamada', $descripcion);
    }

    /** @test */
    public function descripcion_solo_con_items_sin_acciones()
    {
        $lead = $this->seedChatbotLead();
        $entities = [
            'items_solicitados' => [
                ['item_texto_original' => 'libretas ecológicas', 'item_cantidad' => '50'],
            ],
            'acciones_solicitadas' => [],
        ];

        $descripcion = $this->service->buildLeadDescripcion($lead, $entities);

        $this->assertEquals('50 libretas ecológicas', $descripcion);
    }

    /** @test */
    public function descripcion_incluye_interes_resumen_cuando_no_es_redundante()
    {
        $lead = $this->seedChatbotLead();
        $entities = [
            'items_solicitados' => [
                ['item_texto_original' => 'casacas', 'item_cantidad' => '100'],
            ],
            'acciones_solicitadas' => [],
            'interes_resumen'      => 'Mercadeo para feria de exportación',
        ];

        $descripcion = $this->service->buildLeadDescripcion($lead, $entities);

        $this->assertStringContainsString('100 casacas', $descripcion);
        $this->assertStringContainsString('Mercadeo para feria', $descripcion);
    }

    /** @test */
    public function descripcion_no_duplica_interes_resumen_si_ya_esta_cubierto_por_items()
    {
        $lead = $this->seedChatbotLead();
        $entities = [
            'items_solicitados' => [
                ['item_texto_original' => 'casacas corporativas', 'item_cantidad' => '100'],
            ],
            'acciones_solicitadas' => [],
            // Mismo contenido que los items — no debe repetirse
            'interes_resumen' => 'casacas corporativas para uniforme',
        ];

        $descripcion = $this->service->buildLeadDescripcion($lead, $entities);

        // Debe contener los items pero NO duplicar el interes_resumen
        $this->assertEquals(1, substr_count(mb_strtolower($descripcion), 'casacas corporativas'),
            'No debería duplicarse el texto cubierto por los items');
    }

    /** @test */
    public function descripcion_fallback_a_conversation_summary_si_no_hay_items_ni_acciones()
    {
        $lead = $this->seedChatbotLead([
            'conversation_summary' => 'Cliente preguntó por catálogo general pero no especificó productos.',
        ]);
        $entities = [
            'items_solicitados'   => [],
            'acciones_solicitadas'=> [],
        ];

        $descripcion = $this->service->buildLeadDescripcion($lead, $entities);

        $this->assertStringContainsString('catálogo general', $descripcion);
    }

    /** @test */
    public function descripcion_es_null_si_no_hay_ninguna_fuente()
    {
        $lead = $this->seedChatbotLead(['conversation_summary' => null]);
        $entities = [
            'items_solicitados'   => [],
            'acciones_solicitadas'=> [],
        ];

        $descripcion = $this->service->buildLeadDescripcion($lead, $entities);

        $this->assertNull($descripcion);
    }

    /** @test */
    public function descripcion_caso_real_de_dante_envio_catalogo()
    {
        // Reproducción exacta del caso que reportó Dante:
        //   - Cliente: "200 casacas corporativas con logo"
        //   - Cliente: "enviar catalogo a josegevara@senter.com"
        // Antes del fix: descripción = "200 casacas corporativas con logo"
        // Después del fix: la solicitud de catálogo TAMBIÉN aparece
        $lead = $this->seedChatbotLead([
            'name'  => 'José',
            'email' => 'josegevara@senter.com',
        ]);
        $entities = [
            'items_solicitados' => [
                ['item_texto_original' => 'casacas corporativas con logo', 'item_cantidad' => '200'],
            ],
            'acciones_solicitadas' => [
                'Enviar catálogo de Textil al correo del cliente',
            ],
        ];

        $descripcion = $this->service->buildLeadDescripcion($lead, $entities);

        $this->assertStringContainsString('200 casacas corporativas con logo', $descripcion,
            'La descripción debe seguir teniendo los items (no regresión)');
        $this->assertStringContainsString('Enviar catálogo', $descripcion,
            'La descripción debe incluir la solicitud explícita del cliente (fix del bug)');
    }

    /*
    |--------------------------------------------------------------------------
    | structured_data — verificar que el JSON guarda acciones_solicitadas
    |--------------------------------------------------------------------------
    */

    /** @test */
    public function register_persiste_acciones_solicitadas_en_structured_data()
    {
        $aiResponse = $this->buildAiResponse([
            'nombre_contacto' => 'José',
            'correo'          => 'jose-test-' . uniqid() . '@empresa.com',
            'items_solicitados' => [
                ['item_texto_original' => 'casacas', 'item_cantidad' => '100'],
            ],
            'acciones_solicitadas' => [
                'Enviar catálogo al correo',
                'Agendar llamada',
            ],
        ]);

        $lead = $this->service->registerOrUpdate($aiResponse, 'session-' . uniqid(), 'web');
        $this->assertNotNull($lead);
        $this->createdLeadIds[] = $lead->id;

        $structured = is_array($lead->structured_data) ? $lead->structured_data : json_decode($lead->structured_data, true);
        $this->assertArrayHasKey('acciones_solicitadas', $structured);
        $this->assertCount(2, $structured['acciones_solicitadas']);
        $this->assertContains('Enviar catálogo al correo', $structured['acciones_solicitadas']);
    }

    /*
    |--------------------------------------------------------------------------
    | Lead Comercial (módulo Comercial) — descripción end-to-end
    |--------------------------------------------------------------------------
    */

    /** @test */
    public function lead_comercial_recibe_descripcion_con_items_y_acciones()
    {
        $email = 'comercial-test-' . uniqid() . '@empresa.com';
        $aiResponse = $this->buildAiResponse([
            'nombre_contacto' => 'José',
            'correo'          => $email,
            'items_solicitados' => [
                ['item_texto_original' => 'casacas corporativas con logo', 'item_cantidad' => '200'],
            ],
            'acciones_solicitadas' => [
                'Enviar catálogo de Textil al correo del cliente',
            ],
        ]);

        $lead = $this->service->registerOrUpdate($aiResponse, 'session-' . uniqid(), 'web');
        $this->createdLeadIds[] = $lead->id;

        $comercialLead = Lead::where('email', $email)->latest('id')->first();
        $this->assertNotNull($comercialLead, 'Debería haber creado el lead comercial');
        $this->createdComercialLeadIds[] = $comercialLead->id;

        $this->assertStringContainsString('200 casacas corporativas con logo', $comercialLead->descripcion);
        $this->assertStringContainsString('Enviar catálogo', $comercialLead->descripcion);
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    private function seedChatbotLead(array $overrides = [])
    {
        $lead = MarketingChatbotLead::create(array_merge([
            'name'         => 'Test User',
            'email'        => 'test-' . uniqid() . '@example.com',
            'company_name' => '',
            'source'       => 'marketing_chatbot',
            'status'       => MarketingChatbotLead::STATUS_NEW,
            'channel'      => 'web',
            'session_id'   => 'session-' . uniqid(),
        ], $overrides));
        $this->createdLeadIds[] = $lead->id;
        return $lead;
    }

    private function buildAiResponse(array $overrides = [])
    {
        $entities = array_merge([
            'nombre_contacto'    => null,
            'empresa'            => null,
            'correo'             => null,
            'celular'            => null,
            'items_solicitados'  => [],
            'acciones_solicitadas' => [],
            'interes_resumen'    => null,
        ], $overrides);

        return [
            'reply'              => 'OK',
            'intent'             => 'registrar_para_cotizacion',
            'entities_extracted' => $entities,
            'conversation_state' => [
                'phase'                 => 'registro_cotizacion',
                'has_contact'           => !empty($entities['correo']) || !empty($entities['celular']),
                'has_commercial_intent' => true,
                'has_useful_data'       => true,
            ],
            'actions' => [
                'should_register_lead' => true,
                'ready_to_convert'     => false,
                'escalate_to_human'    => false,
            ],
            'lead_summary' => 'Cliente registrado para cotización.',
        ];
    }
}
