<?php

namespace Modules\Marketing\Services\Image;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Modules\Marketing\Contracts\ImageProviderInterface;
use Modules\Marketing\Services\AI\OpenAICompatibleApi;
use Modules\Marketing\Support\PublicUrl;

class OpenAICompatibleImageProvider implements ImageProviderInterface
{
    private $apiKey;
    private $model;
    private $baseUrl;
    private $providerKey;
    private $storageDisk;
    private $storagePath;

    public function __construct(array $config)
    {
        $this->apiKey = isset($config['api_key']) ? $config['api_key'] : null;
        $this->model = isset($config['model']) ? $config['model'] : '';
        $this->baseUrl = isset($config['base_url']) ? $config['base_url'] : '';
        $this->providerKey = isset($config['provider_key']) ? $config['provider_key'] : 'openai-compatible';
        $this->storageDisk = config('marketing.chatbot.mkt03.image_storage_disk', 'public');
        $this->storagePath = config('marketing.chatbot.mkt03.image_storage_path', 'mkt03/pieces');
    }

    public function generateImage($prompt, array $options = [])
    {
        if (!$this->isConfigured()) {
            return $this->failure('El proveedor no tiene API key configurada.');
        }

        $payload = [
            'model' => $this->model,
            'prompt' => $prompt,
            'n' => 1,
        ];
        if (!empty($options['size'])) {
            $payload['size'] = $options['size'];
        } elseif (!empty($options['aspect_ratio'])) {
            $sizes = [
                '1:1' => '1024x1024',
                '16:9' => '1536x1024',
                '3:2' => '1536x1024',
                '9:16' => '1024x1536',
                '2:3' => '1024x1536',
            ];
            $payload['size'] = isset($sizes[$options['aspect_ratio']])
                ? $sizes[$options['aspect_ratio']]
                : '1024x1024';
        }
        if (!empty($options['quality']) && $options['quality'] !== 'standard') {
            $payload['quality'] = $options['quality'];
        }
        if (!empty($options['format'])) {
            $payload['output_format'] = $options['format'];
        }

        try {
            $body = OpenAICompatibleApi::request('POST', $this->baseUrl, $this->apiKey, 'images/generations', $payload);
            $image = isset($body['data'][0]['b64_json']) ? $body['data'][0]['b64_json'] : null;
            $bytes = is_string($image) ? base64_decode($image, true) : false;
            if ($bytes === false && !empty($body['data'][0]['url'])) {
                $bytes = $this->downloadImage($body['data'][0]['url']);
            }
            if ($bytes === false || $bytes === null) {
                return $this->failure('La respuesta del proveedor no contiene una imagen válida.');
            }

            $format = isset($options['format']) && in_array($options['format'], ['jpeg', 'webp'], true)
                ? $options['format']
                : 'png';
            $extension = $format === 'jpeg' ? 'jpg' : $format;
            $path = rtrim($this->storagePath, '/') . '/piece_' . date('Ymd_His') . '_' . uniqid() . '.' . $extension;
            if (!Storage::disk($this->storageDisk)->put($path, $bytes)) {
                return $this->failure('No se pudo guardar la imagen en storage.');
            }

            return [
                'success' => true,
                'url' => PublicUrl::forDisk($this->storageDisk, $path),
                'storage_path' => $path,
                'provider' => $this->getProviderName(),
                'model' => $this->getModelName(),
                'error' => null,
            ];
        } catch (\Exception $e) {
            Log::warning('OpenAI-compatible image generation failed', [
                'provider' => $this->providerKey,
                'error' => $e->getMessage(),
            ]);
            return $this->failure($e->getMessage());
        }
    }

    public function editImage($prompt, array $referenceImagePaths, array $options = [])
    {
        return $this->failure('Este conector compatible no implementa edición de imágenes.');
    }

    public function supportsImageEdit()
    {
        return false;
    }

    public function getProviderName()
    {
        return $this->providerKey;
    }

    public function getModelName()
    {
        return $this->model;
    }

    public function isConfigured()
    {
        return !empty($this->apiKey) && !empty($this->model) && !empty($this->baseUrl);
    }

    private function downloadImage($url)
    {
        $parts = parse_url($url);
        if (
            !is_array($parts)
            || empty($parts['scheme'])
            || strtolower($parts['scheme']) !== 'https'
            || empty($parts['host'])
            || isset($parts['user'])
            || isset($parts['pass'])
            || (isset($parts['port']) && (int) $parts['port'] !== 443)
        ) {
            throw new \RuntimeException('El proveedor devolvió una URL de imagen no segura.');
        }
        OpenAICompatibleApi::validateBaseUrl($parts['scheme'] . '://' . $parts['host']);
        $client = new \GuzzleHttp\Client(['timeout' => 45, 'connect_timeout' => 10, 'allow_redirects' => false]);
        $response = $client->get($url);
        return $response->getBody()->getContents();
    }

    private function failure($message)
    {
        return [
            'success' => false,
            'url' => null,
            'storage_path' => null,
            'provider' => $this->getProviderName(),
            'model' => $this->getModelName(),
            'error' => $message,
        ];
    }
}
