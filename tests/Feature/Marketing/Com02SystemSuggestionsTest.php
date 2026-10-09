<?php

namespace Tests\Feature\Marketing;

use Hyn\Tenancy\Environment;
use Hyn\Tenancy\Models\Website;
use Illuminate\Support\Facades\DB;
use Modules\Marketing\Models\PreQuoteItem;
use Modules\Marketing\Models\QuotationConfig;
use Modules\Marketing\Models\QuotationSystemSuggestion;
use Modules\Marketing\Services\QuotationPatternAnalyzer;
use Tests\TestCase;

/**
 * Com02SystemSuggestionsTest — COM-02 S4
 *
 * Cubre el QuotationPatternAnalyzer + ciclo de vida de suggestions.
 *
 * Casos:
 *   1. (retirado en COM08 — el patrón de tiempo se desactivó: tiempo es texto libre)
 *   2. analyzer NO detecta técnica cuando hay poca evidencia (<threshold)
 *   3. analyzer detecta patrón de técnica con frecuencia >70%
 *   4. analyzer NO duplica sugerencias: actualiza la pending existente
 *   5. analyzer respeta sugerencias ya applied (no las re-genera)
 *   6. markApplied actualiza estado + applied_by + applied_at
 *   7. markRejected guarda razón
 *   8. snoozeFor pone snoozed_until en N días
 *   9. scope pending() incluye snoozed con snoozed_until vencido
 */
class Com02SystemSuggestionsTest extends TestCase
{
    private $createdSuggestionIds = [];
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

