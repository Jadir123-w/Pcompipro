<?php

namespace Modules\Marketing\Models;

use App\Models\Tenant\ModelTenant;

/**
 * MarketingEmailReceived — Correo entrante leído por el Email Triage (MKT-01 ET).
 *
 * Tabla: marketing_emails_received
 *
 * Cada registro representa un correo único leído de la bandeja IMAP. La
 * deduplicación se hace por `message_id` (header RFC-5322). El ciclo de
 * vida del correo recorre los siguientes estados:
 *
 *   received       → recién leído de IMAP, todavía no clasificado
 *   classified     → clasificado por el LLM (rfq | consulta | etc.)
 *   draft_pending  → con borrador de respuesta esperando aprobación humana
 *   replied        → respuesta aprobada y enviada vía SMTP
 *   discarded      → descartado (no_relacionado, spam, o decidido por humano)
 *   error          → fallo durante alguna etapa (parsing, clasificación, envío)
 *
 * Helpers:
 *   $email->isDraftPending()  → bool
 *   $email->isReplied()       → bool
 *   $email->isClassifiedAs('rfq')  → bool
 */
class MarketingEmailReceived extends ModelTenant
{
    protected $table = 'marketing_emails_received';

    protected $fillable = [
        'message_id', 'imap_uid', 'imap_folder',
        'from_email', 'from_name', 'to_email', 'cc_emails',
        'subject', 'body_plain', 'body_html',
        'received_at', 'has_attachments', 'size_bytes',
        'status',
        'classification', 'classification_confidence', 'classification_reason',
        'urgency_level',
        'ai_provider', 'ai_model',
        'error_message',
    ];

    protected $casts = [
        'cc_emails'                 => 'array',
        'received_at'               => 'datetime',
        'has_attachments'           => 'boolean',
        'size_bytes'                => 'integer',
        'imap_uid'                  => 'integer',
        'classification_confidence' => 'decimal:2',
    ];

    /*
    |--------------------------------------------------------------------------
    | Constantes
    |--------------------------------------------------------------------------
    */
    const STATUS_RECEIVED       = 'received';
    const STATUS_CLASSIFIED     = 'classified';
    const STATUS_DRAFT_PENDING  = 'draft_pending';
    const STATUS_REPLIED        = 'replied';
    const STATUS_DISCARDED      = 'discarded';
    const STATUS_ERROR          = 'error';

    const STATUSES = [
        self::STATUS_RECEIVED,
        self::STATUS_CLASSIFIED,
        self::STATUS_DRAFT_PENDING,
        self::STATUS_REPLIED,
        self::STATUS_DISCARDED,
        self::STATUS_ERROR,
    ];

    /** Categorías de clasificación que usa el LLM */
    const CLASSIFICATION_RFQ           = 'rfq';
    const CLASSIFICATION_CONSULTA      = 'consulta';
    const CLASSIFICATION_NO_RELACIONADO = 'no_relacionado';
    const CLASSIFICATION_SPAM          = 'spam';
    const CLASSIFICATION_OTRO          = 'otro';

    const CLASSIFICATIONS = [
        self::CLASSIFICATION_RFQ,
        self::CLASSIFICATION_CONSULTA,
        self::CLASSIFICATION_NO_RELACIONADO,
        self::CLASSIFICATION_SPAM,
        self::CLASSIFICATION_OTRO,
    ];

    /** Niveles de urgencia detectados por el clasificador (ET2). */
    const URGENCY_LOW      = 'low';
    const URGENCY_NORMAL   = 'normal';
    const URGENCY_HIGH     = 'high';
    const URGENCY_CRITICAL = 'critical';

    const URGENCY_LEVELS = [
        self::URGENCY_LOW,
        self::URGENCY_NORMAL,
        self::URGENCY_HIGH,
        self::URGENCY_CRITICAL,
    ];

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopePendingClassification($query)
    {
        return $query->where('status', self::STATUS_RECEIVED);
    }

    public function scopePendingDraft($query)
    {
        return $query->where('status', self::STATUS_DRAFT_PENDING);
    }

    public function scopeClassifiedAs($query, $classification)
    {
        return $query->where('classification', $classification);
    }

    public function scopeRecentFirst($query)
    {
        return $query->orderByDesc('received_at')->orderByDesc('id');
    }

    /**
     * Scope: correos con urgencia alta o crítica (para destacarlos en la UI).
     */
    public function scopeWithHighUrgency($query)
    {
        return $query->whereIn('urgency_level', [self::URGENCY_HIGH, self::URGENCY_CRITICAL]);
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    public function isReceived()
    {
        return $this->status === self::STATUS_RECEIVED;
    }

    public function isClassified()
    {
        return $this->status === self::STATUS_CLASSIFIED;
    }

    public function isDraftPending()
    {
        return $this->status === self::STATUS_DRAFT_PENDING;
    }

    public function isReplied()
    {
        return $this->status === self::STATUS_REPLIED;
    }

    public function isDiscarded()
    {
        return $this->status === self::STATUS_DISCARDED;
    }

    public function isError()
    {
        return $this->status === self::STATUS_ERROR;
    }

    public function isClassifiedAs($classification)
    {
        return $this->classification === $classification;
    }

    /**
     * ¿La urgencia es alta o crítica?
     *
     * @return bool
     */
    public function isHighUrgency()
    {
        return in_array($this->urgency_level, [self::URGENCY_HIGH, self::URGENCY_CRITICAL], true);
    }

    /**
     * Marcar como error (sin tocar la clasificación si ya estaba).
     *
     * @param  string  $reason
     * @return void
     */
    public function markError($reason)
    {
        $this->update([
            'status'        => self::STATUS_ERROR,
            'error_message' => $reason,
        ]);
    }
}
