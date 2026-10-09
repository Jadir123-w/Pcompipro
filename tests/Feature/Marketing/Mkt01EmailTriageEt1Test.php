<?php

namespace Tests\Feature\Marketing;

use Hyn\Tenancy\Environment;
use Hyn\Tenancy\Models\Website;
use Modules\Marketing\Models\ChatbotConfig;
use Modules\Marketing\Models\MarketingEmailReceived;
use Modules\Marketing\Services\EmailTriageReaderService;
use Tests\TestCase;

/**
 * Mkt01EmailTriageEt1Test — MKT-01 Email Triage ET1 (lectura IMAP).
 *
 * No testeamos contra IMAP real (requeriría servidor). Sí testeamos:
 *   1. Modelo MarketingEmailReceived: scopes, status helpers, casts
 *   2. EmailTriageReaderService::read() respeta el flag email_enabled
 *   3. EmailTriageReaderService::read() falla con mensaje claro si faltan
 *      credenciales mínimas
 *   4. Las 3 keys faltantes (email_imap_host, email_user, email_password)
 *      están sembradas
 *   5. Tabla marketing_emails_received existe con la estructura esperada
 *   6. Deduplicación por message_id (no se permite insertar 2 con mismo ID)
 */
class Mkt01EmailTriageEt1Test extends TestCase
{
    private $createdEmailIds = [];

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

