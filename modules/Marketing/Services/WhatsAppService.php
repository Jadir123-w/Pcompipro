<?php

namespace Modules\Marketing\Services;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

/**
 * WhatsAppService
 *
 * Servicio para enviar mensajes via WhatsApp Cloud API (Graph API de Meta).
 * Usado por el chatbot MKT-01 para responder mensajes de WhatsApp.
 *
 * Requiere configurar en .env:
 *   WHATSAPP_TOKEN=<access_token_permanente>
 *   WHATSAPP_PHONE_NUMBER_ID=<phone_number_id>
 *   WHATSAPP_API_VERSION=v21.0
 */
class WhatsAppService
{
    /** @var string */
    private $token;

    /** @var string */
    private $phoneNumberId;

    /** @var string */
    private $apiVersion;

    /** @var string */
    private $baseUrl;

    /** @var Client */
    private $client;

    public function __construct()
    {
        // wa_access_token y wa_phone_number_id viven en marketing_chatbot_config
        // (el primero CIFRADO con Laravel Crypt). Prioridad BD → .env (legacy).
        $this->token         = \Modules\Marketing\Models\ChatbotConfig::getOrEnv(
            'wa_access_token',
            'whatsapp.token',
            null
        );
        $this->phoneNumberId = \Modules\Marketing\Models\ChatbotConfig::getOrEnv(
            'wa_phone_number_id',
            'whatsapp.phone_number_id',
            null
        );
        // api_version es decisión de infra — sigue en .env / config Laravel
        $this->apiVersion    = config('marketing.chatbot.whatsapp.api_version', 'v21.0');
        $this->baseUrl       = 'https://graph.facebook.com/' . $this->apiVersion . '/' . $this->phoneNumberId;

        $this->client = new Client([
            'timeout'         => 15,
            'connect_timeout' => 10,
        ]);
    }

    /**
     * Verificar que el servicio está configurado.
     *
     * @return bool
     */
    public function isConfigured()
    {
        return !empty($this->token) && !empty($this->phoneNumberId);
    }

    /**
     * Descargar un archivo media (imagen, documento, audio…) recibido en un
     * webhook entrante de Meta WhatsApp Cloud API.
     *
     * Meta envía el `id` del media (no la URL); el flujo es 2 pasos:
     *   1. GET https://graph.facebook.com/{version}/{media_id}
     *      → devuelve { url, mime_type, file_size, sha256, ... }
     *   2. GET <url>  (con el mismo Bearer token)
     *      → devuelve los bytes raw
     *
     * @param  string  $mediaId
     * @return array  [
     *    'success'    => bool,
     *    'mime_type'  => string|null,
     *    'sha256'     => string|null,
     *    'size_bytes' => int|null,
     *    'bytes'      => string|null  binario raw,
     *    'error'      => string|null,
     * ]
     */
    public function downloadMedia($mediaId)
    {
        if (!$this->isConfigured()) {
            return $this->mediaError('WhatsApp no configurado');
        }
        if (empty($mediaId)) {
            return $this->mediaError('media_id vacío');
        }

        try {
            // Paso 1: resolver la URL temporal del media
            $metaUrl = 'https://graph.facebook.com/' . $this->apiVersion . '/' . urlencode($mediaId);
            $metaResp = $this->client->get($metaUrl, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->token,
                ],
            ]);
            $metaJson = json_decode($metaResp->getBody()->getContents(), true);

            $mediaUrl  = isset($metaJson['url']) ? $metaJson['url'] : null;
            $mimeType  = isset($metaJson['mime_type']) ? $metaJson['mime_type'] : null;
            $sha256    = isset($metaJson['sha256']) ? $metaJson['sha256'] : null;
            $sizeMeta  = isset($metaJson['file_size']) ? (int) $metaJson['file_size'] : null;

            if (empty($mediaUrl)) {
                return $this->mediaError('Meta no devolvió URL para el media_id ' . $mediaId);
            }

