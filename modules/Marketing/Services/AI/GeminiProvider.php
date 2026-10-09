<?php

namespace Modules\Marketing\Services\AI;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;
use Modules\Marketing\Contracts\AIProviderInterface;
use Modules\Marketing\Exceptions\AIProviderException;

/**
 * GeminiProvider
 *
 * Implementación del proveedor de IA usando Google Gemini API
 * (Generative Language API — modo "API key" sin OAuth).
 *
 * Endpoint:
 *   POST https://generativelanguage.googleapis.com/v1beta/models/{model}:generateContent?key={API_KEY}
 *
 * Diferencias vs Anthropic/OpenAI:
 *   - El system prompt va en un campo separado `systemInstruction`, no como
 *     mensaje role=system.
 *   - Los mensajes usan `contents[].role` con valores 'user' o 'model'
 *     (no 'assistant'). Mapeamos 'assistant' → 'model'.
 *   - Cada mensaje es `{role, parts: [{text}]}`.
 *   - La API key viaja como query string `?key=`, NO como header Authorization.
 *   - JSON mode: `generationConfig.responseMimeType = "application/json"`.
 *     Lo activamos siempre porque el system prompt MKT-01 exige JSON.
 *
 * Modelos soportados (al 2026-07):
 *   - gemini-3.6-flash        (default, GA — línea flash rápida y estructurada, 1M de contexto)
 *   - gemini-3.5-flash-lite   (más barato, para volumen)
 *   - gemini-flash-latest     (alias móvil al flash más nuevo; escape ante retiros)
 *
 * ⚠️ La línea 2.x (gemini-2.5-*, gemini-2.0-flash) fue retirada por Google
 *    (404 "no longer available to new users"). No usar.
 *
 * ⚠️ Gemini 3.x trae "thinking" activado por defecto y consume del
 *    maxOutputTokens; sendMessage() lo baja a 'minimal' para no truncar la
 *    respuesta (ver thinkingConfig).
 *
 * Uso:
 *   new GeminiProvider(['api_key' => '...', 'model' => 'gemini-3.6-flash'])
 */
class GeminiProvider implements AIProviderInterface
{
    /** @var string */
    private $apiKey;

    /** @var string */
    private $model;

    /** @var string */
    private $baseUrl;

    /** @var int */
    private $maxTokens;

    /** @var Client */
    private $client;

    /**
     * @param  array  $config  Override opcional. Soporta keys: api_key, model, base_url, max_tokens.
     *                         Si no se pasa, lee de marketing.chatbot.ai.gemini.
     */
    public function __construct(array $config = [])
    {
        // api_key sigue del .env (es secret)
        $this->apiKey    = isset($config['api_key'])    ? $config['api_key']    : config('marketing.chatbot.ai.gemini.api_key');
        // model + max_tokens: prioridad config explícito → BD → config legacy → default
        $this->model     = isset($config['model'])      ? $config['model']      : self::resolveSettingFromDb('chatbot_gemini_model',      'model',      'gemini-3.6-flash');
        $this->baseUrl   = isset($config['base_url'])   ? $config['base_url']   : config('marketing.chatbot.ai.gemini.base_url', 'https://generativelanguage.googleapis.com/v1beta/models');
        $this->maxTokens = isset($config['max_tokens']) ? (int) $config['max_tokens'] : (int) self::resolveSettingFromDb('chatbot_gemini_max_tokens', 'max_tokens', 1024);

        if (empty($this->apiKey)) {
            throw new AIProviderException(
                'API key no configurada. Configura CHATBOT_GEMINI_API_KEY en .env',
                'gemini'
            );
        }

        $this->client = new Client([
            // 90s alcanza para respuestas largas (análisis de patrones, etc.).
            // Con 30s se cortaba el GridPatternAnalyzer al procesar 100+ ítems.
            'timeout'         => 90,
            'connect_timeout' => 10,
        ]);
    }

