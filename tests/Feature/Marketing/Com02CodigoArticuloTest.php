<?php

namespace Tests\Feature\Marketing;

use Hyn\Tenancy\Environment;
use Hyn\Tenancy\Models\Website;
use Illuminate\Support\Facades\DB;
use Modules\Marketing\Models\PreQuote;
use Modules\Marketing\Models\PreQuoteItem;
use Modules\Marketing\Models\QuotationProposal;
use Modules\Marketing\Services\ProposalPdfGenerator;
use Tests\TestCase;

/**
 * Com02CodigoArticuloTest — "Cód. Artíc." editable por línea de cotización.
 *
 * El código lleva las iniciales del proveedor y el proveedor puede cambiar, así
 * que la ejecutiva necesita poder corregirlo — incluso en una cotización ya
 * aprobada. Se guarda como override por línea (NUNCA se toca el catálogo
 * global, que es compartido entre cotizaciones).
 *
 * Cubre:
 *   - La cascada del código efectivo: manual → catálogo → ''.
 *   - Que los 'COM02-ADHOC-…' (marca interna de la conversión) no se muestren.
 *   - El endpoint dedicado: normaliza, vacía a NULL, valida el largo, funciona
 *     sin candado de estado y NO recalcula los totales.
 *   - La limpieza del código al CAMBIAR de proveedor (y su conservación al
 *     asignar el primero).
 *   - Que el PDF imprima el override.
 */
class Com02CodigoArticuloTest extends TestCase
{
    /** @var array IDs de prequotes creadas para limpiar */
    private $cleanupPrequoteIds = [];

    /** @var array IDs de items de catálogo creados para limpiar */
    private $cleanupItemIds = [];

    protected function setUp(): void
    {
        parent::setUp();

        config(['database.connections.system.database' => 'dberp']);
        config(['database.connections.mysql.database'  => 'dberp']);
        DB::purge('system');
        DB::purge('mysql');

        $website = Website::first();
        if (!$website) {
            $this->markTestSkipped('Sin tenants registrados');
        }
        app(Environment::class)->tenant($website);

        // El cache de códigos del catálogo es estático y vive lo que dura el
        // proceso: entre tests hay que vaciarlo o un id reutilizado devolvería
        // el código de un fixture anterior.
        PreQuoteItem::flushInternalIdCache();
    }

    protected function tearDown(): void
    {
        try {
            foreach ($this->cleanupPrequoteIds as $pid) {
                PreQuoteItem::where('prequote_id', $pid)->delete();
                // QuotationProposal usa borrado lógico: con delete() la fila
                // sobrevive y su `numero_cotizacion` (UNIQUE) seguiría ocupado
                // en la próxima corrida. Se borra de verdad.
                DB::connection('tenant')->table('quotation_proposals')->where('prequote_id', $pid)->delete();
                PreQuote::where('id', $pid)->delete();
            }
        } catch (\Exception $e) {}
        try {
            if (!empty($this->cleanupItemIds)) {
                DB::connection('tenant')->table('items')->whereIn('id', $this->cleanupItemIds)->delete();
            }
        } catch (\Exception $e) {}
        PreQuoteItem::flushInternalIdCache();
        parent::tearDown();
    }

    /*
    |--------------------------------------------------------------------------
    | Cascada del código efectivo (modelo)
    |--------------------------------------------------------------------------
    */

    /** El override manual gana sobre el código del catálogo. */
    public function test_el_codigo_efectivo_prefiere_el_manual_sobre_el_del_catalogo()
    {
        $catId = $this->makeCatalogItem('CAT-001');
        $item = $this->makePrequoteConItem($catId, 'MI-COD');

        $this->assertSame('MI-COD', $item->codigo_articulo);
        $this->assertSame('CAT-001', $item->codigo_articulo_catalogo);
    }

