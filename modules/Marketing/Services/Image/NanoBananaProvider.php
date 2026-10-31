<?php

namespace Modules\Marketing\Services\Image;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Modules\Marketing\Contracts\ImageProviderInterface;
use Modules\Marketing\Support\PublicUrl;

/**
 * NanoBananaProvider — Generacion y edicion de imagenes con Gemini Image.
 *
 * Usa Gemini 3.1 Flash Image (`gemini-3.1-flash-image`) mediante generateContent.
 */
class NanoBananaProvider implements ImageProviderInterface
{
    /** @var string|null */
    private $apiKey;

    /** @var string */
    private $model;

    /** @var string */
    private $baseUrl;

    /** @var string */
    private $defaultAspectRatio;

    /** @var string */
    private $storageDisk;

    /** @var string */
    private $storagePath;

    /** @var Client */
    private $client;

    /**
     * @param  array  $config  api_key, model, base_url, aspect_ratio, storage_disk, storage_path
     */
    public function __construct(array $config = [])
    {
        $this->apiKey = isset($config['api_key'])
            ? $config['api_key']
            : config('marketing.chatbot.ai.gemini.api_key');
        $this->model = isset($config['model'])
            ? $config['model']
            : self::resolveModelFromDb();
        $this->baseUrl = isset($config['base_url'])
            ? rtrim($config['base_url'], '/')
            : rtrim(config(
                'marketing.chatbot.ai.gemini.base_url',
                'https://generativelanguage.googleapis.com/v1beta/models'
            ), '/');
        $this->defaultAspectRatio = isset($config['aspect_ratio']) ? $config['aspect_ratio'] : '1:1';
        $this->storageDisk = isset($config['storage_disk'])
            ? $config['storage_disk']
            : config('marketing.chatbot.mkt03.image_storage_disk', 'public');
        $this->storagePath = isset($config['storage_path'])
            ? $config['storage_path']
            : config('marketing.chatbot.mkt03.image_storage_path', 'mkt03/pieces');

        $this->client = new Client([
            'timeout'         => 180,
            'connect_timeout' => 10,
        ]);
    }

    /**
     * @return bool
     */
    public function isConfigured()
    {
        return !empty($this->apiKey);
    }

    /**
     * Generar una imagen con Nano Banana.
     *
     * Opciones admitidas: aspect_ratio y image_size (1K, 2K o 4K).
     *
     * @param  string  $prompt
     * @param  array   $options
     * @return array
     */
    public function generateImage($prompt, array $options = [])
    {
        if (!$this->isConfigured()) {
            return $this->failure('API key no configurada (CHATBOT_GEMINI_API_KEY ausente)');
        }

        return $this->generateFromParts([
            ['text' => $prompt],
        ], $options);
    }

    /**
     * Editar imagenes de referencia con un prompt.
     *
     * @param  string  $prompt
     * @param  array   $referenceImagePaths
     * @param  array   $options
     * @return array
     */
    public function editImage($prompt, array $referenceImagePaths, array $options = [])
    {
        if (!$this->isConfigured()) {
            return $this->failure('API key no configurada (CHATBOT_GEMINI_API_KEY ausente)');
        }
        if (empty($referenceImagePaths)) {
            return $this->failure('editImage requiere al menos 1 imagen de referencia');
        }

        $parts = [];
        foreach ($referenceImagePaths as $index => $path) {
            if (!is_string($path) || !is_readable($path)) {
                return $this->failure("Imagen de referencia #{$index} no existe o no es legible");
            }

            $mimeType = $this->imageMimeType($path);
            if (!$mimeType) {
                return $this->failure("Formato de imagen no soportado para la referencia #{$index}");
            }

            $bytes = file_get_contents($path);
            if ($bytes === false) {
                return $this->failure("No se pudo leer la imagen de referencia #{$index}");
            }

            $parts[] = [
                'inline_data' => [
                    'mime_type' => $mimeType,
                    'data'      => base64_encode($bytes),
                ],
            ];
        }
        $parts[] = ['text' => $prompt];

        return $this->generateFromParts($parts, $options);
    }

    /**
     * @return bool
     */
    public function supportsImageEdit()
    {
        return true;
    }

    /**
     * @return string
     */
    public function getProviderName()
    {
        return 'nano-banana';
    }

    /**
     * @return string
     */
    public function getModelName()
    {
        return $this->model;
    }

