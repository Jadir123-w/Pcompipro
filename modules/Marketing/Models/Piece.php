<?php

namespace Modules\Marketing\Models;

use App\Models\Tenant\ModelTenant;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Piece — Pieza final de contenido (MKT-03)
 *
 * Pieza publicable: copy + CTA + hashtags + imagen + formato,
 * adaptada a una red social específica.
 */
class Piece extends ModelTenant
{
    use SoftDeletes;

    protected $table = 'mkt_pieces';

    protected $fillable = [
        'grid_item_id',
        'extraordinary_id',
        'red_social',
        'copy',
        'cta',
        'hashtags',
        'formato',
        'enfoque_visual',
        'referencia_visual',
        'imagen_final_url',
        'imagen_prompt',
        'imagen_source',
        'estado_pieza',
        'version',
        'is_current',
        'scheduled_at',
        'published_at',
        'external_post_id',
        'creator_id',
        'approver_id',
        'observaciones',
        'ai_text_provider',
        'ai_image_provider',
        'ai_image_model',
    ];

    protected $casts = [
        'version'       => 'integer',
        'is_current'    => 'boolean',
        'scheduled_at'  => 'datetime',
        'published_at'  => 'datetime',
    ];

    /*
    |--------------------------------------------------------------------------
    | Constantes de estado
    |--------------------------------------------------------------------------
    */
    const ESTADO_PENDIENTE         = 'pendiente';
    const ESTADO_EN_PRODUCCION     = 'en_produccion';
    const ESTADO_LISTA_PUBLICAR    = 'lista_publicar';
    const ESTADO_PUBLICADA         = 'publicada';
    const ESTADO_OBSERVADA         = 'observada';
    const ESTADO_DESCARTADA        = 'descartada';

    const ESTADOS = [
        self::ESTADO_PENDIENTE,
        self::ESTADO_EN_PRODUCCION,
        self::ESTADO_LISTA_PUBLICAR,
        self::ESTADO_PUBLICADA,
        self::ESTADO_OBSERVADA,
        self::ESTADO_DESCARTADA,
    ];

    const IMAGEN_AI_GENERATED  = 'ai_generated';
    const IMAGEN_UPLOADED      = 'uploaded';
    const IMAGEN_TEMPLATE      = 'template';

    /*
    |--------------------------------------------------------------------------
    | Relaciones
    |--------------------------------------------------------------------------
    */

    public function gridItem()
    {
        return $this->belongsTo(GridItem::class, 'grid_item_id');
    }

    public function extraordinaryContent()
    {
        return $this->belongsTo(ExtraordinaryContent::class, 'extraordinary_id');
    }

    public function creator()
    {
        return $this->belongsTo(\App\Models\Tenant\User::class, 'creator_id');
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

    public function scopeCurrent($query)
    {
        return $query->where('is_current', true);
    }

    public function scopeByRed($query, $red)
    {
        return $query->where('red_social', $red);
    }

    public function scopeListasParaPublicar($query)
    {
        return $query->where('estado_pieza', self::ESTADO_LISTA_PUBLICAR);
    }

    public function scopePublicadas($query)
    {
        return $query->where('estado_pieza', self::ESTADO_PUBLICADA);
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    public function isPublishable()
    {
        return $this->estado_pieza === self::ESTADO_LISTA_PUBLICAR
            && !empty($this->copy)
            && !empty($this->imagen_final_url);
    }

    public function hasImage()
    {
        return !empty($this->imagen_final_url);
    }
}
