<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Migración del proveedor Gemini a gemini-3.6-flash.
 *
 * Motivo: Google retiró la línea Gemini 2.x para proyectos nuevos. Desde
 * julio 2026 `generateContent` devuelve 404 "no longer available to new
 * users" para gemini-2.5-flash, gemini-2.5-flash-lite, gemini-2.5-pro y
 * gemini-2.0-flash. Todo tenant nuevo (p. ej. producción, creada esta
 * semana) queda sin poder generar con esos modelos.
 *
 * gemini-3.6-flash (GA jul-2026) es el sucesor directo de la línea flash:
 * 1M de contexto, salida estructurada y thinking configurable.
 *
 * Esta migración deja el catálogo y las configuraciones apuntando a modelos
 * vigentes:
 *   1. marketing_ai_providers (gemini) → default_model, supported_models,
 *      description y pricing_notes vigentes.
 *   2. marketing_agent_config → limpia text_model_override que apunten a
 *      modelos retirados (caen al default corregido del provider).
 *   3. marketing_chatbot_config (vía legacy) → chatbot_gemini_model vigente.
 *
 * Idempotente: si el default ya no está en un modelo retirado, no lo pisa
 * (respeta una elección manual válida del cliente). Refresca siempre el
 * catálogo (supported_models/description/pricing) para que el dropdown de la
 * UI ofrezca modelos vigentes.
 *
 * Referencia: https://ai.google.dev/gemini-api/docs/models
 */
class MigrateGeminiTo36Flash extends Migration
{
    /** Modelos Gemini retirados que deben migrarse. */
    private function retiredModels()
    {
        return [
            'gemini-2.5-flash',
            'gemini-2.5-pro',
            'gemini-2.5-flash-lite',
            'gemini-2.0-flash',
            'gemini-1.5-flash',
            'gemini-1.5-pro',
        ];
    }

    public function up()
    {
        $now = date('Y-m-d H:i:s');

        $newDefault     = 'gemini-3.6-flash';
        $retired        = $this->retiredModels();
        $newSupported   = json_encode([
            'gemini-3.6-flash',
            'gemini-3.5-flash-lite',
            'gemini-flash-latest',
            'gemini-flash-lite-latest',
        ]);
        $newDescription = 'LLM de Google. Gemini 3.6 Flash (GA jul-2026): línea flash rápida, 1M de contexto y salida estructurada; thinking configurable. flash-lite para volumen económico.';
        $newPricing     = 'gemini-3.6-flash: $1.50/MTok input · $7.50/MTok output. gemini-3.5-flash-lite: $0.30 · $2.50. Conversación promedio: ~$0.02.';

        // ─────────────────────────────────────────────────────────
        // 1) Catálogo de providers (marketing_ai_providers)
        // ─────────────────────────────────────────────────────────
        if (Schema::connection('tenant')->hasTable('marketing_ai_providers')) {
            $provider = DB::connection('tenant')
                ->table('marketing_ai_providers')
                ->where('provider_key', 'gemini')
                ->first();

            if ($provider) {
                // Metadatos de catálogo: se refrescan siempre (no son elección
                // del usuario) para que la UI ofrezca modelos vigentes.
                $update = [
                    'supported_models' => $newSupported,
                    'description'      => $newDescription,
                    'pricing_notes'    => $newPricing,
                    'updated_at'       => $now,
                ];
                // default_model: solo se mueve si sigue en un modelo retirado
                // (o vacío), para no pisar una elección manual válida.
                if (empty($provider->default_model) || in_array($provider->default_model, $retired, true)) {
                    $update['default_model'] = $newDefault;
                }

                DB::connection('tenant')
                    ->table('marketing_ai_providers')
                    ->where('provider_key', 'gemini')
                    ->update($update);
            }
        }

        // ─────────────────────────────────────────────────────────
        // 2) Overrides por agente: limpiar los que apunten a modelos muertos
        //    (NULL → caen al default corregido del provider).
        // ─────────────────────────────────────────────────────────
        if (Schema::connection('tenant')->hasTable('marketing_agent_config')) {
            DB::connection('tenant')
                ->table('marketing_agent_config')
                ->whereIn('text_model_override', $retired)
                ->update(['text_model_override' => null]);
        }

        // ─────────────────────────────────────────────────────────
        // 3) Vía legacy (marketing_chatbot_config.chatbot_gemini_model)
        // ─────────────────────────────────────────────────────────
        if (Schema::connection('tenant')->hasTable('marketing_chatbot_config')) {
            DB::connection('tenant')
                ->table('marketing_chatbot_config')
                ->where('config_key', 'chatbot_gemini_model')
                ->whereIn('config_value', $retired)
                ->update([
                    'config_value' => $newDefault,
                    'updated_at'   => $now,
                ]);
        }
    }

    public function down()
    {
        // Rollback a la fila anterior del catálogo (útil solo en testing;
        // los modelos 2.x están retirados y no generan). No se restauran los
        // text_model_override limpiados (apuntaban a modelos muertos).
        $now          = date('Y-m-d H:i:s');
        $oldDefault   = 'gemini-2.5-flash';
        $oldSupported = json_encode([
            'gemini-2.5-flash',
            'gemini-2.5-pro',
            'gemini-2.5-flash-lite',
            'gemini-2.0-flash',
        ]);
        $oldDescription = 'LLM de Google. Gemini 2.5 Flash es ~10x más barato que Claude Sonnet con calidad similar para conversación corta. Pro recomendado para razonamiento complejo (cotización, triage avanzado).';
        $oldPricing   = 'gemini-2.5-flash: $0.075/MTok input · $0.30/MTok output. Pro: $1.25/MTok input · $5/MTok output. Free tier disponible para pruebas (rate-limited).';

        if (Schema::connection('tenant')->hasTable('marketing_ai_providers')) {
            DB::connection('tenant')
                ->table('marketing_ai_providers')
                ->where('provider_key', 'gemini')
                ->where('default_model', 'gemini-3.6-flash')
                ->update([
                    'default_model'    => $oldDefault,
                    'supported_models' => $oldSupported,
                    'description'      => $oldDescription,
                    'pricing_notes'    => $oldPricing,
                    'updated_at'       => $now,
                ]);
        }

        if (Schema::connection('tenant')->hasTable('marketing_chatbot_config')) {
            DB::connection('tenant')
                ->table('marketing_chatbot_config')
                ->where('config_key', 'chatbot_gemini_model')
                ->where('config_value', 'gemini-3.6-flash')
                ->update([
                    'config_value' => $oldDefault,
                    'updated_at'   => $now,
                ]);
        }
    }
}
