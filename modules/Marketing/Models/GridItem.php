<?php

namespace Modules\Marketing\Models;

use App\Models\Tenant\ModelTenant;

/**
 * GridItem — Ítem de grilla editorial (MKT-03)
 *
 * Representa una publicación planificada dentro de una grilla.
 * Cada ítem puede tener una o varias piezas finales asociadas.
 */
class GridItem extends ModelTenant
{
    protected $table = 'mkt_grid_items';

    protected $fillable = [
        'grid_id',
        'fecha_publicacion',
        'red_social',
        'tematica',
        'campania',
        'temporada',
        'evento',
        'objetivo',
        'linea_comercial',
        'tipo_contenido',
        'prioridad',
        'origen',
        'feria_id',
        'extraordinary_id',
        'fase_feria',
        'item_order',
        'observaciones',
    ];

    protected $casts = [
        'fecha_publicacion' => 'date',
        'item_order'        => 'integer',
    ];

    /*
    |--------------------------------------------------------------------------
    | Constantes
    |--------------------------------------------------------------------------
    */
    const ORIGEN_GRILLA          = 'grilla';
    const ORIGEN_EXTRAORDINARIO  = 'extraordinario';
    const ORIGEN_FERIA           = 'feria';
    const ORIGEN_PUNTUAL         = 'puntual';

    const PRIORIDAD_ALTA   = 'alta';
    const PRIORIDAD_MEDIA  = 'media';
    const PRIORIDAD_BAJA   = 'baja';

    const FASE_FERIA_2M   = '2m';
    const FASE_FERIA_1M   = '1m';
    const FASE_FERIA_15D  = '15d';

    const OBJETIVOS = [
        'visibilidad',
        'posicionamiento',
        'recordacion',
        'campania',
        'temporada',
        'evento',
        'conversion',
        'presencia_institucional',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relaciones
    |--------------------------------------------------------------------------
    */

    public function grid()
    {
        return $this->belongsTo(Grid::class, 'grid_id');
    }

    public function pieces()
    {
        return $this->hasMany(Piece::class, 'grid_item_id');
    }

    public function currentPiece()
    {
        return $this->hasOne(Piece::class, 'grid_item_id')
            ->where('is_current', true);
    }

    public function feria()
    {
        return $this->belongsTo(FeriaReceived::class, 'feria_id');
    }

    public function extraordinaryContent()
    {
        return $this->belongsTo(ExtraordinaryContent::class, 'extraordinary_id');
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    public function scopeByRed($query, $red)
    {
        return $query->where('red_social', $red);
    }

    public function scopeByFecha($query, $desde, $hasta = null)
    {
        $query->where('fecha_publicacion', '>=', $desde);
        if ($hasta) {
            $query->where('fecha_publicacion', '<=', $hasta);
        }
        return $query;
    }

    public function scopeDeFeria($query)
    {
        return $query->where('origen', self::ORIGEN_FERIA);
    }
}
