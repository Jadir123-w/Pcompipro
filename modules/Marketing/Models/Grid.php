<?php

namespace Modules\Marketing\Models;

use App\Models\Tenant\ModelTenant;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Grid — Grilla editorial (MKT-03)
 *
 * Grilla de planificación de contenido para redes sociales.
 * Puede ser anual, trimestral, semestral, mensual, por campaña o fecha.
 */
class Grid extends ModelTenant
{
    use SoftDeletes;

    protected $table = 'mkt_grids';

    protected $fillable = [
        'anio',
        'periodo',
        'tipo_periodo',
        'red_social',
        'version',
        'estado',
        'titulo',
        'descripcion',
        'fuente_base_historica_id',
        'revisor_id',
        'aprobador_id',
        'estado_aprobacion',
        'fecha_aprobacion',
        'observaciones_operativas',
        'user_id',
        'ai_provider',
        'ai_model',
    ];

    protected $casts = [
        'anio'              => 'integer',
        'version'           => 'integer',
        'fecha_aprobacion'  => 'datetime',
    ];

    /*
    |--------------------------------------------------------------------------
    | Constantes
    |--------------------------------------------------------------------------
    */
    const ESTADO_BORRADOR     = 'borrador';
    const ESTADO_EN_REVISION  = 'en_revision';
    const ESTADO_VALIDADA     = 'validada';
    const ESTADO_ACTIVA       = 'activa';
    const ESTADO_CERRADA      = 'cerrada';

    const ESTADOS = [
        self::ESTADO_BORRADOR,
        self::ESTADO_EN_REVISION,
        self::ESTADO_VALIDADA,
        self::ESTADO_ACTIVA,
        self::ESTADO_CERRADA,
    ];

    const TIPO_ANUAL        = 'anual';
    const TIPO_TRIMESTRAL   = 'trimestral';
    const TIPO_SEMESTRAL    = 'semestral';
    const TIPO_MENSUAL      = 'mensual';
    const TIPO_CAMPANIA     = 'campania';
    const TIPO_FECHA        = 'fecha';

    const TIPOS_PERIODO = [
        self::TIPO_ANUAL,
        self::TIPO_TRIMESTRAL,
        self::TIPO_SEMESTRAL,
        self::TIPO_MENSUAL,
        self::TIPO_CAMPANIA,
        self::TIPO_FECHA,
    ];

    const RED_FACEBOOK    = 'facebook';
    const RED_INSTAGRAM   = 'instagram';
    const RED_LINKEDIN    = 'linkedin';
    const RED_TIKTOK      = 'tiktok';
    const RED_MULTIRED    = 'multired';
    const RED_TODAS       = 'todas';

    const REDES = [
        self::RED_FACEBOOK,
        self::RED_INSTAGRAM,
        self::RED_LINKEDIN,
        self::RED_TIKTOK,
        self::RED_MULTIRED,
        self::RED_TODAS,
    ];

    /*
    |--------------------------------------------------------------------------
    | Relaciones
    |--------------------------------------------------------------------------
    */

    public function items()
    {
        return $this->hasMany(GridItem::class, 'grid_id')
            ->orderBy('fecha_publicacion')
            ->orderBy('item_order');
    }

    public function historicalBase()
    {
        return $this->belongsTo(HistoricalGrid::class, 'fuente_base_historica_id');
    }

    public function user()
    {
        return $this->belongsTo(\App\Models\Tenant\User::class, 'user_id');
    }

    public function revisor()
    {
        return $this->belongsTo(\App\Models\Tenant\User::class, 'revisor_id');
    }

    public function aprobador()
    {
        return $this->belongsTo(\App\Models\Tenant\User::class, 'aprobador_id');
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    public function scopeActivas($query)
    {
        return $query->where('estado', self::ESTADO_ACTIVA);
    }

    public function scopeByAnio($query, $anio)
    {
        return $query->where('anio', $anio);
    }

    public function scopeByRed($query, $red)
    {
        return $query->where('red_social', $red);
    }

    public function scopeByEstado($query, $estado)
    {
        return $query->where('estado', $estado);
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    public function isEditable()
    {
        return in_array($this->estado, [
            self::ESTADO_BORRADOR,
            self::ESTADO_EN_REVISION,
        ]);
    }

    public function isActive()
    {
        return $this->estado === self::ESTADO_ACTIVA;
    }

    public function totalItems()
    {
        return $this->items()->count();
    }
}
