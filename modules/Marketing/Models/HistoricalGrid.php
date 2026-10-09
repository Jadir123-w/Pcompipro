<?php

namespace Modules\Marketing\Models;

use App\Models\Tenant\ModelTenant;

/**
 * HistoricalGrid — Grilla editorial histórica importada (MKT-03)
 *
 * Grilla de años anteriores (2023, 2024, 2025) importada desde Excel.
 * Base para el aprendizaje 70% del agente.
 */
class HistoricalGrid extends ModelTenant
{
    protected $table = 'mkt_historical_grids';

    protected $fillable = [
        'anio',
        'nombre',
        'archivo_original',
        'archivo_storage_path',
        'estructura_detectada',
        'contenido_normalizado',
        'total_items',
        'redes_detectadas',
        'estado',
        'error_message',
        'user_id',
    ];

    protected $casts = [
        'anio'                  => 'integer',
        'estructura_detectada'  => 'array',
        'contenido_normalizado' => 'array',
        'redes_detectadas'      => 'array',
        'total_items'           => 'integer',
    ];

    /*
    |--------------------------------------------------------------------------
    | Constantes
    |--------------------------------------------------------------------------
    */
    const ESTADO_IMPORTADO  = 'importado';
    const ESTADO_ANALIZADO  = 'analizado';
    const ESTADO_ERROR      = 'error';

    /*
    |--------------------------------------------------------------------------
    | Relaciones
    |--------------------------------------------------------------------------
    */

    public function grids()
    {
        return $this->hasMany(Grid::class, 'fuente_base_historica_id');
    }

    public function learningPatterns()
    {
        return $this->hasMany(LearningPattern::class, 'historical_grid_id');
    }

    public function user()
    {
        return $this->belongsTo(\App\Models\Tenant\User::class, 'user_id');
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    public function scopeAnalizadas($query)
    {
        return $query->where('estado', self::ESTADO_ANALIZADO);
    }

    public function scopeByAnio($query, $anio)
    {
        return $query->where('anio', $anio);
    }
}
