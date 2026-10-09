<?php

namespace Modules\Marketing\Models;

use App\Models\Tenant\ModelTenant;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * ExtraordinaryContent — Contenido extraordinario (MKT-03)
 *
 * Pieza fuera de la grilla planificada. Debe clasificarse y
 * el usuario debe decidir si alimenta futuras grillas.
 */
class ExtraordinaryContent extends ModelTenant
{
    use SoftDeletes;

    protected $table = 'mkt_extraordinary_content';

    protected $fillable = [
        'fecha',
        'motivo',
        'red_social',
        'descripcion',
        'clasificacion',
        'estado',
        'aprobado_para_futuras_grillas',
        'motivo_decision',
        'solicitante_id',
        'decisor_id',
        'fecha_decision',
    ];

    protected $casts = [
        'fecha'                         => 'date',
        'aprobado_para_futuras_grillas' => 'boolean',
        'fecha_decision'                => 'datetime',
    ];

    /*
    |--------------------------------------------------------------------------
    | Constantes
    |--------------------------------------------------------------------------
    */
    const ESTADO_SOLICITADO              = 'solicitado';
    const ESTADO_EN_DESARROLLO           = 'en_desarrollo';
    const ESTADO_ENTREGADO               = 'entregado';
    const ESTADO_APROBADO_PARA_FUTURAS   = 'aprobado_para_futuras';
    const ESTADO_NO_APROBADO             = 'no_aprobado';

    const ESTADOS = [
        self::ESTADO_SOLICITADO,
        self::ESTADO_EN_DESARROLLO,
        self::ESTADO_ENTREGADO,
        self::ESTADO_APROBADO_PARA_FUTURAS,
        self::ESTADO_NO_APROBADO,
    ];

    /*
    |--------------------------------------------------------------------------
    | Relaciones
    |--------------------------------------------------------------------------
    */

    public function pieces()
    {
        return $this->hasMany(Piece::class, 'extraordinary_id');
    }

    public function solicitante()
    {
        return $this->belongsTo(\App\Models\Tenant\User::class, 'solicitante_id');
    }

    public function decisor()
    {
        return $this->belongsTo(\App\Models\Tenant\User::class, 'decisor_id');
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    public function scopePendientesDecision($query)
    {
        return $query->whereIn('estado', [
            self::ESTADO_ENTREGADO,
        ])->whereNull('aprobado_para_futuras_grillas');
    }

    public function scopeAprobadasParaFuturas($query)
    {
        return $query->where('aprobado_para_futuras_grillas', true);
    }
}
