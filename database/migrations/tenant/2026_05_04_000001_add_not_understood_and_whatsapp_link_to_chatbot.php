<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Migración Priority 1 — Web Chat (MKT-01)
 *
 * 1) Agrega columna `not_understood_count` a `chatbot_sessions`
 *    El orchestrator la incrementa cuando intent_detected = 'no_entendido'
 *    Cuando >= 2 → fuerza escalate_to_human (regla R12 del playbook).
 *
 * 2) Agrega clave `general_whatsapp_link` a `marketing_chatbot_config`
 *    URL pública del WhatsApp Business de COMPIPRO (wa.me/...) — la usa el
 *    chatbot al cierre cuando indica "podés escribirnos por WhatsApp".
 *    Idempotente: solo inserta si no existe.
 *
 * Ambos cambios son aditivos y NO destructivos: seguros para tenants en
 * producción.  Patrón: usa Schema::connection('tenant') explícito (mismo
 * patrón que las migraciones de COM-02 E1).
 */
class AddNotUnderstoodAndWhatsappLinkToChatbot extends Migration
{
    public function up()
    {
        // ── 1) chatbot_sessions: not_understood_count ─────────────────────
        if (Schema::connection('tenant')->hasTable('chatbot_sessions')) {
            if (!Schema::connection('tenant')->hasColumn('chatbot_sessions', 'not_understood_count')) {
                Schema::connection('tenant')->table('chatbot_sessions', function (Blueprint $table) {
                    $table->unsignedSmallInteger('not_understood_count')
                        ->default(0)
                        ->after('closed_reason')
                        ->comment('Veces que el LLM marcó intent=no_entendido en la sesión. Cuando llega a 2 se fuerza escalate_to_human (R12 playbook).');
                });
            }
        }

        // ── 2) marketing_chatbot_config: general_whatsapp_link ────────────
        if (Schema::connection('tenant')->hasTable('marketing_chatbot_config')) {
            $exists = DB::connection('tenant')
                ->table('marketing_chatbot_config')
                ->where('config_key', 'general_whatsapp_link')
                ->exists();

            if (!$exists) {
                $now = date('Y-m-d H:i:s');
                DB::connection('tenant')->table('marketing_chatbot_config')->insert([
                    'config_key'    => 'general_whatsapp_link',
                    'config_value'  => null,
                    'value_type'    => 'string',
                    'group'         => 'general',
                    'display_label' => 'Link público de WhatsApp (wa.me/...)',
                    'description'   => 'URL del WhatsApp Business al que el chatbot redirige al usuario cuando ofrece "escribirnos por WhatsApp" en cierre o escalada. Formato: https://wa.me/51XXXXXXXXX o https://wa.me/51XXXXXXXXX?text=Hola',
                    'is_sensitive'  => 0,
                    'is_encrypted'  => 0,
                    'sort_order'    => 35,
                    'created_at'    => $now,
                    'updated_at'    => $now,
                ]);
            }

            // ── 3) general_max_upload_mb ──
            $existsMax = DB::connection('tenant')
                ->table('marketing_chatbot_config')
                ->where('config_key', 'general_max_upload_mb')
                ->exists();

            if (!$existsMax) {
                $now = date('Y-m-d H:i:s');
                DB::connection('tenant')->table('marketing_chatbot_config')->insert([
                    'config_key'    => 'general_max_upload_mb',
                    'config_value'  => '5',
                    'value_type'    => 'int',
                    'group'         => 'general',
                    'display_label' => 'Tamaño máximo de archivo (MB)',
                    'description'   => 'Límite en MB para archivos que el usuario puede subir desde el widget (logos, fotos de referencia, screenshots). Rango aceptado: 1–50. Tipos permitidos: JPG, PNG, WEBP, GIF, PDF.',
                    'is_sensitive'  => 0,
                    'is_encrypted'  => 0,
                    'sort_order'    => 90,
                    'created_at'    => $now,
                    'updated_at'    => $now,
                ]);
            }
        }
    }

    public function down()
    {
        // Reverse 1)
        if (Schema::connection('tenant')->hasTable('chatbot_sessions')) {
            if (Schema::connection('tenant')->hasColumn('chatbot_sessions', 'not_understood_count')) {
                Schema::connection('tenant')->table('chatbot_sessions', function (Blueprint $table) {
                    $table->dropColumn('not_understood_count');
                });
            }
        }

        // Reverse 2 + 3)
        if (Schema::connection('tenant')->hasTable('marketing_chatbot_config')) {
            DB::connection('tenant')
                ->table('marketing_chatbot_config')
                ->whereIn('config_key', ['general_whatsapp_link', 'general_max_upload_mb'])
                ->delete();
        }
    }
}
