<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * MKT-01 WhatsApp — aclarar el toggle "modo de respuesta" (Manual / Automatizado).
 *
 * El modo automático del chatbot de WhatsApp YA existe: lo gobierna el flag
 * booleano `wa_approval_required` en marketing_chatbot_config
 *   - ON  (1) = Manual      → cada respuesta se encola y un ejecutivo la aprueba.
 *   - OFF (0) = Automatizado → el agente responde solo, sin aprobación (como el chat web).
 *
 * El cliente pidió ver claramente los DOS modos en Configuración. El switch ya
 * aparece (la UI de settings renderiza cualquier fila bool), pero su texto
 * ("Aprobación humana antes de enviar (Nivel 1)") no dejaba claro que apagarlo
 * activa el modo automático. Esta migración solo REESCRIBE el rótulo y la
 * descripción de esa fila para que la ejecutiva entienda el binomio.
 *
 * No cambia el comportamiento ni el valor; solo textos. Idempotente.
 */
class ImproveWaApprovalRequiredWording extends Migration
{
    public function up()
    {
        if (!Schema::connection('tenant')->hasTable('marketing_chatbot_config')) {
            return;
        }

        DB::connection('tenant')->table('marketing_chatbot_config')
            ->where('config_key', 'wa_approval_required')
            ->update([
                'display_label' => 'Modo de respuesta de WhatsApp — Aprobación manual (ON = Manual · OFF = Automatizado)',
                'description'   => 'Define cómo responde el agente de WhatsApp. ACTIVADO (Manual): cada respuesta del agente se encola y un ejecutivo debe aprobarla antes de enviarla al cliente (aparecen en Pendientes de WhatsApp). DESACTIVADO (Automatizado): el agente responde solo, al instante, sin aprobación humana — conversación fluida, igual que el chat web.',
                'updated_at'    => date('Y-m-d H:i:s'),
            ]);
    }

    public function down()
    {
        if (!Schema::connection('tenant')->hasTable('marketing_chatbot_config')) {
            return;
        }

        DB::connection('tenant')->table('marketing_chatbot_config')
            ->where('config_key', 'wa_approval_required')
            ->update([
                'display_label' => 'Aprobación humana antes de enviar (Nivel 1)',
                'description'   => 'Si está activo, las respuestas del bot de WhatsApp se encolan y la ejecutiva debe aprobarlas antes del envío. Recomendado mientras se valida el comportamiento. Apagar para Nivel 2 (envío directo).',
                'updated_at'    => date('Y-m-d H:i:s'),
            ]);
    }
}
