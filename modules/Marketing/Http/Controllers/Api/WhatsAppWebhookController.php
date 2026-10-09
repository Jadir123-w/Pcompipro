<?php

namespace Modules\Marketing\Http\Controllers\Api;

use Hyn\Tenancy\Environment;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Marketing\Models\ChatbotAttachment;
use Modules\Marketing\Models\ChatbotConfig;
use Modules\Marketing\Models\WhatsAppMessageLog;
use Modules\Marketing\Services\ChatbotOrchestrator;
use Modules\Marketing\Services\ConversationSessionManager;
use Modules\Marketing\Services\WhatsAppApprovalService;
use Modules\Marketing\Services\WhatsAppService;

/**
 * WhatsAppWebhookController
 *
 * Integración con WhatsApp Cloud API (Meta).
 *
 * GET  /api/marketing/chatbot/whatsapp/webhook → verificación Meta
 * POST /api/marketing/chatbot/whatsapp/webhook → recepción de mensajes
 *
 * Flujo:
 *   Meta POST → validar firma → parsear payload → Orchestrator → WhatsAppService → 200
 */
class WhatsAppWebhookController extends Controller
{
    /** @var ChatbotOrchestrator */
    private $orchestrator;

    /** @var ConversationSessionManager */
    private $sessionManager;

    /** @var WhatsAppService */
    private $whatsAppService;

    /** @var WhatsAppApprovalService */
    private $approvalService;

    public function __construct(
        ChatbotOrchestrator $orchestrator,
        ConversationSessionManager $sessionManager,
        WhatsAppService $whatsAppService,
        WhatsAppApprovalService $approvalService
    ) {
        $this->orchestrator    = $orchestrator;
        $this->sessionManager  = $sessionManager;
        $this->whatsAppService = $whatsAppService;
        $this->approvalService = $approvalService;
    }

    /**
     * Verificación del webhook (challenge de Meta).
     *
     * Meta envía un GET con hub.mode, hub.verify_token y hub.challenge.
     * Debemos responder con hub.challenge en texto plano si el token coincide.
     *
     * Notas técnicas:
     *   - Meta usa "hub.mode", "hub.verify_token", "hub.challenge" en query
     *     string. PHP convierte los dots a underscores al poblar $_GET, pero
     *     algunos proxies preservan el dot. Probamos ambas formas para ser
     *     robustos a cualquier setup intermedio.
     *   - El verify_token se lee de marketing_chatbot_config.wa_verify_token
     *     (BD) con fallback a config('marketing.chatbot.whatsapp.verify_token')
     *     para compatibilidad con instalaciones legacy que aún tenían el valor
     *     en .env.
     *   - Loguear cada intento (success o fail) ayuda mucho a diagnosticar
     *     cuando Meta muestra "no se pudo validar" sin más detalle.
     */
    public function verify(Request $request)
    {
        // Tolerancia a dots: Meta envía hub.mode / hub.verify_token / hub.challenge.
        // PHP por defecto los convierte a underscores, pero algunos setups conservan
        // el punto. Aceptamos ambas variantes.
        $mode      = $request->query('hub_mode',         $request->query('hub.mode'));
        $token     = $request->query('hub_verify_token', $request->query('hub.verify_token'));
        $challenge = $request->query('hub_challenge',    $request->query('hub.challenge'));

        // Resolver el verify_token: BD primero, .env como fallback.
        $expectedToken = ChatbotConfig::getOrEnv(
            'wa_verify_token',
            'whatsapp.verify_token',
            null
        );

        if (empty($expectedToken)) {
            Log::warning('WhatsAppWebhookController::verify — verify_token NO está configurado', [
                'received_mode'  => $mode,
                'received_token' => $token ? '[REDACTED]' : null,
                'ip'             => $request->ip(),
            ]);
            return response('Forbidden — verify_token no configurado en el ERP', 403);
        }

        $matches = ($mode === 'subscribe' && $token === $expectedToken);

        Log::info('WhatsAppWebhookController::verify — intento de verificación de Meta', [
            'mode_received' => $mode,
            'token_match'   => $matches,
            'has_challenge' => !empty($challenge),
            'ip'            => $request->ip(),
        ]);

        if ($matches) {
            return response((string) $challenge, 200)
                ->header('Content-Type', 'text/plain');
        }

        return response('Forbidden', 403);
    }

