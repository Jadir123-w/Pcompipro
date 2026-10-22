<?php

namespace Modules\Comercial\Support;

/**
 * Normalización de correos de contactos: sin espacios y en minúsculas.
 */
class Correo
{
    public static function normalizar($valor)
    {
        return mb_strtolower(trim((string) $valor));
    }
}
