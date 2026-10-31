<?php

namespace Modules\Marketing\Services;

use Illuminate\Support\Facades\Log;
use Modules\Marketing\Models\AgentConfig;
use Modules\Marketing\Models\AiProvider;

/**
 * AgentConfigService — Resolución central de config de agentes.
 *
 * Punto único de acceso para que el resto del módulo Marketing decida QUÉ
 * provider usar para cada agente y con QUÉ parámetros.
 *
 * Estrategia:
 *   1. Lee marketing_agent_config (tabla tenant)
 *   2. Si la tabla no existe (fresh install) o el registro está deshabilitado,
 *      cae a fallback en .env (preserva comportamiento actual)
 *   3. Cachea resoluciones por request para performance
 *
 * Uso desde otros services:
 *
 *   $svc = app(AgentConfigService::class);
 *
 *   $textCfg = $svc->getTextConfig('mkt03');
 *   // → ['provider' => 'claude', 'model' => 'claude-sonnet-4-...', 'temperature' => 0.8, 'max_tokens' => 2000, 'api_key' => 'sk-ant-...']
 *
 *   $imgCfg = $svc->getImageConfig('mkt03');
 *   // → ['provider' => 'gpt-image-2', 'model' => 'gpt-image-2', 'quality' => 'medium', 'format' => 'png', 'api_key' => 'sk-...']
 *
 *   $providerKey = $svc->getTextProviderKey('mkt01');  // 'claude'
 *   $providerKey = $svc->getImageProviderKey('mkt03'); // 'gpt-image-2'
 */
class AgentConfigService
{
    /** @var array Cache por request */
    private $cache = [];

    /**
     * Resolver config completa de TEXTO para un agente.
     *
     * @param  string  $agentKey  mkt01 | com02 | mkt03
     * @return array
     */
    public function getTextConfig($agentKey)
    {
        $cacheKey = "text:{$agentKey}";
        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

        $cfg = $this->loadAgentConfig($agentKey);

        // Si tenemos config en BD con provider asignado → usarla
        if ($cfg && $cfg->textProvider) {
            $provider = $cfg->textProvider;
            $resolved = [
                'source'      => 'db',
                'agent_key'   => $agentKey,
                'provider'    => $provider->provider_key,
                'model'       => $cfg->resolvedTextModel(),
                'temperature' => $cfg->text_temperature !== null ? (float) $cfg->text_temperature : 0.7,
                'max_tokens'  => $cfg->resolvedTextMaxTokens() ?: 1024,
                'base_url'    => $provider->base_url,
                'api_key'     => $provider->getApiKey(),
            ];
        } else {
            // Fallback a .env (config actual)
            $resolved = $this->fallbackTextFromEnv($agentKey);
        }

        $this->cache[$cacheKey] = $resolved;
        return $resolved;
    }

    /**
     * Resolver config completa de IMAGEN para un agente (típicamente solo MKT-03).
     *
     * @param  string  $agentKey
     * @return array
     */
    public function getImageConfig($agentKey)
    {
        $cacheKey = "image:{$agentKey}";
        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

        $cfg = $this->loadAgentConfig($agentKey);

        if ($cfg && $cfg->imageProvider) {
            $provider = $cfg->imageProvider;
            $resolved = [
                'source'              => 'db',
                'agent_key'           => $agentKey,
                'provider'            => $provider->provider_key,
                'model'               => $cfg->resolvedImageModel(),
                'quality'             => $cfg->image_quality,
                'format'              => $cfg->image_format ?: 'png',
                'aspect_ratio'        => $cfg->image_aspect_ratio_default ?: '1:1',
                'auto_generate'       => (bool) $cfg->image_auto_generate,
                'supports_image_edit' => (bool) $provider->supports_image_edit,
                'base_url'            => $provider->base_url,
                'api_key'             => $provider->getApiKey(),
            ];
        } else {
            $resolved = $this->fallbackImageFromEnv($agentKey);
        }

        $this->cache[$cacheKey] = $resolved;
        return $resolved;
    }

    /**
     * Atajo: provider_key del texto. Usado por ChatbotServiceProvider para
     * decidir qué clase concreta instanciar.
     *
     * @param  string  $agentKey
     * @return string  'claude' | 'openai' | etc.
     */
    public function getTextProviderKey($agentKey)
    {
        $cfg = $this->getTextConfig($agentKey);
        return $cfg['provider'];
    }

    /**
     * Atajo: provider_key de la imagen.
     *
     * @param  string  $agentKey
     * @return string  'dalle3' | 'gpt-image-2' | 'nano-banana' | etc.
     */
    public function getImageProviderKey($agentKey)
    {
        $cfg = $this->getImageConfig($agentKey);
        return $cfg['provider'];
    }

    /**
     * Forzar reload (útil después de cambios en UI).
     *
     * @return void
     */
    public function clearCache()
    {
        $this->cache = [];
    }

    /*
    |--------------------------------------------------------------------------
    | Resolución de instancias concretas de provider
    |--------------------------------------------------------------------------
    */

