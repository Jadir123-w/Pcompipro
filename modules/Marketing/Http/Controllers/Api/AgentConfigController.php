<?php

namespace Modules\Marketing\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Validator;
use Modules\Marketing\Models\AgentConfig;
use Modules\Marketing\Models\AiProvider;
use Modules\Marketing\Services\AI\OpenAICompatibleApi;
use Modules\Marketing\Services\AI\OpenAICompatibleTextProvider;
use Modules\Marketing\Services\AgentConfigService;

/**
 * AgentConfigController — Gestión de configuración por agente y catálogo de providers.
 *
 * Endpoints:
 *   GET    /marketing/agents-config/providers              listado de providers (catálogo)
 *   PUT    /marketing/agents-config/providers/{id}         toggle enabled / update display
 *   GET    /marketing/agents-config/agents                 listado de configs por agente
 *   GET    /marketing/agents-config/agents/{key}           detalle config de un agente
 *   PUT    /marketing/agents-config/agents/{key}           actualizar config de un agente
 *   POST   /marketing/agents-config/agents/{key}/reset     restaurar defaults del seed
 */
class AgentConfigController extends Controller
{
    /** @var AgentConfigService */
    private $service;

    public function __construct(AgentConfigService $service)
    {
        $this->service = $service;
    }

    /*
    |--------------------------------------------------------------------------
    | Catálogo de Providers
    |--------------------------------------------------------------------------
    */

    /**
     * GET /marketing/agents-config/providers
     */
    public function listProviders(Request $request)
    {
        $query = AiProvider::query()->ordered();

        if ($request->filled('capability')) {
            $query->ofCapability($request->input('capability'));
        }
        if ($request->filled('enabled_only')) {
            $query->enabled();
        }

        $providers = $query->get();

        // Marcar cuáles tienen API key configurada
        $providers->each(function ($p) {
            $p->is_configured = $p->isConfigured();
        });

        return response()->json(['ok' => true, 'providers' => $providers]);
    }

