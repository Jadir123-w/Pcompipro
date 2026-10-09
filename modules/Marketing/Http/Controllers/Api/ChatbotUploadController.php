<?php

namespace Modules\Marketing\Http\Controllers\Api;

use Hyn\Tenancy\Environment;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Modules\Marketing\Models\ChatbotAttachment;
use Modules\Marketing\Models\ChatbotConfig;
use Modules\Marketing\Models\ChatbotSession;

/**
 * ChatbotUploadController
 *
 * Recibe archivos del widget público (logos, fotos de referencia, screenshots)
 * y los almacena en disk local con metadata persistida en chatbot_attachments.
 *
 * Endpoints:
 *   POST   /api/marketing/chatbot/upload                    público (rate-limited)
 *   GET    /marketing/chatbot/attachments/{id}/download     auth (ejecutiva ve el lead)
 *   DELETE /marketing/chatbot/attachments/{id}              auth (limpieza)
 *
 * Validación estricta:
 *   - MIME real (vía finfo, no confiar en Content-Type del cliente)
 *   - Tamaño max desde config general_max_upload_mb (default 5 MB)
 *   - Filename de storage = UUID + extensión inferida del MIME
 *   - Extensión declarada por cliente se ignora; usamos la del MIME validado
 *
 * Storage path: chatbot-uploads/{tenant_uuid}/{session_id}/{uuid}.{ext}
 */
class ChatbotUploadController extends Controller
{
    /**
     * POST /api/marketing/chatbot/upload  (público)
     *
     * Body multipart:
     *   - session_id  (requerido)  ID de sesión activa del chatbot
     *   - file        (requerido)  archivo a subir
     *   - kind        (opcional)   logo|reference|screenshot|other (default 'other')
     */
    public function upload(Request $request)
    {
        $v = Validator::make($request->all(), [
            'session_id' => ['required', 'string', 'max:64'],
            'file'       => ['required', 'file'],
            'kind'       => ['nullable', 'string', 'in:logo,reference,screenshot,other'],
        ], [
            'file.required' => 'No se recibió ningún archivo.',
            'file.file'     => 'El archivo no es válido.',
        ]);

        if ($v->fails()) {
            return response()->json(['ok' => false, 'errors' => $v->errors()], 422);
        }

        $sessionId = $request->input('session_id');

        // 1) Verificar que la sesión exista y esté activa
        $session = ChatbotSession::where('session_id', $sessionId)->first();
        if (!$session) {
            return response()->json([
                'ok'      => false,
                'message' => 'La sesión no existe. Refresca el chat e intenta de nuevo.',
            ], 404);
        }
        if (!$session->isActive()) {
            return response()->json([
                'ok'      => false,
                'message' => 'La conversación está cerrada. No se aceptan más archivos.',
            ], 409);
        }

        // 2) Detectar MIME real con finfo (no el Content-Type que envía el cliente)
        $file = $request->file('file');
        $realMime = $this->detectRealMime($file->getRealPath());

        if (!in_array($realMime, ChatbotAttachment::$allowedMimes)) {
            return response()->json([
                'ok'      => false,
                'message' => 'Tipo de archivo no permitido. Solo se aceptan imágenes (JPG, PNG, WEBP, GIF) y PDF.',
                'detail'  => ['detected_mime' => $realMime],
            ], 422);
        }

        // 3) Validar tamaño contra config (general_max_upload_mb, default 5 MB)
        $maxMb = $this->resolveMaxMb();
        $maxBytes = $maxMb * 1024 * 1024;
        $size = $file->getSize();
        if ($size > $maxBytes) {
            return response()->json([
                'ok'      => false,
                'message' => "El archivo excede el tamaño máximo permitido ({$maxMb} MB).",
                'detail'  => ['size_bytes' => $size, 'max_bytes' => $maxBytes],
            ], 413);
        }

        // 4) Construir path seguro: chatbot-uploads/{tenant_uuid}/{session_id}/{uuid}.{ext}
        $tenantUuid = $this->getTenantUuid();
        $ext = isset(ChatbotAttachment::$extByMime[$realMime])
            ? ChatbotAttachment::$extByMime[$realMime]
            : 'bin';
        $uuid = (string) Str::uuid();
        $filename = $uuid . '.' . $ext;
        $relativePath = 'chatbot-uploads/' . $tenantUuid . '/' . $sessionId . '/' . $filename;

        // 5) Guardar en disk local
        try {
            $stream = fopen($file->getRealPath(), 'r');
            Storage::disk('local')->put($relativePath, $stream);
            if (is_resource($stream)) {
                fclose($stream);
            }
        } catch (\Exception $e) {
            Log::error('ChatbotUploadController: error guardando archivo', [
                'session_id' => $sessionId,
                'error'      => $e->getMessage(),
            ]);
            return response()->json([
                'ok'      => false,
                'message' => 'No pudimos guardar tu archivo. Intentá de nuevo.',
            ], 500);
        }

        // 6) Persistir metadata
        $original = $this->sanitizeOriginalName($file->getClientOriginalName());

        $att = ChatbotAttachment::create([
            'session_id'    => $sessionId,
            'lead_id'       => null,
            'original_name' => $original,
            'stored_path'   => $relativePath,
            'mime_type'     => $realMime,
            'size_bytes'    => $size,
            'kind'          => $request->input('kind', ChatbotAttachment::KIND_OTHER),
            'ip_address'    => $request->ip(),
            'user_agent'    => substr((string) $request->userAgent(), 0, 500),
        ]);

        Log::info('ChatbotUploadController: archivo recibido', [
            'attachment_id' => $att->id,
            'session_id'    => $sessionId,
            'mime'          => $realMime,
            'size'          => $size,
            'kind'          => $att->kind,
        ]);

        return response()->json([
            'ok'         => true,
            'attachment' => [
                'id'            => $att->id,
                'original_name' => $att->original_name,
                'mime_type'     => $att->mime_type,
                'size_bytes'    => $att->size_bytes,
                'human_size'    => $att->humanSize(),
                'is_image'      => $att->isImage(),
                'kind'          => $att->kind,
            ],
        ], 201);
    }

