<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Seed del flag `email_smtp_verify_ssl` para el Email Triage del MKT-01.
 *
 * Permite deshabilitar la verificación del certificado SSL del servidor SMTP
 * cuando se conecta para enviar borradores aprobados. Útil para:
 *
 *   - Servers de cliente con certificados auto-firmados o cadenas SSL
 *     incompletas (más común de lo que parece en mail-servers tipo cPanel).
 *   - Entornos de testing donde el container Docker no tiene un CA bundle
 *     actualizado y por eso falla la verificación incluso contra
 *     certificados válidos.
 *
 * Por defecto = `true` (seguro). Solo se desactiva con conocimiento del caso.
 *
 * Idempotente: si la clave ya existe (insertada a mano durante testing),
 * NO se sobreescribe.
 */
class SeedEmailSmtpVerifySsl extends Migration
{
    public function up()
    {
        $now = date('Y-m-d H:i:s');

        $row = [
            'config_key'    => 'email_smtp_verify_ssl',
            'config_value'  => '1', // default: verificar (seguro)
            'value_type'    => 'boolean',
            'options'       => null,
            'group'         => 'email',
            'display_label' => 'Verificar certificado SSL del servidor SMTP',
            'description'   => 'Si está activo (recomendado), valida el certificado SSL del servidor SMTP al enviar borradores. Desactivar SOLO si el server del cliente tiene certificados auto-firmados o cadenas SSL problemáticas.',
            'is_sensitive'  => 0,
            'is_encrypted'  => 0,
            'sort_order'    => 60,
            'created_at'    => $now,
            'updated_at'    => $now,
        ];

        $exists = DB::connection('tenant')
            ->table('marketing_chatbot_config')
            ->where('config_key', $row['config_key'])
            ->exists();

        if (!$exists) {
            DB::connection('tenant')->table('marketing_chatbot_config')->insert($row);
        }
    }

    public function down()
    {
        DB::connection('tenant')->table('marketing_chatbot_config')
            ->where('config_key', 'email_smtp_verify_ssl')
            ->delete();
    }
}
