<?php

namespace Modules\Marketing\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Marketing\Models\ChatbotConfig;
use Modules\Marketing\Models\ChatbotPendingMessage;

/**
 * WhatsAppApprovalService
 *
 * Gestiona la cola de respuestas pendientes de aprobación humana en el flujo
 * de WhatsApp Nivel 1.
 *
 * Responsabilidades:
 *   - Encolar una respuesta del LLM como pendiente (en lugar de enviarla)
 *   - Listar pendientes para la UI de la ejecutiva
 *   - Aprobar / editar / rechazar pendientes
 *   - Despachar a Meta cuando un pendiente queda aprobado/editado
 *
 * Es agnóstico al transporte: recibe ya el JSON parseado del LLM y delega
 * el envío real a WhatsAppService.
 */
class WhatsAppApprovalService
{
    /** @var WhatsAppService */
    private $whatsAppService;

    public function __construct(WhatsAppService $whatsAppService)
    {
        $this->whatsAppService = $whatsAppService;
    }

    /*
    |--------------------------------------------------------------------------
    | Configuración
    |--------------------------------------------------------------------------
    */

    /**
     * ¿El tenant exige aprobación humana antes de enviar (Nivel 1)?
     *
     * Lee primero de marketing_chatbot_config (key 'wa_approval_required').
     * Si la tabla no existe o no tiene la key, default true (Nivel 1 conservador).
     *
     * @return bool
     */
    public function isApprovalRequired()
    {
        try {
            $val = ChatbotConfig::get('wa_approval_required');
            if ($val !== null) {
                return (bool) $val;
            }
        } catch (\Exception $e) {
            // tabla puede no existir → default seguro
        }
        return true;
    }

    /*
    |--------------------------------------------------------------------------
    | Encolar
    |--------------------------------------------------------------------------
    */

    /**
     * Encolar una respuesta del LLM como pendiente de aprobación.
     *
     * @param  array  $params  Estructura:
     *   - session_id           string (requerido)
     *   - wa_id                string (requerido)
     *   - contact_name         string|null
     *   - incoming_message_id  string|null
     *   - user_message         string (mensaje del usuario que disparó esto)
     *   - ai_response          array  Respuesta completa del Orchestrator
     * @return ChatbotPendingMessage
     */
    public function enqueue(array $params)
    {
        $aiResponse = isset($params['ai_response']) ? $params['ai_response'] : [];

        // Estructura del Orchestrator (post-buildResponse):
        //   { reply, intent, confidence, ai_actions, escalation, actions: { show_catalog, ... } }
        // Estructura legacy o directo del LLM:
        //   { reply, intent, actions: { show_catalog, escalate_to_human, ... } }
        // Soportamos ambas: leer first ai_actions (procesado), fallback a actions.
        $aiActions    = isset($aiResponse['ai_actions']) ? $aiResponse['ai_actions'] : null;
        if ($aiActions === null) {
            $aiActions = isset($aiResponse['actions']) ? $aiResponse['actions'] : [];
        }

        // Catálogo: el Orchestrator lo expone bajo actions.show_catalog
        $orchActions = isset($aiResponse['actions']) ? $aiResponse['actions'] : [];
        $catalog = isset($orchActions['show_catalog']) ? $orchActions['show_catalog'] : null;

        $row = ChatbotPendingMessage::create([
            'session_id'          => $params['session_id'],
            'wa_id'               => $params['wa_id'],
            'contact_name'        => isset($params['contact_name']) ? $params['contact_name'] : null,
            'incoming_message_id' => isset($params['incoming_message_id']) ? $params['incoming_message_id'] : null,
            'user_message'        => isset($params['user_message']) ? $params['user_message'] : '',
            'ai_reply'            => isset($aiResponse['reply']) ? $aiResponse['reply'] : '',
            'ai_intent'           => isset($aiResponse['intent']) ? $aiResponse['intent'] : null,
            'ai_actions'          => $aiActions ?: null,
            'catalog_url'         => is_array($catalog) && isset($catalog['url'])  ? $catalog['url']  : null,
            'catalog_line'        => is_array($catalog) && isset($catalog['line']) ? $catalog['line'] : null,
            'status'              => ChatbotPendingMessage::STATUS_PENDING,
        ]);

        Log::info('WhatsAppApprovalService: pendiente encolado', [
            'pending_id' => $row->id,
            'session_id' => $row->session_id,
            'wa_id'      => $row->wa_id,
            'intent'     => $row->ai_intent,
        ]);

        return $row;
    }

