<?php

namespace Modules\Marketing\Services;

use Illuminate\Support\Facades\DB;

/**
 * Com07ActividadResolver — Última actividad comercial válida de cada contacto.
 *
 * Es el dato del que se deriva TODA la antigüedad de COM-07: los rangos del
 * panel, el umbral de 8 semanas del proceso de detección y el factor de
 * antigüedad del Potencial Comercial. No se persiste como verdad: el proceso
 * diario lo recalcula y lo guarda como resumen en la oportunidad.
 *
 * ── Qué cuenta como actividad (Especificación Puntual, punto 3.1) ──
 * Toda gestión REGISTRADA que evidencie interacción o continuidad comercial
 * real con el contacto:
 *
 *   1. Interacciones directas del contacto (llamadas, visitas, reuniones).
 *   2. El ciclo de sus Requerimientos: creación, última interacción registrada,
 *      aprobación y escalado a proyecto.
 *   3. Interacciones registradas sobre sus Requerimientos.
 *   4. Cotizaciones subidas a sus Requerimientos.
 *   5. Precotizaciones COM-02 del contacto (creación y trabajo posterior).
 *   6. Propuestas COM-02 (creación, emisión, edición).
 *   7. Gestiones registradas por el propio COM-07.
 *
 * ── Qué NO cuenta, a propósito ──
 * - Ediciones de datos maestros del contacto (punto 3.4: una corrección
 *   administrativa no es gestión comercial). Se cumple por construcción:
 *   nunca se lee contacto.updated_at.
 * - Actividad de OTROS contactos de la misma empresa (punto 3.2): todo se
 *   consulta por contacto_id, nunca por empresa.
 * - Conversaciones del chatbot: el número de WhatsApp no está vinculado a la
 *   ficha del contacto, así que no son determinables de manera verificable.
 * - Ediciones de proyectos: el nacimiento del proyecto ya cuenta (vía el
 *   escalado del Requerimiento) y un proyecto activo excluye al contacto de la
 *   detección; el trabajo posterior de producción no es gestión sobre el
 *   contacto.
 *
 * Como el resolutor de responsable, trabaja EN LOTE: el proceso diario evalúa
 * cientos de contactos y aquí eso cuesta 7 consultas en total, no 7 por
 * contacto.
 */
class Com07ActividadResolver
{
    /**
     * Última actividad comercial válida de un contacto.
     *
     * @param  int  $contactoId
     * @return string|null  'Y-m-d H:i:s', o null si no hay actividad registrada.
     */
    public function ultimaActividadDe($contactoId)
    {
        $mapa = $this->ultimaActividadPara([$contactoId]);

        return isset($mapa[$contactoId]) ? $mapa[$contactoId] : null;
    }

