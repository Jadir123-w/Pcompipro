<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tipo de línea de los ítems de pre-cotización: product, service o comision.
 * Los ítems existentes quedan como 'product' por el valor por defecto.
 *
 * Idempotente: en algunas empresas la columna ya se agregó con el script manual.
 */
class AddTipoLineaToQuotationPrequoteItems extends Migration
{
    public function up()
    {
        if (Schema::hasColumn('quotation_prequote_items', 'tipo_linea')) {
            return;
        }

        Schema::table('quotation_prequote_items', function (Blueprint $table) {
            $table->string('tipo_linea', 20)->default('product')->after('item_order')
                ->comment('Tipo de línea: product, service o comision');
        });
    }

    public function down()
    {
        if (Schema::hasColumn('quotation_prequote_items', 'tipo_linea')) {
            Schema::table('quotation_prequote_items', function (Blueprint $table) {
                $table->dropColumn('tipo_linea');
            });
        }
    }
}
