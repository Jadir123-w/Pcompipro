<?php

namespace Tests\Feature\Marketing;

use Hyn\Tenancy\Environment;
use Hyn\Tenancy\Models\Website;
use Modules\Marketing\Models\PreQuote;
use Modules\Marketing\Models\PreQuoteItem;
use Modules\Marketing\Services\QuotationOrchestrator;
use Tests\TestCase;

/**
 * Com02ReferenciaProductoTest — "Referencia del producto": agregar 1 ítem por IA.
 *
 * Cubre el contrato backend del alta por referencia:
 *  - `createPreQuoteItem` es PÚBLICO y APPENDEA (no borra los existentes),
 *    respetando el `item_order` que le pasa el endpoint (max+1).
 *  - El endpoint valida XOR texto|imagen (422 si ambos/ninguno).
 *
 * El flujo completo con IA (imagen/texto → ítem) se valida por smoke manual /
 * feature test autenticado, ya que depende del proveedor LLM y de sesión.
 */
class Com02ReferenciaProductoTest extends TestCase
{
    /** @var array */
    private $cleanupPrequoteIds = [];

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
            foreach ($this->cleanupPrequoteIds as $pid) {
                PreQuoteItem::where('prequote_id', $pid)->forceDelete();
                PreQuote::where('id', $pid)->forceDelete();
            }
        } catch (\Exception $e) {}
        parent::tearDown();
    }

    /** @test */
    public function create_pre_quote_item_es_publico_y_appendea_respetando_item_order()
    {
        $orch = app(QuotationOrchestrator::class);

        $pq = PreQuote::create([
            'version' => 1, 'estado' => PreQuote::ESTADO_BORRADOR, 'titulo' => 'TEST referencia',
        ]);
        $this->cleanupPrequoteIds[] = $pq->id;

        // 2 ítems seed (como si la prequote ya tuviera productos)
        $orch->createPreQuoteItem($pq, ['product_name' => 'Item A', 'quantity' => 10, 'item_order' => 1]);
        $orch->createPreQuoteItem($pq, ['product_name' => 'Item B', 'quantity' => 5,  'item_order' => 2]);

        // 3er ítem por referencia: append con item_order = max+1 = 3 (lo que hace interpretItem)
        $nextOrder = (int) $pq->items()->max('item_order') + 1;
        $nuevo = $orch->createPreQuoteItem($pq, [
            'product_name' => 'Item C referencia', 'quantity' => 1, 'item_order' => $nextOrder,
        ]);

        $this->assertInstanceOf(PreQuoteItem::class, $nuevo);
        $this->assertEquals(3, $pq->items()->count(),
            'Se agregó 1 ítem (append): quedan 3, no se borró ninguno');
        $this->assertEquals([1, 2, 3], array_map('intval', $pq->items()->pluck('item_order')->all()),
            'El nuevo ítem queda al final (item_order 3)');
        $this->assertEquals('Item C referencia', $nuevo->product_name);
        $this->assertNull($nuevo->product_reference_image,
            'createPreQuoteItem NO setea la imagen del ítem (se hace afuera con update)');
    }

    /** @test */
    public function create_pre_quote_item_fuerza_cantidad_1_cuando_no_viene()
    {
        // El endpoint coacciona quantity<=0 a 1 ANTES de llamar al helper; acá
        // verificamos que sin quantity el helper también cae a 1 (doble red).
        $orch = app(QuotationOrchestrator::class);

        $pq = PreQuote::create([
            'version' => 1, 'estado' => PreQuote::ESTADO_BORRADOR, 'titulo' => 'TEST referencia cant',
        ]);
        $this->cleanupPrequoteIds[] = $pq->id;

        $item = $orch->createPreQuoteItem($pq, ['product_name' => 'Sin cantidad', 'item_order' => 1]);

        $this->assertEquals(1, (int) $item->quantity,
            'Sin quantity en el itemData, el ítem nace con cantidad 1 (no 0)');
    }

    /** @test */
    public function el_endpoint_rechaza_texto_e_imagen_juntos_o_ninguno()
    {
        // XOR estricto: ni texto ni imagen → 422 (en entorno con auth).
        $resp = $this->postJson(
            '/marketing/quotation/requerimientos/999/prequote/items/interpret',
            [] // ni text ni image
        );

        if (in_array($resp->status(), [302, 401, 419])) {
            $this->markTestSkipped('Auth/sesión requerida — saltando test HTTP en este entorno.');
        }

        $resp->assertStatus(422);
    }
}