    /**
     * Recepción de mensajes entrantes de WhatsApp.
     *
     * Meta SIEMPRE espera un 200 como respuesta.
     * Si retornamos otro código, Meta reintentará el envío.
     */
    public function handle(Request $request)
    {
        // ── 1. Verificar que WhatsApp está habilitado ──
        //
        // Lee wa_enabled de BD primero (marketing_chatbot_config), con fallback
        // al .env. Antes leía SOLO del .env y como el sistema migró el switch
        // a la UI del ERP, el webhook respondía 'disabled' aunque el cliente
        // tuviera el toggle activado.  (Mismo patrón ya usado en verify()).
        $enabled = (bool) ChatbotConfig::getOrEnv(
            'wa_enabled',
            'whatsapp.enabled',
            false
        );

        if (!$enabled) {
            Log::warning('WhatsAppWebhook: payload recibido pero wa_enabled=false — se ignora', [
                'ip'                => $request->ip(),
                'object_in_payload' => $request->input('object'),
            ]);
            return response()->json(['status' => 'disabled'], 200);
        }

        // ── 2. Validar firma X-Hub-Signature-256 ──
        if (!$this->validateSignature($request)) {
            Log::warning('WhatsAppWebhook: firma inválida', [
                'ip' => $request->ip(),
            ]);
            return response()->json(['status' => 'invalid_signature'], 200);
        }

        // ── 3. Parsear el payload de Meta ──
        $payload = $request->all();

        // Verificar que es un evento de WhatsApp
        $object = isset($payload['object']) ? $payload['object'] : null;
        if ($object !== 'whatsapp_business_account') {
            return response()->json(['status' => 'ignored'], 200);
        }

        // Extraer mensajes del payload
        $messages = $this->extractMessages($payload);

        if (empty($messages)) {
            // Puede ser un status update (delivered, read) — ignorar
            return response()->json(['status' => 'no_messages'], 200);
        }

        // ── 4. Procesar cada mensaje ──
        foreach ($messages as $msg) {
            $this->processIncomingMessage($msg);
        }

        return response()->json(['status' => 'processed'], 200);
    }

    /**
     * Validar la firma HMAC-SHA256 del webhook de Meta.
     *
     * Meta firma cada webhook con tu App Secret (NO el access_token).
     * Se obtiene de Meta Business → tu app → Settings → Basic → App Secret.
     *
     * Lookup order:
     *   1. marketing_chatbot_config.wa_app_secret (cifrado en BD)
     *   2. config('marketing.chatbot.whatsapp.app_secret')  (env)
     *   3. config('marketing.chatbot.whatsapp.token')       (LEGACY, mal pero
     *       lo dejamos como último fallback para no romper instalaciones que
     *       están corriendo así actualmente)
     *
     * Si no hay ningún secret configurado → permitir (modo dev sin Meta).
     *
     * @param  Request  $request
     * @return bool
     */
    private function validateSignature(Request $request)
    {
        $signature = $request->header('X-Hub-Signature-256');

        $appSecret = $this->resolveAppSecret();

        if (empty($appSecret)) {
            // No hay secret configurado → modo dev / fresh install.
            // Permitimos el request pero loggeamos que la firma no se está validando.
            Log::warning('WhatsAppWebhook: app_secret NO configurado, firma no se valida (modo desarrollo)');
            return true;
        }

        if (empty($signature)) {
            return false;
        }

        // La firma viene como "sha256=<hash>"
        $expectedHash = 'sha256=' . hash_hmac('sha256', $request->getContent(), $appSecret);

        return hash_equals($expectedHash, $signature);
    }

