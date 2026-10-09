<?php

namespace Modules\Marketing\Console;

use Hyn\Tenancy\Environment;
use Hyn\Tenancy\Models\Website;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Modules\Marketing\Services\Com07DetectorService;
use Modules\Marketing\Services\Com07PriorizacionService;

/**
 * Proceso automático diario de detección de oportunidades (COM-07).
 *
 * Recorre el ERP y crea una oportunidad por cada cliente que merece ser
 * recuperado: cotizaciones caídas, Requerimientos no cotizados y contactos sin
 * actividad comercial por 8 semanas o más.
 *
 * Corre de madrugada para que las ejecutivas encuentren el panel al día cuando
 * empiezan la jornada. Es idempotente: correrlo dos veces no duplica nada.
 *
 * Uso:
 *   php artisan marketing:com07:detectar                → todos los tenants
 *   php artisan marketing:com07:detectar --tenant=1     → un tenant
 *   php artisan marketing:com07:detectar --dry-run      → no escribe, solo reporta
 */
class Com07DetectarOportunidadesCommand extends Command
{
    protected $signature = 'marketing:com07:detectar
                            {--tenant= : ID del tenant (Website) a procesar. Sin este parámetro, procesa todos.}
                            {--dry-run : No crea oportunidades; solo reporta cuántas se crearían.}';

    protected $description = 'Detecta oportunidades de recuperación comercial: cotizaciones caídas, no cotizados e inactividad de 8 semanas.';

    public function handle()
    {
        $tenantId  = $this->option('tenant');
        $simulacion = (bool) $this->option('dry-run');

        if ($tenantId) {
            $website = Website::find($tenantId);
            if (!$website) {
                $this->error("Tenant con ID {$tenantId} no encontrado.");
                return 1;
            }
            $websites = collect([$website]);
        } else {
            $websites = Website::all();
            if ($websites->isEmpty()) {
                $this->info('No hay tenants registrados.');
                return 0;
            }
        }

        if ($simulacion) {
            $this->warn('[DRY-RUN] No se creará ninguna oportunidad; solo se reportará.');
        }

        $tenancy = app(Environment::class);
        $conFallas = 0;

        foreach ($websites as $website) {
            try {
                $tenancy->tenant($website);

                $servicio = new Com07DetectorService();
                $resumen  = $servicio->detectar($simulacion);

                // El recalculo diario de la priorización, acordado en la
                // Conformidad: "los indicadores se recalculan en cada
                // ejecución del proceso automático diario". Cubre tanto las
                // recién creadas como las que ya estaban vigentes.
                $resumen['priorizadas'] = 0;
                if (!$simulacion) {
                    $resumen['priorizadas'] = (new Com07PriorizacionService())->calcularParaVigentes();
                }

                $this->reportar($website->id, $resumen);
                $this->registrarEnBitacora($website->id, $resumen, $simulacion);
            } catch (\Exception $e) {
                // Un tenant con datos inconsistentes no debe impedir que los
                // demás se procesen: se reporta y se sigue.
                $conFallas++;
                $this->error("Tenant {$website->id}: {$e->getMessage()}");
                Log::error('COM-07 detección: falló un tenant', [
                    'tenant'  => $website->id,
                    'mensaje' => $e->getMessage(),
                ]);
            }
        }

        return $conFallas > 0 ? 1 : 0;
    }

    private function reportar($tenantId, array $resumen)
    {
        $this->info("Tenant {$tenantId}: {$resumen['creadas']} oportunidad(es) de {$resumen['evaluados']} candidato(s).");

        if ($resumen['creadas'] > 0) {
            $this->table(
                ['Origen', 'Creadas'],
                [
                    ['Cotización caída', $resumen['por_origen']['cotizacion_caida']],
                    ['No cotizado',      $resumen['por_origen']['no_cotizado']],
                    ['Sin movimiento',   $resumen['por_origen']['sin_movimiento']],
                ]
            );
        }

        if ($resumen['omitidas_vigente'] > 0) {
            $this->line("  Omitidas por tener una oportunidad vigente: {$resumen['omitidas_vigente']}");
        }
        if ($resumen['omitidas_repeticion'] > 0) {
            $this->line("  Omitidas por haberse generado antes: {$resumen['omitidas_repeticion']}");
        }
        if ($resumen['sin_responsable'] > 0) {
            $this->warn("  Quedaron pendientes de asignación: {$resumen['sin_responsable']}");
        }
        if (isset($resumen['priorizadas']) && $resumen['priorizadas'] > 0) {
            $this->line("  Priorización recalculada: {$resumen['priorizadas']} oportunidad(es)");
        }
    }

    private function registrarEnBitacora($tenantId, array $resumen, $simulacion)
    {
        Log::info('COM-07 detección diaria', [
            'tenant'          => $tenantId,
            'simulacion'      => $simulacion,
            'evaluados'       => $resumen['evaluados'],
            'creadas'         => $resumen['creadas'],
            'por_origen'      => $resumen['por_origen'],
            'sin_responsable' => $resumen['sin_responsable'],
        ]);
    }
}
