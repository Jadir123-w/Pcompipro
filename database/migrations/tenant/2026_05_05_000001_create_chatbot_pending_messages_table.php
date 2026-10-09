<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Migración para WhatsApp Nivel 1 — Aprobación humana antes de enviar.
 *
 * 1) Tabla chatbot_pending_messages: cuando llega un mensaje de WhatsApp y
 *    `wa_approval_required` está activo, en lugar de enviar la respuesta del
 *    LLM directamente al usuario, la encolamos acá. Una ejecutiva la revisa,
 *    la aprueba (o edita o rechaza) desde el panel del ERP, y recién ahí se
 *    dispara el envío real via WhatsAppService.
 *
 * 2) Flags nuevas en marketing_chatbot_config:
 *    - wa_approval_required (bool, default 1): si true, Nivel 1 (con aprobación);
 *      si false, Nivel 2 (envío directo, comportamiento previo).
 *    - wa_app_secret (string, sensible/cifrado): App Secret de Meta para
 *      validar la firma X-Hub-Signature-256. SEPARADO del access_token
 *      (que sirve para enviar). Hoy el código usa el access_token para
 *      validar firma — esto es incorrecto (Meta firma con app_secret) y se
 *      arregla en este PR.
 *
 * Ambos cambios son aditivos.  Idempotentes ante doble corrida.
 */
class CreateChatbotPendingMessagesTable extends Migration
{
    public function up()
    {
        // ── 1) Tabla chatbot_pending_messages ──────────────────────────────
        if (!Schema::connection('tenant')->hasTable('chatbot_pending_messages')) {
            Schema::connection('tenant')->create('chatbot_pending_messages', function (Blueprint $table) {
                $table->bigIncrements('id');

                // ── Identificación de la conversación ──
                $table->string('session_id', 64)
                    ->comment('FK lógica a chatbot_sessions.session_id (formato wa_<numero>)');

                $table->string('wa_id', 30)
                    ->comment('Número de WhatsApp del usuario (E.164 sin +, ej: 51987654321)');

                $table->string('contact_name', 150)->nullable()
                    ->comment('Nombre del contacto tal como WhatsApp lo provee (profile.name)');

                $table->string('incoming_message_id', 100)->nullable()
                    ->comment('ID del mensaje entrante de Meta (para deduplicación)');

                // ── Mensaje del usuario que dispara este pendiente ──
                $table->text('user_message')
                    ->comment('Texto del usuario que el LLM respondió');

                // ── Respuesta sugerida por el LLM ──
                $table->text('ai_reply')
                    ->comment('Respuesta cruda del LLM, lista para enviar');

                $table->string('ai_intent', 60)->nullable()
                    ->comment('Intent detectado por el LLM (saludo, registrar_para_cotizacion, etc.)');

                $table->json('ai_actions')->nullable()
                    ->comment('Bloque actions del JSON del LLM (show_catalog, escalate_to_human, etc.)');

                $table->text('catalog_url')->nullable()
                    ->comment('Si actions.show_catalog está, la URL del PDF que se enviaría como documento');

                $table->string('catalog_line', 100)->nullable();

                // ── Estado de aprobación ──
                $table->enum('status', [
                    'pending',     // Esperando revisión de la ejecutiva
                    'approved',    // Aprobado tal cual, listo para enviar
                    'edited',      // La ejecutiva editó el texto antes de aprobar
                    'rejected',    // Rechazado, no se enviará al usuario
                    'sending',     // Lock optimista mientras se envía a Meta
                    'sent',        // Confirmado el envío exitoso
                    'failed',      // Falló al enviar a Meta
                ])->default('pending');

                // ── Si fue editado, el texto final que se envió ──
                $table->text('final_reply')->nullable()
                    ->comment('Texto efectivamente enviado. Si status=edited, distinto de ai_reply.');

                // ── Auditoría ──
                $table->unsignedInteger('approved_by_user_id')->nullable()
                    ->comment('FK lógica a users.id de la ejecutiva que aprobó/editó/rechazó');

                $table->timestamp('approved_at')->nullable();

                $table->timestamp('sent_at')->nullable()
                    ->comment('Cuándo se confirmó el envío a Meta');

                $table->string('sent_message_id', 100)->nullable()
                    ->comment('ID del mensaje de WhatsApp devuelto por Meta tras envío exitoso');

                $table->text('error_message')->nullable()
                    ->comment('Si status=failed, el mensaje de error de Meta o de red');

                $table->string('rejection_reason', 255)->nullable()
                    ->comment('Si status=rejected, la nota de la ejecutiva (opcional)');

                $table->timestamps();

                // ── Índices ──
                $table->index('session_id');
                $table->index('wa_id');
                $table->index(['status', 'created_at'], 'idx_status_created');
                $table->index('approved_by_user_id');
            });
        }

        // ── 2) Flags en marketing_chatbot_config ───────────────────────────
        if (Schema::connection('tenant')->hasTable('marketing_chatbot_config')) {
            $now = date('Y-m-d H:i:s');

            // 2a) wa_approval_required
            $existsApproval = DB::connection('tenant')
                ->table('marketing_chatbot_config')
                ->where('config_key', 'wa_approval_required')
                ->exists();

            if (!$existsApproval) {
                DB::connection('tenant')->table('marketing_chatbot_config')->insert([
                    'config_key'    => 'wa_approval_required',
                    'config_value'  => '1',
                    'value_type'    => 'bool',
                    'group'         => 'whatsapp',
                    'display_label' => 'Aprobación humana antes de enviar (Nivel 1)',
                    'description'   => 'Si está activo, las respuestas del bot de WhatsApp se encolan y la ejecutiva debe aprobarlas antes del envío. Recomendado mientras se valida el comportamiento. Apagar para Nivel 2 (envío directo).',
                    'is_sensitive'  => 0,
                    'is_encrypted'  => 0,
                    'sort_order'    => 60,
                    'created_at'    => $now,
                    'updated_at'    => $now,
                ]);
            }

            // 2b) wa_app_secret (sensible, cifrado)
            $existsAppSecret = DB::connection('tenant')
                ->table('marketing_chatbot_config')
                ->where('config_key', 'wa_app_secret')
                ->exists();

            if (!$existsAppSecret) {
                DB::connection('tenant')->table('marketing_chatbot_config')->insert([
                    'config_key'    => 'wa_app_secret',
                    'config_value'  => null,
                    'value_type'    => 'string',
                    'group'         => 'whatsapp',
                    'display_label' => 'App Secret de Meta (firma de webhooks)',
                    'description'   => 'Secret de la app de Meta Business. Se usa para validar la firma X-Hub-Signature-256 que viene en cada webhook. ES DISTINTO al Access Token (token sirve para enviar; secret sirve para validar). Se obtiene de Meta Business → tu app → Settings → Basic → App Secret.',
                    'is_sensitive'  => 1,
                    'is_encrypted'  => 1,
                    'sort_order'    => 35,
                    'created_at'    => $now,
                    'updated_at'    => $now,
                ]);
            }
        }
    }

    public function down()
    {
        Schema::connection('tenant')->dropIfExists('chatbot_pending_messages');

        if (Schema::connection('tenant')->hasTable('marketing_chatbot_config')) {
            DB::connection('tenant')
                ->table('marketing_chatbot_config')
                ->whereIn('config_key', ['wa_approval_required', 'wa_app_secret'])
                ->delete();
        }
    }
}
