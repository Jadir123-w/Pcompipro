<?php

namespace Modules\Marketing\Services;

use Illuminate\Support\Facades\DB;
use Modules\Marketing\Models\Com07Oportunidad;

/**
 * Com07PriorizacionService — Modelo de Priorización Comercial (COM-07).
 *
 * Implementa EXACTAMENTE lo acordado: las tablas de puntos de la
 * Especificación Puntual (punto 6.2) aplicadas con la regla de la Conformidad
 * a las Precisiones — se usa la información verificable existente en el ERP y
 * el factor cuyo dato no pueda determinarse obtiene 0 puntos. Nada se
 * interpreta de textos libres.
 *
 * ── Potencial Comercial (0-10, siempre se calcula) ──
 *   Historial por monto:      >= 4,000 → 3 · 2,000-3,999 → 2 · 1,000-1,999 → 1 · resto → 0
 *   Nº de trabajadores:       >= 250 → 3 · 100-249 → 2 · 20-99 → 1 · resto → 0
 *   Antigüedad de relación:   >= 2 años → 2 · >= 1 año → 1 · resto → 0
 *   Frecuencia comercial:     intervalo promedio <= 6 meses → 2 · > 6 meses → 1 · insuficiente → 0
 *
 * ── Urgencia Comercial (0-10, SOLO con necesidad actual) ──
 * La necesidad actual es el Requerimiento vinculado: una cotización caída o un
 * no cotizado la tienen; una oportunidad por inactividad no — su Urgencia es
 * "No aplica" (NULL), que no es lo mismo que 0 puntos.
 *   Valor estimado:   los mismos rangos de monto, sobre la mayor valorización
 *                     relacionada (cotización, precotización o Requerimiento
 *                     valorizado); sin valorización → 0.
 *   Proximidad:       días hasta «Fecha requerida»: <= 15 → 3 · 16-30 → 2 ·
 *                     31-60 → 1 · más lejos o sin fecha → 0. Una fecha ya
 *                     vencida es lo más próximo que existe: 3.
 *   Intención:        existe la acción comercial concreta (el Requerimiento) → 2.
 *                     Conforme a la Conformidad, en esta fase los valores
 *                     posibles son 2 o 0 — y con necesidad actual siempre hay
 *                     Requerimiento, así que es 2.
 *   Definición:       producto y cantidad (ítems de la precotización vinculada)
 *                     + «Fecha requerida» → 2 · solo producto y cantidad → 1 ·
 *                     sin información estructurada → 0.
 *
 * ── Prioridad Comercial ──
 *   Prioridad = Potencial × 40 % + Urgencia × 60 %   (Urgencia No aplica → 0)
 *
 * El cálculo se hace por CUENTA (empresa) en los factores de cuenta — monto,
 * trabajadores, antigüedad, frecuencia — con el mismo criterio del proceso de
 * detección: la mayoría del historial no tiene contacto asignado, y la
 * Especificación permite usar la información de la empresa cuando la del
 * contacto es insuficiente.
 *
 * Trabaja EN LOTE: 13 consultas agregadas por corrida, sin importar cuántas
 * oportunidades entren.
 */
class Com07PriorizacionService
{
    /** Días del "intervalo promedio <= 6 meses" de la frecuencia. */
    const DIAS_SEIS_MESES = 183;

    /*
    |--------------------------------------------------------------------------
    | Entradas
    |--------------------------------------------------------------------------
    */

    /**
     * Recalcula todas las oportunidades vigentes (la corrida diaria).
     *
     * @return int  cuántas se recalcularon
     */
    public function calcularParaVigentes()
    {
        $ids = Com07Oportunidad::vigentes()->pluck('id')->all();

        foreach (array_chunk($ids, 500) as $lote) {
            $this->calcularPara($lote);
        }

        return count($ids);
    }