    /**
     * @param  string  $systemPrompt
     * @param  array   $messages
     * @param  array   $options
     * @return string
     */
    public function sendMessage($systemPrompt, array $messages, array $options = [])
    {
        $model = isset($options['model']) ? $options['model'] : $this->model;
        $maxTokens = isset($options['max_tokens']) ? (int) $options['max_tokens'] : $this->maxTokens;
        $temperature = isset($options['temperature']) ? (float) $options['temperature'] : 0.4;

        // El system prompt MKT-01 exige JSON; activamos JSON mode siempre que se
        // pueda. Los modelos gemini-2.0+ y 2.5+ soportan responseMimeType.
        $jsonMode = !isset($options['json_mode']) || $options['json_mode'] !== false;

        // S2 — multimodal: si hay imágenes, se inyectan en el primer user message
        $images = isset($options['images']) && is_array($options['images']) ? $options['images'] : [];

        $payload = [
            'contents'         => $this->formatMessages($messages, $images),
            'systemInstruction' => [
                'parts' => [
                    ['text' => $systemPrompt],
                ],
            ],
            'generationConfig' => [
                'temperature'     => $temperature,
                'maxOutputTokens' => $maxTokens,
            ],
        ];

        if ($jsonMode) {
            $payload['generationConfig']['responseMimeType'] = 'application/json';
        }

        // Gemini 3.x trae "thinking" activado por defecto (nivel medium). Ese
        // razonamiento se factura como salida y consume del mismo
        // maxOutputTokens, por lo que puede truncar la respuesta — crítico para
        // el JSON del cotizador COM-02, que corre con un tope de 2048. Lo
        // bajamos a 'minimal'. Se puede subir por llamada con
        // $options['thinking_level'] (minimal|low|medium|high).
        if (self::modelSupportsThinkingLevel($model)) {
            $thinkingLevel = isset($options['thinking_level']) ? $options['thinking_level'] : 'minimal';
            $payload['generationConfig']['thinkingConfig'] = [
                'thinkingLevel' => $thinkingLevel,
            ];
        }

        $url = rtrim($this->baseUrl, '/') . '/' . $model . ':generateContent?key=' . urlencode($this->apiKey);

        $startTime = microtime(true);

        try {
            $response = $this->client->post($url, [
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'json' => $payload,
            ]);

            $duration = round((microtime(true) - $startTime) * 1000);
            $statusCode = $response->getStatusCode();
            $body = json_decode($response->getBody()->getContents(), true);

            if ($statusCode < 200 || $statusCode >= 300) {
                Log::error('Gemini API error', [
                    'status'   => $statusCode,
                    'body'     => $body,
                    'duration' => $duration . 'ms',
                ]);

                $errorMsg = isset($body['error']['message']) ? $body['error']['message'] : 'Unknown error';

                throw new AIProviderException(
                    'Error HTTP ' . $statusCode . ': ' . $errorMsg,
                    'gemini',
                    $body,
                    $statusCode
                );
            }

            $text = $this->extractText($body);

            $promptTokens     = isset($body['usageMetadata']['promptTokenCount']) ? $body['usageMetadata']['promptTokenCount'] : 0;
            $candidatesTokens = isset($body['usageMetadata']['candidatesTokenCount']) ? $body['usageMetadata']['candidatesTokenCount'] : 0;
            $finishReason     = isset($body['candidates'][0]['finishReason']) ? $body['candidates'][0]['finishReason'] : null;

            Log::info('Gemini API call', [
                'model'         => $model,
                'input_tokens'  => $promptTokens,
                'output_tokens' => $candidatesTokens,
                'duration_ms'   => $duration,
                'finish_reason' => $finishReason,
            ]);

            return $text;

        } catch (AIProviderException $e) {
            throw $e;
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $responseBody = null;
            $statusCode = 0;
            if ($e->hasResponse()) {
                $statusCode = $e->getResponse()->getStatusCode();
                $responseBody = json_decode($e->getResponse()->getBody()->getContents(), true);
            }

            $errorMsg = isset($responseBody['error']['message']) ? $responseBody['error']['message'] : $e->getMessage();

            throw new AIProviderException(
                'Error HTTP ' . $statusCode . ': ' . $errorMsg,
                'gemini',
                $responseBody,
                $statusCode,
                $e
            );
        } catch (\GuzzleHttp\Exception\ServerException $e) {
            $responseBody = null;
            $statusCode = 0;
            if ($e->hasResponse()) {
                $statusCode = $e->getResponse()->getStatusCode();
                $responseBody = json_decode($e->getResponse()->getBody()->getContents(), true);
            }

            throw new AIProviderException(
                'Error 5xx Gemini API: ' . $e->getMessage(),
                'gemini',
                $responseBody,
                $statusCode,
                $e
            );
        } catch (\Exception $e) {
            throw new AIProviderException(
                'Error de conexión con Gemini API: ' . $e->getMessage(),
                'gemini',
                null,
                0,
                $e
            );
        }
    }

    /**
     * @return string
     */
    public function getProviderName()
    {
        return 'gemini';
    }

