<?php

namespace Modules\Marketing\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
use Modules\Marketing\Models\AgentConfig;
use Modules\Marketing\Models\AiProvider;
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

        $provider->update($updates);
        $this->service->clearCache();

        $provider->is_configured = $provider->isConfigured();

        return response()->json(['ok' => true, 'provider' => $provider]);
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
