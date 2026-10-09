<?php

namespace Tests\Feature\Marketing;

use App\Models\Tenant\ItemUnitType;
use Hyn\Tenancy\Environment;
use Hyn\Tenancy\Models\Website;
use Illuminate\Support\Facades\DB;
use Modules\Marketing\Services\VolumePricingService;
use Tests\TestCase;

/**
 * Com02PreciosVolumenTest — Fase C (precios por volumen desde item_unit_types)
 *
 * Verifica que el VolumePricingService respeta la lógica mayorista clásica:
 * para una cantidad Q, aplica el escalón con MAX(quantity_unit) <= Q.
 *
 *   Escalones del test (50, 100, 500):
 *     Q=30   → null         (no llega al menor escalón, fallback al caller)
 *     Q=50   → escalón 50
 *     Q=75   → escalón 50   (el último alcanzado)
 *     Q=100  → escalón 100
 *     Q=499  → escalón 100
 *     Q=500  → escalón 500
 *     Q=1000 → escalón 500
 */
class Com02PreciosVolumenTest extends TestCase
{
    /** @var VolumePricingService */
    private $service;

    /** @var int|null Item de prueba creado en setUp */
    private $itemId = null;

    /** @var array IDs de escalones creados */
    private $tierIds = [];

    /** @var int Sentinela para item_id que no existe */
    const ITEM_INEXISTENTE = 999999999;

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

        $this->service = new VolumePricingService();

        // Usamos un ítem real del catálogo (cualquier active=1) para no
        // depender de la FK. Si no hay items, marcamos skip.
        $item = DB::connection('tenant')->table('items')
            ->where('active', 1)
            ->orderBy('id')
            ->first();

        if (!$item) {
            $this->markTestSkipped('Sin items en el catálogo para asociar escalones de prueba');
        }
        $this->itemId = (int) $item->id;

        // Crear 3 escalones: 50, 100, 500 con precios decrecientes.
        // Los creamos solo si no hay ya escalones — para no romper data real.
        $existing = ItemUnitType::where('item_id', $this->itemId)->count();
        if ($existing > 0) {
            // Buscar otro ítem que no tenga escalones
            $itemsWithTiers = ItemUnitType::distinct()->pluck('item_id')->toArray();
            $freeItem = DB::connection('tenant')->table('items')
                ->where('active', 1)
                ->whereNotIn('id', $itemsWithTiers)
                ->orderBy('id')
                ->first();
            if (!$freeItem) {
                $this->markTestSkipped('Sin ítems libres para insertar escalones de prueba');
            }
            $this->itemId = (int) $freeItem->id;
        }

        foreach ([[50, 10.00], [100, 9.00], [500, 7.50]] as $row) {
            $tier = ItemUnitType::create([
                'description'   => 'TEST tier ' . $row[0],
                'item_id'       => $this->itemId,
                'unit_type_id'  => 'NIU',
                'quantity_unit' => $row[0],
                'price1'        => $row[1],
                'price2'        => 0,
                'price3'        => 0,
                'price_default' => 1,
            ]);
            $this->tierIds[] = $tier->id;
        }
    }

    protected function tearDown(): void
    {
        try {
            if (!empty($this->tierIds)) {
                ItemUnitType::whereIn('id', $this->tierIds)->delete();
            }
        } catch (\Exception $e) {
            // Ignorar
        }
        parent::tearDown();
    }

    /*
    |--------------------------------------------------------------------------
    | Lógica de selección mayorista
    |--------------------------------------------------------------------------
    */

    /** @test */
    public function cantidad_debajo_del_menor_escalon_retorna_null()
    {
        $result = $this->service->getCostForVolume($this->itemId, 30);

        $this->assertNull($result,
            'Q=30 < primer escalón (50) debe retornar null — el caller decide el fallback.');
    }

    /** @test */
    public function cantidad_exacta_del_primer_escalon_aplica_ese_escalon()
    {
        $result = $this->service->getCostForVolume($this->itemId, 50);

        $this->assertNotNull($result);
        $this->assertEquals(10.00, $result['cost']);
        $this->assertEquals(50, $result['tier']['quantity_unit']);
    }

    /** @test */
    public function cantidad_entre_escalones_aplica_el_anterior_alcanzado()
    {
        // Q=75 → último alcanzado es 50 (no llega a 100)
        $result = $this->service->getCostForVolume($this->itemId, 75);

        $this->assertEquals(10.00, $result['cost'],
            'Q=75 entre 50 y 100 → debe aplicar precio del escalón 50 (último alcanzado).');
        $this->assertEquals(50, $result['tier']['quantity_unit']);
    }

    /** @test */
    public function cantidad_exacta_del_segundo_escalon_aplica_ese_escalon()
    {
        $result = $this->service->getCostForVolume($this->itemId, 100);

        $this->assertEquals(9.00, $result['cost']);
        $this->assertEquals(100, $result['tier']['quantity_unit']);
    }

    /** @test */
    public function cantidad_alta_aplica_el_escalon_mayor_alcanzado()
    {
        // Q=499 → 100 (no llega a 500); Q=500 → 500; Q=1000 → 500
        $r499  = $this->service->getCostForVolume($this->itemId, 499);
        $r500  = $this->service->getCostForVolume($this->itemId, 500);
        $r1000 = $this->service->getCostForVolume($this->itemId, 1000);

        $this->assertEquals(9.00, $r499['cost'],  'Q=499 → escalón 100');
        $this->assertEquals(7.50, $r500['cost'],  'Q=500 → escalón 500');
        $this->assertEquals(7.50, $r1000['cost'], 'Q=1000 → escalón 500');
    }

    /*
    |--------------------------------------------------------------------------
    | Casos límite
    |--------------------------------------------------------------------------
    */

    /** @test */
    public function item_sin_escalones_retorna_null()
    {
        $result = $this->service->getCostForVolume(self::ITEM_INEXISTENTE, 100);

        $this->assertNull($result,
            'Si el item_id no tiene escalones en item_unit_types, debe retornar null.');
    }

    /** @test */
    public function item_id_vacio_retorna_null()
    {
        $this->assertNull($this->service->getCostForVolume(0, 100));
        $this->assertNull($this->service->getCostForVolume(null, 100));
    }

    /** @test */
    public function cantidad_cero_o_negativa_retorna_null()
    {
        $this->assertNull($this->service->getCostForVolume($this->itemId, 0));
        $this->assertNull($this->service->getCostForVolume($this->itemId, -10));
    }

    /*
    |--------------------------------------------------------------------------
    | Helper getAvailableTiers
    |--------------------------------------------------------------------------
    */

    /** @test */
    public function getavailabletiers_devuelve_los_escalones_ordenados()
    {
        $tiers = $this->service->getAvailableTiers($this->itemId);

        $this->assertCount(3, $tiers);
        $this->assertEquals(50,  $tiers[0]['quantity_unit']);
        $this->assertEquals(100, $tiers[1]['quantity_unit']);
        $this->assertEquals(500, $tiers[2]['quantity_unit']);

        // Cada escalón debe traer los campos que el frontend espera
        $this->assertArrayHasKey('price1', $tiers[0]);
        $this->assertArrayHasKey('description', $tiers[0]);
        $this->assertArrayHasKey('unit_type_id', $tiers[0]);
    }

    /** @test */
    public function getavailabletiers_devuelve_vacio_para_item_sin_escalones()
    {
        $tiers = $this->service->getAvailableTiers(self::ITEM_INEXISTENTE);

        $this->assertIsArray($tiers);
        $this->assertEmpty($tiers);
    }
}
