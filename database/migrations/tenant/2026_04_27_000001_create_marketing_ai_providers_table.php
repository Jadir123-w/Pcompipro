<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Tabla marketing_ai_providers — Catálogo de proveedores de IA disponibles.
 *
 * Permite cambiar de provider sin tocar código. Cada fila representa una
 * implementación concreta (claude, openai, gemini, dalle3, gpt-image-2, flux).
 *
 * Distinguimos por `capability`:
 *   - text:  conversacional / chat (MKT-01, COM-02 quotation, MKT-03 copy generation)
 *   - image: generación de imágenes (MKT-03 piezas)
 *
 * El api_key_env_var apunta a una variable de entorno que tiene la API key.
 * Mantenemos las API keys en .env por seguridad (NUNCA en BD plana).
 *
 * Nota: la tabla viene seedeada con los 4 providers base ya soportados.
 */
class CreateMarketingAiProvidersTable extends Migration
{
    public function up()
    {
        Schema::connection('tenant')->create('marketing_ai_providers', function (Blueprint $table) {
            $table->bigIncrements('id');

            // ── Identidad ──
            $table->string('provider_key', 40)->unique()
                ->comment('claude | openai | gemini | dalle3 | gpt-image-2 | flux | etc.');
            $table->enum('capability', ['text', 'image'])
                ->comment('Tipo de capacidad principal del provider');
            $table->string('display_name', 100)
                ->comment('Nombre humano para mostrar en UI');
            $table->text('description')->nullable();

            // ── Modelo y opciones ──
            $table->string('default_model', 100)
                ->comment('Modelo por defecto cuando se elige este provider');
            $table->json('supported_models')->nullable()
                ->comment('Lista de strings de modelos soportados (para dropdown en UI)');

            // ── Solo para image providers ──
            $table->boolean('supports_image_edit')->default(false)
                ->comment('¿Soporta editImage() con imágenes de referencia?');
            $table->json('supported_qualities')->nullable()
                ->comment('Calidades soportadas: ["low","medium","high","auto"] o ["standard","hd"]');
            $table->json('supported_aspect_ratios')->nullable()
                ->comment('["1:1","4:5","16:9","9:16","2:3","3:2"]');

            // ── Configuración técnica ──
            $table->string('base_url', 255)->nullable()
                ->comment('API endpoint base (override de la default del provider)');
            $table->string('api_key_env_var', 100)->nullable()
                ->comment('Nombre de la env var con la API key. Ej: CHATBOT_CLAUDE_API_KEY');
            $table->unsignedInteger('max_tokens_default')->nullable();

            // ── Estado ──
            $table->boolean('enabled')->default(true);
            $table->integer('sort_order')->default(0);

            // ── Notas referenciales ──
            $table->text('pricing_notes')->nullable()
                ->comment('Pricing aproximado para mostrar al usuario en UI');

            $table->timestamps();

            $table->index(['capability', 'enabled'], 'idx_capability_enabled');
        });

        // ── Seed inicial ──
        $now = date('Y-m-d H:i:s');
        DB::connection('tenant')->table('marketing_ai_providers')->insert([
            [
                'provider_key'             => 'claude',
                'capability'               => 'text',
                'display_name'             => 'Anthropic Claude',
                'description'              => 'Claude de Anthropic. Recomendado para tareas conversacionales B2B con tono cercano y razonamiento profundo.',
                'default_model'            => 'claude-sonnet-4-20250514',
                'supported_models'         => json_encode([
                    'claude-sonnet-4-20250514',
                    'claude-opus-4-20250514',
                    'claude-haiku-4-20250514',
                ]),
                'supports_image_edit'      => 0,
                'supported_qualities'      => null,
                'supported_aspect_ratios'  => null,
                'base_url'                 => 'https://api.anthropic.com/v1/messages',
                'api_key_env_var'          => 'CHATBOT_CLAUDE_API_KEY',
                'max_tokens_default'       => 4096,
                'enabled'                  => 1,
                'sort_order'               => 10,
                'pricing_notes'            => 'Sonnet: $3/MTok input · $15/MTok output. Conversación promedio: ~$0.02',
                'created_at'               => $now,
                'updated_at'               => $now,
            ],
            [
                'provider_key'             => 'openai',
                'capability'               => 'text',
                'display_name'             => 'OpenAI GPT',
                'description'              => 'GPT de OpenAI. Útil como fallback de Claude.',
                'default_model'            => 'gpt-4o-mini',
                'supported_models'         => json_encode([
                    'gpt-4o',
                    'gpt-4o-mini',
                    'gpt-4-turbo',
                    'o1-mini',
                ]),
                'supports_image_edit'      => 0,
                'supported_qualities'      => null,
                'supported_aspect_ratios'  => null,
                'base_url'                 => 'https://api.openai.com/v1/chat/completions',
                'api_key_env_var'          => 'CHATBOT_OPENAI_API_KEY',
                'max_tokens_default'       => 4096,
                'enabled'                  => 1,
                'sort_order'               => 20,
                'pricing_notes'            => 'gpt-4o-mini: $0.15/MTok input · $0.60/MTok output. Más barato que Claude.',
                'created_at'               => $now,
                'updated_at'               => $now,
            ],
            [
                'provider_key'             => 'dalle3',
                'capability'               => 'image',
                'display_name'             => 'OpenAI DALL-E 3 (legacy)',
                'description'              => 'Modelo previo de OpenAI para imágenes. NO soporta referencias visuales. Usar como fallback económico.',
                'default_model'            => 'dall-e-3',
                'supported_models'         => json_encode(['dall-e-3']),
                'supports_image_edit'      => 0,
                'supported_qualities'      => json_encode(['standard', 'hd']),
                'supported_aspect_ratios'  => json_encode(['1:1', '4:5', '16:9', '9:16']),
                'base_url'                 => 'https://api.openai.com/v1/images/generations',
                'api_key_env_var'          => 'CHATBOT_OPENAI_API_KEY',
                'max_tokens_default'       => null,
                'enabled'                  => 1,
                'sort_order'               => 40,
                'pricing_notes'            => '$0.04/img standard · $0.08/img hd (1024×1024)',
                'created_at'               => $now,
                'updated_at'               => $now,
            ],
            [
                'provider_key'             => 'gpt-image-2',
                'capability'               => 'image',
                'display_name'             => 'OpenAI gpt-image-2 (recomendado)',
                'description'              => 'Modelo state-of-the-art de OpenAI. Soporta imágenes de referencia (multimodal). Mejor calidad para B2B.',
                'default_model'            => 'gpt-image-2',
                'supported_models'         => json_encode(['gpt-image-2', 'gpt-image-1.5', 'gpt-image-1', 'gpt-image-1-mini']),
                'supports_image_edit'      => 1,
                'supported_qualities'      => json_encode(['low', 'medium', 'high', 'auto']),
                'supported_aspect_ratios'  => json_encode(['1:1', '4:5', '16:9', '9:16', '2:3', '3:2']),
                'base_url'                 => 'https://api.openai.com/v1/images/generations',
                'api_key_env_var'          => 'CHATBOT_OPENAI_API_KEY',
                'max_tokens_default'       => null,
                'enabled'                  => 1,
                'sort_order'               => 30,
                'pricing_notes'            => 'low ~$0.006 · medium ~$0.053 · high ~$0.211 (1024×1024). Requiere API Org Verification en OpenAI.',
                'created_at'               => $now,
                'updated_at'               => $now,
            ],
        ]);
    }

    public function down()
    {
        Schema::connection('tenant')->dropIfExists('marketing_ai_providers');
    }
}
