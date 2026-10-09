<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Tabla marketing_chatbot_config — Configuración key/value del agente MKT-01.
 *
 * Mismo patrón que quotation_config (COM-02): permite agregar nuevas claves
 * sin migrations. La UI agrupa las claves en tabs lógicos por convención de
 * prefijo (general_*, wa_*, email_*, faq_*…).
 *
 * Las claves secretas (IMAP password, WhatsApp Meta token) NO viven aquí —
 * siguen en .env. La columna `secret_env_var` apunta al nombre de la env var
 * para que la UI muestre el estado "configurado / faltante" sin exponer el
 * valor.
 */
class CreateMarketingChatbotConfigTable extends Migration
{
    public function up()
    {
        Schema::connection('tenant')->create('marketing_chatbot_config', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->string('config_key', 100)->unique()
                ->comment('Ej: general_greeting | wa_verify_token | email_imap_host');
            $table->text('config_value')->nullable();
            $table->string('value_type', 20)->default('string')
                ->comment('string | text | int | bool | json');
            $table->string('group', 30)
                ->comment('general | whatsapp | email | faqs');
            $table->string('display_label', 150)->nullable()
                ->comment('Etiqueta humana para mostrar en UI');
            $table->text('description')->nullable()
                ->comment('Descripción / hint para la UI');
            $table->string('secret_env_var', 100)->nullable()
                ->comment('DEPRECADO: si la clave era metadata de un secret en .env. Hoy preferimos is_encrypted=1');
            $table->boolean('is_sensitive')->default(false)
                ->comment('Si true, la UI nunca lo muestra en plano (placeholder ●●●). Mostrar solo "configurado / no configurado"');
            $table->boolean('is_encrypted')->default(false)
                ->comment('Si true, config_value se cifra con Laravel Crypt antes de guardar (usa APP_KEY)');
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['group', 'sort_order'], 'idx_group_sort');
        });

        // ── Seed inicial ──
        $now = date('Y-m-d H:i:s');

        $rows = [
            // ═════════════════════════ GENERAL ═════════════════════════
            [
                'config_key'    => 'general_greeting',
                'config_value'  => 'Hola, soy el Asistente Virtual de COMPIPRO. Te ayudo con tu consulta y, si hace falta, te derivo con una ejecutiva. ¿Qué estás buscando para tu empresa?',
                'value_type'    => 'text',
                'group'         => 'general',
                'display_label' => 'Saludo inicial del widget',
                'description'   => 'Primer mensaje que el asistente envía al usuario al abrir el chat.',
                'sort_order'    => 10,
            ],
            [
                'config_key'    => 'general_farewell',
                'config_value'  => 'Gracias por escribirnos. ¡Que tengas un excelente día!',
                'value_type'    => 'text',
                'group'         => 'general',
                'display_label' => 'Despedida del widget',
                'description'   => 'Mensaje de cierre cuando la conversación termina.',
                'sort_order'    => 20,
            ],
            [
                'config_key'    => 'general_enabled',
                'config_value'  => '1',
                'value_type'    => 'bool',
                'group'         => 'general',
                'display_label' => 'Widget activo',
                'description'   => 'Si está apagado, el widget no aparece en la web.',
                'sort_order'    => 30,
            ],
            [
                'config_key'    => 'general_default_assigned_to',
                'config_value'  => null,
                'value_type'    => 'int',
                'group'         => 'general',
                'display_label' => 'Ejecutiva default (user_id)',
                'description'   => 'ID del usuario al que se asignan los leads creados sin regla específica. Vacío = sin asignación automática.',
                'sort_order'    => 40,
            ],
            [
                'config_key'    => 'general_timeout_1',
                'config_value'  => '50',
                'value_type'    => 'int',
                'group'         => 'general',
                'display_label' => 'Timeout 1 — primer reenganche (segundos)',
                'description'   => 'Segundos sin actividad tras los cuales el bot manda un mensaje de reenganche suave (45-60s recomendado, según playbook §3.17).',
                'sort_order'    => 50,
            ],
            [
                'config_key'    => 'general_timeout_2',
                'config_value'  => '150',
                'value_type'    => 'int',
                'group'         => 'general',
                'display_label' => 'Timeout 2 — cierre (segundos)',
                'description'   => 'Segundos sin actividad tras los cuales el bot cierra la conversación y persiste el lead (120-180s recomendado).',
                'sort_order'    => 60,
            ],
            [
                'config_key'    => 'general_max_messages_per_session',
                'config_value'  => '50',
                'value_type'    => 'int',
                'group'         => 'general',
                'display_label' => 'Máx. mensajes por sesión',
                'description'   => 'Límite anti-abuso. Cuando se alcanza, el bot deriva a ejecutiva.',
                'sort_order'    => 70,
            ],
            [
                'config_key'    => 'general_rate_limit_per_min',
                'config_value'  => '20',
                'value_type'    => 'int',
                'group'         => 'general',
                'display_label' => 'Rate limit por minuto (requests)',
                'description'   => 'Máximo de mensajes por minuto que un usuario puede enviar al bot.',
                'sort_order'    => 80,
            ],

            // ═════════════════════════ WHATSAPP ═════════════════════════
            [
                'config_key'    => 'wa_enabled',
                'config_value'  => '0',
                'value_type'    => 'bool',
                'group'         => 'whatsapp',
                'display_label' => 'WhatsApp activo',
                'description'   => 'Si está apagado, el agente solo responde por web. Activar SOLO cuando WHATSAPP_TOKEN esté configurado en .env y el webhook de Meta apunte a este servidor.',
                'sort_order'    => 10,
            ],
            [
                'config_key'    => 'wa_verify_token',
                'config_value'  => null,
                'value_type'    => 'string',
                'group'         => 'whatsapp',
                'display_label' => 'Verify Token (UUID)',
                'description'   => 'Token de verificación que registramos en el panel de Meta. Cualquier UUID alfanumérico sirve. Ej: ecf9952d-6b8e-4de0-ae8f-bf4bcb846850',
                'sort_order'    => 20,
            ],
            [
                'config_key'      => 'wa_access_token',
                'config_value'    => null,
                'value_type'      => 'string',
                'group'           => 'whatsapp',
                'display_label'   => 'Access Token Meta',
                'description'     => 'Token largo de WhatsApp Cloud API generado desde Meta Business. Se guarda CIFRADO en BD con Laravel Crypt (usa APP_KEY como master).',
                'is_sensitive'    => 1,
                'is_encrypted'    => 1,
                'sort_order'      => 30,
            ],
            [
                'config_key'    => 'wa_phone_number_id',
                'config_value'  => null,
                'value_type'    => 'string',
                'group'         => 'whatsapp',
                'display_label' => 'Phone Number ID',
                'description'   => 'ID del número de WhatsApp Business. NO es secreto pero sí privado.',
                'sort_order'    => 40,
            ],
            [
                'config_key'    => 'wa_max_consecutive',
                'config_value'  => '2',
                'value_type'    => 'int',
                'group'         => 'whatsapp',
                'display_label' => 'Máx. mensajes consecutivos (anti-saturación)',
                'description'   => 'Cuántos mensajes seguidos el bot puede enviar sin recibir respuesta del usuario antes de pausar.',
                'sort_order'    => 50,
            ],

            // ═════════════════════════ CORREO (Email Triage) ═════════════════════════
            [
                'config_key'    => 'email_enabled',
                'config_value'  => '0',
                'value_type'    => 'bool',
                'group'         => 'email',
                'display_label' => 'Triage de correo activo',
                'description'   => 'Si está activo, un job cron lee la bandeja IMAP cada N minutos, clasifica los correos con IA y crea leads/borradores. Activar solo cuando IMAP esté configurado.',
                'sort_order'    => 10,
            ],
            [
                'config_key'    => 'email_imap_host',
                'config_value'  => null,
                'value_type'    => 'string',
                'group'         => 'email',
                'display_label' => 'IMAP host',
                'description'   => 'Servidor IMAP entrante. Ej: mail.compipro.net',
                'sort_order'    => 20,
            ],
            [
                'config_key'    => 'email_imap_port',
                'config_value'  => '993',
                'value_type'    => 'int',
                'group'         => 'email',
                'display_label' => 'IMAP port',
                'description'   => '993 (SSL) por default.',
                'sort_order'    => 30,
            ],
            [
                'config_key'    => 'email_imap_encryption',
                'config_value'  => 'ssl',
                'value_type'    => 'string',
                'group'         => 'email',
                'display_label' => 'IMAP encryption',
                'description'   => 'ssl | tls | none',
                'sort_order'    => 40,
            ],
            [
                'config_key'    => 'email_user',
                'config_value'  => null,
                'value_type'    => 'string',
                'group'         => 'email',
                'display_label' => 'Usuario / dirección',
                'description'   => 'Cuenta dedicada al bot. Ej: informacion@compipro.net',
                'sort_order'    => 50,
            ],
            [
                'config_key'      => 'email_password',
                'config_value'    => null,
                'value_type'      => 'string',
                'group'           => 'email',
                'display_label'   => 'Contraseña',
                'description'     => 'Contraseña de la cuenta IMAP/SMTP. Se guarda CIFRADA en BD con Laravel Crypt.',
                'is_sensitive'    => 1,
                'is_encrypted'    => 1,
                'sort_order'      => 60,
            ],
            [
                'config_key'    => 'email_smtp_host',
                'config_value'  => null,
                'value_type'    => 'string',
                'group'         => 'email',
                'display_label' => 'SMTP host',
                'description'   => 'Servidor SMTP saliente para enviar borradores aprobados. Ej: mail.compipro.net',
                'sort_order'    => 70,
            ],
            [
                'config_key'    => 'email_smtp_port',
                'config_value'  => '465',
                'value_type'    => 'int',
                'group'         => 'email',
                'display_label' => 'SMTP port',
                'description'   => '465 (SSL) o 587 (TLS).',
                'sort_order'    => 80,
            ],
            [
                'config_key'    => 'email_smtp_encryption',
                'config_value'  => 'ssl',
                'value_type'    => 'string',
                'group'         => 'email',
                'display_label' => 'SMTP encryption',
                'description'   => 'ssl | tls',
                'sort_order'    => 90,
            ],
            [
                'config_key'    => 'email_folder_inbox',
                'config_value'  => 'INBOX',
                'value_type'    => 'string',
                'group'         => 'email',
                'display_label' => 'Carpeta a monitorear',
                'description'   => 'Carpeta IMAP donde el bot busca correos nuevos. Default: INBOX (bandeja entera).',
                'sort_order'    => 100,
            ],
            [
                'config_key'    => 'email_polling_minutes',
                'config_value'  => '15',
                'value_type'    => 'int',
                'group'         => 'email',
                'display_label' => 'Frecuencia de revisión (minutos)',
                'description'   => 'Cada cuántos minutos se ejecuta el job que lee correos nuevos.',
                'sort_order'    => 110,
            ],
            [
                'config_key'    => 'email_processed_label',
                'config_value'  => 'MKT-01-procesado',
                'value_type'    => 'string',
                'group'         => 'email',
                'display_label' => 'Etiqueta post-procesamiento',
                'description'   => 'Después de procesar un correo, el bot le agrega esta etiqueta para no re-procesarlo.',
                'sort_order'    => 120,
            ],
            [
                'config_key'    => 'email_default_from_name',
                'config_value'  => 'Equipo Comercial COMPIPRO',
                'value_type'    => 'string',
                'group'         => 'email',
                'display_label' => 'Nombre remitente (From)',
                'description'   => 'Nombre que aparece como remitente en los borradores enviados.',
                'sort_order'    => 130,
            ],
            [
                'config_key'    => 'email_disclaimer_ai',
                'config_value'  => '1',
                'value_type'    => 'bool',
                'group'         => 'email',
                'display_label' => 'Incluir disclaimer "preparado con asistencia de IA"',
                'description'   => 'Agrega una línea al final del borrador indicando uso de IA. Buena práctica de transparencia.',
                'sort_order'    => 140,
            ],
        ];

        // Completar campos faltantes con defaults
        foreach ($rows as &$r) {
            if (!isset($r['secret_env_var'])) $r['secret_env_var'] = null;
            if (!isset($r['is_sensitive']))   $r['is_sensitive']   = 0;
            if (!isset($r['is_encrypted']))   $r['is_encrypted']   = 0;
            $r['created_at'] = $now;
            $r['updated_at'] = $now;
        }

        DB::connection('tenant')->table('marketing_chatbot_config')->insert($rows);
    }

    public function down()
    {
        Schema::connection('tenant')->dropIfExists('marketing_chatbot_config');
    }
}