    /*
    |--------------------------------------------------------------------------
    | Listado para la UI
    |--------------------------------------------------------------------------
    */

    /**
     * @param  array  $filters  ['status' => ..., 'wa_id' => ..., 'limit' => N]
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function list(array $filters = [])
    {
        $q = ChatbotPendingMessage::query();

        if (!empty($filters['status'])) {
            $q->where('status', $filters['status']);
        } else {
            // Por default: pendientes + listos a enviar (en transición visible)
            $q->whereIn('status', [
                ChatbotPendingMessage::STATUS_PENDING,
                ChatbotPendingMessage::STATUS_APPROVED,
                ChatbotPendingMessage::STATUS_EDITED,
                ChatbotPendingMessage::STATUS_SENDING,
                ChatbotPendingMessage::STATUS_FAILED,
            ]);
        }

        if (!empty($filters['wa_id'])) {
            $q->where('wa_id', $filters['wa_id']);
        }

        $limit = isset($filters['limit']) && (int) $filters['limit'] > 0 ? (int) $filters['limit'] : 50;

        return $q->orderBy('created_at', 'desc')->limit($limit)->get();
    }

    /**
     * @return int  Cuántos pendientes esperan aprobación ahora.
     */
    public function pendingCount()
    {
        return ChatbotPendingMessage::pending()->count();
    }

    /*
    |--------------------------------------------------------------------------
    | Acciones de la ejecutiva
    |--------------------------------------------------------------------------
    */

    /**
     * Aprobar tal cual + disparar envío.
     *
     * @param  int  $pendingId
     * @param  int  $userId    user_id de la ejecutiva
     * @return array  ['ok'=>bool,'message'=>string,'pending'=>ChatbotPendingMessage|null]
     */
    public function approve($pendingId, $userId)
    {
        $msg = ChatbotPendingMessage::find($pendingId);
        if (!$msg) return $this->notFound();

        if ($msg->isTerminal()) {
            return ['ok' => false, 'message' => 'Este mensaje ya fue procesado (estado: ' . $msg->status . ').', 'pending' => $msg];
        }

        $msg->markApproved($userId);
        return $this->dispatchSend($msg);
    }

    /**
     * Aprobar con edición + disparar envío.
     *
     * @param  int     $pendingId
     * @param  int     $userId
     * @param  string  $newText
     * @return array
     */
    public function approveWithEdit($pendingId, $userId, $newText)
    {
        $newText = trim((string) $newText);
        if ($newText === '') {
            return ['ok' => false, 'message' => 'El texto editado no puede estar vacío.', 'pending' => null];
        }

        $msg = ChatbotPendingMessage::find($pendingId);
        if (!$msg) return $this->notFound();

        if ($msg->isTerminal()) {
            return ['ok' => false, 'message' => 'Este mensaje ya fue procesado (estado: ' . $msg->status . ').', 'pending' => $msg];
        }

        $msg->markEdited($userId, $newText);
        return $this->dispatchSend($msg);
    }

    /**
     * Rechazar (no se envía nada).
     *
     * @param  int          $pendingId
     * @param  int          $userId
     * @param  string|null  $reason
     * @return array
     */
    public function reject($pendingId, $userId, $reason = null)
    {
        $msg = ChatbotPendingMessage::find($pendingId);
        if (!$msg) return $this->notFound();

        if ($msg->isTerminal()) {
            return ['ok' => false, 'message' => 'Este mensaje ya fue procesado (estado: ' . $msg->status . ').', 'pending' => $msg];
        }

        $msg->markRejected($userId, $reason);

        Log::info('WhatsAppApprovalService: pendiente rechazado', [
            'pending_id' => $msg->id,
            'user_id'    => $userId,
            'reason'     => $reason,
        ]);

        return ['ok' => true, 'message' => 'Mensaje rechazado.', 'pending' => $msg->fresh()];
    }

