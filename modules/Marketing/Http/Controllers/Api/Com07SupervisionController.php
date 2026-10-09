<?php

namespace Modules\Marketing\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Marketing\Models\Com07Gestion;
use Modules\Marketing\Models\Com07Oportunidad;

/**
 * Com07SupervisionController — Vista de Supervisión Comercial (COM-07, Pantalla 3).
 *
 * El consolidado de TODAS las carteras: a diferencia del Panel de Trabajo, acá
 * no se filtra por el usuario conectado. Es la vista de jefatura: quién tiene
 * qué, qué está vencido y qué quedó sin dueño.
 *
 * Las oportunidades pendientes de asignación viven acá y solo acá: no
 * aparecen en el panel de ninguna ejecutiva, pero la supervisión las ve para
 * poder repartirlas.
 */
class Com07SupervisionController extends Controller
{
    const POR_PAGINA = 20;

    /** Valor del filtro de ejecutiva para las oportunidades sin responsable. */
    const SIN_ASIGNAR = 'sin_asignar';

    /**
     * GET /marketing/com07/supervision/oportunidades
     *
     * Filtros: ejecutiva (id | 'sin_asignar'), seguimiento, origen, buscar.
     */
    public function index(Request $request)
    {
        $consulta = Com07Oportunidad::query()
            ->with([
                'contacto:id,nombres,apellidos,cargo',
                'empresa:id,razon_social,ruc',
                'responsable:id,name',
                'ultimaGestion',
            ])
            ->vigentes();

        $this->aplicarFiltros($consulta, $request);
        $this->aplicarOrden($consulta);

        $pagina = $consulta->paginate(
            (int) $request->input('por_pagina', self::POR_PAGINA)
        );

        return response()->json([
            'ok'         => true,
            'datos'      => $this->presentar($pagina->items()),
            'total'      => $pagina->total(),
            'pagina'     => $pagina->currentPage(),
            'por_pagina' => $pagina->perPage(),
            'resumen'    => $this->resumen(),
            'ejecutivas' => $this->catalogoDeEjecutivas(),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Contadores del encabezado
    |--------------------------------------------------------------------------
    | Los cuatro de la maqueta. Siempre sobre el total vigente, sin filtros:
    | son el estado del área, no del listado que se esté mirando.
    */

    private function resumen()
    {
        $hoy = now()->toDateString();

        return [
            'activas' => Com07Oportunidad::vigentes()->count(),
            'seguimientos_pendientes' => Com07Oportunidad::vigentes()
                ->whereNotNull('proxima_atencion_at')
                ->whereDate('proxima_atencion_at', '>=', $hoy)
                ->count(),
            'seguimientos_vencidos' => Com07Oportunidad::vigentes()->vencidas()->count(),
            'pendientes_asignacion' => Com07Oportunidad::vigentes()->sinAsignar()->count(),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Filtros
    |--------------------------------------------------------------------------
    */

    private function aplicarFiltros($consulta, Request $request)
    {
        $ejecutiva = $request->input('ejecutiva');
        if ($ejecutiva === self::SIN_ASIGNAR) {
            $consulta->sinAsignar();
        } elseif ($ejecutiva) {
            $consulta->deResponsable((int) $ejecutiva);
        }

        $origen = $request->input('origen');
        if ($origen && in_array($origen, Com07Oportunidad::ORIGENES, true)) {
            $consulta->where('origen', $origen);
        }

        $this->filtrarPorSeguimiento($consulta, $request->input('seguimiento'));
        $this->filtrarPorTexto($consulta, $request->input('buscar'));
    }

    /**
     * En esta vista el seguimiento tiene un valor más que en el panel:
     * "pendiente de asignación", que es como la maqueta muestra a las
     * oportunidades sin dueño.
     */
    private function filtrarPorSeguimiento($consulta, $condicion)
    {
        if (!$condicion) {
            return;
        }

        $hoy = now()->toDateString();

        if ($condicion === 'pendiente_asignacion') {
            $consulta->sinAsignar();
        } elseif ($condicion === 'vencido') {
            $consulta->whereNotNull('proxima_atencion_at')
                     ->whereDate('proxima_atencion_at', '<', $hoy);
        } elseif ($condicion === 'pendiente') {
            $consulta->whereNotNull('proxima_atencion_at')
                     ->whereDate('proxima_atencion_at', '>=', $hoy);
        } elseif ($condicion === 'sin_seguimiento') {
            $consulta->whereNull('proxima_atencion_at');
        }
    }

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
    | El mismo criterio del panel — Prioridad Comercial con los desempates de
    | la Especificación — para que jefatura y ejecutiva miren la misma lista
    | en el mismo orden.
    */

    private function aplicarOrden($consulta)
    {
        $consulta
            ->orderByRaw('prioridad_puntaje IS NULL')
            ->orderBy('prioridad_puntaje', 'desc')
            ->orderByRaw('urgencia_puntaje IS NULL')
            ->orderBy('urgencia_puntaje', 'desc')
            ->orderByRaw('proxima_atencion_at IS NULL')
            ->orderBy('proxima_atencion_at', 'asc')
            ->orderBy('potencial_puntaje', 'desc')
            ->orderByRaw('ultima_actividad_at IS NULL')
            ->orderBy('ultima_actividad_at', 'asc')
            ->orderBy('id', 'asc');
    }

    /*
    |--------------------------------------------------------------------------
    | Presentación
    |--------------------------------------------------------------------------
    */

    private function presentar(array $oportunidades)
    {
        $filas = [];

        foreach ($oportunidades as $o) {
            $contacto = $o->contacto;
            $empresa  = $o->empresa;

            $filas[] = [
                'id'                  => $o->id,
                'ejecutiva'           => $o->responsable ? $o->responsable->name : null,
                'empresa'             => $empresa ? $empresa->razon_social : '—',
                'contacto'            => $contacto ? trim($contacto->nombres . ' ' . $contacto->apellidos) : '—',
                'prioridad_puntaje'   => $o->prioridad_puntaje !== null ? (float) $o->prioridad_puntaje : null,
                'nivel_prioridad'     => $o->nivel_prioridad,
                'prioridad_label'     => $this->etiquetaNivel($o->nivel_prioridad),
                'origen'              => $o->origen,
                'origen_label'        => $this->etiquetaOrigen($o->origen),
                'rango_antiguedad'    => $o->rango_antiguedad,
                'antiguedad_label'    => $this->etiquetaAntiguedad($o->rango_antiguedad),
                'proxima_atencion_at' => $o->proxima_atencion_at ? $o->proxima_atencion_at->format('d/m/Y') : null,
                'seguimiento'         => $o->tieneResponsable() ? $o->condicion_seguimiento : 'pendiente_asignacion',
                'seguimiento_label'   => $this->etiquetaSeguimiento($o),
                'dias_atraso'         => $o->getDiasAtraso(),
                'ultima_gestion'      => $this->etiquetaUltimaGestion($o),
            ];
        }

        return $filas;
    }

    /**
     * En la maqueta, la columna Seguimiento de una oportunidad sin dueño dice
     * "Pendiente de asignación": lo urgente ahí no es la fecha, es que nadie
     * la tiene.
     */
    private function etiquetaSeguimiento(Com07Oportunidad $o)
    {
        if (!$o->tieneResponsable()) {
            return 'Pendiente de asignación';
        }

        $etiquetas = [
            'vencido'         => 'Vencido',
            'pendiente'       => 'Pendiente',
            'sin_seguimiento' => 'Sin seguimiento',
        ];

        $condicion = $o->condicion_seguimiento;

        return isset($etiquetas[$condicion]) ? $etiquetas[$condicion] : $condicion;
    }

    /** "05/08/2026 · Sin respuesta", o null si nunca se gestionó. */
    private function etiquetaUltimaGestion(Com07Oportunidad $o)
    {
        $gestion = $o->ultimaGestion;
        if (!$gestion) {
            return null;
        }

        return $gestion->created_at->format('d/m/Y') . ' · ' . $this->etiquetaResultado($gestion->resultado);
    }

    private function etiquetaResultado($resultado)
    {
        $etiquetas = [
            Com07Gestion::RESULTADO_SIN_RESPONDER        => 'Sin respuesta',
            Com07Gestion::RESULTADO_NUEVO_SEGUIMIENTO    => 'Requiere nuevo seguimiento',
            Com07Gestion::RESULTADO_EXISTE_INTERES       => 'Existe interés o necesidad actual',
            Com07Gestion::RESULTADO_NECESIDAD_FUTURA     => 'Existe una necesidad futura',
            Com07Gestion::RESULTADO_REQUIERE_CONTINUIDAD => 'Requiere continuidad comercial',
            Com07Gestion::RESULTADO_CONCLUIDA            => 'Gestión concluida',
        ];

        return isset($etiquetas[$resultado]) ? $etiquetas[$resultado] : $resultado;
    }

    /**
     * Ejecutivas para el filtro: las que tienen cartera vigente, más la opción
     * "Sin asignar" que agrega el frontend.
     */
    private function catalogoDeEjecutivas()
    {
        $filas = Com07Oportunidad::query()
            ->vigentes()
            ->whereNotNull('responsable_comercial_id')
            ->with('responsable:id,name')
            ->get()
            ->pluck('responsable')
            ->filter()
            ->unique('id')
            ->values();

        $catalogo = [];
        foreach ($filas as $usuario) {
            $catalogo[] = ['id' => $usuario->id, 'nombre' => $usuario->name];
        }

        return $catalogo;
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
}
