<?php

namespace Modules\Marketing\Services;

use Illuminate\Support\Facades\DB;
use Modules\Marketing\Models\Com07Oportunidad;

/**
 * Com07DetectorService — Proceso automático de detección de oportunidades.
 *
 * Es el corazón del COM-07: una vez al día recorre el ERP buscando clientes que
 * merecen ser recuperados y crea una oportunidad por cada uno. Nadie las carga
 * a mano.
 *
 * ── Los tres disparadores (Especificación Puntual, punto 2) ──
 *   1. Cotización caída  — el Requerimiento quedó en esa etapa, o su cotización
 *                          fue marcada como caída.
 *   2. No cotizado       — se decidió no cotizar el Requerimiento.
 *   3. Sin movimiento    — el contacto lleva 8 semanas o más sin actividad
 *                          comercial registrada.
 *
 * Un contacto genera UNA sola oportunidad por corrida. Si califica por varios
 * disparadores gana el más específico: una cotización caída dice mucho más
 * sobre qué hacer que un silencio de dos meses.
 *
 * ── Por qué no se repite ──
 * Correrlo dos veces el mismo día no puede duplicar nada, y concluir una
 * oportunidad no puede hacer que reaparezca al día siguiente:
 *
 *   - Un contacto con una oportunidad vigente (pendiente o en gestión) no
 *     vuelve a entrar por ningún disparador.
 *   - Cada Requerimiento genera una sola oportunidad en toda su vida: si ya
 *     hubo una por esa cotización caída, aunque se haya concluido, no se
 *     vuelve a crear.
 *   - Por inactividad solo reaparece si la relación se movió y volvió a
 *     enfriarse: se compara la actividad actual contra la que tenía la
 *     oportunidad concluida.
 *
 * ── Rendimiento ──
 * Todo trabaja en lotes: los contactos se procesan de a `TAMANO_LOTE` y cada
 * lote cuesta un número fijo de consultas, sin importar cuántos contactos
 * tenga el tenant.
 */
class Com07DetectorService
{
    /** Contactos por lote. Cada lote son ~12 consultas agregadas. */
    const TAMANO_LOTE = 500;

    /** Etapas del Requerimiento que disparan una oportunidad. */
    const ETAPA_COTIZACION_CAIDA = 'Cotización caída';
    const ETAPA_NO_COTIZADO      = 'No cotizado';

    /** Estados de la cotización que disparan una oportunidad. */
    const COTIZACION_CAIDA       = 'Caida';
    const COTIZACION_NO_COTIZADO = 'No cotizado';

    /** @var Com07ActividadResolver */
    private $actividad;

    /** @var Com07ResponsableResolver */
    private $responsable;

    public function __construct(
        Com07ActividadResolver $actividad = null,
        Com07ResponsableResolver $responsable = null
    ) {
        $this->actividad   = $actividad ?: new Com07ActividadResolver();
        $this->responsable = $responsable ?: new Com07ResponsableResolver();
    }

    /**
     * Ejecuta una corrida completa de detección.
     *
     * @param  bool  $simulacion  Si es true, calcula todo pero no escribe nada.
     * @return array  Resumen: creadas por origen, omitidas y total evaluado.
     */
    public function detectar($simulacion = false)
    {
        $resumen = [
            'evaluados'        => 0,
            'creadas'          => 0,
            'por_origen'       => [
                Com07Oportunidad::ORIGEN_COTIZACION_CAIDA => 0,
                Com07Oportunidad::ORIGEN_NO_COTIZADO      => 0,
                Com07Oportunidad::ORIGEN_SIN_MOVIMIENTO   => 0,
            ],
            'omitidas_vigente' => 0,
            'omitidas_repeticion' => 0,
            'sin_responsable'  => 0,
        ];

        $candidatos = $this->reunirCandidatos($resumen);
        if (empty($candidatos)) {
            return $resumen;
        }

        foreach (array_chunk($candidatos, self::TAMANO_LOTE, true) as $lote) {
            $this->procesarLote($lote, $simulacion, $resumen);
        }

        return $resumen;
    }

    /*
    |--------------------------------------------------------------------------
    | Reunión de candidatos
    |--------------------------------------------------------------------------
    */

