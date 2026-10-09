<?php

namespace Modules\Marketing\Http\Controllers;

use Illuminate\Routing\Controller;

/**
 * ChatbotWidgetController
 * 
 * Controlador para servir el widget JavaScript del chatbot.
 * El widget es un IIFE (Immediately Invoked Function Expression) que:
 * 1. Se carga desde cualquier dominio
 * 2. Inyecta estilos y HTML en la página
 * 3. Carga configuración desde /api/marketing/chatbot/settings
 * 4. Envía leads a /api/marketing/chatbot/leads
 */
class ChatbotWidgetController extends Controller
{
    /**
     * Servir el widget JavaScript
     * 
     * GET /marketing/chatbot/widget.js
     * 
     * @return \Illuminate\Http\Response
     */
    public function js()
    {
        // Renderizar la vista como JavaScript
        $content = view('marketing::chatbot.widget-js')->render();
        
        return response($content, 200)
            ->header('Content-Type', 'application/javascript; charset=UTF-8')
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
    }
}
