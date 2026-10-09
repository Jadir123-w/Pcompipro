<?php

namespace Modules\Marketing\Services;

use Illuminate\Support\Facades\DB;

/**
 * Com07ResponsableResolver — A qué ejecutiva corresponde cada contacto.
 *
 * COM-07 necesita saber a quién entregarle cada oportunidad. El criterio, según
 * la Especificación Puntual (punto 4.3), tiene tres escalones:
 *
 *   1. El responsable comercial registrado en el contacto, si existe. Prevalece
 *      siempre: una interacción puntual de otra ejecutiva no lo reemplaza.
 *   2. Si está vacío, la última ejecutiva que gestionó comercialmente al
 *      contacto, buscando en el historial disponible.
 *   3. Si tampoco se puede determinar, NULL: la oportunidad queda "pendiente de
 *      asignación", visible para supervisión pero fuera del panel de todas.
 *
 * ── Por qué existe el escalón 2 ──
 * El campo del contacto lo carga COMPIPRO y va a estar vacío en la mayoría de
 * las cuentas al arrancar. Sin un respaldo, casi todas las oportunidades
 * nacerían sin dueño y el panel quedaría vacío el primer día.
 *
 * ── Por qué hay una versión en lote ──
 * El proceso diario resuelve cientos de contactos de una pasada. Resolverlos de
 * a uno serían cuatro consultas por contacto; `paraContactos()` hace cuatro
 * consultas en total, sin importar cuántos contactos entren.
 */
class Com07ResponsableResolver
{
    /**
     * Resolver el responsable de un contacto.
     *
     * @param  int  $contactoId
     * @return int|null  ID del usuario, o null si queda pendiente de asignación.
     */
    public function paraContacto($contactoId)
    {
        $mapa = $this->paraContactos([$contactoId]);

        return isset($mapa[$contactoId]) ? $mapa[$contactoId] : null;
    }

