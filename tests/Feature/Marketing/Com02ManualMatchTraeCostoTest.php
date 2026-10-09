<?php

namespace Tests\Feature\Marketing;

use Hyn\Tenancy\Environment;
use Hyn\Tenancy\Models\Website;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Marketing\Http\Controllers\Api\QuotationProductController;
use Modules\Marketing\Models\PreQuote;
use Modules\Marketing\Models\PreQuoteItem;
use Tests\TestCase;

/**
 * Com02ManualMatchTraeCostoTest — Auto-copia de costo desde el catálogo.
 *
 * Cuando la ejecutiva asocia manualmente un producto del catálogo a un ítem
 * de la pre-cotización (vía el dialog "Sin match — buscar"), el sistema
 * debe traer automáticamente el sale_unit_price del catálogo y ponerlo
 * como costo_base del prequote_item.
 *
 * Política (decidida con el cliente — 2026-05-18):
 *   - El catálogo siempre manda. Si tiene sale_unit_price > 0, se copia
 *     SIEMPRE — incluso si el costo_base actual del ítem tenía un valor
 *     manual editado por la ejecutiva.
 *   - Excepción: si el catálogo no tiene precio (sale_unit_price = 0),
 *     no se toca el costo_base del ítem. Sería destruir un dato útil para
 *     reemplazarlo por nada.
 *
 * Casos cubiertos:
 *   1. costo_base = 0 + catálogo con sale_unit_price > 0 → se copia.
 *   2. costo_base > 0 (manual) + catálogo > 0 → SE SOBRESCRIBE (regla nueva).
 *   3. cualquier costo_base + catálogo con sale_unit_price = 0 → no se toca.
 *   4. Desasociar (item_id=null) → no se toca el costo_base.
 *   5. La prequote recalculada vuelve con totales actualizados.
 */
class Com02ManualMatchTraeCostoTest extends TestCase
{
    /** @var int[] */
    private $createdItemIds = [];

    /** @var int[] */
    private $createdPrequoteIds = [];

