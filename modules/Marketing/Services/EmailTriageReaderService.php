<?php

namespace Modules\Marketing\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Modules\Marketing\Models\ChatbotConfig;
use Modules\Marketing\Models\MarketingEmailReceived;

/**
 * EmailTriageReaderService — MKT-01 ET1 (lectura IMAP).
 *
 * Lee correos no leídos de la bandeja IMAP configurada y los persiste en
 * marketing_emails_received con status='received', listos para que el
 * clasificador IA (ET2) los procese.
 *
 * Características:
 *   - Deduplicación por Message-ID (header RFC-5322).
 *   - Marca como leídos (\Seen) los correos que persiste OK, así no los
 *     vuelve a procesar el siguiente run.
 *   - Si un correo individual falla al parsear, se loguea y sigue con el
 *     resto (no aborta el run completo).
 *   - Configurable: lee credenciales IMAP de marketing_chatbot_config con
 *     fallback al .env (vía ChatbotConfig::getOrEnv).
 *
 * Stack: PHP nativo ext-imap (sin dependencias externas adicionales).
 *
 * Uso típico (desde un comando artisan o un job en cola):
 *
 *   $svc = new EmailTriageReaderService();
 *   $result = $svc->read(50);  // lee hasta 50 correos no procesados
 *   // ['fetched' => 12, 'persisted' => 10, 'duplicates' => 2, 'errors' => 0]
 */
