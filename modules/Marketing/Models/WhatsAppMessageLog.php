<?php

namespace Modules\Marketing\Models;

use App\Models\Tenant\ModelTenant;

/**
 * WhatsAppMessageLog — Log temporal de mensajes WhatsApp (MKT-01).
 *
 * Tabla: chatbot_whatsapp_message_log
 *
 * Persiste cada inbound (mensaje del usuario al bot) y cada outbound
 * (respuesta que la IA generó, ANTES de la aprobación de Nivel 1). Útil
 * para diagnosticar si los mensajes están llegando al webhook y qué
 * generó la IA en cada caso.
 *
 * Esta tabla NO se usa en flujo operativo — solo para inspección con
 * DBeaver/SQL durante la fase de validación del canal WhatsApp.
 */
class WhatsAppMessageLog extends ModelTenant
{
    protected $table = 'chatbot_whatsapp_message_log';

    /** El log es inmutable — no usamos updated_at */
    public $timestamps = false;

    protected $fillable = [
        'session_id',
        'wa_id',
        'contact_name',
        'direction',
        'message_text',
        'ai_intent',
        'ai_provider',
        'ai_model',
        'meta',
        'meta_message_id',
        'created_at',
    ];

    protected $casts = [
        'meta'       => 'array',
        'created_at' => 'datetime',
    ];

    const DIRECTION_INBOUND  = 'inbound';
    const DIRECTION_OUTBOUND = 'outbound';

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    public function scopeBySession($query, $sessionId)
    {
        return $query->where('session_id', $sessionId);
    }

    public function scopeByWaId($query, $waId)
    {
        return $query->where('wa_id', $waId);
    }

    public function scopeChronological($query)
    {
        return $query->orderBy('created_at', 'asc')->orderBy('id', 'asc');
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers de fábrica — facilitan loguear sin armar el array a mano
    |--------------------------------------------------------------------------
    */

    /**
     * Registrar un mensaje INBOUND (del usuario al bot).
     *
     * @return self
     */
    public static function logInbound(array $data)
    {
        return self::create(array_merge([
            'direction'  => self::DIRECTION_INBOUND,
            'created_at' => now(),
        ], $data));
    }

    /**
     * Registrar un mensaje OUTBOUND (respuesta del bot).
     *
     * @return self
     */
    public static function logOutbound(array $data)
    {
        return self::create(array_merge([
            'direction'  => self::DIRECTION_OUTBOUND,
            'created_at' => now(),
        ], $data));
    }

    /**
     * ¿Ya se procesó un INBOUND con este message_id de Meta? (idempotencia)
     *
     * Meta reintenta el webhook si no recibe 200 a tiempo (p. ej. cuando la
     * respuesta del LLM tarda). Sin dedup, en modo Automatizado el agente
     * respondería dos veces. Como cada inbound se registra aquí (logInbound)
     * antes de procesarse, este chequeo permite ignorar el reintento.
     *
     * @param  string|null  $metaMessageId
     * @return bool
     */
    public static function isInboundProcessed($metaMessageId)
    {
        if (empty($metaMessageId)) {
            return false;
        }

        return self::where('meta_message_id', $metaMessageId)
            ->where('direction', self::DIRECTION_INBOUND)
            ->exists();
    }
}
