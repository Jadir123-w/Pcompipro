<?php

namespace Modules\Comercial\Http\Controllers;

use Hyn\Tenancy\Environment;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Modules\Comercial\Models\ProyectoProducto;

/**
 * ProyectoProductoImageController
 *
 * Sube / sirve / quita la imagen de referencia de un producto de proyecto
 * (`proyecto_productos.imagen`). Replica el patrón de
 * Modules\Marketing\Http\Controllers\Api\QuotationImageController (COM-02 S2),
 * pero aplicado al módulo Comercial.
 *
 * La columna `imagen` puede contener dos tipos de ruta:
 *   - PROPIA    → project-product-images/{tenant_uuid}/{producto_id}/{uuid}.{ext}
 *                 (imagen subida/cambiada desde el propio proyecto)
 *   - HEREDADA  → quotation-images/{tenant_uuid}/...
 *                 (snapshot del path de la imagen de la pre-cotización, copiado
 *                  al crear el proyecto). NO se borra del disco al quitarla,
 *                  porque ese archivo pertenece a la pre-cotización (Marketing).
 *
 * Endpoints:
 *   POST   /comercial/proyecto-productos/{id}/imagen   subir/cambiar imagen
 *   GET    /comercial/proyecto-productos/{id}/imagen   ver imagen (auth)
 *   DELETE /comercial/proyecto-productos/{id}/imagen   quitar imagen
 *
 * Validación estricta (igual a Marketing):
 *   - MIME real con finfo (no Content-Type del cliente)
 *   - Whitelist [JPG, PNG, WEBP, GIF]
 *   - Tamaño máx 5 MB
 *   - Storage en disk `local`
 */
class ProyectoProductoImageController extends Controller
{
    /** Carpeta raíz (en el disk local) de las imágenes PROPIAS del proyecto. */
    const OWN_DIR = 'project-product-images';

    /** Tamaño máximo permitido, en MB. */
    const MAX_MB = 5;

