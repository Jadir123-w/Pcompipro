<?php

namespace Modules\Marketing\Services\Image;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Modules\Marketing\Contracts\ImageProviderInterface;
use Modules\Marketing\Support\PublicUrl;

/**
 * GPTImage2Provider — Generación de imágenes con gpt-image-2 (OpenAI).
 *
 * Usa la Image API directa: POST /v1/images/generations con model="gpt-image-2".
 * A diferencia de DALL-E 3:
 *   - Respuesta viene en base64 (b64_json), no URL → se decodifica y guarda directo
 *   - Quality: low|medium|high|auto (no standard|hd)
 *   - NO soporta parámetro `style` (vivid/natural fueron retirados)
 *   - Sizes nuevos: 1024×1024, 1024×1536, 1536×1024, 2048×… hasta 3840×2160
 *   - Soporta format=png|jpeg|webp + output_compression
 *   - Latencia: hasta 2 min en prompts complejos
 *
 * ⚠️ Requiere "API Organization Verification" en platform.openai.com/settings/organization
 * (puede pedirte foto de ID a quien administra la cuenta).
 *
 * Configuración en .env:
 *   MKT03_IMAGE_PROVIDER=gpt-image-2
 *   MKT03_IMAGE_MODEL=gpt-image-2
 *   MKT03_IMAGE_QUALITY=medium
 *   MKT03_IMAGE_FORMAT=png
 *   MKT03_IMAGE_STORAGE_DISK=public
 *   MKT03_IMAGE_STORAGE_PATH=mkt03/pieces
 *
 * Reutiliza CHATBOT_OPENAI_API_KEY (misma cuenta que DallE3Provider y OpenAIProvider).
 *
 * Pricing referencial (2026-04, sujeto a cambios):
 *   - 1024×1024 low:    $0.006
 *   - 1024×1024 medium: $0.053
 *   - 1024×1024 high:   $0.211
 */
class GPTImage2Provider implements ImageProviderInterface
{
    /** @var string */
    private $apiKey;

    /** @var string */
    private $model;

    /** @var string */
    private $defaultQuality;

    /** @var string */
    private $defaultFormat;

    /** @var string */
    private $baseUrl;

    /** @var string */
    private $storageDisk;

    /** @var string */
    private $storagePath;

    /** @var string */
    private $editsUrl;

    /** @var Client */
    private $client;

