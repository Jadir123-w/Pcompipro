<?php

namespace Modules\Payroll\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Modules\Payroll\Models\PayrollConfiguration;

class PayrollSettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        Model::unguard();

        $configuraciones = [
          // Tasas Generales
          [
            'clave' => 'rmv',
            'nombre' => 'Remuneración Mínima Vital (RMV)',
            'valor' => '1025.00',
            'tipo' => 'decimal',
            'descripcion' => 'Remuneración mínima vital vigente en Perú',
            'anio' => 2025,
          ],
          [
            'clave' => 'uit',
            'nombre' => 'Unidad Impositiva Tributaria (UIT)',
            'valor' => '5150.00',
            'tipo' => 'decimal',
            'descripcion' => 'UIT vigente para cálculos tributarios',
            'anio' => 2025,
          ],
          [
            'clave' => 'porcentaje_essalud',
            'nombre' => 'Tasa EsSalud',
            'valor' => '9.00',
            'tipo' => 'decimal',
            'descripcion' => 'Aporte del empleador a EsSalud (9%)',
            'anio' => 2025,
          ],
          [
            'clave' => 'porcentaje_onp',
            'nombre' => 'Tasa ONP',
            'valor' => '13.00',
            'tipo' => 'decimal',
            'descripcion' => 'Aporte obligatorio a ONP (13%)',
            'anio' => 2025,
          ],
          [
            'clave' => 'asignacion_familiar_porcentaje',
            'nombre' => 'Asignación Familiar (%)',
            'valor' => '10.00',
            'tipo' => 'decimal',
            'descripcion' => 'Porcentaje de la RMV para asignación familiar',
            'anio' => 2025,
          ],

          // Renta de 5ta Categoría - Tramos 2025
          [
            'clave' => 'renta5_uit_exonerado',
            'nombre' => 'Renta 5ta - UITs Exoneradas',
            'valor' => '7',
            'tipo' => 'entero',
            'descripcion' => 'Primeras 7 UITs anuales exoneradas',
            'anio' => 2025,
          ],
          [
            'clave' => 'renta5_tramo1_hasta_uit',
            'nombre' => 'Renta 5ta Tramo 1 - Hasta UITs',
            'valor' => '5',
            'tipo' => 'entero',
            'descripcion' => 'Hasta 5 UITs adicionales (8% tasa)',
            'anio' => 2025,
          ],
          [
            'clave' => 'renta5_tramo1_tasa',
            'nombre' => 'Renta 5ta Tramo 1 - Tasa',
            'valor' => '8.00',
            'tipo' => 'decimal',
            'descripcion' => 'Tasa del primer tramo (8%)',
            'anio' => 2025,
          ],
          [
            'clave' => 'renta5_tramo2_hasta_uit',
            'nombre' => 'Renta 5ta Tramo 2 - Hasta UITs',
            'valor' => '15',
            'tipo' => 'entero',
            'descripcion' => 'Hasta 15 UITs adicionales (14% tasa)',
            'anio' => 2025,
          ],
          [
            'clave' => 'renta5_tramo2_tasa',
            'nombre' => 'Renta 5ta Tramo 2 - Tasa',
            'valor' => '14.00',
            'tipo' => 'decimal',
            'descripcion' => 'Tasa del segundo tramo (14%)',
            'anio' => 2025,
          ],
          [
            'clave' => 'renta5_tramo3_hasta_uit',
            'nombre' => 'Renta 5ta Tramo 3 - Hasta UITs',
            'valor' => '25',
            'tipo' => 'entero',
            'descripcion' => 'Hasta 25 UITs adicionales (17% tasa)',
            'anio' => 2025,
          ],
          [
            'clave' => 'renta5_tramo3_tasa',
            'nombre' => 'Renta 5ta Tramo 3 - Tasa',
            'valor' => '17.00',
            'tipo' => 'decimal',
            'descripcion' => 'Tasa del tercer tramo (17%)',
            'anio' => 2025,
          ],
          [
            'clave' => 'renta5_tramo4_hasta_uit',
            'nombre' => 'Renta 5ta Tramo 4 - Hasta UITs',
            'valor' => '25',
            'tipo' => 'entero',
            'descripcion' => 'Hasta 25 UITs adicionales (20% tasa)',
            'anio' => 2025,
          ],
          [
            'clave' => 'renta5_tramo4_tasa',
            'nombre' => 'Renta 5ta Tramo 4 - Tasa',
            'valor' => '20.00',
            'tipo' => 'decimal',
            'descripcion' => 'Tasa del cuarto tramo (20%)',
            'anio' => 2025,
          ],
          [
            'clave' => 'renta5_tramo5_tasa',
            'nombre' => 'Renta 5ta Tramo 5 - Tasa',
            'valor' => '30.00',
            'tipo' => 'decimal',
            'descripcion' => 'Tasa del quinto tramo - Exceso (30%)',
            'anio' => 2025,
          ],

          // Configuraciones de Sistema
          [
            'clave' => 'dias_mes_laboral',
            'nombre' => 'Días por mes completo',
            'valor' => '30',
            'tipo' => 'entero',
            'descripcion' => 'Días considerados para un mes completo de trabajo',
            'anio' => 2025,
          ],
          [
            'clave' => 'horas_dia_laboral',
            'nombre' => 'Horas por día laboral',
            'valor' => '8',
            'tipo' => 'entero',
            'descripcion' => 'Horas consideradas por día laboral',
            'anio' => 2025,
          ],
           [
            'clave' => 'tasa_hora_extra',
            'nombre' => 'Tasa Hora Extra',
            'valor' => '1.25',
            'tipo' => 'decimal',
            'descripcion' => 'Factor multiplicador para horas extras (1.25 = 25%)',
            'anio' => 2025,
          ],
        ];

        foreach ($configuraciones as $config) {
             PayrollConfiguration::updateOrCreate(
                ['clave' => $config['clave'], 'anio' => $config['anio']],
                $config
             );
        }
    }
}
