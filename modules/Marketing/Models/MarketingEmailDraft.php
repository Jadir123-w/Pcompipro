<?php

namespace Modules\Marketing\Models;

use App\Models\Tenant\ModelTenant;

/**
 * MarketingEmailDraft — Borrador de respuesta del Email Triage (MKT-01 ET).
 *
 * Tabla: marketing_email_drafts
 * Relación: 1:1 con MarketingEmailReceived (vía email_received_id, UNIQUE).
 *
 * Ciclo de vida:
 *   pending     → recién creado por la IA
 *   edited      → editado por la ejecutiva (sin aprobar aún)
 *   approved    → aprobado, listo para enviar SMTP
 *   rejected    → descartado, no se envía
 *   sent        → enviado vía SMTP con éxito
 *   send_error  → falló el envío SMTP (reintentable)
 */
class MarketingEmailDraft extends ModelTenant
{
    protected $table = 'marketing_email_drafts';

    protected $fillable = [
        'email_received_id',
        'draft_subject', 'draft_body',
        'to_email', 'cc_emails',
        'tone_used', 'data_gaps',
        'ai_provider', 'ai_model',
        'status',
        'approver_id', 'edited_at', 'approved_at', 'rejected_at', 'rejected_reason',
        'sent_at', 'sent_message_id', 'send_error_message',
    ];

    protected $casts = [
        'cc_emails'        => 'array',
        'data_gaps'        => 'array',
        'edited_at'        => 'datetime',
        'approved_at'      => 'datetime',
        'rejected_at'      => 'datetime',
        'sent_at'          => 'datetime',
    ];

    /*
    |--------------------------------------------------------------------------
    | Constantes
    |--------------------------------------------------------------------------
    */
    const STATUS_PENDING    = 'pending';
    const STATUS_EDITED     = 'edited';
    const STATUS_APPROVED   = 'approved';
    const STATUS_REJECTED   = 'rejected';
    const STATUS_SENT       = 'sent';
    const STATUS_SEND_ERROR = 'send_error';

    const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_EDITED,
        self::STATUS_APPROVED,
        self::STATUS_REJECTED,
        self::STATUS_SENT,
        self::STATUS_SEND_ERROR,
    ];

    /*
    |--------------------------------------------------------------------------
    | Relaciones
    |--------------------------------------------------------------------------
    */

    public function emailReceived()
    {
        return $this->belongsTo(MarketingEmailReceived::class, 'email_received_id');
    }

    public function approver()
    {
        return $this->belongsTo(\App\Models\Tenant\User::class, 'approver_id');
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    public function scopePending($query)
    {
        return $query->whereIn('status', [self::STATUS_PENDING, self::STATUS_EDITED]);
    }

    public function scopeApproved($query)
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    public function scopeReadyToSend($query)
    {
        return $query->whereIn('status', [self::STATUS_APPROVED, self::STATUS_SEND_ERROR]);
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    public function isPending()
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_EDITED]);
    }

    public function isApproved()
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function isSent()
    {
        return $this->status === self::STATUS_SENT;
    }

    public function isRejected()
    {
        return $this->status === self::STATUS_REJECTED;
    }

    /**
     * Marcar como aprobado por una persona.
     */
    public function approve($userId)
    {
        $this->update([
            'status'      => self::STATUS_APPROVED,
            'approver_id' => $userId,
            'approved_at' => now(),
        ]);
    }

    /**
     * Marcar como rechazado.
     */
    public function reject($userId, $reason = null)
    {
        $this->update([
            'status'          => self::STATUS_REJECTED,
            'approver_id'     => $userId,
            'rejected_at'     => now(),
            'rejected_reason' => $reason,
        ]);
    }

    /**
     * Marcar como editado (sin aprobar aún).
     */
    public function markEdited($userId, array $changes = [])
    {
        $payload = array_merge($changes, [
            'status'      => self::STATUS_EDITED,
            'approver_id' => $userId,
            'edited_at'   => now(),
        ]);
        $this->update($payload);
    }

    /**
     * Marcar como enviado.
     */
    public function markSent($messageId = null)
    {
        $this->update([
            'status'             => self::STATUS_SENT,
            'sent_at'            => now(),
            'sent_message_id'    => $messageId,
            'send_error_message' => null,
        ]);
    }

    /**
     * Marcar como error de envío.
     */
    public function markSendError($error)
    {
        $this->update([
            'status'             => self::STATUS_SEND_ERROR,
            'send_error_message' => $error,
        ]);
    }
}
