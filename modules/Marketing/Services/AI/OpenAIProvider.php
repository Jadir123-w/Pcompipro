<?php

namespace Modules\Marketing\Services\AI;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;
use Modules\Marketing\Contracts\AIProviderInterface;
use Modules\Marketing\Exceptions\AIProviderException;

/**
 * OpenAIProvider
 *
 * Implementación alternativa usando la API de OpenAI (GPT).
 * Usa GuzzleHttp\Client (compatible con Laravel 5.x).
 */
class OpenAIProvider implements AIProviderInterface
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
     *                         Si no se pasa, lee del config global (.env vía marketing.chatbot.ai.openai).
     */
    public function __construct(array $config = [])
    {
        // api_key sigue del .env (es secret)
        $this->apiKey    = isset($config['api_key'])    ? $config['api_key']    : config('marketing.chatbot.ai.openai.api_key');
        // model: prioridad config explícito → BD → config legacy → default
        $this->model     = isset($config['model'])      ? $config['model']      : self::resolveModelFromDb('gpt-4o-mini');
        $this->baseUrl   = isset($config['base_url'])   ? $config['base_url']   : config('marketing.chatbot.ai.openai.base_url', 'https://api.openai.com/v1/chat/completions');
        $this->maxTokens = isset($config['max_tokens']) ? (int) $config['max_tokens'] : config('marketing.chatbot.ai.openai.max_tokens', 1024);

        if (empty($this->apiKey)) {
            throw new AIProviderException(
                'API key no configurada. Configura CHATBOT_OPENAI_API_KEY en .env',
                'openai'
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
        // S2 — multimodal: si hay imágenes, se inyectan en el primer user message
        $images = isset($options['images']) && is_array($options['images']) ? $options['images'] : [];
        $imagesInjected = false;

        $allMessages = [
            ['role' => 'system', 'content' => $systemPrompt],
        ];

        foreach ($messages as $msg) {
            $role = isset($msg['role']) ? $msg['role'] : 'user';
            if (!in_array($role, ['user', 'assistant'])) continue;

            $text = isset($msg['content']) ? $msg['content'] : '';

            // Si hay imágenes y este es el primer user message, lo
            // construimos como array de content blocks (formato vision OpenAI).
            if (!$imagesInjected && $role === 'user' && !empty($images)) {
                $contentBlocks = [];
                foreach ($images as $img) {
                    if (empty($img['mime_type']) || empty($img['data'])) continue;
                    $contentBlocks[] = [
                        'type'      => 'image_url',
                        'image_url' => [
                            'url' => 'data:' . $img['mime_type'] . ';base64,' . $img['data'],
                        ],
                    ];
                }
                $contentBlocks[] = [
                    'type' => 'text',
                    'text' => is_string($text) ? $text : (string) $text,
                ];
                $allMessages[] = [
                    'role'    => $role,
                    'content' => $contentBlocks,
                ];
                $imagesInjected = true;
            } else {
                $allMessages[] = [
                    'role'    => $role,
                    'content' => $text,
                ];
            }
        }

        $payload = [
            'model'       => isset($options['model']) ? $options['model'] : $this->model,
            'max_tokens'  => isset($options['max_tokens']) ? $options['max_tokens'] : $this->maxTokens,
            'messages'    => $allMessages,
            'temperature' => isset($options['temperature']) ? $options['temperature'] : 0.4,
        ];

        $startTime = microtime(true);

        try {
            $response = $this->client->post($this->baseUrl, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type'  => 'application/json',
                ],
                'json' => $payload,
            ]);

            $duration = round((microtime(true) - $startTime) * 1000);
            $statusCode = $response->getStatusCode();
            $body = json_decode($response->getBody()->getContents(), true);

            if ($statusCode < 200 || $statusCode >= 300) {
                Log::error('OpenAI API error', [
                    'status'   => $statusCode,
                    'body'     => $body,
                    'duration' => $duration . 'ms',
                ]);

                $errorMsg = isset($body['error']['message']) ? $body['error']['message'] : 'Unknown error';

                throw new AIProviderException(
                    'Error HTTP ' . $statusCode . ': ' . $errorMsg,
                    'openai',
                    $body,
                    $statusCode
                );
            }

            $text = isset($body['choices'][0]['message']['content']) ? $body['choices'][0]['message']['content'] : '';

            $usage = isset($body['usage']) ? $body['usage'] : [];

            Log::info('OpenAI API call', [
                'model'             => $payload['model'],
                'prompt_tokens'     => isset($usage['prompt_tokens']) ? $usage['prompt_tokens'] : 0,
                'completion_tokens' => isset($usage['completion_tokens']) ? $usage['completion_tokens'] : 0,
                'total_tokens'      => isset($usage['total_tokens']) ? $usage['total_tokens'] : 0,
                'duration_ms'       => $duration,
                'finish_reason'     => isset($body['choices'][0]['finish_reason']) ? $body['choices'][0]['finish_reason'] : null,
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
                'openai',
                $responseBody,
                $statusCode,
                $e
            );
        } catch (\Exception $e) {
            throw new AIProviderException(
                'Error de conexión con OpenAI API: ' . $e->getMessage(),
                'openai',
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
        return 'openai';
    }

    /**
     * @return string
     */
    public function getModelName()
    {
        return $this->model;
    }

    /**
     * Resolver el modelo OpenAI desde BD (marketing_chatbot_config) con
     * fallback al config legacy. Permite cambiar el modelo desde la UI Config
     * Chatbot sin tocar .env.
     *
     * @param  string  $default
     * @return string
     */
    private static function resolveModelFromDb($default = 'gpt-4o-mini')
    {
        try {
            $val = \Modules\Marketing\Models\ChatbotConfig::get('chatbot_openai_model');
            if ($val !== null && $val !== '') {
                return (string) $val;
            }
        } catch (\Exception $e) {
            // Fallback silencioso si la tabla no está disponible
        }
        return config('marketing.chatbot.ai.openai.model', $default);
    }
}
