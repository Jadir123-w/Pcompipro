<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Fix de codificación UTF-8 doble en marketing_chatbot_config.
 *
 * Contexto del bug
 * =================
 * La migración 2026_05_05_000001_create_chatbot_pending_messages_table.php
 * insertó el config key `wa_approval_required` cuando la conexión MySQL
 * de Hyn aún no estaba con utf8mb4. El resultado: los caracteres con tilde
 * de `display_label` y `description` quedaron grabados con doble-codificación
 * UTF-8 (los bytes UTF-8 "Aprobación" → C3 B3 se reinterpretaron como latin1,
 * resultando en C3 83 C2 B3 al guardarse).
 *
 * Síntoma visual en la UI:
 *   "AprobaciÃ³n humana antes de enviar (Nivel 1)"   (debería ser "Aprobación")
 *   "Si estÃ¡ activo, ... antes del envÃ­o..."        (debería ser "está", "envío")
 *
 * Solo afecta a UNA fila — el resto de los seeds usaron strings sin
 * caracteres acentuados o se ejecutaron después del fix de charset.
 *
 * Fix
 * ===
 * Esta migración hace UPDATE solo si detecta los bytes corruptos (búsqueda
 * por el patrón "Ã" que solo aparece cuando hay doble-codificación). Si el
 * dato ya está OK (porque alguien lo corrigió manualmente), no hace nada.
 *
 * Idempotente: corre múltiples veces sin efectos secundarios.
 */
class FixUtf8EncodingWaApprovalLabel extends Migration
{
    public function up()
    {
        $now = date('Y-m-d H:i:s');

        // Strings correctos en UTF-8
        $correctLabel       = 'Aprobación humana antes de enviar (Nivel 1)';
        $correctDescription = 'Si está activo, las respuestas del bot de WhatsApp se encolan y la ejecutiva debe aprobarlas antes del envío. Recomendado mientras se valida el comportamiento. Apagar para Nivel 2 (envío directo).';

        // Buscar la fila afectada
        $row = DB::connection('tenant')
            ->table('marketing_chatbot_config')
            ->where('config_key', 'wa_approval_required')
            ->first();

        if (!$row) {
            // No existe la clave todavía (seed pendiente). No hacemos nada;
            // la migración que la crea se encargará.
            return;
        }

        // Detectar doble-codificación buscando los bytes "Ã" (0xC3 0x83 en UTF-8
        // que es lo que vemos cuando un C3 fue interpretado como latin1).
        // Si encontramos ese patrón en display_label o description, asumimos
        // que está roto y lo reemplazamos.
        $isLabelBroken = (strpos($row->display_label, "\xC3\x83") !== false);
        $isDescBroken  = (strpos($row->description ?? '', "\xC3\x83") !== false);

        if ($isLabelBroken || $isDescBroken) {
            DB::connection('tenant')
                ->table('marketing_chatbot_config')
                ->where('config_key', 'wa_approval_required')
                ->update([
                    'display_label' => $correctLabel,
                    'description'   => $correctDescription,
                    'updated_at'    => $now,
                ]);
        }
    }

    public function down()
    {
        // No reversamos el fix de encoding: re-introducir bytes corruptos
        // intencionalmente no tiene sentido. down() es no-op.
    }
}
