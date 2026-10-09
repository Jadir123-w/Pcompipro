<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

/**
 * Tabla chatbot_attachments — Archivos adjuntos por usuarios del chatbot MKT-01.
 *
 * El widget público sube archivos (logos, fotos de referencia, pantallazos)
 * vía POST /api/marketing/chatbot/upload. Los archivos se guardan en disk
 * local bajo storage/app/chatbot-uploads/{tenant_uuid}/{session_id}/{uuid}.{ext}
 * y esta tabla persiste la metadata.
 *
 * Cuando el orchestrator crea un Lead a partir de la sesión, se asocian todos
 * los attachments con el lead_id (UPDATE WHERE session_id = ...).
 *
 * Notas de seguridad:
 * - stored_path NO contiene nombre original (UUID) → evita path traversal
 * - original_name se mantiene solo para mostrar a la ejecutiva
 * - El archivo NO es servido públicamente; solo via endpoint protegido auth
 *   que valida que la ejecutiva tenga permiso de ver leads
 */
class CreateChatbotAttachmentsTable extends Migration
{
    public function up()
    {
        if (Schema::connection('tenant')->hasTable('chatbot_attachments')) {
            return;
        }

        Schema::connection('tenant')->create('chatbot_attachments', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->string('session_id', 64)
                ->comment('FK lógica a chatbot_sessions.session_id');

            $table->unsignedBigInteger('lead_id')->nullable()
                ->comment('FK a marketing_chatbot_leads cuando el lead se crea');

            $table->string('original_name', 255)
                ->comment('Nombre original del archivo subido por el usuario');

            $table->string('stored_path', 500)
                ->comment('Path relativo dentro del disk: chatbot-uploads/{tenant}/{session}/{uuid}.{ext}');

            $table->string('mime_type', 100);

            $table->unsignedInteger('size_bytes');

            $table->enum('kind', ['logo', 'reference', 'screenshot', 'other'])
                ->default('other')
                ->comment('Categoría inferida o declarada por el usuario');

            $table->string('ip_address', 64)->nullable()
                ->comment('IP del uploader para auditoría/anti-abuso');

            $table->string('user_agent', 500)->nullable();

            $table->timestamps();

            $table->index('session_id');
            $table->index('lead_id');
            $table->index('created_at');
        });
    }

    public function down()
    {
        Schema::connection('tenant')->dropIfExists('chatbot_attachments');
    }
}