    /** MIMEs permitidos para imágenes de referencia. */
    public static $allowedMimes = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/gif',
    ];

    /** Mapeo MIME → extensión. */
    public static $extByMime = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/gif'  => 'gif',
    ];

    /**
     * POST /comercial/proyecto-productos/{id}/imagen
     * Sube (o reemplaza) la imagen del producto.
     */
    public function upload($id, Request $request)
    {
        $producto = ProyectoProducto::findOrFail($id);

        $result = $this->validateAndStoreImage($request, $producto->id);
        if (!$result['ok']) {
            return response()->json($result, $result['_status']);
        }

        // Borrar la imagen PROPIA anterior (si la había). Las imágenes heredadas
        // de la pre-cotización NO se tocan en disco (pertenecen a Marketing).
        if (!empty($producto->imagen) && $this->isOwnImage($producto->imagen)) {
            $this->safelyDelete($producto->imagen);
        }

        $producto->update(['imagen' => $result['stored_path']]);

        return response()->json([
            'ok'      => true,
            'message' => 'Imagen guardada.',
            'image'   => [
                'path'       => $producto->imagen,
                'mime'       => $result['mime_type'],
                'size'       => $result['size_bytes'],
                'human_size' => $this->humanSize($result['size_bytes']),
            ],
        ], 201);
    }

    /**
     * GET /comercial/proyecto-productos/{id}/imagen
     * Devuelve el archivo de imagen con su mime. 404 si no hay.
     */
    public function show($id)
    {
        $producto = ProyectoProducto::find($id);
        if (!$producto || empty($producto->imagen)) {
            return response()->json(['ok' => false, 'message' => 'Sin imagen.'], 404);
        }

        if (!Storage::disk('local')->exists($producto->imagen)) {
            return response()->json(['ok' => false, 'message' => 'El archivo ya no está disponible.'], 404);
        }

        // Detectar mime por extensión del path (igual que showItemImage de Marketing).
        $ext  = strtolower(pathinfo($producto->imagen, PATHINFO_EXTENSION));
        $mime = ($ext === 'png') ? 'image/png' : (($ext === 'webp') ? 'image/webp' : (($ext === 'gif') ? 'image/gif' : 'image/jpeg'));

        return response()->file(Storage::disk('local')->path($producto->imagen), [
            'Content-Type'        => $mime,
            'Cache-Control'       => 'private, max-age=300',
            'Content-Disposition' => 'inline',
        ]);
    }

    /**
     * DELETE /comercial/proyecto-productos/{id}/imagen
     * Quita la imagen del producto. Si es PROPIA, borra el archivo del disco;
     * si es HEREDADA, solo limpia la referencia (el archivo es de Marketing).
     */
    public function destroy($id)
    {
        $producto = ProyectoProducto::findOrFail($id);

        if (!empty($producto->imagen) && $this->isOwnImage($producto->imagen)) {
            $this->safelyDelete($producto->imagen);
        }

        $producto->update(['imagen' => null]);

        return response()->json(['ok' => true, 'message' => 'Imagen eliminada.']);
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers privados
    |--------------------------------------------------------------------------
    */

    /**
     * Una imagen es "propia" del proyecto cuando vive bajo OWN_DIR. Cualquier
     * otra ruta (p.ej. quotation-images/...) se considera heredada y no se borra.
     *
     * @param  string  $path
     * @return bool
     */
    private function isOwnImage($path)
    {
        return is_string($path) && strpos($path, self::OWN_DIR . '/') === 0;
    }

    /**
     * Valida el upload y persiste el archivo en disco.
     *
     * @param  Request  $request
     * @param  int      $productoId
     * @return array{
     *   ok: bool,
     *   _status: int,
     *   message?: string,
     *   stored_path?: string,
     *   mime_type?: string,
     *   size_bytes?: int,
     * }
     */
    private function validateAndStoreImage(Request $request, $productoId)
    {
        $v = Validator::make($request->all(), [
            'image' => ['required', 'file'],
        ], [
            'image.required' => 'No se recibió ningún archivo.',
            'image.file'     => 'El archivo no es válido.',
        ]);

        if ($v->fails()) {
            return ['ok' => false, '_status' => 422, 'errors' => $v->errors()];
        }

        $file = $request->file('image');

        // 1) MIME real con finfo
        $realMime = $this->detectRealMime($file->getRealPath());
        if (!in_array($realMime, self::$allowedMimes)) {
            return [
                'ok'       => false,
                '_status'  => 422,
                'message'  => 'Tipo de archivo no permitido. Solo imágenes JPG, PNG, WEBP o GIF.',
                'detected' => $realMime,
            ];
        }

        // 2) Tamaño máx
        $size = $file->getSize();
        if ($size > self::MAX_MB * 1024 * 1024) {
            return [
                'ok'      => false,
                '_status' => 413,
                'message' => 'La imagen excede el tamaño máximo permitido (' . self::MAX_MB . ' MB).',
            ];
        }

        // 3) Construir path: project-product-images/{tenant_uuid}/{producto_id}/{uuid}.{ext}
        $tenantUuid   = $this->getTenantUuid();
        $ext          = isset(self::$extByMime[$realMime]) ? self::$extByMime[$realMime] : 'jpg';
        $uuid         = (string) Str::uuid();
        $relativePath = self::OWN_DIR . '/' . $tenantUuid . '/' . $productoId . '/' . $uuid . '.' . $ext;

        try {
            $stream = fopen($file->getRealPath(), 'r');
            Storage::disk('local')->put($relativePath, $stream);
            if (is_resource($stream)) {
                fclose($stream);
            }
        } catch (\Exception $e) {
            Log::error('ProyectoProductoImageController: error guardando imagen', [
                'producto_id' => $productoId,
                'error'       => $e->getMessage(),
            ]);
            return [
                'ok'      => false,
                '_status' => 500,
                'message' => 'No pudimos guardar la imagen. Intenta de nuevo.',
            ];
        }

        Log::info('ProyectoProductoImageController: imagen guardada', [
            'producto_id' => $productoId,
            'mime'        => $realMime,
            'size'        => $size,
            'stored_path' => $relativePath,
        ]);

        return [
            'ok'          => true,
            '_status'     => 201,
            'stored_path' => $relativePath,
            'mime_type'   => $realMime,
            'size_bytes'  => $size,
        ];
    }

    private function detectRealMime($path)
    {
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo) {
                $mime = finfo_file($finfo, $path);
                finfo_close($finfo);
                if (is_string($mime) && $mime !== '') {
                    return $mime;
                }
            }
        }
        if (function_exists('mime_content_type')) {
            return (string) @mime_content_type($path);
        }
        return 'application/octet-stream';
    }

    private function getTenantUuid()
    {
        try {
            $env     = app(Environment::class);
            $website = $env->website();
            if ($website && !empty($website->uuid)) {
                return $website->uuid;
            }
            if ($website && !empty($website->id)) {
                return 'tenant-' . $website->id;
            }
        } catch (\Exception $e) {
            /* sin tenant */
        }
        return 'unknown';
    }

    private function safelyDelete($path)
    {
        try {
            if (Storage::disk('local')->exists($path)) {
                Storage::disk('local')->delete($path);
            }
        } catch (\Exception $e) {
            Log::warning('ProyectoProductoImageController: no se pudo borrar archivo', [
                'path'  => $path,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function humanSize($bytes)
    {
        $bytes = (int) $bytes;
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 1) . ' MB';
        }
        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 0) . ' KB';
        }
        return $bytes . ' B';
    }
}