        // Asegurar threshold conocido para los tests
        QuotationConfig::setValue('defop_pattern_min_evidence', '5');
        QuotationConfig::setValue('defop_historico_meses_lookback', '12');
        QuotationConfig::setValue('defop_tiempo_textil_dias', '15');
        QuotationConfig::setValue('defop_tecnica_textil', 'serigrafía');
    }

    protected function tearDown(): void
    {
        if (!empty($this->createdSuggestionIds)) {
            try {
                QuotationSystemSuggestion::whereIn('id', $this->createdSuggestionIds)->delete();
            } catch (\Exception $e) {}
        }
        if (!empty($this->createdPrequoteIds)) {
            try {
                PreQuoteItem::whereIn('prequote_id', $this->createdPrequoteIds)->delete();
                DB::connection('tenant')->table('quotation_prequotes')
                    ->whereIn('id', $this->createdPrequoteIds)->delete();
            } catch (\Exception $e) {}
        }
        // Limpiar sugerencias creadas por el analyzer indirectamente
        try {
            QuotationSystemSuggestion::where('target_config_key', 'LIKE', 'defop_%')
                ->where('created_at', '>=', now()->subMinutes(10))
                ->delete();
        } catch (\Exception $e) {}

        parent::tearDown();
    }

    /*
    |--------------------------------------------------------------------------
    | Tests del analyzer
    |--------------------------------------------------------------------------
    */

    /*
     * Caso 1 — RETIRADO en COM08.
     *
     * Antes verificaba el patrón "tiempo por categoría" (mediana de
     * tiempo_estimado_dias). Ese patrón se desactivó: el campo pasó a texto
     * libre y la mediana numérica dejó de aplicar. La detección de patrones
     * queda cubierta por el caso 3 (técnica); la idempotencia y el respeto a
     * sugerencias ya decididas, por los casos 4 y 5 (también sobre técnica).
     */

    /**
     * Caso 2 — Si hay menos de threshold items → NO genera sugerencia.
     * (Sobre el patrón de técnica, que sigue vigente tras COM08.)
     */
    public function test_caso2_no_genera_con_poca_evidencia()
    {
        // Solo 3 items textiles con técnica "bordado" (threshold es 5)
        for ($i = 0; $i < 3; $i++) {
            $this->seedValidatedItem([
                'product_name'      => 'camiseta poca evidencia S4 ' . $i,
                'tecnica_impresion' => 'bordado',
                'margen_porcentaje' => 30,
            ]);
        }

        $analyzer = app(QuotationPatternAnalyzer::class);
        $analyzer->analyze();

        $count = QuotationSystemSuggestion::ofTargetKey('defop_tecnica_textil')->count();
        $this->assertEquals(0, $count, 'No debe generar sugerencia con <5 items');
    }

    /**
     * Caso 3 — Patrón de técnica: 6 items textiles con técnica="bordado"
     * cuando el default es "serigrafía" → genera sugerencia.
     */
    public function test_caso3_analyzer_detecta_patron_tecnica()
    {
        for ($i = 0; $i < 6; $i++) {
            $this->seedValidatedItem([
                'product_name'      => 'camiseta tecnica test S4 ' . $i,
                'tecnica_impresion' => 'bordado',
                'margen_porcentaje' => 30,
            ]);
        }

        $analyzer = app(QuotationPatternAnalyzer::class);
        $analyzer->analyze();

        $sug = QuotationSystemSuggestion::ofTargetKey('defop_tecnica_textil')
            ->where('status', 'pending')
            ->first();

        $this->assertNotNull($sug);
        $this->assertEquals('bordado', $sug->suggested_value);
        $this->assertEquals('serigrafía', $sug->current_value);
    }

    /**
     * Caso 4 — Idempotencia: correr el analyzer 2 veces NO crea duplicados;
     * actualiza la sugerencia pending existente.
     */
    public function test_caso4_analyzer_no_duplica()
    {
        for ($i = 0; $i < 6; $i++) {
            $this->seedValidatedItem([
                'product_name'      => 'camiseta idempotente S4 ' . $i,
                'tecnica_impresion' => 'bordado',
                'margen_porcentaje' => 30,
            ]);
        }

        $analyzer = app(QuotationPatternAnalyzer::class);
        $analyzer->analyze();
        $countAfter1 = QuotationSystemSuggestion::ofTargetKey('defop_tecnica_textil')->count();

        $analyzer->analyze();
        $countAfter2 = QuotationSystemSuggestion::ofTargetKey('defop_tecnica_textil')->count();

        $this->assertEquals($countAfter1, $countAfter2,
            'Correr el analyzer dos veces NO debe crear duplicados');
    }

    /**
     * Caso 5 — Una sugerencia ya applied no se re-genera.
     */
    public function test_caso5_no_regenera_applied()
    {
        // Crear sugerencia applied manualmente (técnica textil = bordado)
        $existing = QuotationSystemSuggestion::create([
            'type'              => QuotationSystemSuggestion::TYPE_TECNICA_CATEGORIA,
            'category'          => 'textil',
            'target_config_key' => 'defop_tecnica_textil',
            'current_value'     => 'serigrafía',
            'suggested_value'   => 'bordado',
            'evidence_count'    => 7,
            'confidence'        => 0.85,
            'reasoning'         => 'test pre-applied',
            'status'            => 'applied',
            'applied_at'        => now(),
        ]);
        $this->createdSuggestionIds[] = $existing->id;

        // Sembrar items que producirían el mismo patrón (bordado)
        for ($i = 0; $i < 6; $i++) {
            $this->seedValidatedItem([
                'product_name'      => 'camiseta not-regen S4 ' . $i,
                'tecnica_impresion' => 'bordado',
                'margen_porcentaje' => 30,
            ]);
        }

        $analyzer = app(QuotationPatternAnalyzer::class);
        $analyzer->analyze();

        // No debe haber NUEVA sugerencia con el mismo target+suggested_value
        $count = QuotationSystemSuggestion::ofTargetKey('defop_tecnica_textil')
            ->where('suggested_value', 'bordado')
            ->count();

        $this->assertEquals(1, $count,
            'No debe duplicar una sugerencia ya applied con el mismo suggested_value');
    }

    /*
    |--------------------------------------------------------------------------
    | Tests del modelo
    |--------------------------------------------------------------------------
    */

    /**
     * Caso 6 — markApplied setea status, applied_by, applied_at.
     */
    public function test_caso6_mark_applied()
    {
        $sug = $this->createPendingSuggestion();
        $sug->markApplied(99);

        $sug = $sug->fresh();
        $this->assertEquals('applied', $sug->status);
        $this->assertEquals(99, $sug->applied_by_user_id);
        $this->assertNotNull($sug->applied_at);
        $this->assertTrue($sug->isTerminal());
    }

    /**
     * Caso 7 — markRejected guarda razón.
     */
    public function test_caso7_mark_rejected()
    {
        $sug = $this->createPendingSuggestion();
        $sug->markRejected(7, 'No aplica para nuestro mercado');

        $sug = $sug->fresh();
        $this->assertEquals('rejected', $sug->status);
        $this->assertEquals('No aplica para nuestro mercado', $sug->rejection_reason);
        $this->assertTrue($sug->isTerminal());
    }

    /**
     * Caso 8 — snoozeFor setea snoozed_until N días.
     */
    public function test_caso8_snooze()
    {
        $sug = $this->createPendingSuggestion();
        $sug->snoozeFor(45);

        $sug = $sug->fresh();
        $this->assertEquals('snoozed', $sug->status);
        $this->assertNotNull($sug->snoozed_until);
        $this->assertTrue($sug->snoozed_until->diffInDays(now()) >= 44);
    }

    /**
     * Caso 9 — scope pending() incluye snoozed con plazo vencido.
     */
    public function test_caso9_scope_pending_incluye_snoozed_vencido()
    {
        $sugFuture = $this->createPendingSuggestion();
        $sugFuture->snoozeFor(30);  // venc futuro

        $sugPast = $this->createPendingSuggestion();
        $sugPast->update([
            'status'        => 'snoozed',
            'snoozed_until' => now()->subDays(5), // ya venció
        ]);

        $pendingIds = QuotationSystemSuggestion::pending()
            ->whereIn('id', [$sugFuture->id, $sugPast->id])
            ->pluck('id')->all();

        $this->assertContains($sugPast->id, $pendingIds,
            'Snoozed con plazo vencido debe aparecer en pending()');
        $this->assertNotContains($sugFuture->id, $pendingIds,
            'Snoozed con plazo futuro NO debe aparecer en pending()');
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    private function createPendingSuggestion()
    {
        $sug = QuotationSystemSuggestion::create([
            'type'              => 'tiempo_categoria',
            'category'          => 'textil',
            'target_config_key' => 'defop_tiempo_textil_dias',
            'current_value'     => '15',
            'suggested_value'   => '20',
            'evidence_count'    => 6,
            'confidence'        => 0.8,
            'reasoning'         => 'test sugerencia',
            'status'            => 'pending',
        ]);
        $this->createdSuggestionIds[] = $sug->id;
        return $sug;
    }

    private function seedValidatedItem(array $itemData)
    {
        $prequoteId = DB::connection('tenant')->table('quotation_prequotes')->insertGetId([
            'titulo'    => 'Test S4 ' . uniqid(),
            'estado'    => 'validada',
            'subtotal'  => 0, 'total_igv' => 0, 'total' => 0,
            'created_at'=> now(), 'updated_at' => now(),
        ]);
        $this->createdPrequoteIds[] = $prequoteId;

        $row = array_merge([
            'prequote_id'           => $prequoteId,
            'item_order'            => 1,
            'quantity'              => 100,
            'cantidad_colores'      => 1,
            'cantidad_lados'        => 1,
            'costo_base'            => 0,
            'margen_porcentaje'     => 30,
            'valor_unitario'        => 0,
            'valor_total'           => 0,
            'base_igv'              => 0,
            'igv'                   => 0,
            'total_item'            => 0,
            'tiempo_estimado_dias'  => 15,
            'is_inferred'           => 1,
            'created_at'            => now(),
            'updated_at'            => now(),
        ], $itemData);

        DB::connection('tenant')->table('quotation_prequote_items')->insert($row);
    }
}