    /**
     * Instanciar el provider de texto correcto para este agente, ya configurado.
     *
     * @param  string  $agentKey
     * @return \Modules\Marketing\Contracts\AIProviderInterface
     * @throws \InvalidArgumentException si el provider key no está soportado
     */
    public function resolveTextProvider($agentKey)
    {
        $cfg = $this->getTextConfig($agentKey);

        $providerCfg = [
            'api_key'    => $cfg['api_key'],
            'model'      => $cfg['model'],
            'base_url'   => $cfg['base_url'],
            'max_tokens' => $cfg['max_tokens'],
        ];

        switch ($cfg['provider']) {
            case 'claude':
                return new \Modules\Marketing\Services\AI\ClaudeProvider($providerCfg);
            case 'openai':
                return new \Modules\Marketing\Services\AI\OpenAIProvider($providerCfg);
            case 'gemini':
                return new \Modules\Marketing\Services\AI\GeminiProvider($providerCfg);
            default:
                throw new \InvalidArgumentException(
                    "Text provider no soportado: {$cfg['provider']}. Soportados: claude, openai, gemini."
                );
        }
    }

    /**
     * Instanciar el provider de imagen correcto para este agente, ya configurado.
     *
     * @param  string  $agentKey
     * @return \Modules\Marketing\Contracts\ImageProviderInterface
     * @throws \InvalidArgumentException si el provider key no está soportado
     */
    public function resolveImageProvider($agentKey)
    {
        $cfg = $this->getImageConfig($agentKey);

        $providerCfg = [
            'api_key'      => $cfg['api_key'],
            'model'        => $cfg['model'],
            'quality'      => $cfg['quality'],
            'format'       => $cfg['format'],
            'base_url'     => $cfg['base_url'],
            'aspect_ratio' => $cfg['aspect_ratio'],
        ];

        switch ($cfg['provider']) {
            case 'dalle3':
                return new \Modules\Marketing\Services\Image\DallE3Provider($providerCfg);
            case 'gpt-image-2':
                return new \Modules\Marketing\Services\Image\GPTImage2Provider($providerCfg);
            case 'nano-banana':
                return new \Modules\Marketing\Services\Image\NanoBananaProvider($providerCfg);
            default:
                throw new \InvalidArgumentException(
                    "Image provider no soportado: {$cfg['provider']}. Soportados: dalle3, gpt-image-2, nano-banana."
                );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Internos
    |--------------------------------------------------------------------------
    */

    /**
     * Cargar AgentConfig con providers, defensivo contra tabla inexistente.
     *
     * @param  string  $agentKey
     * @return AgentConfig|null
     */
    private function loadAgentConfig($agentKey)
    {
        try {
            return AgentConfig::forAgent($agentKey);
        } catch (\Exception $e) {
            // Tabla aún no migrada → fallback silencioso a .env
            Log::debug('AgentConfigService: tabla marketing_agent_config no disponible aún, usando .env', [
                'agent_key' => $agentKey,
                'error'     => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Fallback de texto leyendo del .env actual.
     *
     * @param  string  $agentKey
     * @return array
     */
    private function fallbackTextFromEnv($agentKey)
    {
        $providerKey = config('marketing.chatbot.ai_provider', 'claude');
        $providerCfg = config('marketing.chatbot.ai.' . $providerKey, []);

        // Temperatures sensatas por agente
        $tempByAgent = [
            'mkt01' => 0.7,
            'com02' => 0.3,
            'mkt03' => 0.8,
        ];
        $temp = isset($tempByAgent[$agentKey]) ? $tempByAgent[$agentKey] : 0.7;

        return [
            'source'      => 'env',
            'agent_key'   => $agentKey,
            'provider'    => $providerKey,
            'model'       => isset($providerCfg['model']) ? $providerCfg['model'] : null,
            'temperature' => $temp,
            'max_tokens'  => isset($providerCfg['max_tokens']) ? (int) $providerCfg['max_tokens'] : 1024,
            'base_url'    => isset($providerCfg['base_url']) ? $providerCfg['base_url'] : null,
            'api_key'     => isset($providerCfg['api_key']) ? $providerCfg['api_key'] : null,
        ];
    }

    /**
     * Fallback de imagen leyendo del .env actual.
     *
     * @param  string  $agentKey
     * @return array
     */
    private function fallbackImageFromEnv($agentKey)
    {
        $providerKey = config('marketing.chatbot.mkt03.image_provider', 'dalle3');
        $defaultModel = $providerKey === 'gpt-image-2'
            ? 'gpt-image-2'
            : ($providerKey === 'nano-banana' ? 'gemini-3.1-flash-image' : 'dall-e-3');
        $isNanoBanana = $providerKey === 'nano-banana';
        $configuredModel = config('marketing.chatbot.mkt03.image_model');

        return [
            'source'              => 'env',
            'agent_key'           => $agentKey,
            'provider'            => $providerKey,
            'model'               => $configuredModel ?: $defaultModel,
            'quality'             => config('marketing.chatbot.mkt03.image_quality', 'medium'),
            'format'              => config('marketing.chatbot.mkt03.image_format', 'png'),
            'aspect_ratio'        => '1:1',
            'auto_generate'       => (bool) config('marketing.chatbot.mkt03.auto_generate_image', false),
            'supports_image_edit' => $providerKey === 'gpt-image-2' || $isNanoBanana,
            'base_url'            => $isNanoBanana
                ? config('marketing.chatbot.ai.gemini.base_url')
                : null,
            'api_key'             => $isNanoBanana
                ? config('marketing.chatbot.ai.gemini.api_key')
                : config('marketing.chatbot.ai.openai.api_key'),
        ];
    }
}
