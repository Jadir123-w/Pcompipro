<?php

namespace Tests\Feature\Marketing;

use Hyn\Tenancy\Environment;
use Hyn\Tenancy\Models\Website;
use Illuminate\Support\Facades\DB;
use Modules\Marketing\Models\PreQuote;
use Modules\Marketing\Models\PreQuoteItem;
use Modules\Marketing\Models\PreQuoteHistory;
use Modules\Marketing\Models\QuotationConfig;
use Modules\Marketing\Services\QuotationAIEngine;
use Modules\Marketing\Services\QuotationCalculationEngine;
use Modules\Marketing\Services\QuotationOrchestrator;
use Tests\TestCase;

/**
 * Com02OrchestratorE2ETest — COM-02 S5
 *
 * Tests end-to-end del flujo completo de cotización:
 *
 *   text → AIEngine (mockeado) → Orchestrator → MarginService → ProductMatching
 *        → CalculationEngine → PreQuote + PreQuoteItems persistidos
 *
 * Solo se mockea AIEngine::interpretRequirement() para retornar respuestas
 * canned. Todo el resto del orchestrator y los servicios subyacentes
 * (matching, defaults, cálculo, persistencia) corre real.
 *
 * Cubre 10 casos del playbook:
 *   1. Texto simple un producto → 1 item creado correctamente
 *   2. Multi-producto → N items separados (sec 13)
 *   3. Herencia de atributos compartidos (sec 14)
 *   4. Defaults colores=1, lados=1 cuando el LLM no los infiere
 *   5. Default categoría-aware tiempo (textil=15 vs impresos=7) (S3)
 *   6. Cálculo correcto: cantidad * costo_base * (1+margen) + IGV
 *   7. Recalculate al editar costo_base actualiza totales
 *   8. Match exacto del producto setea item_id
 *   9. Match ambiguo marca needs_product_review=true
 *  10. Texto vacío de productos → prequote con 0 items, warning persistido
 */
class Com02OrchestratorE2ETest extends TestCase
{
    /** @var QuotationOrchestrator */
    private $orchestrator;

    /** @var \Mockery\MockInterface */
    private $aiMock;

    /** @var array  IDs creados para limpieza */
    private $createdItemIds = [];
    private $createdPrequoteIds = [];

    protected function setUp(): void
    {
        parent::setUp();

        // ── DB override ──
        config(['database.connections.system.database' => 'dberp']);
        config(['database.connections.mysql.database'  => 'dberp']);
        \Illuminate\Support\Facades\DB::purge('system');
        \Illuminate\Support\Facades\DB::purge('mysql');

        $website = Website::first();
        if (!$website) {
            $this->markTestSkipped('Sin tenants registrados');
        }
        app(Environment::class)->tenant($website);

        // Mock AIEngine
        $this->aiMock = \Mockery::mock(QuotationAIEngine::class);
        $this->app->instance(QuotationAIEngine::class, $this->aiMock);

        // Resolver el orchestrator (toma el mock automáticamente)
        $this->orchestrator = app(QuotationOrchestrator::class);

        // Sembrar 2 items en el catálogo para los tests de matching
        $this->createdItemIds = $this->seedCatalog();
    }

    protected function tearDown(): void
    {
        // Limpiar prequotes y sus items en cascada
        if (!empty($this->createdPrequoteIds)) {
            try {
                PreQuoteItem::whereIn('prequote_id', $this->createdPrequoteIds)->delete();
                PreQuoteHistory::whereIn('prequote_id', $this->createdPrequoteIds)->delete();
                DB::connection('tenant')->table('quotation_prequotes')
                    ->whereIn('id', $this->createdPrequoteIds)->delete();
            } catch (\Exception $e) {}
        }
        if (!empty($this->createdItemIds)) {
            try {
                DB::connection('tenant')->table('items')
                    ->whereIn('id', $this->createdItemIds)->delete();
            } catch (\Exception $e) {}
        }

        \Mockery::close();
        parent::tearDown();
    }

