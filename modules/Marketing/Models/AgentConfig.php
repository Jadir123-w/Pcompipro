<?php

namespace Modules\Marketing\Models;

use App\Models\Tenant\ModelTenant;

/**
 * AgentConfig — Configuración por agente.
 *
 * Tabla: marketing_agent_config
 * 1 fila por agente (mkt01, com02, mkt03).
 *
 * Constantes de agent_key:
 *   - AGENT_MKT01 = 'mkt01' (chatbot)
 *   - AGENT_COM02 = 'com02' (cotizador)
 *   - AGENT_MKT03 = 'mkt03' (social media)
 *
 * Helpers estáticos:
 *   - AgentConfig::forAgent('mkt03')        → retorna la config del agente con providers cargados
 *
 * Helpers de instancia:
 *   - $cfg->resolvedTextModel()   → modelo resuelto (override > default del provider)
 *   - $cfg->resolvedImageQuality()→ quality resuelta (config > default del provider)
 */
class AgentConfig extends ModelTenant
{
    protected $table = 'marketing_agent_config';

    /*
    |--------------------------------------------------------------------------
    | Constantes
    |--------------------------------------------------------------------------
    */
    const AGENT_MKT01 = 'mkt01';
    const AGENT_COM02 = 'com02';
    const AGENT_MKT03 = 'mkt03';

    public static $AGENTS = [
        self::AGENT_MKT01,
        self::AGENT_COM02,
        self::AGENT_MKT03,
    ];

    /*
    |--------------------------------------------------------------------------
    | Mass assignment & casts
    |--------------------------------------------------------------------------
    */
    protected $fillable = [
        'agent_key',
        'display_name',
        'description',
        'text_provider_id',
        'text_model_override',
        'text_temperature',
        'text_max_tokens',
        'image_provider_id',
        'image_model_override',
        'image_quality',
        'image_format',
        'image_aspect_ratio_default',
        'image_auto_generate',
        'extra_params',
        'enabled',
    ];

    protected $casts = [
        'text_temperature'    => 'float',
        'text_max_tokens'     => 'integer',
        'extra_params'        => 'array',
        'image_auto_generate' => 'boolean',
        'enabled'             => 'boolean',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relaciones
    |--------------------------------------------------------------------------
    */

    public function textProvider()
    {
        return $this->belongsTo(AiProvider::class, 'text_provider_id');
    }

    public function imageProvider()
    {
        return $this->belongsTo(AiProvider::class, 'image_provider_id');
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    public function scopeEnabled($query)
    {
        return $query->where('enabled', true);
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers estáticos
    |--------------------------------------------------------------------------
    */

    /**
     * Obtener la config de un agente con sus providers cargados.
     *
     * @param  string  $agentKey
     * @return AgentConfig|null
     */
    public static function forAgent($agentKey)
    {
        return static::with(['textProvider', 'imageProvider'])
            ->where('agent_key', $agentKey)
            ->first();
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers de instancia — resolución de valores efectivos
    |--------------------------------------------------------------------------
    */

    /**
     * Modelo de texto resuelto: override > default del provider.
     *
     * @return string|null
     */
    public function resolvedTextModel()
    {
        if (!empty($this->text_model_override)) {
            return $this->text_model_override;
        }
        return $this->textProvider ? $this->textProvider->default_model : null;
    }

    /**
     * Modelo de imagen resuelto: override > default del provider.
     *
     * @return string|null
     */
    public function resolvedImageModel()
    {
        if (!empty($this->image_model_override)) {
            return $this->image_model_override;
        }
        return $this->imageProvider ? $this->imageProvider->default_model : null;
    }

    /**
     * Max tokens resuelto: config del agente > default del provider.
     *
     * @return int|null
     */
    public function resolvedTextMaxTokens()
    {
        if (!empty($this->text_max_tokens)) {
            return (int) $this->text_max_tokens;
        }
        return $this->textProvider ? $this->textProvider->max_tokens_default : null;
    }

    /**
     * Provider key del proveedor de texto (claude, openai...).
     *
     * @return string|null
     */
    public function textProviderKey()
    {
        return $this->textProvider ? $this->textProvider->provider_key : null;
    }

    /**
     * Provider key del proveedor de imagen (dalle3, gpt-image-2...).
     *
     * @return string|null
     */
    public function imageProviderKey()
    {
        return $this->imageProvider ? $this->imageProvider->provider_key : null;
    }
}
