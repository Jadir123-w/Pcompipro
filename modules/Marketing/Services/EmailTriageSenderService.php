<?php

namespace Modules\Marketing\Services;

use Illuminate\Support\Facades\Log;
use Modules\Marketing\Models\ChatbotConfig;
use Modules\Marketing\Models\MarketingEmailDraft;

/**
 * EmailTriageSenderService — MKT-01 Email Triage ET3 (envío SMTP).
 *
 * Toma un MarketingEmailDraft en estado `approved` o `send_error` y lo
 * envía vía SMTP usando las credenciales del tenant (BD primero, .env como
 * fallback).
 *
 * Stack: PHP nativo (fsockopen + comandos SMTP) — sin dependencias adicionales.
 * El mismo patrón que ChatbotConnectionTestController::trySmtp pero con
 * MAIL FROM / RCPT TO / DATA reales para enviar el correo.
 *
 * Soporta:
 *   - SSL implícito (puerto 465)
 *   - STARTTLS (puerto 587 / 25)
 *   - Sin encryption (no recomendado)
 *
 * NO soporta (queda para ET4 si se necesita):
 *   - Adjuntos
 *   - HTML body (solo texto plano por ahora — el LLM redacta plain text)
 *   - Reply-To / In-Reply-To threading
 */
class EmailTriageSenderService
{
    /** @var resource|null */
    private $sock;

