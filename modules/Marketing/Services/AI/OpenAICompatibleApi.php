<?php

namespace Modules\Marketing\Services\AI;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;

class OpenAICompatibleApi
{
    public static function validateBaseUrl($baseUrl)
    {
        if (!is_string($baseUrl) || strlen($baseUrl) > 255) {
            throw new InvalidArgumentException('La URL base no es válida.');
        }

        $parts = parse_url($baseUrl);
        if (
            !is_array($parts)
            || !isset($parts['scheme'], $parts['host'])
            || strtolower($parts['scheme']) !== 'https'
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['query'])
            || isset($parts['fragment'])
            || (isset($parts['port']) && (int) $parts['port'] !== 443)
        ) {
            throw new InvalidArgumentException('La URL base debe ser HTTPS, pública y no incluir credenciales ni parámetros.');
        }

        $host = strtolower(rtrim($parts['host'], '.'));
        if (
            strpos($host, '.') === false
            || $host === 'localhost'
            || filter_var($host, FILTER_VALIDATE_IP)
        ) {
            throw new InvalidArgumentException('La URL debe usar un dominio público, no una IP o un host local.');
        }

        $addresses = gethostbynamel($host);
        if (empty($addresses)) {
            throw new InvalidArgumentException('No se pudo resolver el dominio público de la URL base.');
        }

        foreach ($addresses as $address) {
            if (!filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                throw new InvalidArgumentException('La URL base debe resolver únicamente a direcciones IP públicas.');
            }
        }
        $ipv6Addresses = dns_get_record($host, DNS_AAAA);
        foreach (is_array($ipv6Addresses) ? $ipv6Addresses : [] as $record) {
            if (
                isset($record['ipv6'])
                && !filter_var($record['ipv6'], FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)
            ) {
                throw new InvalidArgumentException('La URL base debe resolver únicamente a direcciones IP públicas.');
            }
        }

        return rtrim($baseUrl, '/');
    }

    public static function request($method, $baseUrl, $apiKey, $path, array $payload = null)
    {
        $baseUrl = self::validateBaseUrl($baseUrl);
        if (!is_string($apiKey) || $apiKey === '') {
            throw new InvalidArgumentException('Falta la API key del proveedor.');
        }

        $client = new Client([
            // Las generaciones de grids/piezas piden muchos tokens y pueden tardar
            // varios minutos. Antes eran 45s, lo que causaba "No se pudo conectar"
            // aunque OpenRouter llegara a completar y registrar el 200.
            'timeout'         => 180,
            'connect_timeout' => 15,
            'allow_redirects' => false,
        ]);
        $options = [
            'headers' => [
                'Authorization' => 'Bearer ' . $apiKey,
                'Accept'        => 'application/json',
                'Content-Type'  => 'application/json',
            ],
        ];
        if ($payload !== null) {
            $options['json'] = $payload;
        }

        try {
            $response = $client->request($method, $baseUrl . '/' . ltrim($path, '/'), $options);
            $body = json_decode($response->getBody()->getContents(), true);
            if (!is_array($body)) {
                throw new RuntimeException('El proveedor devolvió una respuesta JSON no válida.');
            }
            return $body;
        } catch (RequestException $e) {
            $status = $e->hasResponse() ? $e->getResponse()->getStatusCode() : 0;
            $message = 'No se pudo conectar con el proveedor (' . self::sanitizeExceptionMessage($e->getMessage(), $apiKey) . ')';
            if ($e->hasResponse()) {
                $responseBody = json_decode($e->getResponse()->getBody()->getContents(), true);
                if (!empty($responseBody['error']['message']) && is_string($responseBody['error']['message'])) {
                    $message = substr($responseBody['error']['message'], 0, 300);
                    if ($apiKey !== '') {
                        $message = str_replace($apiKey, '[redacted]', $message);
                    }
                }
            }
            Log::warning('OpenAI-compatible provider request failed', [
                'status'    => $status,
                'exception' => get_class($e),
                'reason'    => self::sanitizeExceptionMessage($e->getMessage(), $apiKey),
            ]);
            throw new RuntimeException($status ? 'HTTP ' . $status . ': ' . $message : $message);
        }
    }

    private static function sanitizeExceptionMessage($message, $apiKey)
    {
        $clean = preg_replace('/\s+/', ' ', (string) $message);
        $clean = str_replace(["\r", "\n"], ' ', $clean);
        if (is_string($apiKey) && $apiKey !== '') {
            $clean = str_replace($apiKey, '[redacted]', $clean);
        }
        return mb_substr($clean, 0, 300);
    }

    public static function listModels($baseUrl, $apiKey)
    {
        $body = self::request('GET', $baseUrl, $apiKey, 'models');
        $models = [];
        foreach (isset($body['data']) && is_array($body['data']) ? $body['data'] : [] as $item) {
            if (isset($item['id']) && is_string($item['id'])) {
                $models[] = $item['id'];
            }
        }
        sort($models);
        return array_values(array_unique($models));
    }
}
