<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SeedNanoBananaImageProvider extends Migration
{
    public function up()
    {
        if (!Schema::connection('tenant')->hasTable('marketing_ai_providers')) {
            return;
        }

        $now = date('Y-m-d H:i:s');

        DB::connection('tenant')->table('marketing_ai_providers')->updateOrInsert(
            ['provider_key' => 'nano-banana'],
            [
                'capability'              => 'image',
                'display_name'            => 'Google Nano Banana',
                'description'             => 'Generación y edición de imágenes con Gemini 3.1 Flash Image.',
                'default_model'           => 'gemini-3.1-flash-image',
                'supported_models'        => json_encode(['gemini-3.1-flash-image']),
                'supports_image_edit'     => 1,
                'supported_qualities'     => null,
                'supported_aspect_ratios' => json_encode(['1:1', '4:5', '16:9', '9:16', '2:3', '3:2']),
                'base_url'                => 'https://generativelanguage.googleapis.com/v1beta/models',
                'api_key_env_var'         => 'CHATBOT_GEMINI_API_KEY',
                'max_tokens_default'      => null,
                'enabled'                 => 1,
                'sort_order'              => 25,
                'pricing_notes'           => 'Usa la API key de Gemini configurada en CHATBOT_GEMINI_API_KEY.',
                'updated_at'              => $now,
                'created_at'              => $now,
            ]
        );
    }

    public function down()
    {
        if (!Schema::connection('tenant')->hasTable('marketing_ai_providers')) {
            return;
        }

        DB::connection('tenant')->table('marketing_ai_providers')
            ->where('provider_key', 'nano-banana')
            ->delete();
    }
}
