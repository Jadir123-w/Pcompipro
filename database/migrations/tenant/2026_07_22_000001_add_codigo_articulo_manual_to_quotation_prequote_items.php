<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * COM-02 — "Cód. Artíc." editable por línea de cotización.
 *
 * Agrega `codigo_articulo_manual` a `quotation_prequote_items`: override manual
 * del código de artículo que se imprime en la propuesta. NULL = el ítem hereda
 * el código del catálogo (`items.internal_id`) vía el accessor del modelo, que
 * es el comportamiento actual.
 *
 * El motivo del pedido: el código lleva las iniciales del proveedor, y el
 * proveedor puede cambiar en cualquier momento.
 *
 * VARCHAR(30) para espejar `items.internal_id` y no truncar al compararlos.
 * Sin backfill: todas las cotizaciones existentes siguen mostrando el código
 * del catálogo igual que hoy. Idempotente (el CI corre `tenancy:migrate`).
 */
class AddCodigoArticuloManualToQuotationPrequoteItems extends Migration
{
    public function up()
    {
        if (!Schema::connection('tenant')->hasTable('quotation_prequote_items')) {
            return;
        }

        if (Schema::connection('tenant')->hasColumn('quotation_prequote_items', 'codigo_articulo_manual')) {
            return;
        }

        Schema::connection('tenant')->table('quotation_prequote_items', function (Blueprint $table) {
            $table->string('codigo_articulo_manual', 30)->nullable()->after('item_id');
        });
    }

    public function down()
    {
        if (!Schema::connection('tenant')->hasTable('quotation_prequote_items')) {
            return;
        }

        if (!Schema::connection('tenant')->hasColumn('quotation_prequote_items', 'codigo_articulo_manual')) {
            return;
        }

        Schema::connection('tenant')->table('quotation_prequote_items', function (Blueprint $table) {
            $table->dropColumn('codigo_articulo_manual');
        });
    }
}
