<?php
// app/Services/RequerimientoPrioridadService.php

namespace App\Services;

use Modules\Comercial\Models\Requerimiento;
use Modules\Comercial\Models\Empresa;
use Modules\Comercial\Models\Proyecto;
use Carbon\Carbon;

/**
 * Servicio para calcular la prioridad de un requerimiento
 * 
 * Sistema de Scoring (0-10 puntos):
 * - Factor 1: Monto estimado (0-3 puntos)
 * - Factor 2: Número de trabajadores de la empresa (0-3 puntos)
 * - Factor 3: Antigüedad del cliente (0-2 puntos)
 * - Factor 4: Frecuencia de compra (0-2 puntos)
 * 
 * Clasificación final:
 * - 8-10 puntos: Muy Alto
 * - 5-7 puntos:  Alto
 * - 3-4 puntos:  Medio
 * - 0-2 puntos:  Bajo
 */
class RequerimientoPrioridadService
{
    /**
     * Calcular prioridad completa de un requerimiento
     * 
     * @param Requerimiento $requerimiento
     * @return array ['nivel' => string, 'puntaje' => int, 'desglose' => array]
     */
    public function calcularPrioridad(Requerimiento $requerimiento): array
    {
        $puntajes = [
            'monto'           => $this->calcularPuntajeMonto($requerimiento),
            'trabajadores'    => $this->calcularPuntajeTrabajadores($requerimiento),
            'antiguedad'      => $this->calcularPuntajeAntiguedad($requerimiento),
            'frecuencia'      => $this->calcularPuntajeFrecuencia($requerimiento),
        ];

        $puntajeTotal = array_sum($puntajes);
        $nivel = $this->determinarNivel($puntajeTotal);

        return [
            'nivel'     => $nivel,
            'puntaje'   => $puntajeTotal,
            'desglose'  => $puntajes,
        ];
    }

    /**
     * Actualizar la prioridad del requerimiento en la base de datos
     * 
     * @param Requerimiento $requerimiento
     * @return Requerimiento
     */
    public function actualizarPrioridad(Requerimiento $requerimiento): Requerimiento
    {
        $resultado = $this->calcularPrioridad($requerimiento);
        
        $requerimiento->nivel_prioridad = $resultado['nivel'];
        $requerimiento->save();

        // Opcional: Registrar en historial
        $requerimiento->historial()->create([
            'tipo_actividad' => 'Recalculo de prioridad',
            'detalle'        => "Prioridad actualizada a '{$resultado['nivel']}' (Puntaje: {$resultado['puntaje']}/10)",
            'etapa_anterior' => $requerimiento->etapa,
            'etapa_nueva'    => $requerimiento->etapa,
            'usuario'        => 'Sistema',
        ]);

        return $requerimiento;
    }

    // =========================================================================
    // FACTOR 1: MONTO ESTIMADO (0-3 puntos)
    // =========================================================================

    /**
     * Calcular puntos según el monto estimado del requerimiento
     * 
     * Escala:
     * - >= S/ 4,000:     3 puntos (Muy Alto)
     * - S/ 2,000-3,999:  2 puntos (Alto)
     * - S/ 500-1,999:    1 punto  (Medio)
     * - < S/ 500:        0 puntos (Bajo)
     * 
     * @param Requerimiento $requerimiento
     * @return int
     */
    protected function calcularPuntajeMonto(Requerimiento $requerimiento): int
    {
        $monto = $requerimiento->monto_estimado ?? 0;

        if ($monto >= 4000) {
            return 3;
        } elseif ($monto >= 2000) {
            return 2;
        } elseif ($monto >= 500) {
            return 1;
        }

        return 0;
    }

    // =========================================================================
    // FACTOR 2: NÚMERO DE TRABAJADORES (0-3 puntos)
    // =========================================================================

    /**
     * Calcular puntos según el número de trabajadores de la empresa
     * 
     * Escala:
     * - >= 250 trabajadores:   3 puntos (Grande)
     * - 100-249 trabajadores:  2 puntos (Mediana)
     * - 20-99 trabajadores:    1 punto  (Pequeña)
     * - < 20 trabajadores:     0 puntos (Micro)
     * 
     * @param Requerimiento $requerimiento
     * @return int
     */
    protected function calcularPuntajeTrabajadores(Requerimiento $requerimiento): int
    {
        if (!$requerimiento->empresa) {
            return 0;
        }

        $numTrabajadores = $requerimiento->empresa->numero_trabajadores ?? 0;

        if ($numTrabajadores >= 250) {
            return 3;
        } elseif ($numTrabajadores >= 100) {
            return 2;
        } elseif ($numTrabajadores >= 20) {
            return 1;
        }

        return 0;
    }

    // =========================================================================
    // FACTOR 3: ANTIGÜEDAD DEL CLIENTE (0-2 puntos)
    // =========================================================================

