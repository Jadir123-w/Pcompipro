<?php

namespace Modules\Marketing\Services\Image;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Modules\Marketing\Contracts\ImageProviderInterface;
use Modules\Marketing\Exceptions\AIProviderException;
use Modules\Marketing\Support\PublicUrl;

/**
 * DallE3Provider — Generación de imágenes con DALL-E 3 de OpenAI.
 *
 * Usado por MKT-03 para producir imágenes finales de piezas publicables.
 * Requiere CHATBOT_OPENAI_API_KEY configurado (misma key que usa OpenAIProvider
 * del módulo, para reutilizar la cuenta de API).
 *
 * Configuración en .env:
 *   MKT03_IMAGE_PROVIDER=dalle3
 *   MKT03_IMAGE_MODEL=dall-e-3
 *   MKT03_IMAGE_STORAGE_DISK=public
 *   MKT03_IMAGE_STORAGE_PATH=mkt03/pieces
 */
class DallE3Provider implements ImageProviderInterface
{
    /** @var string */
    private $apiKey;

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
     * @param  array  $config  Override opcional. Soporta keys: api_key, model, storage_disk, storage_path.
     *                         Si no se pasa, lee del config global.
     */
    public function __construct(array $config = [])
    {
        // Reutilizar la misma API key de OpenAI que se usa en MKT-01/COM-02
        // Para `model`: priorizar config explícito → BD (marketing_chatbot_config) → config legacy → default.
        // Para `api_key`, `storage_*`: siguen viniendo del config Laravel (api_key=secret en .env;
        // storage=decisión de infra).
        $this->apiKey      = isset($config['api_key'])      ? $config['api_key']      : config('marketing.chatbot.ai.openai.api_key');
        $this->model       = isset($config['model'])        ? $config['model']        : self::resolveModelFromDb('dall-e-3');
        $this->baseUrl     = 'https://api.openai.com/v1/images/generations';
        $this->storageDisk = isset($config['storage_disk']) ? $config['storage_disk'] : config('marketing.chatbot.mkt03.image_storage_disk', 'public');
        $this->storagePath = isset($config['storage_path']) ? $config['storage_path'] : config('marketing.chatbot.mkt03.image_storage_path', 'mkt03/pieces');

        $this->client = new Client([
            'timeout'         => 120, // DALL-E 3 puede tardar 30-60s
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
     * Generar una imagen con DALL-E 3.
     *
     * @param  string  $prompt
     * @param  array   $options  Opciones DALL-E 3:
     *                           - size: '1024x1024' (default), '1024x1792', '1792x1024'
     *                           - quality: 'standard' (default), 'hd'
     *                           - style: 'vivid' (default), 'natural'
     *                           - n: 1 (DALL-E 3 solo soporta 1)
     *                           - aspect_ratio: helper para convertir a size
     * @return array
     */
    public function generateImage($prompt, array $options = [])
    {
        if (!$this->isConfigured()) {
            Log::error('DallE3Provider: API key no configurada');
            return [
                'success'      => false,
                'url'          => null,
                'storage_path' => null,
                'provider'     => $this->getProviderName(),
                'model'        => $this->model,
                'error'        => 'API key no configurada (CHATBOT_OPENAI_API_KEY ausente)',
            ];
        }

        // Determinar size según aspect_ratio si se pasó
        $size = isset($options['size']) ? $options['size'] : $this->aspectRatioToSize(
            isset($options['aspect_ratio']) ? $options['aspect_ratio'] : '1:1'
        );

        $payload = [
            'model'   => $this->model,
            'prompt'  => $prompt,
            'n'       => 1, // DALL-E 3 solo soporta 1
            'size'    => $size,
            'quality' => isset($options['quality']) ? $options['quality'] : 'standard',
            'style'   => isset($options['style']) ? $options['style'] : 'vivid',
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

            $duration   = round((microtime(true) - $startTime) * 1000);
            $statusCode = $response->getStatusCode();
            $body       = json_decode($response->getBody()->getContents(), true);

            if ($statusCode < 200 || $statusCode >= 300) {
                $errorMsg = isset($body['error']['message']) ? $body['error']['message'] : 'Unknown error';
                Log::error('DallE3Provider: error API', [
                    'status'   => $statusCode,
                    'error'    => $errorMsg,
                    'duration' => $duration . 'ms',
                ]);
                return [
                    'success'      => false,
                    'url'          => null,
                    'storage_path' => null,
                    'provider'     => $this->getProviderName(),
                    'model'        => $this->model,
                    'error'        => 'HTTP ' . $statusCode . ': ' . $errorMsg,
                ];
            }

            // DALL-E 3 retorna: { data: [ { url: "https://...", revised_prompt: "..." } ] }
            $imageUrl = isset($body['data'][0]['url']) ? $body['data'][0]['url'] : null;
            $revisedPrompt = isset($body['data'][0]['revised_prompt']) ? $body['data'][0]['revised_prompt'] : null;

            if (!$imageUrl) {
                return [
                    'success'      => false,
                    'url'          => null,
                    'storage_path' => null,
                    'provider'     => $this->getProviderName(),
                    'model'        => $this->model,
                    'error'        => 'Respuesta sin URL de imagen',
                ];
            }

            // Descargar la imagen y guardarla localmente (la URL de OpenAI expira en 1h)
            $localPath = $this->downloadAndStore($imageUrl);

            Log::info('DallE3Provider: imagen generada', [
                'model'          => $this->model,
                'size'           => $size,
                'duration_ms'    => $duration,
                'storage_path'   => $localPath,
                'revised_prompt' => $revisedPrompt ? substr($revisedPrompt, 0, 100) : null,
            ]);

            return [
                'success'        => true,
                'url'            => $localPath ? $this->getPublicUrl($localPath) : $imageUrl,
                'storage_path'   => $localPath,
                'openai_url'     => $imageUrl, // URL temporal original
                'revised_prompt' => $revisedPrompt,
                'provider'       => $this->getProviderName(),
                'model'          => $this->model,
                'error'          => null,
            ];

        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $errorBody = null;
            $status    = 0;
            if ($e->hasResponse()) {
                $status    = $e->getResponse()->getStatusCode();
                $errorBody = json_decode($e->getResponse()->getBody()->getContents(), true);
            }
            $errorMsg = isset($errorBody['error']['message']) ? $errorBody['error']['message'] : $e->getMessage();

            Log::error('DallE3Provider: ClientException', [
                'status' => $status,
                'error'  => $errorMsg,
            ]);

            return [
                'success'      => false,
                'url'          => null,
                'storage_path' => null,
                'provider'     => $this->getProviderName(),
                'model'        => $this->model,
                'error'        => 'HTTP ' . $status . ': ' . $errorMsg,
            ];

        } catch (\Exception $e) {
            Log::error('DallE3Provider: error inesperado', [
                'error' => $e->getMessage(),
            ]);

            return [
                'success'      => false,
                'url'          => null,
                'storage_path' => null,
                'provider'     => $this->getProviderName(),
                'model'        => $this->model,
                'error'        => 'Error de conexión: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * DALL-E 3 NO soporta edición con imágenes de referencia.
     * Para esa capacidad, usa GPTImage2Provider (gpt-image-2).
     *
     * @param  string  $prompt
     * @param  array   $referenceImagePaths
     * @param  array   $options
     * @return array
     */
    public function editImage($prompt, array $referenceImagePaths, array $options = [])
    {
        return [
            'success'      => false,
            'url'          => null,
            'storage_path' => null,
            'provider'     => $this->getProviderName(),
            'model'        => $this->model,
            'error'        => 'DALL-E 3 no soporta edición con imágenes de referencia. Cambia MKT03_IMAGE_PROVIDER=gpt-image-2 para usar esta funcionalidad.',
        ];
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
        return 'dalle3';
    }

    /**
     * @return string
     */
    public function getModelName()
    {
        return $this->model;
    }

    /**
     * Convertir aspect_ratio a size soportado por DALL-E 3.
     *
     * @param  string  $aspectRatio
     * @return string
     */
    private function aspectRatioToSize($aspectRatio)
    {
        // DALL-E 3 soporta: 1024x1024, 1024x1792 (vertical), 1792x1024 (horizontal)
        switch ($aspectRatio) {
            case '1:1':
                return '1024x1024';
            case '4:5':
            case '9:16':
            case '2:3':
                return '1024x1792'; // vertical
            case '16:9':
            case '1.91:1':
            case '3:2':
                return '1792x1024'; // horizontal
            default:
                return '1024x1024';
        }
    }

    /**
     * Descargar imagen de URL temporal y guardarla en storage local.
     *
     * @param  string  $imageUrl
     * @return string|null  Storage path relativo, null si falla
     */
    private function downloadAndStore($imageUrl)
    {
        try {
            $response = $this->client->get($imageUrl, ['timeout' => 60]);
            $imageContent = $response->getBody()->getContents();

            $filename = 'piece_' . date('Ymd_His') . '_' . uniqid() . '.png';
            $fullPath = rtrim($this->storagePath, '/') . '/' . $filename;

            Storage::disk($this->storageDisk)->put($fullPath, $imageContent);

            return $fullPath;
        } catch (\Exception $e) {
            Log::warning('DallE3Provider: no se pudo guardar imagen localmente', [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Obtener URL pública de una imagen almacenada.
     *
     * @param  string  $storagePath
     * @return string
     */
    private function getPublicUrl($storagePath)
    {
        return PublicUrl::forDisk($this->storageDisk, $storagePath);
    }

    /**
     * Resolver el modelo DALL-E desde BD (marketing_chatbot_config) con fallback
     * al config legacy (.env). Este orden de prioridad permite que el admin
     * cambie el modelo desde la UI Config Chatbot sin tocar .env.
     *
     * @param  string  $default
     * @return string
     */
    private static function resolveModelFromDb($default = 'dall-e-3')
    {
        try {
            $val = \Modules\Marketing\Models\ChatbotConfig::get('mkt03_image_model');
            if ($val !== null && $val !== '') {
                return (string) $val;
            }
        } catch (\Exception $e) {
            // Fallback silencioso si la tabla no está disponible
        }
        return config('marketing.chatbot.mkt03.image_model', $default);
    }
}
