<?php

namespace Tests\Feature\Item;

use App\Models\Tenant\ItemUnitType;
use Hyn\Tenancy\Environment;
use Hyn\Tenancy\Models\Website;
use Tests\TestCase;

/**
 * ItemUnitTypeDescriptionTest — Fix del Server Error al guardar Presentaciones.
 *
 * Bug
 * ===
 * Al guardar un producto con presentaciones (listado de precios por cantidad)
 * dejando la columna "Descripción" vacía, el ERP devolvía HTTP 500:
 *   SQLSTATE[23000]: Column 'description' cannot be null
 * porque ItemController::store asignaba $value['description'] (null) directo a
 * la columna NOT NULL item_unit_types.description.
 *
 * Fix
 * ===
 * ItemUnitType::generateDescription() genera una descripción legible a partir
 * del factor (quantity_unit) + el nombre de la unidad. El controller la usa
 * cuando la descripción viene vacía.
 *
 * Estos tests validan el generador. El lookup de la unidad usa la tabla
 * cat_unit_types del tenant (NIU=Unidades, BX=Caja).
 */
class ItemUnitTypeDescriptionTest extends TestCase
{
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

    /** @test */
    public function genera_descripcion_con_factor_y_nombre_de_unidad()
    {
        // NIU existe en cat_unit_types como "Unidades"
        $this->assertEquals('100 Unidades', ItemUnitType::generateDescription(100, 'NIU'));
        $this->assertEquals('500 Unidades', ItemUnitType::generateDescription(500, 'NIU'));
        $this->assertEquals('1000 Unidades', ItemUnitType::generateDescription(1000, 'NIU'));
    }

    /** @test */
    public function genera_descripcion_con_caja_bx()
    {
        // BX existe en cat_unit_types como "Caja"
        $this->assertEquals('12 Caja', ItemUnitType::generateDescription(12, 'BX'));
    }

    /** @test */
    public function formatea_factor_decimal_sin_ceros_sobrantes()
    {
        // 1.5000 debe mostrarse como "1.5", no "1.5000"
        $this->assertEquals('1.5 Unidades', ItemUnitType::generateDescription(1.5, 'NIU'));
        // 100.0000 -> "100"
        $this->assertEquals('100 Unidades', ItemUnitType::generateDescription(100.0000, 'NIU'));
    }

    /** @test */
    public function factor_cero_o_vacio_defaultea_a_uno()
    {
        // Un factor 0 no tiene sentido como presentación; defaultea a 1
        $this->assertEquals('1 Unidades', ItemUnitType::generateDescription(0, 'NIU'));
        $this->assertEquals('1 Unidades', ItemUnitType::generateDescription(null, 'NIU'));
        $this->assertEquals('1 Unidades', ItemUnitType::generateDescription('', 'NIU'));
    }

    /** @test */
    public function unidad_desconocida_usa_el_codigo_como_fallback()
    {
        // Un código de unidad que no existe en el catálogo → se usa el código
        $result = ItemUnitType::generateDescription(50, 'CODIGO_INEXISTENTE_XYZ');
        $this->assertEquals('50 CODIGO_INEXISTENTE_XYZ', $result);
    }

    /** @test */
    public function sin_unidad_ni_factor_devuelve_fallback_legible()
    {
        // Caso extremo: sin datos útiles, no debe devolver vacío (la columna
        // es NOT NULL). Devuelve al menos "1 " + algo, nunca string vacío.
        $result = ItemUnitType::generateDescription(null, null);
        $this->assertNotEmpty($result, 'Nunca debe devolver vacío (columna NOT NULL)');
    }

    /** @test */
    public function el_resultado_nunca_es_null_ni_vacio()
    {
        // Garantía central del fix: pase lo que pase, hay una descripción
        // no-nula que satisface la columna NOT NULL.
        $casos = [
            [100, 'NIU'],
            [0, ''],
            [null, null],
            ['', ''],
            [1.25, 'BX'],
        ];
        foreach ($casos as $caso) {
            $result = ItemUnitType::generateDescription($caso[0], $caso[1]);
            $this->assertIsString($result);
            $this->assertNotSame('', $result, 'Descripción vacía rompería la columna NOT NULL');
        }
    }
}
