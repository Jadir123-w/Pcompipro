<?php

namespace Modules\Marketing\Models;

use App\Models\Tenant\ModelTenant;
use App\Models\Tenant\User;

/**
 * ChatbotPendingMessage — Cola de respuestas del bot pendientes de aprobación
 * humana antes de ser enviadas por WhatsApp (Nivel 1 del MKT-01).
 *
 * Tabla: chatbot_pending_messages
 *
 * Ciclo de vida:
 *   pending  → approved → sending → sent
 *           → edited   → sending → sent
 *           → rejected (terminal)
 *           → failed   (terminal, con error_message)
 *
 * Helpers:
 *   ChatbotPendingMessage::pending()->latest()->get()  — bandeja para la UI
 *   $msg->markApproved($userId)
 *   $msg->markEdited($userId, $newText)
 *   $msg->markRejected($userId, $reason)
 *   $msg->markSending() / markSent($metaId) / markFailed($error)
 */
class ChatbotPendingMessage extends ModelTenant
{
    protected $table = 'chatbot_pending_messages';

    /*
    |--------------------------------------------------------------------------
    | Estados
    |--------------------------------------------------------------------------
    */
    const STATUS_PENDING  = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_EDITED   = 'edited';
    const STATUS_REJECTED = 'rejected';
    const STATUS_SENDING  = 'sending';
    const STATUS_SENT     = 'sent';
    const STATUS_FAILED   = 'failed';

    public static $TERMINAL_STATUSES = [
        self::STATUS_REJECTED,
        self::STATUS_SENT,
        self::STATUS_FAILED,
    ];

    public static $READY_TO_SEND_STATUSES = [
        self::STATUS_APPROVED,
        self::STATUS_EDITED,
    ];

    /*
    |--------------------------------------------------------------------------
    | Mass assignment & casts
    |--------------------------------------------------------------------------
    */
    protected $fillable = [
        'session_id',
        'wa_id',
        'contact_name',
        'incoming_message_id',
        'user_message',
        'ai_reply',
        'ai_intent',
        'ai_actions',
        'catalog_url',
        'catalog_line',
        'status',
        'final_reply',
        'approved_by_user_id',
        'approved_at',
        'sent_at',
        'sent_message_id',
        'error_message',
        'rejection_reason',
    ];

    protected $casts = [
        'ai_actions'           => 'array',
        'approved_at'          => 'datetime',
        'sent_at'              => 'datetime',
        'approved_by_user_id'  => 'integer',
    ];

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeReadyToSend($query)
    {
        return $query->whereIn('status', self::$READY_TO_SEND_STATUSES);
    }

    public function scopeForSession($query, $sessionId)
    {
        return $query->where('session_id', $sessionId);
    }

    public function scopeForWaId($query, $waId)
    {
        return $query->where('wa_id', $waId);
    }

    public function scopeOfStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /*
    |--------------------------------------------------------------------------
    | Relaciones
    |--------------------------------------------------------------------------
    */

    /**
     * Ejecutiva que aprobó/editó/rechazó.
     */
    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers de instancia — Transiciones de estado
    |--------------------------------------------------------------------------
    */

    /**
     * @return bool
     */
    public function isPending()
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * @return bool  Si está en estado terminal (no se puede transicionar más)
     */
    public function isTerminal()
    {
        return in_array($this->status, self::$TERMINAL_STATUSES);
    }

    /**
     * @return bool
     */
    public function isReadyToSend()
    {
        return in_array($this->status, self::$READY_TO_SEND_STATUSES);
    }

    /**
     * Aprobar tal cual: el texto final será ai_reply.
     *
     * @param  int  $userId
     * @return bool
     */
    public function markApproved($userId)
    {
        if ($this->isTerminal()) return false;

        $this->update([
            'status'              => self::STATUS_APPROVED,
            'final_reply'         => $this->ai_reply,
            'approved_by_user_id' => $userId,
            'approved_at'         => now(),
        ]);
        return true;
    }

    /**
     * Aprobar con edición: el texto final es el editado.
     *
     * @param  int     $userId
     * @param  string  $newText
     * @return bool
     */
    public function markEdited($userId, $newText)
    {
        if ($this->isTerminal()) return false;

        $this->update([
            'status'              => self::STATUS_EDITED,
            'final_reply'         => trim((string) $newText),
            'approved_by_user_id' => $userId,
            'approved_at'         => now(),
        ]);
        return true;
    }

    /**
     * Rechazar: no se enviará nada.
     *
     * @param  int          $userId
     * @param  string|null  $reason
     * @return bool
     */
    public function markRejected($userId, $reason = null)
    {
        if ($this->isTerminal()) return false;

        $this->update([
            'status'              => self::STATUS_REJECTED,
            'approved_by_user_id' => $userId,
            'approved_at'         => now(),
            'rejection_reason'    => $reason,
        ]);
        return true;
    }

    /**
     * Lock optimista antes de despachar a Meta.
     *
     * @return bool
     */
    public function markSending()
    {
        if (!$this->isReadyToSend()) return false;

        $this->update(['status' => self::STATUS_SENDING]);
        return true;
    }

    /**
     * Marcar envío exitoso.
     *
     * @param  string|null  $metaMessageId
     * @return void
     */
    public function markSent($metaMessageId = null)
    {
        $this->update([
            'status'          => self::STATUS_SENT,
            'sent_at'         => now(),
            'sent_message_id' => $metaMessageId,
        ]);
    }

    /**
     * Marcar envío fallido.
     *
     * @param  string  $errorMessage
     * @return void
     */
    public function markFailed($errorMessage)
    {
        $this->update([
            'status'        => self::STATUS_FAILED,
            'error_message' => substr((string) $errorMessage, 0, 1000),
        ]);
    }

    /**
     * El texto que efectivamente se debe enviar al usuario.
     * Si fue editado se respeta la edición; si no, ai_reply.
     *
     * @return string
     */
    public function effectiveText()
    {
        return !empty($this->final_reply) ? $this->final_reply : $this->ai_reply;
    }
}