    /*
    |--------------------------------------------------------------------------
    | Tests
    |--------------------------------------------------------------------------
    */

    /**
     * Caso 1 — Texto simple con un solo producto. El orchestrator crea 1
     * item con product_name + quantity correctos.
     */
    public function test_caso1_texto_simple_un_producto()
    {
        $this->mockAi([
            'interpretation_summary' => 'Pedido de 100 mochilas',
            'total_items_detected'   => 1,
            'items' => [
                [
                    'item_order'        => 1,
                    'product_name'      => 'mochila escolar test e2e',
                    'quantity'          => 100,
                    'category'          => 'Textil',
                ],
            ],
            'shared_attributes_applied' => [],
            'warnings' => [],
        ]);

        $prequote = $this->orchestrator->generateFromText('Necesito 100 mochilas escolares', []);
        $this->createdPrequoteIds[] = $prequote->id;

        $this->assertNotNull($prequote);
        $this->assertEquals('borrador', $prequote->estado);
        $this->assertCount(1, $prequote->items);

        $item = $prequote->items->first();
        $this->assertEquals('mochila escolar test e2e', $item->product_name);
        $this->assertEquals(100, $item->quantity);
    }

    /**
     * Caso 2 — Multi-producto: 2 items separados (sec 13).
     */
    public function test_caso2_multi_producto_separados_en_items()
    {
        $this->mockAi([
            'total_items_detected' => 2,
            'items' => [
                ['item_order' => 1, 'product_name' => 'libreta a4 test e2e', 'quantity' => 200, 'category' => 'Impresos'],
                ['item_order' => 2, 'product_name' => 'lapicero metalico', 'quantity' => 150, 'category' => 'Otros'],
            ],
            'shared_attributes_applied' => [],
            'warnings' => [],
        ]);

        $prequote = $this->orchestrator->generateFromText('200 libretas A4 y 150 lapiceros', []);
        $this->createdPrequoteIds[] = $prequote->id;

        $this->assertCount(2, $prequote->items);
        $itemsByName = $prequote->items->keyBy('product_name');
        $this->assertEquals(200, $itemsByName['libreta a4 test e2e']->quantity);
        $this->assertEquals(150, $itemsByName['lapicero metalico']->quantity);
    }

    /**
     * Caso 3 — Herencia de atributos compartidos (sec 14).
     * El LLM ya aplica la herencia y nos pasa cada item con su personalizacion;
     * verificamos que el orchestrator persiste correctamente.
     */
    public function test_caso3_herencia_de_atributos_personalizacion()
    {
        $this->mockAi([
            'total_items_detected' => 2,
            'items' => [
                [
                    'item_order' => 1, 'product_name' => 'mochila eco', 'quantity' => 100,
                    'category' => 'Textil', 'personalizacion' => 'logo de la empresa',
                ],
                [
                    'item_order' => 2, 'product_name' => 'termo metalico', 'quantity' => 100,
                    'category' => 'Otros', 'personalizacion' => 'logo de la empresa',
                ],
            ],
            'shared_attributes_applied' => ['personalizacion: logo de la empresa'],
            'warnings' => [],
        ]);

        $prequote = $this->orchestrator->generateFromText(
            '100 mochilas y 100 termos personalizados con el logo de la empresa', []
        );
        $this->createdPrequoteIds[] = $prequote->id;

        $this->assertCount(2, $prequote->items);
        foreach ($prequote->items as $item) {
            $this->assertEquals('logo de la empresa', $item->personalizacion,
                'Ítem ' . $item->product_name . ' debe heredar la personalización compartida');
        }
    }

