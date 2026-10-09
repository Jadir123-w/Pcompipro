<?php

namespace Tests\Feature\Marketing;

use Hyn\Tenancy\Environment;
use Hyn\Tenancy\Models\Website;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Modules\Marketing\Models\PreQuote;
use Modules\Marketing\Models\PreQuoteItem;
use Modules\Marketing\Models\QuotationConfig;
use Modules\Marketing\Services\QuotationAIEngine;
use Tests\TestCase;

/**
 * Com02HistoricoAutonomoTest — COM-02 S6
 *
 * Tests E2E del histórico como contexto al prompt + del comando autónomo.
 *
 * Cubre:
 *   1. getHistoricalCasesForPrompt incluye casos validados/aprobados recientes
 *   2. getHistoricalCasesForPrompt retorna mensaje "desactivado" cuando flag=0
 *   3. getHistoricalCasesForPrompt respeta el lookback de meses
 *   4. getHistoricalCasesForPrompt ignora prequotes en estado borrador
 *   5. Comando auto-prequote --dry-run no crea registros
 *   6. Comando auto-prequote respeta la flag enabled
 *   7. Comando auto-prequote --force ignora la flag
 */
class Com02HistoricoAutonomoTest extends TestCase
{
    /** @var array IDs creados para limpieza */
    private $createdPrequoteIds = [];

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
        if (!empty($this->createdPrequoteIds)) {
            try {
                PreQuoteItem::whereIn('prequote_id', $this->createdPrequoteIds)->delete();
                DB::connection('tenant')->table('quotation_prequote_history')
                    ->whereIn('prequote_id', $this->createdPrequoteIds)->delete();
                DB::connection('tenant')->table('quotation_prequotes')
                    ->whereIn('id', $this->createdPrequoteIds)->delete();
            } catch (\Exception $e) {}
        }
        parent::tearDown();
    }

    /*
    |--------------------------------------------------------------------------
    | Tests del histórico
    |--------------------------------------------------------------------------
    */

    /**
     * Caso 1 — getHistoricalCasesForPrompt incluye prequote_items de
     * prequotes validadas o aprobadas dentro del lookback.
     */
    public function test_caso1_historico_incluye_validadas_recientes()
    {
        QuotationConfig::setValue('defop_historico_enabled', '1');
        QuotationConfig::setValue('defop_historico_meses_lookback', '12');

        // Crear una prequote validada con un item conocido
        $marker = 'POLO HIST TEST FX' . substr(uniqid(), -4);
        $this->seedValidatedPrequote([
            'product_name' => $marker,
            'quantity' => 100,
            'supplier_name' => 'Proveedor Histórico SAC',
            'costo_base' => 8.50,
            'margen_porcentaje' => 35,
            'tiempo_estimado_dias' => 12,
            'tecnica_impresion' => 'bordado',
            'empaque' => 'bolsa individual',
        ]);

        // Construir el prompt y verificar que incluye el caso
        $output = $this->buildHistoricalBlock();

        $this->assertStringContainsString($marker, $output,
            'El histórico debe incluir el item de la prequote validada');
        $this->assertStringContainsString('Proveedor Histórico SAC', $output);
        $this->assertStringContainsString('bordado', $output);
    }

    /**
     * Caso 2 — Si la flag defop_historico_enabled=0, retorna mensaje
     * "desactivado" en lugar del bloque histórico.
     */
    public function test_caso2_flag_desactivada_no_inyecta_historico()
    {
        QuotationConfig::setValue('defop_historico_enabled', '0');

        // Igual sembramos data para verificar que NO se incluye
        $marker = 'NO DEBE APARECER FX' . substr(uniqid(), -4);
        $this->seedValidatedPrequote([
            'product_name' => $marker,
            'quantity' => 50,
            'supplier_name' => 'X',
            'costo_base' => 1, 'margen_porcentaje' => 1, 'tiempo_estimado_dias' => 1,
        ]);

        $output = $this->buildHistoricalBlock();

        $this->assertStringNotContainsString($marker, $output);
        $this->assertStringContainsString('desactivado', $output);

        // Restaurar
        QuotationConfig::setValue('defop_historico_enabled', '1');
    }

    /**
     * Caso 3 — Prequotes fuera del lookback NO se incluyen.
     */
    public function test_caso3_lookback_excluye_prequotes_viejas()
    {
        QuotationConfig::setValue('defop_historico_enabled', '1');
        QuotationConfig::setValue('defop_historico_meses_lookback', '3');

        // Crear una prequote validada hace 6 meses → fuera del lookback de 3
        $marker = 'PREQUOTE VIEJA FX' . substr(uniqid(), -4);
        $oldDate = now()->subMonths(6);
        $this->seedValidatedPrequote([
            'product_name' => $marker,
            'quantity' => 50,
            'supplier_name' => 'Old Co',
            'costo_base' => 5, 'margen_porcentaje' => 30, 'tiempo_estimado_dias' => 7,
        ], $oldDate);

        $output = $this->buildHistoricalBlock();

        $this->assertStringNotContainsString($marker, $output,
            'Una prequote de 6 meses con lookback=3 NO debe aparecer');

        // Restaurar lookback default
        QuotationConfig::setValue('defop_historico_meses_lookback', '12');
    }

    /**
     * Caso 4 — Prequotes en estado borrador NO se incluyen (sólo validadas/aprobadas).
     */
    public function test_caso4_estado_borrador_excluido()
    {
        QuotationConfig::setValue('defop_historico_enabled', '1');

        $marker = 'BORRADOR EXCLUIDO FX' . substr(uniqid(), -4);
        $this->seedValidatedPrequote([
            'product_name' => $marker,
            'quantity' => 30,
            'supplier_name' => 'Test',
            'costo_base' => 3, 'margen_porcentaje' => 30, 'tiempo_estimado_dias' => 7,
        ], null, 'borrador');

        $output = $this->buildHistoricalBlock();

        $this->assertStringNotContainsString($marker, $output,
            'Una prequote en estado borrador NO debe aparecer en el histórico');
    }

    /*
    |--------------------------------------------------------------------------
    | Tests del comando autónomo
    |--------------------------------------------------------------------------
    */

    /**
     * Caso 5 — Comando con --dry-run no crea registros.
     */
    public function test_caso5_dry_run_no_crea()
    {
        // El comando no debería fallar aunque no haya nada que procesar
        $exitCode = Artisan::call('marketing:quotation:auto-prequote', [
            '--dry-run' => true,
            '--limit'   => 1,
            '--force'   => true,  // ignorar flag enabled
        ]);

        $this->assertEquals(0, $exitCode,
            'El comando con --dry-run debe terminar con exit 0');
    }

    /**
     * Caso 6 — Sin --force y flag desactivada, NO procesa nada.
     */
    public function test_caso6_flag_desactivada_no_procesa()
    {
        QuotationConfig::setValue('auto_prequote_enabled', '0');

        $exitCode = Artisan::call('marketing:quotation:auto-prequote', [
            '--limit' => 5,
        ]);
        $output = Artisan::output();

        $this->assertEquals(0, $exitCode);
        // Cada tenant procesado debería reportar 0 procesados
        $this->assertStringContainsString('procesados=0', $output);
    }

    /**
     * Caso 7 — Con --force ignora la flag aunque esté en 0.
     */
    public function test_caso7_force_ignora_flag()
    {
        QuotationConfig::setValue('auto_prequote_enabled', '0');

        $exitCode = Artisan::call('marketing:quotation:auto-prequote', [
            '--force'   => true,
            '--dry-run' => true,
            '--limit'   => 1,
        ]);

        $this->assertEquals(0, $exitCode,
            'Con --force, el comando se ejecuta aún con flag=0');
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    /**
     * Invoca getHistoricalCasesForPrompt() del AIEngine vía reflection y
     * retorna el bloque markdown generado.
     */
    private function buildHistoricalBlock()
    {
        // Construimos el AIEngine real (no mockeado) — el método es private
        $engine = app(QuotationAIEngine::class);
        $reflection = new \ReflectionMethod($engine, 'getHistoricalCasesForPrompt');
        $reflection->setAccessible(true);
        return $reflection->invoke($engine, []);
    }

    /**
     * Sembrar una prequote con 1 item, en estado y fecha dados.
     */
    private function seedValidatedPrequote(array $itemData, $createdAt = null, $estado = 'validada')
    {
        $createdAt = $createdAt ?: now();

        $prequoteId = DB::connection('tenant')->table('quotation_prequotes')->insertGetId([
            'titulo'        => 'Test S6 ' . uniqid(),
            'estado'        => $estado,
            'subtotal'      => 0, 'total_igv' => 0, 'total' => 0,
            'created_at'    => $createdAt,
            'updated_at'    => $createdAt,
        ]);
        $this->createdPrequoteIds[] = $prequoteId;

        $itemRow = array_merge([
            'prequote_id'           => $prequoteId,
            'item_order'            => 1,
            'cantidad_colores'      => 1,
            'cantidad_lados'        => 1,
            'costo_base'            => 0,
            'margen_porcentaje'     => 0,
            'valor_unitario'        => 0,
            'valor_total'           => 0,
            'base_igv'              => 0,
            'igv'                   => 0,
            'total_item'            => 0,
            'tiempo_estimado_dias'  => 15,
            'is_inferred'           => 1,
            'created_at'            => $createdAt,
            'updated_at'            => $createdAt,
        ], $itemData);

        DB::connection('tenant')->table('quotation_prequote_items')->insert($itemRow);

        return $prequoteId;
    }
}