    /**
     * Calcular puntos según antigüedad del cliente
     * (tiempo desde el primer proyecto cerrado/completado)
     * 
     * Escala:
     * - >= 2 años:  2 puntos (Cliente antiguo)
     * - 1-2 años:   1 punto  (Cliente regular)
     * - < 1 año:    0 puntos (Cliente nuevo)
     * 
     * @param Requerimiento $requerimiento
     * @return int
     */
    protected function calcularPuntajeAntiguedad(Requerimiento $requerimiento): int
    {
        if (!$requerimiento->empresa) {
            return 0;
        }

        // Buscar el primer proyecto cerrado/completado de esta empresa
        $primerProyecto = Proyecto::where('cliente_id', $requerimiento->empresa_id)
            ->whereIn('etapa_actual_proyecto', ['Cerrado', 'Completado', 'Entregado'])
            ->orderBy('created_at', 'asc')
            ->first();

        if (!$primerProyecto) {
            // No tiene proyectos cerrados = Cliente nuevo
            return 0;
        }

        $antiguedadAnios = Carbon::parse($primerProyecto->created_at)
            ->diffInYears(now());

        if ($antiguedadAnios >= 2) {
            return 2;
        } elseif ($antiguedadAnios >= 1) {
            return 1;
        }

        return 0;
    }

    // =========================================================================
    // FACTOR 4: FRECUENCIA DE COMPRA (0-2 puntos)
    // =========================================================================

    /**
     * Calcular puntos según frecuencia de compra
     * (promedio de meses entre proyectos)
     * 
     * Escala:
     * - Cada 0-2 meses:   2 puntos (Muy frecuente)
     * - Cada 3-5 meses:   1 punto  (Frecuente)
     * - > 6 meses:        0 puntos (Poco frecuente)
     * - Sin historial:    0 puntos (Nuevo cliente)
     * 
     * @param Requerimiento $requerimiento
     * @return int
     */
    protected function calcularPuntajeFrecuencia(Requerimiento $requerimiento): int
    {
        if (!$requerimiento->empresa) {
            return 0;
        }

        // Obtener proyectos cerrados ordenados por fecha
        $proyectos = Proyecto::where('cliente_id', $requerimiento->empresa_id)
            ->whereIn('etapa_actual_proyecto', ['Cerrado', 'Completado', 'Entregado'])
            ->orderBy('created_at', 'asc')
            ->get();

        // Necesitamos al menos 2 proyectos para calcular frecuencia
        if ($proyectos->count() < 2) {
            return 0;
        }

        // Calcular intervalos entre proyectos consecutivos
        $intervalos = [];
        for ($i = 1; $i < $proyectos->count(); $i++) {
            $fechaAnterior = Carbon::parse($proyectos[$i - 1]->created_at);
            $fechaActual = Carbon::parse($proyectos[$i]->created_at);
            $intervalos[] = $fechaAnterior->diffInMonths($fechaActual);
        }

        // Calcular promedio de meses entre proyectos
        $promedioMeses = array_sum($intervalos) / count($intervalos);

        if ($promedioMeses <= 2) {
            return 2; // Muy frecuente
        } elseif ($promedioMeses <= 5) {
            return 1; // Frecuente
        }

        return 0; // Poco frecuente
    }

    // =========================================================================
    // DETERMINACIÓN DEL NIVEL FINAL
    // =========================================================================

    /**
     * Determinar el nivel de prioridad según el puntaje total
     * 
     * @param int $puntajeTotal
     * @return string
     */
    protected function determinarNivel(int $puntajeTotal): string
    {
        if ($puntajeTotal >= 8) {
            return 'Muy alto';
        } elseif ($puntajeTotal >= 5) {
            return 'Alto';
        } elseif ($puntajeTotal >= 3) {
            return 'Medio';
        }

        return 'Bajo';
    }

    // =========================================================================
    // MÉTODOS AUXILIARES
    // =========================================================================

    /**
     * Análisis semántico básico de la descripción
     * para estimar monto cuando no está especificado
     * 
     * Busca patrones como:
     * - "100 uniformes"
     * - "50 chalecos"
     * - "200 gorras"
     * 
     * @param Requerimiento $requerimiento
     * @return float|null
     */
    public function estimarMontoDesdeDescripcion(Requerimiento $requerimiento): ?float
    {
        if (!$requerimiento->descripcion) {
            return null;
        }

        $descripcion = strtolower($requerimiento->descripcion);

        // Catálogo de productos con precios promedio
        $catalogoProductos = [
            'uniforme'   => 80.00,
            'chaleco'    => 45.00,
            'gorra'      => 15.00,
            'polo'       => 25.00,
            'camisa'     => 35.00,
            'pantalon'   => 50.00,
            'mochila'    => 40.00,
            'libreta'    => 8.00,
            'lapicero'   => 2.50,
            'taza'       => 12.00,
            'llavero'    => 5.00,
            'bolsa'      => 3.50,
        ];

        $montoTotal = 0;

        // Buscar patrones: "CANTIDAD PRODUCTO"
        foreach ($catalogoProductos as $producto => $precio) {
            // Pattern: número + producto (ej: "100 uniformes", "50 chalecos")
            $pattern = '/(\d+)\s*' . preg_quote($producto) . 's?/i';
            
            if (preg_match($pattern, $descripcion, $matches)) {
                $cantidad = intval($matches[1]);
                $subtotal = $cantidad * $precio;
                $montoTotal += $subtotal;
            }
        }

        return $montoTotal > 0 ? $montoTotal : null;
    }

