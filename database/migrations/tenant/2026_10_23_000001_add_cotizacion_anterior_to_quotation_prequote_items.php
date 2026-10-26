<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Producto ya cotizado en otro requerimiento: el ítem guarda de dónde tomó el proveedor
 * y el costo (requerimiento, pre-cotización, fecha y similitud) para mostrarlo en pantalla.
 * JSON en texto; NULL = no se encontró una cotización anterior.
 *
 * Idempotente.
 */
class AddCotizacionAnteriorToQuotationPrequoteItems extends Migration
{
    public function up()
    {
        if (Schema::hasColumn('quotation_prequote_items', 'cotizacion_anterior')) {
            return;
        }

        Schema::table('quotation_prequote_items', function (Blueprint $table) {
            $table->text('cotizacion_anterior')->nullable()
                ->comment('JSON: cotización anterior del mismo producto usada como referencia');
        });
    }

    public function down()
    {
        if (Schema::hasColumn('quotation_prequote_items', 'cotizacion_anterior')) {
            Schema::table('quotation_prequote_items', function (Blueprint $table) {
                $table->dropColumn('cotizacion_anterior');
            });
        }
    }
}
