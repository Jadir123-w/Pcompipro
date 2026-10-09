<?php

namespace Modules\Marketing\Http\Controllers\Api\Concerns;

use Hyn\Tenancy\Environment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Modules\Marketing\Models\QuotationConfig;

/**
 * InteractsWithQuotationImages — validación + persistencia de imágenes de
 * referencia del cotizador COM-02, compartida entre controllers.
 *
 * Extraído de QuotationImageController para reusarlo desde el alta de ítems por
 * referencia (interpretItem) sin duplicar la validación estricta (MIME real por
 * finfo, whitelist, tamaño configurable) ni la convención de path.
 *
 * Storage: quotation-images/{tenant_uuid}/{type}/{entity_id}/{uuid}.{ext} (disco local).
 */
trait InteractsWithQuotationImages
{
    /** MIMEs permitidos para imágenes de referencia */
    public static $allowedMimes = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/gif',
    ];

    /** Mapeo MIME → extensión */
    public static $extByMime = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/gif'  => 'gif',
    ];

    /**
     * Validar y guardar una imagen del request en disco local.
     *
     * @param  Request  $request
     * @param  string   $type      segmento de path (p. ej. 'prequotes', 'prequote-items')
     * @param  mixed    $entityId  segmento organizativo del path (NO es FK)
     * @return array {
     *   ok: bool, _status: int (201|422|413|500),
     *   stored_path?: string, mime_type?: string, size_bytes?: int,
     *   message?: string, errors?: mixed,
     * }
     */
    protected function validateAndStoreImage(Request $request, $type, $entityId)
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

        // 1) MIME real con finfo (no el Content-Type del cliente)
        $realMime = $this->detectRealMime($file->getRealPath());
        if (!in_array($realMime, self::$allowedMimes)) {
            return [
                'ok'       => false,
                '_status'  => 422,
                'message'  => 'Tipo de archivo no permitido. Solo imágenes JPG, PNG, WEBP o GIF.',
                'detected' => $realMime,
            ];
        }

        // 2) Tamaño max
        $maxMb = $this->resolveMaxMb();
        $size  = $file->getSize();
        if ($size > $maxMb * 1024 * 1024) {
            return [
                'ok'      => false,
                '_status' => 413,
                'message' => "La imagen excede el tamaño máximo permitido ({$maxMb} MB).",
            ];
        }

        // 3) Construir path: quotation-images/{tenant_uuid}/{type}/{entity_id}/{uuid}.{ext}
        $tenantUuid = $this->getTenantUuid();
        $ext  = isset(self::$extByMime[$realMime]) ? self::$extByMime[$realMime] : 'jpg';
        $uuid = (string) Str::uuid();
        $relativePath = 'quotation-images/' . $tenantUuid . '/' . $type . '/' . $entityId . '/' . $uuid . '.' . $ext;

        try {
            $stream = fopen($file->getRealPath(), 'r');
            Storage::disk('local')->put($relativePath, $stream);
            if (is_resource($stream)) fclose($stream);
        } catch (\Exception $e) {
            Log::error('InteractsWithQuotationImages: error guardando imagen', [
                'type'      => $type,
                'entity_id' => $entityId,
                'error'     => $e->getMessage(),
            ]);
            return [
                'ok'      => false,
                '_status' => 500,
                'message' => 'No pudimos guardar la imagen. Inténtalo de nuevo.',
            ];
        }

        Log::info('InteractsWithQuotationImages: imagen guardada', [
            'type'        => $type,
            'entity_id'   => $entityId,
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

    protected function detectRealMime($path)
    {
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo) {
                $mime = finfo_file($finfo, $path);
                finfo_close($finfo);
                if (is_string($mime) && $mime !== '') return $mime;
            }
        }
        if (function_exists('mime_content_type')) {
            return (string) @mime_content_type($path);
        }
        return 'application/octet-stream';
    }

    protected function resolveMaxMb()
    {
        try {
            $val = QuotationConfig::getValue('general_max_upload_mb', 5);
            $i = (int) $val;
            if ($i > 0 && $i <= 50) return $i;
        } catch (\Exception $e) { /* fallback */ }
        return 5;
    }

    protected function getTenantUuid()
    {
        try {
            $env = app(Environment::class);
            $website = $env->website();
            if ($website && !empty($website->uuid)) return $website->uuid;
            if ($website && !empty($website->id)) return 'tenant-' . $website->id;
        } catch (\Exception $e) { /* sin tenant */ }
        return 'unknown';
    }
}
