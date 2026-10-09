<?php

namespace Modules\Marketing\Console;

use Hyn\Tenancy\Environment;
use Hyn\Tenancy\Models\Website;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Modules\Marketing\Models\MarketingEmailReceived;

/**
 * Mkt01EmailTriageImportEmlCommand — herramienta de testing local del MKT-01.
 *
 * Lee archivos .eml (formato MIME estándar) desde un directorio y los
 * persiste en marketing_emails_received como si los hubiera leído del IMAP.
 * Útil para:
 *   - Testear el clasificador IA con correos REALES del cliente sin necesitar
 *     ext-imap ni un buzón configurado
 *   - Reproducir bugs con correos específicos que un cliente reporta
 *   - Hacer benchmarks comparando providers (Claude vs Gemini vs OpenAI)
 *
 * NO está pensado para producción. Solo para QA + smoke pre-prod.
 *
 * Stack: PHP nativo, NO requiere ext-imap. Usa mb_decode_mimeheader (mbstring)
 * para decodificar headers MIME-encoded, y un parser MIME minimal con regex
 * para separar multipart/* y extraer text/plain.
 *
 * Uso:
 *   # Importar todos los .eml de un directorio al tenant 1
 *   php artisan marketing:email-triage:import-eml --path=/tmp/emls --tenant=1
 *
 *   # Limpiar antes de importar (borra previos importados con este comando)
 *   php artisan marketing:email-triage:import-eml --path=/tmp/emls --tenant=1 --truncate
 */
class Mkt01EmailTriageImportEmlCommand extends Command
{
    protected $signature = 'marketing:email-triage:import-eml
                            {--path= : Directorio que contiene los archivos .eml}
                            {--tenant= : ID del tenant (Website) destino}
                            {--truncate : Vaciar marketing_emails_received antes de importar (útil para tests reproducibles)}';

    protected $description = '[QA only] Importa archivos .eml desde disco a marketing_emails_received para testear el clasificador IA con correos reales.';

    public function handle()
    {
        // Algunos .eml de Outlook con adjuntos pueden pasar de 15MB. El parser
        // hace varias copias del string en memoria (preg_replace, preg_split)
        // y se queda corto con el límite por defecto (128M).
        ini_set('memory_limit', '512M');

        $path     = $this->option('path');
        $tenantId = $this->option('tenant');
        $truncate = (bool) $this->option('truncate');

        if (empty($path) || !is_dir($path)) {
            $this->error('Falta --path o no existe el directorio: ' . $path);
            return 1;
        }
        if (empty($tenantId)) {
            $this->error('Falta --tenant');
            return 1;
        }

        $website = Website::find($tenantId);
        if (!$website) {
            $this->error("Tenant {$tenantId} no encontrado");
            return 1;
        }

        app(Environment::class)->tenant($website);
        $this->info(">>> Tenant {$website->uuid} (ID {$website->id})");

        // Truncate opcional
        if ($truncate) {
            $deletedDrafts = \Modules\Marketing\Models\MarketingEmailDraft::query()->delete();
            $deletedEmails = MarketingEmailReceived::query()->delete();
            $this->warn("    [truncate] borrados {$deletedDrafts} drafts y {$deletedEmails} correos previos");
        }

        // Listar archivos .eml
        $files = glob(rtrim($path, '/') . '/*.eml');
        if (empty($files)) {
            $this->warn("    No se encontraron .eml en {$path}");
            return 0;
        }

        $persisted = 0;
        $duplicates = 0;
        $errors = 0;

        foreach ($files as $file) {
            $name = basename($file);
            try {
                $parsed = $this->parseEml(file_get_contents($file));

                // Dedup por message_id
                if (!empty($parsed['message_id'])) {
                    $exists = MarketingEmailReceived::where('message_id', $parsed['message_id'])->exists();
                    if ($exists) {
                        $duplicates++;
                        $this->line("    ↺ duplicado: {$name}");
                        continue;
                    }
                } else {
                    // Sin Message-ID, generamos uno determinístico para evitar dups en re-runs
                    $parsed['message_id'] = 'imported-' . md5($file . filemtime($file)) . '@local-import';
                }

                MarketingEmailReceived::create([
                    'message_id'      => $parsed['message_id'],
                    'imap_uid'        => null,
                    'imap_folder'     => 'IMPORTED-EML',
                    'from_email'      => $parsed['from_email'],
                    'from_name'       => $parsed['from_name'],
                    'to_email'        => $parsed['to_email'],
                    'cc_emails'       => !empty($parsed['cc_emails']) ? $parsed['cc_emails'] : null,
                    'subject'         => $parsed['subject'],
                    'body_plain'      => $parsed['body_plain'],
                    'body_html'       => $parsed['body_html'],
                    'received_at'     => $parsed['received_at'] ?: now(),
                    'has_attachments' => $parsed['has_attachments'],
                    'size_bytes'      => filesize($file),
                    'status'          => MarketingEmailReceived::STATUS_RECEIVED,
                ]);
                $persisted++;
                $this->info(sprintf(
                    '    ✓ %s  →  [%s] "%s"',
                    $name,
                    $parsed['from_email'] ?: '?',
                    mb_strimwidth((string) $parsed['subject'], 0, 60, '…')
                ));
            } catch (\Exception $e) {
                $errors++;
                $this->error("    ✗ {$name}: {$e->getMessage()}");
            }
        }

        $this->line('');
        $this->info("Resumen import: persisted={$persisted}  duplicates={$duplicates}  errors={$errors}");
        return $errors > 0 ? 1 : 0;
    }

