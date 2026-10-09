<?php

namespace Modules\Marketing\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Marketing\Models\Com07Gestion;
use Modules\Marketing\Models\Com07Oportunidad;

/**
 * Com07GestionController — Registro de la gestión (COM-07, Pantalla 2).
 *
 * show()  arma el modal: contexto de la oportunidad, antecedentes comerciales
 *         de la cuenta y el catálogo de resultados.
 * store() guarda la gestión y aplica sus efectos sobre la oportunidad.
 *
 * Solo la responsable de la oportunidad puede verla y gestionarla. Para
 * cualquier otro usuario la oportunidad "no existe" (404): responder 403
 * confirmaría que existe, y la cartera de cada ejecutiva no es asunto de las
 * demás.
 */
class Com07GestionController extends Controller
{
    /** Cuántos antecedentes de la cuenta muestra el modal. */
    const MAX_ANTECEDENTES = 10;

    /*
    |--------------------------------------------------------------------------
    | GET /marketing/com07/oportunidades/{id}
    |--------------------------------------------------------------------------
    */

    public function show($id)
    {
        $oportunidad = $this->oportunidadDelUsuario($id);

        $contacto = $oportunidad->contacto;
        $empresa  = $oportunidad->empresa;

        return response()->json([
            'ok'       => true,
            'contexto' => [
                'id'               => $oportunidad->id,
                'empresa'          => $empresa ? $empresa->razon_social : '—',
                'contacto'         => $contacto ? trim($contacto->nombres . ' ' . $contacto->apellidos) : '—',
                'cargo'            => $contacto ? $contacto->cargo : null,
                'responsable'      => $oportunidad->responsable ? $oportunidad->responsable->name : null,
                'origen_label'     => $this->etiquetaOrigen($oportunidad->origen),
                'ultima_actividad' => $oportunidad->ultima_actividad_at ? $oportunidad->ultima_actividad_at->format('d/m/Y') : '—',
                'antiguedad_label' => $this->etiquetaAntiguedad($oportunidad->rango_antiguedad),
            ],
            'prioridad'    => $this->detalleDePrioridad($oportunidad),
            'antecedentes' => $this->antecedentesDeLaCuenta($oportunidad),
            'gestiones'    => $this->historialDeGestiones($oportunidad),
            'seguimiento'  => $this->seguimientoVigente($oportunidad),
            'enlaces'      => $this->enlacesRelacionados($oportunidad),
            'resultados'   => $this->catalogoDeResultados(),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | POST /marketing/com07/oportunidades/{id}/cancelar-seguimiento
    |--------------------------------------------------------------------------
    */

    /**
     * Cancela el seguimiento vigente conservando su motivo.
     *
     * Cancelar un seguimiento NO cierra la oportunidad ni borra su historial:
     * significa que ese compromiso concreto dejó de aplicar, no que el cliente
     * deje de ser recuperable. La oportunidad queda sin próxima atención,
     * lista para que la ejecutiva decida el siguiente paso.
     */
    public function cancelarSeguimiento(Request $request, $id)
    {
        $oportunidad = $this->oportunidadDelUsuario($id);

        $datos = $request->validate([
            'motivo' => 'required|string|max:255',
        ], [
            'motivo.required' => 'Indica el motivo por el que el seguimiento deja de aplicar.',
        ]);

        $gestion = $oportunidad->gestiones()->pendientes()->first();
        if (!$gestion) {
            return response()->json([
                'ok'      => false,
                'mensaje' => 'La oportunidad no tiene un seguimiento vigente que cancelar.',
            ], 422);
        }

        DB::connection('tenant')->transaction(function () use ($oportunidad, $gestion, $datos) {
            $gestion->cancelarSeguimiento($datos['motivo']);

            // La oportunidad sigue vigente; solo deja de tener fecha comprometida.
            $oportunidad->proxima_atencion_at = null;
            $oportunidad->save();

            $this->registrarInteraccion(
                $oportunidad,
                'Seguimiento cancelado: ' . $datos['motivo'],
                null
            );
        });

        return response()->json([
            'ok'     => true,
            'estado' => $oportunidad->estado,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | POST /marketing/com07/oportunidades/{id}/gestiones
    |--------------------------------------------------------------------------
    */

    public function store(Request $request, $id)
    {
        $oportunidad = $this->oportunidadDelUsuario($id);

        $datos = $request->validate([
            'resultado'            => 'required|in:' . implode(',', Com07Gestion::RESULTADOS),
            'proxima_accion'       => 'nullable|string|max:500',
            'observacion'          => 'nullable|string|max:2000',
            'requiere_seguimiento' => 'required|boolean',
            'seguimiento_at'       => 'required_if:requiere_seguimiento,1|nullable|date_format:Y-m-d|after_or_equal:today',
        ], [
            'seguimiento_at.required_if'     => 'Indica la fecha del seguimiento.',
            'seguimiento_at.after_or_equal'  => 'La fecha del seguimiento no puede estar en el pasado.',
        ]);

        $conSeguimiento = (bool) $datos['requiere_seguimiento'];
        $fechaSeguimiento = $conSeguimiento ? $datos['seguimiento_at'] : null;

        $gestion = DB::connection('tenant')->transaction(function () use ($oportunidad, $datos, $conSeguimiento, $fechaSeguimiento) {

            // La gestión anterior con seguimiento pendiente queda atendida:
            // esta atención ES la respuesta a aquel "¿y ahora qué?".
            $anterior = $oportunidad->gestiones()->pendientes()->first();
            if ($anterior) {
                $anterior->marcarAtendido();
            }

            $gestion = Com07Gestion::create([
                'oportunidad_id'          => $oportunidad->id,
                'user_id'                 => auth()->id(),
                'resultado'               => $datos['resultado'],
                'proxima_accion'          => isset($datos['proxima_accion']) ? $datos['proxima_accion'] : null,
                'observacion'             => isset($datos['observacion']) ? $datos['observacion'] : null,
                'seguimiento_at'          => $fechaSeguimiento,
                // Si había una fecha comprometida y se fija otra, la anterior
                // se conserva: eso es la reprogramación del punto 10.
                'seguimiento_at_anterior' => ($fechaSeguimiento && $oportunidad->proxima_atencion_at)
                    ? $oportunidad->proxima_atencion_at->toDateString()
                    : null,
                'seguimiento_estado'      => $conSeguimiento ? Com07Gestion::SEG_PENDIENTE : null,
            ]);

            $this->registrarInteraccionDelContacto($oportunidad, $gestion);
            $this->aplicarEfectos($oportunidad, $gestion, $conSeguimiento, $fechaSeguimiento);

            return $gestion;
        });

        return response()->json([
            'ok'      => true,
            'gestion' => ['id' => $gestion->id],
            'estado'  => $oportunidad->estado,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Efectos de la gestión sobre la oportunidad
    |--------------------------------------------------------------------------
    */

    private function aplicarEfectos(Com07Oportunidad $oportunidad, Com07Gestion $gestion, $conSeguimiento, $fechaSeguimiento)
    {
        // "La fecha del seguimiento pasa a ser la próxima fecha de atención."
        $oportunidad->proxima_atencion_at = $fechaSeguimiento;

        // Un resultado de cierre sin seguimiento programado concluye la
        // oportunidad; con seguimiento, la gestión continúa.
        $cierra = in_array($gestion->resultado, Com07Gestion::RESULTADOS_DE_CIERRE, true)
            && !$conSeguimiento;

        $oportunidad->estado = $cierra
            ? Com07Oportunidad::ESTADO_CONCLUIDA
            : Com07Oportunidad::ESTADO_EN_GESTION;

        // Registrar una gestión es actividad comercial válida (punto 3.1),
        // así que la antigüedad del panel parte de hoy.
        $oportunidad->ultima_actividad_at = now()->toDateString();

        $oportunidad->save();
    }

    /**
     * La gestión también queda en el historial del contacto, con su referencia
     * de origen — es el criterio confirmado con Compipro: las interacciones del
     * COM-07 se centralizan ahí, sin duplicar estructuras.
     */
    private function registrarInteraccionDelContacto(Com07Oportunidad $oportunidad, Com07Gestion $gestion)
    {
        $resumen = 'Gestión de recuperación: ' . $this->etiquetaResultado($gestion->resultado) . '.';
        if ($gestion->observacion) {
            $resumen .= ' ' . $gestion->observacion;
        }
        if ($gestion->proxima_accion) {
            $resumen .= ' Próxima acción: ' . $gestion->proxima_accion . '.';
        }

        $this->registrarInteraccion($oportunidad, $resumen, $gestion->seguimiento_at);
    }

    /** Escribe una interacción del COM-07 en el historial del contacto. */
    private function registrarInteraccion(Com07Oportunidad $oportunidad, $resumen, $seguimientoAt)
    {
        DB::connection('tenant')->table('contacto_interaccion')->insert([
            'contacto_id'          => $oportunidad->contacto_id,
            'canal'                => 'COM-07',
            'resumen'              => $resumen,
            'requiere_seguimiento' => $seguimientoAt ? 1 : 0,
            'proximo_seguimiento'  => $seguimientoAt ? $seguimientoAt->format('Y-m-d 00:00:00') : null,
            'user_id'              => auth()->id(),
            'created_at'           => now(),
            'updated_at'           => now(),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Detalle de la Prioridad
    |--------------------------------------------------------------------------
    */

    /**
     * El sustento de la Prioridad, factor por factor — lo comprometido en la
     * Conformidad: "el detalle de Potencial y Urgencia que sustenta cada
     * Prioridad podrá consultarse en el detalle de la oportunidad".
     *
     * @return array|null  null si el proceso diario aún no la calculó
     */
    private function detalleDePrioridad(Com07Oportunidad $oportunidad)
    {
        if ($oportunidad->prioridad_puntaje === null) {
            return null;
        }

        $sustento = $oportunidad->prioridad_sustento
            ? json_decode($oportunidad->prioridad_sustento, true)
            : null;

        return [
            'puntaje'           => (float) $oportunidad->prioridad_puntaje,
            'nivel'             => $oportunidad->nivel_prioridad,
            'nivel_label'       => $this->etiquetaNivel($oportunidad->nivel_prioridad),
            'potencial_puntaje' => $oportunidad->potencial_puntaje !== null ? (int) $oportunidad->potencial_puntaje : null,
            'urgencia_puntaje'  => $oportunidad->urgencia_puntaje !== null ? (int) $oportunidad->urgencia_puntaje : null,
            'urgencia_label'    => $this->etiquetaNivel($oportunidad->nivel_urgencia),
            'factores'          => [
                'potencial' => $sustento && isset($sustento['potencial']) ? $sustento['potencial'] : [],
                'urgencia'  => $sustento && isset($sustento['urgencia']) ? $sustento['urgencia'] : null,
            ],
        ];
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

    /*
    |--------------------------------------------------------------------------
    | Historial de la gestión
    |--------------------------------------------------------------------------
    */

    /**
     * Gestiones previas de esta oportunidad, de la más reciente a la más
     * antigua: qué se hizo, quién y qué pasó con el seguimiento.
     *
     * Es lo que la ejecutiva necesita para retomar una cuenta sin repetir lo
     * ya intentado — y lo que permite reconstruir la continuidad comercial.
     *
     * @return array
     */
    private function historialDeGestiones(Com07Oportunidad $oportunidad)
    {
        $filas = [];

        foreach ($oportunidad->gestiones()->with('usuario:id,name')->get() as $gestion) {
            $filas[] = [
                'fecha'          => $gestion->created_at->format('d/m/Y'),
                'usuario'        => $gestion->usuario ? $gestion->usuario->name : '—',
                'resultado'      => $this->etiquetaResultado($gestion->resultado),
                'proxima_accion' => $gestion->proxima_accion,
                'observacion'    => $gestion->observacion,
                'seguimiento_at' => $gestion->seguimiento_at ? $gestion->seguimiento_at->format('d/m/Y') : null,
                'seguimiento_at_anterior' => $gestion->seguimiento_at_anterior
                    ? $gestion->seguimiento_at_anterior->format('d/m/Y')
                    : null,
                'seguimiento'       => $gestion->condicion_seguimiento,
                'seguimiento_label' => $this->etiquetaCondicionSeguimiento($gestion->condicion_seguimiento),
                'motivo'            => $gestion->seguimiento_motivo,
            ];
        }

        return $filas;
    }

    /**
     * Datos del seguimiento vigente, si lo hay: habilita el botón de cancelar
     * y muestra a qué fecha está comprometida la ejecutiva.
     *
     * @return array|null
     */
    private function seguimientoVigente(Com07Oportunidad $oportunidad)
    {
        $gestion = $oportunidad->gestiones()->pendientes()->first();
        if (!$gestion || empty($gestion->seguimiento_at)) {
            return null;
        }

        return [
            'gestion_id' => $gestion->id,
            'fecha'      => $gestion->seguimiento_at->format('d/m/Y'),
            'condicion'  => $gestion->condicion_seguimiento,
            'vencido'    => $gestion->condicion_seguimiento === 'vencido',
        ];
    }

    /**
     * Enlaces a los registros del ERP relacionados con la oportunidad.
     *
     * Se apunta a los módulos originales, que siguen siendo la única fuente
     * de la información: desde el COM-07 se consulta, no se duplica.
     *
     * @return array
     */
    private function enlacesRelacionados(Com07Oportunidad $oportunidad)
    {
        return [
            'contacto'      => $oportunidad->contacto_id ? '/comercial/contactos/listado' : null,
            'empresa'       => $oportunidad->empresa_id ? '/comercial/empresas/listado' : null,
            'requerimiento' => $oportunidad->requerimiento_id ? '/comercial/requerimientos' : null,
        ];
    }

    private function etiquetaCondicionSeguimiento($condicion)
    {
        $etiquetas = [
            'pendiente' => 'Pendiente',
            'vencido'   => 'Vencido',
            'atendido'  => 'Atendido',
            'cancelado' => 'Cancelado',
        ];

        return isset($etiquetas[$condicion]) ? $etiquetas[$condicion] : null;
    }

    /*
    |--------------------------------------------------------------------------
    | Antecedentes comerciales
    |--------------------------------------------------------------------------
    */

    /**
     * El historial de la CUENTA (empresa), no solo del contacto: la mayoría de
     * los Requerimientos históricos no tiene contacto asignado, y lo que la
     * ejecutiva necesita ver antes de llamar es qué se le vendió y qué se le
     * intentó vender a esa empresa.
     *
     * @return array
     */
    private function antecedentesDeLaCuenta(Com07Oportunidad $oportunidad)
    {
        if (empty($oportunidad->empresa_id)) {
            return [];
        }

        $requerimientos = DB::connection('tenant')->table('requerimiento as r')
            ->leftJoin(
                DB::raw('(SELECT requerimiento_id, MAX(monto) AS monto FROM cotizaciones_requerimiento GROUP BY requerimiento_id) as cot'),
                'cot.requerimiento_id', '=', 'r.id'
            )
            ->selectRaw('r.id, r.titulo, r.etapa, r.escalado_a_proyecto, r.created_at,
                         COALESCE(cot.monto, r.monto_estimado) AS monto')
            ->where('r.empresa_id', $oportunidad->empresa_id)
            ->whereNull('r.deleted_at')
            ->orderByDesc('r.created_at')
            ->limit(self::MAX_ANTECEDENTES)
            ->get();

        $filas = [];
        foreach ($requerimientos as $r) {
            $filas[] = [
                'fecha'       => date('d/m/Y', strtotime($r->created_at)),
                'antecedente' => 'REQ-' . str_pad($r->id, 4, '0', STR_PAD_LEFT) . ($r->titulo ? ' · ' . $r->titulo : ''),
                'monto'       => $r->monto,
                'situacion'   => $r->escalado_a_proyecto ? 'Convertido a proyecto' : ($r->etapa ?: '—'),
            ];
        }

        return $filas;
    }

    /*
    |--------------------------------------------------------------------------
    | Apoyo
    |--------------------------------------------------------------------------
    */

    /** @return Com07Oportunidad */
    private function oportunidadDelUsuario($id)
    {
        $oportunidad = Com07Oportunidad::with(['contacto', 'empresa', 'responsable'])
            ->vigentes()
            ->deResponsable(auth()->id())
            ->find($id);

        if (!$oportunidad) {
            abort(404, 'Oportunidad no encontrada');
        }

        return $oportunidad;
    }

    private function catalogoDeResultados()
    {
        $catalogo = [];
        foreach (Com07Gestion::RESULTADOS as $resultado) {
            $catalogo[] = [
                'value' => $resultado,
                'label' => $this->etiquetaResultado($resultado),
            ];
        }

        return $catalogo;
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

    private function etiquetaOrigen($origen)
    {
        $etiquetas = [
            Com07Oportunidad::ORIGEN_COTIZACION_CAIDA => 'Cotización caída',
            Com07Oportunidad::ORIGEN_NO_COTIZADO      => 'No cotizado',
            Com07Oportunidad::ORIGEN_SIN_MOVIMIENTO   => 'Sin movimiento',
        ];

        return isset($etiquetas[$origen]) ? $etiquetas[$origen] : $origen;
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
