<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * COM09 — "Número de muestras" en la pre-cotización.
 *
 * El costo de muestra deja de ingresarse directo y pasa a DERIVARSE:
 *
 *   costo_muestra = numero_muestras × costo_base
 *
 * (el costo unitario de la muestra es el costo_base del producto, antes de
 * margen). Esta migración solo agrega el entero `numero_muestras`; la
 * derivación vive en QuotationCalculationEngine::recalculateItem.
 *
 * Default 0 → "no todos los productos tendrán muestras" (0 muestras = costo 0).
 *
 * Idempotente.
 */
class AddNumeroMuestrasToQuotationPrequoteItems extends Migration
{
    public function up()
    {
        if (!Schema::connection('tenant')->hasTable('quotation_prequote_items')) {
            return;
        }

        Schema::connection('tenant')->table('quotation_prequote_items', function (Blueprint $table) {
            if (!Schema::connection('tenant')->hasColumn('quotation_prequote_items', 'numero_muestras')) {
                $table->integer('numero_muestras')->default(0)->after('costo_muestra')
                    ->comment('Cantidad de muestras. costo_muestra = numero_muestras × costo_base.');
            }
        });
    }

    public function down()
    {
        if (!Schema::connection('tenant')->hasTable('quotation_prequote_items')) {
            return;
        }

        Schema::connection('tenant')->table('quotation_prequote_items', function (Blueprint $table) {
            if (Schema::connection('tenant')->hasColumn('quotation_prequote_items', 'numero_muestras')) {
                $table->dropColumn('numero_muestras');
            }
        });
    }
}
