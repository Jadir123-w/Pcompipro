<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * COM-02 — SCTR editable por cotización.
 *
 * Agrega `sctr_porcentaje` (nullable) a quotation_prequotes. Define el % de SCTR
 * de ESA cotización (depende de la condición de pago: 1% / 1.5% / 2% / 2%-2.5%).
 *
 *   NULL  → usar el global del cotizador (quotation_config.sctr_porcentaje).
 *   valor → ese % se aplica a todos los ítems de la cotización.
 *
 * Las prequotes actuales quedan en NULL → dan EXACTAMENTE el mismo total que hoy
 * (retrocompatible). Idempotente.
 */
class AddSctrPorcentajeToQuotationPrequotes extends Migration
{
    public function up()
    {
        if (!Schema::connection('tenant')->hasTable('quotation_prequotes')) {
            return;
        }

        Schema::connection('tenant')->table('quotation_prequotes', function (Blueprint $table) {
            if (!Schema::connection('tenant')->hasColumn('quotation_prequotes', 'sctr_porcentaje')) {
                $table->decimal('sctr_porcentaje', 5, 2)->nullable()->after('total')
                    ->comment('% de SCTR de esta cotización. NULL = usar el global del config (sctr_porcentaje).');
            }
        });
    }

    public function down()
    {
        if (!Schema::connection('tenant')->hasTable('quotation_prequotes')) {
            return;
        }

        Schema::connection('tenant')->table('quotation_prequotes', function (Blueprint $table) {
            if (Schema::connection('tenant')->hasColumn('quotation_prequotes', 'sctr_porcentaje')) {
                $table->dropColumn('sctr_porcentaje');
            }
        });
    }
}