    /** @var int[] */
    private $createdPrequoteItemIds = [];

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
            if (!empty($this->createdPrequoteItemIds)) {
                PreQuoteItem::whereIn('id', $this->createdPrequoteItemIds)->delete();
            }
            if (!empty($this->createdPrequoteIds)) {
                DB::connection('tenant')->table('quotation_prequotes')
                    ->whereIn('id', $this->createdPrequoteIds)->delete();
            }
            if (!empty($this->createdItemIds)) {
                DB::connection('tenant')->table('items')
                    ->whereIn('id', $this->createdItemIds)->delete();
            }
        } catch (\Exception $e) {}
        parent::tearDown();
    }

    /*
    |--------------------------------------------------------------------------
    | Tests
    |--------------------------------------------------------------------------
    */

    /** @test */
    public function trae_costo_desde_catalogo_cuando_costo_base_esta_en_cero()
    {
        $catalogItem = $this->seedCatalogItem('POLO TEST T1', 25.50);
        $prequoteItem = $this->seedPrequoteItem(['costo_base' => 0]);

        $response = $this->callManualMatch($prequoteItem->id, $catalogItem->id);

        $payload = $response->getData(true);
        $this->assertTrue($payload['ok']);
        $this->assertEquals(25.50, $payload['costo_traido']);
        $this->assertArrayHasKey('prequote', $payload, 'Debe incluir la prequote recalculada');

        $fresh = $prequoteItem->fresh();
        $this->assertEquals(25.50, (float) $fresh->costo_base);
        $this->assertEquals($catalogItem->id, $fresh->item_id);
    }

    /** @test */
    public function sobreescribe_costo_manual_con_el_del_catalogo()
    {
        // Política nueva (2026-05-18): el catálogo siempre manda. Aunque la
        // ejecutiva haya escrito un costo manual antes, al elegir un producto
        // del catálogo el costo se reemplaza con sale_unit_price.
        $catalogItem = $this->seedCatalogItem('POLO TEST T2', 25.50);
        $prequoteItem = $this->seedPrequoteItem(['costo_base' => 18.75]);  // valor manual previo

        $response = $this->callManualMatch($prequoteItem->id, $catalogItem->id);

        $payload = $response->getData(true);
        $this->assertTrue($payload['ok']);
        $this->assertEquals(25.50, $payload['costo_traido'],
            'Debe traer el costo del catálogo aunque hubiera un valor manual');
        $this->assertArrayHasKey('prequote', $payload, 'Debe incluir la prequote recalculada');

        $fresh = $prequoteItem->fresh();
        $this->assertEquals(25.50, (float) $fresh->costo_base,
            'El costo_base manual debe ser reemplazado por el del catálogo');
        $this->assertEquals($catalogItem->id, $fresh->item_id);
    }

    /** @test */
    public function no_trae_nada_si_el_catalogo_tiene_sale_unit_price_en_cero()
    {
        $catalogItem = $this->seedCatalogItem('PRODUCTO SIN PRECIO T3', 0.00);
        $prequoteItem = $this->seedPrequoteItem(['costo_base' => 0]);

        $response = $this->callManualMatch($prequoteItem->id, $catalogItem->id);

        $payload = $response->getData(true);
        $this->assertTrue($payload['ok']);
        $this->assertNull($payload['costo_traido']);

        $fresh = $prequoteItem->fresh();
        $this->assertEquals(0.0, (float) $fresh->costo_base);
        $this->assertEquals($catalogItem->id, $fresh->item_id,
            'El match igual se aplica aunque el catálogo no tenga precio');
    }

    /** @test */
    public function catalogo_sin_precio_no_pisa_costo_manual_existente()
    {
        // Caso límite: la ejecutiva ya tiene un costo manual (ej. 99.99) y
        // elige un producto del catálogo que NO tiene precio (0). No queremos
        // destruir su trabajo manual reemplazándolo por 0.
        $catalogItem  = $this->seedCatalogItem('PRODUCTO SIN PRECIO T3B', 0.00);
        $prequoteItem = $this->seedPrequoteItem(['costo_base' => 99.99]);

        $response = $this->callManualMatch($prequoteItem->id, $catalogItem->id);
        $payload  = $response->getData(true);

        $this->assertTrue($payload['ok']);
        $this->assertNull($payload['costo_traido']);

        $fresh = $prequoteItem->fresh();
        $this->assertEquals(99.99, (float) $fresh->costo_base,
            'Si el catálogo no tiene precio, no debe pisar el costo manual existente');
    }

    /** @test */
    public function desasociar_no_toca_costo_base()
    {
        $prequoteItem = $this->seedPrequoteItem(['costo_base' => 12.34, 'item_id' => null]);
        // Asociarlo primero a algo
        $catalogItem = $this->seedCatalogItem('POLO T4', 50.00);
        $prequoteItem->item_id = $catalogItem->id;
        $prequoteItem->save();

        // Ahora desasociar (item_id = null)
        $response = $this->callManualMatch($prequoteItem->id, null);

        $payload = $response->getData(true);
        $this->assertTrue($payload['ok']);
        $this->assertNull($payload['costo_traido']);

        $fresh = $prequoteItem->fresh();
        $this->assertNull($fresh->item_id);
        $this->assertEquals(12.34, (float) $fresh->costo_base,
            'El costo_base no debe modificarse al desasociar');
    }

    /** @test */
    public function la_prequote_recalculada_tiene_subtotal_actualizado()
    {
        $catalogItem = $this->seedCatalogItem('POLO RECALC T5', 100.00);
        $prequoteItem = $this->seedPrequoteItem([
            'costo_base'        => 0,
            'quantity'          => 5,
            'margen_porcentaje' => 30,
        ]);

        $response = $this->callManualMatch($prequoteItem->id, $catalogItem->id);
        $payload  = $response->getData(true);

        $this->assertNotNull($payload['prequote']);
        // valor_unitario = costo (100) × (1 + 30/100) = 130
        // valor_total    = 130 × 5 = 650
        // Esos numbers están sujetos al engine. Acá solo validamos que el
        // subtotal NO sea 0 (que era el estado inicial). El cálculo exacto
        // está cubierto por los tests del QuotationCalculationEngine.
        $this->assertGreaterThan(0, (float) $payload['prequote']['subtotal'],
            'El subtotal debe haberse recalculado tras traer el costo del catálogo');
    }

    /** @test */
    public function el_message_de_response_indica_el_costo_traido()
    {
        $catalogItem  = $this->seedCatalogItem('POLO MSG T6', 42.00);
        $prequoteItem = $this->seedPrequoteItem(['costo_base' => 0]);

        $response = $this->callManualMatch($prequoteItem->id, $catalogItem->id);
        $payload  = $response->getData(true);

        $this->assertStringContainsString('Costo base actualizado', $payload['message']);
        $this->assertStringContainsString('42.00', $payload['message']);
    }

    /*
    |--------------------------------------------------------------------------
    | IGV — el costo_base debe quedar SIN IGV (el motor le suma IGV al final)
    |--------------------------------------------------------------------------
    */

    /** @test */
    public function descuenta_igv_cuando_el_catalogo_tiene_has_igv_true()
    {
        // sale_unit_price = 23.60 con has_igv=1 (incluye IGV) → costo_base sin
        // IGV = 23.60 / 1.18 = 20.00. Así, cuando el motor le sume el 18% otra
        // vez, el precio al cliente vuelve a 23.60 (sin IGV duplicado).
        $catalogItem  = $this->seedCatalogItem('POLO CON IGV T7', 23.60, 1);
        $prequoteItem = $this->seedPrequoteItem(['costo_base' => 0]);

        $response = $this->callManualMatch($prequoteItem->id, $catalogItem->id);
        $payload  = $response->getData(true);

        $this->assertTrue($payload['ok']);
        $this->assertEquals(20.00, $payload['costo_traido'],
            'El costo_base debe ser el precio SIN IGV (23.60 / 1.18 = 20.00)');
        $this->assertTrue($payload['igv_removido'], 'Debe marcar que se removió el IGV');

        $fresh = $prequoteItem->fresh();
        $this->assertEquals(20.00, (float) $fresh->costo_base);
    }

    /** @test */
    public function no_descuenta_igv_cuando_el_catalogo_tiene_has_igv_false()
    {
        // sale_unit_price = 20.00 con has_igv=0 (NO incluye IGV) → el precio ya
        // es base → costo_base = 20.00 directo, sin tocar.
        $catalogItem  = $this->seedCatalogItem('POLO SIN IGV T8', 20.00, 0);
        $prequoteItem = $this->seedPrequoteItem(['costo_base' => 0]);

        $response = $this->callManualMatch($prequoteItem->id, $catalogItem->id);
        $payload  = $response->getData(true);

        $this->assertTrue($payload['ok']);
        $this->assertEquals(20.00, $payload['costo_traido'],
            'El costo_base debe ser el precio directo (sin descontar IGV)');
        $this->assertFalse($payload['igv_removido'], 'NO debe marcar IGV removido');

        $fresh = $prequoteItem->fresh();
        $this->assertEquals(20.00, (float) $fresh->costo_base);
    }

    /** @test */
    public function el_mensaje_avisa_cuando_se_removio_el_igv()
    {
        $catalogItem  = $this->seedCatalogItem('POLO MSG IGV T9', 118.00, 1);
        $prequoteItem = $this->seedPrequoteItem(['costo_base' => 0]);

        $response = $this->callManualMatch($prequoteItem->id, $catalogItem->id);
        $payload  = $response->getData(true);

        // 118.00 / 1.18 = 100.00
        $this->assertEquals(100.00, $payload['costo_traido']);
        $this->assertStringContainsString('inclu', mb_strtolower($payload['message']),
            'El mensaje debe avisar que el precio incluía IGV');
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    private function callManualMatch($prequoteItemId, $catalogItemId)
    {
        $request = Request::create('/match', 'POST', ['item_id' => $catalogItemId]);
        $controller = app(QuotationProductController::class);
        return $controller->manualMatch($request, $prequoteItemId);
    }

    private function seedCatalogItem($name, $salePrice, $hasIgv = 0)
    {
        $id = DB::connection('tenant')->table('items')->insertGetId([
            'name'                  => $name,
            'description'           => $name . ' descripción',
            'item_type_id'          => '01',
            'unit_type_id'          => 'NIU',
            'currency_type_id'      => 'PEN',
            'sale_unit_price'       => $salePrice,
            'purchase_unit_price'   => $salePrice * 0.6,
            'has_igv'               => $hasIgv,
            'purchase_has_igv'      => 1,
            'subject_to_detraction' => 0,
            'has_isc'               => 0,
            'restrict_sale_cpe'     => 0,
            'exchange_points'       => 0,
            'quantity_of_points'    => 0,
            'sale_affectation_igv_type_id'     => '10',
            'purchase_affectation_igv_type_id' => '10',
            'active'                => 1,
            'created_at'            => now(),
            'updated_at'            => now(),
        ]);
        $this->createdItemIds[] = $id;
        // Devolver un stdClass para acceder cómodo a id+sale_unit_price
        return (object) ['id' => $id, 'sale_unit_price' => $salePrice, 'has_igv' => $hasIgv];
    }

    private function seedPrequoteItem(array $overrides = [])
    {
        // Crear prequote padre
        $prequoteId = DB::connection('tenant')->table('quotation_prequotes')->insertGetId([
            'titulo'     => 'Test prequote ' . uniqid(),
            'version'    => 1,
            'estado'     => 'borrador',
            'subtotal'   => 0,
            'total_igv'  => 0,
            'total'      => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->createdPrequoteIds[] = $prequoteId;

        $item = PreQuoteItem::create(array_merge([
            'prequote_id'         => $prequoteId,
            'item_order'          => 1,
            'product_name'        => 'Polo de prueba',
            'product_description' => 'Polo para test de match',
            'quantity'            => 10,
            'costo_base'          => 0,
            'margen_porcentaje'   => 30,
            'is_inferred'         => false,
        ], $overrides));

        $this->createdPrequoteItemIds[] = $item->id;
        return $item;
    }
}
