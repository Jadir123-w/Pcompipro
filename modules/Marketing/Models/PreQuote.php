<?php

namespace Modules\Marketing\Models;

use App\Models\Tenant\ModelTenant;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * PreQuote — Precotización interna maestra (COM-02)
 *
 * Contenedor principal de una precotización generada por el agente cotizador.
 * Puede contener uno o varios PreQuoteItem.
 */
class PreQuote extends ModelTenant
{
    use SoftDeletes;

    protected $table = 'quotation_prequotes';

    protected $fillable = [
        'requerimiento_id',
        'empresa_id',
        'contacto_id',
        'version',
        'estado',
        'titulo',
        'descripcion_original',
        'texto_interpretado',
        'reference_image_path',
        'reference_image_mime',
        'user_id',
        'reviewer_id',
        'subtotal',
        'total_igv',
        'total',
        'sctr_porcentaje',
        'observaciones',
        'ai_provider',
        'ai_model',
        'quotation_id',
    ];

    protected $casts = [
        'version'    => 'integer',
        'subtotal'   => 'decimal:2',
        'total_igv'  => 'decimal:2',
        'total'      => 'decimal:2',
        // COM-02 — SCTR editable por cotización. NULL = usar el global del config
        // (sctr_porcentaje). El cast de Laravel preserva el NULL (no lo vuelve 0).
        'sctr_porcentaje' => 'decimal:2',
    ];

    /*
    |--------------------------------------------------------------------------
    | Constantes de estado
    |--------------------------------------------------------------------------
    */
    const ESTADO_BORRADOR              = 'borrador';
    const ESTADO_EN_REVISION           = 'en_revision';
    const ESTADO_AJUSTADA              = 'ajustada';
    const ESTADO_VALIDADA              = 'validada';
    const ESTADO_PROPUESTA_EMITIDA     = 'propuesta_emitida';
    const ESTADO_APROBADA              = 'aprobada';
    const ESTADO_RECHAZADA             = 'rechazada';
    const ESTADO_CONVERTIDA_PROYECTO   = 'convertida_en_proyecto';

    const ESTADOS = [
        self::ESTADO_BORRADOR,
        self::ESTADO_EN_REVISION,
        self::ESTADO_AJUSTADA,
        self::ESTADO_VALIDADA,
        self::ESTADO_PROPUESTA_EMITIDA,
        self::ESTADO_APROBADA,
        self::ESTADO_RECHAZADA,
        self::ESTADO_CONVERTIDA_PROYECTO,
    ];

    /*
    |--------------------------------------------------------------------------
    | Relaciones
    |--------------------------------------------------------------------------
    */

    public function items()
    {
        return $this->hasMany(PreQuoteItem::class, 'prequote_id')->orderBy('item_order');
    }

    public function history()
    {
        return $this->hasMany(PreQuoteHistory::class, 'prequote_id')->orderByDesc('created_at');
    }

    public function requerimiento()
    {
        return $this->belongsTo(\Modules\Comercial\Models\Requerimiento::class, 'requerimiento_id');
    }

    public function empresa()
    {
        return $this->belongsTo(\Modules\Comercial\Models\Empresa::class, 'empresa_id');
    }

    public function contacto()
    {
        return $this->belongsTo(\Modules\Comercial\Models\Contacto::class, 'contacto_id');
    }

    public function user()
    {
        return $this->belongsTo(\App\Models\Tenant\User::class, 'user_id');
    }

    public function reviewer()
    {
        return $this->belongsTo(\App\Models\Tenant\User::class, 'reviewer_id');
    }

    public function quotation()
    {
        return $this->belongsTo(\App\Models\Tenant\Quotation::class, 'quotation_id');
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    public function scopePendientes($query)
    {
        return $query->whereIn('estado', [
            self::ESTADO_BORRADOR,
            self::ESTADO_EN_REVISION,
            self::ESTADO_AJUSTADA,
        ]);
    }

    public function scopeValidadas($query)
    {
        return $query->where('estado', self::ESTADO_VALIDADA);
    }

    public function scopeByEstado($query, $estado)
    {
        return $query->where('estado', $estado);
    }

    public function scopeByEmpresa($query, $empresaId)
    {
        return $query->where('empresa_id', $empresaId);
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    /**
     * Obtener el texto_interpretado como array.
     *
     * @return array
     */
    public function getTextoInterpretadoArray()
    {
        if (empty($this->texto_interpretado)) {
            return [];
        }
        $decoded = json_decode($this->texto_interpretado, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * ¿La precotización es editable?
     *
     * @return bool
     */
    public function isEditable()
    {
        return in_array($this->estado, [
            self::ESTADO_BORRADOR,
            self::ESTADO_EN_REVISION,
            self::ESTADO_AJUSTADA,
        ]);
    }

    /**
     * ¿Ya fue convertida a cotización formal?
     *
     * @return bool
     */
    public function isConverted()
    {
        return $this->quotation_id !== null;
    }
}
