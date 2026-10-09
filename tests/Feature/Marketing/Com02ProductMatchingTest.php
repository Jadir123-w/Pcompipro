<?php

namespace Tests\Feature\Marketing;

use Hyn\Tenancy\Environment;
use Hyn\Tenancy\Models\Website;
use Illuminate\Support\Facades\DB;
use Modules\Marketing\Models\PreQuoteItem;
use Modules\Marketing\Services\ProductMatchingService;
use Tests\TestCase;

/**
 * Com02ProductMatchingTest
 *
 * Tests del ProductMatchingService + helpers del modelo PreQuoteItem.
 *
 * Cubre:
 *   1. Match con confianza alta (query=item.name → score >= 0.85, no review)
 *   2. Match ambiguo (query corta y genérica → review=true)
 *   3. No match (query random → best=null, suggestions vacías)
 *   4. Search libre devuelve top-N con scores ordenados
 *   5. PreQuoteItem::applyMatchResult persiste correctamente
 *   6. PreQuoteItem::manualMatchTo asocia / desasocia
 *
 * Estrategia: usa la BD del tenant existente (no mocks). Los items de test
 * los creamos al setUp y los limpiamos al tearDown para no contaminar.
 */
class Com02ProductMatchingTest extends TestCase
{
    /** @var ProductMatchingService */
    private $matcher;

    /** @var array  IDs de items de catálogo creados para el test */
    private $createdItemIds = [];

    /** @var array  IDs de prequotes y prequote-items creados */
    private $createdPrequoteIds = [];
    private $createdPrequoteItemIds = [];

    protected function setUp(): void
    {
        parent::setUp();

        // ── DB override (phpunit.xml hardcodea multifacturalo_dusk) ──
        config(['database.connections.system.database' => 'dberp']);
        config(['database.connections.mysql.database'  => 'dberp']);
        \Illuminate\Support\Facades\DB::purge('system');
        \Illuminate\Support\Facades\DB::purge('mysql');

        $website = Website::first();
        if (!$website) {
            $this->markTestSkipped('Sin tenants registrados');
        }
        app(Environment::class)->tenant($website);

        $this->matcher = app(ProductMatchingService::class);

        // Crear catálogo de prueba con productos diferenciables
        $this->createdItemIds = $this->seedCatalog();
    }

    protected function tearDown(): void
    {
        // Limpiar items de catálogo creados
        if (!empty($this->createdItemIds)) {
            try {
                DB::connection('tenant')->table('items')
                    ->whereIn('id', $this->createdItemIds)
                    ->delete();
            } catch (\Exception $e) {}
        }
        // Limpiar prequote items
        if (!empty($this->createdPrequoteItemIds)) {
            try {
                PreQuoteItem::whereIn('id', $this->createdPrequoteItemIds)->delete();
            } catch (\Exception $e) {}
        }
        // Limpiar prequotes
        if (!empty($this->createdPrequoteIds)) {
            try {
                DB::connection('tenant')->table('quotation_prequotes')
                    ->whereIn('id', $this->createdPrequoteIds)
                    ->delete();
            } catch (\Exception $e) {}
        }
        parent::tearDown();
    }

    /*
    |--------------------------------------------------------------------------
    | Tests
    |--------------------------------------------------------------------------
    */

    /**
     * Caso 1 — Match con confianza alta cuando la query coincide claramente
     * con el nombre de un item.
     */
    public function test_caso1_match_alto_confianza()
    {
        $result = $this->matcher->match('mochila escolar test FX1', 'con compartimiento porta laptop');

        $this->assertNotNull($result['best_match'], 'Se debe encontrar match');
        $this->assertEquals('MOCHILA ESCOLAR TEST FX1', $result['best_match']['name']);
        $this->assertGreaterThanOrEqual(0.85, $result['confidence'],
            'Score debe ser >= 0.85 cuando la query coincide con el nombre');
        $this->assertFalse($result['needs_review'],
            'No debe pedir review con score alto');
        $this->assertGreaterThan(0, count($result['suggestions']));
    }

    /**
     * Caso 2 — Match ambiguo: query corta que matchea parcialmente varios
     * items debe disparar needs_review=true.
     */
    public function test_caso2_match_ambiguo_pide_review()
    {
        // 'libreta' aparece en 2 items: "LIBRETA A4 TEST FX2" y "LIBRETA A5 ECO TEST FX3"
        $result = $this->matcher->match('libreta', null);

        $this->assertNotNull($result['best_match'], 'Hay candidatos pero ambiguos');
        $this->assertGreaterThanOrEqual(2, count($result['suggestions']),
            'Debe haber al menos 2 sugerencias para casos ambiguos');
        $this->assertTrue($result['needs_review'],
            'Score medio o empate cercano debe pedir review');
    }

    /**
     * Caso 3 — Sin match: query muy distinta del catálogo.
     */
    public function test_caso3_no_match()
    {
        $result = $this->matcher->match('nave espacial cuántica zorbiana', null);

        $this->assertNull($result['best_match']);
        $this->assertLessThan(0.40, $result['confidence']);
        $this->assertEmpty($result['suggestions']);
        $this->assertFalse($result['needs_review']);
    }

    /**
     * Caso 4 — Search libre: ordenado por score desc.
     */
    public function test_caso4_search_libre_devuelve_top_n_ordenados()
    {
        $results = $this->matcher->search('libreta', [], 10);

        $this->assertGreaterThanOrEqual(2, count($results));

        // Verificar orden descendente por score
        $prevScore = 1.0;
        foreach ($results as $r) {
            $this->assertLessThanOrEqual($prevScore, $r['score'],
                'Resultados deben venir ordenados por score desc');
            $prevScore = $r['score'];
        }

        // Cada resultado tiene la estructura esperada
        foreach ($results as $r) {
            $this->assertArrayHasKey('item_id', $r);
            $this->assertArrayHasKey('name', $r);
            $this->assertArrayHasKey('score', $r);
            $this->assertArrayHasKey('reason', $r);
        }
    }