    /**
     * Resolver el app_secret de Meta para validar firma del webhook.
     *
     * @return string|null
     */
    private function resolveAppSecret()
    {
        // 1) BD (cifrado)
        try {
            $val = ChatbotConfig::get('wa_app_secret');
            if (!empty($val)) {
                return $val;
            }
        } catch (\Exception $e) {
            // tabla no existe en este tenant
        }

        // 2) .env como app_secret explícito
        $val = config('marketing.chatbot.whatsapp.app_secret');
        if (!empty($val)) {
            return $val;
        }

        // 3) Legacy: usar el access token (incorrecto pero compatibilidad)
        $val = config('marketing.chatbot.whatsapp.token');
        if (!empty($val)) {
            Log::warning('WhatsAppWebhook: usando access_token como app_secret (LEGACY, configurar wa_app_secret)');
            return $val;
        }

        return null;
    }

    /**
     * Extraer mensajes del payload de Meta (texto + media: imagen, documento).
     *
     * Tipos soportados:
     *   - text     → procesado normal
     *   - image    → media_id incluido para download posterior; el caption va como texto
     *   - document → media_id + filename incluidos
     *   - audio/video/sticker → no procesables semánticamente. Generamos un
     *     mensaje sintético tipo "[el cliente envió un audio…]" para que la
     *     ejecutiva lo vea en el panel y decida.
     *
     * @param  array  $payload
     * @return array  Lista de ['wa_id', 'text', 'name', 'message_id', 'media' => [...] | null]
     */
    private function extractMessages(array $payload)
    {
        $messages = [];

        $entries = isset($payload['entry']) ? $payload['entry'] : [];

        foreach ($entries as $entry) {
            $changes = isset($entry['changes']) ? $entry['changes'] : [];

            foreach ($changes as $change) {
                $value = isset($change['value']) ? $change['value'] : [];

                // Contacto (nombre del usuario)
                $contacts = isset($value['contacts']) ? $value['contacts'] : [];
                $contactName = '';
                if (!empty($contacts)) {
                    $profile = isset($contacts[0]['profile']) ? $contacts[0]['profile'] : [];
                    $contactName = isset($profile['name']) ? $profile['name'] : '';
                }

                $rawMessages = isset($value['messages']) ? $value['messages'] : [];

                foreach ($rawMessages as $rawMsg) {
                    $type      = isset($rawMsg['type']) ? $rawMsg['type'] : '';
                    $waId      = isset($rawMsg['from']) ? $rawMsg['from'] : '';
                    $messageId = isset($rawMsg['id']) ? $rawMsg['id'] : '';

                    if ($type === 'text') {
                        $text = isset($rawMsg['text']['body']) ? trim($rawMsg['text']['body']) : '';
                        if ($text === '') continue;

                        $messages[] = [
                            'wa_id'      => $waId,
                            'text'       => $text,
                            'name'       => $contactName,
                            'message_id' => $messageId,
                            'media'      => null,
                        ];

                    } elseif ($type === 'image') {
                        $img      = isset($rawMsg['image']) ? $rawMsg['image'] : [];
                        $caption  = isset($img['caption']) ? trim($img['caption']) : '';
                        $mediaId  = isset($img['id']) ? $img['id'] : null;
                        $mimeType = isset($img['mime_type']) ? $img['mime_type'] : 'image/jpeg';

                        if (!$mediaId) continue;

                        // Si no hay caption, usamos texto sintético para que el LLM
                        // tenga contexto y la ejecutiva vea algo en la UI.
                        $text = $caption !== '' ? $caption : '(envió una imagen)';

                        $messages[] = [
                            'wa_id'      => $waId,
                            'text'       => $text,
                            'name'       => $contactName,
                            'message_id' => $messageId,
                            'media'      => [
                                'kind'      => 'image',
                                'media_id'  => $mediaId,
                                'mime_type' => $mimeType,
                                'filename'  => $mediaId . '.jpg',
                            ],
                        ];

                    } elseif ($type === 'document') {
                        $doc      = isset($rawMsg['document']) ? $rawMsg['document'] : [];
                        $caption  = isset($doc['caption']) ? trim($doc['caption']) : '';
                        $mediaId  = isset($doc['id']) ? $doc['id'] : null;
                        $mimeType = isset($doc['mime_type']) ? $doc['mime_type'] : 'application/octet-stream';
                        $filename = isset($doc['filename']) ? $doc['filename'] : ($mediaId . '.dat');

                        if (!$mediaId) continue;

                        $text = $caption !== '' ? $caption : '(envió un documento: ' . $filename . ')';

                        $messages[] = [
                            'wa_id'      => $waId,
                            'text'       => $text,
                            'name'       => $contactName,
                            'message_id' => $messageId,
                            'media'      => [
                                'kind'      => 'document',
                                'media_id'  => $mediaId,
                                'mime_type' => $mimeType,
                                'filename'  => $filename,
                            ],
                        ];

                    } elseif (in_array($type, ['audio', 'video', 'sticker', 'voice'])) {
                        // No procesables: dejamos un mensaje sintético para que
                        // la ejecutiva sepa que algo llegó (y pueda pedirle al
                        // cliente otra forma).
                        Log::info('WhatsAppWebhook: media no procesable', [
                            'type'  => $type,
                            'wa_id' => $waId,
                        ]);
                        $messages[] = [
                            'wa_id'      => $waId,
                            'text'       => '(envió un ' . $type . ' que no se puede procesar automáticamente)',
                            'name'       => $contactName,
                            'message_id' => $messageId,
                            'media'      => null,
                        ];

                    } else {
                        Log::info('WhatsAppWebhook: tipo desconocido ignorado', [
                            'type'  => $type,
                            'wa_id' => $waId,
                        ]);
                    }
                }
            }
        }

        return $messages;
    }