class EmailTriageReaderService
{
    /**
     * Leer hasta $limit correos no leídos del IMAP y persistirlos.
     *
     * @param  int  $limit  Máximo de correos a procesar en este run
     * @return array        ['fetched','persisted','duplicates','errors','error_message','enabled','circuit_breaker_tripped']
     */
    public function read($limit = 50)
    {
        $result = [
            'fetched'                 => 0,
            'persisted'               => 0,
            'duplicates'              => 0,
            'errors'                  => 0,
            'error_message'           => null,
            'enabled'                 => false,
            'circuit_breaker_tripped' => false,
        ];

        // ── 1) Verificar que el módulo Email Triage esté habilitado ──
        $enabled = (bool) ChatbotConfig::getOrEnv('email_enabled', 'email.enabled', false);
        if (!$enabled) {
            $result['error_message'] = 'Email Triage deshabilitado (email_enabled=false). Activarlo desde Config Chatbot.';
            return $result;
        }
        $result['enabled'] = true;

        // ── 1b) Circuit breaker: si acumulamos N fallos consecutivos, abortar ──
        //
        // Diseñado para prevenir bans del firewall del hosting (regla LF_IMAPD
        // del CSF: 12 logins IMAP fallidos en 1h = ban temporal, 4 bans en 24h =
        // PERMBLOCK).  Con polling cada 15min + 3 retries internos de ext-imap,
        // 3 polls fallidos consecutivos = 9 logins fallidos = MUY cerca del
        // límite de 12.  Por eso el default del breaker es 3.
        //
        // Cuando salta el breaker:
        //   1. Este return temprano evita el imap_open() que generaría más fails.
        //   2. recordFailure() ya deshabilitó email_enabled cuando llegó al máx,
        //      así que el próximo run también va a cortar en el paso 1.
        $breakerStatus = $this->getCircuitBreakerStatus();
        if ($breakerStatus['tripped']) {
            $result['error_message']           = $breakerStatus['message'];
            $result['circuit_breaker_tripped'] = true;
            Log::warning('EmailTriageReaderService: circuit breaker activo — aborting', $breakerStatus);
            return $result;
        }

        // ── 2) Verificar que ext-imap esté disponible ──
        if (!function_exists('imap_open')) {
            $result['error_message'] = 'Extensión PHP ext-imap no disponible. Pedirle al DevOps instalarla en el contenedor.';
            return $result;
        }

        // ── 3) Resolver credenciales ──
        $host       = ChatbotConfig::getOrEnv('email_imap_host',       'email.imap_host',       null);
        $port       = (int) ChatbotConfig::getOrEnv('email_imap_port', 'email.imap_port',       993);
        $encryption = ChatbotConfig::getOrEnv('email_imap_encryption', 'email.imap_encryption', 'ssl');
        $user       = ChatbotConfig::getOrEnv('email_user',            'email.user',            null);
        $pass       = ChatbotConfig::getOrEnv('email_password',        'email.password',        null);
        $folder     = ChatbotConfig::getOrEnv('email_folder_inbox',    'email.folder_inbox',    'INBOX');

        if (empty($host) || empty($user) || empty($pass)) {
            $result['error_message'] = 'Faltan credenciales IMAP (host, usuario, contraseña). Completar en Config Chatbot.';
            return $result;
        }

        // ── 4) Construir mailbox + abrir conexión ──
        $flags = $this->buildImapFlags($encryption);
        $mailbox = '{' . $host . ':' . $port . $flags . '}' . $folder;

        $conn = @imap_open($mailbox, $user, $pass, OP_HALFOPEN);  // HALFOPEN: no abre folder, abre cuenta
        if (!$conn) {
            $errors  = imap_errors();
            $errMsg  = is_array($errors) ? implode(' | ', $errors) : 'sin detalle';

            // Circuit breaker: registrar el fallo.  Si llegamos al máximo, también
            // se deshabilita email_enabled automáticamente para frenar el cron.
            $this->recordFailure($errMsg);

            $result['error_message'] = 'imap_open falló: ' . $errMsg;
            return $result;
        }
        // Re-abrir contra el folder específico (con flags de lectura por default)
        if (!imap_reopen($conn, $mailbox)) {
            imap_close($conn);
            $reopenErr = 'No se pudo abrir el folder ' . $folder;
            $this->recordFailure($reopenErr);
            $result['error_message'] = $reopenErr;
            return $result;
        }

        // Login + reopen exitoso → resetear el contador del breaker (si venía > 0)
        $this->recordSuccess();

        try {
            // ── 5) Buscar UIDs no leídos ──
            $uids = imap_search($conn, 'UNSEEN', SE_UID);
            if ($uids === false) $uids = [];

            $result['fetched'] = count($uids);

            // Procesar hasta $limit
            $toProcess = array_slice($uids, 0, max(1, (int) $limit));

            foreach ($toProcess as $uid) {
                try {
                    $persisted = $this->processUid($conn, $uid, $folder);
                    if ($persisted === 'persisted') {
                        $result['persisted']++;
                        // Marcar como leído (\Seen) — para no volver a procesarlo
                        @imap_setflag_full($conn, (string) $uid, '\\Seen', ST_UID);
                    } elseif ($persisted === 'duplicate') {
                        $result['duplicates']++;
                        // También lo marcamos como leído para limpiar la bandeja
                        @imap_setflag_full($conn, (string) $uid, '\\Seen', ST_UID);
                    }
                } catch (\Exception $e) {
                    $result['errors']++;
                    Log::error('EmailTriageReaderService: error procesando UID', [
                        'uid'   => $uid,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        } finally {
            imap_close($conn);
            imap_errors();  // limpiar errores residuales
            imap_alerts();
        }

        Log::info('EmailTriageReaderService: run completado', $result);
        return $result;
    }

    /**
     * Procesar un UID concreto: parsear y persistir si no es duplicado.
     *
     * @param  resource  $conn
     * @param  int       $uid
     * @param  string    $folder
     * @return string    'persisted' | 'duplicate'
     */
    private function processUid($conn, $uid, $folder)
    {
        // Header completo
        $header = imap_rfc822_parse_headers(imap_fetchheader($conn, $uid, FT_UID));
        $messageId = isset($header->message_id) ? trim($header->message_id, '<> ') : null;

        if (empty($messageId)) {
            // Generar uno sintético basado en uid + fecha si el correo vino sin Message-ID
            // (raro pero posible con remitentes mal configurados)
            $messageId = 'no-msgid-' . $uid . '-' . md5(json_encode($header));
        }

        // Deduplicar
        $existing = MarketingEmailReceived::where('message_id', $messageId)->first();
        if ($existing) {
            return 'duplicate';
        }

        // Parsear from / to / subject
        $fromEmail = '';
        $fromName  = null;
        if (!empty($header->from) && is_array($header->from) && isset($header->from[0])) {
            $f = $header->from[0];
            $fromEmail = (isset($f->mailbox) ? $f->mailbox : '') . '@' . (isset($f->host) ? $f->host : '');
            $fromName  = isset($f->personal) ? $this->decodeMimeHeader($f->personal) : null;
        }

        $toEmail = '';
        if (!empty($header->to) && is_array($header->to) && isset($header->to[0])) {
            $t = $header->to[0];
            $toEmail = (isset($t->mailbox) ? $t->mailbox : '') . '@' . (isset($t->host) ? $t->host : '');
        }

        $ccEmails = [];
        if (!empty($header->cc) && is_array($header->cc)) {
            foreach ($header->cc as $cc) {
                $ccEmails[] = (isset($cc->mailbox) ? $cc->mailbox : '') . '@' . (isset($cc->host) ? $cc->host : '');
            }
        }

        $subject = isset($header->subject) ? $this->decodeMimeHeader($header->subject) : null;
        $receivedAt = isset($header->date) ? $this->parseDate($header->date) : null;

        // Cuerpo
        $structure = imap_fetchstructure($conn, $uid, FT_UID);
        $bodyPlain = $this->extractBody($conn, $uid, $structure, 'TEXT/PLAIN');
        $bodyHtml  = $this->extractBody($conn, $uid, $structure, 'TEXT/HTML');

        // Adjuntos: detectar presencia (no descargar todavía — eso es para ET2/ET3)
        $hasAttachments = $this->detectAttachments($structure);
        $sizeBytes = isset($structure->bytes) ? (int) $structure->bytes : null;

        // Persistir
        MarketingEmailReceived::create([
            'message_id'      => $messageId,
            'imap_uid'        => $uid,
            'imap_folder'     => $folder,
            'from_email'      => $fromEmail,
            'from_name'       => $fromName,
            'to_email'        => $toEmail,
            'cc_emails'       => !empty($ccEmails) ? $ccEmails : null,
            'subject'         => $subject,
            'body_plain'      => $bodyPlain,
            'body_html'       => $bodyHtml,
            'received_at'     => $receivedAt,
            'has_attachments' => $hasAttachments,
            'size_bytes'      => $sizeBytes,
            'status'          => MarketingEmailReceived::STATUS_RECEIVED,
        ]);

        return 'persisted';
    }

    /**
     * Construir el sufijo de mailbox para imap_open según encryption.
     */
    private function buildImapFlags($encryption)
    {
        $enc = strtolower((string) $encryption);
        if ($enc === 'ssl' || $enc === 'tls') {
            return "/{$enc}/novalidate-cert";
        }
        return '/notls/novalidate-cert';
    }

    /**
     * Decodificar header MIME (e.g. =?utf-8?B?...?=).
     */
    private function decodeMimeHeader($value)
    {
        if (empty($value)) return null;
        $parts = imap_mime_header_decode($value);
        $out = '';
        foreach ($parts as $p) {
            $charset = isset($p->charset) && $p->charset !== 'default' ? $p->charset : 'UTF-8';
            $text    = isset($p->text) ? $p->text : '';
            // Si el charset declarado no es UTF-8 lo convertimos
            if (strtoupper($charset) !== 'UTF-8' && function_exists('mb_convert_encoding')) {
                $text = @mb_convert_encoding($text, 'UTF-8', $charset);
            }
            $out .= $text;
        }
        return $out;
    }

    /**
     * Parsear fecha del header.
     */
    private function parseDate($dateStr)
    {
        try {
            return Carbon::parse($dateStr);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Extraer cuerpo del correo del tipo MIME indicado (TEXT/PLAIN o TEXT/HTML).
     *
     * Recorre la estructura buscando el primer body part que coincida.
     */
    private function extractBody($conn, $uid, $structure, $mimeType)
    {
        if (!$structure) return null;

        // Mensaje simple sin parts
        if (!isset($structure->parts) || empty($structure->parts)) {
            $thisType = $this->mimeTypeOf($structure);
            if ($thisType === $mimeType) {
                $raw = imap_fetchbody($conn, $uid, '1', FT_UID);
                return $this->decodeBody($raw, $structure);
            }
            return null;
        }

        // Multipart: buscar la part correcta recursivamente
        return $this->findBodyPart($conn, $uid, $structure->parts, $mimeType, '');
    }

    /**
     * Recursivamente buscar una part con el MIME type indicado.
     */
    private function findBodyPart($conn, $uid, $parts, $mimeType, $section)
    {
        foreach ($parts as $i => $part) {
            $partSection = $section === '' ? (string) ($i + 1) : $section . '.' . ($i + 1);
            $thisType = $this->mimeTypeOf($part);

            if ($thisType === $mimeType) {
                $raw = imap_fetchbody($conn, $uid, $partSection, FT_UID);
                return $this->decodeBody($raw, $part);
            }

            if (!empty($part->parts)) {
                $nested = $this->findBodyPart($conn, $uid, $part->parts, $mimeType, $partSection);
                if ($nested !== null) return $nested;
            }
        }
        return null;
    }

    /**
     * Decodificar el body según encoding (base64, quoted-printable, etc.) y charset.
     */
    private function decodeBody($raw, $part)
    {
        $encoding = isset($part->encoding) ? (int) $part->encoding : 0;
        switch ($encoding) {
            case 3: $decoded = base64_decode($raw); break;            // BASE64
            case 4: $decoded = quoted_printable_decode($raw); break;  // QUOTED-PRINTABLE
            default: $decoded = $raw;
        }

        // Normalizar a UTF-8
        $charset = 'UTF-8';
        if (!empty($part->parameters)) {
            foreach ($part->parameters as $p) {
                if (strtoupper($p->attribute) === 'CHARSET') {
                    $charset = $p->value;
                    break;
                }
            }
        }
        if (strtoupper($charset) !== 'UTF-8' && function_exists('mb_convert_encoding')) {
            $decoded = @mb_convert_encoding($decoded, 'UTF-8', $charset);
        }
        return $decoded;
    }

    /**
     * Extraer "TYPE/SUBTYPE" string de un objeto structure de imap_fetchstructure.
     */
    private function mimeTypeOf($part)
    {
        $types = ['TEXT', 'MULTIPART', 'MESSAGE', 'APPLICATION', 'AUDIO', 'IMAGE', 'VIDEO', 'OTHER'];
        $primary = isset($part->type) ? ($types[$part->type] ?? 'OTHER') : 'OTHER';
        $sub = isset($part->subtype) ? strtoupper($part->subtype) : '';
        return $primary . '/' . $sub;
    }

    /**
     * Detectar si el correo tiene adjuntos.
     */
    private function detectAttachments($structure)
    {
        if (!$structure || empty($structure->parts)) return false;
        return $this->anyPartIsAttachment($structure->parts);
    }

    private function anyPartIsAttachment($parts)
    {
        foreach ($parts as $p) {
            if (isset($p->ifdisposition) && $p->ifdisposition && isset($p->disposition)
                && strtoupper($p->disposition) === 'ATTACHMENT') {
                return true;
            }
            if (!empty($p->parts) && $this->anyPartIsAttachment($p->parts)) return true;
        }
        return false;
    }

    /*
    |--------------------------------------------------------------------------
    | Circuit Breaker — protección contra bans del firewall del hosting
    |--------------------------------------------------------------------------
    |
    | Lee/escribe 4 claves de marketing_chatbot_config:
    |   - email_imap_circuit_breaker_max_failures (configurable, default 3)
    |   - email_imap_consecutive_failures        (state, contador)
    |   - email_imap_last_failure_at             (state, timestamp)
    |   - email_imap_last_failure_reason         (state, mensaje)
    |
    | Las 4 claves se siembran con la migración
    | 2026_05_17_000001_add_email_imap_circuit_breaker_config.
    */

    /**
     * ¿El breaker está activo (= no debe intentar conexión IMAP)?
     *
     * @return array  ['tripped' => bool, 'failures' => int, 'max' => int,
     *                 'last_failure_at' => string|null, 'last_failure_reason' => string|null,
     *                 'message' => string|null]
     */
    public function getCircuitBreakerStatus()
    {
        $failures = (int) ChatbotConfig::getOrEnv('email_imap_consecutive_failures', null, 0);
        $max      = (int) ChatbotConfig::getOrEnv('email_imap_circuit_breaker_max_failures', null, 3);
        if ($max < 1) $max = 3;  // Defensa: max=0 deshabilitaría el breaker accidentalmente

        $tripped = $failures >= $max;
        $status = [
            'tripped'             => $tripped,
            'failures'            => $failures,
            'max'                 => $max,
            'last_failure_at'     => ChatbotConfig::get('email_imap_last_failure_at'),
            'last_failure_reason' => ChatbotConfig::get('email_imap_last_failure_reason'),
            'message'             => null,
        ];

        if ($tripped) {
            $status['message'] = sprintf(
                'Circuit breaker ACTIVO: %d fallos consecutivos de login IMAP (máx permitido: %d). '
                . 'El bot está pausado para evitar bans del hosting. '
                . 'Último fallo: %s — motivo: %s. '
                . 'Acción: corregir credenciales en Config Chatbot y luego correr '
                . '"php artisan marketing:email-triage:reset-breaker" para reactivar.',
                $failures,
                $max,
                $status['last_failure_at']     ?: '(sin timestamp)',
                $status['last_failure_reason'] ?: '(sin motivo)'
            );
        }

        return $status;
    }

    /**
     * Registrar un fallo de login IMAP. Incrementa el contador y, si llegamos
     * al máximo, deshabilita email_enabled para frenar el cron.
     *
     * Público para que el ChatbotConnectionTestController y los tests puedan
     * orquestar el breaker programáticamente.
     *
     * @param  string  $errorMessage
     * @return void
     */
    public function recordFailure($errorMessage)
    {
        $current = (int) ChatbotConfig::getOrEnv('email_imap_consecutive_failures', null, 0);
        $max     = (int) ChatbotConfig::getOrEnv('email_imap_circuit_breaker_max_failures', null, 3);
        if ($max < 1) $max = 3;

        $newCount = $current + 1;

        // Truncar el mensaje a 500 chars para no llenar la BD con stacks gigantes
        $reason = mb_substr((string) $errorMessage, 0, 500);

        ChatbotConfig::set('email_imap_consecutive_failures', $newCount);
        ChatbotConfig::set('email_imap_last_failure_at', date('Y-m-d H:i:s'));
        ChatbotConfig::set('email_imap_last_failure_reason', $reason);

        Log::warning('EmailTriageReaderService: fallo de login IMAP registrado', [
            'consecutive_failures' => $newCount,
            'max_allowed'          => $max,
            'error'                => $reason,
        ]);

        if ($newCount >= $max) {
            // Disparar el breaker: apagar el módulo + log critical.  Así el
            // próximo poll del cron va a cortar en el chequeo de email_enabled
            // y no va a sumar más logins fallidos contra el server IMAP.
            ChatbotConfig::set('email_enabled', false);

            Log::critical(
                'EmailTriageReaderService: CIRCUIT BREAKER DISPARADO — Email Triage deshabilitado automáticamente '
                . 'tras ' . $newCount . ' fallos consecutivos. Revisar credenciales IMAP. '
                . 'Reactivar con: php artisan marketing:email-triage:reset-breaker',
                ['last_error' => $reason]
            );
        }
    }

    /**
     * Login IMAP exitoso → reset del contador.
     *
     * Público para que el ChatbotConnectionTestController y los tests puedan
     * orquestar el breaker programáticamente.
     *
     * @return void
     */
    public function recordSuccess()
    {
        $current = (int) ChatbotConfig::getOrEnv('email_imap_consecutive_failures', null, 0);
        if ($current > 0) {
            ChatbotConfig::set('email_imap_consecutive_failures', 0);
            ChatbotConfig::set('email_imap_last_failure_at', null);
            ChatbotConfig::set('email_imap_last_failure_reason', null);
            Log::info('EmailTriageReaderService: login IMAP exitoso, contador del circuit breaker reseteado', [
                'previous_failures' => $current,
            ]);
        }
    }
}
