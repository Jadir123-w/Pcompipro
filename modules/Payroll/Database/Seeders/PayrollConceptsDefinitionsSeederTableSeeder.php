<?php

namespace Modules\Payroll\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class PayrollConceptsDefinitionsSeederTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        Model::unguard();

      $conceptos = [
        [
          'codigo' => 'SBASICO',
          'nombre' => 'Sueldo Básico',
          'descripcion' => 'Remuneración básica mensual',
          'tipo' => 'INGRESO',
          'naturaleza' => 'FIJO',
          'afecto_essalud' => true,
          'afecto_renta' => true,
          'afecto_afp_onp' => true,
          'sistema' => true,
          'orden' => 1,
        ],
        [
          'codigo' => 'ASFAM',
          'nombre' => 'Asignación Familiar',
          'descripcion' => '10% de la RMV para trabajadores con hijos menores',
          'tipo' => 'INGRESO',
          'naturaleza' => 'FIJO',
          'afecto_essalud' => true,
          'afecto_renta' => true,
          'afecto_afp_onp' => true,
          'sistema' => true,
          'orden' => 2,
        ],
        [
          'codigo' => 'HEXTRAS',
          'nombre' => 'Horas Extras',
          'descripcion' => 'Pago por horas extras laboradas',
          'tipo' => 'INGRESO',
          'naturaleza' => 'VARIABLE',
          'afecto_essalud' => true,
          'afecto_renta' => true,
          'afecto_afp_onp' => true,
          'sistema' => false,
          'orden' => 3,
        ],
        [
          'codigo' => 'BONIF',
          'nombre' => 'Bonificaciones',
          'descripcion' => 'Bonificaciones extraordinarias',
          'tipo' => 'INGRESO',
          'naturaleza' => 'VARIABLE',
          'afecto_essalud' => true,
          'afecto_renta' => true,
          'afecto_afp_onp' => true,
          'sistema' => false,
          'orden' => 4,
        ],
        [
          'codigo' => 'MOVIL',
          'nombre' => 'Movilidad',
          'descripcion' => 'Asignación por movilidad',
          'tipo' => 'INGRESO',
          'naturaleza' => 'FIJO',
          'afecto_essalud' => false,
          'afecto_renta' => false,
          'afecto_afp_onp' => false,
          'sistema' => false,
          'orden' => 5,
        ],
        [
          'codigo' => 'AFP',
          'nombre' => 'Aporte AFP',
          'descripcion' => 'Aporte obligatorio al fondo de pensiones AFP',
          'tipo' => 'DESCUENTO',
          'naturaleza' => 'FIJO',
          'afecto_essalud' => false,
          'afecto_renta' => false,
          'afecto_afp_onp' => false,
          'sistema' => true,
          'orden' => 1,
        ],
        [
          'codigo' => 'COMAFP',
          'nombre' => 'Comisión AFP',
          'descripcion' => 'Comisión por administración AFP',
          'tipo' => 'DESCUENTO',
          'naturaleza' => 'FIJO',
          'afecto_essalud' => false,
          'afecto_renta' => false,
          'afecto_afp_onp' => false,
          'sistema' => true,
          'orden' => 2,
        ],
        [
          'codigo' => 'SEGAFP',
          'nombre' => 'Seguro AFP',
          'descripcion' => 'Prima de seguro de invalidez y sobrevivencia',
          'tipo' => 'DESCUENTO',
          'naturaleza' => 'FIJO',
          'afecto_essalud' => false,
          'afecto_renta' => false,
          'afecto_afp_onp' => false,
          'sistema' => true,
          'orden' => 3,
        ],
        [
          'codigo' => 'ONP',
          'nombre' => 'Aporte ONP',
          'descripcion' => 'Aporte al Sistema Nacional de Pensiones',
          'tipo' => 'DESCUENTO',
          'naturaleza' => 'FIJO',
          'afecto_essalud' => false,
          'afecto_renta' => false,
          'afecto_afp_onp' => false,
          'sistema' => true,
          'orden' => 4,
        ],
        [
          'codigo' => 'RENTA5',
          'nombre' => 'Renta 5ta Categoría',
          'descripcion' => 'Impuesto a la Renta de 5ta categoría',
          'tipo' => 'DESCUENTO',
          'naturaleza' => 'VARIABLE',
          'afecto_essalud' => false,
          'afecto_renta' => false,
          'afecto_afp_onp' => false,
          'sistema' => true,
          'orden' => 5,
        ],
        [
          'codigo' => 'PRESTAMO',
          'nombre' => 'Préstamo',
          'descripcion' => 'Descuento por préstamo personal',
          'tipo' => 'DESCUENTO',
          'naturaleza' => 'VARIABLE',
          'afecto_essalud' => false,
          'afecto_renta' => false,
          'afecto_afp_onp' => false,
          'sistema' => false,
          'orden' => 6,
        ],
        [
          'codigo' => 'ADELANTO',
          'nombre' => 'Adelanto de Sueldo',
          'descripcion' => 'Descuento por adelanto de haberes',
          'tipo' => 'DESCUENTO',
          'naturaleza' => 'VARIABLE',
          'afecto_essalud' => false,
          'afecto_renta' => false,
          'afecto_afp_onp' => false,
          'sistema' => false,
          'orden' => 7,
        ],
      ];

      foreach ($conceptos as $concepto) {
        DB::table('conceptos_planilla')->insert(array_merge($concepto, [
          'created_at' => now(),
          'updated_at' => now(),
        ]));
      }

    }
}