    /**
     * Procesar un mensaje entrante de WhatsApp.
     *
     * En Nivel 1 (approval_required=true): encolamos la respuesta del LLM en
     * chatbot_pending_messages para que la ejecutiva la apruebe.
     *
     * En Nivel 2 (approval_required=false): enviamos la respuesta directamente
     * a Meta (comportamiento legacy).
     *
     * @param  array  $msg  ['wa_id', 'text', 'name', 'message_id', 'media' => array|null]
     * @return void
     */
    private function processIncomingMessage(array $msg)
    {
        $waId      = $msg['wa_id'];
        $text      = $msg['text'];
        $name      = $msg['name'];
        $messageId = $msg['message_id'];
        $media     = isset($msg['media']) ? $msg['media'] : null;

        // Derivar session_id desde el número de WhatsApp
        $sessionId = 'wa_' . $waId;

        Log::info('WhatsAppWebhook: mensaje recibido', [
            'wa_id'      => $waId,
            'session_id' => $sessionId,
            'text'       => substr($text, 0, 100),
            'name'       => $name,
        ]);

        // ── Idempotencia: ignorar reintentos de Meta ──
        // Meta reenvía el webhook si no recibe un 200 a tiempo (p. ej. cuando el
        // LLM tarda en responder). Si este message_id ya fue registrado como
        // inbound, es un reintento: no volver a procesarlo ni responder. Es
        // crítico en modo Automatizado, donde NO hay cola de aprobación que
        // absorba el duplicado y el agente respondería dos veces.
        try {
            if (WhatsAppMessageLog::isInboundProcessed($messageId)) {
                Log::info('WhatsAppWebhook: message_id ya procesado, se ignora (reintento de Meta)', [
                    'message_id' => $messageId,
                    'session_id' => $sessionId,
                ]);
                return;
            }
        } catch (\Exception $e) {
            // Si el log de diagnóstico no está disponible, seguimos (no bloquea el flujo).
        }

        // ── Log temporal de diagnóstico (inbound) ──
        // Tabla chatbot_whatsapp_message_log: para verificar via DBeaver que
        // los mensajes están llegando al webhook. Se elimina cuando el canal
        // esté estable. No afecta el flujo operativo si falla.
        try {
            WhatsAppMessageLog::logInbound([
                'session_id'      => $sessionId,
                'wa_id'           => $waId,
                'contact_name'    => $name,
                'message_text'    => (string) $text,
                'meta_message_id' => $messageId,
                'meta'            => [
                    'has_media'      => !empty($media),
                    'media_type'     => $media && isset($media['type']) ? $media['type'] : null,
                ],
            ]);
        } catch (\Exception $e) {
            Log::warning('WhatsAppMessageLog::logInbound falló (no bloquea el flujo)', [
                'error' => $e->getMessage(),
            ]);
        }

        // ── Verificar anti-saturación ──
        if ($this->isAntiSaturationExceeded($sessionId)) {
            Log::info('WhatsAppWebhook: anti-saturación activada, no se responde', [
                'session_id' => $sessionId,
            ]);
            return;
        }

        // ── Si llegó un media (image/document), descargarlo + persistir ──
        $attachmentIds = [];
        if ($media) {
            $att = $this->downloadAndPersistMedia($media, $sessionId, $waId);
            if ($att) {
                $attachmentIds[] = $att->id;
            }
        }

        // ── Determinar si es primer mensaje (sesión nueva) ──
        $isFirstMessage = !$this->sessionManager->isSessionActive($sessionId);

        try {
            if ($isFirstMessage) {
                // Primer mensaje: crear sesión y generar saludo
                $greeting = $this->orchestrator->handleFirstMessage(
                    $sessionId,
                    'whatsapp',
                    ['channel_identifier' => $waId]
                );

                // El saludo NO requiere aprobación (es estándar y siempre el mismo
                // patrón). Lo enviamos directo o lo encolamos según la política.
                $this->dispatchOrEnqueue($sessionId, $waId, $name, $messageId, '[apertura de conversación]', $greeting);

                // Ahora procesar el mensaje real del usuario (con attachments si los hay)
                $response = $this->orchestrator->handleMessage(
                    $sessionId,
                    $text,
                    'whatsapp',
                    [
                        'channel_identifier' => $waId,
                        'attachment_ids'     => $attachmentIds,
                    ]
                );
            } else {
                // Sesión existente: procesar mensaje normal (con attachments si los hay)
                $response = $this->orchestrator->handleMessage(
                    $sessionId,
                    $text,
                    'whatsapp',
                    [
                        'channel_identifier' => $waId,
                        'attachment_ids'     => $attachmentIds,
                    ]
                );
            }

            // ── Enviar respuesta o encolarla para aprobación ──
            $this->dispatchOrEnqueue($sessionId, $waId, $name, $messageId, $text, $response);

        } catch (\Exception $e) {
            Log::error('WhatsAppWebhook: error procesando mensaje', [
                'session_id' => $sessionId,
                'wa_id'      => $waId,
                'error'      => $e->getMessage(),
            ]);

            // Mensaje de error: si Nivel 2 lo enviamos directo. Si Nivel 1
            // tampoco lo encolamos (no tiene sentido hacer aprobar un error
            // genérico) — solo loggeamos. La ejecutiva ya verá la sesión
            // abierta sin respuesta y puede actuar manualmente.
            if (!$this->approvalService->isApprovalRequired()) {
                $this->whatsAppService->sendTextMessage(
                    $waId,
                    'Disculpa, tuve un problema técnico. Por favor intenta de nuevo en unos segundos.'
                );
            }
        }
    }