    /**
     * @param  array  $config  Override opcional. Soporta keys: api_key, model, quality, format, storage_disk, storage_path.
     *                         Si no se pasa, lee del config global.
     */
    public function __construct(array $config = [])
    {
        $this->apiKey         = isset($config['api_key'])      ? $config['api_key']      : config('marketing.chatbot.ai.openai.api_key');
        // Para `model`, `quality`, `format`: prioridad config explícito → BD → config legacy → default.
        $this->model          = isset($config['model'])        ? $config['model']        : self::resolveSettingFromDb('mkt03_image_model',   'image_model',   'gpt-image-2');
        $this->defaultQuality = isset($config['quality'])      ? $config['quality']      : self::resolveSettingFromDb('mkt03_image_quality', 'image_quality', 'medium');
        $this->defaultFormat  = isset($config['format'])       ? $config['format']       : self::resolveSettingFromDb('mkt03_image_format',  'image_format',  'png');
        $this->baseUrl        = 'https://api.openai.com/v1/images/generations';
        $this->editsUrl       = 'https://api.openai.com/v1/images/edits';
        $this->storageDisk    = isset($config['storage_disk']) ? $config['storage_disk'] : config('marketing.chatbot.mkt03.image_storage_disk', 'public');
        $this->storagePath    = isset($config['storage_path']) ? $config['storage_path'] : config('marketing.chatbot.mkt03.image_storage_path', 'mkt03/pieces');

        $this->client = new Client([
            'timeout'         => 180, // gpt-image-2 puede tardar hasta 2 min
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
     * Generar una imagen con gpt-image-2.
     *
     * @param  string  $prompt
     * @param  array   $options  Opciones soportadas:
     *                           - size: '1024x1024' (default), '1024x1536', '1536x1024', '2048x2048', etc.
     *                           - quality: 'low' | 'medium' (default) | 'high' | 'auto'
     *                           - aspect_ratio: helper para convertir a size (1:1 | 4:5 | 16:9 | 2:3 | 3:2 | etc.)
     *                           - format: 'png' (default) | 'jpeg' | 'webp'
     *                           - output_compression: 0-100 (solo para jpeg/webp)
     *                           - background: 'opaque' | 'auto' (NO soporta 'transparent' en gpt-image-2)
     *                           - moderation: 'auto' (default) | 'low'
     *                           - n: 1 (default; soporta múltiples)
     *                           - style: ⚠️ ignorado silenciosamente (no soportado por gpt-image-2)
     * @return array
     */
    public function generateImage($prompt, array $options = [])
    {
        if (!$this->isConfigured()) {
            Log::error('GPTImage2Provider: API key no configurada');
            return [
                'success'      => false,
                'url'          => null,
                'storage_path' => null,
                'provider'     => $this->getProviderName(),
                'model'        => $this->model,
                'error'        => 'API key no configurada (CHATBOT_OPENAI_API_KEY ausente)',
            ];
        }

        // Resolver size (explícito tiene prioridad sobre aspect_ratio)
        $size = isset($options['size']) ? $options['size'] : $this->aspectRatioToSize(
            isset($options['aspect_ratio']) ? $options['aspect_ratio'] : '1:1'
        );

        $quality = isset($options['quality']) ? $options['quality'] : $this->defaultQuality;
        $format  = isset($options['format'])  ? $options['format']  : $this->defaultFormat;

        // Construir payload
        $payload = [
            'model'   => $this->model,
            'prompt'  => $prompt,
            'n'       => isset($options['n']) ? (int) $options['n'] : 1,
            'size'    => $size,
            'quality' => $quality,
        ];

        // format solo si difiere del default png (algunos endpoints son sensibles)
        if ($format !== 'png') {
            $payload['output_format'] = $format;
            if (isset($options['output_compression']) && in_array($format, ['jpeg', 'webp'])) {
                $payload['output_compression'] = (int) $options['output_compression'];
            }
        }

        if (isset($options['background']) && $options['background'] !== 'transparent') {
            // gpt-image-2 NO soporta transparent → silenciosamente lo descartamos
            $payload['background'] = $options['background'];
        }

        if (isset($options['moderation']) && in_array($options['moderation'], ['auto', 'low'])) {
            $payload['moderation'] = $options['moderation'];
        }

        // Nota: el parámetro 'style' de DALL-E 3 NO se envía — gpt-image-2 lo rechaza.

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
                Log::error('GPTImage2Provider: error API', [
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

            // gpt-image-2 retorna: { data: [ { b64_json: "..." } ] }
            $b64 = isset($body['data'][0]['b64_json']) ? $body['data'][0]['b64_json'] : null;

            if (!$b64) {
                return [
                    'success'      => false,
                    'url'          => null,
                    'storage_path' => null,
                    'provider'     => $this->getProviderName(),
                    'model'        => $this->model,
                    'error'        => 'Respuesta sin b64_json',
                ];
            }

            // Decodificar y guardar localmente
            $localPath = $this->decodeAndStore($b64, $format);
            if (!$localPath) {
                return [
                    'success'      => false,
                    'url'          => null,
                    'storage_path' => null,
                    'provider'     => $this->getProviderName(),
                    'model'        => $this->model,
                    'error'        => 'No se pudo guardar la imagen en storage',
                ];
            }

            // Tokens consumidos (si vienen en la respuesta — gpt-image-2 los reporta)
            $usage = isset($body['usage']) ? $body['usage'] : null;

            Log::info('GPTImage2Provider: imagen generada', [
                'model'        => $this->model,
                'size'         => $size,
                'quality'      => $quality,
                'format'       => $format,
                'duration_ms'  => $duration,
                'storage_path' => $localPath,
                'usage'        => $usage,
            ]);

            return [
                'success'      => true,
                'url'          => $this->getPublicUrl($localPath),
                'storage_path' => $localPath,
                'provider'     => $this->getProviderName(),
                'model'        => $this->model,
                'quality'      => $quality,
                'size'         => $size,
                'usage'        => $usage,
                'error'        => null,
            ];

        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $errorBody = null;
            $status    = 0;
            if ($e->hasResponse()) {
                $status    = $e->getResponse()->getStatusCode();
                $errorBody = json_decode($e->getResponse()->getBody()->getContents(), true);
            }
            $errorMsg = isset($errorBody['error']['message']) ? $errorBody['error']['message'] : $e->getMessage();
            $errorCode = isset($errorBody['error']['code']) ? $errorBody['error']['code'] : null;

            // Detectar el caso típico de "Organization Verification required"
            $hint = '';
            if ($status === 403 || stripos($errorMsg, 'verification') !== false || stripos($errorMsg, 'organization') !== false) {
                $hint = ' [HINT: completa la API Organization Verification en platform.openai.com/settings/organization/general]';
            }

            Log::error('GPTImage2Provider: ClientException', [
                'status' => $status,
                'code'   => $errorCode,
                'error'  => $errorMsg,
            ]);

            return [
                'success'      => false,
                'url'          => null,
                'storage_path' => null,
                'provider'     => $this->getProviderName(),
                'model'        => $this->model,
                'error'        => 'HTTP ' . $status . ': ' . $errorMsg . $hint,
            ];

        } catch (\Exception $e) {
            Log::error('GPTImage2Provider: error inesperado', [
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
     * Editar/transformar imagen(es) con prompt usando endpoint /v1/images/edits.
     *
     * Acepta 1+ imágenes de referencia (PNG/JPG/WEBP, ≤50MB cada una) y opcionalmente
     * una máscara con canal alfa (para inpainting). El modelo usa las referencias como
     * base contextual: respeta forma/color/estilo de productos reales y los recompone
     * según el prompt.
     *
     * Casos típicos COMPIPRO:
     *   - 1 foto producto + prompt "place on wooden desk" → mockup de catálogo
     *   - Logo cliente + foto bolsa + prompt "apply logo to bag back" → mockup branding
     *   - 4 productos + prompt "generate gift basket with these items" → composición
     *
     * @param  string  $prompt
     * @param  array   $referenceImagePaths  Rutas absolutas a 1+ imágenes (debe existir cada archivo)
     * @param  array   $options              size, quality, aspect_ratio, format, mask_path
     * @return array
     */
    public function editImage($prompt, array $referenceImagePaths, array $options = [])
    {
        if (!$this->isConfigured()) {
            return [
                'success'      => false,
                'url'          => null,
                'storage_path' => null,
                'provider'     => $this->getProviderName(),
                'model'        => $this->model,
                'error'        => 'API key no configurada (CHATBOT_OPENAI_API_KEY ausente)',
            ];
        }

        // Validar que se pasó al menos 1 imagen
        if (empty($referenceImagePaths)) {
            return [
                'success'      => false,
                'url'          => null,
                'storage_path' => null,
                'provider'     => $this->getProviderName(),
                'model'        => $this->model,
                'error'        => 'editImage requiere al menos 1 imagen de referencia',
            ];
        }

        // Validar que cada archivo existe y es legible
        foreach ($referenceImagePaths as $idx => $path) {
            if (!is_string($path) || !file_exists($path) || !is_readable($path)) {
                return [
                    'success'      => false,
                    'url'          => null,
                    'storage_path' => null,
                    'provider'     => $this->getProviderName(),
                    'model'        => $this->model,
                    'error'        => "Imagen de referencia #{$idx} no existe o no es legible: {$path}",
                ];
            }
            // Validar tamaño máximo 50MB (límite de la API)
            $size = filesize($path);
            if ($size === false || $size > 50 * 1024 * 1024) {
                return [
                    'success'      => false,
                    'url'          => null,
                    'storage_path' => null,
                    'provider'     => $this->getProviderName(),
                    'model'        => $this->model,
                    'error'        => "Imagen de referencia #{$idx} excede 50MB: {$path}",
                ];
            }
        }

        // Resolver size + quality + format
        $size = isset($options['size']) ? $options['size'] : $this->aspectRatioToSize(
            isset($options['aspect_ratio']) ? $options['aspect_ratio'] : '1:1'
        );
        $quality = isset($options['quality']) ? $options['quality'] : $this->defaultQuality;
        $format  = isset($options['format'])  ? $options['format']  : $this->defaultFormat;

        // Construir multipart payload
        $multipart = [
            ['name' => 'model',   'contents' => $this->model],
            ['name' => 'prompt',  'contents' => $prompt],
            ['name' => 'size',    'contents' => $size],
            ['name' => 'quality', 'contents' => $quality],
            ['name' => 'n',       'contents' => isset($options['n']) ? (string) ((int) $options['n']) : '1'],
        ];

        if ($format !== 'png') {
            $multipart[] = ['name' => 'output_format', 'contents' => $format];
            if (isset($options['output_compression']) && in_array($format, ['jpeg', 'webp'])) {
                $multipart[] = ['name' => 'output_compression', 'contents' => (string) ((int) $options['output_compression'])];
            }
        }

        // Adjuntar imágenes de referencia. La API acepta 'image[]' para múltiples,
        // o 'image' simple para una sola. Para uniformidad usamos 'image[]'.
        foreach ($referenceImagePaths as $path) {
            $multipart[] = [
                'name'     => 'image[]',
                'contents' => fopen($path, 'r'),
                'filename' => basename($path),
            ];
        }

        // Máscara opcional para inpainting (debe matchear formato y tamaño de la 1ra imagen)
        if (!empty($options['mask_path']) && is_string($options['mask_path'])) {
            if (!file_exists($options['mask_path']) || !is_readable($options['mask_path'])) {
                return [
                    'success'      => false,
                    'url'          => null,
                    'storage_path' => null,
                    'provider'     => $this->getProviderName(),
                    'model'        => $this->model,
                    'error'        => "Mask path no existe o no es legible: {$options['mask_path']}",
                ];
            }
            $multipart[] = [
                'name'     => 'mask',
                'contents' => fopen($options['mask_path'], 'r'),
                'filename' => basename($options['mask_path']),
            ];
        }

        $startTime = microtime(true);

        try {
            $response = $this->client->post($this->editsUrl, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->apiKey,
                ],
                'multipart' => $multipart,
            ]);

            $duration   = round((microtime(true) - $startTime) * 1000);
            $statusCode = $response->getStatusCode();
            $body       = json_decode($response->getBody()->getContents(), true);

            if ($statusCode < 200 || $statusCode >= 300) {
                $errorMsg = isset($body['error']['message']) ? $body['error']['message'] : 'Unknown error';
                Log::error('GPTImage2Provider.editImage: error API', [
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

            $b64 = isset($body['data'][0]['b64_json']) ? $body['data'][0]['b64_json'] : null;
            if (!$b64) {
                return [
                    'success'      => false,
                    'url'          => null,
                    'storage_path' => null,
                    'provider'     => $this->getProviderName(),
                    'model'        => $this->model,
                    'error'        => 'Respuesta sin b64_json',
                ];
            }

            $localPath = $this->decodeAndStore($b64, $format);
            if (!$localPath) {
                return [
                    'success'      => false,
                    'url'          => null,
                    'storage_path' => null,
                    'provider'     => $this->getProviderName(),
                    'model'        => $this->model,
                    'error'        => 'No se pudo guardar la imagen en storage',
                ];
            }

            $usage = isset($body['usage']) ? $body['usage'] : null;

            Log::info('GPTImage2Provider.editImage: imagen generada', [
                'model'         => $this->model,
                'size'          => $size,
                'quality'       => $quality,
                'format'        => $format,
                'duration_ms'   => $duration,
                'storage_path'  => $localPath,
                'reference_count' => count($referenceImagePaths),
                'has_mask'      => !empty($options['mask_path']),
                'usage'         => $usage,
            ]);

            return [
                'success'         => true,
                'url'             => $this->getPublicUrl($localPath),
                'storage_path'    => $localPath,
                'provider'        => $this->getProviderName(),
                'model'           => $this->model,
                'quality'         => $quality,
                'size'            => $size,
                'reference_count' => count($referenceImagePaths),
                'usage'           => $usage,
                'error'           => null,
            ];

        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $errorBody = null;
            $status    = 0;
            if ($e->hasResponse()) {
                $status    = $e->getResponse()->getStatusCode();
                $errorBody = json_decode($e->getResponse()->getBody()->getContents(), true);
            }
            $errorMsg = isset($errorBody['error']['message']) ? $errorBody['error']['message'] : $e->getMessage();

            $hint = '';
            if ($status === 403 || stripos($errorMsg, 'verification') !== false || stripos($errorMsg, 'organization') !== false) {
                $hint = ' [HINT: completa la API Organization Verification en platform.openai.com/settings/organization/general]';
            }

            Log::error('GPTImage2Provider.editImage: ClientException', [
                'status' => $status,
                'error'  => $errorMsg,
            ]);

            return [
                'success'      => false,
                'url'          => null,
                'storage_path' => null,
                'provider'     => $this->getProviderName(),
                'model'        => $this->model,
                'error'        => 'HTTP ' . $status . ': ' . $errorMsg . $hint,
            ];

        } catch (\Exception $e) {
            Log::error('GPTImage2Provider.editImage: error inesperado', [
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
        return 'gpt-image-2';
    }

    /**
     * @return string
     */
    public function getModelName()
    {
        return $this->model;
    }

    /**
     * Convertir aspect_ratio a size soportado por gpt-image-2.
     *
     * gpt-image-2 soporta cualquier resolución que cumpla:
     *   - max edge ≤ 3840px
     *   - ambos lados múltiplos de 16
     *   - long/short ratio ≤ 3:1
     *   - total pixels entre 655,360 y 8,294,400
     *
     * Retornamos los tamaños "populares" oficiales por simplicidad.
     *
     * @param  string  $aspectRatio
     * @return string
     */
    private function aspectRatioToSize($aspectRatio)
    {
        switch ($aspectRatio) {
            case '1:1':
                return '1024x1024';
            case '4:5':
            case '9:16':
            case '2:3':
                return '1024x1536'; // portrait
            case '16:9':
            case '1.91:1':
            case '3:2':
                return '1536x1024'; // landscape
            default:
                return '1024x1024';
        }
    }

    /**
     * Decodificar base64 y guardar en storage.
     *
     * @param  string  $b64
     * @param  string  $format
     * @return string|null  Storage path relativo, null si falla
     */
    private function decodeAndStore($b64, $format)
    {
        try {
            $bytes = base64_decode($b64, true);
            if ($bytes === false) {
                Log::warning('GPTImage2Provider: base64 inválido en respuesta');
                return null;
            }

            $extension = in_array($format, ['png', 'jpeg', 'webp']) ? $format : 'png';
            // 'jpeg' como string del API → '.jpg' como extensión de archivo es opcional, mantenemos jpeg para coherencia
            $filename = 'piece_' . date('Ymd_His') . '_' . uniqid() . '.' . $extension;
            $fullPath = rtrim($this->storagePath, '/') . '/' . $filename;

            Storage::disk($this->storageDisk)->put($fullPath, $bytes);

            return $fullPath;
        } catch (\Exception $e) {
            Log::warning('GPTImage2Provider: no se pudo guardar imagen localmente', [
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
     * Resolver un setting de imagen desde BD con fallback al config legacy.
     *
     * Orden de prioridad:
     *   1. marketing_chatbot_config (key configurable desde UI)
     *   2. config('marketing.chatbot.mkt03.{legacyConfigPath}') (.env)
     *   3. $default
     *
     * @param  string  $configKey         Clave en marketing_chatbot_config
     * @param  string  $legacyConfigPath  Sub-key dentro de mkt03 en config Laravel
     * @param  string  $default
     * @return string
     */
    private static function resolveSettingFromDb($configKey, $legacyConfigPath, $default)
    {
        try {
            $val = \Modules\Marketing\Models\ChatbotConfig::get($configKey);
            if ($val !== null && $val !== '') {
                return (string) $val;
            }
        } catch (\Exception $e) {
            // Tabla no disponible — fallback silencioso
        }
        return config('marketing.chatbot.mkt03.' . $legacyConfigPath, $default);
    }
}
