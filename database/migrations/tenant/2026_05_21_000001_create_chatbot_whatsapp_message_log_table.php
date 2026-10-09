<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MKT-01 WhatsApp — Log temporal de mensajes para diagnóstico.
 *
 * Contexto
 * ========
 * Mientras validamos que los mensajes de WhatsApp efectivamente llegan al
 * webhook (problema reportado por el cliente durante la configuración inicial
 * de Meta), guardamos cada inbound + outbound en BD para poder verificarlo
 * directamente con DBeaver/SQL — sin depender de logs de Laravel o de la
 * cache (que vence).
 *
 * Por convención del módulo, el historial completo de la conversación NO se
 * persiste — vive en Redis cache y se pierde al cerrarse la sesión. Esta
 * tabla es una excepción intencional, temporal, solo para WhatsApp.
 *
 * Plan de remoción
 * ================
 * Una vez confirmado que el flujo WhatsApp es estable, esta tabla puede
 * eliminarse con una migración drop. No tiene FKs ni dependencias en el
 * código operativo — solo es diagnóstico.
 */
class CreateChatbotWhatsappMessageLogTable extends Migration
{
    public function up()
    {
        if (Schema::connection('tenant')->hasTable('chatbot_whatsapp_message_log')) {
            return;
        }

        Schema::connection('tenant')->create('chatbot_whatsapp_message_log', function (Blueprint $table) {
            $table->bigIncrements('id');

            // ── Identificación de la conversación ──
            $table->string('session_id', 64)
                ->comment('FK lógica a chatbot_sessions.session_id (formato wa_<numero>)');
            $table->string('wa_id', 30)
                ->comment('Número de WhatsApp del usuario (E.164 sin +)');
            $table->string('contact_name', 150)->nullable();

            // ── Dirección del mensaje ──
            $table->enum('direction', ['inbound', 'outbound'])
                ->comment('inbound = del usuario al bot; outbound = del bot al usuario (lo que la IA generó, antes de aprobación)');

            // ── Contenido del mensaje ──
            $table->text('message_text');

            // ── Metadata de la IA (solo para outbound) ──
            $table->string('ai_intent', 60)->nullable()
                ->comment('Intent detectado por el LLM (saludo, registrar_para_cotizacion, etc.)');
            $table->string('ai_provider', 30)->nullable()
                ->comment('claude | openai | gemini');
            $table->string('ai_model', 100)->nullable();

            // ── Meta del payload (JSON con entities, actions, etc.) ──
            $table->json('meta')->nullable()
                ->comment('Para inbound: {message_id de Meta, attachments}. Para outbound: {entities_extracted, actions, confidence, etc.}');

            // ── Trazabilidad del mensaje de Meta ──
            $table->string('meta_message_id', 100)->nullable()
                ->comment('ID del mensaje de Meta (para inbound, el incoming_message_id)');

            // Sin updated_at — los logs son inmutables
            $table->timestamp('created_at')->useCurrent();

            // ── Índices para queries comunes ──
            $table->index(['session_id', 'created_at'], 'idx_session_created');
            $table->index('wa_id', 'idx_wa_id');
            $table->index('direction', 'idx_direction');
        });
    }

    public function down()
    {
        Schema::connection('tenant')->dropIfExists('chatbot_whatsapp_message_log');
    }
}
