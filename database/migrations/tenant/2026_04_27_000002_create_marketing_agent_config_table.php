<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Tabla marketing_agent_config — Configuración por agente.
 *
 * Una fila por agente (mkt01, com02, mkt03). Apunta a un text_provider y
 * (solo MKT-03) un image_provider, con parámetros custom (temperatura, quality,
 * etc.) que se aplican en runtime en lugar de leer de .env.
 *
 * Si una columna está NULL, se usa el default del provider apuntado.
 *
 * Diseño 1:1 (un agente, una configuración) — no necesitamos múltiples configs
 * por agente en MVP. Si en el futuro queremos A/B testing, agregamos columna
 * `variant` y la PK pasa a ser (agent_key, variant).
 */
class CreateMarketingAgentConfigTable extends Migration
{
    public function up()
    {
        Schema::connection('tenant')->create('marketing_agent_config', function (Blueprint $table) {
            $table->bigIncrements('id');

            // ── Identidad del agente ──
            $table->string('agent_key', 20)->unique()
                ->comment('mkt01 | com02 | mkt03');
            $table->string('display_name', 100);
            $table->text('description')->nullable();

            // ── Provider de TEXTO ──
            $table->unsignedBigInteger('text_provider_id')->nullable()
                ->comment('FK a marketing_ai_providers (capability=text)');
            $table->string('text_model_override', 100)->nullable()
                ->comment('Si null, usa default del provider');
            $table->decimal('text_temperature', 3, 2)->nullable()
                ->comment('0.00 a 2.00. Null = default del Service');
            $table->unsignedInteger('text_max_tokens')->nullable();

            // ── Provider de IMAGEN (solo aplica a MKT-03) ──
            $table->unsignedBigInteger('image_provider_id')->nullable()
                ->comment('FK a marketing_ai_providers (capability=image)');
            $table->string('image_model_override', 100)->nullable();
            $table->string('image_quality', 20)->nullable()
                ->comment('low|medium|high|auto (gpt-image-2) o standard|hd (dalle3)');
            $table->string('image_format', 10)->nullable()
                ->comment('png | jpeg | webp');
            $table->string('image_aspect_ratio_default', 10)->nullable()
                ->comment('1:1 | 4:5 | 16:9 | 9:16 | 2:3 | 3:2');
            $table->boolean('image_auto_generate')->default(false)
                ->comment('Generar imagen automáticamente al producir pieza (consume créditos)');

            // ── Extras (configuración custom por agente) ──
            $table->json('extra_params')->nullable()
                ->comment('Parámetros específicos del agente. Ej: timeouts, rate_limits, prompt overrides');

            // ── Estado ──
            $table->boolean('enabled')->default(true);

            $table->timestamps();

            // ── FKs (con SET NULL para no romper si se elimina un provider) ──
            $table->foreign('text_provider_id')
                ->references('id')->on('marketing_ai_providers')
                ->onDelete('set null');
            $table->foreign('image_provider_id')
                ->references('id')->on('marketing_ai_providers')
                ->onDelete('set null');
        });

        // ── Seed inicial: 3 agentes con config alineada al .env actual ──
        $now = date('Y-m-d H:i:s');

        // Resolver IDs por provider_key (viene del seed de la migración previa)
        $providers = DB::connection('tenant')->table('marketing_ai_providers')
            ->pluck('id', 'provider_key');
        $claudeId       = isset($providers['claude'])       ? $providers['claude']       : null;
        $gptImage2Id    = isset($providers['gpt-image-2'])  ? $providers['gpt-image-2']  : null;

        DB::connection('tenant')->table('marketing_agent_config')->insert([
            [
                'agent_key'                  => 'mkt01',
                'display_name'               => 'MKT-01 Chatbot — Atención y FAQ',
                'description'                => 'Chatbot conversacional para Web + WhatsApp + Correo. Conduce leads, registra requerimientos y deriva a ejecutiva.',
                'text_provider_id'           => $claudeId,
                'text_model_override'        => null,
                'text_temperature'           => 0.70,
                'text_max_tokens'            => 1024,
                'image_provider_id'          => null,
                'image_model_override'       => null,
                'image_quality'              => null,
                'image_format'               => null,
                'image_aspect_ratio_default' => null,
                'image_auto_generate'        => 0,
                'extra_params'               => null,
                'enabled'                    => 1,
                'created_at'                 => $now,
                'updated_at'                 => $now,
            ],
            [
                'agent_key'                  => 'com02',
                'display_name'               => 'COM-02 Cotizador Inteligente',
                'description'                => 'Genera precotizaciones y propuestas comerciales formales a partir de requerimientos del ERP o texto libre.',
                'text_provider_id'           => $claudeId,
                'text_model_override'        => null,
                'text_temperature'           => 0.30, // Más bajo: cotizar quiere precisión, no creatividad
                'text_max_tokens'            => 2000,
                'image_provider_id'          => null,
                'image_model_override'       => null,
                'image_quality'              => null,
                'image_format'               => null,
                'image_aspect_ratio_default' => null,
                'image_auto_generate'        => 0,
                'extra_params'               => null,
                'enabled'                    => 1,
                'created_at'                 => $now,
                'updated_at'                 => $now,
            ],
            [
                'agent_key'                  => 'mkt03',
                'display_name'               => 'MKT-03 Social Media Content',
                'description'                => 'Genera grillas editoriales y piezas publicables (copy + CTA + hashtags + imagen) para redes sociales.',
                'text_provider_id'           => $claudeId,
                'text_model_override'        => null,
                'text_temperature'           => 0.80, // Más alto: creatividad para copy
                'text_max_tokens'            => 2000,
                'image_provider_id'          => $gptImage2Id,
                'image_model_override'       => null,
                'image_quality'              => 'medium',
                'image_format'               => 'png',
                'image_aspect_ratio_default' => '1:1',
                'image_auto_generate'        => 0,
                'extra_params'               => null,
                'enabled'                    => 1,
                'created_at'                 => $now,
                'updated_at'                 => $now,
            ],
        ]);
    }

    public function down()
    {
        Schema::connection('tenant')->dropIfExists('marketing_agent_config');
    }
}