    /** Sin override, el ítem hereda el código del catálogo. */
    public function test_el_codigo_efectivo_cae_al_catalogo_si_no_hay_manual()
    {
        $catId = $this->makeCatalogItem('CAT-001');
        $item = $this->makePrequoteConItem($catId, null);

        $this->assertSame('CAT-001', $item->codigo_articulo);
    }

    /**
     * Los 'COM02-ADHOC-…' son una marca de trazabilidad interna que genera la
     * conversión, no un código de artículo: no deben verse ni en el preview ni
     * en la propuesta del cliente.
     */
    public function test_los_codigos_adhoc_internos_no_se_exponen()
    {
        $catId = $this->makeCatalogItem('COM02-ADHOC-1-1');
        $item = $this->makePrequoteConItem($catId, null);

        $this->assertSame('', $item->codigo_articulo_catalogo);
        $this->assertSame('', $item->codigo_articulo);
    }

    /** Sin match de catálogo, el código efectivo es cadena vacía. */
    public function test_sin_match_de_catalogo_el_codigo_queda_vacio()
    {
        $item = $this->makePrequoteConItem(null, null);

        $this->assertSame('', $item->codigo_articulo);
        $this->assertSame('', $item->codigo_articulo_catalogo);
    }

    /*
    |--------------------------------------------------------------------------
    | Contrato HTTP
    |--------------------------------------------------------------------------
    */

    /**
     * Las tres claves salen por la API. Es lo que arregla el bug del preview:
     * antes la columna mostraba "—" siempre, porque ningún backend emitía
     * `codigo_articulo`.
     */
    public function test_las_tres_claves_salen_en_el_show()
    {
        $catId = $this->makeCatalogItem('CAT-001');
        $item = $this->makePrequoteConItem($catId, 'MI-COD');

        $resp = $this->getJson("/api/marketing/quotation/prequotes/{$item->prequote_id}");
        if ($this->authSkipped($resp)) return;

        $resp->assertStatus(200);
        $this->assertSame('MI-COD',  $resp->json('prequote.items.0.codigo_articulo_manual'));
        $this->assertSame('CAT-001', $resp->json('prequote.items.0.codigo_articulo_catalogo'));
        $this->assertSame('MI-COD',  $resp->json('prequote.items.0.codigo_articulo'));
    }

    /** El endpoint normaliza: recorta espacios y pasa a mayúsculas. */
    public function test_el_endpoint_normaliza_trim_y_mayusculas()
    {
        $item = $this->makePrequoteConItem(null, null);

        $resp = $this->putJson($this->codigoUrl($item), ['codigo_articulo_manual' => '  ab-12  ']);
        if ($this->authSkipped($resp)) return;

        $resp->assertStatus(200);
        $this->assertSame('AB-12', $item->fresh()->codigo_articulo_manual);
    }

    /** Vaciar el código lo deja en NULL y el ítem vuelve al del catálogo. */
    public function test_el_endpoint_vacia_a_null_y_vuelve_al_catalogo()
    {
        $catId = $this->makeCatalogItem('CAT-001');
        $item = $this->makePrequoteConItem($catId, 'MI-COD');

        $resp = $this->putJson($this->codigoUrl($item), ['codigo_articulo_manual' => '']);
        if ($this->authSkipped($resp)) return;

        $resp->assertStatus(200);
        $fresh = $item->fresh();
        $this->assertNull($fresh->codigo_articulo_manual, 'Vacío debe guardarse como NULL, no como cadena vacía');
        $this->assertSame('CAT-001', $fresh->codigo_articulo);
    }

    /**
     * El tope no es el de la columna (30) sino el que aguanta la columna del PDF:
     * un código más largo la ensancha y le roba ancho a "Detalles".
     */
    public function test_el_endpoint_rechaza_codigos_demasiado_largos()
    {
        $item = $this->makePrequoteConItem(null, null);

        $resp = $this->putJson($this->codigoUrl($item), [
            'codigo_articulo_manual' => str_repeat('X', 13),
        ]);
        if ($this->authSkipped($resp)) return;

        $resp->assertStatus(422);
        $this->assertNull($item->fresh()->codigo_articulo_manual);
    }

