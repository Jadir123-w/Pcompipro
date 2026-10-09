<?php

namespace Tests\Feature\Marketing;

use Hyn\Tenancy\Environment;
use Hyn\Tenancy\Models\Website;
use Modules\Marketing\Models\PreQuote;
use Modules\Marketing\Models\PreQuoteItem;
use Modules\Marketing\Models\QuotationConfig;
use Modules\Marketing\Services\QuotationCalculationEngine;
use Tests\TestCase;

/**
 * Com02MotorCosteoTest — Motor de costeo ampliado (Fase A).
 *
 * Valida la cadena de cálculo reconstruida a partir del Excel real de
 * COMPIPRO (ProcesoProyecto_BSF, pestaña Cotizacion1):
 *
 *   T     = costo_base × (1 + markup%)            [solo el producto lleva markup]
 *   TP    = (Q × T) + muestra + movilidad + adicionales
 *   impr  = impresion_por_unidad ? costo_impresion × Q : costo_impresion
 *   emp   = empaque_por_unidad   ? costo_empaque   × Q : costo_empaque
 *   SCTR  = sctr% × (TP + impr + emp)
 *   Total = TP + impr + emp + SCTR
 *   unit  = Total / Q
 *
 * Nota sobre precisión: el Excel redondea valores intermedios (ej. Re a 2
 * decimales) por visualización. Nuestro motor acumula precisión completa y
 * redondea solo al final (mejor práctica de costeo). Por eso la validación
 * contra el Excel usa una tolerancia de centavos.
 */
class Com02MotorCosteoTest extends TestCase
{
    /** @var QuotationCalculationEngine */
    private $engine;

    /** @var array */
    private $previousConfig = [];

    /** @var array IDs de prequotes creadas para limpiar */
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

        // Asegurar IGV=18 y SCTR=1.5 para cálculos determinísticos
        $this->previousConfig['igv_percentage'] = QuotationConfig::getValue('igv_percentage', 18);
        $this->previousConfig['sctr_porcentaje'] = QuotationConfig::getValue('sctr_porcentaje', 1.5);
        QuotationConfig::setValue('igv_percentage', 18);
        QuotationConfig::setValue('sctr_porcentaje', 1.5);

