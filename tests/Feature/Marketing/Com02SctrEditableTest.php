<?php

namespace Tests\Feature\Marketing;

use Hyn\Tenancy\Environment;
use Hyn\Tenancy\Models\Website;
use Modules\Marketing\Models\PreQuote;
use Modules\Marketing\Models\PreQuoteItem;
use Modules\Marketing\Models\QuotationConfig;
use Tests\TestCase;

/**
 * Com02SctrEditableTest — SCTR editable por cotización (endpoint).
 *
 * El SCTR depende de la condición de pago, así que se fija POR cotización.
 * Este test cubre el contrato HTTP que consume la UI (COM-02):
 *
 *   PUT /api/marketing/quotation/prequotes/{id}/sctr   Body: { sctr_porcentaje }
 *
 *  1. Fijar un % propio recalcula los totales con ese %.
 *  2. Mandar null (o vacío) resetea al SCTR global del config.
 *  3. La respuesta expone `sctr_porcentaje` (el propio) y `sctr_config_default`
 *     (el global, para precargar el input).
 *  4. Validación: rechaza valores fuera de rango (max 10).
 *
 * El motor de cálculo en sí se valida en Com02MotorCosteoTest; aquí probamos el
 * endpoint end-to-end (guardar + recalcular + serializar).
 */
class Com02SctrEditableTest extends TestCase
{
    /** @var array IDs de prequotes creadas para limpiar */
    private $cleanupPrequoteIds = [];

    /** @var mixed */
    private $previousSctrConfig;

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

