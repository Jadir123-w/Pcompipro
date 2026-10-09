<?php

namespace Tests\Feature\Marketing;

use Hyn\Tenancy\Environment;
use Hyn\Tenancy\Models\Website;
use Modules\Marketing\Models\WhatsAppMessageLog;
use Tests\TestCase;

/**
 * Mkt01WhatsAppIdempotencyTest — dedup de reintentos de Meta (Tarea 2).
 *
 * WhatsAppMessageLog::isInboundProcessed() permite ignorar un webhook que Meta
 * reenvía con el mismo message_id, para que el agente (sobre todo en modo
 * Automatizado) no responda dos veces.
 */
class Mkt01WhatsAppIdempotencyTest extends TestCase
{
    /** @var array IDs de logs creados para limpiar */
    private $cleanupIds = [];

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
            if (!empty($this->cleanupIds)) {
                WhatsAppMessageLog::whereIn('id', $this->cleanupIds)->delete();
            }
        } catch (\Exception $e) {}
        parent::tearDown();
    }

    /** @test */
    public function un_inbound_ya_registrado_se_detecta_como_procesado()
    {
        $mid = 'wamid.TEST-' . uniqid();
        $row = WhatsAppMessageLog::logInbound([
            'session_id'      => 'wa_51999test',
            'wa_id'           => '51999test',
            'contact_name'    => 'Test',
            'message_text'    => 'hola',
            'meta_message_id' => $mid,
        ]);
        $this->cleanupIds[] = $row->id;

        $this->assertTrue(WhatsAppMessageLog::isInboundProcessed($mid),
            'Un message_id ya registrado como inbound debe detectarse como procesado');
    }

    /** @test */
    public function un_message_id_nuevo_no_esta_procesado()
    {
        $this->assertFalse(
            WhatsAppMessageLog::isInboundProcessed('wamid.NOEXISTE-' . uniqid()),
            'Un message_id que nunca llegó no debe considerarse procesado'
        );
    }

    /** @test */
    public function message_id_vacio_o_nulo_no_esta_procesado()
    {
        $this->assertFalse(WhatsAppMessageLog::isInboundProcessed(''));
        $this->assertFalse(WhatsAppMessageLog::isInboundProcessed(null));
    }

    /** @test */
    public function un_outbound_con_ese_id_no_cuenta_como_inbound_procesado()
    {
        // Solo los inbound cuentan para dedup del reintento del webhook.
        $mid = 'wamid.OUT-' . uniqid();
        $row = WhatsAppMessageLog::logOutbound([
            'session_id'      => 'wa_51999test',
            'wa_id'           => '51999test',
            'message_text'    => 'respuesta',
            'meta_message_id' => $mid,
        ]);
        $this->cleanupIds[] = $row->id;

        $this->assertFalse(WhatsAppMessageLog::isInboundProcessed($mid),
            'Un outbound con ese id NO debe contar como inbound ya procesado');
    }
}