        $this->engine = new QuotationCalculationEngine();
    }

    protected function tearDown(): void
    {
        try {
            QuotationConfig::setValue('igv_percentage', $this->previousConfig['igv_percentage']);
            QuotationConfig::setValue('sctr_porcentaje', $this->previousConfig['sctr_porcentaje']);
        } catch (\Exception $e) {}
        try {
            foreach ($this->cleanupPrequoteIds as $pid) {
                PreQuoteItem::where('prequote_id', $pid)->delete();
                PreQuote::where('id', $pid)->delete();
            }
        } catch (\Exception $e) {}
        parent::tearDown();
    }

    /*
    |--------------------------------------------------------------------------
    | Validación contra datos reales del Excel BSF
    |--------------------------------------------------------------------------
    */

    /** @test */
    public function valida_fila_pines_del_excel_bsf()
    {
        // Datos reales de la fila "Pines corporativos":
        //   Co=10.67, markup=146%, Q=30, muestra=10.67, movilidad=80,
        //   impresión=0, empaque=3.50 (global), SCTR=1.5%
        // Excel: Total sin IGV = 894.59 | costo unit sin IGV = 29.82
        $r = $this->engine->computeItem(
            10.67,   // costo_base (Co)
            146,     // markup %
            30,      // cantidad
            0,       // costos_adicionales
            10.67,   // costo_muestra
            80.00,   // costo_movilidad
            0,       // costo_impresion
            3.50,    // costo_empaque
            false,   // impresion_por_unidad
            false    // empaque_por_unidad
        );

        // Tolerancia ±1 sol por el redondeo intermedio del Excel
        $this->assertEqualsWithDelta(894.59, $r['valor_total'], 1.00,
            'Total del ítem sin IGV debe coincidir con el Excel (±1 por redondeo)');
        $this->assertEqualsWithDelta(29.82, $r['valor_unitario'], 0.10,
            'Costo unitario sin IGV debe coincidir con el Excel');
    }

    /*
    |--------------------------------------------------------------------------
    | COM09 — Costo de muestra derivado (N° muestras × costo base)
    |--------------------------------------------------------------------------
    */

    /** @test */
    public function costo_muestra_se_deriva_de_numero_muestras_por_costo_base()
    {
        // COM09: 3 muestras × S/ 18.00 (costo base) = S/ 54.00.
        $pq = PreQuote::create([
            'version' => 1, 'estado' => PreQuote::ESTADO_BORRADOR, 'titulo' => 'TEST muestras',
        ]);
        $this->cleanupPrequoteIds[] = $pq->id;

        $item = PreQuoteItem::create([
            'prequote_id'       => $pq->id,
            'product_name'      => 'Producto con muestras',
            'quantity'          => 10,
            'costo_base'        => 18,
            'margen_porcentaje' => 30,
            'numero_muestras'   => 3,
        ]);

        $fresh = $this->engine->recalculateItem($item);

        $this->assertEquals(54.00, (float) $fresh->costo_muestra,
            '3 muestras × S/18 (costo base) = S/54, derivado y persistido');
    }

    /** @test */
    public function sin_numero_muestras_el_costo_muestra_es_cero()
    {
        // "No todos los productos tendrán muestras": numero_muestras=0 → costo 0.
        $pq = PreQuote::create([
            'version' => 1, 'estado' => PreQuote::ESTADO_BORRADOR, 'titulo' => 'TEST sin muestras',
        ]);
        $this->cleanupPrequoteIds[] = $pq->id;

        $item = PreQuoteItem::create([
            'prequote_id'       => $pq->id,
            'product_name'      => 'Producto sin muestras',
            'quantity'          => 10,
            'costo_base'        => 18,
            'margen_porcentaje' => 30,
            'numero_muestras'   => 0,
        ]);

        $fresh = $this->engine->recalculateItem($item);

        $this->assertEquals(0.00, (float) $fresh->costo_muestra,
            '0 muestras → costo de muestra 0');
    }

    /*
    |--------------------------------------------------------------------------
    | Cálculo exacto (sin redondeos intermedios de Excel)
    |--------------------------------------------------------------------------
    */

    /** @test */
    public function caso_simple_solo_producto_y_markup()
    {
        // costo 100, markup 30%, Q 10, sin componentes extra
        // T = 130 | TP = 1300 | SCTR = 1.5%×1300 = 19.50 | Total = 1319.50
        $r = $this->engine->computeItem(100, 30, 10);

        $this->assertEquals(1319.50, $r['valor_total']);
        $this->assertEquals(131.95, $r['valor_unitario']);  // 1319.50 / 10
        $this->assertEquals(19.50, $r['sctr']);
        // IGV = 1319.50 × 18% = 237.51 | Total con IGV = 1557.01
        $this->assertEquals(237.51, $r['igv']);
        $this->assertEquals(1557.01, $r['total_item']);
    }

    /** @test */
    public function muestra_y_movilidad_son_globales_no_se_multiplican_por_cantidad()
    {
        // costo 10, markup 0, Q 100, muestra 50, movilidad 80
        // T = 10 | costos_fijos = 130 | TP = 100×10 + 130 = 1130
        // SCTR = 1.5%×1130 = 16.95 | Total = 1146.95
        $r = $this->engine->computeItem(10, 0, 100, 0, 50, 80);

        $this->assertEquals(1146.95, $r['valor_total'],
            'muestra(50) + movilidad(80) deben sumarse UNA vez, no × 100');
    }

    /** @test */
    public function empaque_global_vs_unitario()
    {
        // Empaque 5, markup 0, Q 10, costo 100
        // GLOBAL: empaque=5 → TP=1000, SCTR=1.5%×1005=15.075, Total=1020.08
        $global = $this->engine->computeItem(100, 0, 10, 0, 0, 0, 0, 5, false, false);
        $this->assertEqualsWithDelta(1020.08, $global['valor_total'], 0.01);

        // UNITARIO: empaque=5×10=50 → TP=1000, SCTR=1.5%×1050=15.75, Total=1065.75
        $unit = $this->engine->computeItem(100, 0, 10, 0, 0, 0, 0, 5, false, true);
        $this->assertEquals(1065.75, $unit['valor_total']);

        $this->assertGreaterThan($global['valor_total'], $unit['valor_total'],
            'El empaque unitario (×Q) debe dar un total mayor que el global');
    }

    /** @test */
    public function impresion_global_vs_unitaria()
    {
        // Impresión 8, markup 0, Q 20, costo 50
        // GLOBAL: impr=8 → TP=1000, SCTR=1.5%×1008=15.12, Total=1023.12
        $global = $this->engine->computeItem(50, 0, 20, 0, 0, 0, 8, 0, false, false);
        $this->assertEqualsWithDelta(1023.12, $global['valor_total'], 0.01);

        // UNITARIO: impr=8×20=160 → TP=1000, SCTR=1.5%×1160=17.40, Total=1177.40
        $unit = $this->engine->computeItem(50, 0, 20, 0, 0, 0, 8, 0, true, false);
        $this->assertEquals(1177.40, $unit['valor_total']);
    }

    /** @test */
    public function markup_solo_aplica_al_producto_no_a_los_costos_fijos()
    {
        // costo 100, markup 100% (duplica), Q 1, muestra 50
        // T = 200 (producto con markup) | TP = 1×200 + 50 = 250
        // El markup NO se aplica a la muestra: la muestra entra a costo puro (50).
        // SCTR = 1.5%×250 = 3.75 | Total = 253.75
        $r = $this->engine->computeItem(100, 100, 1, 0, 50);

        $this->assertEquals(253.75, $r['valor_total']);
        // Si el markup se hubiera aplicado también a la muestra, sería:
        // (100+50)×2 = 300 + SCTR. Verificamos que NO es así.
        $this->assertLessThan(300, $r['valor_total'],
            'El markup no debe aplicarse a la muestra (costo fijo)');
    }

    /** @test */
    public function sctr_es_configurable()
    {
        // Subir SCTR a 5% y verificar el efecto
        QuotationConfig::setValue('sctr_porcentaje', 5);
        $engine = new QuotationCalculationEngine();  // re-instanciar para re-leer config

        // costo 100, markup 0, Q 10 → TP=1000, SCTR=5%×1000=50, Total=1050
        $r = $engine->computeItem(100, 0, 10);
        $this->assertEquals(50.00, $r['sctr']);
        $this->assertEquals(1050.00, $r['valor_total']);
    }

    /** @test */
    public function ganancia_absoluta_se_calcula_correctamente()
    {
        // costo 10, markup 50%, Q 20
        // ganancia unitaria = 10×0.5 = 5 | ganancia total = 5×20 = 100
        $r = $this->engine->computeItem(10, 50, 20);
        $this->assertEquals(100.00, $r['ganancia_absoluta']);
    }

    /** @test */
    public function el_item_expone_ganancia_absoluta_igual_que_el_motor()
    {
        // R4 — el accesor del ítem debe exponer el mismo valor que computeItem.
        // Mismo caso: costo 10, markup 50%, Q 20 → ganancia 100.
        $item = new PreQuoteItem([
            'quantity'          => 20,
            'costo_base'        => 10,
            'margen_porcentaje' => 50,
        ]);

        $this->assertEquals(100.00, (float) $item->ganancia_absoluta);

        // Debe coincidir exactamente con lo que expone el motor.
        $r = $this->engine->computeItem(10, 50, 20);
        $this->assertEquals($r['ganancia_absoluta'], (float) $item->ganancia_absoluta);

        // Y debe estar en la serialización (appends), para que el front lo lea.
        $this->assertArrayHasKey('ganancia_absoluta', $item->toArray());
    }

    /** @test */
    public function costos_adicionales_json_son_fijos_sin_markup()
    {
        // costo 100, markup 100%, Q 1, adicionales 30
        // T = 200 | TP = 200 + 30 = 230 (adicionales sin markup)
        // SCTR = 1.5%×230 = 3.45 | Total = 233.45
        $r = $this->engine->computeItem(100, 100, 1, 30);
        $this->assertEquals(233.45, $r['valor_total']);
    }

    /*
    |--------------------------------------------------------------------------
    | COM-02 — SCTR editable por cotización
    |--------------------------------------------------------------------------
    | El SCTR depende de la condición de pago, así que es por cotización. Cada
    | cotización puede fijar su propio %; si no lo fija (NULL) usa el global del
    | config. La regresión clave: NULL debe dar EXACTAMENTE el mismo total que hoy.
    */

    /** @test */
    public function computeItem_acepta_sctr_por_parametro_y_prevalece_sobre_el_config()
    {
        // Config global = 1.5%, pero pasamos 3% por parámetro.
        // costo 100, markup 0, Q 10 → TP=1000, SCTR=3%×1000=30, Total=1030
        $r = $this->engine->computeItem(
            100, 0, 10,
            0, 0, 0, 0, 0, false, false,
            3.0   // sctr% explícito de la cotización
        );

        $this->assertEquals(30.00, $r['sctr'],
            'El SCTR por parámetro (3%) debe prevalecer sobre el config (1.5%)');
        $this->assertEquals(1030.00, $r['valor_total']);
    }

    /** @test */
    public function computeItem_sin_sctr_usa_el_del_config()
    {
        // Sin pasar sctr (null) → usa el global del config (1.5%).
        // costo 100, markup 0, Q 10 → TP=1000, SCTR=1.5%×1000=15, Total=1015
        $r = $this->engine->computeItem(100, 0, 10);   // sctr param omitido → null
        $this->assertEquals(15.00, $r['sctr']);
        $this->assertEquals(1015.00, $r['valor_total']);
    }

    /** @test */
    public function computeItem_sctr_cero_explicito_no_cae_al_config()
    {
        // sctr=0 EXPLÍCITO debe aplicar 0, NO caer al global (1.5%).
        // costo 100, markup 0, Q 10 → TP=1000, SCTR=0, Total=1000
        $r = $this->engine->computeItem(
            100, 0, 10,
            0, 0, 0, 0, 0, false, false,
            0.0
        );
        $this->assertEquals(0.00, $r['sctr'], 'sctr=0 explícito no debe caer al config');
        $this->assertEquals(1000.00, $r['valor_total']);
    }

    /** @test */
    public function prequote_con_sctr_null_da_exactamente_el_mismo_total_que_hoy()
    {
        // REGRESIÓN: una prequote con sctr_porcentaje = NULL debe dar EXACTAMENTE
        // el mismo total que con el SCTR global (1.5%). Sin cambio alguno.
        $pq = PreQuote::create([
            'version' => 1, 'estado' => PreQuote::ESTADO_BORRADOR, 'titulo' => 'TEST sctr null',
        ]);
        $this->cleanupPrequoteIds[] = $pq->id;

        // El cast decimal:2 debe PRESERVAR el NULL (no volverlo 0.00).
        $this->assertNull($pq->fresh()->sctr_porcentaje,
            'Una prequote nueva nace con sctr NULL y el cast lo preserva');

        $item = PreQuoteItem::create([
            'prequote_id'       => $pq->id,
            'product_name'      => 'Producto SCTR null',
            'quantity'          => 10,
            'costo_base'        => 100,
            'margen_porcentaje' => 0,
        ]);

        $this->engine->recalculate($pq);

        // Con SCTR global 1.5%: TP=1000, SCTR=15, total sin IGV=1015.
        $this->assertEquals(1015.00, (float) $item->fresh()->valor_total,
            'sctr NULL → total idéntico al del SCTR global (retrocompatible)');
    }

    /** @test */
    public function prequote_con_sctr_propio_se_aplica_a_sus_items()
    {
        // Una prequote con sctr_porcentaje=3 aplica 3% (no el global 1.5%).
        $pq = PreQuote::create([
            'version' => 1, 'estado' => PreQuote::ESTADO_BORRADOR, 'titulo' => 'TEST sctr propio',
            'sctr_porcentaje' => 3,
        ]);
        $this->cleanupPrequoteIds[] = $pq->id;

        $item = PreQuoteItem::create([
            'prequote_id'       => $pq->id,
            'product_name'      => 'Producto SCTR 3%',
            'quantity'          => 10,
            'costo_base'        => 100,
            'margen_porcentaje' => 0,
        ]);

        $this->engine->recalculate($pq);

        // TP=1000, SCTR=3%×1000=30, total sin IGV=1030.
        $this->assertEquals(1030.00, (float) $item->fresh()->valor_total,
            'El SCTR propio de la cotización (3%) debe aplicarse a sus ítems');
    }

    /** @test */
    public function recalculateItem_suelto_resuelve_el_sctr_de_su_prequote()
    {
        // recalculateItem también se invoca fuera de recalculate(PreQuote)
        // (p. ej. al editar un solo ítem): debe resolver el SCTR leyendo la
        // prequote del propio ítem, no el global.
        $pq = PreQuote::create([
            'version' => 1, 'estado' => PreQuote::ESTADO_BORRADOR, 'titulo' => 'TEST sctr suelto',
            'sctr_porcentaje' => 5,
        ]);
        $this->cleanupPrequoteIds[] = $pq->id;

        $item = PreQuoteItem::create([
            'prequote_id'       => $pq->id,
            'product_name'      => 'Producto SCTR suelto',
            'quantity'          => 10,
            'costo_base'        => 100,
            'margen_porcentaje' => 0,
        ]);

        // Sin setRelation manual: recalculateItem debe cargar la prequote del ítem.
        $fresh = $this->engine->recalculateItem($item);

        // TP=1000, SCTR=5%×1000=50, total sin IGV=1050.
        $this->assertEquals(1050.00, (float) $fresh->valor_total,
            'recalculateItem debe resolver el SCTR de la prequote del ítem');
    }

    /*
    |--------------------------------------------------------------------------
    | COM-02 — SCTR UNITARIO (override por ítem: cascada ítem → cotización → global)
    |--------------------------------------------------------------------------
    | El cliente pidió SCTR distinto por producto. El % efectivo de cada ítem se
    | resuelve por cascada; si ningún ítem tiene % propio, el total es idéntico al
    | de R3 (SCTR por cotización) y al de hoy.
    */

    /** @test */
    public function item_con_sctr_propio_prevalece_sobre_el_de_la_cotizacion()
    {
        // Cotización con SCTR 1.5%, pero el ítem fija 2% propio → aplica 2%.
        $pq = PreQuote::create([
            'version' => 1, 'estado' => PreQuote::ESTADO_BORRADOR, 'titulo' => 'TEST sctr item propio',
            'sctr_porcentaje' => 1.5,
        ]);
        $this->cleanupPrequoteIds[] = $pq->id;

        $item = PreQuoteItem::create([
            'prequote_id'       => $pq->id,
            'product_name'      => 'Producto 2%',
            'quantity'          => 10,
            'costo_base'        => 100,
            'margen_porcentaje' => 0,
            'sctr_porcentaje'   => 2,
        ]);

        $fresh = $this->engine->recalculateItem($item);

        // TP=1000, SCTR=2%×1000=20, total=1020 (no 1015 de la cotización).
        $this->assertEquals(1020.00, (float) $fresh->valor_total,
            'El SCTR propio del ítem (2%) debe pisar el de la cotización (1.5%)');
    }

    /** @test */
    public function item_sin_sctr_hereda_el_de_la_cotizacion()
    {
        // Ítem sin SCTR propio (NULL) → hereda el de la cotización (3%).
        $pq = PreQuote::create([
            'version' => 1, 'estado' => PreQuote::ESTADO_BORRADOR, 'titulo' => 'TEST sctr hereda cotizacion',
            'sctr_porcentaje' => 3,
        ]);
        $this->cleanupPrequoteIds[] = $pq->id;

        $item = PreQuoteItem::create([
            'prequote_id'       => $pq->id,
            'product_name'      => 'Producto hereda',
            'quantity'          => 10,
            'costo_base'        => 100,
            'margen_porcentaje' => 0,
            // sin sctr_porcentaje → NULL
        ]);

        $fresh = $this->engine->recalculateItem($item);

        // TP=1000, SCTR=3%×1000=30, total=1030.
        $this->assertEquals(1030.00, (float) $fresh->valor_total,
            'Un ítem sin SCTR propio hereda el de la cotización (3%)');
    }

    /** @test */
    public function item_y_cotizacion_sin_sctr_usan_el_global()
    {
        // Ni ítem ni cotización tienen SCTR → global del config (1.5%).
        $pq = PreQuote::create([
            'version' => 1, 'estado' => PreQuote::ESTADO_BORRADOR, 'titulo' => 'TEST sctr global item',
        ]);
        $this->cleanupPrequoteIds[] = $pq->id;

        $item = PreQuoteItem::create([
            'prequote_id'       => $pq->id,
            'product_name'      => 'Producto global',
            'quantity'          => 10,
            'costo_base'        => 100,
            'margen_porcentaje' => 0,
        ]);

        $fresh = $this->engine->recalculateItem($item);

        // TP=1000, SCTR=1.5%×1000=15, total=1015.
        $this->assertEquals(1015.00, (float) $fresh->valor_total,
            'Sin SCTR de ítem ni de cotización se usa el global (1.5%)');
    }

    /** @test */
    public function item_sctr_cero_explicito_no_hereda()
    {
        // Ítem con SCTR=0 explícito → aplica 0%, NO hereda la cotización (5%).
        $pq = PreQuote::create([
            'version' => 1, 'estado' => PreQuote::ESTADO_BORRADOR, 'titulo' => 'TEST sctr item cero',
            'sctr_porcentaje' => 5,
        ]);
        $this->cleanupPrequoteIds[] = $pq->id;

        $item = PreQuoteItem::create([
            'prequote_id'       => $pq->id,
            'product_name'      => 'Producto 0%',
            'quantity'          => 10,
            'costo_base'        => 100,
            'margen_porcentaje' => 0,
            'sctr_porcentaje'   => 0,
        ]);

        $fresh = $this->engine->recalculateItem($item);

        // TP=1000, SCTR=0, total=1000 (no 1050 de la cotización).
        $this->assertEquals(1000.00, (float) $fresh->valor_total,
            'sctr=0 explícito en el ítem no debe heredar la cotización');
    }

    /** @test */
    public function la_cascada_solo_pisa_el_item_con_sctr_propio()
    {
        // Cotización 1.5%. Dos ítems: A sin SCTR (hereda 1.5%), B con 2% propio.
        // Solo B cambia; A sigue con el de la cotización.
        $pq = PreQuote::create([
            'version' => 1, 'estado' => PreQuote::ESTADO_BORRADOR, 'titulo' => 'TEST cascada mixta',
            'sctr_porcentaje' => 1.5,
        ]);
        $this->cleanupPrequoteIds[] = $pq->id;

        $itemA = PreQuoteItem::create([
            'prequote_id' => $pq->id, 'item_order' => 1,
            'product_name' => 'Item A (hereda)', 'quantity' => 10,
            'costo_base' => 100, 'margen_porcentaje' => 0,
        ]);
        $itemB = PreQuoteItem::create([
            'prequote_id' => $pq->id, 'item_order' => 2,
            'product_name' => 'Item B (2% propio)', 'quantity' => 10,
            'costo_base' => 100, 'margen_porcentaje' => 0,
            'sctr_porcentaje' => 2,
        ]);

        $this->engine->recalculate($pq);

        // A: 1.5% → 1015 | B: 2% → 1020.
        $this->assertEquals(1015.00, (float) $itemA->fresh()->valor_total,
            'El ítem sin SCTR propio mantiene el de la cotización (1.5%)');
        $this->assertEquals(1020.00, (float) $itemB->fresh()->valor_total,
            'El ítem con SCTR propio (2%) aplica su override');

        // Subtotal de la cotización = A(1015) + B(1020).
        $this->assertEquals(2035.00, (float) $pq->fresh()->subtotal,
            'El subtotal suma los dos ítems con su SCTR efectivo');
    }

    /*
    |--------------------------------------------------------------------------
    | COM-02 — Costo Total de Impresión (4 factores: costo × colores × lados × millar)
    |--------------------------------------------------------------------------
    | Excel del cliente: AD = Z × AA × AB × AC. Entra al costeo como costo FIJO del
    | pedido (una sola vez) — NO escala por la cantidad del pedido.
    */

    /** @test */
    public function costo_total_impresion_multiplica_costo_colores_lados_millar()
    {
        // costo_impresion=5, colores=3, lados=2, millar=1 → impresión total = 30.
        // TP=1000 (100×10), SCTR=1.5%×(1000+30)=15.45, total sin IGV = 1045.45.
        $pq = PreQuote::create([
            'version' => 1, 'estado' => PreQuote::ESTADO_BORRADOR, 'titulo' => 'TEST impresion 4 factores',
        ]);
        $this->cleanupPrequoteIds[] = $pq->id;

        $item = PreQuoteItem::create([
            'prequote_id'       => $pq->id,
            'product_name'      => 'Producto impresión',
            'quantity'          => 10,
            'costo_base'        => 100,
            'margen_porcentaje' => 0,
            'costo_impresion'   => 5,
            'cantidad_colores'  => 3,
            'cantidad_lados'    => 2,
            'cantidad_millar'   => 1,
        ]);

        $fresh = $this->engine->recalculateItem($item);

        $this->assertEquals(1045.45, (float) $fresh->valor_total,
            'La impresión total (5×3×2×1=30) entra UNA vez a TP+impr+emp y sube el SCTR');
    }

    /** @test */
    public function la_cantidad_por_millar_multiplica_la_impresion()
    {
        // costo_impresion=10, colores=1, lados=1, millar=2 → impresión total = 20.
        // TP=1000, SCTR=1.5%×(1000+20)=15.30, total sin IGV = 1035.30.
        $pq = PreQuote::create([
            'version' => 1, 'estado' => PreQuote::ESTADO_BORRADOR, 'titulo' => 'TEST impresion millar',
        ]);
        $this->cleanupPrequoteIds[] = $pq->id;

        $item = PreQuoteItem::create([
            'prequote_id'       => $pq->id,
            'product_name'      => 'Producto millar',
            'quantity'          => 10,
            'costo_base'        => 100,
            'margen_porcentaje' => 0,
            'costo_impresion'   => 10,
            'cantidad_colores'  => 1,
            'cantidad_lados'    => 1,
            'cantidad_millar'   => 2,
        ]);

        $fresh = $this->engine->recalculateItem($item);

        $this->assertEquals(1035.30, (float) $fresh->valor_total,
            'El factor "Cantidad por Millar" (2) duplica la impresión');
    }

    /** @test */
    public function la_impresion_total_no_escala_por_la_cantidad_del_pedido()
    {
        // Mismo ítem (impresión total = 30) con dos cantidades del pedido: la
        // impresión NO cambia; solo cambia TP (Q×T) y, por ende, la base del SCTR.
        $pq = PreQuote::create([
            'version' => 1, 'estado' => PreQuote::ESTADO_BORRADOR, 'titulo' => 'TEST impresion no escala Q',
        ]);
        $this->cleanupPrequoteIds[] = $pq->id;

        $item = PreQuoteItem::create([
            'prequote_id'       => $pq->id,
            'product_name'      => 'Producto impresión fija',
            'quantity'          => 10,
            'costo_base'        => 100,
            'margen_porcentaje' => 0,
            'costo_impresion'   => 5,
            'cantidad_colores'  => 3,
            'cantidad_lados'    => 2,
            'cantidad_millar'   => 1,
        ]);

        // Q=10 → TP=1000, impr=30, SCTR=1.5%×1030=15.45, total=1045.45.
        $this->assertEquals(1045.45, (float) $this->engine->recalculateItem($item)->valor_total);

        // Q=20 → TP=2000, impr sigue 30 (no ×Q), SCTR=1.5%×2030=30.45, total=2060.45.
        // Si la impresión escalara por Q, el total sería 2090.90 (impr=600). No.
        $item->quantity = 20;
        $item->save();
        $this->assertEquals(2060.45, (float) $this->engine->recalculateItem($item)->valor_total,
            'La impresión total (30) no debe escalar con la cantidad del pedido');
    }

    /** @test */
    public function caso_neutro_de_impresion_da_el_mismo_total_que_hoy()
    {
        // colores=lados=millar=1 (y flag false, el default): la impresión entra una
        // vez, idéntico al comportamiento "global" de hoy. costo_impresion=8 →
        // TP=1000, impr=8, SCTR=1.5%×1008=15.12 → total 1023.12
        // (mismo valor que el caso GLOBAL de impresion_global_vs_unitaria).
        $pq = PreQuote::create([
            'version' => 1, 'estado' => PreQuote::ESTADO_BORRADOR, 'titulo' => 'TEST impresion neutra',
        ]);
        $this->cleanupPrequoteIds[] = $pq->id;

        $item = PreQuoteItem::create([
            'prequote_id'       => $pq->id,
            'product_name'      => 'Producto impresión neutra',
            'quantity'          => 10,
            'costo_base'        => 100,
            'margen_porcentaje' => 0,
            'costo_impresion'   => 8,
            'cantidad_colores'  => 1,
            'cantidad_lados'    => 1,
            'cantidad_millar'   => 1,
        ]);

        $this->assertEquals(1023.12, (float) $this->engine->recalculateItem($item)->valor_total,
            'Con factores neutros (1) el total es idéntico al de hoy');
    }

    /** @test */
    public function el_flag_impresion_por_unidad_ya_no_escala_la_impresion()
    {
        // El viejo flag "por unidad" ya NO escala la impresión por la cantidad del
        // pedido (la "Cantidad por Millar" lo reemplaza). costo_impresion=5, Q=10,
        // factores=1, flag TRUE → impr=5 (no 5×10=50). TP=1000, SCTR=1.5%×1005=15.075
        // → total 1020.08. (Si el flag se honrara, impr=50 → total 1065.75.)
        $pq = PreQuote::create([
            'version' => 1, 'estado' => PreQuote::ESTADO_BORRADOR, 'titulo' => 'TEST flag impresion ignorado',
        ]);
        $this->cleanupPrequoteIds[] = $pq->id;

        $item = PreQuoteItem::create([
            'prequote_id'          => $pq->id,
            'product_name'         => 'Producto flag',
            'quantity'             => 10,
            'costo_base'           => 100,
            'margen_porcentaje'    => 0,
            'costo_impresion'      => 5,
            'cantidad_colores'     => 1,
            'cantidad_lados'       => 1,
            'cantidad_millar'      => 1,
            'impresion_por_unidad' => true,
        ]);

        $this->assertEquals(1020.08, (float) $this->engine->recalculateItem($item)->valor_total,
            'impresion_por_unidad ya no escala la impresión por la cantidad del pedido');
    }
}
