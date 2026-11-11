<?php

namespace Modules\Marketing\Services\Image;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Modules\Marketing\Contracts\ImageProviderInterface;
use Modules\Marketing\Support\PublicUrl;

/**
 * Generación de imágenes con Flux 1 Schnell en Cloudflare Workers AI.
 */
class CloudflareFluxProvider implements ImageProviderInterface
{
    /** @var string|null */
    private $apiKey;

    /** @var string|null */
    private $accountId;

    /** @var string */
    private $model;

    /** @var string */
    private $baseUrl;

    /** @var string */
    private $storageDisk;

    /** @var string */
    private $storagePath;

    /** @var Client */
    private $client;

    /**
     * @param  array  $config  api_key, account_id, model, base_url, storage_disk, storage_path
     */
    public function __construct(array $config = [])
    {
        $this->apiKey = isset($config['api_key'])
            ? $config['api_key']
            : config('marketing.chatbot.ai.cloudflare.api_key');
        $this->accountId = isset($config['account_id'])
            ? $config['account_id']
            : config('marketing.chatbot.ai.cloudflare.account_id');
        $this->model = isset($config['model'])
            ? $config['model']
            : config('marketing.chatbot.ai.cloudflare.model', '@cf/black-forest-labs/flux-1-schnell');
        $this->baseUrl = isset($config['base_url'])
            ? rtrim($config['base_url'], '/')
            : rtrim(config(
                'marketing.chatbot.ai.cloudflare.base_url',
                'https://api.cloudflare.com/client/v4/accounts'
            ), '/');
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
        return !empty($this->apiKey) && !empty($this->accountId);
    }

    /**
     * Generar una imagen con Cloudflare Workers AI.
     *
     * Opciones admitidas: aspect_ratio (informativo, no se envía al API) y steps.
     *
     * Nota: flux-1-schnell NO acepta width/height en su esquema de entrada;
     * enviarlos produce 400 "Additional or unevaluated properties ... not allowed".
     * Las dimensiones se devuelven a partir de aspect_ratio.
     *
     * @param  string  $prompt
     * @param  array   $options
     * @return array
     */
    public function generateImage($prompt, array $options = [])
    {
        if (empty($this->apiKey)) {
            return $this->failure('API token no configurado (CLOUDFLARE_API_TOKEN ausente)');
        }
        if (empty($this->accountId)) {
            return $this->failure('Account ID no configurado (CLOUDFLARE_ACCOUNT_ID ausente)');
        }

        $payload = ['prompt' => $prompt];
        $dimensions = $this->resolveDimensions($options);
        $width = isset($options['width']) && is_numeric($options['width'])
            ? (int) $options['width']
            : $dimensions['width'];
        $height = isset($options['height']) && is_numeric($options['height'])
            ? (int) $options['height']
            : $dimensions['height'];
        if (isset($options['steps']) && is_numeric($options['steps'])) {
            $steps = (int) $options['steps'];
            if ($steps >= 1 && $steps <= 8) {
                $payload['steps'] = $steps;
            }
        }

        $modelPath = implode('/', array_map('rawurlencode', explode('/', $this->model)));
        $url = $this->baseUrl . '/' . rawurlencode($this->accountId)
            . '/ai/run/' . str_replace('%40', '@', $modelPath);
        $startedAt = microtime(true);

        try {
            $response = $this->client->post($url, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type'  => 'application/json',
                ],
                'json' => $payload,
            ]);
            $body = json_decode($response->getBody()->getContents(), true);
            if (!is_array($body)) {
                return $this->failure('Respuesta JSON invalida de Cloudflare');
            }

            $image = isset($body['result']['image'])
                ? $body['result']['image']
                : (isset($body['image']) ? $body['image'] : null);
            if (!is_string($image) || $image === '') {
                $message = isset($body['errors'][0]['message'])
                    ? $body['errors'][0]['message']
                    : 'La respuesta de Cloudflare no contiene una imagen';
                return $this->failure($message);
            }

            $storagePath = $this->decodeAndStore($image);
            if (!$storagePath) {
                return $this->failure('No se pudo guardar la imagen en storage');
            }

            Log::info('CloudflareFluxProvider: imagen procesada', [
                'model'        => $this->model,
                'duration_ms'  => round((microtime(true) - $startedAt) * 1000),
                'storage_path' => $storagePath,
            ]);

            return [
                'success'      => true,
                'url'          => PublicUrl::forDisk($this->storageDisk, $storagePath),
                'storage_path' => $storagePath,
                'provider'     => $this->getProviderName(),
                'model'        => $this->model,
                'quality'      => null,
                'size'         => $width . 'x' . $height,
                'usage'        => isset($body['result']['usage']) ? $body['result']['usage'] : null,
                'error'        => null,
            ];
        } catch (RequestException $e) {
            $status = 0;
            $errorBody = null;
            if ($e->hasResponse()) {
                $status = $e->getResponse()->getStatusCode();
                $errorBody = json_decode($e->getResponse()->getBody()->getContents(), true);
            }
            $message = isset($errorBody['errors'][0]['message'])
                ? $errorBody['errors'][0]['message']
                : ($status ? 'Error HTTP de Cloudflare' : 'Fallo la solicitud a Cloudflare');
            Log::error('CloudflareFluxProvider: error API', [
                'status' => $status,
                'error'  => $message,
            ]);
            return $this->failure('HTTP ' . $status . ': ' . $message);
        } catch (\Exception $e) {
            Log::error('CloudflareFluxProvider: error inesperado', [
                'exception_class' => get_class($e),
            ]);
            return $this->failure('Error interno procesando la respuesta de Cloudflare');
        }
    }

    /**
     * @param  string  $prompt
     * @param  array   $referenceImagePaths
     * @param  array   $options
     * @return array
     */
    public function editImage($prompt, array $referenceImagePaths, array $options = [])
    {
        return $this->failure('Cloudflare Flux Schnell no soporta edición con imágenes de referencia');
    }

    /**
     * @return bool
     */
    public function supportsImageEdit()
    {
        return false;
    }

    /**
     * @return string
     */
    public function getProviderName()
    {
        return 'cloudflare';
    }

    /**
     * @return string
     */
    public function getModelName()
    {
        return $this->model;
    }

    /**
     * @param  array  $options
     * @return array
     */
    private function resolveDimensions(array $options)
    {
        $ratios = [
            '1:1'  => [1024, 1024],
            '4:5'  => [1024, 1280],
            '16:9' => [1280, 720],
            '9:16' => [720, 1280],
            '2:3'  => [1024, 1536],
            '3:2'  => [1536, 1024],
        ];
        $aspectRatio = isset($options['aspect_ratio']) ? $options['aspect_ratio'] : '1:1';
        $dimensions = isset($ratios[$aspectRatio]) ? $ratios[$aspectRatio] : $ratios['1:1'];

        return ['width' => $dimensions[0], 'height' => $dimensions[1]];
    }

    /**
     * @param  string  $data
     * @return string|null
     */
    private function decodeAndStore($data)
    {
        $bytes = base64_decode($data, true);
        if ($bytes === false) {
            Log::warning('CloudflareFluxProvider: base64 invalido en respuesta');
            return null;
        }

        $filename = 'piece_' . date('Ymd_His') . '_' . uniqid() . '.png';
        $path = rtrim($this->storagePath, '/') . '/' . $filename;
        if (!Storage::disk($this->storageDisk)->put($path, $bytes)) {
            Log::warning('CloudflareFluxProvider: storage rechazo la imagen', ['storage_path' => $path]);
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
}
