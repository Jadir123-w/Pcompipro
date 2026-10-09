<?php

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;


class TenancyPayrollAfpTable extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $afps = [
            [
                'nombre' => 'AFP Integra',
                'codigo' => 'INTEGRA',
                'comision_flujo' => 0.82,
                'comision_mixta' => 1.55,
                'aporte_obligatorio' => 10.00,
                'seguro_invalidez' => 1.35,
                'activo' => true,
            ],
            [
                'nombre' => 'AFP Prima',
                'codigo' => 'PRIMA',
                'comision_flujo' => 1.25,
                'comision_mixta' => 1.60,
                'aporte_obligatorio' => 10.00,
                'seguro_invalidez' => 1.35,
                'activo' => true,
            ],
            [
                'nombre' => 'AFP Profuturo',
                'codigo' => 'PROFUTURO',
                'comision_flujo' => 1.20,
                'comision_mixta' => 1.69,
                'aporte_obligatorio' => 10.00,
                'seguro_invalidez' => 1.35,
                'activo' => true,
            ],
            [
                'nombre' => 'AFP Habitat',
                'codigo' => 'HABITAT',
                'comision_flujo' => 1.47,
                'comision_mixta' => 1.47,
                'aporte_obligatorio' => 10.00,
                'seguro_invalidez' => 1.35,
                'activo' => true,
            ],
        ];

        foreach ($afps as $afp) {
            DB::table('payroll_afps')->insert(array_merge($afp, [
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }

    }
}
