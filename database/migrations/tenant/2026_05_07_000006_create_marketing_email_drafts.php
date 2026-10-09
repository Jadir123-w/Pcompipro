<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MKT-01 Email Triage — ET2: Borrador de respuesta.
 *
 * Tabla `marketing_email_drafts`: 1:1 con marketing_emails_received.
 * Cada correo clasificado como rfq/consulta genera un borrador esperando
 * aprobación humana antes del envío SMTP.
 *
 * Ciclo del borrador:
 *   pending  → recién creado por la IA, esperando revisión humana
 *   edited   → la ejecutiva editó el cuerpo o subject (pero aún no aprobó)
 *   approved → aprobado, listo para que el sender SMTP lo despache (ET3)
 *   rejected → la ejecutiva descartó el borrador (no se envía)
 *   sent     → enviado vía SMTP con éxito (final, terminal)
 *   send_error → falló el envío SMTP (puede reintentarse desde la UI)
 */
class CreateMarketingEmailDrafts extends Migration
{
    public function up()
    {
        if (Schema::connection('tenant')->hasTable('marketing_email_drafts')) {
            return;
        }

        Schema::connection('tenant')->create('marketing_email_drafts', function (Blueprint $table) {
            $table->bigIncrements('id');

            // Relación con el correo recibido (1:1 — un correo, un borrador)
            $table->unsignedBigInteger('email_received_id')->unique()
                ->comment('FK a marketing_emails_received.id — único (un correo, un borrador)');

            // Contenido del borrador (lo que el LLM redactó, editable por humano)
            $table->string('draft_subject', 500)->nullable();
            $table->mediumText('draft_body')->nullable();
            $table->string('to_email', 255);
            $table->json('cc_emails')->nullable();
            $table->string('tone_used', 60)->nullable()
                ->comment('formal | casual | formal_cercano | etc. (lo eligió el LLM)');
            $table->json('data_gaps')->nullable()
                ->comment('Lista de datos que faltan en el correo original (RUC, talla, etc.)');

            // Metadata IA
            $table->string('ai_provider', 30)->nullable();
            $table->string('ai_model', 100)->nullable();

            // Estado del borrador
            $table->string('status', 30)->default('pending')
                ->comment('pending | edited | approved | rejected | sent | send_error');

            // Auditoría humana
            $table->unsignedBigInteger('approver_id')->nullable()
                ->comment('FK a users.id — quien aprobó/editó/rechazó');
            $table->timestamp('edited_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->text('rejected_reason')->nullable();

            // Auditoría de envío SMTP (ET3)
            $table->timestamp('sent_at')->nullable();
            $table->string('sent_message_id', 255)->nullable()
                ->comment('Message-ID del correo enviado vía SMTP (para tracking de respuestas)');
            $table->text('send_error_message')->nullable();

            $table->timestamps();

            $table->index('status', 'idx_drafts_status');
            $table->index('approver_id', 'idx_drafts_approver');
            $table->index('to_email', 'idx_drafts_to_email');
        });
    }

    public function down()
    {
        Schema::connection('tenant')->dropIfExists('marketing_email_drafts');
    }
}
