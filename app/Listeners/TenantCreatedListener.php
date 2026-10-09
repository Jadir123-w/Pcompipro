<?php

namespace App\Listeners;

use Hyn\Tenancy\Events\Websites\Created;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;

class TenantCreatedListener
{
    public function handle(Created $event)
    {
        $website = $event->website;

        // // Insertar configuración por defecto para el nuevo tenant
        // DB::connection('tenant')->table('payroll_settings')->insert([
        //     'website_id' => $website->id,
        //     'uit' => 4950,
        //     'porcentaje_essalud' => 9.00,
        //     'porcentaje_afp_fondo' => 10.00,
        //     'porcentaje_afp_seguro' => 1.37,
        //     'porcentaje_afp_comision' => 0.77,
        //     'tasa_hora_extra' => 1.25,
        //     'dias_mes_laboral' => 30,
        //     'horas_dia_laboral' => 8,
        //     'created_at' => now(),
        //     'updated_at' => now(),
        // ]);
    }
}
