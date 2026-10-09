<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * COM-02 — SCTR unitario (por producto) con override.
 *
 * Agrega `sctr_porcentaje` (nullable) a quotation_prequote_items. Define el % de
 * SCTR propio de ESE ítem, que pisa el de la cotización.
 *
 * Cascada de resolución (motor):
 *   item.sctr_porcentaje  →  prequote.sctr_porcentaje  →  global del config.
 *
 *   NULL  → hereda el % de la cotización (o el global si la cotización tampoco tiene).
 *   valor → ese % se aplica solo a este ítem.
 *
 * Los ítems actuales quedan en NULL → heredan como hasta hoy (retrocompatible:
 * mismos totales que R3 / que hoy). Idempotente.
 */
class AddSctrPorcentajeToQuotationPrequoteItems extends Migration
{
    public function up()
    {
        if (!Schema::connection('tenant')->hasTable('quotation_prequote_items')) {
            return;
        }

        Schema::connection('tenant')->table('quotation_prequote_items', function (Blueprint $table) {
            if (!Schema::connection('tenant')->hasColumn('quotation_prequote_items', 'sctr_porcentaje')) {
                $table->decimal('sctr_porcentaje', 5, 2)->nullable()->after('margen_porcentaje')
                    ->comment('% de SCTR propio del ítem. NULL = hereda el de la cotización (y este el global).');
            }
        });
    }

    public function down()
    {
        if (!Schema::connection('tenant')->hasTable('quotation_prequote_items')) {
            return;
        }

        Schema::connection('tenant')->table('quotation_prequote_items', function (Blueprint $table) {
            if (Schema::connection('tenant')->hasColumn('quotation_prequote_items', 'sctr_porcentaje')) {
                $table->dropColumn('sctr_porcentaje');
            }
        });
    }
}