            // Paso 2: descargar el binario raw
            $binResp = $this->client->get($mediaUrl, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->token,
                ],
                // Stream para archivos grandes; recogemos completo abajo
            ]);
            $bytes = $binResp->getBody()->getContents();
            $sizeReal = strlen($bytes);

            Log::info('WhatsAppService: media descargado', [
                'media_id'  => $mediaId,
                'mime'      => $mimeType,
                'size_meta' => $sizeMeta,
                'size_real' => $sizeReal,
            ]);

            return [
                'success'    => true,
                'mime_type'  => $mimeType,
                'sha256'     => $sha256,
                'size_bytes' => $sizeReal,
                'bytes'      => $bytes,
                'error'      => null,
            ];

        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $body = null;
            $code = 0;
            if ($e->hasResponse()) {
                $code = $e->getResponse()->getStatusCode();
                $body = json_decode($e->getResponse()->getBody()->getContents(), true);
            }
            $msg = isset($body['error']['message']) ? $body['error']['message'] : $e->getMessage();

            Log::error('WhatsAppService: error descargando media', [
                'media_id' => $mediaId,
                'status'   => $code,
                'error'    => $msg,
            ]);

            return $this->mediaError('Error HTTP ' . $code . ': ' . $msg);

        } catch (\Exception $e) {
            Log::error('WhatsAppService: error de conexión descargando media', [
                'media_id' => $mediaId,
                'error'    => $e->getMessage(),
            ]);
            return $this->mediaError('Error de conexión: ' . $e->getMessage());
        }
    }

    /**
     * @param  string  $error
     * @return array
     */
    private function mediaError($error)
    {
        return [
            'success'    => false,
            'mime_type'  => null,
            'sha256'     => null,
            'size_bytes' => null,
            'bytes'      => null,
            'error'      => $error,
        ];
    }

    /**
     * Enviar un mensaje de texto a un número de WhatsApp.
     *
     * @param  string  $to    Número del destinatario (ej: "51987654321")
     * @param  string  $text  Texto del mensaje
     * @return array          ['success' => bool, 'message_id' => string|null, 'error' => string|null]
     */
    public function sendTextMessage($to, $text)
    {
        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type'    => 'individual',
            'to'                => $to,
            'type'              => 'text',
            'text'              => [
                'preview_url' => false,
                'body'        => $text,
            ],
        ];

        return $this->sendRequest($payload, 'text', $to);
    }

    /**
     * Enviar un documento (catálogo PDF) a un número de WhatsApp.
     *
     * @param  string       $to       Número del destinatario
     * @param  string       $url      URL pública del documento
     * @param  string|null  $caption  Texto debajo del documento
     * @return array
     */
    public function sendDocument($to, $url, $caption = null)
    {
        $document = [
            'link' => $url,
        ];

        if ($caption) {
            $document['caption'] = $caption;
        }

        // Intentar deducir el filename de la URL
        $filename = basename(parse_url($url, PHP_URL_PATH));
        if ($filename) {
            $document['filename'] = $filename;
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type'    => 'individual',
            'to'                => $to,
            'type'              => 'document',
            'document'          => $document,
        ];

        return $this->sendRequest($payload, 'document', $to);
    }

    /**
     * Enviar request a la Graph API de Meta.
     *
     * @param  array   $payload
     * @param  string  $type     Tipo de mensaje para logging
     * @param  string  $to       Destinatario para logging
     * @return array
     */
    private function sendRequest(array $payload, $type, $to)
    {
        if (!$this->isConfigured()) {
            Log::error('WhatsAppService: servicio no configurado (falta token o phone_number_id)');
            return [
                'success'    => false,
                'message_id' => null,
                'error'      => 'WhatsApp no configurado',
            ];
        }

        $url = $this->baseUrl . '/messages';

        try {
            $response = $this->client->post($url, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->token,
                    'Content-Type'  => 'application/json',
                ],
                'json' => $payload,
            ]);

            $body = json_decode($response->getBody()->getContents(), true);
            $messageId = isset($body['messages'][0]['id']) ? $body['messages'][0]['id'] : null;

            Log::info('WhatsAppService: mensaje enviado', [
                'type'       => $type,
                'to'         => $to,
                'message_id' => $messageId,
            ]);

            return [
                'success'    => true,
                'message_id' => $messageId,
                'error'      => null,
            ];

        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $errorBody = null;
            $statusCode = 0;
            if ($e->hasResponse()) {
                $statusCode = $e->getResponse()->getStatusCode();
                $errorBody = json_decode($e->getResponse()->getBody()->getContents(), true);
            }

            $errorMsg = isset($errorBody['error']['message']) ? $errorBody['error']['message'] : $e->getMessage();

            Log::error('WhatsAppService: error enviando mensaje', [
                'type'   => $type,
                'to'     => $to,
                'status' => $statusCode,
                'error'  => $errorMsg,
            ]);

            return [
                'success'    => false,
                'message_id' => null,
                'error'      => $errorMsg,
            ];

        } catch (\Exception $e) {
            Log::error('WhatsAppService: error de conexión', [
                'type'  => $type,
                'to'    => $to,
                'error' => $e->getMessage(),
            ]);

            return [
                'success'    => false,
                'message_id' => null,
                'error'      => $e->getMessage(),
            ];
        }
    }
}