    /**
     * Calcula y guarda la priorización de un conjunto de oportunidades.
     *
     * @param  array  $oportunidadIds
     * @return void
     */
    public function calcularPara(array $oportunidadIds)
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $oportunidadIds))));
        if (empty($ids)) {
            return;
        }

        $oportunidades = Com07Oportunidad::whereIn('id', $ids)->get();
        if ($oportunidades->isEmpty()) {
            return;
        }

        $empresaIds = $oportunidades->pluck('empresa_id')->filter()->unique()->map(function ($v) { return (int) $v; })->values()->all();
        $reqIds     = $oportunidades->pluck('requerimiento_id')->filter()->unique()->map(function ($v) { return (int) $v; })->values()->all();

        $cuenta = $this->datosDeCuenta($empresaIds);
        $necesidad = $this->datosDeNecesidad($reqIds);

        foreach ($oportunidades as $oportunidad) {
            $this->calcularUna($oportunidad, $cuenta, $necesidad);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Cálculo de una oportunidad
    |--------------------------------------------------------------------------
    */

    private function calcularUna(Com07Oportunidad $oportunidad, array $cuenta, array $necesidad)
    {
        $empresaId = (int) $oportunidad->empresa_id;
        $reqId     = $oportunidad->requerimiento_id ? (int) $oportunidad->requerimiento_id : null;

        list($potencial, $sustentoPotencial) = $this->puntajePotencial($empresaId, $cuenta);

        $urgencia         = null;
        $sustentoUrgencia = null;
        if ($reqId !== null) {
            list($urgencia, $sustentoUrgencia) = $this->puntajeUrgencia($reqId, $necesidad);
        }

        // "Cuando la Urgencia sea No aplica, se utilizará valor 0 únicamente
        // para el cálculo matemático de la Prioridad."
        $prioridad = round($potencial * 0.4 + ($urgencia === null ? 0 : $urgencia) * 0.6, 1);

        $oportunidad->potencial_puntaje  = $potencial;
        $oportunidad->urgencia_puntaje   = $urgencia;
        $oportunidad->prioridad_puntaje  = $prioridad;
        $oportunidad->prioridad_sustento = json_encode([
            'potencial' => $sustentoPotencial,
            'urgencia'  => $sustentoUrgencia,
        ], JSON_UNESCAPED_UNICODE);
        $oportunidad->save();
    }

    /*
    |--------------------------------------------------------------------------
    | Potencial Comercial
    |--------------------------------------------------------------------------
    */

    /** @return array [puntaje, sustento] */
    private function puntajePotencial($empresaId, array $cuenta)
    {
        $monto        = isset($cuenta['montos'][$empresaId]) ? $cuenta['montos'][$empresaId] : null;
        $trabajadores = isset($cuenta['trabajadores'][$empresaId]) ? $cuenta['trabajadores'][$empresaId] : null;
        $primera      = isset($cuenta['primera_fecha'][$empresaId]) ? $cuenta['primera_fecha'][$empresaId] : null;
        $intervalo    = isset($cuenta['intervalo_dias'][$empresaId]) ? $cuenta['intervalo_dias'][$empresaId] : null;

        $pMonto        = $this->puntosPorMonto($monto);
        $pTrabajadores = $this->puntosPorTrabajadores($trabajadores);
        $pAntiguedad   = $this->puntosPorAntiguedad($primera);
        $pFrecuencia   = $this->puntosPorFrecuencia($intervalo);

        $sustento = [
            ['factor' => 'Historial comercial por monto', 'valor' => $monto !== null ? 'S/ ' . number_format($monto, 2) : 'Sin historial', 'puntos' => $pMonto],
            ['factor' => 'Número de trabajadores', 'valor' => $trabajadores !== null ? (string) $trabajadores : 'Sin dato', 'puntos' => $pTrabajadores],
            ['factor' => 'Antigüedad de relación', 'valor' => $primera ? 'Desde ' . date('d/m/Y', strtotime($primera)) : 'Información insuficiente', 'puntos' => $pAntiguedad],
            ['factor' => 'Frecuencia comercial', 'valor' => $intervalo !== null ? 'Cada ' . round($intervalo) . ' día(s)' : 'Información insuficiente', 'puntos' => $pFrecuencia],
        ];

        return [$pMonto + $pTrabajadores + $pAntiguedad + $pFrecuencia, $sustento];
    }

    /** Rangos de monto de la Especificación — los mismos para Potencial y Valor estimado. */
    private function puntosPorMonto($monto)
    {
        if ($monto === null) {
            return 0;
        }
        if ($monto >= 4000) {
            return 3;
        }
        if ($monto >= 2000) {
            return 2;
        }
        if ($monto >= 1000) {
            return 1;
        }

        return 0;
    }

    private function puntosPorTrabajadores($n)
    {
        if ($n === null) {
            return 0;
        }
        if ($n >= 250) {
            return 3;
        }
        if ($n >= 100) {
            return 2;
        }
        if ($n >= 20) {
            return 1;
        }

        return 0;
    }

    private function puntosPorAntiguedad($primeraFecha)
    {
        if ($primeraFecha === null) {
            return 0;
        }

        $anios = (time() - strtotime($primeraFecha)) / (365.25 * 86400);
        if ($anios >= 2) {
            return 2;
        }
        if ($anios >= 1) {
            return 1;
        }

        return 0;
    }

    private function puntosPorFrecuencia($intervaloDias)
    {
        if ($intervaloDias === null) {
            return 0;   // menos de dos hechos comerciales: información insuficiente
        }

        return $intervaloDias <= self::DIAS_SEIS_MESES ? 2 : 1;
    }

    /*
    |--------------------------------------------------------------------------
    | Urgencia Comercial
    |--------------------------------------------------------------------------
    */

    /** @return array [puntaje, sustento] */
    private function puntajeUrgencia($reqId, array $necesidad)
    {
        $valor         = isset($necesidad['valores'][$reqId]) ? $necesidad['valores'][$reqId] : null;
        $fechaRequerida = isset($necesidad['fechas'][$reqId]) ? $necesidad['fechas'][$reqId] : null;
        $conItems      = !empty($necesidad['con_items'][$reqId]);

        $pValor      = $this->puntosPorMonto($valor);
        $pProximidad = $this->puntosPorProximidad($fechaRequerida);

        // La acción comercial concreta y verificable ES el Requerimiento, y
        // este método solo corre cuando existe: 2 puntos. (Conformidad: en
        // esta fase los valores posibles de la Intención son 2 o 0.)
        $pIntencion = 2;

        $pDefinicion = 0;
        if ($conItems) {
            $pDefinicion = $fechaRequerida !== null ? 2 : 1;
        }

        $sustento = [
            ['factor' => 'Valor estimado de la necesidad', 'valor' => $valor !== null ? 'S/ ' . number_format($valor, 2) : 'Sin valorización', 'puntos' => $pValor],
            ['factor' => 'Proximidad', 'valor' => $fechaRequerida ? 'Fecha requerida ' . date('d/m/Y', strtotime($fechaRequerida)) : 'Sin fecha', 'puntos' => $pProximidad],
            ['factor' => 'Intención comercial', 'valor' => 'Requerimiento registrado', 'puntos' => $pIntencion],
            ['factor' => 'Definición de la necesidad', 'valor' => $conItems ? ($fechaRequerida ? 'Producto, cantidad y fecha' : 'Producto y cantidad, sin fecha') : 'Sin información estructurada', 'puntos' => $pDefinicion],
        ];

        return [$pValor + $pProximidad + $pIntencion + $pDefinicion, $sustento];
    }

    private function puntosPorProximidad($fechaRequerida)
    {
        if ($fechaRequerida === null) {
            return 0;
        }

        // Días que faltan; negativo si la fecha ya pasó. Una necesidad con la
        // fecha vencida es lo más próximo que existe: puntúa como <= 15.
        $dias = (strtotime(substr($fechaRequerida, 0, 10)) - strtotime(date('Y-m-d'))) / 86400;

        if ($dias <= 15) {
            return 3;
        }
        if ($dias <= 30) {
            return 2;
        }
        if ($dias <= 60) {
            return 1;
        }

        return 0;
    }

    /*
    |--------------------------------------------------------------------------
    | Datos de la CUENTA (empresa), en lote
    |--------------------------------------------------------------------------
    */

    private function datosDeCuenta(array $empresaIds)
    {
        if (empty($empresaIds)) {
            return ['montos' => [], 'trabajadores' => [], 'primera_fecha' => [], 'intervalo_dias' => []];
        }

        // Fechas de los hechos comerciales: alimentan antigüedad y frecuencia.
        // La frecuencia usa solo Requerimientos, cotizaciones y Proyectos
        // (Especificación 6.2.1); la antigüedad usa además el alta de
        // contactos, interacciones y precotizaciones — el primer rastro real
        // de la relación.
        $eventosFrecuencia = [];
        $primeraFecha      = [];

        $registrar = function ($filas, $paraFrecuencia) use (&$eventosFrecuencia, &$primeraFecha) {
            foreach ($filas as $fila) {
                $empresaId = (int) $fila->empresa_id;
                $fecha     = substr((string) $fila->f, 0, 10);
                if ($fecha === '') {
                    continue;
                }
                if ($paraFrecuencia) {
                    $eventosFrecuencia[$empresaId][] = $fecha;
                }
                if (!isset($primeraFecha[$empresaId]) || strcmp($fecha, $primeraFecha[$empresaId]) < 0) {
                    $primeraFecha[$empresaId] = $fecha;
                }
            }
        };

        $registrar($this->tabla('requerimiento')
            ->selectRaw('empresa_id, created_at AS f')
            ->whereIn('empresa_id', $empresaIds)->whereNull('deleted_at')->get(), true);

        $registrar($this->tabla('cotizaciones_requerimiento as cr')
            ->join('requerimiento as r', 'r.id', '=', 'cr.requerimiento_id')
            ->selectRaw('r.empresa_id, cr.created_at AS f')
            ->whereIn('r.empresa_id', $empresaIds)->whereNull('r.deleted_at')->get(), true);

        $registrar($this->tabla('proyecto')
            ->selectRaw('empresa_id, created_at AS f')
            ->whereIn('empresa_id', $empresaIds)->whereNull('deleted_at')->get(), true);

        $registrar($this->tabla('quotation_prequotes')
            ->selectRaw('empresa_id, created_at AS f')
            ->whereIn('empresa_id', $empresaIds)->whereNull('deleted_at')->get(), false);

        $registrar($this->tabla('contacto')
            ->selectRaw('empresa_id, MIN(created_at) AS f')
            ->whereIn('empresa_id', $empresaIds)->whereNull('deleted_at')
            ->groupBy('empresa_id')->get(), false);

        $registrar($this->tabla('contacto_interaccion as ci')
            ->join('contacto as c', 'c.id', '=', 'ci.contacto_id')
            ->selectRaw('c.empresa_id, MIN(ci.created_at) AS f')
            ->whereIn('c.empresa_id', $empresaIds)->whereNull('ci.deleted_at')
            ->groupBy('c.empresa_id')->get(), false);

        // Intervalo promedio entre hechos = (último - primero) / (n - 1).
        $intervalo = [];
        foreach ($eventosFrecuencia as $empresaId => $fechas) {
            $fechas = array_values(array_unique($fechas));
            if (count($fechas) < 2) {
                continue;
            }
            sort($fechas);
            $dias = (strtotime(end($fechas)) - strtotime($fechas[0])) / 86400;
            $intervalo[$empresaId] = $dias / (count($fechas) - 1);
        }

        return [
            'montos'         => $this->montoHistoricoPorEmpresa($empresaIds),
            'trabajadores'   => $this->trabajadoresPorEmpresa($empresaIds),
            'primera_fecha'  => $primeraFecha,
            'intervalo_dias' => $intervalo,
        ];
    }

    /** Mayor importe histórico: cotizaciones, Requerimientos valorizados, Proyectos y precotizaciones. */
    private function montoHistoricoPorEmpresa(array $empresaIds)
    {
        $mapa = [];

        $acumular = function ($filas) use (&$mapa) {
            foreach ($filas as $fila) {
                if ($fila->m === null) {
                    continue;
                }
                $empresaId = (int) $fila->empresa_id;
                $monto     = (float) $fila->m;
                if (!isset($mapa[$empresaId]) || $monto > $mapa[$empresaId]) {
                    $mapa[$empresaId] = $monto;
                }
            }
        };

        $acumular($this->tabla('cotizaciones_requerimiento as cr')
            ->join('requerimiento as r', 'r.id', '=', 'cr.requerimiento_id')
            ->selectRaw('r.empresa_id, MAX(cr.monto) AS m')
            ->whereIn('r.empresa_id', $empresaIds)->whereNull('r.deleted_at')
            ->groupBy('r.empresa_id')->get());

        $acumular($this->tabla('requerimiento')
            ->selectRaw('empresa_id, MAX(monto_estimado) AS m')
            ->whereIn('empresa_id', $empresaIds)->whereNull('deleted_at')
            ->groupBy('empresa_id')->get());

        $acumular($this->tabla('proyecto')
            ->selectRaw('empresa_id, MAX(monto_total) AS m')
            ->whereIn('empresa_id', $empresaIds)->whereNull('deleted_at')
            ->groupBy('empresa_id')->get());

        $acumular($this->tabla('quotation_prequotes')
            ->selectRaw('empresa_id, MAX(total) AS m')
            ->whereIn('empresa_id', $empresaIds)->whereNull('deleted_at')
            ->groupBy('empresa_id')->get());

        return $mapa;
    }

    private function trabajadoresPorEmpresa(array $empresaIds)
    {
        $filas = $this->tabla('empresa')
            ->select('id', 'n_trabajadores')
            ->whereIn('id', $empresaIds)
            ->get();

        $mapa = [];
        foreach ($filas as $fila) {
            if ($fila->n_trabajadores !== null) {
                $mapa[(int) $fila->id] = (int) $fila->n_trabajadores;
            }
        }

        return $mapa;
    }

    /*
    |--------------------------------------------------------------------------
    | Datos de la NECESIDAD ACTUAL (requerimiento), en lote
    |--------------------------------------------------------------------------
    */

    private function datosDeNecesidad(array $reqIds)
    {
        if (empty($reqIds)) {
            return ['valores' => [], 'fechas' => [], 'con_items' => []];
        }

        // La mayor valorización relacionada: cotización subida, precotización
        // o el propio Requerimiento valorizado.
        $valores = [];
        $mayor = function ($filas) use (&$valores) {
            foreach ($filas as $fila) {
                if ($fila->m === null) {
                    continue;
                }
                $reqId = (int) $fila->requerimiento_id;
                $monto = (float) $fila->m;
                if (!isset($valores[$reqId]) || $monto > $valores[$reqId]) {
                    $valores[$reqId] = $monto;
                }
            }
        };

        $mayor($this->tabla('cotizaciones_requerimiento')
            ->selectRaw('requerimiento_id, MAX(monto) AS m')
            ->whereIn('requerimiento_id', $reqIds)
            ->groupBy('requerimiento_id')->get());

        $mayor($this->tabla('quotation_prequotes')
            ->selectRaw('requerimiento_id, MAX(total) AS m')
            ->whereIn('requerimiento_id', $reqIds)->whereNull('deleted_at')
            ->groupBy('requerimiento_id')->get());

        $mayor($this->tabla('requerimiento')
            ->selectRaw('id AS requerimiento_id, monto_estimado AS m')
            ->whereIn('id', $reqIds)->get());

        // «Fecha requerida» — la precisión de nombre de la Conformidad.
        $fechas = [];
        foreach ($this->tabla('requerimiento')->select('id', 'fecha_requerida')->whereIn('id', $reqIds)->get() as $fila) {
            if ($fila->fecha_requerida !== null) {
                $fechas[(int) $fila->id] = (string) $fila->fecha_requerida;
            }
        }

        // Producto y cantidad estructurados = la precotización vinculada tiene
        // ítems (nombre y cantidad son obligatorios en esa tabla).
        $conItems = [];
        $filas = $this->tabla('quotation_prequote_items as i')
            ->join('quotation_prequotes as q', 'q.id', '=', 'i.prequote_id')
            ->selectRaw('q.requerimiento_id, COUNT(i.id) AS n')
            ->whereIn('q.requerimiento_id', $reqIds)->whereNull('q.deleted_at')
            ->groupBy('q.requerimiento_id')->get();
        foreach ($filas as $fila) {
            $conItems[(int) $fila->requerimiento_id] = ((int) $fila->n) > 0;
        }

        return ['valores' => $valores, 'fechas' => $fechas, 'con_items' => $conItems];
    }

    private function tabla($tabla)
    {
        return DB::connection('tenant')->table($tabla);
    }
}
