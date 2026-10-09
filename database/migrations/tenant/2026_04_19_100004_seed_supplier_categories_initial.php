<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Pobla las tablas `supplier_categories` y `supplier_subcategories` con los
 * valores maestros extraídos del listado del cliente (Excel + SQL propuesto).
 *
 * Son valores que el administrador puede editar luego desde la UI.
 */
class SeedSupplierCategoriesInitial extends Migration
{
    public function up()
    {
        $now = now();

        $categories = [
            ['nombre' => 'IMPRENTA', 'orden' => 10],
            ['nombre' => 'TEXTIL', 'orden' => 20],
            ['nombre' => 'CERAMICA', 'orden' => 30],
            ['nombre' => 'PLASTICOS', 'orden' => 40],
            ['nombre' => 'BOLSAS PLASTICAS', 'orden' => 50],
            ['nombre' => 'CAJAS DE CARTON', 'orden' => 60],
            ['nombre' => 'COURIER', 'orden' => 70],
            ['nombre' => 'IMPORTADOS', 'orden' => 80],
            ['nombre' => 'MERCH GENERAL', 'orden' => 90],
            ['nombre' => 'PUBLICIDAD EXTERIOR', 'orden' => 100],
            ['nombre' => 'BANDERAS', 'orden' => 110],
            ['nombre' => 'GOLOSINAS Y BEBIDAS', 'orden' => 120],
            ['nombre' => 'SERVICIOS', 'orden' => 130],
            ['nombre' => 'TAMPOGRAFIA', 'orden' => 140],
            ['nombre' => 'SERIGRAFIA', 'orden' => 150],
            ['nombre' => 'PAPELERIA', 'orden' => 160],
            ['nombre' => 'CANASTAS', 'orden' => 170],
            ['nombre' => 'CUERO', 'orden' => 180],
            ['nombre' => 'ARTICULOS DE SEGURIDAD', 'orden' => 190],
            ['nombre' => 'AGENDAS', 'orden' => 200],
            ['nombre' => 'BORDADOS', 'orden' => 210],
            ['nombre' => 'OTROS', 'orden' => 999],
        ];

        foreach ($categories as $c) {
            DB::table('supplier_categories')->updateOrInsert(
                ['nombre' => $c['nombre']],
                ['orden' => $c['orden'], 'estado' => 1, 'created_at' => $now, 'updated_at' => $now]
            );
        }

        $subcategories = [
            ['cat' => 'CERAMICA', 'nombre' => 'Tazas', 'orden' => 10],
            ['cat' => 'CERAMICA', 'nombre' => 'Chop', 'orden' => 20],
            ['cat' => 'CERAMICA', 'nombre' => 'Vasos', 'orden' => 30],
            ['cat' => 'BANDERAS', 'nombre' => 'Bandera de escritorio', 'orden' => 10],
            ['cat' => 'BANDERAS', 'nombre' => 'Gallardetes', 'orden' => 20],
            ['cat' => 'BOLSAS PLASTICAS', 'nombre' => 'Bolsa Publicitaria', 'orden' => 10],
            ['cat' => 'TEXTIL', 'nombre' => 'Ropa de Cama', 'orden' => 10],
            ['cat' => 'TEXTIL', 'nombre' => 'Toallas', 'orden' => 20],
            ['cat' => 'TEXTIL', 'nombre' => 'Uniformes', 'orden' => 30],
            ['cat' => 'IMPRENTA', 'nombre' => 'Laser', 'orden' => 10],
            ['cat' => 'IMPRENTA', 'nombre' => 'Cartón corrugado', 'orden' => 20],
            ['cat' => 'IMPORTADOS', 'nombre' => 'Lapiceros', 'orden' => 10],
            ['cat' => 'IMPORTADOS', 'nombre' => 'Merchandising', 'orden' => 20],
            ['cat' => 'AGENDAS', 'nombre' => 'Planners', 'orden' => 10],
        ];

        foreach ($subcategories as $s) {
            $catId = DB::table('supplier_categories')->where('nombre', $s['cat'])->value('id');
            if (!$catId) continue;

            DB::table('supplier_subcategories')->updateOrInsert(
                ['supplier_category_id' => $catId, 'nombre' => $s['nombre']],
                ['orden' => $s['orden'], 'estado' => 1, 'created_at' => $now, 'updated_at' => $now]
            );
        }
    }

    public function down()
    {
        DB::table('supplier_subcategories')->truncate();
        DB::table('supplier_categories')->truncate();
    }
}
