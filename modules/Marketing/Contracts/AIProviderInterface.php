<?php

namespace Modules\Marketing\Contracts;

/**
 * AIProviderInterface
 *
 * Contrato agnóstico para proveedores de IA (Claude, OpenAI, etc.)
 * Permite cambiar de proveedor con solo modificar una variable de entorno.
 */
interface AIProviderInterface
{
    /**
     * Enviar un mensaje al LLM y obtener la respuesta.
     *
     * @param  string  $systemPrompt   Prompt del sistema (playbook completo)
     * @param  array   $messages       Historial de mensajes
     * @param  array   $options        Opciones adicionales:
     *
     *   - 'temperature' (float)
     *   - 'max_tokens'  (int)
     *   - 'model'       (string)  Override del modelo configurado
     *
     *   COM-02 S2 — multimodal:
     *   - 'images' (array)  Imágenes a adjuntar al PRIMER user message del
     *     historial. Cada item: ['mime_type' => 'image/jpeg', 'data' => '<base64>']
     *     Cada provider lo formatea según su API (Claude: content blocks,
     *     Gemini: inline_data parts, OpenAI: image_url).  Si el provider no
     *     soporta multimodal, se ignora silenciosamente.
     *
     * @return string                  Respuesta raw del LLM
     */
    public function sendMessage($systemPrompt, array $messages, array $options = []);

    /**
     * Nombre del proveedor (para logs).
     *
     * @return string
     */
    public function getProviderName();

    /**
     * Modelo en uso (para logs).
     *
     * @return string
     */
    public function getModelName();
}
