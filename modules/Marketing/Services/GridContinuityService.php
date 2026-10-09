<?php

namespace Modules\Marketing\Services;

use Carbon\Carbon;
use Modules\Marketing\Models\Grid;
use Modules\Marketing\Models\GridItem;

/**
 * GridContinuityService — Modo C (§11.5 del Documento Funcional)
 *
 * Implementa las 5 sub-reglas de continuidad automática editorial:
 *
 *   §11.5    Regla de continuidad: si la grilla parcial está por agotarse,
 *            extender el siguiente tramo (sin esperar orden manual).
 *
 *   §11.5.1  Proactividad editorial inteligente: detectar planificación
 *            por agotarse y actuar antes que falte contenido.
 *
 *   §11.5.2  Expansión progresiva: trimestre por defecto (no año completo).
 *
 *   §11.5.3  Orden de construcción por red social: FB → IG → LinkedIn → TikTok.
 *
 *   §11.5.4  Criterio cuánto ampliar: por defecto trimestre, semestre solo
 *            si conviene, año solo si claramente viable y conveniente.
 *
 *   §11.5.5  No contradicción con configuración antigua: una grilla
 *            "solo enero" debe interpretarse como instrucción de arranque,
 *            no prohibición permanente.
 *
 * El servicio NO ejecuta la extensión por sí mismo (eso lo hace el
 * MKT03Orchestrator). Se limita a detectar, sugerir y ordenar candidatas.
 */
class GridContinuityService
{
    /** Días por defecto antes de agotarse para considerar candidata */
    const UMBRAL_DIAS_DEFAULT = 14;

    /**
     * Orden recomendado de redes (§11.5.3).
     * Se procesa primero el bloque conjunto FB+IG, luego LinkedIn, luego TikTok.
     */
    const ORDEN_REDES = [
        Grid::RED_FACEBOOK  => 1,
        Grid::RED_INSTAGRAM => 2,
        Grid::RED_LINKEDIN  => 3,
        Grid::RED_TIKTOK    => 4,
        Grid::RED_MULTIRED  => 5,
        Grid::RED_TODAS     => 6,
    ];

    /** Estados de Grid considerados "vivos" para el análisis de continuidad */
    const ESTADOS_VIVOS = [
        Grid::ESTADO_ACTIVA,
        Grid::ESTADO_VALIDADA,
        Grid::ESTADO_BORRADOR,
        Grid::ESTADO_EN_REVISION,
    ];

    /** Tipos de tramo que el servicio puede sugerir */
    const TRAMO_TRIMESTRE = 'trimestral';
    const TRAMO_SEMESTRE  = 'semestral';
    const TRAMO_ANUAL     = 'anual';

    /**
     * Detectar todas las grillas candidatas a continuidad automática.
     *
     * Retorna lista ORDENADA por §11.5.3 (FB/IG → LinkedIn → TikTok).
     * Cada elemento incluye sugerencia de tramo siguiente.
     *
     * @param  int|null  $umbralDias  Días antes de agotarse para considerar candidata
     * @return array
     */
    public function detectCandidates($umbralDias = null)
    {
        $umbral = $umbralDias !== null ? (int) $umbralDias : self::UMBRAL_DIAS_DEFAULT;

        $grids = Grid::whereIn('estado', self::ESTADOS_VIVOS)->get();

        $candidates = [];
        foreach ($grids as $grid) {
            $eval = $this->evaluateGrid($grid, $umbral);
            if ($eval['needs_extension']) {
                $candidates[] = $eval;
            }
        }

        // §11.5.3: orden de construcción por red social
        usort($candidates, function ($a, $b) {
            $orderA = isset(self::ORDEN_REDES[$a['grid']->red_social]) ? self::ORDEN_REDES[$a['grid']->red_social] : 99;
            $orderB = isset(self::ORDEN_REDES[$b['grid']->red_social]) ? self::ORDEN_REDES[$b['grid']->red_social] : 99;
            if ($orderA !== $orderB) {
                return $orderA - $orderB;
            }
            // Empate: priorizar las MÁS agotadas (menos días restantes)
            return $a['dias_restantes'] - $b['dias_restantes'];
        });

        return $candidates;
    }

