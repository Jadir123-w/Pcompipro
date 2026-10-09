<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Refactor: configs operativas a BD (solo API keys quedan en .env).
 *
 * 1) Agrega columna `options` (JSON nullable) a marketing_chatbot_config para
 *    soportar value_type='select' con lista de opciones permitidas (radio/select
 *    en la UI).
 *
 * 2) Inserta nuevas claves del grupo `mkt03` (provider de imagen, modelo,
 *    quality, format, auto_generate) — todas las que hoy viven en .env y son
 *    DECISIONES OPERATIVAS (no secretos). El admin las puede cambiar desde la
 *    UI de Config Chatbot sin tocar .env.
 *
 * 3) Idempotente: si la columna o las keys ya existen, no las recrea.
 */
class AddOptionsAndMkt03KeysToChatbotConfig extends Migration
{
    public function up()
    {
        // 1) Agregar columna `options` si no existe
        if (!Schema::connection('tenant')->hasColumn('marketing_chatbot_config', 'options')) {
            Schema::connection('tenant')->table('marketing_chatbot_config', function (Blueprint $table) {
                $table->json('options')->nullable()->after('value_type')
                    ->comment('Para value_type=select: array JSON de opciones permitidas. Cada opción {value, label}.');
            });
        }

        // 2) Seed de nuevas keys del grupo mkt03 (idempotente: skip si existe)
        $now = date('Y-m-d H:i:s');

        $rows = [
            [
                'config_key'    => 'mkt03_image_provider',
                'config_value'  => 'dalle3',
                'value_type'    => 'select',
                'options'       => json_encode([
                    ['value' => 'dalle3',      'label' => 'DALL-E 3 (legacy, $0.04/img — no requiere verificación de Org)'],
                    ['value' => 'gpt-image-2', 'label' => 'GPT-Image-2 (recomendado — mejor calidad + imágenes de referencia. Requiere Organization Verification en OpenAI)'],
                ]),
                'group'         => 'mkt03',
                'display_label' => 'Proveedor de imagen (MKT-03)',
                'description'   => 'Proveedor que se usa para generar las imágenes de las piezas de Social Media.',
                'is_sensitive'  => 0,
                'is_encrypted'  => 0,
                'sort_order'    => 10,
                'created_at'    => $now,
                'updated_at'    => $now,
            ],
            [
                'config_key'    => 'mkt03_image_model',
                'config_value'  => 'dall-e-3',
                'value_type'    => 'select',
                'options'       => json_encode([
                    ['value' => 'dall-e-3',    'label' => 'dall-e-3 (provider dalle3)'],
                    ['value' => 'gpt-image-2', 'label' => 'gpt-image-2 (provider gpt-image-2)'],
                ]),
                'group'         => 'mkt03',
                'display_label' => 'Modelo de imagen',
                'description'   => 'Modelo concreto que el provider usa. Asegurate que coincida con el provider seleccionado arriba.',
                'is_sensitive'  => 0,
                'is_encrypted'  => 0,
                'sort_order'    => 20,
                'created_at'    => $now,
                'updated_at'    => $now,
            ],
            [
                'config_key'    => 'mkt03_image_quality',
                'config_value'  => 'medium',
                'value_type'    => 'select',
                'options'       => json_encode([
                    ['value' => 'auto',     'label' => 'auto (provider decide)'],
                    ['value' => 'low',      'label' => 'low (más barato — gpt-image-2 ~$0.006)'],
                    ['value' => 'medium',   'label' => 'medium (balance — gpt-image-2 ~$0.053)'],
                    ['value' => 'high',     'label' => 'high (mejor calidad — gpt-image-2 ~$0.211)'],
                    ['value' => 'standard', 'label' => 'standard (DALL-E 3 — $0.040)'],
                    ['value' => 'hd',       'label' => 'hd (DALL-E 3 — $0.080)'],
                ]),
                'group'         => 'mkt03',
                'display_label' => 'Calidad de imagen',
                'description'   => 'Costo varía según calidad. Solo gpt-image-2 acepta low/medium/high/auto; DALL-E 3 usa standard/hd.',
                'is_sensitive'  => 0,
                'is_encrypted'  => 0,
                'sort_order'    => 30,
                'created_at'    => $now,
                'updated_at'    => $now,
            ],
            [
                'config_key'    => 'mkt03_image_format',
                'config_value'  => 'png',
                'value_type'    => 'select',
                'options'       => json_encode([
                    ['value' => 'png',  'label' => 'PNG (sin pérdida, ideal para piezas con texto)'],
                    ['value' => 'jpeg', 'label' => 'JPEG (más liviano, ideal para fotos)'],
                    ['value' => 'webp', 'label' => 'WebP (mejor compresión moderna)'],
                ]),
                'group'         => 'mkt03',
                'display_label' => 'Formato de imagen',
                'description'   => 'Solo aplica a gpt-image-2; DALL-E 3 siempre devuelve PNG.',
                'is_sensitive'  => 0,
                'is_encrypted'  => 0,
                'sort_order'    => 40,
                'created_at'    => $now,
                'updated_at'    => $now,
            ],
            [
                'config_key'    => 'mkt03_auto_generate_image',
                'config_value'  => '0',
                'value_type'    => 'bool',
                'options'       => null,
                'group'         => 'mkt03',
                'display_label' => 'Generar imagen automáticamente al producir pieza',
                'description'   => 'Si está activo, al producir pieza se genera la imagen DALL-E/GPT-Image automáticamente (consume créditos OpenAI). Si está apagado, solo se genera copy + brief; la imagen se genera on-demand desde la UI.',
                'is_sensitive'  => 0,
                'is_encrypted'  => 0,
                'sort_order'    => 50,
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
        // Borrar las keys mkt03 sembradas (en down conservamos la columna `options` por si otra
        // migración futura ya la consumió)
        DB::connection('tenant')->table('marketing_chatbot_config')
            ->whereIn('config_key', [
                'mkt03_image_provider',
                'mkt03_image_model',
                'mkt03_image_quality',
                'mkt03_image_format',
                'mkt03_auto_generate_image',
            ])
            ->delete();
    }
}