    /**
     * Junta los candidatos de los tres disparadores.
     *
     * El orden importa: se agregan del más específico al más genérico y el
     * primero en llegar se queda con el contacto.
     *
     * @return array  [contacto_id => ['origen' => ..., 'requerimiento_id' => ...]]
     */
    private function reunirCandidatos(array &$resumen)
    {
        $candidatos = [];

        $this->agregar($candidatos, $this->porCotizacionCaida(), Com07Oportunidad::ORIGEN_COTIZACION_CAIDA);
        $this->agregar($candidatos, $this->porNoCotizado(), Com07Oportunidad::ORIGEN_NO_COTIZADO);
        $this->agregar($candidatos, $this->porInactividad(), Com07Oportunidad::ORIGEN_SIN_MOVIMIENTO);

        $resumen['evaluados'] = count($candidatos);

        return $candidatos;
    }

    /** @param array $nuevos [contacto_id => requerimiento_id|null] */
    private function agregar(array &$candidatos, array $nuevos, $origen)
    {
        foreach ($nuevos as $contactoId => $requerimientoId) {
            if (isset($candidatos[$contactoId])) {
                continue;   // ya lo tomó un disparador más específico
            }
            $candidatos[$contactoId] = [
                'origen'           => $origen,
                'requerimiento_id' => $requerimientoId,
            ];
        }
    }

    /**
     * Requerimientos cuya cotización cayó.
     *
     * Se mira tanto la etapa del Requerimiento como el estado de la cotización
     * subida, porque el flujo permite marcar la caída en cualquiera de los dos
     * lugares.
     *
     * @return array  [contacto_id => requerimiento_id]
     */
    private function porCotizacionCaida()
    {
        return $this->requerimientosDisparadores(
            self::ETAPA_COTIZACION_CAIDA,
            self::COTIZACION_CAIDA
        );
    }

    /** @return array [contacto_id => requerimiento_id] */
    private function porNoCotizado()
    {
        return $this->requerimientosDisparadores(
            self::ETAPA_NO_COTIZADO,
            self::COTIZACION_NO_COTIZADO
        );
    }

    /**
     * Requerimientos que califican por su etapa o por el estado de su cotización.
     *
     * Se excluyen los ya ganados —aprobados o escalados a proyecto— porque una
     * venta cerrada no es una oportunidad de recuperación.
     *
     * @return array  [contacto_id => requerimiento_id]  el más reciente por contacto
     */
    private function requerimientosDisparadores($etapa, $estadoCotizacion)
    {
        $filas = DB::connection('tenant')->table('requerimiento as r')
            ->select('r.id', 'r.contacto_id', 'r.empresa_id')
            ->whereNull('r.deleted_at')
            ->where('r.cotizacion_aprobada', 0)
            ->where('r.escalado_a_proyecto', 0)
            ->where(function ($q) use ($etapa, $estadoCotizacion) {
                $q->where('r.etapa', $etapa)
                  ->orWhereExists(function ($sub) use ($estadoCotizacion) {
                      $sub->select(DB::raw(1))
                          ->from('cotizaciones_requerimiento as cr')
                          ->whereRaw('cr.requerimiento_id = r.id')
                          ->where('cr.estado', $estadoCotizacion);
                  });
            })
            ->orderBy('r.id')
            ->get();

        // Buena parte de los Requerimientos históricos quedó sin contacto
        // asignado, y son justamente los que COM-07 debe recuperar. Para esos
        // se deduce el contacto desde la empresa.
        $huerfanos = [];
        foreach ($filas as $fila) {
            if (empty($fila->contacto_id) && !empty($fila->empresa_id)) {
                $huerfanos[] = (int) $fila->empresa_id;
            }
        }
        $porEmpresa = $huerfanos ? $this->contactoDeReferenciaPorEmpresa($huerfanos) : [];

        // Un contacto puede tener varios Requerimientos caídos; se toma el más
        // reciente, que es el que la ejecutiva va a querer retomar.
        $mapa = [];
        foreach ($filas as $fila) {
            $contactoId = (int) $fila->contacto_id;

            if (!$contactoId) {
                $empresaId  = (int) $fila->empresa_id;
                $contactoId = isset($porEmpresa[$empresaId]) ? $porEmpresa[$empresaId] : 0;
            }

            if ($contactoId) {
                $mapa[$contactoId] = (int) $fila->id;
            }
        }

        return $mapa;
    }

