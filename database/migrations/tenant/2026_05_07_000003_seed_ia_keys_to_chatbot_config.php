<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Refactor "configs operativas a BD", parte II: AI providers + modelos.
 *
 * Agrega al marketing_chatbot_config las claves de IA que aún viven en .env:
 *
 *   chatbot_ai_provider           — claude | openai | gemini  (provider de TEXTO global)
 *   chatbot_claude_model          — modelo Claude
 *   chatbot_openai_model          — modelo OpenAI
 *   chatbot_gemini_model          — modelo Gemini
 *   chatbot_gemini_max_tokens     — int (default 1024)
 *
 * Las API keys (CHATBOT_CLAUDE_API_KEY, CHATBOT_OPENAI_API_KEY, CHATBOT_GEMINI_API_KEY)
 * SIGUEN en .env porque son secretos puros (alineado con la decisión arquitectónica
 * "solo API keys en .env, todo lo demás en BD").
 *
 * Idempotente: solo inserta si la clave no existe.
 */
class SeedIaKeysToChatbotConfig extends Migration
{
    public function up()
    {
        $now = date('Y-m-d H:i:s');

        $rows = [
            [
                'config_key'    => 'chatbot_ai_provider',
                'config_value'  => 'claude',
                'value_type'    => 'select',
                'options'       => json_encode([
                    ['value' => 'claude', 'label' => 'Claude (Anthropic) — recomendado para conversación'],
                    ['value' => 'openai', 'label' => 'OpenAI GPT — equilibrio calidad/costo'],
                    ['value' => 'gemini', 'label' => 'Gemini (Google) — más barato, multimodal nativo'],
                ]),
                'group'         => 'ia',
                'display_label' => 'Proveedor de IA (texto)',
                'description'   => 'Proveedor de IA usado por defecto en los engines del módulo (chatbot, cotizador, social media). Los engines nuevos (PieceProducer, Quotation) pueden override esto desde Config Agentes IA.',
                'is_sensitive'  => 0,
                'is_encrypted'  => 0,
                'sort_order'    => 10,
                'created_at'    => $now,
                'updated_at'    => $now,
            ],
            [
                'config_key'    => 'chatbot_claude_model',
                'config_value'  => 'claude-sonnet-4-20250514',
                'value_type'    => 'string',
                'options'       => null,
                'group'         => 'ia',
                'display_label' => 'Modelo Claude',
                'description'   => 'Modelo concreto de Anthropic. Ej: claude-sonnet-4-20250514, claude-opus-4-20250121.',
                'is_sensitive'  => 0,
                'is_encrypted'  => 0,
                'sort_order'    => 20,
                'created_at'    => $now,
                'updated_at'    => $now,
            ],
            [
                'config_key'    => 'chatbot_openai_model',
                'config_value'  => 'gpt-4o-mini',
                'value_type'    => 'string',
                'options'       => null,
                'group'         => 'ia',
                'display_label' => 'Modelo OpenAI',
                'description'   => 'Modelo concreto de OpenAI. Ej: gpt-4o, gpt-4o-mini, gpt-4-turbo.',
                'is_sensitive'  => 0,
                'is_encrypted'  => 0,
                'sort_order'    => 30,
                'created_at'    => $now,
                'updated_at'    => $now,
            ],
            [
                'config_key'    => 'chatbot_gemini_model',
                'config_value'  => 'gemini-3.6-flash',
                'value_type'    => 'string',
                'options'       => null,
                'group'         => 'ia',
                'display_label' => 'Modelo Gemini',
                'description'   => 'Modelo concreto de Google. Ej: gemini-3.6-flash, gemini-3.5-flash-lite.',
                'is_sensitive'  => 0,
                'is_encrypted'  => 0,
                'sort_order'    => 40,
                'created_at'    => $now,
                'updated_at'    => $now,
            ],
            [
                'config_key'    => 'chatbot_gemini_max_tokens',
                'config_value'  => '1024',
                'value_type'    => 'int',
                'options'       => null,
                'group'         => 'ia',
                'display_label' => 'Gemini max tokens (output)',
                'description'   => 'Límite de tokens de salida para Gemini. Subir si el JSON de respuesta se corta (típico en agentes que generan grillas grandes).',
                'is_sensitive'  => 0,
                'is_encrypted'  => 0,
                'sort_order'    => 50,
                'created_at'    => $now,
                'updated_at'    => $now,
            ],
        ];

        foreach ($rows as $row) {
            $exists = DB::connection('tenant')
                ->table('marketing_chatbot_config')
                ->where('config_key', $row['config_key'])
                ->exists();
            if (!$exists) {
                DB::connection('tenant')->table('marketing_chatbot_config')->insert($row);
            }
        }
    }

    public function down()
    {
        DB::connection('tenant')->table('marketing_chatbot_config')
            ->whereIn('config_key', [
                'chatbot_ai_provider',
                'chatbot_claude_model',
                'chatbot_openai_model',
                'chatbot_gemini_model',
                'chatbot_gemini_max_tokens',
            ])
            ->delete();
    }
}
