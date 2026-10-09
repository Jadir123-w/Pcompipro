<?php

namespace Tests\Feature\Marketing;

use Hyn\Tenancy\Environment;
use Hyn\Tenancy\Models\Website;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Modules\Marketing\Http\Controllers\Api\QuotationPreQuoteController;
use Modules\Marketing\Models\PreQuote;
use Modules\Marketing\Models\PreQuoteItem;
use Modules\Marketing\Services\QuotationCalculationEngine;
use Tests\TestCase;

/**
 * Com02EliminarItemTest — Eliminar un ítem de la pre-cotización.
 *
 * Valida el endpoint DELETE /quotation/prequotes/{id}/item/{itemId}:
 *   - Borra el ítem y recalcula los totales.
 *   - Al borrar el último ítem, la prequote queda en 0.
 *   - Un ítem que no pertenece a la prequote → 404.
 *   - Una prequote no editable → 422.
 */
class Com02EliminarItemTest extends TestCase
{
    /** @var QuotationPreQuoteController */
    private $controller;

    /** @var PreQuote|null */
    private $prequote;

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

        // Prequote temporal con 2 ítems, en estado editable (borrador).
        $this->prequote = PreQuote::create([
            'version' => 1,
            'estado'  => PreQuote::ESTADO_BORRADOR,
            'titulo'  => 'TEST eliminar item',
        ]);
        PreQuoteItem::create([
            'prequote_id' => $this->prequote->id,
            'product_name' => 'TEST A', 'quantity' => 10,
            'costo_base' => 20, 'margen_porcentaje' => 30,
        ]);
        PreQuoteItem::create([
            'prequote_id' => $this->prequote->id,
            'product_name' => 'TEST B', 'quantity' => 5,
            'costo_base' => 50, 'margen_porcentaje' => 40,
        ]);

        (new QuotationCalculationEngine())->recalculate($this->prequote);
        $this->prequote->refresh();
    }

    protected function tearDown(): void
    {
        try {
            if ($this->prequote) {
                PreQuoteItem::where('prequote_id', $this->prequote->id)->delete();
                $this->prequote->delete();
            }
        } catch (\Exception $e) {}
        parent::tearDown();
    }

    /** @test */
    public function borra_un_item_y_recalcula_los_totales()
    {
        $this->assertEquals(2, $this->prequote->items()->count());
        $subtotalAntes = (float) $this->prequote->subtotal;
        $this->assertGreaterThan(0, $subtotalAntes);

        $itemABorrar = $this->prequote->items()->first();

        $resp = $this->controller->deleteItem(Request::create('/', 'DELETE'), $this->prequote->id, $itemABorrar->id);
        $data = $resp->getData(true);

        $this->assertTrue($data['ok']);
        $this->assertNull(PreQuoteItem::find($itemABorrar->id), 'El ítem debe quedar eliminado');

        $this->prequote->refresh();
        $this->assertEquals(1, $this->prequote->items()->count());
        $this->assertNotEquals($subtotalAntes, (float) $this->prequote->subtotal,
            'El subtotal debe recalcularse tras borrar un ítem');
        $this->assertGreaterThan(0, (float) $this->prequote->subtotal);
    }

    /** @test */
    public function borrar_el_ultimo_item_deja_los_totales_en_cero()
    {
        // Borrar ambos ítems, uno por uno.
        foreach ($this->prequote->items()->pluck('id') as $itemId) {
            $this->controller->deleteItem(Request::create('/', 'DELETE'), $this->prequote->id, $itemId);
        }

        $this->prequote->refresh();
        $this->assertEquals(0, $this->prequote->items()->count());
        $this->assertEquals(0.0, (float) $this->prequote->subtotal);
        $this->assertEquals(0.0, (float) $this->prequote->total_igv);
        $this->assertEquals(0.0, (float) $this->prequote->total);
    }

    /** @test */
    public function item_de_otra_prequote_lanza_404()
    {
        // Creamos un ítem en OTRA prequote y pedimos borrarlo vía la nuestra.
        $otra = PreQuote::create([
            'version' => 1, 'estado' => PreQuote::ESTADO_BORRADOR, 'titulo' => 'TEST otra',
        ]);
        $itemAjeno = PreQuoteItem::create([
            'prequote_id' => $otra->id, 'product_name' => 'AJENO', 'quantity' => 1,
            'costo_base' => 1, 'margen_porcentaje' => 1,
        ]);

        try {
            $this->expectException(ModelNotFoundException::class);
            $this->controller->deleteItem(Request::create('/', 'DELETE'), $this->prequote->id, $itemAjeno->id);
        } finally {
            $itemAjeno->delete();
            $otra->delete();
        }
    }

    /** @test */
    public function prequote_no_editable_responde_422()
    {
        $this->prequote->update(['estado' => PreQuote::ESTADO_APROBADA]);
        $itemId = $this->prequote->items()->first()->id;

        $resp = $this->controller->deleteItem(Request::create('/', 'DELETE'), $this->prequote->id, $itemId);
        $data = $resp->getData(true);

        $this->assertEquals(422, $resp->getStatusCode());
        $this->assertFalse($data['ok']);
        // El ítem NO debe borrarse si la prequote no es editable.
        $this->assertNotNull(PreQuoteItem::find($itemId));
    }
}
