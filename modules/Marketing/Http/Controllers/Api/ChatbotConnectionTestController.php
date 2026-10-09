<?php

namespace Modules\Marketing\Http\Controllers\Api;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Modules\Marketing\Models\ChatbotConfig;

/**
 * ChatbotConnectionTestController — botones "Probar conexión"
 *
 * Permite al admin validar desde la UI que las credenciales configuradas
 * en Config Chatbot funcionan realmente, sin tener que esperar al primer
 * mensaje de WhatsApp o al primer correo entrante para descubrir un error.
 *
 * Endpoints (auth):
 *   POST /marketing/chatbot/whatsapp/test-connection      → ping a Meta Cloud API
 *   POST /marketing/chatbot/email-triage/test-connection  → IMAP + SMTP
 *
 * Ambos retornan { ok: bool, checks: [...], details: {...} } con detalle por
 * cada chequeo individual para que el frontend pinte una checklist.
 */
class ChatbotConnectionTestController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | WhatsApp — ping a Meta Cloud API
    |--------------------------------------------------------------------------
    */

    /**
     * POST /marketing/chatbot/whatsapp/test-connection
     *
     * Lee credenciales desde marketing_chatbot_config (BD) y hace una llamada
     * GET a https://graph.facebook.com/{api_version}/{phone_number_id}.
     *
     * Si la respuesta es 200 con datos del número, todo OK.
     * Si es 401 → access_token inválido o expirado.
     * Si es 404 → phone_number_id incorrecto.
     */
    public function whatsapp(Request $request)
    {
        $checks = [];

        // 1) Verificar que las credenciales están en BD
        $phoneNumberId = ChatbotConfig::get('wa_phone_number_id');
        $accessToken   = ChatbotConfig::get('wa_access_token');
        $verifyToken   = ChatbotConfig::get('wa_verify_token');
        $appSecret     = ChatbotConfig::get('wa_app_secret');
        $apiVersion    = config('marketing.chatbot.whatsapp.api_version', 'v21.0');

        $checks[] = $this->check('Phone Number ID configurado',
            !empty($phoneNumberId),
            empty($phoneNumberId) ? 'Falta configurar wa_phone_number_id' : null);

        $checks[] = $this->check('Access Token configurado',
            !empty($accessToken),
            empty($accessToken) ? 'Falta configurar wa_access_token (cifrado en BD)' : null);

        $checks[] = $this->check('Verify Token configurado',
            !empty($verifyToken),
            empty($verifyToken) ? 'Falta configurar wa_verify_token (lo decide el equipo)' : null);

        $checks[] = $this->check('App Secret configurado',
            !empty($appSecret),
            empty($appSecret) ? 'Falta configurar wa_app_secret (Meta Settings → Basic → App Secret)' : null);

        if (empty($phoneNumberId) || empty($accessToken)) {
            return response()->json([
                'ok'      => false,
                'message' => 'Faltan credenciales mínimas (Phone Number ID + Access Token).',
                'checks'  => $checks,
            ]);
        }

        // 2) Llamar a Meta Cloud API: GET /{phone_number_id}
        $url = "https://graph.facebook.com/{$apiVersion}/{$phoneNumberId}";

        try {
            $client = new Client(['timeout' => 15, 'connect_timeout' => 5]);
            $response = $client->get($url, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Accept'        => 'application/json',
                ],
                'http_errors' => false,
            ]);

            $status = $response->getStatusCode();
            $body   = json_decode($response->getBody()->getContents(), true);

            if ($status === 200 && isset($body['id'])) {
                $checks[] = $this->check('Conexión a Meta Cloud API exitosa', true,
                    "GET {$url} → 200 OK");

                return response()->json([
                    'ok'      => true,
                    'message' => 'Todo OK — el ERP puede comunicarse con Meta usando estas credenciales.',
                    'checks'  => $checks,
                    'details' => [
                        'phone_number_id'    => isset($body['id'])                     ? $body['id']                     : null,
                        'display_phone'      => isset($body['display_phone_number'])   ? $body['display_phone_number']   : null,
                        'verified_name'      => isset($body['verified_name'])          ? $body['verified_name']          : null,
                        'quality_rating'     => isset($body['quality_rating'])         ? $body['quality_rating']         : null,
                        'platform'           => isset($body['platform_type'])          ? $body['platform_type']          : null,
                        'api_version_used'   => $apiVersion,
                    ],
                ]);
            }

            // Mapear errores comunes de Meta a mensajes accionables
            $reason = isset($body['error']['message']) ? $body['error']['message'] : 'Sin detalle del error';
            $code   = isset($body['error']['code'])    ? $body['error']['code']    : null;
            $type   = isset($body['error']['type'])    ? $body['error']['type']    : null;

            $hint = $this->mapMetaErrorToHint($status, $code, $type);

            $checks[] = $this->check('Conexión a Meta Cloud API exitosa', false,
                "HTTP {$status}: {$reason}" . ($hint ? " — {$hint}" : ''));

            return response()->json([
                'ok'      => false,
                'message' => "Meta respondió HTTP {$status}: {$reason}",
                'checks'  => $checks,
                'details' => [
                    'http_status'    => $status,
                    'meta_error'     => $body['error'] ?? null,
                    'hint'           => $hint,
                ],
            ]);

        } catch (RequestException $e) {
            $checks[] = $this->check('Conexión a Meta Cloud API exitosa', false,
                'Error de red: ' . $e->getMessage());
            return response()->json([
                'ok'      => false,
                'message' => 'Error de red al contactar Meta: ' . $e->getMessage(),
                'checks'  => $checks,
            ]);
        } catch (\Exception $e) {
            Log::error('ChatbotConnectionTest: error WhatsApp', ['error' => $e->getMessage()]);
            $checks[] = $this->check('Conexión a Meta Cloud API exitosa', false,
                'Excepción: ' . $e->getMessage());
            return response()->json([
                'ok'      => false,
                'message' => 'Error inesperado: ' . $e->getMessage(),
                'checks'  => $checks,
            ], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Email Triage — IMAP + SMTP
    |--------------------------------------------------------------------------
    */

    /**
     * POST /marketing/chatbot/email-triage/test-connection
     *
     * Hace 2 chequeos independientes:
     *   1. IMAP: conectar + autenticar + listar bandeja (no descarga mails).
     *   2. SMTP: conectar + autenticar (NO envía email).
     *
     * Devuelve resultado por cada uno; un fallo no bloquea el otro.
     */
    public function emailTriage(Request $request)
    {
        $checks = [];

        // ── IMAP ──
        $imapHost = ChatbotConfig::get('email_imap_host');
        $imapPort = (int) ChatbotConfig::get('email_imap_port', 993);
        $imapEnc  = ChatbotConfig::get('email_imap_encryption', 'ssl');
        $user     = ChatbotConfig::get('email_user');
        $pass     = ChatbotConfig::get('email_password');

        $checks[] = $this->check('IMAP host configurado',
            !empty($imapHost),
            empty($imapHost) ? 'Falta configurar email_imap_host (ej: mail.compipro.net)' : null);
        $checks[] = $this->check('Usuario configurado',
            !empty($user),
            empty($user) ? 'Falta configurar email_user' : null);
        $checks[] = $this->check('Contraseña configurada',
            !empty($pass),
            empty($pass) ? 'Falta configurar email_password (cifrada en BD)' : null);

        if (empty($imapHost) || empty($user) || empty($pass)) {
            return response()->json([
                'ok'      => false,
                'message' => 'Faltan credenciales mínimas (IMAP host + usuario + contraseña).',
                'checks'  => $checks,
            ]);
        }

        // 1) Probar IMAP
        $imapResult = $this->tryImap($imapHost, $imapPort, $imapEnc, $user, $pass);
        $checks[] = $this->check(
            sprintf('Conexión IMAP %s:%d (%s)', $imapHost, $imapPort, $imapEnc),
            $imapResult['ok'],
            $imapResult['ok'] ? sprintf('Bandeja accesible. %d mensajes en INBOX.', $imapResult['count']) : $imapResult['error']
        );

        // 2) Probar SMTP (opcional — no bloquea si no está configurado)
        $smtpHost = ChatbotConfig::get('email_smtp_host');
        $smtpPort = (int) ChatbotConfig::get('email_smtp_port', 465);
        $smtpEnc  = ChatbotConfig::get('email_smtp_encryption', 'ssl');
        // Flag para deshabilitar verificación de cert SSL (default true = seguro).
        // Útil en testing local con Docker que no tiene CA bundle actualizado, o en
        // clientes con certificados auto-firmados.
        $verifySsl = (bool) ChatbotConfig::get('email_smtp_verify_ssl', true);

        if (!empty($smtpHost)) {
            $smtpResult = $this->trySmtp($smtpHost, $smtpPort, $smtpEnc, $user, $pass, $verifySsl);
            $checks[] = $this->check(
                sprintf('Conexión SMTP %s:%d (%s)', $smtpHost, $smtpPort, $smtpEnc),
                $smtpResult['ok'],
                $smtpResult['ok'] ? 'Autenticación SMTP exitosa (no se envió email)' : $smtpResult['error']
            );
        } else {
            $checks[] = $this->check('SMTP host configurado',
                false,
                'SMTP no configurado (opcional para Email Triage — solo lectura IMAP funciona sin SMTP)');
        }

        $allOk = !in_array(false, array_column($checks, 'ok'), true);

        return response()->json([
            'ok'      => $allOk,
            'message' => $allOk
                ? 'Conexión a buzón de correo exitosa — el Email Triage puede leer y enviar.'
                : 'Hay errores en la configuración. Revisá la lista de chequeos.',
            'checks'  => $checks,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    /**
     * @param  string       $titulo
     * @param  bool         $ok
     * @param  string|null  $detalle
     * @return array
     */
    private function check($titulo, $ok, $detalle = null)
    {
        return [
            'titulo'  => $titulo,
            'ok'      => (bool) $ok,
            'detalle' => $detalle,
        ];
    }

    /**
     * Mapear errores típicos de Meta a hints accionables para el admin.
     *
     * @param  int          $httpStatus
     * @param  mixed        $code
     * @param  string|null  $type
     * @return string|null
     */
    private function mapMetaErrorToHint($httpStatus, $code, $type)
    {
        if ($httpStatus === 401 || $code === 190) {
            return 'Access Token inválido o expirado. Generar uno nuevo en Meta → WhatsApp → API Setup. Para producción usá un System User Token.';
        }
        if ($httpStatus === 404 || $code === 100) {
            return 'Phone Number ID inexistente. Verificá el ID exacto en Meta → WhatsApp → API Setup (es el ID, no el número telefónico).';
        }
        if ($httpStatus === 403) {
            return 'El Access Token no tiene permisos sobre este Phone Number. Verificá que el token y el número estén en la misma App de Meta.';
        }
        if ($httpStatus === 429) {
            return 'Rate limit de Meta. Esperá 1-2 minutos y reintentá.';
        }
        return null;
    }

    /**
     * Intentar conexión IMAP usando ext-imap nativa de PHP.
     *
     * @return array  ['ok' => bool, 'count' => int, 'error' => string|null]
     */
    private function tryImap($host, $port, $encryption, $user, $pass)
    {
        if (!function_exists('imap_open')) {
            return [
                'ok'    => false,
                'count' => 0,
                'error' => 'Extensión PHP ext-imap no instalada en el contenedor. Pedirle al DevOps instalarla.',
            ];
        }

        $flags = '';
        $enc = strtolower($encryption);
        if ($enc === 'ssl' || $enc === 'tls') {
            $flags = "/{$enc}/novalidate-cert";
        } else {
            $flags = '/notls/novalidate-cert';
        }
        $mailbox = "{" . $host . ":" . $port . $flags . "}INBOX";

        // Suprimir warnings y capturarlos vía imap_errors() / imap_alerts()
        $conn = @imap_open($mailbox, $user, $pass, OP_READONLY, 1);
        if (!$conn) {
            $errors = imap_errors();
            $errMsg = is_array($errors) ? implode(' | ', $errors) : 'Sin detalle';
            return [
                'ok'    => false,
                'count' => 0,
                'error' => 'imap_open falló: ' . $errMsg,
            ];
        }

        try {
            $count = imap_num_msg($conn);
            imap_close($conn);
            // Limpiar errores residuales que igual encolaron
            imap_errors();
            imap_alerts();
            return [
                'ok'    => true,
                'count' => (int) $count,
                'error' => null,
            ];
        } catch (\Exception $e) {
            @imap_close($conn);
            return [
                'ok'    => false,
                'count' => 0,
                'error' => 'Error después de conectar: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Intentar conexión SMTP con autenticación (sin enviar email).
     *
     * Usa fsockopen + comandos SMTP manuales para no depender de Symfony Mailer.
     *
     * @param  bool  $verifySsl  Si false, no valida certificado del servidor SMTP
     *                            (útil en testing local con Docker sin CA bundle
     *                            actualizado, o servidores con certs auto-firmados).
     * @return array  ['ok' => bool, 'error' => string|null]
     */
    private function trySmtp($host, $port, $encryption, $user, $pass, $verifySsl = true)
    {
        $enc = strtolower($encryption);
        $useImplicitSsl = ($enc === 'ssl');

        $remote = $useImplicitSsl ? "ssl://{$host}:{$port}" : "{$host}:{$port}";
        $errno  = 0;
        $errstr = '';

        // Stream context con opciones SSL — usado tanto en SSL implícito (465)
        // como STARTTLS (587). Si verifySsl=false, ignoramos verificación.
        $context = stream_context_create([
            'ssl' => [
                'verify_peer'       => $verifySsl,
                'verify_peer_name'  => $verifySsl,
                'allow_self_signed' => !$verifySsl,
            ],
        ]);

        $sock = @stream_socket_client($remote, $errno, $errstr, 10,
            STREAM_CLIENT_CONNECT, $context);
        if (!$sock) {
            return ['ok' => false, 'error' => "stream_socket_client falló: ({$errno}) {$errstr}"];
        }

        stream_set_timeout($sock, 10);

        $cmd = function ($command) use ($sock) {
            if ($command !== null) {
                fwrite($sock, $command . "\r\n");
            }
            $data = '';
            while (!feof($sock)) {
                $line = fgets($sock, 515);
                $data .= $line;
                // Las respuestas SMTP multi-línea tienen "-" después del código; última tiene espacio
                if (isset($line[3]) && $line[3] === ' ') break;
            }
            return $data;
        };

        try {
            $banner = $cmd(null);
            if (strpos($banner, '220') !== 0) {
                return ['ok' => false, 'error' => 'No banner SMTP 220: ' . trim($banner)];
            }
            $cmd('EHLO compipro.localhost');

            // STARTTLS si encryption=tls
            if ($enc === 'tls') {
                $cmd('STARTTLS');
                // Aplicar opciones SSL al socket existente para STARTTLS
                if (!$verifySsl) {
                    stream_context_set_option($sock, 'ssl', 'verify_peer', false);
                    stream_context_set_option($sock, 'ssl', 'verify_peer_name', false);
                    stream_context_set_option($sock, 'ssl', 'allow_self_signed', true);
                }
                if (!stream_socket_enable_crypto($sock, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    return ['ok' => false, 'error' => 'STARTTLS falló'];
                }
                $cmd('EHLO compipro.localhost');
            }

            $cmd('AUTH LOGIN');
            $authU = $cmd(base64_encode($user));
            if (strpos($authU, '334') !== 0 && strpos($authU, '235') !== 0) {
                // ok: 334 espera password, otros 5xx indican rechazo del usuario
            }
            $authP = $cmd(base64_encode($pass));
            if (strpos($authP, '235') !== 0) {
                $cmd('QUIT');
                fclose($sock);
                return ['ok' => false, 'error' => 'Autenticación SMTP rechazada: ' . trim($authP)];
            }

            $cmd('QUIT');
            fclose($sock);
            return ['ok' => true, 'error' => null];

        } catch (\Exception $e) {
            @fclose($sock);
            return ['ok' => false, 'error' => 'Excepción: ' . $e->getMessage()];
        }
    }
}