    /**
     * @return string
     */
    public function getModelName()
    {
        return $this->model;
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers privados
    |--------------------------------------------------------------------------
    */

    /**
     * Convierte el formato estándar interno (role=user|assistant, content=string)
     * al formato Gemini (role=user|model, parts=[{text}]).
     *
     * Filtra mensajes con role inválido y mensajes con content vacío. Si dos
     * mensajes consecutivos terminan teniendo el mismo role tras el filtrado
     * (cosa que Gemini rechaza), los fusionamos.
     *
     * S2 — Si vienen $images, las inyecta como `inline_data` parts en el
     * PRIMER user message del historial (el que tiene el texto del
     * requerimiento típicamente). Cada imagen es un objeto:
     *   ['mime_type' => 'image/jpeg', 'data' => '<base64>']
     *
     * @param  array  $messages
     * @param  array  $images   Array de imágenes a inyectar (puede ser vacío)
     * @return array
     */
    private function formatMessages(array $messages, array $images = [])
    {
        $formatted = [];

        foreach ($messages as $msg) {
            $role = isset($msg['role']) ? $msg['role'] : 'user';

            // Mapeo de roles
            if ($role === 'assistant') {
                $role = 'model';
            }

            if (!in_array($role, ['user', 'model'], true)) {
                continue;
            }

            $text = isset($msg['content']) ? (string) $msg['content'] : '';
            if ($text === '') {
                continue;
            }

            // Si el último mensaje agregado tiene el mismo role, fusionamos
            // (Gemini rechaza turnos consecutivos del mismo role).
            $lastIdx = count($formatted) - 1;
            if ($lastIdx >= 0 && $formatted[$lastIdx]['role'] === $role) {
                $formatted[$lastIdx]['parts'][0]['text'] .= "\n\n" . $text;
                continue;
            }

            $formatted[] = [
                'role'  => $role,
                'parts' => [
                    ['text' => $text],
                ],
            ];
        }

        // Gemini exige que el primer mensaje sea role='user'. Si por algún
        // motivo el historial empieza con 'model', anteponemos un user vacío.
        if (!empty($formatted) && $formatted[0]['role'] !== 'user') {
            array_unshift($formatted, [
                'role'  => 'user',
                'parts' => [['text' => '(inicio de conversación)']],
            ]);
        }

        // ── Multimodal: inyectar imágenes en el primer user message ──
        if (!empty($images) && !empty($formatted)) {
            // Buscar el primer user message
            foreach ($formatted as $idx => $msg) {
                if ($msg['role'] !== 'user') continue;

                foreach ($images as $img) {
                    if (empty($img['mime_type']) || empty($img['data'])) continue;
                    $formatted[$idx]['parts'][] = [
                        'inline_data' => [
                            'mime_type' => $img['mime_type'],
                            'data'      => $img['data'],
                        ],
                    ];
                }
                break; // Solo el primer user message
            }
        }

        return $formatted;
    }

    /**
     * Extrae el texto de la respuesta de Gemini.
     *
     * Estructura típica:
     *   { candidates: [{ content: { parts: [{ text: "..." }], role: "model" }, ... }], ... }
     *
     * @param  array  $body
     * @return string
     */
    private function extractText(array $body)
    {
        $candidates = isset($body['candidates']) ? $body['candidates'] : [];
        if (empty($candidates)) {
            return '';
        }

        $first = $candidates[0];
        $parts = isset($first['content']['parts']) ? $first['content']['parts'] : [];

        $texts = [];
        foreach ($parts as $part) {
            if (isset($part['text'])) {
                $texts[] = (string) $part['text'];
            }
        }

        return implode("\n", $texts);
    }

    /**
     * ¿El modelo usa el parámetro generationConfig.thinkingConfig.thinkingLevel?
     * Aplica a la familia Gemini 3.x y a los alias -latest (que hoy resuelven a
     * 3.x). Los 2.x usaban thinkingBudget y quedaron retirados, así que no lo
     * seteamos para nombres antiguos.
     *
     * @param  string  $model
     * @return bool
     */
    private static function modelSupportsThinkingLevel($model)
    {
        if (strpos($model, 'gemini-3') === 0) {
            return true;
        }
        return in_array($model, ['gemini-flash-latest', 'gemini-flash-lite-latest'], true);
    }

    /**
     * Resolver un setting Gemini desde BD (marketing_chatbot_config) con
     * fallback al config legacy. Permite cambiar modelo/max_tokens desde la
     * UI Config Chatbot sin tocar .env.
     *
     * @param  string  $configKey         Clave en marketing_chatbot_config
     * @param  string  $legacyConfigPath  Sub-key dentro de ai.gemini en config Laravel
     * @param  mixed   $default
     * @return mixed
     */
    private static function resolveSettingFromDb($configKey, $legacyConfigPath, $default)
    {
        try {
            $val = \Modules\Marketing\Models\ChatbotConfig::get($configKey);
            if ($val !== null && $val !== '') {
                return $val;
            }
        } catch (\Exception $e) {
            // Tabla no disponible — fallback silencioso
        }
        return config('marketing.chatbot.ai.gemini.' . $legacyConfigPath, $default);
    }
}
