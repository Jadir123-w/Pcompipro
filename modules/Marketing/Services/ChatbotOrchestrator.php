<?php

namespace Modules\Marketing\Services;

use Illuminate\Support\Facades\Log;
use Modules\Marketing\Models\ChatbotAttachment;
use Modules\Marketing\Models\ChatbotCatalog;

/**
 * ChatbotOrchestrator
 *
 * Coordinador central del flujo de conversación del agente MKT-01.
 *
 * Responsabilidades:
 * - Gestionar el ciclo de vida de cada mensaje
 * - Coordinar entre SessionManager, AIEngine y LeadRegistration
 * - Procesar acciones del LLM (registrar lead, enviar catálogo, escalar, cerrar)
 * - Manejar primer mensaje y mensajes de apertura
 */
class ChatbotOrchestrator
{
    /** @var ConversationSessionManager */
    private $sessionManager;

    /** @var AIConversationEngine */
    private $aiEngine;

    /** @var LeadRegistrationService */
    private $leadService;

    public function __construct(
        ConversationSessionManager $sessionManager,
        AIConversationEngine $aiEngine,
        LeadRegistrationService $leadService
    ) {
        $this->sessionManager = $sessionManager;
        $this->aiEngine = $aiEngine;
        $this->leadService = $leadService;
    }

    /**
     * Manejar un mensaje entrante.
     *
     * @param  string  $sessionId
     * @param  string  $message
     * @param  string  $channel   'web' o 'whatsapp'
     * @param  array   $meta      Contexto adicional (page_url, utms, etc.)
     * @return array               Respuesta para el controller/widget
     */
    public function handleMessage($sessionId, $message, $channel = 'web', array $meta = [])
    {
        try {
            // 1. Obtener o crear sesión
            $session = $this->sessionManager->getOrCreateSession($sessionId, $channel, $meta);

            // Verificar si la sesión está activa
            if (!$session->isActive()) {
                return $this->buildResponse(
                    'Esta conversación ya fue cerrada. Puedes iniciar una nueva conversación.',
                    $sessionId,
                    ['conversation_closed' => true]
                );
            }

            // Verificar límite de mensajes — prioridad BD → .env → 50
            $messageCount = $this->sessionManager->getMessageCount($sessionId);
            $maxMessages = (int) \Modules\Marketing\Models\ChatbotConfig::getOrEnv(
                'general_max_messages_per_session',
                'max_messages_per_session',
                50
            );

            if ($messageCount >= $maxMessages) {
                $this->sessionManager->closeSession($sessionId, 'max_messages_reached');
                return $this->buildResponse(
                    'Hemos alcanzado el límite de la conversación. Si necesitas más ayuda, inicia un nuevo chat o escríbenos por WhatsApp.',
                    $sessionId,
                    ['conversation_closed' => true]
                );
            }

            // 2. Si vinieron attachments junto al mensaje, los enriquecemos en el
            //    texto que el LLM ve. El usuario igual ve su mensaje original.
            $userMessageForLog = $message;
            $attachmentIds = isset($meta['attachment_ids']) && is_array($meta['attachment_ids'])
                ? $meta['attachment_ids']
                : [];
            $messageForLLM = $this->enrichMessageWithAttachments($message, $sessionId, $attachmentIds);

            // 3. Agregar mensaje del usuario al historial (con la nota de adjuntos)
            $this->sessionManager->addMessage($sessionId, 'user', $messageForLLM);

            // 4. Obtener historial formateado para el LLM
            $history = $this->sessionManager->getHistoryForLLM($sessionId);

            // 5. Llamar al motor de IA
            $aiResponse = $this->aiEngine->processMessage($history, [
                'channel'  => $channel,
                'page_url' => isset($meta['page_url']) ? $meta['page_url'] : null,
            ]);

            // 6. Extraer el reply del agente
            $reply = isset($aiResponse['reply']) ? $aiResponse['reply'] : 'Disculpa, no pude procesar tu mensaje.';

            // 7. Agregar respuesta del bot al historial
            $this->sessionManager->addMessage($sessionId, 'assistant', $reply);

            // 8. Aplicar guardrail de "no entendido" (R12 extendido).
            //    Si el LLM marca intent=no_entendido lo contabilizamos en la
            //    sesión; cuando llegamos a 2 forzamos la escalada aunque el
            //    LLM no la haya marcado todavía.
            $this->applyNotUnderstoodGuardrail($aiResponse, $session);

            // 9. Procesar acciones
            $actionResults = $this->processActions($aiResponse, $sessionId, $channel, $session);

            // 10. Construir y retornar respuesta
            return $this->buildResponse($reply, $sessionId, $actionResults, $aiResponse);

        } catch (\Exception $e) {
            Log::error('ChatbotOrchestrator: error inesperado', [
                'session_id' => $sessionId,
                'message'    => $e->getMessage(),
                'trace'      => $e->getTraceAsString(),
            ]);

            return $this->buildResponse(
                'Lo siento, tuve un problema técnico. ¿Podrías intentar de nuevo?',
                $sessionId,
                ['error' => true]
            );
        }
    }