    /**
     * El requisito central: el código se edita aun con la cotización aprobada,
     * mientras que el resto de campos del ítem sigue bloqueado.
     */
    public function test_el_codigo_se_edita_sin_candado_de_estado()
    {
        $item = $this->makePrequoteConItem(null, null);
        PreQuote::where('id', $item->prequote_id)->update(['estado' => PreQuote::ESTADO_APROBADA]);

        // El endpoint del código: pasa.
        $resp = $this->putJson($this->codigoUrl($item), ['codigo_articulo_manual' => 'APROB-1']);
        if ($this->authSkipped($resp)) return;

        $resp->assertStatus(200);
        $this->assertSame('APROB-1', $item->fresh()->codigo_articulo_manual);

        // El update genérico del ítem: sigue bloqueado.
        $otro = $this->putJson(
            "/api/marketing/quotation/prequotes/{$item->prequote_id}/item/{$item->id}",
            ['product_name' => 'No deberia guardarse']
        );
        $otro->assertStatus(422);
    }

    /**
     * Guardar el código NO debe recalcular: si recalculara, editar una etiqueta
     * movería los totales de una cotización ya aprobada.
     */
    public function test_el_endpoint_no_recalcula_los_totales()
    {
        $item = $this->makePrequoteConItem(null, null);

        // Total deliberadamente inconsistente: si hubiera recálculo, se pisaría.
        PreQuote::where('id', $item->prequote_id)->update(['total' => 999]);

        $resp = $this->putJson($this->codigoUrl($item), ['codigo_articulo_manual' => 'X1']);
        if ($this->authSkipped($resp)) return;

        $resp->assertStatus(200);
        $this->assertEquals(999, (float) PreQuote::find($item->prequote_id)->total);
        // La respuesta devuelve solo el ítem: evidencia de que no recalculó.
        $this->assertNull($resp->json('prequote'));
    }

    /*
    |--------------------------------------------------------------------------
    | Relación con el proveedor
    |--------------------------------------------------------------------------
    */

    /** Al CAMBIAR de proveedor, el código (que lleva sus iniciales) se limpia. */
    public function test_cambiar_de_proveedor_limpia_el_codigo_manual()
    {
        $item = $this->makePrequoteConItem(null, 'PROV-A1');
        $item->update(['supplier_id' => 10]);

        $resp = $this->putJson(
            "/api/marketing/quotation/prequotes/{$item->prequote_id}/item/{$item->id}",
            ['supplier_id' => 20]
        );
        if ($this->authSkipped($resp)) return;

        $resp->assertStatus(200);
        $this->assertNull($item->fresh()->codigo_articulo_manual);
    }

    /**
     * Al asignar el PRIMER proveedor se conserva: lo habitual es completar el
     * preview (y escribir el código) antes de elegir proveedor.
     */
    public function test_asignar_el_primer_proveedor_conserva_el_codigo_manual()
    {
        $item = $this->makePrequoteConItem(null, 'PROV-A1');
        $this->assertNull($item->supplier_id, 'El ítem nace sin proveedor');

        $resp = $this->putJson(
            "/api/marketing/quotation/prequotes/{$item->prequote_id}/item/{$item->id}",
            ['supplier_id' => 20]
        );
        if ($this->authSkipped($resp)) return;

        $resp->assertStatus(200);
        $this->assertSame('PROV-A1', $item->fresh()->codigo_articulo_manual);
    }