    /**
     * Caso 4 — Defaults colores=1, lados=1 cuando el LLM no los infiere.
     */
    public function test_caso4_defaults_colores_lados()
    {
        $this->mockAi([
            'total_items_detected' => 1,
            'items' => [
                ['item_order' => 1, 'product_name' => 'libreta default test', 'quantity' => 50, 'category' => 'Impresos'],
            ],
            'shared_attributes_applied' => [],
            'warnings' => [],
        ]);

        $prequote = $this->orchestrator->generateFromText('50 libretas', []);
        $this->createdPrequoteIds[] = $prequote->id;

        $item = $prequote->items->first();
        $this->assertEquals(1, $item->cantidad_colores, 'Default colores debe ser 1');
        $this->assertEquals(1, $item->cantidad_lados,    'Default lados debe ser 1');
    }

    /**
     * Caso 5 — Default categoría-aware: tiempo de textil != tiempo de impresos.
     * Verifica que la integración S3 (defop_*) funciona en el orchestrator.
     */
    public function test_caso5_default_categoria_aware_tiempo()
    {
        // Forzar valores conocidos para este test
        QuotationConfig::setValue('defop_tiempo_textil_dias', '14');
        QuotationConfig::setValue('defop_tiempo_impresos_dias', '6');

        $this->mockAi([
            'total_items_detected' => 2,
            'items' => [
                ['item_order' => 1, 'product_name' => 'polo test', 'quantity' => 50, 'category' => 'Textil'],
                ['item_order' => 2, 'product_name' => 'agenda test', 'quantity' => 80, 'category' => 'Impresos'],
            ],
            'shared_attributes_applied' => [],
            'warnings' => [],
        ]);

        $prequote = $this->orchestrator->generateFromText('50 polos y 80 agendas', []);
        $this->createdPrequoteIds[] = $prequote->id;

        $byName = $prequote->items->keyBy('product_name');
        // COM08 — el tiempo es texto: el default por categoría se renderiza como
        // "{n} días útiles" editable.
        $this->assertEquals('14 días útiles', $byName['polo test']->tiempo_estimado_dias,
            'Polo (textil) debe usar defop_tiempo_textil_dias como texto');
        $this->assertEquals('6 días útiles', $byName['agenda test']->tiempo_estimado_dias,
            'Agenda (impresos) debe usar defop_tiempo_impresos_dias como texto');

        // Restaurar defaults
        QuotationConfig::setValue('defop_tiempo_textil_dias', '15');
        QuotationConfig::setValue('defop_tiempo_impresos_dias', '7');
    }

    /**
     * Caso 6 — Cálculo correcto: cantidad * costo_base * (1+margen) + IGV.
     */
    public function test_caso6_calculo_correcto_con_margen_e_igv()
    {
        $this->mockAi([
            'total_items_detected' => 1,
            'items' => [[
                'item_order' => 1, 'product_name' => 'producto calculo', 'quantity' => 10, 'category' => 'Otros',
            ]],
            'shared_attributes_applied' => [], 'warnings' => [],
        ]);

        $prequote = $this->orchestrator->generateFromText('10 productos calculo', []);
        $this->createdPrequoteIds[] = $prequote->id;

        $item = $prequote->items->first();

        // Setear valores conocidos manualmente y recalcular
        $item->update([
            'costo_base'        => 10.00,
            'costos_adicionales' => null,
            'margen_porcentaje' => 50.00,
        ]);
        $calc = app(QuotationCalculationEngine::class);
        $calc->recalculateItem($item);

        $item = $item->fresh();
        // Esperado con el motor de costeo ampliado (Fase A) — incluye SCTR 1.5%:
        //   T   = 10 × (1 + 0.50)        = 15      (precio producto unitario)
        //   TP  = 15 × 10                = 150     (sin costos fijos en este caso)
        //   SCTR = 1.5% × 150            = 2.25
        //   Total sin IGV = 150 + 2.25   = 152.25
        //   valor_unitario = 152.25 / 10 = 15.23  (costo unit sin IGV)
        //   IGV = 152.25 × 0.18          = 27.41
        //   total_item = 152.25 + 27.41  = 179.66
        $this->assertEqualsWithDelta(15.23, (float) $item->valor_unitario, 0.01);
        $this->assertEqualsWithDelta(152.25, (float) $item->valor_total, 0.01);
        $this->assertEqualsWithDelta(27.41,  (float) $item->igv, 0.02);
        $this->assertEqualsWithDelta(179.66, (float) $item->total_item, 0.02);
    }

