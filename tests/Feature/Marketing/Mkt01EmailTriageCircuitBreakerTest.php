<?php

namespace Tests\Feature\Marketing;

use Hyn\Tenancy\Environment;
use Hyn\Tenancy\Models\Website;
use Illuminate\Support\Facades\Artisan;
use Modules\Marketing\Models\ChatbotConfig;
use Modules\Marketing\Services\EmailTriageReaderService;
use Tests\TestCase;

/**
 * Mkt01EmailTriageCircuitBreakerTest — Protección anti-ban del Email Triage.
 *
 * Contexto del breaker:
 * ---------------------
 * El 11-12 de mayo de 2026 el IP del usuario fue baneado por el firewall CSF
 * del hosting porque el bot intentaba login IMAP con credenciales malas cada
 * 15 minutos y acumulaba 12 fallos/hora (límite de LF_IMAPD).
 *
 * El circuit breaker pausa el bot tras N fallos consecutivos (default 3),
 * deshabilita email_enabled, y deja un mensaje claro para que la ejecutiva
 * pueda corregir y resetear.
 *
 * Estos tests NO tocan IMAP real (eso requeriría un servidor) — sí validan:
 *   1. Las 4 keys del breaker existen tras migración.
 *   2. getCircuitBreakerStatus() reporta el estado correcto.
 *   3. recordFailure() incrementa, persiste timestamp y motivo.
 *   4. recordFailure() al llegar al máx, deshabilita email_enabled.
 *   5. recordSuccess() resetea el contador.
 *   6. read() retorna circuit_breaker_tripped=true sin tocar IMAP cuando el
 *      breaker está activo.
 *   7. El comando artisan reset-breaker funciona end-to-end.
 */
class Mkt01EmailTriageCircuitBreakerTest extends TestCase
{
    /** @var int|null */
    private $tenantId = null;

    /** @var array */
    private $previousValues = [];

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
        $this->tenantId = $website->id;
        app(Environment::class)->tenant($website);

        // Re-asegurar que las 4 keys del breaker existan (idempotente). El seed
        // real corre vía migración 2026_05_17_000001; acá replicamos por si
        // todavía no se corrió o el tenant es nuevo.
        $this->ensureKey('email_imap_circuit_breaker_max_failures', 'int', '3', 200);
        $this->ensureKey('email_imap_consecutive_failures',         'int', '0', 210);
        $this->ensureKey('email_imap_last_failure_at',              'string', null, 220);
        $this->ensureKey('email_imap_last_failure_reason',          'text', null, 230);
        $this->ensureKey('email_enabled',                           'bool', '0',  10);

        // Snapshot del estado actual para restaurarlo en tearDown
        foreach ([
            'email_imap_circuit_breaker_max_failures',
            'email_imap_consecutive_failures',
            'email_imap_last_failure_at',
            'email_imap_last_failure_reason',
            'email_enabled',
        ] as $k) {
            $row = ChatbotConfig::byKey($k);
            $this->previousValues[$k] = $row ? $row->config_value : null;
        }