    /**
     * Contacto con el que retomar a una empresa cuyo Requerimiento no lo indica.
     *
     * Se elige el contacto con actividad comercial más reciente: es el
     * interlocutor vigente hoy, que es a quien conviene llamar, más allá de
     * quién figurara en aquel momento. Si ninguno registra actividad se toma el
     * más antiguo de la empresa, para que la elección sea siempre la misma
     * entre corridas.
     *
     * Las empresas sin ningún contacto activo quedan fuera: no hay a quién
     * contactar.
     *
     * @return array  [empresa_id => contacto_id]
     */
    private function contactoDeReferenciaPorEmpresa(array $empresaIds)
    {
        $contactos = DB::connection('tenant')->table('contacto')
            ->select('id', 'empresa_id')
            ->whereIn('empresa_id', array_unique($empresaIds))
            ->where('estado', 1)
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->get();

        if ($contactos->isEmpty()) {
            return [];
        }

        $ids = [];
        foreach ($contactos as $contacto) {
            $ids[] = (int) $contacto->id;
        }
        $actividades = $this->actividad->ultimaActividadPara($ids);

        $elegido = [];
        foreach ($contactos as $contacto) {
            $empresaId  = (int) $contacto->empresa_id;
            $contactoId = (int) $contacto->id;
            $fecha      = isset($actividades[$contactoId]) ? $actividades[$contactoId] : null;

            if (!isset($elegido[$empresaId])) {
                $elegido[$empresaId] = ['id' => $contactoId, 'fecha' => $fecha];
                continue;
            }

            $actual = $elegido[$empresaId]['fecha'];
            if ($fecha !== null && ($actual === null || strcmp($fecha, $actual) > 0)) {
                $elegido[$empresaId] = ['id' => $contactoId, 'fecha' => $fecha];
            }
        }

        $mapa = [];
        foreach ($elegido as $empresaId => $datos) {
            $mapa[$empresaId] = $datos['id'];
        }

        return $mapa;
    }

    /**
     * Contactos sin actividad comercial en las últimas 8 semanas.
     *
     * Los contactos con un proyecto activo quedan fuera: están siendo atendidos
     * en producción, no abandonados. Esa exclusión se aplica SOLO aquí — una
     * cotización caída sigue siendo recuperable aunque el cliente tenga otro
     * proyecto en curso.
     *
     * @return array  [contacto_id => null]
     */
    private function porInactividad()
    {
        $conProyectoActivo = DB::connection('tenant')->table('proyecto')
            ->whereNotNull('contacto_id')
            ->where('estado', 'activo')
            ->whereNull('deleted_at')
            ->distinct()
            ->pluck('contacto_id')
            ->all();

        $consulta = DB::connection('tenant')->table('contacto')
            ->where('estado', 1)
            ->whereNull('deleted_at');

        if (!empty($conProyectoActivo)) {
            $consulta->whereNotIn('id', $conProyectoActivo);
        }

        $contactos = $consulta->orderBy('id')->pluck('created_at', 'id')->all();
        if (empty($contactos)) {
            return [];
        }

        $limite = now()->subDays(Com07Oportunidad::DIAS_INACTIVIDAD)->format('Y-m-d H:i:s');
        $mapa   = [];

        foreach (array_chunk($contactos, self::TAMANO_LOTE, true) as $lote) {
            $actividades = $this->actividad->ultimaActividadPara(array_keys($lote));

            foreach ($lote as $contactoId => $creadoEn) {
                $referencia = $this->referenciaDeAntiguedad($actividades, $contactoId, $creadoEn);
                if ($referencia !== null && strcmp($referencia, $limite) < 0) {
                    $mapa[(int) $contactoId] = null;
                }
            }
        }

        return $mapa;
    }

    /**
     * Desde cuándo se cuenta la antigüedad comercial de un contacto.
     *
     * Cuando no hay ninguna actividad registrada se usa la fecha en que se
     * registró el contacto: la relación no se movió nunca, y contarla desde su
     * alta es lo que refleja la realidad comercial.
     *
     * @return string|null  'Y-m-d H:i:s'
     */
    private function referenciaDeAntiguedad(array $actividades, $contactoId, $creadoEn)
    {
        if (!empty($actividades[$contactoId])) {
            return $actividades[$contactoId];
        }

        return $creadoEn ? (string) $creadoEn : null;
    }

