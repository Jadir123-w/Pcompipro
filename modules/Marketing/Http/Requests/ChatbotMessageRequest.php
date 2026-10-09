<?php

namespace Modules\Marketing\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * ChatbotMessageRequest
 *
 * Validación del endpoint POST /api/marketing/chatbot/message
 */
class ChatbotMessageRequest extends FormRequest
{
    /**
     * No requiere autenticación (endpoint público del widget).
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Reglas de validación.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'session_id' => ['required', 'string', 'max:64'],
            'message'    => ['required', 'string', 'max:2000'],

            // Contexto del widget (opcionales)
            'page_url'         => ['nullable', 'string', 'max:500'],
            'utm_source'       => ['nullable', 'string', 'max:100'],
            'utm_medium'       => ['nullable', 'string', 'max:100'],
            'utm_campaign'     => ['nullable', 'string', 'max:100'],
            'is_first_message' => ['nullable', 'boolean'],

            // IDs de attachments subidos previamente vía /upload (opcionales)
            'attachment_ids'   => ['nullable', 'array', 'max:10'],
            'attachment_ids.*' => ['integer', 'min:1'],
        ];
    }

    /**
     * Mensajes de error personalizados.
     *
     * @return array
     */
    public function messages()
    {
        return [
            'session_id.required' => 'Se requiere un identificador de sesión.',
            'message.required'    => 'El mensaje no puede estar vacío.',
            'message.max'         => 'El mensaje es demasiado largo (máximo 2000 caracteres).',
        ];
    }
}