    /**
     * Decidir si despachar la respuesta directamente o encolarla para aprobación.
     *
     * @param  string  $sessionId
     * @param  string  $waId
     * @param  string  $contactName
     * @param  string  $incomingMessageId
     * @param  string  $userMessage      Texto del usuario que disparó la respuesta
     * @param  array   $response          Respuesta del Orchestrator
     * @return void
     */
    private function dispatchOrEnqueue($sessionId, $waId, $contactName, $incomingMessageId, $userMessage, array $response)
    {
        // ── Log temporal de diagnóstico (outbound) ──
        // Captura lo que la IA generó (antes de aprobación de Nivel 1).
        // Útil para inspección via DBeaver durante validación del canal.
        $this->logOutboundForDiagnosis($sessionId, $waId, $contactName, $response);

        if ($this->approvalService->isApprovalRequired()) {
            // Nivel 1: encolar
            $this->approvalService->enqueue([
                'session_id'          => $sessionId,
                'wa_id'               => $waId,
                'contact_name'        => $contactName,
                'incoming_message_id' => $incomingMessageId,
                'user_message'        => $userMessage,
                'ai_response'         => $response,
            ]);
            return;
        }

        // Nivel 2: envío directo (legacy)
        $this->sendReply($waId, $response);
    }

    /**
     * Persistir el outbound en chatbot_whatsapp_message_log para diagnóstico.
     * No bloquea el flujo si falla (try/catch silencioso con warning log).
     *
     * @return void
     */
    private function logOutboundForDiagnosis($sessionId, $waId, $contactName, array $response)
    {
        try {
            $reply = isset($response['reply']) ? (string) $response['reply'] : '';
            if ($reply === '') return;  // sin texto que loguear

            $entities   = isset($response['entities_extracted']) ? $response['entities_extracted'] : null;
            $actions    = isset($response['actions']) ? $response['actions'] : null;
            $confidence = isset($response['confidence']) ? $response['confidence'] : null;

            WhatsAppMessageLog::logOutbound([
                'session_id'   => $sessionId,
                'wa_id'        => $waId,
                'contact_name' => $contactName,
                'message_text' => $reply,
                'ai_intent'    => isset($response['intent']) ? (string) $response['intent'] : null,
                'ai_provider'  => isset($response['ai_provider']) ? (string) $response['ai_provider'] : null,
                'ai_model'     => isset($response['ai_model']) ? (string) $response['ai_model'] : null,
                'meta'         => [
                    'confidence'         => $confidence,
                    'entities_extracted' => $entities,
                    'actions'            => $actions,
                ],
            ]);
        } catch (\Exception $e) {
            Log::warning('WhatsAppMessageLog::logOutbound falló (no bloquea el flujo)', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Enviar la respuesta del Orchestrator al usuario via WhatsApp.
     *
     * @param  string  $waId
     * @param  array   $response  Respuesta del Orchestrator
     * @return void
     */
    private function sendReply($waId, array $response)
    {
        $reply = isset($response['reply']) ? $response['reply'] : '';
        $actions = isset($response['actions']) ? $response['actions'] : [];

        // Enviar texto principal
        if (!empty($reply)) {
            $this->whatsAppService->sendTextMessage($waId, $reply);
        }

        // Si hay catálogo, enviar como documento
        $catalog = isset($actions['show_catalog']) ? $actions['show_catalog'] : null;
        if ($catalog && isset($catalog['url'])) {
            $line = isset($catalog['line']) ? $catalog['line'] : 'Catalogo';
            $this->whatsAppService->sendDocument(
                $waId,
                $catalog['url'],
                'Catalogo ' . $line . ' - COMPIPRO'
            );
        }
    }

    /**
     * Descargar el media de Meta y persistirlo en chatbot_attachments.
     *
     * @param  array   $media        ['kind', 'media_id', 'mime_type', 'filename']
     * @param  string  $sessionId
     * @param  string  $waId
     * @return ChatbotAttachment|null
     */
    private function downloadAndPersistMedia(array $media, $sessionId, $waId)
    {
        $mediaId  = $media['media_id'];
        $kindHint = $media['kind']; // 'image' | 'document'

        // 1) Descargar de Meta
        $result = $this->whatsAppService->downloadMedia($mediaId);
        if (!$result['success']) {
            Log::error('WhatsAppWebhook: no se pudo descargar media', [
                'media_id' => $mediaId,
                'wa_id'    => $waId,
                'error'    => $result['error'],
            ]);
            return null;
        }

        // 2) Validar tipo permitido (mismo whitelist que Web Chat)
        $realMime = $result['mime_type'] ?: $media['mime_type'];
        if (!in_array($realMime, ChatbotAttachment::$allowedMimes)) {
            Log::warning('WhatsAppWebhook: media de tipo no permitido', [
                'media_id' => $mediaId,
                'mime'     => $realMime,
            ]);
            return null;
        }

        // 3) Validar tamaño contra config (general_max_upload_mb default 5)
        $maxMb = $this->resolveMaxUploadMb();
        $maxBytes = $maxMb * 1024 * 1024;
        $sizeBytes = $result['size_bytes'];
        if ($sizeBytes > $maxBytes) {
            Log::warning('WhatsAppWebhook: media excede tamaño máximo', [
                'media_id' => $mediaId,
                'size'     => $sizeBytes,
                'max'      => $maxBytes,
            ]);
            return null;
        }

        // 4) Construir path seguro: chatbot-uploads/{tenant}/{session}/{uuid}.{ext}
        $tenantUuid = $this->getTenantUuid();
        $ext = isset(ChatbotAttachment::$extByMime[$realMime])
            ? ChatbotAttachment::$extByMime[$realMime]
            : 'bin';
        $uuid = (string) Str::uuid();
        $relativePath = 'chatbot-uploads/' . $tenantUuid . '/' . $sessionId . '/' . $uuid . '.' . $ext;

        try {
            Storage::disk('local')->put($relativePath, $result['bytes']);
        } catch (\Exception $e) {
            Log::error('WhatsAppWebhook: error guardando media en disk', [
                'media_id' => $mediaId,
                'path'     => $relativePath,
                'error'    => $e->getMessage(),
            ]);
            return null;
        }

        // 5) Mapear kind ChatbotAttachment
        $kind = ChatbotAttachment::KIND_OTHER;
        if ($kindHint === 'image') {
            $kind = ChatbotAttachment::KIND_REFERENCE;
        }
        // (Para documento dejamos OTHER; la ejecutiva puede recategorizarlo en
        // el panel a futuro si quiere.)

        // 6) Persistir metadata
        $att = ChatbotAttachment::create([
            'session_id'    => $sessionId,
            'lead_id'       => null,  // se asocia cuando el orchestrator cree lead
            'original_name' => isset($media['filename']) ? $media['filename'] : ($uuid . '.' . $ext),
            'stored_path'   => $relativePath,
            'mime_type'     => $realMime,
            'size_bytes'    => $sizeBytes,
            'kind'          => $kind,
            'ip_address'    => 'meta-webhook',
            'user_agent'    => 'WhatsApp Cloud API',
        ]);

        Log::info('WhatsAppWebhook: media persistido', [
            'attachment_id' => $att->id,
            'session_id'    => $sessionId,
            'mime'          => $realMime,
            'size'          => $sizeBytes,
            'kind'          => $kind,
        ]);

        return $att;
    }

    /**
     * Lee el límite de upload en MB desde config (default 5).
     *
     * @return int
     */
    private function resolveMaxUploadMb()
    {
        try {
            $val = ChatbotConfig::get('general_max_upload_mb');
            if ($val !== null) {
                $i = (int) $val;
                if ($i > 0 && $i <= 50) return $i;
            }
        } catch (\Exception $e) { /* tabla puede no existir */ }
        return 5;
    }

    /**
     * UUID del tenant Hyn actual para segmentar storage.
     *
     * @return string
     */
    private function getTenantUuid()
    {
        try {
            $env = app(Environment::class);
            $website = $env->website();
            if ($website && !empty($website->uuid)) {
                return $website->uuid;
            }
            if ($website && !empty($website->id)) {
                return 'tenant-' . $website->id;
            }
        } catch (\Exception $e) { /* sin tenant context */ }
        return 'unknown';
    }

    /**
     * Verificar si se ha excedido el límite de mensajes consecutivos del bot.
     *
     * Evita que el bot sature al usuario enviando muchos mensajes seguidos
     * sin que el usuario haya respondido.
     *
     * @param  string  $sessionId
     * @return bool    true si se excedió el límite
     */
    private function isAntiSaturationExceeded($sessionId)
    {
        // wa_max_consecutive lo configura la ejecutiva desde Config Chatbot (BD).
        // Fallback al .env por compatibilidad. Default 2.
        $maxConsecutive = (int) ChatbotConfig::getOrEnv(
            'wa_max_consecutive',
            'whatsapp_max_consecutive',
            2
        );
        $history = $this->sessionManager->getHistory($sessionId);

        if (empty($history)) {
            return false;
        }

        // Contar mensajes consecutivos del bot al final del historial
        $consecutiveBot = 0;
        $reversed = array_reverse($history);

        foreach ($reversed as $msg) {
            $role = isset($msg['role']) ? $msg['role'] : '';
            if ($role === 'assistant') {
                $consecutiveBot++;
            } else {
                break; // El usuario respondió, romper la cuenta
            }
        }

        if ($consecutiveBot >= $maxConsecutive) {
            // Cerrar sesión por saturación
            $this->sessionManager->closeSession($sessionId, 'max_consecutive_reached');
            return true;
        }

        return false;
    }
}