    /**
     * Resolver el responsable de varios contactos de una sola pasada.
     *
     * @param  array  $contactoIds
     * @return array  [contacto_id => user_id|null]
     */
    public function paraContactos(array $contactoIds)
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $contactoIds))));
        if (empty($ids)) {
            return [];
        }

        $resultado = array_fill_keys($ids, null);

        // ── Escalón 1: asignación explícita del contacto ──
        $explicitos = $this->asignacionExplicita($ids);
        foreach ($explicitos as $contactoId => $userId) {
            $resultado[$contactoId] = $userId;
        }

        // Sólo se busca respaldo para los que quedaron sin asignación.
        $sinAsignar = array_keys(array_filter($resultado, function ($v) {
            return $v === null;
        }));
        if (empty($sinAsignar)) {
            return $resultado;
        }

        // ── Escalón 2: última ejecutiva que gestionó al contacto ──
        // Se consultan las tres fuentes de historial y gana la más reciente.
        $candidatos = [];
        foreach ([
            $this->desdeInteraccionesDeContacto($sinAsignar),
            $this->desdeInteraccionesDeRequerimiento($sinAsignar),
            $this->desdeUltimaInteraccionDelRequerimiento($sinAsignar),
        ] as $fuente) {
            foreach ($fuente as $contactoId => $fila) {
                if (!isset($candidatos[$contactoId])
                    || strcmp($fila['fecha'], $candidatos[$contactoId]['fecha']) > 0) {
                    $candidatos[$contactoId] = $fila;
                }
            }
        }

        foreach ($candidatos as $contactoId => $fila) {
            $resultado[$contactoId] = (int) $fila['user_id'];
        }

        // ── Escalón 3: los que siguen en null quedan pendientes de asignación ──
        return $resultado;
    }

    /*
    |--------------------------------------------------------------------------
    | Fuentes
    |--------------------------------------------------------------------------
    */

    /**
     * Responsable cargado en la ficha del contacto.
     *
     * @return array  [contacto_id => user_id]
     */
    private function asignacionExplicita(array $ids)
    {
        $mapa = [];

        try {
            $filas = $this->tabla('contacto')
                ->whereIn('id', $ids)
                ->whereNotNull('responsable_comercial_id')
                ->whereNull('deleted_at')
                ->get(['id', 'responsable_comercial_id']);

            foreach ($filas as $f) {
                $mapa[(int) $f->id] = (int) $f->responsable_comercial_id;
            }
        } catch (\Exception $e) {
            // La columna todavía no existe (código desplegado antes de migrar):
            // se sigue con el criterio de respaldo en lugar de romper el proceso.
        }

        return $mapa;
    }

    /**
     * Última interacción registrada directamente sobre el contacto.
     *
     * @return array  [contacto_id => ['user_id' => int, 'fecha' => string]]
     */
    private function desdeInteraccionesDeContacto(array $ids)
    {
        $filas = $this->tabla('contacto_interaccion')
            ->whereIn('contacto_id', $ids)
            ->whereNotNull('user_id')
            ->whereNull('deleted_at')
            ->orderByDesc('created_at')
            ->get(['contacto_id', 'user_id', 'created_at']);

        return $this->primeraPorContacto($filas, 'contacto_id', 'user_id', 'created_at');
    }

    /**
     * Última interacción registrada sobre alguno de sus requerimientos.
     *
     * @return array  [contacto_id => ['user_id' => int, 'fecha' => string]]
     */
    private function desdeInteraccionesDeRequerimiento(array $ids)
    {
        $filas = $this->tabla('requerimiento_interaccion as ri')
            ->join('requerimiento as r', 'r.id', '=', 'ri.requerimiento_id')
            ->whereIn('r.contacto_id', $ids)
            ->whereNotNull('ri.user_id')
            ->whereNull('ri.deleted_at')
            ->whereNull('r.deleted_at')
            ->orderByDesc('ri.created_at')
            ->get(['r.contacto_id', 'ri.user_id', 'ri.created_at']);

        return $this->primeraPorContacto($filas, 'contacto_id', 'user_id', 'created_at');
    }

    /**
     * Usuario de la última interacción que el propio Requerimiento registra.
     *
     * @return array  [contacto_id => ['user_id' => int, 'fecha' => string]]
     */
    private function desdeUltimaInteraccionDelRequerimiento(array $ids)
    {
        $filas = $this->tabla('requerimiento')
            ->whereIn('contacto_id', $ids)
            ->whereNotNull('usuario_ultima_interaccion')
            ->whereNotNull('fecha_ultima_interaccion')
            ->whereNull('deleted_at')
            ->orderByDesc('fecha_ultima_interaccion')
            ->get(['contacto_id', 'usuario_ultima_interaccion', 'fecha_ultima_interaccion']);

        return $this->primeraPorContacto(
            $filas, 'contacto_id', 'usuario_ultima_interaccion', 'fecha_ultima_interaccion'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    /**
     * Quedarse con la primera fila de cada contacto.
     *
     * Las consultas vienen ordenadas de más reciente a más antigua, así que la
     * primera que aparece de cada contacto es la que vale. Se hace en PHP y no
     * con un GROUP BY porque MySQL 5.7 no tiene funciones de ventana y sacar
     * "el usuario de la fecha máxima" exigiría una subconsulta por fuente.
     *
     * @return array
     */
    private function primeraPorContacto($filas, $campoContacto, $campoUsuario, $campoFecha)
    {
        $mapa = [];

        foreach ($filas as $f) {
            $contactoId = (int) $f->{$campoContacto};
            if (isset($mapa[$contactoId])) {
                continue;
            }
            $mapa[$contactoId] = [
                'user_id' => (int) $f->{$campoUsuario},
                'fecha'   => (string) $f->{$campoFecha},
            ];
        }

        return $mapa;
    }

    /** Consulta sobre la conexión del tenant. */
    private function tabla($tabla)
    {
        return DB::connection('tenant')->table($tabla);
    }
}
