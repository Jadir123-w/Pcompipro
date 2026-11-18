<?php

namespace Modules\Marketing\Services\AI;

use Modules\Marketing\Contracts\AIProviderInterface;
use Modules\Marketing\Exceptions\AIProviderException;

class OpenAICompatibleTextProvider implements AIProviderInterface
{
    private $apiKey;
    private $model;
    private $baseUrl;
    private $providerKey;
    private $maxTokensLimit;

    public function __construct(array $config)
    {
        $this->apiKey = isset($config['api_key']) ? $config['api_key'] : null;
        $this->model = isset($config['model']) ? $config['model'] : '';
        $this->baseUrl = isset($config['base_url']) ? $config['base_url'] : '';
        $this->providerKey = isset($config['provider_key']) ? $config['provider_key'] : 'openai-compatible';
        $this->maxTokensLimit = isset($config['max_tokens_limit']) && (int) $config['max_tokens_limit'] > 0
            ? (int) $config['max_tokens_limit']
            : null;
    }

    public function sendMessage($systemPrompt, array $messages, array $options = [])
    {
        $allMessages = [['role' => 'system', 'content' => $systemPrompt]];
        foreach ($messages as $message) {
            $role = isset($message['role']) ? $message['role'] : 'user';
            if (!in_array($role, ['user', 'assistant'], true)) {
                continue;
            }
            $allMessages[] = [
                'role' => $role,
                'content' => isset($message['content']) && is_string($message['content'])
                    ? $message['content']
                    : '',
            ];
        }

        try {
            $body = OpenAICompatibleApi::request('POST', $this->baseUrl, $this->apiKey, 'chat/completions', [
                'model' => isset($options['model']) ? $options['model'] : $this->model,
                'messages' => $allMessages,
                'max_tokens' => self::resolveMaxTokens(
                    isset($options['max_tokens']) ? (int) $options['max_tokens'] : 1024,
                    $this->maxTokensLimit
                ),
                'temperature' => isset($options['temperature']) ? (float) $options['temperature'] : 0.4,
            ]);
        } catch (\Exception $e) {
            throw new AIProviderException($e->getMessage(), $this->providerKey, null, 0, $e);
        }

        $content = isset($body['choices'][0]['message']['content'])
            ? $body['choices'][0]['message']['content']
            : null;
        if (!is_string($content) || $content === '') {
            throw new AIProviderException('El proveedor no devolvió contenido de texto.', $this->providerKey);
        }
        return $content;
    }

    public function getProviderName()
    {
        return $this->providerKey;
    }

    public function getModelName()
    {
        return $this->model;
    }

    public static function resolveMaxTokens($requested, $limit = null)
    {
        $requested = max(1, (int) $requested);
        $limit = $limit === null ? null : (int) $limit;
        return $limit && $limit > 0 ? min($requested, $limit) : $requested;
    }
}
