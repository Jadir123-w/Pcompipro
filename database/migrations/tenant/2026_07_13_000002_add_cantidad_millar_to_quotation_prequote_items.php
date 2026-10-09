<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * COM-02 — "Cantidad por Millar" + Costo Total de Impresión.
 *
 * Agrega `cantidad_millar` a quotation_prequote_items. Es el 4º factor manual del
 * Costo Total de Impresión (Excel del cliente, AD = Z × AA × AB × AC):
 *
 *   Costo_Total_Impresion = costo_impresion × cantidad_colores × cantidad_lados × cantidad_millar
 *
 * Ese costo entra al costeo como FIJO del pedido (NO escala por la cantidad del
 * pedido). `default(1)` es el valor neutro: con colores/lados/millar = 1 el
 * resultado es idéntico a hoy (retrocompatible). Idempotente.
 */
class AddCantidadMillarToQuotationPrequoteItems extends Migration
{
    public function up()
    {
        if (!Schema::connection('tenant')->hasTable('quotation_prequote_items')) {
            return;
        }

        Schema::connection('tenant')->table('quotation_prequote_items', function (Blueprint $table) {
            if (!Schema::connection('tenant')->hasColumn('quotation_prequote_items', 'cantidad_millar')) {
                $table->decimal('cantidad_millar', 8, 2)->default(1)->after('cantidad_lados')
                    ->comment('Factor manual "Cantidad por Millar". Costo Total Impresion = costo_impresion x cantidad_colores x cantidad_lados x cantidad_millar.');
            }
        });
    }

    public function down()
    {
        if (!Schema::connection('tenant')->hasTable('quotation_prequote_items')) {
            return;
        }

        Schema::connection('tenant')->table('quotation_prequote_items', function (Blueprint $table) {
            if (Schema::connection('tenant')->hasColumn('quotation_prequote_items', 'cantidad_millar')) {
                $table->dropColumn('cantidad_millar');
            }
        });
    }
}
