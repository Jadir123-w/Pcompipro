<?php

namespace Modules\Marketing\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\Marketing\Http\Requests\ChatbotMessageRequest;
use Modules\Marketing\Services\ChatbotOrchestrator;

/**
 * ChatbotMessageController
 *
 * Endpoint principal de conversación del agente MKT-01.
 * Recibe mensajes del widget web y retorna respuestas del LLM.
 *
 * POST /api/marketing/chatbot/message
 */
class ChatbotMessageController extends Controller
{
    /** @var ChatbotOrchestrator */
    private $orchestrator;

    public function __construct(ChatbotOrchestrator $orchestrator)
    {
        $this->orchestrator = $orchestrator;
    }

    /**
     * Manejar un mensaje entrante del chat.
     *
     * @param  ChatbotMessageRequest  $request
     * @return JsonResponse
     */
    public function handle(ChatbotMessageRequest $request)
    {
        $validated = $request->validated();

        $isFirstMessage = isset($validated['is_first_message']) ? $validated['is_first_message'] : false;

        // Si es el primer mensaje, generar saludo de apertura
        if ($isFirstMessage) {
            $response = $this->orchestrator->handleFirstMessage(
                $validated['session_id'],
                'web',
                [
                    'page_url'     => isset($validated['page_url']) ? $validated['page_url'] : null,
                    'utm_source'   => isset($validated['utm_source']) ? $validated['utm_source'] : null,
                    'utm_medium'   => isset($validated['utm_medium']) ? $validated['utm_medium'] : null,
                    'utm_campaign' => isset($validated['utm_campaign']) ? $validated['utm_campaign'] : null,
                ]
            );

            return response()->json($response);
        }

        // Mensaje normal de conversación
        $response = $this->orchestrator->handleMessage(
            $validated['session_id'],
            $validated['message'],
            'web',
            [
                'page_url'       => isset($validated['page_url']) ? $validated['page_url'] : null,
                'utm_source'     => isset($validated['utm_source']) ? $validated['utm_source'] : null,
                'utm_medium'     => isset($validated['utm_medium']) ? $validated['utm_medium'] : null,
                'utm_campaign'   => isset($validated['utm_campaign']) ? $validated['utm_campaign'] : null,
                'attachment_ids' => isset($validated['attachment_ids']) ? $validated['attachment_ids'] : [],
            ]
        );

        return response()->json($response);
    }
}