    /**
     * Caso 7 — Recalculate al editar costo_base actualiza los totales.
     */
    public function test_caso7_recalculate_al_editar_costo_base()
    {
        $this->mockAi([
            'total_items_detected' => 1,
            'items' => [[
                'item_order' => 1, 'product_name' => 'recalc test', 'quantity' => 5, 'category' => 'Otros',
            ]],
            'shared_attributes_applied' => [], 'warnings' => [],
        ]);

        $prequote = $this->orchestrator->generateFromText('5 productos', []);
        $this->createdPrequoteIds[] = $prequote->id;

        $item = $prequote->items->first();
        $item->update(['costo_base' => 20.00, 'margen_porcentaje' => 25.00]);

        $calc = app(QuotationCalculationEngine::class);
        $calc->recalculateItem($item);

        $totalBefore = (float) $item->fresh()->total_item;

        // Cambiar costo_base y volver a calcular
        $item->update(['costo_base' => 30.00]);
        $calc->recalculateItem($item);

        $totalAfter = (float) $item->fresh()->total_item;

        $this->assertGreaterThan($totalBefore, $totalAfter,
            'Aumentar costo_base debe aumentar total_item');
    }

    /**
     * Caso 8 — Match exacto del producto setea item_id en el ítem.
     * Aprovechamos los items sembrados en setUp().
     */
    public function test_caso8_match_exacto_setea_item_id()
    {
        // Usar el nombre exacto de un item sembrado
        $this->mockAi([
            'total_items_detected' => 1,
            'items' => [[
                'item_order' => 1,
                'product_name' => 'MOCHILA ESCOLAR TEST E2E ALPHA',
                'quantity' => 50,
                'category' => 'Textil',
            ]],
            'shared_attributes_applied' => [], 'warnings' => [],
        ]);

        $prequote = $this->orchestrator->generateFromText('50 mochilas escolares', []);
        $this->createdPrequoteIds[] = $prequote->id;

        $item = $prequote->items->first();
        $this->assertNotNull($item->item_id, 'Match exacto debe setear item_id');
        $this->assertGreaterThanOrEqual(0.85, (float) $item->product_match_confidence);
        $this->assertFalse((bool) $item->needs_product_review);
    }

    /**
     * Caso 9 — Match ambiguo marca needs_product_review=true.
     */
    public function test_caso9_match_ambiguo_marca_review()
    {
        // 'libreta' es genérico y matchea 2 items sembrados → ambiguo
        $this->mockAi([
            'total_items_detected' => 1,
            'items' => [[
                'item_order' => 1,
                'product_name' => 'libreta',
                'quantity' => 30,
                'category' => 'Impresos',
            ]],
            'shared_attributes_applied' => [], 'warnings' => [],
        ]);

        $prequote = $this->orchestrator->generateFromText('30 libretas', []);
        $this->createdPrequoteIds[] = $prequote->id;

        $item = $prequote->items->first();
        $this->assertTrue((bool) $item->needs_product_review,
            'Query genérica con varios candidatos debe marcar needs_review=true');

        // product_suggestions puede venir como array (cast Eloquent) o string JSON
        $suggestions = $item->product_suggestions;
        if (is_string($suggestions)) {
            $decoded = json_decode($suggestions, true);
            $suggestions = is_array($decoded) ? $decoded : [];
        } elseif (!is_array($suggestions)) {
            $suggestions = [];
        }
        $this->assertGreaterThanOrEqual(2, count($suggestions),
            'Debe haber al menos 2 sugerencias para casos ambiguos');
    }