    /**
     * GET /marketing/chatbot/attachments/{id}/download   (auth)
     *
     * Stream el archivo a la ejecutiva. Requiere autenticación; nadie público
     * puede acceder a los uploads.
     */
    public function download($id)
    {
        $att = ChatbotAttachment::findOrFail($id);

        if (!$att->exists()) {
            return response()->json([
                'ok'      => false,
                'message' => 'El archivo ya no está disponible en disco.',
            ], 404);
        }

        $headers = [
            'Content-Type'        => $att->mime_type,
            'Content-Disposition' => 'inline; filename="' . addslashes($att->original_name) . '"',
            'Cache-Control'       => 'private, max-age=300',
        ];

        return response()->file($att->fullPath(), $headers);
    }

    /**
     * DELETE /marketing/chatbot/attachments/{id}   (auth)
     *
     * Borra archivo físico + fila. Útil si la ejecutiva quiere limpiar adjuntos
     * irrelevantes (ej. spam) antes de archivar el lead.
     */
    public function destroy($id)
    {
        $att = ChatbotAttachment::findOrFail($id);
        $att->deleteFile();
        $att->delete();

        return response()->json(['ok' => true, 'message' => 'Archivo eliminado.']);
    }

    /**
     * GET /marketing/chatbot/leads/{leadId}/attachments  (auth)
     *
     * Listar attachments asociados a un lead.
     */
    public function indexByLead($leadId)
    {
        $atts = ChatbotAttachment::where('lead_id', $leadId)
            ->orderBy('created_at')
            ->get()
            ->map(function ($a) {
                return [
                    'id'            => $a->id,
                    'original_name' => $a->original_name,
                    'mime_type'     => $a->mime_type,
                    'size_bytes'    => $a->size_bytes,
                    'human_size'    => $a->humanSize(),
                    'is_image'      => $a->isImage(),
                    'kind'          => $a->kind,
                    'created_at'    => $a->created_at ? $a->created_at->toDateTimeString() : null,
                ];
            });

        return response()->json(['ok' => true, 'attachments' => $atts]);
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers privados
    |--------------------------------------------------------------------------
    */

    /**
     * Detectar MIME real con finfo. Si finfo no está disponible (improbable
     * en hosts modernos), cae al MIME guessing de Symfony — pero igual
     * validamos contra la whitelist.
     *
     * @param  string  $path
     * @return string
     */
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
        // Fallback: mime_content_type (deprecated pero común)
        if (function_exists('mime_content_type')) {
            return (string) @mime_content_type($path);
        }
        return 'application/octet-stream';
    }

    /**
     * Lee el límite en MB desde marketing_chatbot_config; fallback 5 MB.
     *
     * @return int
     */
    private function resolveMaxMb()
    {
        try {
            $configured = ChatbotConfig::get('general_max_upload_mb');
            if ($configured !== null) {
                $val = (int) $configured;
                if ($val > 0 && $val <= 50) {
                    return $val;
                }
            }
        } catch (\Exception $e) {
            // tabla puede no existir
        }
        return 5;
    }

    /**
     * UUID del tenant actual (Hyn) para segmentar el storage por cliente.
     *
     * @return string
     */
    private function getTenantUuid()
    {
        try {
            $env = app(Environment::class);
            $website = $env->website();
            if ($website && !empty($website->uuid)) {
                return $website->uuid;
            }
            if ($website && !empty($website->id)) {
                return 'tenant-' . $website->id;
            }
        } catch (\Exception $e) {
            // sin tenant context
        }
        return 'unknown';
    }

    /**
     * Sanitiza el nombre original eliminando paths y caracteres raros, pero
     * preserva legibilidad para que la ejecutiva sepa qué archivo es.
     *
     * @param  string  $name
     * @return string
     */
    private function sanitizeOriginalName($name)
    {
        $name = (string) $name;
        // Solo basename
        $name = basename($name);
        // Reemplazar caracteres peligrosos pero permitir acentos/espacios
        $name = preg_replace('/[\\\\\/\x00-\x1F\x7F]/u', '_', $name);
        // Truncar a 200 chars (la columna admite 255)
        if (function_exists('mb_strlen') && mb_strlen($name) > 200) {
            $name = mb_substr($name, 0, 200);
        } elseif (strlen($name) > 200) {
            $name = substr($name, 0, 200);
        }
        return $name === '' ? 'archivo' : $name;
    }
}
