<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Migración a Claude Sonnet 4.6 (claude-sonnet-4-6).
 *
 * Motivo: Anthropic anunció la deprecación de `claude-sonnet-4-20250514`
 *   - Deprecated: 14 de abril de 2026
 *   - Tentative retirement: 15 de junio de 2026
 *
 * Sonnet 4.6 ofrece (al mismo precio):
 *   - 1M tokens de context window (vs 200k en Sonnet 4)
 *   - 64k tokens de max output (vs 8k en Sonnet 4)
 *   - Extended thinking (mejor razonamiento)
 *
 * Esta migración:
 *   1. Actualiza marketing_chatbot_config.chatbot_claude_model si está en
 *      `claude-sonnet-4-20250514` → `claude-sonnet-4-6`
 *   2. Actualiza marketing_ai_providers (catálogo) para que el default y
 *      la lista de soportados reflejen los modelos vigentes
 *
 * Idempotente: si ya está migrado, no hace nada.
 *
 * Referencia: https://docs.claude.com/en/docs/about-claude/model-deprecations
 */
class MigrateToClaudeSonnet46 extends Migration
{
    public function up()
    {
        $now = date('Y-m-d H:i:s');

        // ─────────────────────────────────────────────────────────
        // 1) Actualizar chatbot_claude_model en marketing_chatbot_config
        // ─────────────────────────────────────────────────────────
        $modelKey = 'chatbot_claude_model';
        $oldId    = 'claude-sonnet-4-20250514';
        $newId    = 'claude-sonnet-4-6';

        $current = DB::connection('tenant')
            ->table('marketing_chatbot_config')
            ->where('config_key', $modelKey)
            ->value('config_value');

        if ($current === $oldId) {
            DB::connection('tenant')
                ->table('marketing_chatbot_config')
                ->where('config_key', $modelKey)
                ->update([
                    'config_value' => $newId,
                    'updated_at'   => $now,
                ]);
        }

        // ─────────────────────────────────────────────────────────
        // 2) Actualizar el catálogo de providers (marketing_ai_providers)
        //    - default_model: el nuevo Sonnet 4.6
        //    - supported_models: incluye los modelos vigentes (Sonnet 4.6,
        //      Opus 4.7, Haiku 4.5 y Sonnet 4.5 como fallback estable)
        //    - description: actualiza la mención del modelo recomendado
        //    - pricing_notes: nuevos precios
        // ─────────────────────────────────────────────────────────
        $newDefault    = 'claude-sonnet-4-6';
        $newSupported  = json_encode([
            'claude-sonnet-4-6',
            'claude-opus-4-7',
            'claude-haiku-4-5',
            'claude-sonnet-4-5-20250929',
        ]);
        $newPricing    = 'Sonnet 4.6: $3/$15 per MTok | Opus 4.7: $5/$25 | Haiku 4.5: $1/$5. Conversación promedio Sonnet: ~$0.02';

        $providerExists = DB::connection('tenant')
            ->table('marketing_ai_providers')
            ->where('provider_key', 'claude')
            ->exists();

        if ($providerExists) {
            // Solo actualizar si el default todavía está en el modelo viejo,
            // para no pisar configuraciones manuales del cliente.
            $currentDefault = DB::connection('tenant')
                ->table('marketing_ai_providers')
                ->where('provider_key', 'claude')
                ->value('default_model');

            if ($currentDefault === $oldId) {
                DB::connection('tenant')
                    ->table('marketing_ai_providers')
                    ->where('provider_key', 'claude')
                    ->update([
                        'default_model'    => $newDefault,
                        'supported_models' => $newSupported,
                        'pricing_notes'    => $newPricing,
                        'updated_at'       => $now,
                    ]);
            }
        }
    }

    public function down()
    {
        // Rollback: vuelve al modelo viejo (NO recomendado en producción
        // después del 15-jun-2026, pero útil en testing si hace falta).
        $now      = date('Y-m-d H:i:s');
        $oldId    = 'claude-sonnet-4-20250514';
        $oldSupported = json_encode([
            'claude-sonnet-4-20250514',
            'claude-opus-4-20250514',
            'claude-haiku-4-20250514',
        ]);
        $oldPricing = 'Sonnet: $3/MTok input · $15/MTok output. Conversación promedio: ~$0.02';

        DB::connection('tenant')
            ->table('marketing_chatbot_config')
            ->where('config_key', 'chatbot_claude_model')
            ->where('config_value', 'claude-sonnet-4-6')
            ->update(['config_value' => $oldId, 'updated_at' => $now]);

        DB::connection('tenant')
            ->table('marketing_ai_providers')
            ->where('provider_key', 'claude')
            ->where('default_model', 'claude-sonnet-4-6')
            ->update([
                'default_model'    => $oldId,
                'supported_models' => $oldSupported,
                'pricing_notes'    => $oldPricing,
                'updated_at'       => $now,
            ]);
    }
}
