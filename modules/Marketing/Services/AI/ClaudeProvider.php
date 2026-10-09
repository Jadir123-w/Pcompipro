<?php

namespace Modules\Marketing\Services\AI;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;
use Modules\Marketing\Contracts\AIProviderInterface;
use Modules\Marketing\Exceptions\AIProviderException;

/**
 * ClaudeProvider
 *
 * Implementación del proveedor de IA usando la API de Anthropic (Claude).
 * Usa GuzzleHttp\Client (compatible con Laravel 5.x).
 */
class ClaudeProvider implements AIProviderInterface
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
     *                         Si no se pasa, lee del config global (.env vía marketing.chatbot.ai.claude).
     *                         Permite que AgentConfigService instancie el provider con config por-agente.
     */
    public function __construct(array $config = [])
    {
        // api_key sigue del .env (es secret)
        $this->apiKey    = isset($config['api_key'])    ? $config['api_key']    : config('marketing.chatbot.ai.claude.api_key');
        // model: prioridad config explícito → BD → config legacy → default
        $this->model     = isset($config['model'])      ? $config['model']      : self::resolveModelFromDb('claude-sonnet-4-6');
        $this->baseUrl   = isset($config['base_url'])   ? $config['base_url']   : config('marketing.chatbot.ai.claude.base_url', 'https://api.anthropic.com/v1/messages');
        $this->maxTokens = isset($config['max_tokens']) ? (int) $config['max_tokens'] : config('marketing.chatbot.ai.claude.max_tokens', 1024);

        if (empty($this->apiKey)) {
            throw new AIProviderException(
                'API key no configurada. Configura CHATBOT_CLAUDE_API_KEY en .env',
                'claude'
            );
        }

        $this->client = new Client([
            // 90s alcanza para respuestas largas (análisis de patrones, generación de grilla extensa, etc.)
            'timeout' => 90,
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
        // S2 — multimodal: si hay imágenes se inyectan en el primer user message
        $images = isset($options['images']) && is_array($options['images']) ? $options['images'] : [];

        $payload = [
            'model'       => isset($options['model']) ? $options['model'] : $this->model,
            'max_tokens'  => isset($options['max_tokens']) ? $options['max_tokens'] : $this->maxTokens,
            'system'      => $systemPrompt,
            'messages'    => $this->formatMessages($messages, $images),
            'temperature' => isset($options['temperature']) ? $options['temperature'] : 0.4,
        ];

        $startTime = microtime(true);

        try {
            $response = $this->client->post($this->baseUrl, [
                'headers' => [
                    'x-api-key'         => $this->apiKey,
                    'anthropic-version'  => '2023-06-01',
                    'content-type'       => 'application/json',
                ],
                'json' => $payload,
            ]);

            $duration = round((microtime(true) - $startTime) * 1000);
            $statusCode = $response->getStatusCode();
            $body = json_decode($response->getBody()->getContents(), true);

            if ($statusCode < 200 || $statusCode >= 300) {
                Log::error('Claude API error', [
                    'status'   => $statusCode,
                    'body'     => $body,
                    'duration' => $duration . 'ms',
                ]);

                $errorMsg = isset($body['error']['message']) ? $body['error']['message'] : 'Unknown error';

                throw new AIProviderException(
                    'Error HTTP ' . $statusCode . ': ' . $errorMsg,
                    'claude',
                    $body,
                    $statusCode
                );
            }

            $text = $this->extractText($body);

            $inputTokens  = isset($body['usage']['input_tokens']) ? $body['usage']['input_tokens'] : 0;
            $outputTokens = isset($body['usage']['output_tokens']) ? $body['usage']['output_tokens'] : 0;

            Log::info('Claude API call', [
                'model'         => $payload['model'],
                'input_tokens'  => $inputTokens,
                'output_tokens' => $outputTokens,
                'duration_ms'   => $duration,
                'stop_reason'   => isset($body['stop_reason']) ? $body['stop_reason'] : null,
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
                'claude',
                $responseBody,
                $statusCode,
                $e
            );
        } catch (\Exception $e) {
            throw new AIProviderException(
                'Error de conexión con Claude API: ' . $e->getMessage(),
                'claude',
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
        return 'claude';
    }

    /**
     * @return string
     */
    public function getModelName()
    {
        return $this->model;
    }

    /**
     * Formatea los mensajes para Anthropic Messages API.
     *
     * S2 — multimodal: si vienen $images, transforma el PRIMER user message
     * de string a array de content blocks, intercalando blocks de tipo
     * "image" antes del texto. Cada imagen es:
     *   ['mime_type' => 'image/jpeg', 'data' => '<base64>']
     *
     * Formato Anthropic para imagen:
     *   {type: "image", source: {type: "base64", media_type: "image/jpeg", data: "..."}}
     *
     * @param  array  $messages
     * @param  array  $images
     * @return array
     */
    private function formatMessages(array $messages, array $images = [])
    {
        $formatted = [];

        foreach ($messages as $msg) {
            $role = isset($msg['role']) ? $msg['role'] : 'user';

            if (!in_array($role, ['user', 'assistant'])) {
                continue;
            }

            $formatted[] = [
                'role'    => $role,
                'content' => isset($msg['content']) ? $msg['content'] : '',
            ];
        }

        // ── Multimodal: inyectar imágenes en el primer user message ──
        if (!empty($images) && !empty($formatted)) {
            foreach ($formatted as $idx => $msg) {
                if ($msg['role'] !== 'user') continue;

                $blocks = [];
                foreach ($images as $img) {
                    if (empty($img['mime_type']) || empty($img['data'])) continue;
                    $blocks[] = [
                        'type'   => 'image',
                        'source' => [
                            'type'       => 'base64',
                            'media_type' => $img['mime_type'],
                            'data'       => $img['data'],
                        ],
                    ];
                }
                // Texto al final
                $blocks[] = [
                    'type' => 'text',
                    'text' => is_string($msg['content']) ? $msg['content'] : (string) $msg['content'],
                ];
                $formatted[$idx]['content'] = $blocks;
                break; // Solo el primer user message
            }
        }

        return $formatted;
    }

    /**
     * @param  array  $body
     * @return string
     */
    private function extractText(array $body)
    {
        $content = isset($body['content']) ? $body['content'] : [];

        $texts = [];
        foreach ($content as $block) {
            $type = isset($block['type']) ? $block['type'] : '';
            if ($type === 'text') {
                $texts[] = isset($block['text']) ? $block['text'] : '';
            }
        }

        return implode("\n", $texts);
    }

    /**
     * Resolver el modelo Claude desde BD (marketing_chatbot_config) con
     * fallback al config legacy. Permite cambiar el modelo desde la UI Config
     * Chatbot sin tocar .env.
     *
     * @param  string  $default
     * @return string
     */
    private static function resolveModelFromDb($default = 'claude-sonnet-4-6')
    {
        try {
            $val = \Modules\Marketing\Models\ChatbotConfig::get('chatbot_claude_model');
            if ($val !== null && $val !== '') {
                return (string) $val;
            }
        } catch (\Exception $e) {
            // Fallback silencioso si la tabla no está disponible
        }
        return config('marketing.chatbot.ai.claude.model', $default);
    }
}
