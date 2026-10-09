<?php

namespace Tests\Feature\Marketing;

use Hyn\Tenancy\Environment;
use Hyn\Tenancy\Models\Website;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Marketing\Models\MarginRule;
use Modules\Marketing\Services\MarginService;
use Tests\TestCase;

/**
 * Com02MargenMinimoTest — Fase B (margen mínimo + alerta visual)
 *
 * Verifica que:
 *  1. La columna min_margin_percentage existe en quotation_margin_rules.
 *  2. MarginService::getMinMarginForItem devuelve el mínimo correcto según la
 *     prioridad (producto > categoría > General).
 *  3. Retorna null cuando no hay regla aplicable o la regla no define mínimo.
 *  4. El endpoint POST /quotation/margins acepta y persiste min_margin_percentage.
 *  5. Validación: el mínimo no puede ser mayor al sugerido.
 *
 * La alerta visual en sí (cambio de color del input) se verifica manualmente
 * desde la UI — este test cubre el contrato backend que la habilita.
 */
class Com02MargenMinimoTest extends TestCase
{
    /** @var MarginService */
    private $service;

    /** @var array Reglas creadas para limpiar en tearDown */
    private $createdRuleIds = [];

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

        $this->service = new MarginService();
    }

    protected function tearDown(): void
    {
        try {
            if (!empty($this->createdRuleIds)) {
                MarginRule::whereIn('id', $this->createdRuleIds)->delete();
            }
        } catch (\Exception $e) {
            // Ignorar — el test puede haber fallado antes de poblar el array.
        }
        parent::tearDown();
    }

    /*
    |--------------------------------------------------------------------------
    | Esquema y modelo
    |--------------------------------------------------------------------------
    */

    /** @test */
    public function la_columna_min_margin_percentage_existe()
    {
        $exists = Schema::connection('tenant')
            ->hasColumn('quotation_margin_rules', 'min_margin_percentage');

        $this->assertTrue($exists,
            'La migración 2026_05_23_000001 debe estar aplicada en el tenant de tests.');
    }

    /** @test */
    public function el_modelo_persiste_min_margin_percentage()
    {
        $rule = MarginRule::create([
            'category'              => 'Textil',
            'product_name'          => null,
            'quantity_from'         => 1,
            'quantity_to'           => 100,
            'margin_percentage'     => 80,
            'min_margin_percentage' => 50,
            'priority'              => 1,
            'active'                => true,
        ]);
        $this->createdRuleIds[] = $rule->id;

        $fresh = MarginRule::find($rule->id);
        $this->assertEquals(50.00, (float) $fresh->min_margin_percentage);
    }

    /*
    |--------------------------------------------------------------------------
    | MarginService::getMinMarginForItem
    |--------------------------------------------------------------------------
    */

    /** @test */
    public function devuelve_null_cuando_no_hay_regla_aplicable()
    {
        // Categoría inexistente — no debe haber regla
        $min = $this->service->getMinMarginForItem('CategoriaQueNoExisteJamas', null, 10);

        $this->assertNull($min,
            'Sin regla aplicable debe retornar null (frontend interpreta null = sin alerta).');
    }

    /** @test */
    public function devuelve_null_cuando_la_regla_no_define_minimo()
    {
        $rule = MarginRule::create([
            'category'              => 'TestSinMinimo',
            'product_name'          => null,
            'quantity_from'         => 1,
            'quantity_to'           => null,
            'margin_percentage'     => 40,
            'min_margin_percentage' => null,  // explícitamente sin mínimo
            'priority'              => 1,
            'active'                => true,
        ]);
        $this->createdRuleIds[] = $rule->id;

        $min = $this->service->getMinMarginForItem('TestSinMinimo', null, 10);

        $this->assertNull($min,
            'Si la regla matchea pero no tiene mínimo, debe retornar null.');
    }

    /** @test */
    public function devuelve_el_minimo_cuando_la_regla_lo_define()
    {
        $rule = MarginRule::create([
            'category'              => 'TestConMinimo',
            'product_name'          => null,
            'quantity_from'         => 1,
            'quantity_to'           => null,
            'margin_percentage'     => 80,
            'min_margin_percentage' => 35,
            'priority'              => 1,
            'active'                => true,
        ]);
        $this->createdRuleIds[] = $rule->id;

        $min = $this->service->getMinMarginForItem('TestConMinimo', null, 10);

        $this->assertEquals(35.00, $min);
    }

    /** @test */
    public function prioriza_la_regla_de_producto_sobre_la_de_categoria()
    {
        // Regla por categoría (priority 1)
        $catRule = MarginRule::create([
            'category'              => 'TestPrioridad',
            'product_name'          => null,
            'quantity_from'         => 1,
            'quantity_to'           => null,
            'margin_percentage'     => 60,
            'min_margin_percentage' => 30,
            'priority'              => 1,
            'active'                => true,
        ]);
        $this->createdRuleIds[] = $catRule->id;

        // Regla por producto (priority 2 — más específica)
        $prodRule = MarginRule::create([
            'category'              => 'TestPrioridad',
            'product_name'          => 'PoloEspecial',
            'quantity_from'         => 1,
            'quantity_to'           => null,
            'margin_percentage'     => 90,
            'min_margin_percentage' => 55,
            'priority'              => 2,
            'active'                => true,
        ]);
        $this->createdRuleIds[] = $prodRule->id;

        // Match por producto debe ganar
        $min = $this->service->getMinMarginForItem('TestPrioridad', 'PoloEspecial', 10);
        $this->assertEquals(55.00, $min, 'El mínimo de la regla por producto debe ganar.');

        // Sin nombre de producto, solo categoría → mínimo de categoría
        $minCat = $this->service->getMinMarginForItem('TestPrioridad', 'OtroProducto', 10);
        $this->assertEquals(30.00, $minCat, 'Sin match de producto debe caer a la regla por categoría.');
    }

    /*
    |--------------------------------------------------------------------------
    | Endpoint HTTP — POST /marketing/quotation/margins
    |--------------------------------------------------------------------------
    */

    /** @test */
    public function el_endpoint_persiste_min_margin_percentage()
    {
        $payload = [
            'category'              => 'TestEndpoint',
            'product_name'          => null,
            'quantity_from'         => 1,
            'quantity_to'           => 200,
            'margin_percentage'     => 70,
            'min_margin_percentage' => 40,
        ];

        $resp = $this->postJson('/api/marketing/quotation/margins', $payload);

        if ($resp->status() === 401 || $resp->status() === 419) {
            $this->markTestSkipped('Auth requerida — saltando test HTTP en este entorno.');
        }

        $resp->assertStatus(201);
        $json = $resp->json();
        $this->assertTrue($json['ok']);
        $this->assertEquals(40.00, (float) $json['margin']['min_margin_percentage']);

        $this->createdRuleIds[] = $json['margin']['id'];
    }

    /** @test */
    public function el_endpoint_rechaza_minimo_mayor_al_sugerido()
    {
        $payload = [
            'category'              => 'TestValidacion',
            'product_name'          => null,
            'quantity_from'         => 1,
            'quantity_to'           => null,
            'margin_percentage'     => 30,
            'min_margin_percentage' => 50,  // mínimo > sugerido — debe fallar
        ];

        $resp = $this->postJson('/api/marketing/quotation/margins', $payload);

        if ($resp->status() === 401 || $resp->status() === 419) {
            $this->markTestSkipped('Auth requerida — saltando test HTTP en este entorno.');
        }

        $resp->assertStatus(422);
        $this->assertStringContainsString('mínimo no puede ser mayor', $resp->json('message') ?: '');
    }
}