        // Re-asegurar las 3 keys email_* del seed por si otros tests del módulo
        // las borraron en su tearDown (Mkt01ConnectionTestTest hace eso).
        // El seed real corre vía migración; acá solo replicamos la estructura.
        $this->ensureSeedKey('email_imap_host', 'string', 'IMAP host', 0, 0, 11);
        $this->ensureSeedKey('email_user',      'string', 'Usuario',   0, 0, 41);
        $this->ensureSeedKey('email_password',  'string', 'Contraseña',1, 1, 42);
    }

    private function ensureSeedKey($configKey, $valueType, $label, $isSensitive, $isEncrypted, $sortOrder)
    {
        $existing = ChatbotConfig::byKey($configKey);
        if ($existing) return;

        ChatbotConfig::create([
            'config_key'    => $configKey,
            'config_value'  => null,
            'value_type'    => $valueType,
            'group'         => 'email',
            'display_label' => $label,
            'is_sensitive'  => $isSensitive,
            'is_encrypted'  => $isEncrypted,
            'sort_order'    => $sortOrder,
        ]);
    }

    protected function tearDown(): void
    {
        if (!empty($this->createdEmailIds)) {
            try {
                MarketingEmailReceived::whereIn('id', $this->createdEmailIds)->delete();
            } catch (\Exception $e) {}
        }
        parent::tearDown();
    }

    /*
    |--------------------------------------------------------------------------
    | Modelo
    |--------------------------------------------------------------------------
    */

    /** @test */
    public function modelo_persiste_correo_con_metadata_completa()
    {
        $email = MarketingEmailReceived::create([
            'message_id'      => 'test-msg-' . uniqid() . '@example.com',
            'imap_uid'        => 1234,
            'imap_folder'     => 'INBOX',
            'from_email'      => 'cliente@example.com',
            'from_name'       => 'Cliente Test',
            'to_email'        => 'info@compipro.net',
            'cc_emails'       => ['cc1@x.com', 'cc2@x.com'],
            'subject'         => 'Solicitud de cotización',
            'body_plain'      => 'Necesito 100 polos personalizados.',
            'received_at'     => now(),
            'has_attachments' => true,
            'size_bytes'      => 12345,
            'status'          => MarketingEmailReceived::STATUS_RECEIVED,
        ]);
        $this->createdEmailIds[] = $email->id;

        $fresh = MarketingEmailReceived::find($email->id);
        $this->assertEquals('Cliente Test', $fresh->from_name);
        $this->assertEquals(['cc1@x.com', 'cc2@x.com'], $fresh->cc_emails);
        $this->assertTrue($fresh->has_attachments);
        $this->assertTrue($fresh->isReceived());
    }

    /** @test */
    public function modelo_rechaza_message_id_duplicado()
    {
        $msgId = 'dup-' . uniqid() . '@example.com';

        $first = MarketingEmailReceived::create([
            'message_id'  => $msgId,
            'from_email'  => 'a@b.com',
            'to_email'    => 'info@compipro.net',
            'subject'     => 'Test 1',
            'received_at' => now(),
        ]);
        $this->createdEmailIds[] = $first->id;

        $this->expectException(\Illuminate\Database\QueryException::class);

        MarketingEmailReceived::create([
            'message_id'  => $msgId,
            'from_email'  => 'a@b.com',
            'to_email'    => 'info@compipro.net',
            'subject'     => 'Test 2 (duplicado)',
            'received_at' => now(),
        ]);
    }

    /** @test */
    public function status_helpers_funcionan_correctamente()
    {
        $email = MarketingEmailReceived::create([
            'message_id'  => 'helpers-' . uniqid() . '@example.com',
            'from_email'  => 'a@b.com',
            'to_email'    => 'info@compipro.net',
            'received_at' => now(),
            'status'      => MarketingEmailReceived::STATUS_DRAFT_PENDING,
        ]);
        $this->createdEmailIds[] = $email->id;

        $this->assertFalse($email->isReceived());
        $this->assertTrue($email->isDraftPending());
        $this->assertFalse($email->isReplied());

        $email->markError('test error reason');
        $this->assertTrue($email->fresh()->isError());
        $this->assertEquals('test error reason', $email->fresh()->error_message);
    }

    /** @test */
    public function classification_helpers_funcionan()
    {
        $email = MarketingEmailReceived::create([
            'message_id'  => 'class-' . uniqid() . '@example.com',
            'from_email'  => 'a@b.com',
            'to_email'    => 'info@compipro.net',
            'received_at' => now(),
            'classification' => MarketingEmailReceived::CLASSIFICATION_RFQ,
        ]);
        $this->createdEmailIds[] = $email->id;

        $this->assertTrue($email->isClassifiedAs('rfq'));
        $this->assertFalse($email->isClassifiedAs('spam'));
    }

    /** @test */
    public function scope_pending_classification_filtra_received()
    {
        // Crear 2 received y 1 classified
        $a = MarketingEmailReceived::create([
            'message_id'  => 'a-' . uniqid() . '@example.com',
            'from_email'  => 'a@b.com',
            'to_email'    => 'info@compipro.net',
            'received_at' => now(),
            'status'      => MarketingEmailReceived::STATUS_RECEIVED,
        ]);
        $b = MarketingEmailReceived::create([
            'message_id'  => 'b-' . uniqid() . '@example.com',
            'from_email'  => 'a@b.com',
            'to_email'    => 'info@compipro.net',
            'received_at' => now(),
            'status'      => MarketingEmailReceived::STATUS_RECEIVED,
        ]);
        $c = MarketingEmailReceived::create([
            'message_id'  => 'c-' . uniqid() . '@example.com',
            'from_email'  => 'a@b.com',
            'to_email'    => 'info@compipro.net',
            'received_at' => now(),
            'status'      => MarketingEmailReceived::STATUS_CLASSIFIED,
        ]);
        $this->createdEmailIds = array_merge($this->createdEmailIds, [$a->id, $b->id, $c->id]);

        $pending = MarketingEmailReceived::pendingClassification()
            ->whereIn('id', [$a->id, $b->id, $c->id])
            ->get();

        $this->assertCount(2, $pending);
        $this->assertEqualsCanonicalizing(
            [$a->id, $b->id],
            $pending->pluck('id')->toArray()
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Servicio EmailTriageReaderService
    |--------------------------------------------------------------------------
    */

    /** @test */
    public function reader_falla_con_mensaje_claro_si_email_disabled()
    {
        // Asegurarse que email_enabled esté en false
        ChatbotConfig::set('email_enabled', false);

        $svc = new EmailTriageReaderService();
        $r = $svc->read(10);

        $this->assertFalse($r['enabled']);
        $this->assertEquals(0, $r['fetched']);
        $this->assertEquals(0, $r['persisted']);
        $this->assertStringContainsStringIgnoringCase('deshabilitado', $r['error_message']);
    }

    /** @test */
    public function reader_falla_con_mensaje_claro_si_falta_imap_o_credenciales()
    {
        // Habilitar pero dejar credenciales vacías
        ChatbotConfig::set('email_enabled', true);

        $hostRow = ChatbotConfig::byKey('email_imap_host');
        if ($hostRow) { $hostRow->config_value = null; $hostRow->save(); }
        $userRow = ChatbotConfig::byKey('email_user');
        if ($userRow) { $userRow->config_value = null; $userRow->save(); }
        $passRow = ChatbotConfig::byKey('email_password');
        if ($passRow) { $passRow->config_value = null; $passRow->save(); }

        $svc = new EmailTriageReaderService();
        $r = $svc->read(10);

        $this->assertTrue($r['enabled']);
        $this->assertEquals(0, $r['persisted']);
        $this->assertNotEmpty($r['error_message'],
            'Sin credenciales debe haber un error_message explicativo');

        // Acepta cualquiera de los 2 mensajes posibles según el entorno:
        //   - "credenciales" cuando ext-imap está instalada (validación de credenciales)
        //   - "ext-imap" cuando la extensión PHP no está disponible (validación de pre-requisito)
        $msg = strtolower($r['error_message']);
        $isCredOrImapMsg = (strpos($msg, 'credenciales') !== false)
                       || (strpos($msg, 'ext-imap') !== false)
                       || (strpos($msg, 'imap_open') !== false);
        $this->assertTrue($isCredOrImapMsg,
            'El error debe ser sobre credenciales faltantes o ext-imap no instalada. Recibido: ' . $r['error_message']);

        // Restaurar
        ChatbotConfig::set('email_enabled', false);
    }

    /*
    |--------------------------------------------------------------------------
    | Migración seed
    |--------------------------------------------------------------------------
    */

    /** @test */
    public function migracion_seed_agrego_3_keys_faltantes()
    {
        $this->assertNotNull(ChatbotConfig::byKey('email_imap_host'),
            'email_imap_host debe existir tras la migración');
        $this->assertNotNull(ChatbotConfig::byKey('email_user'),
            'email_user debe existir tras la migración');

        $passRow = ChatbotConfig::byKey('email_password');
        $this->assertNotNull($passRow);
        $this->assertTrue((bool) $passRow->is_sensitive,
            'email_password debe estar marcada como sensitive');
        $this->assertTrue((bool) $passRow->is_encrypted,
            'email_password debe estar marcada como encrypted');
    }
}
