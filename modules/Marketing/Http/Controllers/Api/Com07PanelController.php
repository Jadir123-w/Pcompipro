<?php

namespace Modules\Marketing\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Marketing\Models\Com07Oportunidad;

/**
 * Com07PanelController — Panel de Trabajo de la ejecutiva (COM-07).
 *
 * Devuelve las oportunidades de la cartera del usuario conectado, con los
 * filtros y el orden definidos en la maqueta aprobada.
 *
 * Las oportunidades sin responsable NO aparecen acá: existen, pero quedan
 * pendientes de asignación y se ven desde la Vista de Supervisión. Meterlas en
 * el panel de alguien sería asignárselas sin que nadie lo decida.
 */
class Com07PanelController extends Controller
{
    /** Cuántas filas por página muestra el panel. */
    const POR_PAGINA = 20;

    /**
     * GET /marketing/com07/oportunidades
     *
     * Filtros: origen, antiguedad, seguimiento, buscar.
     */
    public function index(Request $request)
    {
        $consulta = Com07Oportunidad::query()
            ->with([
                'contacto:id,nombres,apellidos,cargo,celular,telefono,email',
                'empresa:id,razon_social,ruc',
            ])
            ->vigentes()
            ->deResponsable(auth()->id());

        $this->aplicarFiltros($consulta, $request);
        $this->aplicarOrden($consulta);

        $pagina = $consulta->paginate(
            (int) $request->input('por_pagina', self::POR_PAGINA)
        );

        return response()->json([
            'ok'          => true,
            'datos'       => $this->presentar($pagina->items()),
            'total'       => $pagina->total(),
            'pagina'      => $pagina->currentPage(),
            'por_pagina'  => $pagina->perPage(),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Filtros
    |--------------------------------------------------------------------------
    */

    private function aplicarFiltros($consulta, Request $request)
    {
        $origen = $request->input('origen');
        if ($origen && in_array($origen, Com07Oportunidad::ORIGENES, true)) {
            $consulta->where('origen', $origen);
        }

        $this->filtrarPorAntiguedad($consulta, $request->input('antiguedad'));
        $this->filtrarPorSeguimiento($consulta, $request->input('seguimiento'));
        $this->filtrarPorTexto($consulta, $request->input('buscar'));
    }

    /**
     * El rango de antigüedad no está guardado: se deriva de la última actividad.
     * Para poder filtrar en la consulta se traducen los rangos a las fechas
     * límite equivalentes, con los mismos cortes que usa el modelo.
     */
    private function filtrarPorAntiguedad($consulta, $rango)
    {
        if (!$rango) {
            return;
        }

        $cuatroMeses = now()->startOfDay()->subMonths(4)->toDateString();
        $seisMeses   = now()->startOfDay()->subMonths(6)->toDateString();

        if ($rango === Com07Oportunidad::RANGO_RECIENTE) {
            $consulta->whereNotNull('ultima_actividad_at')
                     ->where('ultima_actividad_at', '>', $cuatroMeses);
        } elseif ($rango === Com07Oportunidad::RANGO_MEDIO) {
            $consulta->where('ultima_actividad_at', '<=', $cuatroMeses)
                     ->where('ultima_actividad_at', '>', $seisMeses);
        } elseif ($rango === Com07Oportunidad::RANGO_ANTIGUO) {
            $consulta->where('ultima_actividad_at', '<=', $seisMeses);
        }
    }

    /** La condición del seguimiento también se deriva: de la próxima atención. */
    private function filtrarPorSeguimiento($consulta, $condicion)
    {
        if (!$condicion) {
            return;
        }

        $hoy = now()->toDateString();

        if ($condicion === 'vencido') {
            $consulta->whereNotNull('proxima_atencion_at')
                     ->whereDate('proxima_atencion_at', '<', $hoy);
        } elseif ($condicion === 'pendiente') {
            $consulta->whereNotNull('proxima_atencion_at')
                     ->whereDate('proxima_atencion_at', '>=', $hoy);
        } elseif ($condicion === 'sin_seguimiento') {
            $consulta->whereNull('proxima_atencion_at');
        }
    }

    /** Busca por empresa o por contacto, que es como la ejecutiva los recuerda. */
    private function filtrarPorTexto($consulta, $texto)
    {
        $texto = trim((string) $texto);
        if ($texto === '') {
            return;
        }

        $patron = '%' . $texto . '%';

        $consulta->where(function ($q) use ($patron) {
            $q->whereHas('empresa', function ($e) use ($patron) {
                $e->where('razon_social', 'like', $patron)
                  ->orWhere('ruc', 'like', $patron);
            })->orWhereHas('contacto', function ($c) use ($patron) {
                $c->where('nombres', 'like', $patron)
                  ->orWhere('apellidos', 'like', $patron);
            });
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Orden
    |--------------------------------------------------------------------------
    */

    /**
     * Orden acordado en la Conformidad: la Prioridad Comercial es el criterio
     * principal, con los desempates de la Especificación (6.2.3) en su orden:
     * mayor Urgencia, fecha de atención más próxima, mayor Potencial y mayor
     * tiempo pendiente de atención.
     *
     * Las columnas de la maqueta (antigüedad, próxima atención, monto) siguen
     * visibles como información complementaria; lo vencido se resalta y se
     * filtra, pero ya no reordena: el contrato dice Prioridad.
     */
    private function aplicarOrden($consulta)
    {
        $consulta
            ->orderByRaw('prioridad_puntaje IS NULL')            // sin calcular, al final
            ->orderBy('prioridad_puntaje', 'desc')
            ->orderByRaw('urgencia_puntaje IS NULL')             // 1º mayor Urgencia (No aplica último)
            ->orderBy('urgencia_puntaje', 'desc')
            ->orderByRaw('proxima_atencion_at IS NULL')          // 2º fecha de atención más próxima
            ->orderBy('proxima_atencion_at', 'asc')
            ->orderBy('potencial_puntaje', 'desc')               // 3º mayor Potencial
            ->orderByRaw('ultima_actividad_at IS NULL')          // 4º mayor tiempo pendiente
            ->orderBy('ultima_actividad_at', 'asc')
            ->orderBy('id', 'asc');                              // estable
    }

    /*
    |--------------------------------------------------------------------------
    | Presentación
    |--------------------------------------------------------------------------
    */

    /**
     * Arma exactamente las columnas de la maqueta.
     *
     * Las etiquetas se resuelven acá y no en la vista para que el panel, la
     * supervisión y cualquier reporte posterior digan lo mismo.
     */
    private function presentar(array $oportunidades)
    {
        $filas = [];

        foreach ($oportunidades as $o) {
            $contacto = $o->contacto;
            $empresa  = $o->empresa;

            $filas[] = [
                'id'                  => $o->id,
                'empresa'             => $empresa ? $empresa->razon_social : '—',
                'contacto'            => $contacto ? trim($contacto->nombres . ' ' . $contacto->apellidos) : '—',
                'cargo'               => $contacto ? $contacto->cargo : null,
                'prioridad_puntaje'   => $o->prioridad_puntaje !== null ? (float) $o->prioridad_puntaje : null,
                'nivel_prioridad'     => $o->nivel_prioridad,
                'prioridad_label'     => $this->etiquetaNivel($o->nivel_prioridad),
                'origen'              => $o->origen,
                'origen_label'        => $this->etiquetaOrigen($o->origen),
                'ultima_actividad_at' => $o->ultima_actividad_at ? $o->ultima_actividad_at->format('d/m/Y') : null,
                'antiguedad_dias'     => $o->antiguedad_dias,
                'rango_antiguedad'    => $o->rango_antiguedad,
                'antiguedad_label'    => $this->etiquetaAntiguedad($o->rango_antiguedad),
                'monto_historico'     => $o->monto_historico,
                'proxima_atencion_at' => $o->proxima_atencion_at ? $o->proxima_atencion_at->format('d/m/Y') : null,
                'seguimiento'         => $o->condicion_seguimiento,
                'seguimiento_label'   => $this->etiquetaSeguimiento($o->condicion_seguimiento),
                'dias_atraso'         => $o->getDiasAtraso(),
                'requerimiento_id'    => $o->requerimiento_id,
            ];
        }

        return $filas;
    }

    private function etiquetaOrigen($origen)
    {
        $etiquetas = [
            Com07Oportunidad::ORIGEN_COTIZACION_CAIDA => 'Cotización caída',
            Com07Oportunidad::ORIGEN_NO_COTIZADO      => 'No cotizado',
            Com07Oportunidad::ORIGEN_SIN_MOVIMIENTO   => 'Sin movimiento',
        ];

        return isset($etiquetas[$origen]) ? $etiquetas[$origen] : $origen;
    }

    private function etiquetaNivel($nivel)
    {
        $etiquetas = [
            Com07Oportunidad::NIVEL_MUY_ALTO  => 'Muy Alta',
            Com07Oportunidad::NIVEL_ALTO      => 'Alta',
            Com07Oportunidad::NIVEL_MEDIO     => 'Media',
            Com07Oportunidad::NIVEL_BAJO      => 'Baja',
            Com07Oportunidad::NIVEL_NO_APLICA => 'No aplica',
        ];

        return isset($etiquetas[$nivel]) ? $etiquetas[$nivel] : '—';
    }

    private function etiquetaAntiguedad($rango)
    {
        $etiquetas = [
            Com07Oportunidad::RANGO_RECIENTE => '8 semanas a 4 meses',
            Com07Oportunidad::RANGO_MEDIO    => '4 a 6 meses',
            Com07Oportunidad::RANGO_ANTIGUO  => 'Más de 6 meses',
        ];

        return isset($etiquetas[$rango]) ? $etiquetas[$rango] : '—';
    }

    private function etiquetaSeguimiento($condicion)
    {
        $etiquetas = [
            'vencido'         => 'Vencido',
            'pendiente'       => 'Pendiente',
            'sin_seguimiento' => 'Sin seguimiento',
        ];

        return isset($etiquetas[$condicion]) ? $etiquetas[$condicion] : $condicion;
    }
}
