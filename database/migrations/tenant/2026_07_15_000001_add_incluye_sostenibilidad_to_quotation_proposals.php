<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * COM-02 — Toggle por cotización del bloque de sostenibilidad.
 *
 * Agrega `incluye_sostenibilidad` a quotation_proposals: define si el PDF muestra
 * el bloque verde de sostenibilidad. Default true = no altera las cotizaciones
 * existentes (que hoy lo muestran siempre). La ejecutiva puede apagarlo por
 * cotización (no es global). Idempotente.
 */
class AddIncluyeSostenibilidadToQuotationProposals extends Migration
{
    public function up()
    {
        if (!Schema::connection('tenant')->hasTable('quotation_proposals')) {
            return;
        }

        Schema::connection('tenant')->table('quotation_proposals', function (Blueprint $table) {
            if (!Schema::connection('tenant')->hasColumn('quotation_proposals', 'incluye_sostenibilidad')) {
                $table->boolean('incluye_sostenibilidad')->default(true)->after('incluye_datos_bancarios')
                    ->comment('Si true, el PDF muestra el bloque de sostenibilidad. Default true (retro-compatible).');
            }
        });
    }

    public function down()
    {
        if (!Schema::connection('tenant')->hasTable('quotation_proposals')) {
            return;
        }

        Schema::connection('tenant')->table('quotation_proposals', function (Blueprint $table) {
            if (Schema::connection('tenant')->hasColumn('quotation_proposals', 'incluye_sostenibilidad')) {
                $table->dropColumn('incluye_sostenibilidad');
            }
        });
    }
}
