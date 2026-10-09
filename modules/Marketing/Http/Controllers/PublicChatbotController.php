<?php

namespace Modules\Marketing\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Comercial\Models\Lead;

class PublicChatbotController extends Controller
{
    /**
     * Devuelve la configuración del chatbot (greeting, farewell, enabled).
     * Lee desde la tabla marketing_chatbot_settings del tenant.
     */
    public function settings()
    {
        $row = null;

        try {
            // Usar la conexión tenant por defecto
            $row = DB::table('marketing_chatbot_settings')
                ->where('id', 1)
                ->first();
        } catch (\Throwable $e) {
            Log::warning('Chatbot settings error: ' . $e->getMessage());
        }

        return response()->json([
            'greeting' => $row->greeting ?? 'Hola 👋 Soy el asistente de COMPIPRO. Te haré unas preguntas rápidas para ayudarte.',
            'farewell' => $row->farewell ?? '¡Gracias! Muy pronto un ejecutivo se pondrá en contacto con Ud.',
            'enabled'  => (int)($row->enabled ?? 1),
        ]);
    }

    /**
     * Almacena un nuevo lead desde el chatbot.
     * Usa el modelo Lead de Comercial para insertar correctamente en el tenant.
     */
    public function storeLead(Request $request)
    {
        // Normaliza input para JSON o form-data
        $payload = $request->all();

        if (empty($payload) && $request->getContent()) {
            $decoded = json_decode($request->getContent(), true);
            if (is_array($decoded)) {
                $request->merge($decoded);
            }
        }

        $data = $request->validate([
            'name'         => ['required', 'string', 'max:120'],
            'email'        => ['nullable', 'email', 'max:191'],
            'phone'        => ['nullable', 'string', 'max:50'],
            'company_name' => ['required', 'string', 'max:191'],
            'meta'         => ['nullable'],
        ]);

        if (empty($data['email']) && empty($data['phone'])) {
            return response()->json([
                'ok' => false,
                'message' => 'Debes ingresar teléfono o correo.',
            ], 422);
        }

        // Descripción para el lead
        $descripcion = 'Lead generado por Chatbot Web';
        if (!empty($data['company_name'])) $descripcion .= ' | Empresa: ' . $data['company_name'];
        if (!empty($data['name']))         $descripcion .= ' | Contacto: ' . $data['name'];
        if (!empty($data['phone']))        $descripcion .= ' | Tel: ' . $data['phone'];
        if (!empty($data['email']))        $descripcion .= ' | Email: ' . $data['email'];

        try {
            // ✅ Usar el modelo Lead que ya maneja la conexión tenant correctamente
            Lead::create([
                'ruc'             => '00000000000',
                'razon_social'    => $data['company_name'] ?? null,
                'origen'          => 'web',
                'etapa'           => 'nuevo',
                'descripcion'     => mb_substr($descripcion, 0, 500),
                'contacto'        => $data['name'],
                'telefono'        => $data['phone'] ?? null,
                'email'           => $data['email'] ?? null,
                'estado'          => 'activo',
                'estado_revision' => 'pendiente',
            ]);

            return response()->json([
                'ok' => true,
                'message' => 'Guardado correctamente.',
            ]);
        } catch (\Throwable $e) {
            Log::error('Chatbot lead insert error: ' . $e->getMessage());

            return response()->json([
                'ok' => false,
                'message' => 'Error al guardar el lead. Intenta nuevamente.',
            ], 500);
        }
    }
}
