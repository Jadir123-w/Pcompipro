<?php

namespace Modules\Marketing\Services;

use Illuminate\Support\Facades\Log;
use Modules\Marketing\Contracts\AIProviderInterface;
use Modules\Marketing\Exceptions\AIProviderException;
use Modules\Marketing\Models\AgentConfig;
use Modules\Marketing\Models\ChatbotCatalog;
use Modules\Marketing\Models\ChatbotFaq;

/**
 * AIConversationEngine
 *
 * Motor central de conversación con IA del agente MKT-01.
 *
 * Responsabilidades:
 * - Construir el prompt completo (system prompt + catálogos + historial)
 * - Llamar al proveedor de IA (Claude/OpenAI) — resuelto vía AgentConfigService
 * - Parsear y validar la respuesta JSON estructurada
 * - Reintentar si la respuesta no es JSON válido
 *
 * Provider y modelo se resuelven en runtime desde marketing_agent_config (agent_key=mkt01),
 * con fallback transparente al .env si la tabla aún no existe.
 */
class AIConversationEngine
{
    /** @var AgentConfigService */
    private $configService;

    /** @var string */
    private $agentKey = AgentConfig::AGENT_MKT01;

    /** @var string */
    private $systemPromptTemplate;

    /** @var AIProviderInterface|null Cache por instancia */
    private $provider = null;

    public function __construct(AgentConfigService $configService)
    {
        $this->configService = $configService;
        $this->systemPromptTemplate = $this->loadSystemPrompt();
    }

    /**
     * Resolver el provider en runtime (lazy + cached).
     * Si la config del agente cambia (UI), llamar $this->reloadProvider().
     *
     * @return AIProviderInterface
     */
    private function getProvider()
    {
        if ($this->provider === null) {
            $this->provider = $this->configService->resolveTextProvider($this->agentKey);
        }
        return $this->provider;
    }

    /**
     * Forzar reload del provider (útil después de cambios en UI).
     *
     * @return void
     */
    public function reloadProvider()
    {
        $this->provider = null;
        $this->configService->clearCache();
    }

    /**
     * Procesar un mensaje del usuario y obtener respuesta estructurada.
     *
     * @param  array  $history   Historial de mensajes [['role' => 'user|assistant', 'content' => '...']]
     * @param  array  $context   Contexto adicional (canal, page_url, utms, etc.)
     * @return array             Respuesta parseada del LLM (JSON decodificado)
     */
    public function processMessage(array $history, array $context = [])
    {
        // 1. Construir system prompt con catálogos inyectados
        $systemPrompt = $this->buildSystemPrompt($context);

        // 2. Llamar al LLM
        try {
            $rawResponse = $this->getProvider()->sendMessage($systemPrompt, $history);
        } catch (AIProviderException $e) {
            Log::error('AIConversationEngine: error del proveedor', [
                'provider' => $e->getProvider(),
                'message'  => $e->getMessage(),
            ]);

            return $this->fallbackResponse('Lo siento, tengo un problema técnico en este momento. ¿Podrías intentar de nuevo en unos segundos?');
        }

        // 3. Parsear JSON
        $parsed = $this->parseResponse($rawResponse);

        if ($parsed === null) {
            // Reintento: pedir al LLM que corrija su respuesta
            Log::warning('AIConversationEngine: respuesta no es JSON válido, reintentando', [
                'raw_response' => substr($rawResponse, 0, 500),
            ]);

            $parsed = $this->retryWithCorrection($systemPrompt, $history, $rawResponse);
        }

        if ($parsed === null) {
            Log::error('AIConversationEngine: segundo intento falló, usando fallback');
            return $this->fallbackResponse('Disculpa, no pude procesar bien tu mensaje. ¿Podrías reformularlo?');
        }

        // 4. Validar y completar campos faltantes
        $parsed = $this->normalizeResponse($parsed);

        // 5. Log de la respuesta procesada
        Log::info('AIConversationEngine: respuesta procesada', [
            'intent'       => $parsed['intent'],
            'confidence'   => $parsed['confidence'],
            'phase'        => $parsed['conversation_state']['phase'],
            'should_register' => $parsed['actions']['should_register_lead'],
            'provider'     => $this->getProvider()->getProviderName(),
            'model'        => $this->getProvider()->getModelName(),
        ]);

        return $parsed;
    }