    /*
    |--------------------------------------------------------------------------
    | Parser MIME minimal (sin ext-imap)
    |--------------------------------------------------------------------------
    */

    /**
     * Parsear un .eml crudo.
     *
     * @param  string  $raw
     * @return array {message_id,from_email,from_name,to_email,cc_emails,subject,body_plain,body_html,received_at,has_attachments}
     */
    private function parseEml($raw)
    {
        // Normalizar saltos de línea
        $raw = preg_replace("/\r\n|\r/", "\n", $raw);

        // Separar headers de body por la primera línea vacía
        $split = preg_split("/\n\n/", $raw, 2);
        $rawHeaders = $split[0] ?? '';
        $rawBody    = $split[1] ?? '';

        // Unfolding: líneas que empiezan con espacio o tab continúan la anterior
        $rawHeaders = preg_replace("/\n[ \t]+/", ' ', $rawHeaders);
        $headers = $this->parseHeaders($rawHeaders);

        // From / To / Cc / Subject / Date / Message-ID
        $fromParsed = $this->parseAddress($headers['from'] ?? '');
        $toParsed   = $this->parseAddress($headers['to']   ?? '');
        $ccList     = $this->parseAddressList($headers['cc'] ?? '');

        $subject = $this->decodeMime($headers['subject'] ?? '');
        $messageId = trim((string) ($headers['message-id'] ?? ''), " <>\t\n\r");
        $receivedAt = !empty($headers['date']) ? $this->parseDate($headers['date']) : null;

        // Body — recorrer recursivamente la estructura MIME (cualquier profundidad).
        // OJO: pasamos el contentType ORIGINAL (sin lowercase) porque los boundaries son
        // case-sensitive (ej: `_000_PH0PR06MB...` se rompe si lo bajamos a minúsculas).
        $contentType = $headers['content-type'] ?? 'text/plain';
        $encoding    = strtolower($headers['content-transfer-encoding'] ?? '');

        $extracted = ['plain' => null, 'html' => null, 'has_attachments' => false];
        $this->extractBodies($contentType, $encoding, $rawBody, $extracted);

        $bodyPlain      = $extracted['plain'];
        $bodyHtml       = $extracted['html'];
        $hasAttachments = $extracted['has_attachments'];

        // Si no hubo plain pero sí html, generamos un plain básico
        if (empty($bodyPlain) && !empty($bodyHtml)) {
            $bodyPlain = trim(html_entity_decode(strip_tags($bodyHtml), ENT_QUOTES, 'UTF-8'));
        }

        return [
            'message_id'      => $messageId,
            'from_email'      => $fromParsed['email'] ?: '(unknown)',
            'from_name'       => $fromParsed['name'],
            'to_email'        => $toParsed['email'] ?: '(unknown)',
            'cc_emails'       => $ccList,
            'subject'         => $subject,
            'body_plain'      => $bodyPlain,
            'body_html'       => $bodyHtml,
            'received_at'     => $receivedAt,
            'has_attachments' => $hasAttachments,
        ];
    }

    /**
     * Parsear bloque de headers RFC-822.
     */
    private function parseHeaders($rawHeaders)
    {
        $out = [];
        foreach (explode("\n", $rawHeaders) as $line) {
            if (preg_match('/^([A-Za-z\-]+):\s*(.*)$/', $line, $m)) {
                $key = strtolower($m[1]);
                $val = trim($m[2]);
                // Si el header se repite (ej: Received), mantenemos solo el primero
                if (!isset($out[$key])) $out[$key] = $val;
            }
        }
        return $out;
    }

    /**
     * Decodificar header MIME-encoded (=?utf-8?B?...?= o =?iso-8859-1?Q?...?=)
     */
    private function decodeMime($value)
    {
        if (empty($value)) return null;
        // mb_decode_mimeheader requiere mbstring (siempre disponible en PHP moderno)
        if (function_exists('mb_decode_mimeheader')) {
            return mb_decode_mimeheader($value);
        }
        return $value;
    }

    /**
     * Parsear "Nombre <email@x.com>" o "email@x.com" en {name, email}
     */
    private function parseAddress($value)
    {
        if (empty($value)) return ['name' => null, 'email' => null];
        $value = $this->decodeMime($value);
        if (preg_match('/^"?(.+?)"?\s*<([^>]+)>/', $value, $m)) {
            return ['name' => trim($m[1]), 'email' => trim($m[2])];
        }
        return ['name' => null, 'email' => trim($value, "<> \t")];
    }