    /**
     * Caso 5 — applyMatchResult persiste los campos en el modelo.
     */
    public function test_caso5_apply_match_result_persiste()
    {
        $item = $this->createPrequoteItem('mochila escolar test FX1');

        $result = $this->matcher->match('mochila escolar test FX1', null);
        $item->applyMatchResult($result);

        $fresh = $item->fresh();
        $this->assertNotNull($fresh->item_id);
        $this->assertNotNull($fresh->product_match_confidence);
        $this->assertGreaterThanOrEqual(0.85, (float) $fresh->product_match_confidence);
        $this->assertFalse((bool) $fresh->needs_product_review);
        $this->assertNotEmpty($fresh->product_suggestions);
    }

    /**
     * Caso 6 — manualMatchTo: asocia y luego desasocia (item_id=null).
     */
    public function test_caso6_manual_match_to_asigna_y_desasigna()
    {
        $item = $this->createPrequoteItem('cualquier nombre');

        // Asignar
        $catalogItemId = $this->createdItemIds[0];
        $item->manualMatchTo($catalogItemId);

        $fresh = $item->fresh();
        $this->assertEquals($catalogItemId, $fresh->item_id);
        $this->assertEquals(1.00, (float) $fresh->product_match_confidence);
        $this->assertFalse((bool) $fresh->needs_product_review);

        // Desasignar
        $item->manualMatchTo(null);
        $fresh = $item->fresh();
        $this->assertNull($fresh->item_id);
        $this->assertNull($fresh->product_match_confidence);
        $this->assertFalse((bool) $fresh->needs_product_review);
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    /**
     * Crear catálogo de prueba: 3 items diferenciables para los tests de match.
     *
     * @return array  IDs creados
     */
    private function seedCatalog()
    {
        $now = now();
        $rows = [
            [
                'name'                => 'MOCHILA ESCOLAR TEST FX1',
                'description'         => 'Mochila escolar de poliéster con compartimiento porta laptop',
                'item_type_id'        => '01',
                'unit_type_id'        => 'NIU',
                'currency_type_id'    => 'PEN',
                'sale_unit_price'     => 50.00,
                'purchase_unit_price' => 30.00,
                'has_igv'             => 1,
                'purchase_has_igv'    => 1,
                'subject_to_detraction' => 0,
                'has_isc'             => 0,
                'restrict_sale_cpe'   => 0,
                'exchange_points'     => 0,
                'quantity_of_points'  => 0,
                'sale_affectation_igv_type_id'     => '10',
                'purchase_affectation_igv_type_id' => '10',
                'active'              => 1,
                'created_at'          => $now,
                'updated_at'          => $now,
            ],
            [
                'name'                => 'LIBRETA A4 TEST FX2',
                'description'         => 'Libreta tamaño A4 con tapa dura',
                'item_type_id'        => '01',
                'unit_type_id'        => 'NIU',
                'currency_type_id'    => 'PEN',
                'sale_unit_price'     => 12.00,
                'purchase_unit_price' => 6.00,
                'has_igv'             => 1,
                'purchase_has_igv'    => 1,
                'subject_to_detraction' => 0,
                'has_isc'             => 0,
                'restrict_sale_cpe'   => 0,
                'exchange_points'     => 0,
                'quantity_of_points'  => 0,
                'sale_affectation_igv_type_id'     => '10',
                'purchase_affectation_igv_type_id' => '10',
                'active'              => 1,
                'created_at'          => $now,
                'updated_at'          => $now,
            ],
            [
                'name'                => 'LIBRETA A5 ECO TEST FX3',
                'description'         => 'Libreta ecológica tamaño A5 papel reciclado',
                'item_type_id'        => '01',
                'unit_type_id'        => 'NIU',
                'currency_type_id'    => 'PEN',
                'sale_unit_price'     => 8.00,
                'purchase_unit_price' => 4.00,
                'has_igv'             => 1,
                'purchase_has_igv'    => 1,
                'subject_to_detraction' => 0,
                'has_isc'             => 0,
                'restrict_sale_cpe'   => 0,
                'exchange_points'     => 0,
                'quantity_of_points'  => 0,
                'sale_affectation_igv_type_id'     => '10',
                'purchase_affectation_igv_type_id' => '10',
                'active'              => 1,
                'created_at'          => $now,
                'updated_at'          => $now,
            ],
        ];

        $ids = [];
        foreach ($rows as $row) {
            $id = DB::connection('tenant')->table('items')->insertGetId($row);
            $ids[] = $id;
        }
        return $ids;
    }

    /**
     * Crear un PreQuoteItem mínimo para testear los helpers del modelo.
     *
     * @param  string  $productName
     * @return PreQuoteItem
     */
    private function createPrequoteItem($productName)
    {
        // Crear prequote padre (campos según tabla real quotation_prequotes)
        $prequoteId = DB::connection('tenant')->table('quotation_prequotes')->insertGetId([
            'titulo'        => 'Test prequote ' . uniqid(),
            'estado'        => 'borrador',
            'subtotal'      => 0,
            'total_igv'     => 0,
            'total'         => 0,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
        $this->createdPrequoteIds[] = $prequoteId;

        $item = PreQuoteItem::create([
            'prequote_id'           => $prequoteId,
            'item_order'            => 1,
            'product_name'          => $productName,
            'quantity'              => 1,
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
            'is_inferred'           => true,
        ]);
        $this->createdPrequoteItemIds[] = $item->id;

        return $item;
    }
}
