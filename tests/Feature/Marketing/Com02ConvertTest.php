<?php

namespace Tests\Feature\Marketing;

use Hyn\Tenancy\Environment;
use Hyn\Tenancy\Models\Website;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Marketing\Http\Controllers\Api\QuotationPreQuoteController;
use Modules\Marketing\Models\PreQuote;
use Modules\Marketing\Models\PreQuoteItem;
use Modules\Marketing\Services\QuotationCalculationEngine;
use Tests\TestCase;

/**
 * Com02ConvertTest — Conversión de pre-cotización a Cotización Formal.
 *
 * Cubre:
 *  1. El insert de quotation_items incluye los campos NOT NULL de Facturador
 *     (affectation_igv_type_id, price_type_id, total_taxes) → no más error 500.
 *  2. Si algún ítem no está asociado al catálogo (item_id null), se bloquea
 *     con 422 y mensaje claro en vez de fallar al insertar (item_id NOT NULL).
 */
class Com02ConvertTest extends TestCase
{
    /** @var QuotationPreQuoteController */
    private $controller;

    /** @var array IDs creados para limpiar */
    private $cleanup = ['prequotes' => [], 'quotations' => [], 'items' => []];

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

        $this->controller = app(QuotationPreQuoteController::class);
    }

    protected function tearDown(): void
    {
        try {
            foreach ($this->cleanup['quotations'] as $qid) {
                DB::connection('tenant')->table('quotation_items')->where('quotation_id', $qid)->delete();
                DB::connection('tenant')->table('quotations')->where('id', $qid)->delete();
            }
            foreach ($this->cleanup['prequotes'] as $pid) {
                PreQuoteItem::where('prequote_id', $pid)->delete();
                PreQuote::where('id', $pid)->delete();
            }
            foreach ($this->cleanup['items'] as $iid) {
                DB::connection('tenant')->table('items')->where('id', $iid)->delete();
            }
        } catch (\Exception $e) {}
        parent::tearDown();
    }

    /** @test */
    public function item_sin_catalogo_se_crea_en_el_catalogo_y_convierte()
    {
        // RF — un producto SIN asociar al catálogo YA NO bloquea: convert() lo
        // crea en `items` (ad-hoc) y lo asocia. Requiere el escenario E2E
        // (empresa cuyo RUC matchea un cliente + usuario). Si no existe, se salta.
        $empresa = DB::connection('tenant')->table('empresa')
            ->whereIn('ruc', function ($q) {
                $q->select('number')->from('persons')->where('type', 'customers');
            })
            ->first();
        $userId = DB::connection('tenant')->table('users')->where('active', 1)->value('id');

        if (!$empresa || !$userId) {
            $this->markTestSkipped('Sin empresa/cliente/usuario para el convert E2E');
        }

        $pq = PreQuote::create([
            'version' => 1, 'estado' => PreQuote::ESTADO_VALIDADA, 'titulo' => 'TEST ad-hoc',
            'empresa_id' => $empresa->id, 'user_id' => $userId,
        ]);
        $this->cleanup['prequotes'][] = $pq->id;

        $item = PreQuoteItem::create([
            'prequote_id' => $pq->id, 'item_id' => null,
            'product_name' => 'Producto artesanal sin catálogo ' . uniqid(),
            'quantity' => 5, 'costo_base' => 10, 'margen_porcentaje' => 30,
        ]);
        (new QuotationCalculationEngine())->recalculate($pq);

        $resp = $this->controller->convert(Request::create('/', 'POST'), $pq->id);
        $data = $resp->getData(true);

        $this->assertEquals(200, $resp->getStatusCode(),
            'Ya NO debe bloquear por catálogo (msg: ' . ($data['message'] ?? '') . ')');
        $this->assertTrue($data['ok']);

        $qid = $pq->fresh()->quotation_id;
        $this->assertNotNull($qid, 'Debe haberse creado la Quotation');
        $this->cleanup['quotations'][] = $qid;

        // El ítem quedó asociado a un producto de catálogo recién creado (ad-hoc).
        $item = $item->fresh();
        $this->assertNotNull($item->item_id, 'El ítem debe quedar asociado al catálogo creado');
        $this->cleanup['items'][] = $item->item_id;

        $catalog = DB::connection('tenant')->table('items')->where('id', $item->item_id)->first();
        $this->assertNotNull($catalog, 'Debe existir el ítem de catálogo creado');
        $this->assertStringStartsWith('COM02-ADHOC-', (string) $catalog->internal_id,
            'El ítem creado debe marcarse como ad-hoc en internal_id');
    }

    /** @test */
    public function convertir_una_precotizacion_sin_productos_responde_422()
    {
        $pq = PreQuote::create([
            'version' => 1, 'estado' => PreQuote::ESTADO_VALIDADA, 'titulo' => 'TEST vacía',
        ]);
        $this->cleanup['prequotes'][] = $pq->id;

        $resp = $this->controller->convert(Request::create('/', 'POST'), $pq->id);
        $data = $resp->getData(true);

        $this->assertEquals(422, $resp->getStatusCode());
        $this->assertFalse($data['ok']);
        $this->assertStringContainsString('no tiene productos', $data['message']);
        $this->assertNull($pq->fresh()->quotation_id);
    }

    /** @test */
    public function validar_una_precotizacion_sin_productos_responde_422()
    {
        $pq = PreQuote::create([
            'version' => 1, 'estado' => PreQuote::ESTADO_BORRADOR, 'titulo' => 'TEST vacía',
        ]);
        $this->cleanup['prequotes'][] = $pq->id;

        $resp = $this->controller->updateStatus(
            Request::create('/', 'PUT', ['estado' => PreQuote::ESTADO_VALIDADA]),
            $pq->id
        );
        $data = $resp->getData(true);

        $this->assertEquals(422, $resp->getStatusCode());
        $this->assertFalse($data['ok']);
        $this->assertStringContainsString('no tiene productos', $data['message']);
        // El estado NO debe haber cambiado.
        $this->assertEquals(PreQuote::ESTADO_BORRADOR, $pq->fresh()->estado);
    }

    /** @test */
    public function convert_crea_quotation_items_con_los_campos_not_null_requeridos()
    {
        // Requiere un escenario completo: empresa cuyo RUC matchea un cliente,
        // un producto del catálogo y un usuario activo. Si no existe, saltamos.
        $empresa = DB::connection('tenant')->table('empresa')
            ->whereIn('ruc', function ($q) {
                $q->select('number')->from('persons')->where('type', 'customers');
            })
            ->first();
        $catalogItemId = DB::connection('tenant')->table('items')->where('active', 1)->value('id');
        $userId = DB::connection('tenant')->table('users')->where('active', 1)->value('id');

        if (!$empresa || !$catalogItemId || !$userId) {
            $this->markTestSkipped('Sin empresa/cliente/catálogo/usuario para el convert E2E');
        }

        $pq = PreQuote::create([
            'version' => 1, 'estado' => PreQuote::ESTADO_VALIDADA, 'titulo' => 'TEST convert OK',
            'empresa_id' => $empresa->id, 'user_id' => $userId,
        ]);
        $this->cleanup['prequotes'][] = $pq->id;

        PreQuoteItem::create([
            'prequote_id' => $pq->id, 'item_id' => $catalogItemId,
            'product_name' => 'Producto catálogo', 'quantity' => 5,
            'costo_base' => 10, 'margen_porcentaje' => 30,
        ]);
        (new QuotationCalculationEngine())->recalculate($pq);

        $resp = $this->controller->convert(Request::create('/', 'POST'), $pq->id);
        $data = $resp->getData(true);

        $this->assertEquals(200, $resp->getStatusCode(),
            'convert debe devolver 200 (msg: ' . ($data['message'] ?? '') . ')');
        $this->assertTrue($data['ok']);

        $qid = $pq->fresh()->quotation_id;
        $this->assertNotNull($qid);
        $this->cleanup['quotations'][] = $qid;

        $qItem = DB::connection('tenant')->table('quotation_items')->where('quotation_id', $qid)->first();
        $this->assertNotNull($qItem, 'Debe haberse creado el quotation_item');
        $this->assertEquals('10', (string) $qItem->affectation_igv_type_id);
        $this->assertEquals('01', (string) $qItem->price_type_id);
        $this->assertNotNull($qItem->total_taxes);
    }
}