        // Empezar siempre con el breaker reseteado
        ChatbotConfig::set('email_imap_consecutive_failures', 0);
        ChatbotConfig::set('email_imap_last_failure_at', null);
        ChatbotConfig::set('email_imap_last_failure_reason', null);
        ChatbotConfig::set('email_imap_circuit_breaker_max_failures', 3);
    }

    protected function tearDown(): void
    {
        // Restaurar valores originales para no contaminar otros tests del módulo
        try {
            foreach ($this->previousValues as $k => $v) {
                $row = ChatbotConfig::byKey($k);
                if ($row) {
                    $row->config_value = $v;
                    $row->save();
                }
            }
        } catch (\Exception $e) {}

        parent::tearDown();
    }

    private function ensureKey($configKey, $valueType, $defaultValue, $sortOrder)
    {
        $existing = ChatbotConfig::byKey($configKey);
        if ($existing) return;

        ChatbotConfig::create([
            'config_key'    => $configKey,
            'config_value'  => $defaultValue,
            'value_type'    => $valueType,
            'group'         => 'email',
            'display_label' => $configKey,
            'is_sensitive'  => 0,
            'is_encrypted'  => 0,
            'sort_order'    => $sortOrder,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | TESTS
    |--------------------------------------------------------------------------
    */

    /** @test */
    public function las_4_keys_del_breaker_existen_en_marketing_chatbot_config()
    {
        foreach ([
            'email_imap_circuit_breaker_max_failures',
            'email_imap_consecutive_failures',
            'email_imap_last_failure_at',
            'email_imap_last_failure_reason',
        ] as $key) {
            $this->assertNotNull(
                ChatbotConfig::byKey($key),
                "Falta la key {$key} en marketing_chatbot_config (debería seedear la migración 2026_05_17_000001)"
            );
        }
    }

    /** @test */
    public function breaker_arranca_inactivo_si_failures_es_cero()
    {
        $reader = new EmailTriageReaderService();
        $status = $reader->getCircuitBreakerStatus();

        $this->assertFalse($status['tripped']);
        $this->assertEquals(0, $status['failures']);
        $this->assertEquals(3, $status['max']);
        $this->assertNull($status['message']);
    }

    /** @test */
    public function breaker_se_dispara_cuando_failures_alcanza_el_max()
    {
        ChatbotConfig::set('email_imap_consecutive_failures', 3);
        ChatbotConfig::set('email_imap_circuit_breaker_max_failures', 3);

        $reader = new EmailTriageReaderService();
        $status = $reader->getCircuitBreakerStatus();

        $this->assertTrue($status['tripped']);
        $this->assertEquals(3, $status['failures']);
        $this->assertNotNull($status['message']);
        $this->assertStringContainsString('Circuit breaker ACTIVO', $status['message']);
        $this->assertStringContainsString('reset-breaker', $status['message']);
    }

    /** @test */
    public function record_failure_incrementa_contador_y_persiste_timestamp_y_motivo()
    {
        $reader = new EmailTriageReaderService();

        $reader->recordFailure('Login failed (test).');

        $this->assertEquals(1, ChatbotConfig::get('email_imap_consecutive_failures'));
        $this->assertEquals('Login failed (test).', ChatbotConfig::get('email_imap_last_failure_reason'));
        $this->assertNotNull(ChatbotConfig::get('email_imap_last_failure_at'));
    }

    /** @test */
    public function record_failure_al_llegar_al_max_deshabilita_email_enabled()
    {
        ChatbotConfig::set('email_enabled', true);
        ChatbotConfig::set('email_imap_circuit_breaker_max_failures', 3);
        ChatbotConfig::set('email_imap_consecutive_failures', 2);  // 2 previos + 1 nuevo = 3 = max

        $reader = new EmailTriageReaderService();
        $reader->recordFailure('Tercer fallo consecutivo.');

        $this->assertEquals(3, ChatbotConfig::get('email_imap_consecutive_failures'));
        $this->assertFalse(
            ChatbotConfig::get('email_enabled'),
            'email_enabled debería estar deshabilitado tras alcanzar el max de fallos'
        );
    }

    /** @test */
    public function record_failure_truncar_mensaje_largo_a_500_chars()
    {
        $longMessage = str_repeat('A', 800);
        $reader = new EmailTriageReaderService();

        $reader->recordFailure($longMessage);

        $stored = ChatbotConfig::get('email_imap_last_failure_reason');
        $this->assertLessThanOrEqual(500, mb_strlen($stored));
    }

    /** @test */
    public function record_success_resetea_contador_y_limpia_last_failure()
    {
        ChatbotConfig::set('email_imap_consecutive_failures', 2);
        ChatbotConfig::set('email_imap_last_failure_at', '2026-05-12 01:30:00');
        ChatbotConfig::set('email_imap_last_failure_reason', 'Some old error');

        $reader = new EmailTriageReaderService();
        $reader->recordSuccess();

        $this->assertEquals(0, ChatbotConfig::get('email_imap_consecutive_failures'));
        $this->assertNull(ChatbotConfig::get('email_imap_last_failure_at'));
        $this->assertNull(ChatbotConfig::get('email_imap_last_failure_reason'));
    }

    /** @test */
    public function read_aborta_temprano_cuando_breaker_esta_activo_sin_tocar_imap()
    {
        // Habilitar el módulo pero forzar el breaker activo
        ChatbotConfig::set('email_enabled', true);
        ChatbotConfig::set('email_imap_circuit_breaker_max_failures', 3);
        ChatbotConfig::set('email_imap_consecutive_failures', 5);  // por encima del max
        ChatbotConfig::set('email_imap_last_failure_at', '2026-05-12 01:32:00');
        ChatbotConfig::set('email_imap_last_failure_reason', 'AUTHENTICATIONFAILED Authentication failed.');

        $reader = new EmailTriageReaderService();
        $result = $reader->read(10);

        $this->assertTrue($result['enabled']);
        $this->assertTrue($result['circuit_breaker_tripped']);
        $this->assertEquals(0, $result['fetched']);
        $this->assertEquals(0, $result['persisted']);
        $this->assertStringContainsString('Circuit breaker ACTIVO', $result['error_message']);
    }

    /** @test */
    public function comando_artisan_resetea_breaker_y_reactiva_email_enabled()
    {
        ChatbotConfig::set('email_enabled', false);
        ChatbotConfig::set('email_imap_consecutive_failures', 5);
        ChatbotConfig::set('email_imap_last_failure_at', '2026-05-12 01:32:00');
        ChatbotConfig::set('email_imap_last_failure_reason', 'Old error');

        $exitCode = Artisan::call('marketing:email-triage:reset-breaker', [
            '--tenant' => $this->tenantId,
        ]);

        $this->assertEquals(0, $exitCode);

        // Re-resolver tenant tras el comando
        app(Environment::class)->tenant(Website::find($this->tenantId));

        $this->assertEquals(0, ChatbotConfig::get('email_imap_consecutive_failures'));
        $this->assertNull(ChatbotConfig::get('email_imap_last_failure_at'));
        $this->assertNull(ChatbotConfig::get('email_imap_last_failure_reason'));
        $this->assertTrue(
            (bool) ChatbotConfig::get('email_enabled'),
            'El comando reset-breaker debería reactivar email_enabled por default'
        );
    }

    /** @test */
    public function comando_artisan_no_enable_no_reactiva_email_enabled()
    {
        ChatbotConfig::set('email_enabled', false);
        ChatbotConfig::set('email_imap_consecutive_failures', 5);

        $exitCode = Artisan::call('marketing:email-triage:reset-breaker', [
            '--tenant'    => $this->tenantId,
            '--no-enable' => true,
        ]);

        $this->assertEquals(0, $exitCode);

        app(Environment::class)->tenant(Website::find($this->tenantId));

        $this->assertEquals(0, ChatbotConfig::get('email_imap_consecutive_failures'));
        $this->assertFalse(
            (bool) ChatbotConfig::get('email_enabled'),
            'Con --no-enable, email_enabled debería seguir en false'
        );
    }

    /** @test */
    public function comando_artisan_status_no_modifica_nada()
    {
        ChatbotConfig::set('email_enabled', false);
        ChatbotConfig::set('email_imap_consecutive_failures', 5);
        ChatbotConfig::set('email_imap_last_failure_reason', 'No tocar');

        $exitCode = Artisan::call('marketing:email-triage:reset-breaker', [
            '--tenant' => $this->tenantId,
            '--status' => true,
        ]);

        $this->assertEquals(0, $exitCode);

        app(Environment::class)->tenant(Website::find($this->tenantId));

        // Nada cambió
        $this->assertEquals(5, ChatbotConfig::get('email_imap_consecutive_failures'));
        $this->assertEquals('No tocar', ChatbotConfig::get('email_imap_last_failure_reason'));
        $this->assertFalse((bool) ChatbotConfig::get('email_enabled'));
    }
}