    /** Guardar el MISMO proveedor no debe borrar el código. */
    public function test_guardar_el_mismo_proveedor_conserva_el_codigo_manual()
    {
        $item = $this->makePrequoteConItem(null, 'PROV-A1');
        $item->update(['supplier_id' => 10]);

        $resp = $this->putJson(
            "/api/marketing/quotation/prequotes/{$item->prequote_id}/item/{$item->id}",
            ['supplier_id' => 10]
        );
        if ($this->authSkipped($resp)) return;

        $resp->assertStatus(200);
        $this->assertSame('PROV-A1', $item->fresh()->codigo_articulo_manual);
    }

    /*
    |--------------------------------------------------------------------------
    | PDF
    |--------------------------------------------------------------------------
    */

    /** El PDF imprime el override manual en la columna "Cód. Artíc.". */
    public function test_el_pdf_imprime_el_codigo_manual()
    {
        $catId = $this->makeCatalogItem('CAT-001');
        $item = $this->makePrequoteConItem($catId, 'MI-COD');

        $proposal = QuotationProposal::create([
            'prequote_id'        => $item->prequote_id,
            'prefijo_numeracion' => 'MAP',
            'fecha_emision'      => date('Y-m-d'),
            'secuencial_dia'     => 99,
            'version_externa'    => 1,
            // numero_cotizacion es UNIQUE: se ata al id de la prequote (siempre
            // distinto) para que dos corridas no choquen entre sí.
            'numero_cotizacion'  => 'TEST-COD-' . $item->prequote_id,
            'moneda'             => 'PEN',
            'estado'             => QuotationProposal::ESTADO_BORRADOR,
        ]);

        $gen = new ProposalPdfGenerator();
        $ref = new \ReflectionMethod($gen, 'buildViewData');
        $ref->setAccessible(true);
        $data = $ref->invoke($gen, $proposal->load('prequote.items'));

        $this->assertSame('MI-COD', $data['items'][0]['codigo']);
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    private function codigoUrl(PreQuoteItem $item)
    {
        return "/api/marketing/quotation/prequotes/{$item->prequote_id}/item/{$item->id}/codigo";
    }

    /** Los endpoints HTTP requieren auth en algunos entornos locales. */
    private function authSkipped($resp)
    {
        if (in_array($resp->status(), [401, 419, 302])) {
            $this->markTestSkipped('Auth requerida — saltando test HTTP en este entorno.');
            return true;
        }
        return false;
    }

    /**
     * Crear un ítem en el catálogo del ERP con INSERT directo.
     *
     * NO se usa Item::create: dispara los observers de inventario de Facturador,
     * que asumen contexto de auth/stock y fallan fuera de él.
     *
     * @return int
     */
    private function makeCatalogItem($internalId)
    {
        $id = DB::connection('tenant')->table('items')->insertGetId([
            'item_type_id'                     => '01',
            'unit_type_id'                     => 'NIU',
            'currency_type_id'                 => 'PEN',
            'sale_affectation_igv_type_id'     => '10',
            'purchase_affectation_igv_type_id' => '10',
            'sale_unit_price'                  => 10,
            'name'                             => 'Producto test codigo',
            'description'                      => 'Producto test codigo',
            'internal_id'                      => $internalId,
            'active'                           => 1,
            'stock'                            => 0,
            'created_at'                       => now(),
            'updated_at'                       => now(),
        ]);

        $this->cleanupItemIds[] = $id;

        return $id;
    }

    /** Prequote borrador con un ítem. */
    private function makePrequoteConItem($catalogItemId, $codigoManual)
    {
        $pq = PreQuote::create([
            'version' => 1,
            'estado'  => PreQuote::ESTADO_BORRADOR,
            'titulo'  => 'TEST codigo articulo',
        ]);
        $this->cleanupPrequoteIds[] = $pq->id;

        return PreQuoteItem::create([
            'prequote_id'            => $pq->id,
            'item_order'             => 1,
            'item_id'                => $catalogItemId,
            'codigo_articulo_manual' => $codigoManual,
            'product_name'           => 'Producto codigo',
            'quantity'               => 10,
            'costo_base'             => 100,
            'margen_porcentaje'      => 0,
        ]);
    }
}