    /*
    |--------------------------------------------------------------------------
    | Creación
    |--------------------------------------------------------------------------
    */

    private function procesarLote(array $lote, $simulacion, array &$resumen)
    {
        $ids = array_keys($lote);

        $vigentes    = $this->contactosConOportunidadVigente($ids);
        $yaGenerados = $this->requerimientosYaGenerados($lote);
        $concluidas  = $this->inactividadYaConcluida($ids);

        $actividades  = $this->actividad->ultimaActividadPara($ids);
        $responsables = $this->responsable->paraContactos($ids);
        $datos        = $this->datosDeContacto($ids);

        $empresas = [];
        foreach ($datos as $fila) {
            if (!empty($fila->empresa_id)) {
                $empresas[] = (int) $fila->empresa_id;
            }
        }
        $montos = $this->montoHistoricoPorEmpresa($empresas);

        foreach ($lote as $contactoId => $candidato) {
            if (isset($vigentes[$contactoId])) {
                $resumen['omitidas_vigente']++;
                continue;
            }

            $requerimientoId = $candidato['requerimiento_id'];
            if ($requerimientoId !== null && isset($yaGenerados[$requerimientoId])) {
                $resumen['omitidas_repeticion']++;
                continue;
            }

            $creadoEn   = isset($datos[$contactoId]) ? $datos[$contactoId]->created_at : null;
            $referencia = $this->referenciaDeAntiguedad($actividades, $contactoId, $creadoEn);

            if ($requerimientoId === null && $this->yaSeConcluyoSinNovedad($concluidas, $contactoId, $referencia)) {
                $resumen['omitidas_repeticion']++;
                continue;
            }

            if (empty($responsables[$contactoId])) {
                $resumen['sin_responsable']++;
            }

            if (!$simulacion) {
                $this->crear($contactoId, $candidato, $referencia, $responsables, $montos, $datos);
            }

            $resumen['creadas']++;
            $resumen['por_origen'][$candidato['origen']]++;
        }
    }