    /**
     * Actualizar monto estimado si no existe, usando análisis semántico
     * 
     * @param Requerimiento $requerimiento
     * @return bool
     */
    public function actualizarMontoSiNoExiste(Requerimiento $requerimiento): bool
    {
        // Si ya tiene monto, no hacer nada
        if ($requerimiento->monto_estimado && $requerimiento->monto_estimado > 0) {
            return false;
        }

        $montoEstimado = $this->estimarMontoDesdeDescripcion($requerimiento);

        if ($montoEstimado) {
            $requerimiento->monto_estimado = $montoEstimado;
            $requerimiento->save();

            // Registrar en historial
            $requerimiento->historial()->create([
                'tipo_actividad' => 'Estimación de monto',
                'detalle'        => "Monto estimado automáticamente: S/ " . number_format($montoEstimado, 2),
                'etapa_anterior' => $requerimiento->etapa,
                'etapa_nueva'    => $requerimiento->etapa,
                'usuario'        => 'Sistema - Análisis Semántico',
            ]);

            return true;
        }

        return false;
    }

    /**
     * Actualizar monto real desde la cotización aprobada
     * 
     * @param Requerimiento $requerimiento
     * @return bool
     */
    public function actualizarMontoDesdeGotizacionAprobada(Requerimiento $requerimiento): bool
    {
        if (!$requerimiento->cotizacion_aprobada) {
            return false;
        }

        $cotizacionAprobada = $requerimiento->cotizaciones()
            ->where('estado', 'Aprobada')
            ->first();

        if (!$cotizacionAprobada || !$cotizacionAprobada->monto) {
            return false;
        }

        $montoAnterior = $requerimiento->monto_estimado;
        $requerimiento->monto_estimado = $cotizacionAprobada->monto;
        $requerimiento->save();

        // Registrar cambio
        $requerimiento->historial()->create([
            'tipo_actividad' => 'Actualización de monto',
            'detalle'        => sprintf(
                'Monto actualizado de S/ %s a S/ %s (desde cotización aprobada)',
                number_format($montoAnterior, 2),
                number_format($cotizacionAprobada->monto, 2)
            ),
            'etapa_anterior' => $requerimiento->etapa,
            'etapa_nueva'    => $requerimiento->etapa,
            'usuario'        => 'Sistema',
        ]);

        return true;
    }

    /**
     * Recalcular prioridad de múltiples requerimientos
     * Útil para ejecución en batch o comando artisan
     * 
     * @param array $requerimientoIds
     * @return array
     */
    public function recalcularPrioridadMasivo(array $requerimientoIds = []): array
    {
        $query = Requerimiento::activosEnCrm();

        if (!empty($requerimientoIds)) {
            $query->whereIn('id', $requerimientoIds);
        }

        $requerimientos = $query->get();
        $resultados = [
            'total'       => $requerimientos->count(),
            'actualizados'=> 0,
            'errores'     => 0,
            'detalles'    => [],
        ];

        foreach ($requerimientos as $req) {
            try {
                $prioridadAnterior = $req->nivel_prioridad;
                $this->actualizarPrioridad($req);
                
                $resultados['actualizados']++;
                $resultados['detalles'][] = [
                    'id'        => $req->id,
                    'titulo'    => $req->titulo,
                    'anterior'  => $prioridadAnterior,
                    'nueva'     => $req->nivel_prioridad,
                ];
            } catch (\Exception $e) {
                $resultados['errores']++;
                $resultados['detalles'][] = [
                    'id'        => $req->id,
                    'error'     => $e->getMessage(),
                ];
            }
        }

        return $resultados;
    }

    /**
     * Obtener estadísticas de prioridades
     * 
     * @return array
     */
    public function obtenerEstadisticasPrioridad(): array
    {
        $requerimientos = Requerimiento::activosEnCrm()->get();
        
        $estadisticas = [];
        $totalPuntajes = 0;

        foreach ($requerimientos as $req) {
            $calculo = $this->calcularPrioridad($req);
            
            $nivel = $calculo['nivel'];
            if (!isset($estadisticas[$nivel])) {
                $estadisticas[$nivel] = [
                    'cantidad' => 0,
                    'puntaje_promedio' => 0,
                    'puntajes' => [],
                ];
            }
            
            $estadisticas[$nivel]['cantidad']++;
            $estadisticas[$nivel]['puntajes'][] = $calculo['puntaje'];
            $totalPuntajes += $calculo['puntaje'];
        }

        // Calcular promedios
        foreach ($estadisticas as $nivel => &$data) {
            $data['puntaje_promedio'] = round(
                array_sum($data['puntajes']) / count($data['puntajes']),
                2
            );
            unset($data['puntajes']); // Limpiar array de puntajes
        }

        return [
            'total_requerimientos' => $requerimientos->count(),
            'puntaje_promedio_general' => $requerimientos->count() > 0 
                ? round($totalPuntajes / $requerimientos->count(), 2)
                : 0,
            'por_nivel' => $estadisticas,
        ];
    }
}
