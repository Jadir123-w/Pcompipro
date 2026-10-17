<?php

namespace Modules\Comercial\Http\Controllers\Traits;

/**
 * Búsqueda por palabra para listados: cada palabra debe aparecer (en cualquier
 * orden y posición) en alguno de los campos. Se ignoran los puntos ("sac"
 * encuentra "S.A.C.") y los comodines del usuario (% _) se buscan como texto.
 */
trait BusquedaPorPalabra
{
    /**
     * Patrones LIKE '%palabra%', uno por palabra (tope de 10 palabras).
     */
    protected function patronesPorPalabra($value)
    {
        $words = preg_split('/\s+/', str_replace('.', '', (string) $value), -1, PREG_SPLIT_NO_EMPTY);

        return array_map(function ($word) {
            return '%' . addcslashes($word, '%_\\') . '%';
        }, array_slice($words, 0, 10));
    }

    /**
     * Aplica la búsqueda sobre columnas de la tabla principal.
     */
    protected function applyWordSearch($query, array $fields, $value)
    {
        foreach ($this->patronesPorPalabra($value) as $like) {
            $query->where(function ($q) use ($fields, $like) {
                foreach ($fields as $field) {
                    $q->orWhereRaw("REPLACE({$field}, '.', '') LIKE ?", [$like]);
                }
            });
        }
    }
}
