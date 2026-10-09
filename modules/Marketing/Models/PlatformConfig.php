<?php

namespace Modules\Marketing\Models;

use App\Models\Tenant\ModelTenant;

/**
 * PlatformConfig — Configuración por red social (MKT-03)
 *
 * Reglas editoriales, límites técnicos y estilo visual por red:
 * facebook, instagram, linkedin, tiktok.
 */
class PlatformConfig extends ModelTenant
{
    protected $table = 'mkt_platform_config';

    protected $fillable = [
        'red_social',
        'nombre_publico',
        'funcion_editorial_principal',
        'que_priorizar',
        'que_evitar',
        'tono_recomendado',
        'max_copy_length',
        'max_hashtags',
        'formatos_soportados',
        'aspect_ratio_default',
        'aspect_ratios_soportados',
        'comparte_grilla_base',
        'red_pareja',
        'active',
    ];

    protected $casts = [
        'max_copy_length'          => 'integer',
        'max_hashtags'             => 'integer',
        'formatos_soportados'      => 'array',
        'aspect_ratios_soportados' => 'array',
        'comparte_grilla_base'     => 'boolean',
        'active'                   => 'boolean',
    ];

    /*
    |--------------------------------------------------------------------------
    | Constantes
    |--------------------------------------------------------------------------
    */
    const RED_FACEBOOK   = 'facebook';
    const RED_INSTAGRAM  = 'instagram';
    const RED_LINKEDIN   = 'linkedin';
    const RED_TIKTOK     = 'tiktok';

    const REDES = [
        self::RED_FACEBOOK,
        self::RED_INSTAGRAM,
        self::RED_LINKEDIN,
        self::RED_TIKTOK,
    ];

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    /**
     * Obtener configuración por red.
     *
     * @param  string $red
     * @return PlatformConfig|null
     */
    public static function forRed($red)
    {
        return static::where('red_social', $red)->active()->first();
    }

    /**
     * ¿La red comparte grilla base con otra red?
     *
     * @return bool
     */
    public function tieneRedPareja()
    {
        return $this->comparte_grilla_base && !empty($this->red_pareja);
    }
}
