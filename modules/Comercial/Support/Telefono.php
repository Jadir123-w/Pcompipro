<?php

namespace Modules\Comercial\Support;

/**
 * Normalización única de teléfonos de contactos.
 *
 * Formato guardado:
 *   - Celular Perú: 9 dígitos que empiezan en 9 ("939446847").
 *   - Fijo Perú: "0" + código de ciudad + número; 7 dígitos sin código = Lima ("014581458").
 *   - Extranjero: "+" + dígitos ("+56912345678").
 *   - Con anexo: "<número> anexo <dígitos>" ("014581458 anexo 003").
 * Lo que no encaja (incompletos, 6 dígitos sin código de ciudad) se guarda solo con sus dígitos.
 */
class Telefono
{
    const SEPARADOR_ANEXO = ' anexo ';

    // Formas reales en que se escribe el anexo: anexo, anex, anx, an, ext, extensión, x.
    const PATRON_ANEXO = '/\s*(?:anexo|anex|anx|an|ext|extensi[oó]n|x)\s*[.:]?\s*(\d{1,6})\s*$/iu';

    /**
     * Separa el anexo escrito al final del texto.
     *
     * @return array ['numero' => string, 'anexo' => string|null]
     */
    public static function separarAnexo($valor)
    {
        $texto = trim((string) $valor);
        if (preg_match(self::PATRON_ANEXO, $texto, $m)) {
            return [
                'numero' => trim(mb_substr($texto, 0, mb_strlen($texto) - mb_strlen($m[0]))),
                'anexo'  => $m[1],
            ];
        }

        return ['numero' => $texto, 'anexo' => null];
    }

    /**
     * Normaliza solo el número (sin anexo).
     */
    public static function normalizarNumero($numero)
    {
        $texto = trim((string) $numero);
        if ($texto === '') {
            return '';
        }
        if ($texto[0] === '#') {
            return '#' . preg_replace('/\D/', '', $texto);
        }

        $conMas = $texto[0] === '+';
        $d = preg_replace('/\D/', '', $texto);
        if (strpos($d, '00') === 0) {
            $d = substr($d, 2);
            $conMas = true;
        }
        $largo = strlen($d);

        // Perú con código de país: 51 + celular / 51 + fijo.
        if ($largo === 11 && strpos($d, '519') === 0) {
            return substr($d, 2);
        }
        if ($largo === 11 && strpos($d, '510') === 0) {
            return substr($d, 2);
        }
        if (($largo === 9 || $largo === 10) && strpos($d, '51') === 0) {
            $resto = substr($d, 2);
            return strlen($resto) === 7 ? '01' . $resto : '0' . $resto;
        }

        if ($largo === 9 && ($d[0] === '9' || $d[0] === '0')) {
            return $d;
        }
        if ($largo === 7) {
            return '01' . $d;
        }
        if ($largo >= 10 && ($conMas || strpos($d, '51') !== 0)) {
            return '+' . $d;
        }

        return $d;
    }

    /**
     * Valor completo a guardar. El anexo explícito (casilla del formulario) manda
     * sobre el que venga escrito en el texto.
     */
    public static function normalizar($valor, $anexo = null)
    {
        $partes = self::separarAnexo($valor);
        $numero = self::normalizarNumero($partes['numero']);
        if ($numero === '') {
            return '';
        }

        $anexo = $anexo !== null && trim((string) $anexo) !== ''
            ? preg_replace('/\D/', '', (string) $anexo)
            : $partes['anexo'];

        return $anexo !== null && $anexo !== '' ? $numero . self::SEPARADOR_ANEXO . $anexo : $numero;
    }

    /**
     * Celular peruano (personal): no se puede repetir entre contactos activos.
     */
    public static function esCelular($normalizado)
    {
        return (bool) preg_match('/^9\d{8}$/', (string) $normalizado);
    }

    /**
     * Texto para mostrar (agrupa los dígitos; el anexo va aparte).
     */
    public static function formatear($normalizado)
    {
        $partes = explode(self::SEPARADOR_ANEXO, (string) $normalizado, 2);
        $n = $partes[0];

        if (preg_match('/^(9\d{2})(\d{3})(\d{3})$/', $n, $m)) {
            $texto = "{$m[1]} {$m[2]} {$m[3]}";
        } elseif (preg_match('/^01(\d{3})(\d{4})$/', $n, $m)) {
            $texto = "(01) {$m[1]} {$m[2]}";
        } elseif (preg_match('/^(0\d{2})(\d{3})(\d{3})$/', $n, $m)) {
            $texto = "({$m[1]}) {$m[2]} {$m[3]}";
        } else {
            $texto = $n;
        }

        return isset($partes[1]) ? $texto . self::SEPARADOR_ANEXO . $partes[1] : $texto;
    }

    /**
     * ¿El texto buscado parece un teléfono? (solo dígitos y signos de teléfono, 6+ dígitos).
     */
    public static function pareceTelefono($texto)
    {
        $texto = trim((string) $texto);
        return $texto !== ''
            && preg_match('/^[\d\s+()\-\/.#]+$/', $texto)
            && strlen(preg_replace('/\D/', '', $texto)) >= 6;
    }
}