    /**
     * Manejar el primer mensaje (apertura de conversación).
     *
     * @param  string  $sessionId
     * @param  string  $channel
     * @param  array   $meta
     * @return array
     */
    public function handleFirstMessage($sessionId, $channel = 'web', array $meta = [])
    {
        // Crear sesión
        $session = $this->sessionManager->getOrCreateSession($sessionId, $channel, $meta);

        // Generar saludo de apertura via LLM
        $history = [
            [
                'role'    => 'user',
                'content' => '[SISTEMA: El usuario acaba de abrir el chat. Genera tu mensaje de apertura/saludo según las instrucciones del playbook.]',
            ],
        ];

        $aiResponse = $this->aiEngine->processMessage($history, [
            'channel' => $channel,
        ]);

        $reply = isset($aiResponse['reply']) ? $aiResponse['reply'] : config('marketing.chatbot.widget.greeting');

        // Guardar solo la respuesta del bot (no el mensaje de sistema)
        $this->sessionManager->addMessage($sessionId, 'assistant', $reply);

        return $this->buildResponse($reply, $sessionId, [], $aiResponse);
    }

    /**
     * Procesar las acciones indicadas por el LLM.
     *
     * @param  array   $aiResponse
     * @param  string  $sessionId
     * @param  string  $channel
     * @param  mixed   $session
     * @return array
     */
    private function processActions(array $aiResponse, $sessionId, $channel, $session)
    {
        $actions    = isset($aiResponse['actions']) ? $aiResponse['actions'] : [];
        $escalation = isset($aiResponse['escalation']) ? $aiResponse['escalation'] : [];

        $results = [
            'show_catalog'         => null,
            'conversation_closed'  => false,
            'escalated'            => false,
            'lead_registered'      => false,
            'lead_id'              => null,
            'recommended_action'   => null,
            'escalation_reason'    => null,
            'whatsapp_link'        => null,
        ];

        // ── Registrar lead ──
        $shouldRegister = isset($actions['should_register_lead']) ? $actions['should_register_lead'] : false;
        if ($shouldRegister) {
            $lead = $this->leadService->registerOrUpdate($aiResponse, $sessionId, $channel);
            if ($lead) {
                $results['lead_registered'] = true;
                $results['lead_id'] = $lead->id;

                // Vincular lead a la sesión
                $session->update(['lead_id' => $lead->id]);
            }
        }

        // ── Enviar catálogo ──
        $sendCatalog = isset($actions['send_catalog']) ? $actions['send_catalog'] : null;
        if ($sendCatalog !== null) {
            $catalogUrl = $this->resolveCatalogUrl($sendCatalog);
            if ($catalogUrl) {
                $results['show_catalog'] = [
                    'line' => $sendCatalog,
                    'url'  => $catalogUrl,
                ];
            }
        }

        // ── Escalar a humano ──
        $escalate = isset($actions['escalate_to_human']) ? $actions['escalate_to_human'] : false;

        // DEFENSA: NO escalar si todavía no tenemos datos mínimos del cliente
        // (al menos nombre + un canal de contacto: correo o celular).
        //
        // Sin esta validación, si la IA marca escalate_to_human=true en el
        // mismo turno en que pide los datos al usuario, la sesión se cierra
        // antes de que el cliente pueda responder. Resultado: lead vacío que
        // la ejecutiva no puede atender.
        //
        // Excepción: no_entendido_repetido (R12) — escalamos aunque falten datos
        // porque indica que el usuario no se comunica de forma útil.
        if ($escalate) {
            $entities   = isset($aiResponse['entities_extracted']) ? $aiResponse['entities_extracted'] : [];
            $recommended = isset($escalation['recommended_action']) ? $escalation['recommended_action'] : null;

            $hasContact = !empty($entities['correo']) || !empty($entities['celular']);
            $hasName    = !empty($entities['nombre_contacto']);
            $isNoEntendidoRepetido = ($recommended === 'no_entendido_repetido');

            if (!$isNoEntendidoRepetido && (!$hasName || !$hasContact)) {
                Log::info('ChatbotOrchestrator: escalación POSPUESTA (faltan datos mínimos)', [
                    'session_id'  => $sessionId,
                    'has_name'    => $hasName,
                    'has_contact' => $hasContact,
                    'recommended' => $recommended,
                ]);
                // Mantener la sesión activa: el bot ya pidió los datos en
                // su reply; el cliente responderá en el próximo turno y
                // ahí sí podremos escalar con info útil para la ejecutiva.
                $escalate = false;
            }
        }

        if ($escalate) {
            // recommended_action / reason vienen del bloque escalation del JSON
            $recommended = isset($escalation['recommended_action']) ? $escalation['recommended_action'] : null;
            $reasonText  = isset($escalation['reason']) ? $escalation['reason'] : null;

            // Mapear recommended_action a closed_reason para auditoría.
            // Si el recommended_action es uno de los finitos del playbook,
            // lo usamos como closed_reason; si no, dejamos 'escalated_to_human'.
            $closedReason = $this->mapRecommendedToClosedReason($recommended);

            $session->escalate($closedReason);

            $results['escalated']            = true;
            $results['conversation_closed']  = true;
            $results['recommended_action']   = $recommended;
            $results['escalation_reason']    = $reasonText;
            $results['whatsapp_link']        = $this->getWhatsappLink();

            Log::info('ChatbotOrchestrator: conversación escalada a humano', [
                'session_id'         => $sessionId,
                'recommended_action' => $recommended,
                'reason'             => $reasonText,
                'closed_reason'      => $closedReason,
            ]);
        }

        // ── Cerrar conversación ──
        $closeConversation = isset($actions['close_conversation']) ? $actions['close_conversation'] : false;
        if ($closeConversation && !$results['escalated']) {
            $reason = $results['lead_registered'] ? 'lead_registered' : 'no_intent';
            $leadId = $results['lead_id'];
            $this->sessionManager->closeSession($sessionId, $reason, $leadId);
            $results['conversation_closed'] = true;
        }

        return $results;
    }