    /**
     * Enviar un borrador aprobado vía SMTP.
     *
     * @param  MarketingEmailDraft  $draft
     * @return array  ['ok'=>bool, 'message_id'=>?string, 'error'=>?string]
     */
    public function send(MarketingEmailDraft $draft)
    {
        // ── 1) Validar estado ──
        if (!in_array($draft->status, [
            MarketingEmailDraft::STATUS_APPROVED,
            MarketingEmailDraft::STATUS_SEND_ERROR,
        ], true)) {
            return [
                'ok'         => false,
                'message_id' => null,
                'error'      => 'El borrador no está en estado aprobado (estado actual: ' . $draft->status . ')',
            ];
        }

        // ── 2) Resolver credenciales SMTP ──
        $host       = ChatbotConfig::getOrEnv('email_smtp_host',       'email.smtp_host',       null);
        $port       = (int) ChatbotConfig::getOrEnv('email_smtp_port', 'email.smtp_port',       465);
        $encryption = ChatbotConfig::getOrEnv('email_smtp_encryption', 'email.smtp_encryption', 'ssl');
        $user       = ChatbotConfig::getOrEnv('email_user',            'email.user',            null);
        $pass       = ChatbotConfig::getOrEnv('email_password',        'email.password',        null);
        $fromName   = ChatbotConfig::getOrEnv('email_default_from_name','email.default_from_name', 'Equipo COMPIPRO');
        // Flag para deshabilitar verificación de cert SSL (útil en testing local con Docker
        // que no tiene CA bundle actualizado, o en clientes con certificados auto-firmados).
        // En PRODUCCIÓN dejar siempre true. Default: true (seguro).
        $verifySsl  = (bool) ChatbotConfig::getOrEnv('email_smtp_verify_ssl', 'email.smtp_verify_ssl', true);

        if (empty($host) || empty($user) || empty($pass)) {
            $err = 'Faltan credenciales SMTP (host/usuario/contraseña). Completar en Config Chatbot.';
            $draft->markSendError($err);
            return ['ok' => false, 'message_id' => null, 'error' => $err];
        }

        // ── 3) Conectar ──
        $enc = strtolower($encryption);
        $remote = ($enc === 'ssl') ? "ssl://{$host}:{$port}" : "{$host}:{$port}";

        // Si el flag verify_ssl está en false, construimos un stream context que
        // ignore la verificación de certificado. Esto cubre tanto SSL implícito
        // (puerto 465) como STARTTLS (puerto 587).
        $context = stream_context_create([
            'ssl' => [
                'verify_peer'       => $verifySsl,
                'verify_peer_name'  => $verifySsl,
                'allow_self_signed' => !$verifySsl,
            ],
        ]);

        $errno = 0; $errstr = '';
        $this->sock = @stream_socket_client($remote, $errno, $errstr, 15,
            STREAM_CLIENT_CONNECT, $context);
        if (!$this->sock) {
            $err = "stream_socket_client falló: ({$errno}) {$errstr}";
            $draft->markSendError($err);
            return ['ok' => false, 'message_id' => null, 'error' => $err];
        }
        stream_set_timeout($this->sock, 30);

        try {
            // ── 4) Handshake SMTP ──
            $banner = $this->readResponse();
            if (substr($banner, 0, 3) !== '220') {
                throw new \RuntimeException('Banner SMTP inválido: ' . trim($banner));
            }

            $this->sendCmd('EHLO compipro.localhost');

            // STARTTLS si encryption=tls
            if ($enc === 'tls') {
                $this->sendCmd('STARTTLS', '220');
                // Para STARTTLS en sockets ya abiertos, necesitamos aplicar las
                // opciones SSL al socket existente vía stream_context_set_option.
                if (!$verifySsl) {
                    stream_context_set_option($this->sock, 'ssl', 'verify_peer', false);
                    stream_context_set_option($this->sock, 'ssl', 'verify_peer_name', false);
                    stream_context_set_option($this->sock, 'ssl', 'allow_self_signed', true);
                }
                if (!stream_socket_enable_crypto($this->sock, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new \RuntimeException('STARTTLS falló al negociar TLS.');
                }
                $this->sendCmd('EHLO compipro.localhost');
            }

            // ── 5) AUTH LOGIN ──
            $this->sendCmd('AUTH LOGIN', '334');
            $this->sendCmd(base64_encode($user), '334');
            $authResp = $this->sendCmdRaw(base64_encode($pass));
            if (substr($authResp, 0, 3) !== '235') {
                throw new \RuntimeException('Autenticación SMTP rechazada: ' . trim($authResp));
            }

            // ── 6) MAIL FROM / RCPT TO ──
            $this->sendCmd("MAIL FROM:<{$user}>", '250');
            $this->sendCmd("RCPT TO:<{$draft->to_email}>", '250');
            // CCs
            if (is_array($draft->cc_emails)) {
                foreach ($draft->cc_emails as $cc) {
                    if (filter_var($cc, FILTER_VALIDATE_EMAIL)) {
                        $this->sendCmd("RCPT TO:<{$cc}>", '250');
                    }
                }
            }

            // ── 7) DATA ──
            $this->sendCmd('DATA', '354');

            $messageId = $this->generateMessageId($host);
            $payload = $this->buildEmailPayload(
                $user,
                $fromName,
                $draft->to_email,
                is_array($draft->cc_emails) ? $draft->cc_emails : [],
                $draft->draft_subject,
                $draft->draft_body,
                $messageId
            );
            $finalResp = $this->sendCmdRaw($payload . "\r\n.");
            if (substr($finalResp, 0, 3) !== '250') {
                throw new \RuntimeException('Servidor rechazó el cuerpo: ' . trim($finalResp));
            }

            // ── 8) QUIT ──
            $this->sendCmdRaw('QUIT');
            fclose($this->sock);
            $this->sock = null;

            // Marcar como enviado
            $draft->markSent($messageId);

            Log::info('EmailTriageSenderService: enviado', [
                'draft_id'   => $draft->id,
                'to'         => $draft->to_email,
                'message_id' => $messageId,
            ]);

            return ['ok' => true, 'message_id' => $messageId, 'error' => null];

        } catch (\Exception $e) {
            if (is_resource($this->sock)) {
                @fclose($this->sock);
                $this->sock = null;
            }
            $err = $e->getMessage();
            $draft->markSendError($err);
            Log::error('EmailTriageSenderService: error enviando', [
                'draft_id' => $draft->id,
                'error'    => $err,
            ]);
            return ['ok' => false, 'message_id' => null, 'error' => $err];
        }
    }

    /**
     * Enviar un comando SMTP y validar el código de respuesta esperado.
     */
    private function sendCmd($cmd, $expectedCode = null)
    {
        $resp = $this->sendCmdRaw($cmd);
        if ($expectedCode !== null && substr($resp, 0, 3) !== $expectedCode) {
            throw new \RuntimeException("Comando '{$cmd}' falló: {$resp}");
        }
        return $resp;
    }

    /**
     * Enviar un comando SMTP y devolver la respuesta sin validar.
     */
    private function sendCmdRaw($cmd)
    {
        if ($cmd !== null) {
            fwrite($this->sock, $cmd . "\r\n");
        }
        return $this->readResponse();
    }

    /**
     * Leer una respuesta SMTP (multi-línea termina con código + espacio).
     */
    private function readResponse()
    {
        $data = '';
        while (!feof($this->sock)) {
            $line = fgets($this->sock, 515);
            $data .= $line;
            // Multi-línea: el código viene seguido de '-' (continúa) o ' ' (último).
            if (isset($line[3]) && $line[3] === ' ') break;
        }
        return $data;
    }

    /**
     * Construir el payload RFC-822 del correo (headers + body).
     */
    private function buildEmailPayload($fromEmail, $fromName, $toEmail, array $ccEmails, $subject, $body, $messageId)
    {
        $boundary = 'compipro-' . md5(uniqid('', true));

        $headers = [];
        $headers[] = "From: " . $this->encodeHeader($fromName) . " <{$fromEmail}>";
        $headers[] = "To: <{$toEmail}>";
        if (!empty($ccEmails)) {
            $headers[] = "Cc: " . implode(', ', array_map(function ($cc) { return "<{$cc}>"; }, $ccEmails));
        }
        $headers[] = "Subject: " . $this->encodeHeader($subject);
        $headers[] = "Message-ID: <{$messageId}>";
        $headers[] = "Date: " . date('r');
        $headers[] = "MIME-Version: 1.0";
        $headers[] = "Content-Type: text/plain; charset=UTF-8";
        $headers[] = "Content-Transfer-Encoding: 8bit";

        return implode("\r\n", $headers) . "\r\n\r\n" . $this->dotStuff((string) $body);
    }

    /**
     * Codificar header con caracteres no-ASCII (RFC-2047).
     */
    private function encodeHeader($value)
    {
        if (empty($value)) return '';
        $value = (string) $value;
        // Solo encode si tiene caracteres no-ASCII
        if (preg_match('/[^\x20-\x7E]/', $value)) {
            return '=?UTF-8?B?' . base64_encode($value) . '?=';
        }
        return $value;
    }

    /**
     * "Dot stuffing" RFC-5321: una línea que empieza con '.' debe escaparse
     * como '..' para que SMTP no la interprete como fin del DATA.
     */
    private function dotStuff($body)
    {
        // Normalizar saltos de línea a CRLF
        $body = preg_replace("/\r\n|\r|\n/", "\r\n", $body);
        // Doble el punto al inicio de línea
        $body = preg_replace('/^\./m', '..', $body);
        return $body;
    }

    /**
     * Generar un Message-ID único.
     */
    private function generateMessageId($host)
    {
        $domain = preg_replace('/^smtp\./i', '', (string) $host);
        return uniqid('compipro-mkt01-') . '@' . $domain;
    }
}