        // SCTR global determinístico = 1.5%
        $this->previousSctrConfig = QuotationConfig::getValue('sctr_porcentaje', 1.5);
        QuotationConfig::setValue('sctr_porcentaje', 1.5);
    }

    protected function tearDown(): void
    {
        try {
            QuotationConfig::setValue('sctr_porcentaje', $this->previousSctrConfig);
        } catch (\Exception $e) {}
        try {
            foreach ($this->cleanupPrequoteIds as $pid) {
                PreQuoteItem::where('prequote_id', $pid)->delete();
                PreQuote::where('id', $pid)->delete();
            }
        } catch (\Exception $e) {}
        parent::tearDown();
    }

    /**
     * Crea una prequote borrador con un ítem simple (costo 100, markup 0, Q 10).
     * Con SCTR 1.5% da valor_total 1015; con 3% da 1030; con 0% da 1000.
     */
    private function makePrequoteConItem()
    {
        $pq = PreQuote::create([
            'version' => 1,
            'estado'  => PreQuote::ESTADO_BORRADOR,
            'titulo'  => 'TEST sctr endpoint',
        ]);
        $this->cleanupPrequoteIds[] = $pq->id;

        PreQuoteItem::create([
            'prequote_id'       => $pq->id,
            'product_name'      => 'Producto SCTR',
            'quantity'          => 10,
            'costo_base'        => 100,
            'margen_porcentaje' => 0,
        ]);

        return $pq;
    }

    /** @test */
    public function fijar_sctr_propio_recalcula_los_totales_con_ese_porcentaje()
    {
        $pq = $this->makePrequoteConItem();

        $resp = $this->putJson("/api/marketing/quotation/prequotes/{$pq->id}/sctr", [
            'sctr_porcentaje' => 3,
        ]);

        if (in_array($resp->status(), [401, 419])) {
            $this->markTestSkipped('Auth requerida — saltando test HTTP en este entorno.');
        }

        $resp->assertStatus(200);
        $this->assertTrue($resp->json('ok'));

        // El % propio quedó guardado y la respuesta lo expone.
        $this->assertEquals(3.0, (float) $resp->json('prequote.sctr_porcentaje'));

        // Recalculó: item con SCTR 3% → valor_total 1030 (era 1015 con 1.5%).
        $itemTotal = (float) $resp->json('prequote.items.0.valor_total');
        $this->assertEquals(1030.00, $itemTotal,
            'El total del ítem debe reflejar el SCTR propio (3%)');
    }

    /** @test */
    public function la_respuesta_expone_el_default_del_config_para_precargar_la_ui()
    {
        $pq = $this->makePrequoteConItem();

        $resp = $this->putJson("/api/marketing/quotation/prequotes/{$pq->id}/sctr", [
            'sctr_porcentaje' => 2,
        ]);

        if (in_array($resp->status(), [401, 419])) {
            $this->markTestSkipped('Auth requerida — saltando test HTTP en este entorno.');
        }

        $resp->assertStatus(200);
        // sctr_config_default = global del config (1.5), para la precarga del input.
        $this->assertEquals(1.5, (float) $resp->json('prequote.sctr_config_default'));
    }

    /** @test */
    public function enviar_null_resetea_al_sctr_global_del_config()
    {
        $pq = $this->makePrequoteConItem();

        // Primero fijamos 5%…
        $this->putJson("/api/marketing/quotation/prequotes/{$pq->id}/sctr", [
            'sctr_porcentaje' => 5,
        ]);

        // …y ahora reseteamos con null.
        $resp = $this->putJson("/api/marketing/quotation/prequotes/{$pq->id}/sctr", [
            'sctr_porcentaje' => null,
        ]);

        if (in_array($resp->status(), [401, 419])) {
            $this->markTestSkipped('Auth requerida — saltando test HTTP en este entorno.');
        }

        $resp->assertStatus(200);
        $this->assertNull($resp->json('prequote.sctr_porcentaje'),
            'null debe volver el sctr propio a NULL');

        // Vuelve a usar el global (1.5%) → item valor_total 1015.
        $itemTotal = (float) $resp->json('prequote.items.0.valor_total');
        $this->assertEquals(1015.00, $itemTotal,
            'Con sctr NULL el total vuelve al del SCTR global (1015)');
    }

    /** @test */
    public function rechaza_sctr_fuera_de_rango()
    {
        $pq = $this->makePrequoteConItem();

        $resp = $this->putJson("/api/marketing/quotation/prequotes/{$pq->id}/sctr", [
            'sctr_porcentaje' => 15,  // > 10 → inválido
        ]);

        if (in_array($resp->status(), [401, 419])) {
            $this->markTestSkipped('Auth requerida — saltando test HTTP en este entorno.');
        }

        $resp->assertStatus(422);
    }

    /*
    |--------------------------------------------------------------------------
    | SCTR unitario (override por ítem) — endpoint updateItem
    |--------------------------------------------------------------------------
    */

    /** @test */
    public function actualizar_item_con_sctr_propio_lo_aplica_y_expone_el_efectivo()
    {
        // updateItem acepta sctr_porcentaje por ítem; se aplica ese % a ese ítem y
        // la respuesta expone item.sctr_porcentaje (propio) + sctr_porcentaje_efectivo.
        $pq   = $this->makePrequoteConItem();  // item costo 100, markup 0, Q 10
        $item = $pq->items()->first();

        $resp = $this->putJson("/api/marketing/quotation/prequotes/{$pq->id}/item/{$item->id}", [
            'product_name'      => $item->product_name,
            'quantity'          => 10,
            'costo_base'        => 100,
            'margen_porcentaje' => 0,
            'sctr_porcentaje'   => 2,   // override del ítem
        ]);

        if (in_array($resp->status(), [401, 419])) {
            $this->markTestSkipped('Auth requerida — saltando test HTTP en este entorno.');
        }

        $resp->assertStatus(200);

        $it = $resp->json('prequote.items.0');
        $this->assertEquals(2.0, (float) $it['sctr_porcentaje'],
            'El ítem guarda su SCTR propio (2%)');
        $this->assertEquals(2.0, (float) $it['sctr_porcentaje_efectivo'],
            'El efectivo del ítem es su propio 2%');
        // TP=1000, SCTR=2%×1000=20 → valor_total 1020 (vs 1015 con el global 1.5%).
        $this->assertEquals(1020.00, (float) $it['valor_total'],
            'El total del ítem refleja su SCTR propio (2%)');
    }

    /** @test */
    public function item_sin_sctr_expone_efectivo_heredado_de_la_cotizacion()
    {
        // Con SCTR de cotización = 3% y el ítem sin override (null), el efectivo
        // del ítem debe ser 3% (heredado), y su % propio null.
        $pq = $this->makePrequoteConItem();
        // Fijar el SCTR de la cotización a 3%.
        $this->putJson("/api/marketing/quotation/prequotes/{$pq->id}/sctr", ['sctr_porcentaje' => 3]);

        $resp = $this->getJson("/api/marketing/quotation/prequotes/{$pq->id}");

        if (in_array($resp->status(), [401, 419])) {
            $this->markTestSkipped('Auth requerida — saltando test HTTP en este entorno.');
        }

        $resp->assertStatus(200);
        $it = $resp->json('prequote.items.0');
        $this->assertNull($it['sctr_porcentaje'],
            'El ítem no tiene SCTR propio (null)');
        $this->assertEquals(3.0, (float) $it['sctr_porcentaje_efectivo'],
            'El efectivo del ítem hereda el de la cotización (3%)');
    }
}
