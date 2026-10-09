<?php
// app/Console/Commands/RecalcularPrioridadRequerimientos.php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\RequerimientoPrioridadService;
use Modules\Comercial\Models\Requerimiento;

class RecalcularPrioridadRequerimientos extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'requerimientos:recalcular-prioridad
                            {--ids= : IDs específicos separados por coma}
                            {--todos : Recalcular todos los requerimientos activos}
                            {--estadisticas : Mostrar estadísticas de prioridades}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Recalcula la prioridad de requerimientos según los 4 factores';

    /**
     * Service de prioridad
     *
     * @var RequerimientoPrioridadService
     */
    protected $prioridadService;

    /**
     * Create a new command instance.
     *
     * @param RequerimientoPrioridadService $prioridadService
     */
    public function __construct(RequerimientoPrioridadService $prioridadService)
    {
        parent::__construct();
        $this->prioridadService = $prioridadService;
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        // Opción 1: Mostrar estadísticas
        if ($this->option('estadisticas')) {
            return $this->mostrarEstadisticas();
        }

        // Opción 2: Recalcular todos
        if ($this->option('todos')) {
            return $this->recalcularTodos();
        }

        // Opción 3: Recalcular IDs específicos
        if ($this->option('ids')) {
            $ids = explode(',', $this->option('ids'));
            $ids = array_map('trim', $ids);
            return $this->recalcularEspecificos($ids);
        }

        // Si no se especifica nada, mostrar ayuda
        $this->error('❌ Debes especificar una opción:');
        $this->line('');
        $this->info('Opciones disponibles:');
        $this->line('  --todos                  Recalcular todos los requerimientos activos');
        $this->line('  --ids=1,2,3              Recalcular requerimientos específicos');
        $this->line('  --estadisticas           Mostrar estadísticas de prioridades');
        $this->line('');
        $this->info('Ejemplos:');
        $this->line('  php artisan requerimientos:recalcular-prioridad --todos');
        $this->line('  php artisan requerimientos:recalcular-prioridad --ids=5,10,15');
        $this->line('  php artisan requerimientos:recalcular-prioridad --estadisticas');

        return 1;
    }

    /**
     * Recalcular todos los requerimientos
     *
     * @return int
     */
    protected function recalcularTodos()
    {
        $this->info('🔄 Recalculando prioridad de TODOS los requerimientos activos...');
        $this->line('');

        $total = Requerimiento::activosEnCrm()->count();
        
        if ($total === 0) {
            $this->warn('⚠️  No hay requerimientos activos en el CRM');
            return 0;
        }

        if (!$this->confirm("¿Confirmas recalcular la prioridad de {$total} requerimientos?", true)) {
            $this->info('❌ Operación cancelada');
            return 0;
        }

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $resultados = $this->prioridadService->recalcularPrioridadMasivo();

        $bar->finish();
        $this->line('');
        $this->line('');

        // Mostrar resultados
        $this->mostrarResultados($resultados);

        return 0;
    }

    /**
     * Recalcular requerimientos específicos
     *
     * @param array $ids
     * @return int
     */
    protected function recalcularEspecificos(array $ids)
    {
        $this->info('🔄 Recalculando prioridad de requerimientos específicos...');
        $this->line('');

        // Validar que los IDs existan
        $requerimientosExistentes = Requerimiento::whereIn('id', $ids)
            ->activosEnCrm()
            ->pluck('id')
            ->toArray();

        $idsInvalidos = array_diff($ids, $requerimientosExistentes);

        if (!empty($idsInvalidos)) {
            $this->warn('⚠️  Los siguientes IDs no existen o no están activos en el CRM:');
            $this->line('   ' . implode(', ', $idsInvalidos));
            $this->line('');
        }

        if (empty($requerimientosExistentes)) {
            $this->error('❌ Ninguno de los IDs proporcionados es válido');
            return 1;
        }

        $resultados = $this->prioridadService->recalcularPrioridadMasivo($requerimientosExistentes);

        $this->mostrarResultados($resultados);

        return 0;
    }

    /**
     * Mostrar estadísticas de prioridades
     *
     * @return int
     */
    protected function mostrarEstadisticas()
    {
        $this->info('📊 Estadísticas de Prioridades');
        $this->line('');

        $estadisticas = $this->prioridadService->obtenerEstadisticasPrioridad();

        $this->table(
            ['Métrica', 'Valor'],
            [
                ['Total Requerimientos', $estadisticas['total_requerimientos']],
                ['Puntaje Promedio', $estadisticas['puntaje_promedio_general'] . ' / 10'],
            ]
        );

        $this->line('');
        $this->info('Distribución por Nivel:');
        $this->line('');

        $datosTabla = [];
        $niveles = ['Muy alto', 'Alto', 'Medio', 'Bajo'];

        foreach ($niveles as $nivel) {
            $data = $estadisticas['por_nivel'][$nivel] ?? ['cantidad' => 0, 'puntaje_promedio' => 0];
            
            $porcentaje = $estadisticas['total_requerimientos'] > 0
                ? round(($data['cantidad'] / $estadisticas['total_requerimientos']) * 100, 1)
                : 0;

            $datosTabla[] = [
                $nivel,
                $data['cantidad'],
                $porcentaje . '%',
                $data['puntaje_promedio'] . ' pts',
            ];
        }

        $this->table(
            ['Nivel', 'Cantidad', 'Porcentaje', 'Puntaje Promedio'],
            $datosTabla
        );

        return 0;
    }

    /**
     * Mostrar resultados del recálculo
     *
     * @param array $resultados
     * @return void
     */
    protected function mostrarResultados(array $resultados)
    {
        $this->info('✅ Recálculo completado');
        $this->line('');

        $this->table(
            ['Métrica', 'Valor'],
            [
                ['Total procesados', $resultados['total']],
                ['Actualizados', $resultados['actualizados']],
                ['Errores', $resultados['errores']],
            ]
        );

        // Mostrar cambios si hubo actualizaciones
        if ($resultados['actualizados'] > 0) {
            $this->line('');
            $this->info('Cambios realizados:');
            $this->line('');

            $cambios = array_filter($resultados['detalles'], function($item) {
                return !isset($item['error']);
            });

            if (count($cambios) <= 20) {
                // Mostrar todos si son pocos
                $datosTabla = [];
                foreach ($cambios as $cambio) {
                    $datosTabla[] = [
                        $cambio['id'],
                        $cambio['titulo'],
                        $cambio['anterior'] ?? 'N/A',
                        $cambio['nueva'],
                    ];
                }

                $this->table(
                    ['ID', 'Título', 'Anterior', 'Nueva'],
                    $datosTabla
                );
            } else {
                // Mostrar solo los primeros 10
                $this->warn("Se actualizaron {$resultados['actualizados']} requerimientos (mostrando primeros 10):");
                
                $datosTabla = [];
                for ($i = 0; $i < min(10, count($cambios)); $i++) {
                    $cambio = $cambios[$i];
                    $datosTabla[] = [
                        $cambio['id'],
                        substr($cambio['titulo'], 0, 30),
                        $cambio['anterior'] ?? 'N/A',
                        $cambio['nueva'],
                    ];
                }

                $this->table(
                    ['ID', 'Título', 'Anterior', 'Nueva'],
                    $datosTabla
                );
            }
        }

        // Mostrar errores si los hubo
        if ($resultados['errores'] > 0) {
            $this->line('');
            $this->error('❌ Errores encontrados:');
            $this->line('');

            $errores = array_filter($resultados['detalles'], function($item) {
                return isset($item['error']);
            });

            foreach ($errores as $error) {
                $this->line("  • ID {$error['id']}: {$error['error']}");
            }
        }
    }
}