    /**
     * Parsear lista de direcciones separadas por coma.
     */
    private function parseAddressList($value)
    {
        if (empty($value)) return [];
        $emails = [];
        foreach (explode(',', $value) as $addr) {
            $p = $this->parseAddress($addr);
            if ($p['email']) $emails[] = $p['email'];
        }
        return $emails;
    }

    /**
     * Parsear fecha del header con tolerancia.
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
     * Cortar un body multipart por boundary.
     */
    private function splitMultipart($body, $boundary)
    {
        $delim = '--' . $boundary;
        $parts = explode($delim, $body);
        // El primer y último elemento son ruido (preámbulo + epílogo)
        array_shift($parts);
        // Filtrar cierre "--"
        $parts = array_filter($parts, function ($p) {
            return trim($p) !== '' && trim($p) !== '--';
        });
        return $parts;
    }

    /**
     * Recursivamente extraer text/plain y text/html de cualquier estructura MIME,
     * sin importar la profundidad de anidación.
     *
     * Maneja correctamente:
     *   - text/plain o text/html simple → asigna a $out['plain'] o $out['html']
     *   - multipart/* (mixed/alternative/related/digest/etc.) → recurre por cada parte
     *   - 3+ niveles anidados (típico de Outlook: mixed → related → alternative → text/plain)
     *   - adjuntos detectados por Content-Disposition o por param 'name=' en CT
     *
     * Ya escribe el resultado en $out (out param) para evitar reasignaciones.
     */
    private function extractBodies($contentType, $encoding, $rawBody, array &$out)
    {
        // Comparamos type con lowercase pero mantenemos el contentType original
        // para extraer boundary (case-sensitive).
        $typeLower = strtolower(trim(strtok((string) $contentType, ';')));

        // Multipart: descomponer y recurrir
        if (strpos($typeLower, 'multipart/') === 0) {
            if (!preg_match('/boundary\s*=\s*"?([^";\n]+)"?/i', $contentType, $m)) {
                return;
            }
            $boundary = $m[1];
            foreach ($this->splitMultipart($rawBody, $boundary) as $rawPart) {
                $rawPart = ltrim($rawPart, "\n");
                $split = preg_split("/\n\n/", $rawPart, 2);
                $partHeaders = $this->parseHeaders(preg_replace("/\n[ \t]+/", ' ', $split[0] ?? ''));
                $partBody    = $split[1] ?? '';

                // Mantenemos el partCT original para boundaries case-sensitive
                $partCT = $partHeaders['content-type'] ?? 'text/plain';
                $partEnc = strtolower($partHeaders['content-transfer-encoding'] ?? '');
                $disposition = strtolower($partHeaders['content-disposition'] ?? '');

                // ¿Es adjunto?
                $isAttachment = (strpos($disposition, 'attachment') !== false)
                    || (strpos($disposition, 'inline') !== false && preg_match('/filename\s*=/i', $disposition));
                if ($isAttachment) {
                    $out['has_attachments'] = true;
                    continue;
                }

                // Recurrir (esto cubre multipart/alternative, multipart/related, etc.)
                $this->extractBodies($partCT, $partEnc, $partBody, $out);
            }
            return;
        }

        // Single-part text/*
        if (strpos($typeLower, 'text/') === 0) {
            $charset = $this->extractCharset($contentType);
            $decoded = $this->decodeBody($rawBody, $encoding, $charset);
            if ($typeLower === 'text/plain' && $out['plain'] === null) {
                $out['plain'] = trim($decoded);
            } elseif ($typeLower === 'text/html' && $out['html'] === null) {
                $out['html'] = $decoded;
            }
            return;
        }

        // Resto (image/*, application/*, etc.) — los consideramos adjuntos
        $out['has_attachments'] = true;
    }

    /**
     * Extraer charset de un Content-Type (default: UTF-8).
     */
    private function extractCharset($contentType)
    {
        if (preg_match('/charset\s*=\s*"?([\w\-]+)"?/i', $contentType, $m)) {
            return strtoupper($m[1]);
        }
        return 'UTF-8';
    }

    /**
     * Decodificar body según Content-Transfer-Encoding y normalizar a UTF-8.
     */
    private function decodeBody($raw, $encoding, $charset = 'UTF-8')
    {
        switch ($encoding) {
            case 'base64':
                $decoded = base64_decode($raw);
                break;
            case 'quoted-printable':
                $decoded = quoted_printable_decode($raw);
                break;
            default:
                $decoded = $raw;
        }
        if (strtoupper($charset) !== 'UTF-8' && function_exists('mb_convert_encoding')) {
            $decoded = @mb_convert_encoding($decoded, 'UTF-8', $charset);
        }
        return $decoded;
    }
}