    /**
     * Enviar contenido a Gemini y guardar la primera imagen devuelta.
     *
     * @param  array  $parts
     * @param  array  $options
     * @return array
     */
    private function generateFromParts(array $parts, array $options)
    {
        $aspectRatio = isset($options['aspect_ratio'])
            ? $options['aspect_ratio']
            : $this->defaultAspectRatio;
        $imageConfig = [];
        if (is_string($aspectRatio) && preg_match('/^\d+:\d+$/', $aspectRatio)) {
            $imageConfig['aspectRatio'] = $aspectRatio;
        }
        if (isset($options['image_size']) && in_array($options['image_size'], ['1K', '2K', '4K'])) {
            $imageConfig['imageSize'] = $options['image_size'];
        }

        $generationConfig = ['responseModalities' => ['TEXT', 'IMAGE']];
        if (!empty($imageConfig)) {
            $generationConfig['imageConfig'] = $imageConfig;
        }

        $payload = [
            'contents' => [
                ['parts' => $parts],
            ],
            'generationConfig' => $generationConfig,
        ];
        $url = $this->baseUrl . '/' . rawurlencode($this->model)
            . ':generateContent?key=' . urlencode($this->apiKey);
        $startedAt = microtime(true);

        try {
            $response = $this->client->post($url, [
                'headers' => ['Content-Type' => 'application/json'],
                'json'    => $payload,
            ]);
            $body = json_decode($response->getBody()->getContents(), true);
            if (!is_array($body)) {
                return $this->failure('Respuesta JSON invalida de Gemini');
            }

            $image = $this->extractImage($body);
            if (!$image) {
                $reason = isset($body['promptFeedback']['blockReason'])
                    ? 'Prompt bloqueado por Gemini: ' . $body['promptFeedback']['blockReason']
                    : 'La respuesta de Gemini no contiene una imagen';
                return $this->failure($reason);
            }

            $storagePath = $this->decodeAndStore($image['data'], $image['mime_type']);
            if (!$storagePath) {
                return $this->failure('No se pudo guardar la imagen en storage');
            }

            $duration = round((microtime(true) - $startedAt) * 1000);
            Log::info('NanoBananaProvider: imagen procesada', [
                'model'        => $this->model,
                'duration_ms'  => $duration,
                'storage_path' => $storagePath,
            ]);

            return [
                'success'      => true,
                'url'          => PublicUrl::forDisk($this->storageDisk, $storagePath),
                'storage_path' => $storagePath,
                'provider'     => $this->getProviderName(),
                'model'        => $this->model,
                'quality'      => isset($options['quality']) ? $options['quality'] : null,
                'size'         => isset($imageConfig['imageSize']) ? $imageConfig['imageSize'] : null,
                'usage'        => isset($body['usageMetadata']) ? $body['usageMetadata'] : null,
                'error'        => null,
            ];
        } catch (RequestException $e) {
            $status = 0;
            $errorBody = null;
            if ($e->hasResponse()) {
                $status = $e->getResponse()->getStatusCode();
                $errorBody = json_decode($e->getResponse()->getBody()->getContents(), true);
            }
            $message = isset($errorBody['error']['message'])
                ? $errorBody['error']['message']
                : ($status ? 'Error HTTP de Gemini' : 'Fallo la solicitud a Gemini');
            Log::error('NanoBananaProvider: error API', [
                'status' => $status,
                'error'  => $message,
            ]);
            return $this->failure('HTTP ' . $status . ': ' . $message);
        } catch (\Exception $e) {
            Log::error('NanoBananaProvider: error inesperado', [
                'exception_class' => get_class($e),
            ]);
            return $this->failure('Error interno procesando la respuesta de Gemini');
        }
    }

    /**
     * Extraer la primera imagen de la respuesta de Gemini.
     *
     * @param  array  $body
     * @return array|null
     */
    private function extractImage(array $body)
    {
        $parts = isset($body['candidates'][0]['content']['parts'])
            ? $body['candidates'][0]['content']['parts']
            : [];
        foreach ($parts as $part) {
            $inlineData = isset($part['inlineData'])
                ? $part['inlineData']
                : (isset($part['inline_data']) ? $part['inline_data'] : null);
            if (is_array($inlineData) && !empty($inlineData['data'])) {
                return [
                    'data'     => $inlineData['data'],
                    'mime_type' => isset($inlineData['mimeType'])
                        ? $inlineData['mimeType']
                        : (isset($inlineData['mime_type']) ? $inlineData['mime_type'] : 'image/png'),
                ];
            }
        }

        return null;
    }

    /**
     * @param  string  $path
     * @return string|null
     */
    private function imageMimeType($path)
    {
        $mimeType = function_exists('mime_content_type') ? mime_content_type($path) : false;
        if (in_array($mimeType, ['image/png', 'image/jpeg', 'image/webp'])) {
            return $mimeType;
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $mimeTypes = [
            'png'  => 'image/png',
            'jpg'  => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'webp' => 'image/webp',
        ];

        return isset($mimeTypes[$extension]) ? $mimeTypes[$extension] : null;
    }

    /**
     * @param  string  $data
     * @param  string  $mimeType
     * @return string|null
     */
    private function decodeAndStore($data, $mimeType)
    {
        $bytes = base64_decode($data, true);
        if ($bytes === false) {
            Log::warning('NanoBananaProvider: base64 invalido en respuesta');
            return null;
        }

        $extensions = [
            'image/png'  => 'png',
            'image/jpeg' => 'jpg',
            'image/webp' => 'webp',
        ];
        if (!isset($extensions[$mimeType])) {
            Log::warning('NanoBananaProvider: formato de imagen no soportado', ['mime_type' => $mimeType]);
            return null;
        }
        $extension = $extensions[$mimeType];
        $filename = 'piece_' . date('Ymd_His') . '_' . uniqid() . '.' . $extension;
        $path = rtrim($this->storagePath, '/') . '/' . $filename;

        if (!Storage::disk($this->storageDisk)->put($path, $bytes)) {
            Log::warning('NanoBananaProvider: storage rechazo la imagen', ['storage_path' => $path]);
            return null;
        }

        return $path;
    }

    /**
     * @param  string  $message
     * @return array
     */
    private function failure($message)
    {
        return [
            'success'      => false,
            'url'          => null,
            'storage_path' => null,
            'provider'     => $this->getProviderName(),
            'model'        => $this->model,
            'error'        => $message,
        ];
    }

    /**
     * Resolver el modelo operativo desde la configuracion existente de MKT-03.
     *
     * @return string
     */
    private static function resolveModelFromDb()
    {
        try {
            $model = \Modules\Marketing\Models\ChatbotConfig::get('mkt03_image_model');
            if ($model !== null && $model !== '') {
                return (string) $model;
            }
        } catch (\Exception $e) {
            // La tabla puede no existir en instalaciones nuevas.
        }

        return config('marketing.chatbot.mkt03.image_model') ?: 'gemini-3.1-flash-image';
    }
}