    /**
     * Reintentar envío de un pendiente que falló.
     *
     * @param  int  $pendingId
     * @return array
     */
    public function retrySend($pendingId)
    {
        $msg = ChatbotPendingMessage::find($pendingId);
        if (!$msg) return $this->notFound();

        if ($msg->status !== ChatbotPendingMessage::STATUS_FAILED) {
            return ['ok' => false, 'message' => 'Solo se pueden reintentar mensajes en estado failed.', 'pending' => $msg];
        }

        // Volver a estado approved/edited según corresponda
        $newStatus = !empty($msg->final_reply) && $msg->final_reply !== $msg->ai_reply
            ? ChatbotPendingMessage::STATUS_EDITED
            : ChatbotPendingMessage::STATUS_APPROVED;

        $msg->update(['status' => $newStatus, 'error_message' => null]);
        return $this->dispatchSend($msg->fresh());
    }

    /*
    |--------------------------------------------------------------------------
    | Despacho real al WhatsApp Service
    |--------------------------------------------------------------------------
    */

    /**
     * Mandar un pendiente (ya aprobado/editado) a Meta vía WhatsAppService.
     *
     * Atomic: usa transaction + lock optimista para que dos workers no envíen
     * el mismo mensaje en paralelo.
     *
     * @param  ChatbotPendingMessage  $msg
     * @return array  ['ok'=>bool,'message'=>string,'pending'=>ChatbotPendingMessage]
     */
    public function dispatchSend(ChatbotPendingMessage $msg)
    {
        if (!$msg->isReadyToSend()) {
            return ['ok' => false, 'message' => 'El mensaje no está listo para enviar (estado: ' . $msg->status . ').', 'pending' => $msg];
        }

        // Lock optimista: si otro proceso ya lo tomó, abortar
        $locked = DB::connection('tenant')->table('chatbot_pending_messages')
            ->where('id', $msg->id)
            ->whereIn('status', ChatbotPendingMessage::$READY_TO_SEND_STATUSES)
            ->update([
                'status'     => ChatbotPendingMessage::STATUS_SENDING,
                'updated_at' => now(),
            ]);

        if ($locked === 0) {
            $msg = $msg->fresh();
            return ['ok' => false, 'message' => 'El mensaje fue tomado por otro proceso (estado actual: ' . $msg->status . ').', 'pending' => $msg];
        }

        $msg = $msg->fresh();

        // 1) Enviar el texto
        $textResult = $this->whatsAppService->sendTextMessage($msg->wa_id, $msg->effectiveText());

        if (!$textResult['success']) {
            $msg->markFailed($textResult['error'] ?: 'Error desconocido al enviar texto.');
            Log::error('WhatsAppApprovalService: envío de texto falló', [
                'pending_id' => $msg->id,
                'error'      => $textResult['error'],
            ]);
            return ['ok' => false, 'message' => 'Error enviando: ' . $textResult['error'], 'pending' => $msg->fresh()];
        }

        // 2) Si había catálogo, enviarlo como documento (best-effort, no rompe el flujo)
        if (!empty($msg->catalog_url)) {
            $line = $msg->catalog_line ?: 'Catalogo';
            $docResult = $this->whatsAppService->sendDocument(
                $msg->wa_id,
                $msg->catalog_url,
                'Catalogo ' . $line . ' - COMPIPRO'
            );

            if (!$docResult['success']) {
                Log::warning('WhatsAppApprovalService: catálogo no se pudo enviar (texto sí se envió)', [
                    'pending_id' => $msg->id,
                    'error'      => $docResult['error'],
                ]);
            }
        }

        // 3) Marcar exitoso
        $msg->markSent($textResult['message_id']);

        Log::info('WhatsAppApprovalService: enviado a Meta', [
            'pending_id'      => $msg->id,
            'wa_id'           => $msg->wa_id,
            'meta_message_id' => $textResult['message_id'],
        ]);

        return ['ok' => true, 'message' => 'Mensaje enviado.', 'pending' => $msg->fresh()];
    }

    /*
    |--------------------------------------------------------------------------
    | Internos
    |--------------------------------------------------------------------------
    */

    private function notFound()
    {
        return ['ok' => false, 'message' => 'Mensaje pendiente no encontrado.', 'pending' => null];
    }
}