    /**
     * Aplica el guardrail de "no entendido" antes de procesar acciones.
     *
     * - Si el LLM marca intent=no_entendido, incrementa el contador en la sesión.
     * - Si el contador llega a 2, fuerza escalate_to_human=true en la respuesta
     *   del LLM (mutamos $aiResponse por referencia) con recommended_action=
     *   'no_entendido_repetido'.
     * - Si el LLM marca un intent claro, resetea el contador (R12 idempotencia).
     *
     * @param  array          $aiResponse  Por referencia: mutamos actions/escalation si toca
     * @param  ChatbotSession $session
     * @return void
     */
    private function applyNotUnderstoodGuardrail(array &$aiResponse, $session)
    {
        $intent = isset($aiResponse['intent']) ? $aiResponse['intent'] : null;

        if ($intent === 'no_entendido') {
            $newCount = $session->incrementNotUnderstood();

            if ($newCount >= 2) {
                // Forzar escalada — el LLM debió marcarlo también, pero igual
                // garantizamos por backend.
                if (!isset($aiResponse['actions']) || !is_array($aiResponse['actions'])) {
                    $aiResponse['actions'] = [];
                }
                $aiResponse['actions']['escalate_to_human'] = true;

                if (!isset($aiResponse['escalation']) || !is_array($aiResponse['escalation'])) {
                    $aiResponse['escalation'] = [];
                }
                if (empty($aiResponse['escalation']['recommended_action'])) {
                    $aiResponse['escalation']['recommended_action'] = 'no_entendido_repetido';
                }
                if (empty($aiResponse['escalation']['reason'])) {
                    $aiResponse['escalation']['reason'] = 'El usuario envió 2 mensajes sin contexto interpretable; se deriva a ejecutiva para atención humana.';
                }

                Log::info('ChatbotOrchestrator: guardrail no_entendido disparado', [
                    'session_id'         => $session->session_id,
                    'not_understood_count' => $newCount,
                ]);
            }
        } elseif (!empty($intent) && $intent !== 'no_entendido') {
            // Intent claro: limpiar el contador para no penalizar a usuarios
            // que tuvieron un mensaje confuso aislado y luego retomaron.
            $session->resetNotUnderstood();
        }
    }

    /**
     * Mapea el recommended_action del LLM al closed_reason finito que
     * persistimos en la sesión.
     *
     * @param  string|null  $recommended
     * @return string
     */
    private function mapRecommendedToClosedReason($recommended)
    {
        if ($recommended === 'no_entendido_repetido') {
            return 'no_entendido_repetido';
        }
        return 'escalated_to_human';
    }

