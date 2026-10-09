<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Seed del proveedor Gemini en marketing_ai_providers.
 *
 * Se agrega como fila adicional (no reemplaza Claude/OpenAI). El cliente
 * podrá elegir el provider por agente desde la UI agents-config; el dropdown
 * lee directamente de esta tabla.
 *
 * Idempotente: solo inserta si la fila no existe.
 */
class SeedGeminiProvider extends Migration
{
    public function up()
    {
        if (!Schema::connection('tenant')->hasTable('marketing_ai_providers')) {
            // Si la tabla aún no existe (tenant nuevo), saltamos sin error;
            // la migración base ya tendrá Gemini cuando se agregue al seed inicial.
            return;
        }

        $exists = DB::connection('tenant')
            ->table('marketing_ai_providers')
            ->where('provider_key', 'gemini')
            ->exists();

        if ($exists) {
            return;
        }

        $now = date('Y-m-d H:i:s');

        DB::connection('tenant')->table('marketing_ai_providers')->insert([
            'provider_key'             => 'gemini',
            'capability'               => 'text',
            'display_name'             => 'Google Gemini',
            'description'              => 'LLM de Google. Gemini 3.6 Flash (GA jul-2026): línea flash rápida, 1M de contexto y salida estructurada; thinking configurable. flash-lite para volumen económico.',
            'default_model'            => 'gemini-3.6-flash',
            'supported_models'         => json_encode([
                'gemini-3.6-flash',
                'gemini-3.5-flash-lite',
                'gemini-flash-latest',
                'gemini-flash-lite-latest',
            ]),
            'supports_image_edit'      => 0,
            'supported_qualities'      => null,
            'supported_aspect_ratios'  => null,
            'base_url'                 => 'https://generativelanguage.googleapis.com/v1beta/models',
            'api_key_env_var'          => 'CHATBOT_GEMINI_API_KEY',
            'max_tokens_default'       => 1024,
            'enabled'                  => 1,
            'sort_order'               => 30,
            'pricing_notes'            => 'gemini-3.6-flash: $1.50/MTok input · $7.50/MTok output. gemini-3.5-flash-lite: $0.30 · $2.50. Conversación promedio: ~$0.02.',
            'created_at'               => $now,
            'updated_at'               => $now,
        ]);
    }

    public function down()
    {
        if (!Schema::connection('tenant')->hasTable('marketing_ai_providers')) {
            return;
        }
        DB::connection('tenant')
            ->table('marketing_ai_providers')
            ->where('provider_key', 'gemini')
            ->delete();
    }
}