    /**
     * Última actividad comercial válida de varios contactos, de una pasada.
     *
     * @param  array  $contactoIds
     * @return array  [contacto_id => 'Y-m-d H:i:s'|null]
     */
    public function ultimaActividadPara(array $contactoIds)
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $contactoIds))));
        if (empty($ids)) {
            return [];
        }

        $resultado = array_fill_keys($ids, null);

        foreach ($this->consultarFuentes($ids) as $fuente) {
            foreach ($fuente as $contactoId => $fecha) {
                if ($fecha === null || $fecha === '') {
                    continue;
                }
                // Formato MySQL 'Y-m-d H:i:s': la comparación de cadenas
                // equivale a la cronológica.
                if ($resultado[$contactoId] === null || strcmp($fecha, $resultado[$contactoId]) > 0) {
                    $resultado[$contactoId] = $fecha;
                }
            }
        }

        return $resultado;
    }

    /*
    |--------------------------------------------------------------------------
    | Fuentes (cada una es UNA consulta agregada)
    |--------------------------------------------------------------------------
    */

    /** @return array[]  lista de mapas [contacto_id => fecha] */
    private function consultarFuentes(array $ids)
    {
        return [
            $this->interaccionesDeContacto($ids),
            $this->cicloDeRequerimientos($ids),
            $this->interaccionesDeRequerimiento($ids),
            $this->cotizacionesSubidas($ids),
            $this->precotizaciones($ids),
            $this->propuestas($ids),
            $this->gestionesCom07($ids),
        ];
    }

    private function interaccionesDeContacto(array $ids)
    {
        $filas = $this->tabla('contacto_interaccion')
            ->selectRaw('contacto_id, MAX(created_at) AS f')
            ->whereIn('contacto_id', $ids)
            ->whereNull('deleted_at')
            ->groupBy('contacto_id')
            ->get();

        return $this->aMapa($filas);
    }

    /**
     * El ciclo de vida del Requerimiento en una sola consulta: crearlo,
     * registrarle la última interacción, aprobarlo y escalarlo a proyecto son
     * todos hechos comerciales con fecha propia.
     */
    private function cicloDeRequerimientos(array $ids)
    {
        $filas = $this->tabla('requerimiento')
            ->selectRaw('contacto_id,
                         MAX(created_at) AS f_creacion,
                         MAX(fecha_ultima_interaccion) AS f_interaccion,
                         MAX(fecha_aprobacion) AS f_aprobacion,
                         MAX(fecha_escalado_proyecto) AS f_escalado')
            ->whereIn('contacto_id', $ids)
            ->whereNull('deleted_at')
            ->groupBy('contacto_id')
            ->get();

        $mapa = [];
        foreach ($filas as $fila) {
            $max = null;
            foreach (['f_creacion', 'f_interaccion', 'f_aprobacion', 'f_escalado'] as $campo) {
                $v = $fila->{$campo};
                if ($v !== null && ($max === null || strcmp($v, $max) > 0)) {
                    $max = $v;
                }
            }
            if ($max !== null) {
                $mapa[(int) $fila->contacto_id] = (string) $max;
            }
        }

        return $mapa;
    }

    private function interaccionesDeRequerimiento(array $ids)
    {
        $filas = $this->tabla('requerimiento_interaccion as ri')
            ->join('requerimiento as r', 'r.id', '=', 'ri.requerimiento_id')
            ->selectRaw('r.contacto_id, MAX(ri.created_at) AS f')
            ->whereIn('r.contacto_id', $ids)
            ->whereNull('ri.deleted_at')
            ->whereNull('r.deleted_at')
            ->groupBy('r.contacto_id')
            ->get();

        return $this->aMapa($filas);
    }

    private function cotizacionesSubidas(array $ids)
    {
        $filas = $this->tabla('cotizaciones_requerimiento as cr')
            ->join('requerimiento as r', 'r.id', '=', 'cr.requerimiento_id')
            ->selectRaw('r.contacto_id, MAX(cr.created_at) AS f')
            ->whereIn('r.contacto_id', $ids)
            ->whereNull('r.deleted_at')
            ->groupBy('r.contacto_id')
            ->get();

        return $this->aMapa($filas);
    }

    /**
     * Precotizaciones COM-02: crearlas Y trabajarlas cuenta — una ejecutiva
     * ajustando costos de una cotización es gestión comercial viva.
     */
    private function precotizaciones(array $ids)
    {
        $filas = $this->tabla('quotation_prequotes')
            ->selectRaw('contacto_id, MAX(created_at) AS f1, MAX(updated_at) AS f2')
            ->whereIn('contacto_id', $ids)
            ->whereNull('deleted_at')
            ->groupBy('contacto_id')
            ->get();

        $mapa = [];
        foreach ($filas as $fila) {
            $max = $this->mayor($fila->f1, $fila->f2);
            if ($max !== null) {
                $mapa[(int) $fila->contacto_id] = $max;
            }
        }

        return $mapa;
    }

    /** Propuestas COM-02: su updated_at cubre creación, emisión y edición. */
    private function propuestas(array $ids)
    {
        $filas = $this->tabla('quotation_proposals as p')
            ->join('quotation_prequotes as q', 'q.id', '=', 'p.prequote_id')
            ->selectRaw('q.contacto_id, MAX(p.updated_at) AS f')
            ->whereIn('q.contacto_id', $ids)
            ->whereNull('p.deleted_at')
            ->whereNull('q.deleted_at')
            ->groupBy('q.contacto_id')
            ->get();

        return $this->aMapa($filas);
    }

    private function gestionesCom07(array $ids)
    {
        $filas = $this->tabla('com07_gestiones as g')
            ->join('com07_oportunidades as o', 'o.id', '=', 'g.oportunidad_id')
            ->selectRaw('o.contacto_id, MAX(g.created_at) AS f')
            ->whereIn('o.contacto_id', $ids)
            ->groupBy('o.contacto_id')
            ->get();

        return $this->aMapa($filas);
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    private function aMapa($filas)
    {
        $mapa = [];
        foreach ($filas as $fila) {
            if ($fila->f !== null) {
                $mapa[(int) $fila->contacto_id] = (string) $fila->f;
            }
        }

        return $mapa;
    }

    /** El mayor de dos datetime en texto, tolerando nulls. */
    private function mayor($a, $b)
    {
        if ($a === null) {
            return $b !== null ? (string) $b : null;
        }
        if ($b === null) {
            return (string) $a;
        }

        return strcmp((string) $a, (string) $b) >= 0 ? (string) $a : (string) $b;
    }

    private function tabla($tabla)
    {
        return DB::connection('tenant')->table($tabla);
    }
}