    /**
     * POST /marketing/agents-config/providers
     */
    public function storeProvider(Request $request)
    {
        $v = Validator::make($request->all(), [
            'provider_key'             => ['required', 'string', 'max:40', 'regex:/^[a-z0-9][a-z0-9_-]{1,39}$/'],
            'display_name'             => 'required|string|max:100',
            'capability'               => 'required|in:text,image',
            'base_url'                 => 'required|string|max:255',
            'api_key'                  => 'required|string|max:4096',
            'default_model'            => 'required|string|max:100',
            'max_tokens_limit'         => 'nullable|integer|min:1|max:128000',
            'supported_models'         => 'nullable|array',
            'supported_models.*'       => 'string|max:100',
            'supported_qualities'      => 'nullable|array',
            'supported_qualities.*'    => 'string|max:20',
            'supported_aspect_ratios'  => 'nullable|array',
            'supported_aspect_ratios.*'=> 'string|max:10',
            'description'              => 'nullable|string',
            'pricing_notes'            => 'nullable|string',
        ], [
            'provider_key.regex' => 'El identificador debe tener de 2 a 40 caracteres: minúsculas, números, guion o guion bajo; no uses puntos ni espacios (por ejemplo: openrouter).',
        ]);
        if ($v->fails()) {
            return response()->json(['ok' => false, 'errors' => $v->errors()], 422);
        }

        if (AiProvider::byKey($request->input('provider_key'))) {
            return response()->json(['ok' => false, 'message' => 'Ese identificador de proveedor ya existe.'], 422);
        }

        try {
            $baseUrl = OpenAICompatibleApi::validateBaseUrl($request->input('base_url'));
            $apiKey = trim($request->input('api_key'));
            $models = $this->modelsIncludingDefault(
                $request->input('supported_models', []),
                $request->input('default_model')
            );

            $provider = AiProvider::create([
                'provider_key' => $request->input('provider_key'),
                'adapter_type' => 'openai_compatible',
                'api_key_encrypted' => Crypt::encryptString($apiKey),
                'capability' => $request->input('capability'),
                'display_name' => $request->input('display_name'),
                'description' => $request->input('description'),
                'default_model' => $request->input('default_model'),
                'max_tokens_limit' => $request->input('max_tokens_limit')
                    ? (int) $request->input('max_tokens_limit')
                    : ($request->input('provider_key') === 'groq' && $request->input('capability') === 'text' ? 4096 : null),
                'supported_models' => $models,
                'supports_image_edit' => false,
                'supported_qualities' => $request->input('capability') === 'image'
                    ? $request->input('supported_qualities', [])
                    : null,
                'supported_aspect_ratios' => $request->input('capability') === 'image'
                    ? $request->input('supported_aspect_ratios', [])
                    : null,
                'base_url' => $baseUrl,
                'api_key_env_var' => null,
                'enabled' => true,
                'sort_order' => (int) AiProvider::max('sort_order') + 10,
                'pricing_notes' => $request->input('pricing_notes'),
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            report($e);
            return response()->json(['ok' => false, 'message' => 'No se pudo guardar el proveedor.'], 500);
        }

        $this->service->clearCache();
        $provider->is_configured = $provider->isConfigured();
        return response()->json(['ok' => true, 'provider' => $provider], 201);
    }

    /**
     * POST /marketing/agents-config/providers/models
     */
    public function listProviderModels(Request $request)
    {
        $v = Validator::make($request->all(), [
            'provider_id' => 'nullable|integer',
            'base_url' => 'nullable|string|max:255',
            'api_key' => 'nullable|string|max:4096',
        ]);
        if ($v->fails()) {
            return response()->json(['ok' => false, 'errors' => $v->errors()], 422);
        }

        try {
            list($baseUrl, $apiKey) = $this->resolveProviderCredentials($request);
            $models = OpenAICompatibleApi::listModels($baseUrl, $apiKey);
            return response()->json(['ok' => true, 'models' => $models]);
        } catch (\Exception $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * POST /marketing/agents-config/providers/test
     * Solo prueba proveedores de texto; no genera imágenes.
     */
    public function testProvider(Request $request)
    {
        $v = Validator::make($request->all(), [
            'provider_id' => 'nullable|integer',
            'capability' => 'required|in:text',
            'base_url' => 'nullable|string|max:255',
            'api_key' => 'nullable|string|max:4096',
            'model' => 'required|string|max:100',
        ]);
        if ($v->fails()) {
            return response()->json(['ok' => false, 'errors' => $v->errors()], 422);
        }

        try {
            $provider = $this->resolveTextProviderForTest($request);
            $response = $provider->sendMessage(
                'Responde de forma muy breve.',
                [['role' => 'user', 'content' => 'Hola']],
                ['model' => $request->input('model'), 'max_tokens' => 128, 'temperature' => 0]
            );
            return response()->json(['ok' => true, 'message' => 'Conexión correcta.', 'response' => substr($response, 0, 300)]);
        } catch (\Exception $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * PUT /marketing/agents-config/providers/{id}
     */
    public function updateProvider(Request $request, $id)
    {
        $provider = AiProvider::findOrFail($id);

        $v = Validator::make($request->all(), [
            'enabled'        => 'nullable',
            'sort_order'     => 'nullable|integer',
            'display_name'   => 'nullable|string|max:100',
            'description'    => 'nullable|string',
            'pricing_notes'  => 'nullable|string',
            'base_url'       => 'nullable|string|max:255',
            'api_key'        => 'nullable|string|max:4096',
            'default_model'  => 'nullable|string|max:100',
            'max_tokens_limit' => 'nullable|integer|min:1|max:128000',
            'supported_models' => 'nullable|array',
            'supported_models.*' => 'string|max:100',
            'supported_qualities' => 'nullable|array',
            'supported_qualities.*' => 'string|max:20',
            'supported_aspect_ratios' => 'nullable|array',
            'supported_aspect_ratios.*' => 'string|max:10',
        ]);

        if ($v->fails()) {
            return response()->json(['ok' => false, 'errors' => $v->errors()], 422);
        }

        $updates = [];
        if ($request->has('enabled')) {
            $updates['enabled'] = filter_var($request->input('enabled'), FILTER_VALIDATE_BOOLEAN);
        }
        foreach (['sort_order', 'display_name', 'description', 'pricing_notes'] as $field) {
            if ($request->has($field)) {
                $updates[$field] = $request->input($field);
            }
        }
        if ($provider->adapter_type === 'openai_compatible') {
            try {
                if ($request->filled('base_url')) {
                    $updates['base_url'] = OpenAICompatibleApi::validateBaseUrl($request->input('base_url'));
                }
                if ($request->filled('api_key')) {
                    $updates['api_key_encrypted'] = Crypt::encryptString(trim($request->input('api_key')));
                }
            } catch (\InvalidArgumentException $e) {
                return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
            }
            foreach (['default_model', 'max_tokens_limit', 'supported_models', 'supported_qualities', 'supported_aspect_ratios'] as $field) {
                if ($request->has($field)) {
                    $updates[$field] = $request->input($field);
                }
            }
            if (isset($updates['default_model'])) {
                $updates['supported_models'] = $this->modelsIncludingDefault(
                    isset($updates['supported_models']) && is_array($updates['supported_models'])
                        ? $updates['supported_models']
                        : (is_array($provider->supported_models) ? $provider->supported_models : []),
                    $updates['default_model']
                );
            }
        }

        $provider->update($updates);
        $this->service->clearCache();

        $provider->is_configured = $provider->isConfigured();

        return response()->json(['ok' => true, 'provider' => $provider]);
    }

    private function modelsIncludingDefault(array $models, $defaultModel)
    {
        $models[] = $defaultModel;
        $models = array_filter($models, function ($model) {
            return is_string($model) && trim($model) !== '';
        });
        return array_values(array_unique($models));
    }

    private function resolveProviderCredentials(Request $request, $textTest = false)
    {
        $provider = null;
        if ($request->filled('provider_id')) {
            $provider = AiProvider::findOrFail($request->input('provider_id'));
            if ($provider->adapter_type !== 'openai_compatible') {
                throw new \InvalidArgumentException('La acción solo está disponible para proveedores OpenAI-compatible.');
            }
            if ($textTest && !$provider->isText()) {
                throw new \InvalidArgumentException('La prueba “Hola” solo está disponible para proveedores de texto.');
            }
        }

        $baseUrl = $request->input('base_url') ?: ($provider ? $provider->base_url : null);
        $apiKey = trim((string) $request->input('api_key'));
        if ($apiKey === '' && $provider) {
            $apiKey = (string) $provider->getApiKey();
        }
        if (!$baseUrl || !$apiKey) {
            throw new \InvalidArgumentException('Ingresa la URL base y la API key para continuar.');
        }

        return [
            OpenAICompatibleApi::validateBaseUrl($baseUrl),
            $apiKey,
            $provider ? $provider->provider_key : 'text-provider-check',
        ];
    }

    private function resolveTextProviderForTest(Request $request)
    {
        if (!$request->filled('provider_id')) {
            list($baseUrl, $apiKey, $providerKey) = $this->resolveProviderCredentials($request, true);
            return new OpenAICompatibleTextProvider([
                'provider_key' => $providerKey,
                'base_url' => $baseUrl,
                'api_key' => $apiKey,
                'model' => $request->input('model'),
            ]);
        }

        $provider = AiProvider::findOrFail($request->input('provider_id'));
        if (!$provider->isText()) {
            throw new \InvalidArgumentException('La prueba “Hola” solo está disponible para proveedores de texto.');
        }

        $model = $request->input('model') ?: $provider->default_model;
        if ($provider->adapter_type === 'openai_compatible') {
            list($baseUrl, $apiKey) = $this->resolveProviderCredentials($request, true);
            return new OpenAICompatibleTextProvider([
                'provider_key' => $provider->provider_key,
                'base_url' => $baseUrl,
                'api_key' => $apiKey,
                'model' => $model,
            ]);
        }

        $apiKey = $provider->getApiKey();
        if (empty($apiKey)) {
            throw new \InvalidArgumentException('El proveedor no tiene API key configurada.');
        }

        $config = [
            'api_key' => $apiKey,
            'model' => $model,
            'base_url' => $provider->base_url,
            'max_tokens' => 128,
        ];
        switch ($provider->provider_key) {
            case 'claude':
                return new \Modules\Marketing\Services\AI\ClaudeProvider($config);
            case 'openai':
                return new \Modules\Marketing\Services\AI\OpenAIProvider($config);
            case 'gemini':
                return new \Modules\Marketing\Services\AI\GeminiProvider($config);
            default:
                throw new \InvalidArgumentException('Este proveedor legacy no tiene una prueba de texto implementada.');
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Configuración por agente
    |--------------------------------------------------------------------------
    */

    /**
     * GET /marketing/agents-config/agents
     */
    public function listAgents()
    {
        $agents = AgentConfig::with(['textProvider', 'imageProvider'])
            ->orderBy('id')
            ->get();

        return response()->json(['ok' => true, 'agents' => $agents]);
    }

    /**
     * GET /marketing/agents-config/agents/{key}
     */
    public function showAgent($key)
    {
        $agent = AgentConfig::with(['textProvider', 'imageProvider'])
            ->where('agent_key', $key)
            ->firstOrFail();

        // Resolución efectiva (lo que realmente usaría el motor en runtime)
        $textCfg  = $this->service->getTextConfig($key);
        $imageCfg = $this->service->getImageConfig($key);

        return response()->json([
            'ok'             => true,
            'agent'          => $agent,
            'resolved_text'  => $this->maskApiKey($textCfg),
            'resolved_image' => $this->maskApiKey($imageCfg),
        ]);
    }

    /**
     * PUT /marketing/agents-config/agents/{key}
     */
    public function updateAgent(Request $request, $key)
    {
        $agent = AgentConfig::where('agent_key', $key)->firstOrFail();

        // NOTA: NO usamos la regla `exists:marketing_ai_providers,id` porque por default
        // Laravel intenta ese check contra la conexión system (dberp), pero la tabla vive
        // en tenant. Hacemos la validación de existencia manualmente abajo via Eloquent
        // (que sí respeta la conexión tenant del modelo).
        $v = Validator::make($request->all(), [
            'text_provider_id'           => 'nullable|integer',
            'text_model_override'        => 'nullable|string|max:100',
            'text_temperature'           => 'nullable|numeric|min:0|max:2',
            'text_max_tokens'            => 'nullable|integer|min:1|max:128000',
            'image_provider_id'          => 'nullable|integer',
            'image_model_override'       => 'nullable|string|max:100',
            'image_quality'              => 'nullable|string|max:20',
            'image_format'               => 'nullable|string|in:png,jpeg,webp',
            'image_aspect_ratio_default' => 'nullable|string|max:10',
            'image_auto_generate'        => 'nullable',
            'enabled'                    => 'nullable',
        ]);

        if ($v->fails()) {
            return response()->json(['ok' => false, 'errors' => $v->errors()], 422);
        }

        // Validación cruzada + existencia: si text_provider_id, debe existir y ser de capability=text
        if ($request->filled('text_provider_id')) {
            $tp = AiProvider::find($request->input('text_provider_id'));
            if (!$tp) {
                return response()->json([
                    'ok'      => false,
                    'message' => 'text_provider_id no existe en el catálogo de providers',
                ], 422);
            }
            if (!$tp->isText()) {
                return response()->json([
                    'ok'      => false,
                    'message' => 'text_provider_id debe ser un provider con capability=text',
                ], 422);
            }
        }
        if ($request->filled('image_provider_id')) {
            $ip = AiProvider::find($request->input('image_provider_id'));
            if (!$ip) {
                return response()->json([
                    'ok'      => false,
                    'message' => 'image_provider_id no existe en el catálogo de providers',
                ], 422);
            }
            if (!$ip->isImage()) {
                return response()->json([
                    'ok'      => false,
                    'message' => 'image_provider_id debe ser un provider con capability=image',
                ], 422);
            }
        }

        $updates = [];
        $simpleFields = [
            'text_provider_id', 'text_model_override', 'text_temperature', 'text_max_tokens',
            'image_provider_id', 'image_model_override', 'image_quality', 'image_format',
            'image_aspect_ratio_default',
        ];
        foreach ($simpleFields as $f) {
            if ($request->has($f)) {
                $val = $request->input($f);
                $updates[$f] = ($val === '' || $val === 'null') ? null : $val;
            }
        }
        if ($request->has('image_auto_generate')) {
            $updates['image_auto_generate'] = filter_var($request->input('image_auto_generate'), FILTER_VALIDATE_BOOLEAN);
        }
        if ($request->has('enabled')) {
            $updates['enabled'] = filter_var($request->input('enabled'), FILTER_VALIDATE_BOOLEAN);
        }

        $agent->update($updates);
        $this->service->clearCache();

        // Reload con providers
        $agent = AgentConfig::with(['textProvider', 'imageProvider'])
            ->where('agent_key', $key)
            ->first();

        return response()->json(['ok' => true, 'agent' => $agent]);
    }

    /**
     * POST /marketing/agents-config/agents/{key}/reset
     */
    public function resetAgent($key)
    {
        $agent = AgentConfig::where('agent_key', $key)->firstOrFail();

        // Defaults equivalentes al seed inicial
        $defaults = $this->getDefaultsForAgent($key);

        $agent->update($defaults);
        $this->service->clearCache();

        $agent = AgentConfig::with(['textProvider', 'imageProvider'])
            ->where('agent_key', $key)
            ->first();

        return response()->json(['ok' => true, 'agent' => $agent, 'message' => 'Configuración restaurada']);
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    /**
     * Enmascarar API key antes de retornarla al frontend.
     */
    private function maskApiKey(array $cfg)
    {
        if (!empty($cfg['api_key'])) {
            $key = $cfg['api_key'];
            $cfg['api_key'] = strlen($key) > 14
                ? substr($key, 0, 10) . '...' . substr($key, -4)
                : '***';
            $cfg['api_key_set'] = true;
        } else {
            $cfg['api_key_set'] = false;
        }
        return $cfg;
    }

    /**
     * Defaults por agente (deben coincidir con el seed de migrations).
     */
    private function getDefaultsForAgent($key)
    {
        $claudeId    = optional(AiProvider::byKey('claude'))->id;
        $gptImage2Id = optional(AiProvider::byKey('gpt-image-2'))->id;

        $defaults = [
            'mkt01' => [
                'text_provider_id'           => $claudeId,
                'text_model_override'        => null,
                'text_temperature'           => 0.70,
                'text_max_tokens'            => 1024,
                'image_provider_id'          => null,
                'image_model_override'       => null,
                'image_quality'              => null,
                'image_format'               => null,
                'image_aspect_ratio_default' => null,
                'image_auto_generate'        => false,
            ],
            'com02' => [
                'text_provider_id'           => $claudeId,
                'text_model_override'        => null,
                'text_temperature'           => 0.30,
                'text_max_tokens'            => 2000,
                'image_provider_id'          => null,
                'image_model_override'       => null,
                'image_quality'              => null,
                'image_format'               => null,
                'image_aspect_ratio_default' => null,
                'image_auto_generate'        => false,
            ],
            'mkt03' => [
                'text_provider_id'           => $claudeId,
                'text_model_override'        => null,
                'text_temperature'           => 0.80,
                'text_max_tokens'            => 2000,
                'image_provider_id'          => $gptImage2Id,
                'image_model_override'       => null,
                'image_quality'              => 'medium',
                'image_format'               => 'png',
                'image_aspect_ratio_default' => '1:1',
                'image_auto_generate'        => false,
            ],
        ];

        return isset($defaults[$key]) ? $defaults[$key] : [];
    }
}