    /**
     * Caso 10 — Texto sin productos detectables: prequote con 0 items + warning.
     */
    public function test_caso10_texto_sin_productos_genera_prequote_vacia()
    {
        $this->mockAi([
            'interpretation_summary' => 'No se detectaron productos.',
            'total_items_detected'   => 0,
            'items' => [],
            'shared_attributes_applied' => [],
            'warnings' => ['No hay productos identificables en el texto'],
        ]);

        $prequote = $this->orchestrator->generateFromText('Un saludo cordial al equipo', []);
        $this->createdPrequoteIds[] = $prequote->id;

        $this->assertNotNull($prequote);
        $this->assertCount(0, $prequote->items);
        $this->assertEquals(0, (float) $prequote->total);

        // El warning queda en texto_interpretado (JSON)
        $interpreted = json_decode($prequote->texto_interpretado, true);
        $this->assertNotEmpty($interpreted['warnings']);
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    private function mockAi(array $response)
    {
        // Respuesta normalizada que el orchestrator espera
        $defaults = [
            'interpretation_summary'    => '',
            'total_items_detected'      => 0,
            'items'                     => [],
            'shared_attributes_applied' => [],
            'warnings'                  => [],
        ];
        $merged = array_replace($defaults, $response);

        $this->aiMock->shouldReceive('interpretRequirement')
            ->andReturn($merged);
    }

    /**
     * Sembrar 2 productos en el catálogo `items` para los tests de matching.
     *
     * @return array  IDs creados
     */
    private function seedCatalog()
    {
        $now = now();
        $rows = [
            [
                'name' => 'MOCHILA ESCOLAR TEST E2E ALPHA',
                'description' => 'Mochila escolar para test E2E',
                'item_type_id' => '01', 'unit_type_id' => 'NIU', 'currency_type_id' => 'PEN',
                'sale_unit_price' => 50, 'purchase_unit_price' => 30, 'has_igv' => 1, 'purchase_has_igv' => 1,
                'subject_to_detraction' => 0, 'has_isc' => 0, 'restrict_sale_cpe' => 0,
                'exchange_points' => 0, 'quantity_of_points' => 0, 'active' => 1,
                'sale_affectation_igv_type_id' => '10', 'purchase_affectation_igv_type_id' => '10',
                'created_at' => $now, 'updated_at' => $now,
            ],
            [
                'name' => 'LIBRETA A4 TEST E2E BETA',
                'description' => 'Libreta A4 con tapa dura para test E2E',
                'item_type_id' => '01', 'unit_type_id' => 'NIU', 'currency_type_id' => 'PEN',
                'sale_unit_price' => 12, 'purchase_unit_price' => 6, 'has_igv' => 1, 'purchase_has_igv' => 1,
                'subject_to_detraction' => 0, 'has_isc' => 0, 'restrict_sale_cpe' => 0,
                'exchange_points' => 0, 'quantity_of_points' => 0, 'active' => 1,
                'sale_affectation_igv_type_id' => '10', 'purchase_affectation_igv_type_id' => '10',
                'created_at' => $now, 'updated_at' => $now,
            ],
            [
                'name' => 'LIBRETA A5 ECO TEST E2E GAMMA',
                'description' => 'Libreta ecológica A5',
                'item_type_id' => '01', 'unit_type_id' => 'NIU', 'currency_type_id' => 'PEN',
                'sale_unit_price' => 8, 'purchase_unit_price' => 4, 'has_igv' => 1, 'purchase_has_igv' => 1,
                'subject_to_detraction' => 0, 'has_isc' => 0, 'restrict_sale_cpe' => 0,
                'exchange_points' => 0, 'quantity_of_points' => 0, 'active' => 1,
                'sale_affectation_igv_type_id' => '10', 'purchase_affectation_igv_type_id' => '10',
                'created_at' => $now, 'updated_at' => $now,
            ],
        ];

        $ids = [];
        foreach ($rows as $row) {
            $ids[] = DB::connection('tenant')->table('items')->insertGetId($row);
        }
        return $ids;
    }
}
