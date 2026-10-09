<?php

namespace Modules\Marketing\Models;

use App\Models\Tenant\ModelTenant;
use Illuminate\Support\Facades\Storage;

/**
 * ChatbotAttachment — Archivos adjuntos por usuarios del chatbot MKT-01.
 *
 * Tabla: chatbot_attachments
 *
 * Helpers:
 *   ChatbotAttachment::forSession($sessionId) → Collection de attachments de una sesión
 *   $att->fullPath()                          → path absoluto en filesystem
 *   $att->humanSize()                         → "230 KB", "2.5 MB"
 *   $att->isImage()                           → bool
 *   ChatbotAttachment::associateToLead($sessionId, $leadId) → bulk update
 */
class ChatbotAttachment extends ModelTenant
{
    protected $table = 'chatbot_attachments';

    protected $fillable = [
        'session_id',
        'lead_id',
        'original_name',
        'stored_path',
        'mime_type',
        'size_bytes',
        'kind',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'size_bytes' => 'integer',
    ];

    const KIND_LOGO       = 'logo';
    const KIND_REFERENCE  = 'reference';
    const KIND_SCREENSHOT = 'screenshot';
    const KIND_OTHER      = 'other';

    /**
     * MIME types permitidos al subir.
     *
     * @var array
     */
    public static $allowedMimes = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/gif',
        'application/pdf',
    ];

    /**
     * Extensiones por mime type para reconstruir filenames seguros.
     *
     * @var array
     */
    public static $extByMime = [
        'image/jpeg'      => 'jpg',
        'image/png'       => 'png',
        'image/webp'      => 'webp',
        'image/gif'       => 'gif',
        'application/pdf' => 'pdf',
    ];

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    public function scopeForSession($query, $sessionId)
    {
        return $query->where('session_id', $sessionId);
    }

    public function scopeForLead($query, $leadId)
    {
        return $query->where('lead_id', $leadId);
    }

    /*
    |--------------------------------------------------------------------------
    | Relaciones
    |--------------------------------------------------------------------------
    */

    public function lead()
    {
        return $this->belongsTo(MarketingChatbotLead::class, 'lead_id');
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers de instancia
    |--------------------------------------------------------------------------
    */

    /**
     * Tamaño legible.
     *
     * @return string
     */
    public function humanSize()
    {
        $bytes = (int) $this->size_bytes;
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 1) . ' MB';
        }
        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 0) . ' KB';
        }
        return $bytes . ' B';
    }

    /**
     * @return bool
     */
    public function isImage()
    {
        return strpos((string) $this->mime_type, 'image/') === 0;
    }

    /**
     * Path absoluto en el filesystem local.
     *
     * @return string|null
     */
    public function fullPath()
    {
        $disk = Storage::disk('local');
        if (!$disk->exists($this->stored_path)) {
            return null;
        }
        return $disk->path($this->stored_path);
    }

    /**
     * ¿El archivo físico todavía existe?
     *
     * @return bool
     */
    public function exists()
    {
        return Storage::disk('local')->exists($this->stored_path);
    }

    /**
     * Borra el archivo físico (no la fila).
     *
     * @return bool
     */
    public function deleteFile()
    {
        if ($this->exists()) {
            return Storage::disk('local')->delete($this->stored_path);
        }
        return true;
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers estáticos
    |--------------------------------------------------------------------------
    */

    /**
     * Asocia todos los attachments de una sesión a un lead recién creado.
     *
     * @param  string  $sessionId
     * @param  int     $leadId
     * @return int      cantidad de filas actualizadas
     */
    public static function associateToLead($sessionId, $leadId)
    {
        return static::where('session_id', $sessionId)
            ->whereNull('lead_id')
            ->update(['lead_id' => $leadId]);
    }

    /**
     * Borra los archivos físicos huérfanos (sesiones cerradas + sin lead) de
     * más de N días. Útil para mantenimiento.
     *
     * @param  int  $days
     * @return int  cantidad de filas borradas
     */
    public static function purgeOrphans($days = 30)
    {
        $threshold = now()->subDays($days);
        $orphans = static::whereNull('lead_id')
            ->where('created_at', '<', $threshold)
            ->get();

        $count = 0;
        foreach ($orphans as $att) {
            $att->deleteFile();
            $att->delete();
            $count++;
        }
        return $count;
    }
}
