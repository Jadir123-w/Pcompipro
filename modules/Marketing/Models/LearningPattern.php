<?php

namespace Modules\Marketing\Models;

use App\Models\Tenant\ModelTenant;

/**
 * LearningPattern — Patrón editorial aprendido (MKT-03)
 *
 * Patrón detectado del histórico o de contenido extraordinario aprobado.
 * Alimenta la generación de nuevas grillas (principio del 70% de aprendizaje).
 */
class LearningPattern extends ModelTenant
{
    protected $table = 'mkt_learning_patterns';

    protected $fillable = [
        'fuente',
        'historical_grid_id',
        'extraordinary_id',
        'tipo_patron',
        'red_social',
        'titulo',
        'descripcion',
        'datos_patron',
        'ocurrencias',
        'confianza',
        'active',
        'aprobado_por_usuario',
    ];

    protected $casts = [
        'datos_patron'         => 'array',
        'ocurrencias'          => 'integer',
        'confianza'            => 'decimal:2',
        'active'               => 'boolean',
        'aprobado_por_usuario' => 'boolean',
    ];

    /*
    |--------------------------------------------------------------------------
    | Constantes
    |--------------------------------------------------------------------------
    */
    const FUENTE_HISTORICO              = 'historico';
    const FUENTE_EXTRAORDINARIO_APROBADO = 'extraordinario_aprobado';
    const FUENTE_MANUAL                 = 'manual';

    const TIPO_FRECUENCIA             = 'frecuencia';
    const TIPO_TEMPORADA              = 'temporada';
    const TIPO_CAMPANIA               = 'campania';
    const TIPO_DISTRIBUCION_RED       = 'distribucion_red';
    const TIPO_TONO                   = 'tono';
    const TIPO_PIEZA                  = 'tipo_pieza';
    const TIPO_RITMO                  = 'ritmo';
    const TIPO_FESTIVIDAD             = 'festividad';
    const TIPO_COMUNICACION_RECURRENTE = 'comunicacion_recurrente';

    const TIPOS_PATRON = [
        self::TIPO_FRECUENCIA,
        self::TIPO_TEMPORADA,
        self::TIPO_CAMPANIA,
        self::TIPO_DISTRIBUCION_RED,
        self::TIPO_TONO,
        self::TIPO_PIEZA,
        self::TIPO_RITMO,
        self::TIPO_FESTIVIDAD,
        self::TIPO_COMUNICACION_RECURRENTE,
    ];

    /*
    |--------------------------------------------------------------------------
    | Relaciones
    |--------------------------------------------------------------------------
    */

    public function historicalGrid()
    {
        return $this->belongsTo(HistoricalGrid::class, 'historical_grid_id');
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

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    public function scopeByTipo($query, $tipo)
    {
        return $query->where('tipo_patron', $tipo);
    }

    public function scopeByRed($query, $red)
    {
        return $query->where(function ($q) use ($red) {
            $q->where('red_social', $red)
              ->orWhereNull('red_social');
        });
    }

    public function scopeConConfianza($query, $minConfianza = 50)
    {
        return $query->where('confianza', '>=', $minConfianza);
    }
}
