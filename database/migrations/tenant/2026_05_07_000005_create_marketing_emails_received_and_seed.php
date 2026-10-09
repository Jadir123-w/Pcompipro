<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * MKT-01 Email Triage — ET1: Modelos + ingesta IMAP.
 *
 * 1) Crea tabla `marketing_emails_received` para persistir los correos leídos
 *    de la bandeja IMAP. Columnas pensadas para soportar el ciclo completo
 *    Triage:
 *      received → classified → draft_pending → replied | discarded
 *
 * 2) Agrega 3 claves faltantes a marketing_chatbot_config (grupo email):
 *      email_imap_host (string)
 *      email_user      (string)
 *      email_password  (string, sensitive, encrypted)
 *    Las demás (email_enabled, email_imap_port, etc.) ya estaban sembradas.
 *
 * Idempotente: chequea existencia antes de crear/insertar.
 */
class CreateMarketingEmailsReceivedAndSeed extends Migration
{
    public function up()
    {
        // ── 1) Tabla marketing_emails_received ──
        if (!Schema::connection('tenant')->hasTable('marketing_emails_received')) {
            Schema::connection('tenant')->create('marketing_emails_received', function (Blueprint $table) {
                $table->bigIncrements('id');

                // Identificadores únicos
                $table->string('message_id', 255)->unique()
                    ->comment('Header Message-ID — clave de deduplicación');
                $table->unsignedBigInteger('imap_uid')->nullable()
                    ->comment('UID dentro de la carpeta IMAP donde se leyó');
                $table->string('imap_folder', 100)->default('INBOX')
                    ->comment('Carpeta IMAP donde se leyó (default INBOX)');

                // Metadata del correo
                $table->string('from_email', 255);
                $table->string('from_name', 255)->nullable();
                $table->string('to_email', 255);
                $table->json('cc_emails')->nullable()
                    ->comment('Lista de emails en CC (JSON array)');
                $table->string('subject', 500)->nullable();
                $table->mediumText('body_plain')->nullable()
                    ->comment('Cuerpo en texto plano (lo que el LLM va a clasificar)');
                $table->mediumText('body_html')->nullable()
                    ->comment('Cuerpo en HTML si vino así (no usado por el LLM)');
                $table->timestamp('received_at')->nullable()
                    ->comment('Fecha del header Date del correo (cuándo lo envió el remitente)');
                $table->boolean('has_attachments')->default(false);
                $table->unsignedInteger('size_bytes')->nullable();

                // Estado del flujo Triage
                $table->string('status', 30)->default('received')
                    ->comment('received | classified | draft_pending | replied | discarded | error');

                // Resultado de la clasificación IA (se llena en ET2)
                $table->string('classification', 50)->nullable()
                    ->comment('rfq | consulta | no_relacionado | spam | otro');
                $table->decimal('classification_confidence', 5, 2)->nullable()
                    ->comment('Confianza 0-100 con la que el LLM clasificó');
                $table->text('classification_reason')->nullable()
                    ->comment('Justificación breve del LLM sobre la clasificación');
                $table->string('ai_provider', 30)->nullable()
                    ->comment('claude | openai | gemini — cuál se usó para clasificar');
                $table->string('ai_model', 100)->nullable();

                // Diagnóstico
                $table->text('error_message')->nullable()
                    ->comment('Si status=error: detalle del fallo (parsing, clasificación, etc.)');

                $table->timestamps();

                // Índices para queries frecuentes
                $table->index('status', 'idx_status');
                $table->index('received_at', 'idx_received_at');
                $table->index('from_email', 'idx_from_email');
                $table->index('classification', 'idx_classification');
            });
        }

        // ── 2) Seed de las 3 keys faltantes en marketing_chatbot_config ──
        $now = date('Y-m-d H:i:s');
        $rows = [
            [
                'config_key'    => 'email_imap_host',
                'config_value'  => null,
                'value_type'    => 'string',
                'options'       => null,
                'group'         => 'email',
                'display_label' => 'IMAP host',
                'description'   => 'Servidor IMAP entrante. Ej: mail.compipro.net',
                'is_sensitive'  => 0,
                'is_encrypted'  => 0,
                'sort_order'    => 11,
                'created_at'    => $now,
                'updated_at'    => $now,
            ],
            [
                'config_key'    => 'email_user',
                'config_value'  => null,
                'value_type'    => 'string',
                'options'       => null,
                'group'         => 'email',
                'display_label' => 'Usuario / dirección de correo',
                'description'   => 'Cuenta dedicada al bot. Ej: informacion@compipro.net',
                'is_sensitive'  => 0,
                'is_encrypted'  => 0,
                'sort_order'    => 41,
                'created_at'    => $now,
                'updated_at'    => $now,
            ],
            [
                'config_key'    => 'email_password',
                'config_value'  => null,
                'value_type'    => 'string',
                'options'       => null,
                'group'         => 'email',
                'display_label' => 'Contraseña',
                'description'   => 'Contraseña de la cuenta IMAP/SMTP. Se guarda CIFRADA en BD con Laravel Crypt.',
                'is_sensitive'  => 1,
                'is_encrypted'  => 1,
                'sort_order'    => 42,
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
        Schema::connection('tenant')->dropIfExists('marketing_emails_received');
        DB::connection('tenant')->table('marketing_chatbot_config')
            ->whereIn('config_key', ['email_imap_host', 'email_user', 'email_password'])
            ->delete();
    }
}
