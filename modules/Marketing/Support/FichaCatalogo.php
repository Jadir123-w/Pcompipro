<?php

namespace Modules\Marketing\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Datos de un producto entre la cotización y el catálogo (tabla `items`), en los dos sentidos.
 *
 * En el catálogo, `description` es el nombre corto del producto ("CASACA CORTAVIENTO") y
 * `name` la ficha larga. En la cotización todo va al campo Descripción: el catálogo no
 * tiene campos de material, medidas o color.
 */
class FichaCatalogo
{
    /** @var array cache de largos de columna por tabla.columna */
    private static $largos = [];

    /**
     * Catálogo → cotización: nombre corto y descripción (ficha larga + modelo y
     * especificaciones técnicas, si los hay).
     *
     * @param  object  $catalogItem  fila o modelo de `items`
     * @return array  ['nombre' => string, 'descripcion' => string]
     */
    public static function desdeCatalogo($catalogItem)
    {
        $corto = trim((string) $catalogItem->description);
        $ficha = trim((string) $catalogItem->name);
        $nombre = $corto !== '' ? $corto : $ficha;

        $lineas = [];
        if ($ficha !== '' && $ficha !== $nombre) {
            $lineas[] = $ficha;
        }
        if (trim((string) ($catalogItem->model ?? '')) !== '') {
            $lineas[] = 'Modelo: ' . trim($catalogItem->model);
        }
        if (trim((string) ($catalogItem->technical_specifications ?? '')) !== '') {
            $lineas[] = trim($catalogItem->technical_specifications);
        }

        return [
            'nombre'      => self::cortar($nombre, 255),
            'descripcion' => implode("\n", $lineas),
        ];
    }

    /**
     * Cotización → catálogo: la Descripción del ítem, más material y medidas si se
     * llenaron aparte (y no están ya en el texto), para no perder lo escrito.
     *
     * @param  \Modules\Marketing\Models\PreQuoteItem  $item
     * @return string
     */
    public static function textoParaCatalogo($item)
    {
        $texto = trim(str_replace(["\r\n", "\r"], "\n", (string) $item->product_description));
        $extras = [
            'Material' => trim((string) $item->material),
            'Medidas'  => trim((string) $item->medida),
        ];
        foreach ($extras as $etiqueta => $valor) {
            if ($valor !== '' && mb_stripos($texto, $valor) === false) {
                $texto .= ($texto !== '' ? "\n" : '') . $etiqueta . ': ' . $valor;
            }
        }
        return $texto;
    }

    /**
     * Largo máximo de una columna de texto del tenant (se lee de la base: puede variar
     * entre empresas). null = sin límite práctico (TEXT).
     *
     * @param  string  $tabla
     * @param  string  $columna
     * @return int|null
     */
    public static function largoColumna($tabla, $columna)
    {
        $clave = $tabla . '.' . $columna;
        if (!array_key_exists($clave, self::$largos)) {
            $fila = DB::connection('tenant')->selectOne(
                'SELECT CHARACTER_MAXIMUM_LENGTH AS largo, DATA_TYPE AS tipo FROM information_schema.COLUMNS
                  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
                [$tabla, $columna]
            );
            $largo = $fila ? (int) $fila->largo : 0;
            self::$largos[$clave] = ($fila && in_array(strtolower($fila->tipo), ['char', 'varchar'], true) && $largo > 0) ? $largo : null;
        }
        return self::$largos[$clave];
    }

    /**
     * Corta el texto a $max caracteres en el último espacio, con "…". null = sin cortar.
     *
     * @param  string    $texto
     * @param  int|null  $max
     * @return string
     */
    public static function cortar($texto, $max)
    {
        $texto = (string) $texto;
        if ($max === null || mb_strlen($texto) <= $max) {
            return $texto;
        }
        $corte = mb_substr($texto, 0, $max - 1);
        $espacio = mb_strrpos($corte, ' ');
        if ($espacio !== false && $espacio > $max * 0.6) {
            $corte = mb_substr($corte, 0, $espacio);
        }
        return rtrim($corte, " ,;.-\n") . '…';
    }

    /**
     * Copia la foto del ítem al catálogo con las tres versiones que genera el ERP al subir
     * una imagen (normal, _medium 512 px y _small 256 px), en storage/app/public/uploads/items.
     *
     * @param  string  $rutaLocal  ruta en el disco 'local' (product_reference_image)
     * @param  string  $base       texto para el nombre del archivo (código o nombre)
     * @return array|null  ['image' => ..., 'image_medium' => ..., 'image_small' => ...]
     */
    public static function copiarImagenAlCatalogo($rutaLocal, $base)
    {
        try {
            if (!$rutaLocal || !Storage::disk('local')->exists($rutaLocal)) {
                return null;
            }
            $origen = Storage::disk('local')->path($rutaLocal);
            $ext = strtolower(pathinfo($origen, PATHINFO_EXTENSION)) ?: 'jpg';
            if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
                $ext = 'jpg';
            }
            $prefijo = Str::limit(Str::slug($base) ?: 'producto', 20, '');
            $fecha = date('YmdHis');
            $dir = 'public' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'items' . DIRECTORY_SEPARATOR;

            $normal = $prefijo . '-' . $fecha . '.' . $ext;
            Storage::put($dir . $normal, file_get_contents($origen));

            $nombres = ['image' => $normal];
            foreach (['image_medium' => [512, '_medium', 30], 'image_small' => [256, '_small', 20]] as $campo => $cfg) {
                $img = \Image::make($origen);
                $img->resize($cfg[0], null, function ($c) {
                    $c->aspectRatio();
                    $c->upsize();
                });
                $archivo = $prefijo . '-' . $fecha . $cfg[1] . '.' . $ext;
                Storage::put($dir . $archivo, (string) $img->encode('jpg', $cfg[2]));
                $nombres[$campo] = $archivo;
            }
            return $nombres;
        } catch (\Exception $e) {
            // La imagen es una ayuda: si falla, el producto se crea igual (sin foto).
            Log::warning('FichaCatalogo: no se pudo copiar la imagen al catálogo', [
                'ruta'  => $rutaLocal,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }
}