    /**
     * Cargar el system prompt desde archivo.
     *
     * @return string
     */
    private function loadSystemPrompt()
    {
        // Usar __DIR__ en vez de module_path() que falla en este proyecto
        $path = __DIR__ . '/../Prompts/system-prompt.md';

        if (!file_exists($path)) {
            // Fallback: intentar ruta alternativa
            $path = base_path('Modules/Marketing/Prompts/system-prompt.md');
        }

        if (!file_exists($path)) {
            Log::error('AIConversationEngine: system-prompt.md no encontrado');
            return 'Eres el asistente virtual de COMPIPRO. Responde siempre en JSON válido.';
        }

        return file_get_contents($path);
    }

    /**
     * Construir el system prompt final con catálogos inyectados dinámicamente.
     *
     * @param  array  $context
     * @return string
     */
    private function buildSystemPrompt(array $context = [])
    {
        $prompt = $this->systemPromptTemplate;

        // Inyectar catálogos disponibles
        $catalogs = $this->getCatalogsForPrompt();
        $prompt = str_replace('{{CATALOGS}}', $catalogs, $prompt);

        // Inyectar FAQs activas
        $faqs = $this->getFaqsForPrompt();
        $prompt = str_replace('{{FAQS}}', $faqs, $prompt);

        // Inyectar contexto del canal si es relevante
        $channel = isset($context['channel']) ? $context['channel'] : 'web';
        if ($channel === 'whatsapp') {
            $prompt .= "\n\n# CONTEXTO DE CANAL\nEstás operando en WhatsApp. El número del usuario ya es su contacto, no necesitas pedirlo. Usa mensajes aún más cortos.";
        }

        return $prompt;
    }

    /**
     * Obtener FAQs activas formateadas para el system prompt.
     *
     * @return string
     */
    private function getFaqsForPrompt()
    {
        try {
            $faqs = ChatbotFaq::getForPrompt();
        } catch (\Exception $e) {
            Log::warning('AIConversationEngine: no se pudieron cargar FAQs', [
                'error' => $e->getMessage(),
            ]);
            $faqs = '';
        }

        if ($faqs === '' || $faqs === null) {
            return "_(No hay FAQs configuradas todavía. Si el usuario pregunta sobre tiempos, mínimos, pagos o políticas, ofrécele derivar con una ejecutiva.)_";
        }

        return $faqs;
    }

    /**
     * Obtener catálogos formateados para el system prompt.
     *
     * @return string
     */
    private function getCatalogsForPrompt()
    {
        try {
            $catalogs = ChatbotCatalog::getForPrompt();
        } catch (\Exception $e) {
            Log::warning('AIConversationEngine: no se pudieron cargar catálogos', [
                'error' => $e->getMessage(),
            ]);
            $catalogs = [];
        }

        if (empty($catalogs)) {
            return "No hay catálogos configurados actualmente. Si el usuario pide catálogo, indícale que pronto estarán disponibles.";
        }

        $lines = [];
        foreach ($catalogs as $cat) {
            $type = $cat['is_general'] ? '(GENERAL)' : '';
            $lines[] = "- {$cat['line']} {$type}: {$cat['url']}";
        }

        return implode("\n", $lines);
    }

    /**
     * Parsear la respuesta raw del LLM como JSON.
     *
     * @param  string  $raw
     * @return array|null
     */
    private function parseResponse($raw)
    {
        // Limpiar posibles artefactos
        $clean = trim($raw);

        // Remover bloques markdown de código si el LLM los agrega
        if (substr($clean, 0, 7) === '```json') {
            $clean = preg_replace('/^```json\s*/i', '', $clean);
            $clean = preg_replace('/\s*```$/', '', $clean);
        } elseif (substr($clean, 0, 3) === '```') {
            $clean = preg_replace('/^```\s*/', '', $clean);
            $clean = preg_replace('/\s*```$/', '', $clean);
        }

        $clean = trim($clean);

        $decoded = json_decode($clean, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }

        // Verificar que tiene el campo mínimo obligatorio
        if (!isset($decoded['reply'])) {
            return null;
        }

        return $decoded;
    }