    private function crear($contactoId, array $candidato, $referencia, array $responsables, array $montos, array $datos)
    {
        $ahora     = now();
        $empresaId = isset($datos[$contactoId]) ? $datos[$contactoId]->empresa_id : null;

        DB::connection('tenant')->table('com07_oportunidades')->insert([
            'contacto_id'              => $contactoId,
            'empresa_id'               => $empresaId,
            'origen'                   => $candidato['origen'],
            'requerimiento_id'         => $candidato['requerimiento_id'],
            'responsable_comercial_id' => isset($responsables[$contactoId]) ? $responsables[$contactoId] : null,
            'ultima_actividad_at'      => $referencia ? substr($referencia, 0, 10) : null,
            'monto_historico'          => isset($montos[(int) $empresaId]) ? $montos[(int) $empresaId] : null,
            'proxima_atencion_at'      => null,   // la define la ejecutiva al gestionar
            'estado'                   => Com07Oportunidad::ESTADO_PENDIENTE,
            'detectada_at'             => $ahora,
            'created_at'               => $ahora,
            'updated_at'               => $ahora,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Guardas contra la repetición
    |--------------------------------------------------------------------------
    */

    /** @return array [contacto_id => true] */
    private function contactosConOportunidadVigente(array $ids)
    {
        $filas = DB::connection('tenant')->table('com07_oportunidades')
            ->whereIn('contacto_id', $ids)
            ->whereIn('estado', Com07Oportunidad::ESTADOS_VIGENTES)
            ->pluck('contacto_id')
            ->all();

        return array_fill_keys(array_map('intval', $filas), true);
    }

    /**
     * Requerimientos que ya generaron una oportunidad alguna vez.
     *
     * @return array [requerimiento_id => true]
     */
    private function requerimientosYaGenerados(array $lote)
    {
        $requerimientos = [];
        foreach ($lote as $candidato) {
            if ($candidato['requerimiento_id'] !== null) {
                $requerimientos[] = $candidato['requerimiento_id'];
            }
        }
        if (empty($requerimientos)) {
            return [];
        }

        $filas = DB::connection('tenant')->table('com07_oportunidades')
            ->whereIn('requerimiento_id', $requerimientos)
            ->pluck('requerimiento_id')
            ->all();

        return array_fill_keys(array_map('intval', $filas), true);
    }

    /**
     * Oportunidades por inactividad ya concluidas, con la actividad que tenían.
     *
     * @return array [contacto_id => 'Y-m-d' de su ultima_actividad_at]
     */
    private function inactividadYaConcluida(array $ids)
    {
        $filas = DB::connection('tenant')->table('com07_oportunidades')
            ->select('contacto_id', DB::raw('MAX(ultima_actividad_at) AS ultima'))
            ->whereIn('contacto_id', $ids)
            ->where('origen', Com07Oportunidad::ORIGEN_SIN_MOVIMIENTO)
            ->where('estado', Com07Oportunidad::ESTADO_CONCLUIDA)
            ->groupBy('contacto_id')
            ->get();

        $mapa = [];
        foreach ($filas as $fila) {
            $mapa[(int) $fila->contacto_id] = $fila->ultima;
        }

        return $mapa;
    }

    /**
     * ¿Ya se gestionó y concluyó a este contacto sin que pasara nada nuevo?
     *
     * Solo vuelve a entrar si la relación se movió después de aquella
     * oportunidad y se enfrió otra vez.
     */
    private function yaSeConcluyoSinNovedad(array $concluidas, $contactoId, $referencia)
    {
        if (!isset($concluidas[$contactoId])) {
            return false;
        }

        $anterior = $concluidas[$contactoId];
        if ($anterior === null) {
            return true;
        }

        $actual = $referencia ? substr($referencia, 0, 10) : null;
        if ($actual === null) {
            return true;
        }

        return strcmp($actual, (string) $anterior) <= 0;
    }

    /*
    |--------------------------------------------------------------------------
    | Datos de apoyo
    |--------------------------------------------------------------------------
    */

    /**
     * Mayor importe histórico de la cuenta (Especificación, Potencial Comercial):
     * cotizaciones, Requerimientos valorizados y Proyectos.
     *
     * Se calcula por EMPRESA, no por contacto. El Potencial mide cuánto vale la
     * cuenta —igual que el número de trabajadores, que también es de la
     * empresa—, no cuánto facturó una persona en particular. Además, la mayoría
     * de las cotizaciones históricas no tiene contacto asignado: mirarlas por
     * contacto dejaría el indicador casi siempre vacío.
     *
     * Es distinto de la antigüedad, que sí es por contacto: que la cuenta valga
     * mucho no significa que esta persona haya sido gestionada.
     *
     * @return array [empresa_id => float]
     */
    private function montoHistoricoPorEmpresa(array $empresaIds)
    {
        $empresaIds = array_values(array_unique(array_filter($empresaIds)));
        if (empty($empresaIds)) {
            return [];
        }

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

        $acumular(DB::connection('tenant')->table('cotizaciones_requerimiento as cr')
            ->join('requerimiento as r', 'r.id', '=', 'cr.requerimiento_id')
            ->selectRaw('r.empresa_id, MAX(cr.monto) AS m')
            ->whereIn('r.empresa_id', $empresaIds)
            ->whereNull('r.deleted_at')
            ->groupBy('r.empresa_id')
            ->get());

        $acumular(DB::connection('tenant')->table('requerimiento')
            ->selectRaw('empresa_id, MAX(monto_estimado) AS m')
            ->whereIn('empresa_id', $empresaIds)
            ->whereNull('deleted_at')
            ->groupBy('empresa_id')
            ->get());

        $acumular(DB::connection('tenant')->table('proyecto')
            ->selectRaw('empresa_id, MAX(monto_total) AS m')
            ->whereIn('empresa_id', $empresaIds)
            ->whereNull('deleted_at')
            ->groupBy('empresa_id')
            ->get());

        $acumular(DB::connection('tenant')->table('quotation_prequotes')
            ->selectRaw('empresa_id, MAX(total) AS m')
            ->whereIn('empresa_id', $empresaIds)
            ->whereNull('deleted_at')
            ->groupBy('empresa_id')
            ->get());

        return $mapa;
    }

    /** @return array [contacto_id => objeto con empresa_id y created_at] */
    private function datosDeContacto(array $ids)
    {
        $filas = DB::connection('tenant')->table('contacto')
            ->select('id', 'empresa_id', 'created_at')
            ->whereIn('id', $ids)
            ->get();

        $mapa = [];
        foreach ($filas as $fila) {
            $mapa[(int) $fila->id] = $fila;
        }

        return $mapa;
    }
}
