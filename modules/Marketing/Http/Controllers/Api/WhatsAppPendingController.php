<?php

namespace Modules\Marketing\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Modules\Marketing\Models\ChatbotPendingMessage;
use Modules\Marketing\Services\WhatsAppApprovalService;

/**
 * WhatsAppPendingController
 *
 * API protegida para que la ejecutiva gestione las respuestas pendientes de
 * aprobación del bot de WhatsApp (Nivel 1).
 *
 * Endpoints:
 *   GET    /marketing/chatbot/whatsapp/pending           listar (con filtros)
 *   GET    /marketing/chatbot/whatsapp/pending/count     contador (para badge)
 *   GET    /marketing/chatbot/whatsapp/pending/{id}      detalle
 *   POST   /marketing/chatbot/whatsapp/pending/{id}/approve   aprobar tal cual + enviar
 *   POST   /marketing/chatbot/whatsapp/pending/{id}/edit      editar + aprobar + enviar
 *   POST   /marketing/chatbot/whatsapp/pending/{id}/reject    rechazar (no envía)
 *   POST   /marketing/chatbot/whatsapp/pending/{id}/retry     reintentar envío de un fallido
 */
class WhatsAppPendingController extends Controller
{
    /** @var WhatsAppApprovalService */
    private $approvalService;

    public function __construct(WhatsAppApprovalService $approvalService)
    {
        $this->approvalService = $approvalService;
    }

    /**
     * GET /pending  — listado con filtros opcionales (?status=, ?wa_id=, ?limit=)
     */
    public function index(Request $request)
    {
        $filters = $request->only(['status', 'wa_id', 'limit']);
        $rows = $this->approvalService->list($filters);

        // Enriquecer con info del aprobador para la UI
        $rows->each(function ($r) {
            if ($r->approved_by_user_id && $r->approver) {
                $r->approver_name = $r->approver->name;
            }
        });

        return response()->json([
            'ok'       => true,
            'pending'  => $rows,
            'count'    => $rows->count(),
        ]);
    }

    /**
     * GET /pending/count  — solo el contador (para refrescar badge frecuentemente)
     */
    public function count()
    {
        return response()->json([
            'ok'    => true,
            'count' => $this->approvalService->pendingCount(),
        ]);
    }

    /**
     * GET /pending/{id}  — detalle completo
     */
    public function show($id)
    {
        $msg = ChatbotPendingMessage::with('approver')->find($id);
        if (!$msg) {
            return response()->json(['ok' => false, 'message' => 'No encontrado.'], 404);
        }
        return response()->json(['ok' => true, 'pending' => $msg]);
    }

    /**
     * POST /pending/{id}/approve  — aprobar tal cual + envío inmediato
     */
    public function approve($id)
    {
        $userId = $this->resolveUserId();
        $result = $this->approvalService->approve($id, $userId);
        return $this->buildResponse($result);
    }

    /**
     * POST /pending/{id}/edit  — editar texto + aprobar + envío inmediato
     *
     * Body: { final_reply: "<texto editado>" }
     */
    public function edit(Request $request, $id)
    {
        $v = Validator::make($request->all(), [
            'final_reply' => ['required', 'string', 'max:4000'],
        ]);
        if ($v->fails()) {
            return response()->json(['ok' => false, 'errors' => $v->errors()], 422);
        }

        $userId = $this->resolveUserId();
        $result = $this->approvalService->approveWithEdit($id, $userId, $request->input('final_reply'));
        return $this->buildResponse($result);
    }

    /**
     * POST /pending/{id}/reject  — rechazar (no se envía nada)
     *
     * Body opcional: { reason: "<motivo libre>" }
     */
    public function reject(Request $request, $id)
    {
        $v = Validator::make($request->all(), [
            'reason' => ['nullable', 'string', 'max:255'],
        ]);
        if ($v->fails()) {
            return response()->json(['ok' => false, 'errors' => $v->errors()], 422);
        }

        $userId = $this->resolveUserId();
        $result = $this->approvalService->reject($id, $userId, $request->input('reason'));
        return $this->buildResponse($result);
    }

    /**
     * POST /pending/{id}/retry  — reintentar el envío de un mensaje failed
     */
    public function retry($id)
    {
        $result = $this->approvalService->retrySend($id);
        return $this->buildResponse($result);
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    private function resolveUserId()
    {
        $u = Auth::user();
        return $u && isset($u->id) ? (int) $u->id : 0;
    }

    private function buildResponse(array $result)
    {
        $status = !empty($result['ok']) ? 200 : 422;
        return response()->json($result, $status);
    }
}