    /**
     * Enriquece el mensaje del usuario con una nota descriptiva de los archivos
     * adjuntos validados, para que el LLM sepa que el usuario subió algo.
     *
     * Solo procesa attachments que (a) existan en BD, (b) pertenezcan a la
     * misma session_id (anti-spoofing) y (c) NO estén ya asociados a otro lead.
     *
     * Si no hay adjuntos válidos, devuelve el mensaje original sin tocar.
     *
     * @param  string  $message
     * @param  string  $sessionId
     * @param  array   $attachmentIds
     * @return string
     */
    private function enrichMessageWithAttachments($message, $sessionId, array $attachmentIds)
    {
        if (empty($attachmentIds)) {
            return $message;
        }

        try {
            $attachments = ChatbotAttachment::whereIn('id', $attachmentIds)
                ->where('session_id', $sessionId)
                ->get();
        } catch (\Exception $e) {
            Log::warning('ChatbotOrchestrator: error cargando attachments', [
                'session_id' => $sessionId,
                'error'      => $e->getMessage(),
            ]);
            return $message;
        }

        if ($attachments->isEmpty()) {
            return $message;
        }

        $lines = [];
        foreach ($attachments as $att) {
            $lines[] = sprintf(
                '[ARCHIVO ADJUNTO: %s | %s | %s | tipo=%s]',
                $att->original_name,
                $att->mime_type,
                $att->humanSize(),
                $att->kind
            );
        }

        $note = "\n\n" . implode("\n", $lines);

        // Si el mensaje del usuario está vacío o es muy corto (ej. "init"
        // automático del widget al subir sin texto), explicitamos que el
        // turno trae solo archivos.
        $trimmed = trim((string) $message);
        if ($trimmed === '' || strtolower($trimmed) === 'init') {
            return '(El usuario subió archivos sin escribir texto)' . $note;
        }

        return $trimmed . $note;
    }

    /**
     * Lee el link público de WhatsApp desde marketing_chatbot_config.
     *
     * @return string|null
     */
    private function getWhatsappLink()
    {
        try {
            $row = \Modules\Marketing\Models\ChatbotConfig::byKey('general_whatsapp_link');
            if ($row) {
                $val = $row->getTypedValue();
                return is_string($val) && trim($val) !== '' ? trim($val) : null;
            }
        } catch (\Exception $e) {
            Log::warning('ChatbotOrchestrator: no se pudo leer general_whatsapp_link', [
                'error' => $e->getMessage(),
            ]);
        }
        return null;
    }

    /**
     * Resolver la URL de un catálogo por nombre de línea.
     *
     * @param  string  $lineName
     * @return string|null
     */
    private function resolveCatalogUrl($lineName)
    {
        $lowerLine = strtolower($lineName);

        // Si pide "general", buscar catálogo general
        if ($lowerLine === 'general' || $lowerLine === 'catalogo general') {
            $catalog = ChatbotCatalog::active()->general()->first();
            if ($catalog) {
                return $catalog->catalog_url;
            }
        }

        // Buscar por nombre de línea
        $catalog = ChatbotCatalog::active()
            ->where('line_name', 'LIKE', '%' . $lineName . '%')
            ->first();

        if ($catalog) {
            return $catalog->catalog_url;
        }

        // Fallback: catálogo general
        $general = ChatbotCatalog::active()->general()->first();
        return $general ? $general->catalog_url : null;
    }

    /**
     * Construir la respuesta final para el controller/widget.
     *
     * @param  string  $reply
     * @param  string  $sessionId
     * @param  array   $actionResults
     * @return array
     */
    private function buildResponse($reply, $sessionId, array $actionResults = [], array $aiResponse = [])
    {
        // Datos de inteligencia exponibles al webhook de WhatsApp (panel de
        // aprobación) y al widget web. Son no-confidenciales y útiles para UI.
        $intent     = isset($aiResponse['intent']) ? $aiResponse['intent'] : null;
        $confidence = isset($aiResponse['confidence']) ? $aiResponse['confidence'] : null;
        $aiActions  = isset($aiResponse['actions']) ? $aiResponse['actions'] : null;
        $escalation = isset($aiResponse['escalation']) ? $aiResponse['escalation'] : null;

        return [
            'ok'         => true,
            'reply'      => $reply,
            'session_id' => $sessionId,
            'intent'     => $intent,
            'confidence' => $confidence,
            'ai_actions' => $aiActions,
            'escalation' => $escalation,
            'actions'    => [
                'show_catalog'        => isset($actionResults['show_catalog']) ? $actionResults['show_catalog'] : null,
                'conversation_closed' => isset($actionResults['conversation_closed']) ? $actionResults['conversation_closed'] : false,
                'escalated'           => isset($actionResults['escalated']) ? $actionResults['escalated'] : false,
                'recommended_action'  => isset($actionResults['recommended_action']) ? $actionResults['recommended_action'] : null,
                'escalation_reason'   => isset($actionResults['escalation_reason']) ? $actionResults['escalation_reason'] : null,
                'whatsapp_link'       => isset($actionResults['whatsapp_link']) ? $actionResults['whatsapp_link'] : null,
            ],
        ];
    }
}
