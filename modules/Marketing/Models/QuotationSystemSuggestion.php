<?php

namespace Modules\Marketing\Models;

use App\Models\Tenant\ModelTenant;

/**
 * QuotationSystemSuggestion — COM-02 S4
 *
 * Sugerencias de mejora de configuración detectadas por el
 * QuotationPatternAnalyzer al revisar el histórico de cotizaciones
 * validadas.  La ejecutiva las revisa desde la UI y decide aplicar,
 * rechazar o snoozearlas.
 *
 * Ciclo de vida:
 *   pending  → applied (terminal, modifica quotation_config)
 *           → rejected (terminal, con razón opcional)
 *           → snoozed (vuelve a pending tras snoozed_until)
 *
 * Tipos:
 *   tiempo_categoria   — Sugiere actualizar defop_tiempo_<cat>_dias
 *   tecnica_categoria  — Sugiere actualizar defop_tecnica_<cat>
 *   empaque_categoria  — Sugiere actualizar defop_empaque_<cat>
 *   margen_categoria   — Sugiere actualizar default_margin_percentage
 */
class QuotationSystemSuggestion extends ModelTenant
{
    protected $table = 'quotation_system_suggestions';

    /*
    |--------------------------------------------------------------------------
    | Constantes
    |--------------------------------------------------------------------------
    */

    const STATUS_PENDING  = 'pending';
    const STATUS_APPLIED  = 'applied';
    const STATUS_REJECTED = 'rejected';
    const STATUS_SNOOZED  = 'snoozed';

    public static $TERMINAL_STATUSES = [
        self::STATUS_APPLIED,
        self::STATUS_REJECTED,
    ];

    const TYPE_TIEMPO_CATEGORIA  = 'tiempo_categoria';
    const TYPE_TECNICA_CATEGORIA = 'tecnica_categoria';
    const TYPE_EMPAQUE_CATEGORIA = 'empaque_categoria';
    const TYPE_MARGEN_CATEGORIA  = 'margen_categoria';

    /*
    |--------------------------------------------------------------------------
    | Mass assignment & casts
    |--------------------------------------------------------------------------
    */

    protected $fillable = [
        'type',
        'category',
        'target_config_key',
        'current_value',
        'suggested_value',
        'evidence_count',
        'confidence',
        'reasoning',
        'status',
        'applied_by_user_id',
        'applied_at',
        'rejection_reason',
        'snoozed_until',
    ];

    protected $casts = [
        'evidence_count'      => 'integer',
        'confidence'          => 'decimal:2',
        'applied_by_user_id'  => 'integer',
        'applied_at'          => 'datetime',
        'snoozed_until'       => 'datetime',
    ];

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    public function scopePending($query)
    {
        // Considera "pending" tanto el status pending como los snoozed cuyo plazo ya pasó
        return $query->where(function ($q) {
            $q->where('status', self::STATUS_PENDING)
              ->orWhere(function ($qq) {
                  $qq->where('status', self::STATUS_SNOOZED)
                     ->where('snoozed_until', '<=', now());
              });
        });
    }

    public function scopeOfType($query, $type)
    {
        return $query->where('type', $type);
    }

    public function scopeOfTargetKey($query, $key)
    {
        return $query->where('target_config_key', $key);
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers de instancia
    |--------------------------------------------------------------------------
    */

    public function isTerminal()
    {
        return in_array($this->status, self::$TERMINAL_STATUSES, true);
    }

    public function isPendingEffective()
    {
        if ($this->status === self::STATUS_PENDING) return true;
        if ($this->status === self::STATUS_SNOOZED) {
            return $this->snoozed_until && $this->snoozed_until->isPast();
        }
        return false;
    }

    public function markApplied($userId)
    {
        if ($this->isTerminal()) return false;
        $this->update([
            'status'             => self::STATUS_APPLIED,
            'applied_by_user_id' => $userId,
            'applied_at'         => now(),
        ]);
        return true;
    }

    public function markRejected($userId, $reason = null)
    {
        if ($this->isTerminal()) return false;
        $this->update([
            'status'             => self::STATUS_REJECTED,
            'applied_by_user_id' => $userId,
            'applied_at'         => now(),
            'rejection_reason'   => $reason ? mb_substr($reason, 0, 255) : null,
        ]);
        return true;
    }

    public function snoozeFor($days = 30)
    {
        if ($this->isTerminal()) return false;
        $this->update([
            'status'        => self::STATUS_SNOOZED,
            'snoozed_until' => now()->addDays(max(1, (int) $days)),
        ]);
        return true;
    }
}
