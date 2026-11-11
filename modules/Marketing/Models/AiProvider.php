<?php

namespace Modules\Marketing\Models;

use App\Models\Tenant\ModelTenant;

/**
 * AiProvider — Catálogo de proveedores de IA disponibles.
 *
 * Tabla: marketing_ai_providers
 * Compartido entre los 3 agentes (vía marketing_agent_config).
 *
 * Constantes de capability:
 *   - CAPABILITY_TEXT
 *   - CAPABILITY_IMAGE
 *
 * Helpers estáticos:
 *   - AiProvider::byKey('claude')
 *   - AiProvider::textProviders()->get()
 *   - AiProvider::imageProviders()->get()
 *   - AiProvider::imageProvidersWithEdit()->get()
 *
 * Helper de instancia:
 *   - $provider->getApiKey()  → resuelve el secret desde env() en runtime
 */
class AiProvider extends ModelTenant
{
    protected $table = 'marketing_ai_providers';

    /*
    |--------------------------------------------------------------------------
    | Constantes
    |--------------------------------------------------------------------------
    */
    const CAPABILITY_TEXT  = 'text';
    const CAPABILITY_IMAGE = 'image';

    public static $CAPABILITIES = [
        self::CAPABILITY_TEXT,
        self::CAPABILITY_IMAGE,
    ];

    /*
    |--------------------------------------------------------------------------
    | Mass assignment & casts
    |--------------------------------------------------------------------------
    */
    protected $fillable = [
        'provider_key',
        'capability',
        'display_name',
        'description',
        'default_model',
        'supported_models',
        'supports_image_edit',
        'supported_qualities',
        'supported_aspect_ratios',
        'base_url',
        'api_key_env_var',
        'max_tokens_default',
        'enabled',
        'sort_order',
        'pricing_notes',
    ];

    protected $casts = [
        'supported_models'        => 'array',
        'supported_qualities'     => 'array',
        'supported_aspect_ratios' => 'array',
        'supports_image_edit'     => 'boolean',
        'enabled'                 => 'boolean',
        'max_tokens_default'      => 'integer',
        'sort_order'              => 'integer',
    ];

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    public function scopeEnabled($query)
    {
        return $query->where('enabled', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    public function scopeOfCapability($query, $capability)
    {
        return $query->where('capability', $capability);
    }

    public function scopeTextProviders($query)
    {
        return $query->ofCapability(self::CAPABILITY_TEXT);
    }

    public function scopeImageProviders($query)
    {
        return $query->ofCapability(self::CAPABILITY_IMAGE);
    }

    public function scopeImageProvidersWithEdit($query)
    {
        return $query->imageProviders()->where('supports_image_edit', true);
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers estáticos
    |--------------------------------------------------------------------------
    */

    /**
     * Buscar por provider_key (claude, openai, dalle3, gpt-image-2).
     *
     * @param  string  $key
     * @return AiProvider|null
     */
    public static function byKey($key)
    {
        return static::where('provider_key', $key)->first();
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers de instancia
    |--------------------------------------------------------------------------
    */

    /**
     * Mapeo de api_key_env_var → config path.
     *
     * IMPORTANTE: NO usamos env() directamente porque Laravel desactiva env()
     * cuando config:cache está activo (típico en producción para performance).
     * Las API keys ya viven en config('marketing.chatbot.ai.<provider>.api_key')
     * que se evalúa AL momento de cachear, así que leemos del config cacheado.
     */
    private static $API_KEY_CONFIG_PATHS = [
        'CHATBOT_CLAUDE_API_KEY' => 'marketing.chatbot.ai.claude.api_key',
        'CHATBOT_OPENAI_API_KEY' => 'marketing.chatbot.ai.openai.api_key',
        'CHATBOT_GEMINI_API_KEY' => 'marketing.chatbot.ai.gemini.api_key',
        'CLOUDFLARE_API_TOKEN'   => 'marketing.chatbot.ai.cloudflare.api_key',
    ];

    /**
     * Obtener la API key del provider.
     *
     * Estrategia:
     *   1. Si la env var está mapeada a un config path conocido → usa config()
     *      (resiste config:cache de producción)
     *   2. Sino → fallback a env() (solo funciona en development sin cache)
     *
     * @return string|null
     */
    public function getApiKey()
    {
        if (empty($this->api_key_env_var)) {
            return null;
        }

        // Path 1 (preferido): config() resiste config:cache
        if (isset(self::$API_KEY_CONFIG_PATHS[$this->api_key_env_var])) {
            $val = config(self::$API_KEY_CONFIG_PATHS[$this->api_key_env_var]);
            if (!empty($val)) {
                return $val;
            }
        }

        // Path 2 (fallback): env() — solo funciona si NO hay config:cache
        $val = env($this->api_key_env_var);
        return $val !== false && $val !== '' ? $val : null;
    }

    /**
     * @return bool
     */
    public function isConfigured()
    {
        if (empty($this->getApiKey())) {
            return false;
        }

        return $this->provider_key !== 'cloudflare'
            || !empty(config('marketing.chatbot.ai.cloudflare.account_id'));
    }

    /**
     * @return bool
     */
    public function isText()
    {
        return $this->capability === self::CAPABILITY_TEXT;
    }

    /**
     * @return bool
     */
    public function isImage()
    {
        return $this->capability === self::CAPABILITY_IMAGE;
    }

    /*
    |--------------------------------------------------------------------------
    | Relaciones
    |--------------------------------------------------------------------------
    */

    /**
     * Agentes que usan este provider para texto.
     */
    public function agentsAsText()
    {
        return $this->hasMany(AgentConfig::class, 'text_provider_id');
    }

    /**
     * Agentes que usan este provider para imagen.
     */
    public function agentsAsImage()
    {
        return $this->hasMany(AgentConfig::class, 'image_provider_id');
    }
}
