<?php

namespace Modules\Marketing\Models;

use App\Models\Tenant\ModelTenant;

class ChatbotSession extends ModelTenant
{
    protected $table = 'chatbot_sessions';

    protected $fillable = [
        'session_id',
        'channel',
        'channel_identifier',
        'status',
        'closed_reason',
        'not_understood_count',
        'page_url',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'lead_id',
        'started_at',
        'last_activity_at',
        'closed_at',
    ];

    protected $casts = [
        'started_at'           => 'datetime',
        'last_activity_at'     => 'datetime',
        'closed_at'            => 'datetime',
        'not_understood_count' => 'integer',
    ];

    /*
    |--------------------------------------------------------------------------
    | Constantes de estado
    |--------------------------------------------------------------------------
    */
    const STATUS_ACTIVE    = 'active';
    const STATUS_CLOSED    = 'closed';
    const STATUS_TIMEOUT   = 'timeout';
    const STATUS_ESCALATED = 'escalated';

    /**
     * Razones de cierre soportadas.
     *
     * - user_closed:          el usuario cerró el widget manualmente
     * - timeout_inactivity:   inactivo más allá del timeout_2 (cron close-expired)
     * - abandoned:            sesión sin ninguna respuesta del usuario tras la apertura (cron close-expired)
     * - lead_registered:      LLM cerró tras registrar lead exitosamente
     * - escalated_to_human:   derivado a una ejecutiva
     * - no_intent:            cerrado sin intención comercial detectada
     * - max_messages_reached: tope de mensajes por sesión alcanzado
     * - no_entendido_repetido: 2 turnos seguidos con intent=no_entendido (R12)
     */
    const CLOSED_REASONS = [
        'user_closed',
        'timeout_inactivity',
        'abandoned',
        'lead_registered',
        'escalated_to_human',
        'no_intent',
        'max_messages_reached',
        'no_entendido_repetido',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relaciones
    |--------------------------------------------------------------------------
    */

    /**
     * Lead generado desde esta sesión.
     */
    public function lead()
    {
        return $this->belongsTo(MarketingChatbotLead::class, 'lead_id');
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeByChannel($query, $channel)
    {
        return $query->where('channel', $channel);
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    /**
     * ¿La sesión sigue activa?
     *
     * @return bool
     */
    public function isActive()
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /**
     * Marcar actividad (actualiza timestamp).
     *
     * @return void
     */
    public function touchActivity()
    {
        $this->update(['last_activity_at' => now()]);
    }

    /**
     * Cerrar sesión con motivo.
     *
     * @param  string    $reason
     * @param  int|null  $leadId
     * @return void
     */
    public function close($reason, $leadId = null)
    {
        $this->update([
            'status'        => self::STATUS_CLOSED,
            'closed_reason' => $reason,
            'closed_at'     => now(),
            'lead_id'       => $leadId !== null ? $leadId : $this->lead_id,
        ]);
    }

    /**
     * Escalar sesión a humano.
     *
     * @param  string|null  $reason   Motivo refinado (ej: 'no_entendido_repetido', 'reclamo_o_urgente').
     *                                Si es null se usa 'escalated_to_human' (default histórico).
     * @return void
     */
    public function escalate($reason = null)
    {
        $this->update([
            'status'        => self::STATUS_ESCALATED,
            'closed_reason' => $reason ?: 'escalated_to_human',
            'closed_at'     => now(),
        ]);
    }

    /**
     * Incrementa el contador de no_entendido y devuelve el nuevo valor.
     *
     * @return int
     */
    public function incrementNotUnderstood()
    {
        $current = (int) ($this->not_understood_count ?: 0);
        $current++;
        $this->update(['not_understood_count' => $current]);
        return $current;
    }

    /**
     * Resetea el contador de no_entendido a 0 (cuando hay un intent claro).
     *
     * @return void
     */
    public function resetNotUnderstood()
    {
        if ((int) $this->not_understood_count > 0) {
            $this->update(['not_understood_count' => 0]);
        }
    }
}
