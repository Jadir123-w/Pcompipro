<?php

namespace Modules\Marketing\Models;

use App\Models\Tenant\ModelTenant;

/**
 * PreQuoteHistory — Historial de cambios de precotización (COM-02)
 *
 * Registra cada acción relevante para trazabilidad y auditoría.
 */
class PreQuoteHistory extends ModelTenant
{
    protected $table = 'quotation_prequote_history';

    public $timestamps = false;

    protected $fillable = [
        'prequote_id',
        'version',
        'action',
        'changes',
        'user_id',
        'created_at',
    ];

    /*
    |--------------------------------------------------------------------------
    | Constantes de acciones
    |--------------------------------------------------------------------------
    */
    const ACTION_CREATED         = 'created';
    const ACTION_ITEM_EDITED     = 'item_edited';
    const ACTION_ITEM_ADDED      = 'item_added';
    const ACTION_ITEM_REMOVED    = 'item_removed';
    const ACTION_STATUS_CHANGED  = 'status_changed';
    const ACTION_RECALCULATED    = 'recalculated';
    const ACTION_APPROVED        = 'approved';
    const ACTION_REJECTED        = 'rejected';
    const ACTION_CONVERTED       = 'converted';

    /*
    |--------------------------------------------------------------------------
    | Relaciones
    |--------------------------------------------------------------------------
    */

    public function prequote()
    {
        return $this->belongsTo(PreQuote::class, 'prequote_id');
    }

    public function user()
    {
        return $this->belongsTo(\App\Models\Tenant\User::class, 'user_id');
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    /**
     * Obtener los cambios como array.
     *
     * @return array
     */
    public function getChangesArray()
    {
        if (empty($this->changes)) {
            return [];
        }
        $decoded = json_decode($this->changes, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Registrar una entrada en el historial.
     *
     * @param  int         $prequoteId
     * @param  int         $version
     * @param  string      $action
     * @param  array       $changes
     * @param  int|null    $userId
     * @return PreQuoteHistory
     */
    public static function log($prequoteId, $version, $action, array $changes = [], $userId = null)
    {
        return static::create([
            'prequote_id' => $prequoteId,
            'version'     => $version,
            'action'      => $action,
            'changes'     => !empty($changes) ? json_encode($changes) : null,
            'user_id'     => $userId,
            'created_at'  => now(),
        ]);
    }
}
