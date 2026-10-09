<?php

namespace Modules\Marketing\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
use Modules\Marketing\Models\MarketingChatbotLead;

/**
 * ChatbotLeadsController
 *
 * Gestión de leads desde el panel de administración del ERP.
 *
 * Rutas protegidas:
 *   GET    /api/marketing/chatbot/leads              → listar
 *   GET    /api/marketing/chatbot/leads/{id}          → detalle
 *   PUT    /api/marketing/chatbot/leads/{id}/status   → cambiar estado
 *   POST   /api/marketing/chatbot/leads/{id}/convert  → convertir a requerimiento
 */
class ChatbotLeadsController extends Controller
{
    /**
     * Listar leads con filtros.
     */
    public function index(Request $request)
    {
        $query = MarketingChatbotLead::query()
            ->orderByDesc('created_at');

        // Filtros opcionales
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('channel')) {
            $query->byChannel($request->input('channel'));
        }

        if ($request->filled('intent')) {
            $query->byIntent($request->input('intent'));
        }

        if ($request->filled('ready_to_convert')) {
            $query->where('ready_to_convert', filter_var($request->input('ready_to_convert'), FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->filled('urgency')) {
            $query->where('urgency_level', $request->input('urgency'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('email', 'LIKE', "%{$search}%")
                  ->orWhere('phone', 'LIKE', "%{$search}%")
                  ->orWhere('company_name', 'LIKE', "%{$search}%");
            });
        }

        $leads = $query->paginate($request->input('per_page', 20));

        return response()->json([
            'ok'    => true,
            'leads' => $leads,
        ]);
    }

    /**
     * Ver detalle de un lead.
     */
    public function show($id)
    {
        $lead = MarketingChatbotLead::with('session')->findOrFail($id);

        return response()->json([
            'ok'   => true,
            'lead' => $lead,
        ]);
    }

    /**
     * Cambiar estado del lead.
     */
    public function updateStatus(Request $request, $id)
    {
        $lead = MarketingChatbotLead::findOrFail($id);

        $v = Validator::make($request->all(), [
            'status' => ['required', 'string', 'in:' . implode(',', MarketingChatbotLead::VALID_STATUSES)],
        ]);

        if ($v->fails()) {
            return response()->json(['ok' => false, 'errors' => $v->errors()], 422);
        }

        $lead->update(['status' => $request->input('status')]);

        return response()->json([
            'ok'   => true,
            'lead' => $lead->fresh(),
        ]);
    }

    /**
     * Convertir lead a requerimiento.
     *
     * Solo se permite si ready_to_convert = true.
     * Cambia estado a "convertido_a_requerimiento".
     */
    public function convert($id)
    {
        $lead = MarketingChatbotLead::findOrFail($id);

        if (!$lead->ready_to_convert) {
            return response()->json([
                'ok'      => false,
                'message' => 'Este lead aún no está listo para convertir. Faltan datos.',
            ], 422);
        }

        $lead->update([
            'status' => MarketingChatbotLead::STATUS_CONVERTED,
        ]);

        return response()->json([
            'ok'      => true,
            'message' => 'Lead convertido exitosamente.',
            'lead'    => $lead->fresh(),
        ]);
    }

    /**
     * Store (mantener compatibilidad con widget legacy).
     *
     * POST /api/marketing/chatbot/leads
     */
    public function store(Request $request)
    {
        $data = $request->all();

        $v = Validator::make($data, [
            'name'         => ['required', 'string', 'max:120'],
            'company_name' => ['required', 'string', 'max:160'],
            'email'        => ['nullable', 'email', 'max:120'],
            'phone'        => ['nullable', 'string', 'max:40'],
            'message'      => ['nullable', 'string', 'max:2000'],
        ]);

        $v->after(function ($validator) use ($data) {
            $hasEmail = !empty($data['email']);
            $hasPhone = !empty($data['phone']);
            if (!$hasEmail && !$hasPhone) {
                $validator->errors()->add('contact', 'Debe ingresar teléfono o correo.');
            }
        });

        if ($v->fails()) {
            return response()->json(['ok' => false, 'errors' => $v->errors()], 422);
        }

        $lead = MarketingChatbotLead::create([
            'name'         => $data['name'],
            'company_name' => $data['company_name'],
            'email'        => isset($data['email']) ? $data['email'] : null,
            'phone'        => isset($data['phone']) ? $data['phone'] : null,
            'source'       => 'marketing_chatbot',
            'status'       => MarketingChatbotLead::STATUS_NEW,
            'channel'      => 'web',
            'meta'         => [
                'message'    => isset($data['message']) ? $data['message'] : null,
                'user_agent' => $request->userAgent(),
                'ip'         => $request->ip(),
            ],
        ]);

        return response()->json([
            'ok'            => true,
            'id'            => $lead->id,
            'final_message' => 'Muy pronto un ejecutivo se pondrá en contacto con Ud.',
        ]);
    }
}