    /**
     * Evaluar UNA grilla y decidir si necesita extensión.
     *
     * @param  Grid      $grid
     * @param  int|null  $umbralDias
     * @return array               ['grid', 'ultima_fecha', 'dias_restantes', 'agotada',
     *                              'needs_extension', 'tramo_sugerido', 'razon']
     */
    public function evaluateGrid(Grid $grid, $umbralDias = null)
    {
        $umbral = $umbralDias !== null ? (int) $umbralDias : self::UMBRAL_DIAS_DEFAULT;

        $ultimaFecha    = $this->getUltimaFecha($grid);
        $hoy            = Carbon::today();
        $diasRestantes  = null;
        $agotada        = false;
        $needsExtension = false;
        $razon          = null;

        if ($ultimaFecha === null) {
            // Sin items todavía. La grilla no fue poblada — no es caso de continuidad,
            // es caso de "construir desde cero" (Modo A).
            $razon = 'sin_items_aun';
        } else {
            $ultima = Carbon::parse($ultimaFecha);
            $diasRestantes = (int) $hoy->diffInDays($ultima, false);
            // diffInDays con $false signed: positivo si $ultima > $hoy, negativo si menor

            if ($diasRestantes < 0) {
                $agotada = true;
                $needsExtension = true;
                $razon = 'agotada_hace_' . abs($diasRestantes) . '_dias';
            } elseif ($diasRestantes <= $umbral) {
                $needsExtension = true;
                $razon = 'por_agotarse_en_' . $diasRestantes . '_dias';
            } else {
                $razon = 'cobertura_suficiente';
            }
        }

        return [
            'grid'            => $grid,
            'ultima_fecha'    => $ultimaFecha,
            'dias_restantes'  => $diasRestantes,
            'agotada'         => $agotada,
            'needs_extension' => $needsExtension,
            'razon'           => $razon,
            'tramo_sugerido'  => $needsExtension ? $this->suggestNextTramo($grid, $ultimaFecha) : null,
        ];
    }

    /**
     * Decidir el siguiente tramo a ampliar (§11.5.2 + §11.5.4).
     *
     * Política por defecto: trimestre (3 meses) desde el día siguiente al
     * último cubierto. Esto sigue §11.5.4 que dice que como práctica por
     * defecto se debe priorizar trimestres antes que años completos.
     *
     * Casos especiales:
     *   - Si la grilla es de tipo "anual", el tramo siguiente alinea al
     *     siguiente trimestre calendario (Q1, Q2, Q3, Q4).
     *   - Si la última fecha cubierta es más antigua que hoy (grilla
     *     agotada), el tramo arranca desde HOY y NO desde la fecha
     *     posterior agotada — para no llenar el pasado.
     *
     * @param  Grid          $grid
     * @param  string|null   $ultimaFechaStr  YYYY-MM-DD o null si sin ítems
     * @return array                          ['fecha_desde','fecha_hasta','tipo','meses']
     */
    public function suggestNextTramo(Grid $grid, $ultimaFechaStr)
    {
        $hoy = Carbon::today();

        // Punto de arranque: día siguiente a la última fecha cubierta,
        // pero nunca anterior a hoy (no llenar el pasado).
        if ($ultimaFechaStr) {
            $inicio = Carbon::parse($ultimaFechaStr)->addDay();
            if ($inicio->lessThan($hoy)) {
                $inicio = $hoy->copy();
            }
        } else {
            $inicio = $hoy->copy();
        }

        // §11.5.4 — defecto: trimestre (90 días aprox = 3 meses)
        $tipo = self::TRAMO_TRIMESTRE;
        $meses = 3;
        $fin = $inicio->copy()->addMonthsNoOverflow($meses)->subDay();

        return [
            'fecha_desde' => $inicio->format('Y-m-d'),
            'fecha_hasta' => $fin->format('Y-m-d'),
            'tipo'        => $tipo,
            'meses'       => $meses,
        ];
    }

    /**
     * Obtener la última fecha de publicación cubierta por una grilla.
     *
     * @param  Grid  $grid
     * @return string|null  YYYY-MM-DD o null si la grilla no tiene ítems
     */
    public function getUltimaFecha(Grid $grid)
    {
        $last = GridItem::where('grid_id', $grid->id)
            ->orderByDesc('fecha_publicacion')
            ->first();

        if (!$last || !$last->fecha_publicacion) {
            return null;
        }
        return $last->fecha_publicacion->format('Y-m-d');
    }

    /**
     * Verificar si una restricción antigua (ej: "solo enero") debe interpretarse
     * como prohibición permanente (§11.5.5).
     *
     * Política: NUNCA. Cualquier configuración previa de alcance se considera
     * instrucción de arranque, no veto contra la continuidad. La presencia de
     * esta función es para documentar la decisión y permitir que callers
     * la consulten explícitamente si en el futuro queremos un override.
     *
     * @param  Grid  $grid
     * @return bool       siempre false en E1 — no hay prohibiciones permanentes
     */
    public function isContinuityForbidden(Grid $grid)
    {
        return false;
    }
}