    /**
     * Reintentar: pedir al LLM que corrija su respuesta.
     *
     * @param  string  $systemPrompt
     * @param  array   $history
     * @param  string  $badResponse
     * @return array|null
     */
    private function retryWithCorrection($systemPrompt, $history, $badResponse)
    {
        // Agregar la respuesta mala como "assistant" y una corrección como "user"
        $correctedHistory = $history;
        $correctedHistory[] = [
            'role'    => 'assistant',
            'content' => $badResponse,
        ];
        $correctedHistory[] = [
            'role'    => 'user',
            'content' => 'Tu respuesta anterior no es un JSON válido. Por favor, responde SOLAMENTE con el JSON válido según el esquema indicado en las instrucciones. Sin texto adicional, sin bloques de código markdown. Solo el JSON puro.',
        ];

        try {
            $rawRetry = $this->getProvider()->sendMessage($systemPrompt, $correctedHistory, [
                'temperature' => 0.2, // Más determinista en el reintento
            ]);

            return $this->parseResponse($rawRetry);
        } catch (\Exception $e) {
            Log::error('AIConversationEngine: reintento también falló', [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Normalizar la respuesta: completar campos faltantes con defaults.
     *
     * @param  array  $parsed
     * @return array
     */
    private function normalizeResponse(array $parsed)
    {
        // Asegurar estructura completa de entities_extracted
        $defaultEntities = [
            'nombre_contacto'       => null,
            'empresa'               => null,
            'rubro'                 => null,
            'correo'                => null,
            'celular'               => null,
            'ciudad_entrega'        => null,
            'fecha_objetivo'        => null,
            'evento_feria'          => null,
            'presupuesto_mencionado' => null,
            'items_solicitados'     => [],
            'interes_resumen'       => null,
        ];

        $parsed['entities_extracted'] = array_merge(
            $defaultEntities,
            isset($parsed['entities_extracted']) ? $parsed['entities_extracted'] : []
        );

        // Asegurar estructura completa de conversation_state
        $defaultState = [
            'phase'                 => 'exploracion',
            'missing_fields'        => [],
            'has_contact'           => false,
            'has_commercial_intent' => false,
            'has_useful_data'       => false,
        ];

        $parsed['conversation_state'] = array_merge(
            $defaultState,
            isset($parsed['conversation_state']) ? $parsed['conversation_state'] : []
        );

        // Asegurar estructura completa de actions
        $defaultActions = [
            'should_register_lead' => false,
            'ready_to_convert'     => false,
            'send_catalog'         => null,
            'escalate_to_human'    => false,
            'close_conversation'   => false,
        ];

        $parsed['actions'] = array_merge(
            $defaultActions,
            isset($parsed['actions']) ? $parsed['actions'] : []
        );

        // Defaults para campos top-level
        $parsed['intent']       = isset($parsed['intent']) ? $parsed['intent'] : 'no_entendido';
        $parsed['confidence']   = isset($parsed['confidence']) ? $parsed['confidence'] : 0.5;
        $parsed['lead_summary'] = isset($parsed['lead_summary']) ? $parsed['lead_summary'] : null;

        // Validación de seguridad: should_register_lead solo si hay los 3 criterios
        if ($parsed['actions']['should_register_lead']) {
            $state = $parsed['conversation_state'];
            if (!$state['has_contact'] || !$state['has_commercial_intent'] || !$state['has_useful_data']) {
                $parsed['actions']['should_register_lead'] = false;
                Log::info('AIConversationEngine: LLM quiso registrar sin cumplir los 3 criterios, corregido a false');
            }
        }

        return $parsed;
    }

    /**
     * Respuesta de fallback cuando el LLM falla.
     *
     * @param  string  $message
     * @return array
     */
    private function fallbackResponse($message)
    {
        return [
            'reply'      => $message,
            'intent'     => 'no_entendido',
            'confidence' => 0.0,
            'entities_extracted' => [
                'nombre_contacto'       => null,
                'empresa'               => null,
                'rubro'                 => null,
                'correo'                => null,
                'celular'               => null,
                'ciudad_entrega'        => null,
                'fecha_objetivo'        => null,
                'evento_feria'          => null,
                'presupuesto_mencionado' => null,
                'items_solicitados'     => [],
                'interes_resumen'       => null,
            ],
            'conversation_state' => [
                'phase'                 => 'exploracion',
                'missing_fields'        => [],
                'has_contact'           => false,
                'has_commercial_intent' => false,
                'has_useful_data'       => false,
            ],
            'actions' => [
                'should_register_lead' => false,
                'ready_to_convert'     => false,
                'send_catalog'         => null,
                'escalate_to_human'    => false,
                'close_conversation'   => false,
            ],
            'lead_summary' => null,
            '_fallback'    => true,
        ];
    }
}
