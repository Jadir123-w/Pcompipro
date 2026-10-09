<?php

namespace Modules\Comercial\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

class RequerimientosSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $now = Carbon::now();

        DB::table('requerimiento')->insert([
            [
                'empresa_id'                => 1,
                'lead_id'                   => null,
                'contacto_principal_id'     => null,
                'codigo'                    => 'REQ-0001',
                'titulo'                    => 'Implementación de ERP para planta industrial',
                'descripcion'               => 'Requerimiento para implementar un ERP completo en planta industrial.',
                'origen'                    => 'Web',
                'etapa'                     => 'Pendiente atender',
                'estado'                    => 1,
                'nivel_prioridad'           => 'Muy alto',
                'monto_estimado'            => 150000.00,
                'fecha_requerida'           => $now->copy()->addDays(30)->toDateString(),
                'fecha_ingreso_etapa_actual'=> $now,
                'created_at'                => $now,
                'updated_at'                => $now,
            ],
            [
                'empresa_id'                => 1,
                'lead_id'                   => null,
                'contacto_principal_id'     => null,
                'codigo'                    => 'REQ-0002',
                'titulo'                    => 'Módulo de facturación electrónica',
                'descripcion'               => 'Requerimiento para módulo de facturación electrónica integrado al sistema actual.',
                'origen'                    => 'Email',
                'etapa'                     => 'En cotización',
                'estado'                    => 1,
                'nivel_prioridad'           => 'Alto',
                'monto_estimado'            => 35000.00,
                'fecha_requerida'           => $now->copy()->addDays(20)->toDateString(),
                'fecha_ingreso_etapa_actual'=> $now->copy()->subDays(2),
                'created_at'                => $now,
                'updated_at'                => $now,
            ],
            [
                'empresa_id'                => 2,
                'lead_id'                   => null,
                'contacto_principal_id'     => null,
                'codigo'                    => 'REQ-0003',
                'titulo'                    => 'CRM comercial básico',
                'descripcion'               => 'Requerimiento de CRM para gestionar leads, requerimientos y proyectos.',
                'origen'                    => 'WhatsApp',
                'etapa'                     => 'Cotización enviada',
                'estado'                    => 1,
                'nivel_prioridad'           => 'Medio',
                'monto_estimado'            => 28000.00,
                'fecha_requerida'           => $now->copy()->addDays(15)->toDateString(),
                'fecha_ingreso_etapa_actual'=> $now->copy()->subDays(5),
                'created_at'                => $now,
                'updated_at'                => $now,
            ],
            [
                'empresa_id'                => 2,
                'lead_id'                   => null,
                'contacto_principal_id'     => null,
                'codigo'                    => 'REQ-0004',
                'titulo'                    => 'Integración con ecommerce',
                'descripcion'               => 'Integración del ERP con tienda online para ventas B2C.',
                'origen'                    => 'Llamada',
                'etapa'                     => 'Cotización aprobada',
                'estado'                    => 1,
                'nivel_prioridad'           => 'Alto',
                'monto_estimado'            => 42000.00,
                'fecha_requerida'           => $now->copy()->addDays(10)->toDateString(),
                'fecha_ingreso_etapa_actual'=> $now->copy()->subDays(7),
                'created_at'                => $now,
                'updated_at'                => $now,
            ],
            [
                'empresa_id'                => 3,
                'lead_id'                   => null,
                'contacto_principal_id'     => null,
                'codigo'                    => 'REQ-0005',
                'titulo'                    => 'Dashboard gerencial',
                'descripcion'               => 'Dashboard para gerencia con KPIs de ventas, facturación y producción.',
                'origen'                    => 'Web',
                'etapa'                     => 'No cotizado',
                'estado'                    => 1,
                'nivel_prioridad'           => 'Bajo',
                'monto_estimado'            => 18000.00,
                'fecha_requerida'           => $now->copy()->addDays(40)->toDateString(),
                'fecha_ingreso_etapa_actual'=> $now->copy()->subDays(1),
                'created_at'                => $now,
                'updated_at'                => $now,
            ],
        ]);
    }
}
