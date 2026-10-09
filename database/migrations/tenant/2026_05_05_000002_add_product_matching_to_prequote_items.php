<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

/**
 * COM-02 S1 — Matching de productos similares (sec 13-15 doc funcional).
 *
 * Cuando el LLM detecta un producto en el requerimiento, intenta mapearlo a
 * la tabla `items` del ERP. Si la confianza no es suficientemente alta o hay
 * varios candidatos parecidos, se marcan estos campos para que la ejecutiva
 * elija manualmente desde la UI.
 *
 * Campos nuevos en quotation_prequote_items:
 *   - product_match_confidence  (decimal 0-1)
 *       Score del mejor match. NULL si no se intentó o el ítem fue ingresado
 *       a mano por la ejecutiva.
 *   - needs_product_review      (boolean)
 *       true si el matching fue ambiguo (top-2 cercanos) o no encontró match
 *       claro y la ejecutiva debe elegir.
 *   - product_suggestions       (json, array)
 *       Top-5 candidatos con: { item_id, name, description, score, reason,
 *       sale_unit_price, internal_id }. NULL si no hubo búsqueda.
 *
 * Migración aditiva e idempotente.
 */
class AddProductMatchingToPrequoteItems extends Migration
{
    public function up()
    {
        if (!Schema::connection('tenant')->hasTable('quotation_prequote_items')) {
            return;
        }

        Schema::connection('tenant')->table('quotation_prequote_items', function (Blueprint $table) {
            if (!Schema::connection('tenant')->hasColumn('quotation_prequote_items', 'product_match_confidence')) {
                $table->decimal('product_match_confidence', 3, 2)->nullable()
                    ->after('product_reference_image')
                    ->comment('Score 0.00-1.00 del mejor match contra la tabla items. NULL si no se intentó.');
            }

            if (!Schema::connection('tenant')->hasColumn('quotation_prequote_items', 'needs_product_review')) {
                $table->boolean('needs_product_review')->default(false)
                    ->after('product_match_confidence')
                    ->comment('true si el matching fue ambiguo o sin match claro. La ejecutiva debe elegir.');
            }

            if (!Schema::connection('tenant')->hasColumn('quotation_prequote_items', 'product_suggestions')) {
                $table->json('product_suggestions')->nullable()
                    ->after('needs_product_review')
                    ->comment('Top-5 candidatos del catálogo con score y razón, para que la ejecutiva elija.');
            }
        });

        // Índice para que la bandeja "items que necesitan revisión" sea rápida
        if (Schema::connection('tenant')->hasColumn('quotation_prequote_items', 'needs_product_review')) {
            try {
                Schema::connection('tenant')->table('quotation_prequote_items', function (Blueprint $table) {
                    $table->index('needs_product_review', 'idx_needs_product_review');
                });
            } catch (\Exception $e) {
                // El índice puede ya existir
            }
        }
    }

    public function down()
    {
        if (!Schema::connection('tenant')->hasTable('quotation_prequote_items')) {
            return;
        }

        Schema::connection('tenant')->table('quotation_prequote_items', function (Blueprint $table) {
            try { $table->dropIndex('idx_needs_product_review'); } catch (\Exception $e) {}

            if (Schema::connection('tenant')->hasColumn('quotation_prequote_items', 'product_suggestions')) {
                $table->dropColumn('product_suggestions');
            }
            if (Schema::connection('tenant')->hasColumn('quotation_prequote_items', 'needs_product_review')) {
                $table->dropColumn('needs_product_review');
            }
            if (Schema::connection('tenant')->hasColumn('quotation_prequote_items', 'product_match_confidence')) {
                $table->dropColumn('product_match_confidence');
            }
        });
    }
}
