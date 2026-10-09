<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * COM-02 — Tratamiento de IGV en la Cotización Final.
 *
 * Agrega `aplica_igv` a quotation_proposals: define si la cotización lleva
 * IGV (sí/no) y AFECTA el monto mostrado del total:
 *
 *   aplica_igv = true  → Total = Subtotal + IGV (18%)   [TOTAL FACTURADO]
 *   aplica_igv = false → Total = Subtotal               [TOTAL (Sin IGV)]
 *
 * Es un campo de PRESENTACIÓN: el motor de cálculo sigue computando subtotal,
 * IGV y total completos; `aplica_igv` solo decide qué se muestra en la sección
 * de totales (frontend + PDF).
 *
 * Default true (= con IGV) para NO alterar las cotizaciones existentes, que
 * hoy ya muestran el IGV.
 *
 * Nota: el campo previo `precios_incluyen_igv` queda obsoleto para este fin
 * (su nombre significaba lo opuesto y confundía). Se mantiene la columna por
 * compatibilidad, pero deja de gobernar el PDF.
 *
 * Idempotente.
 */
class AddAplicaIgvToQuotationProposals extends Migration
{
    public function up()
    {
        if (!Schema::connection('tenant')->hasTable('quotation_proposals')) {
            return;
        }

        Schema::connection('tenant')->table('quotation_proposals', function (Blueprint $table) {
            if (!Schema::connection('tenant')->hasColumn('quotation_proposals', 'aplica_igv')) {
                $table->boolean('aplica_igv')->default(true)->after('precios_incluyen_igv')
                    ->comment('Si true, la cotización lleva IGV (Total = Subtotal + IGV). Si false, Total = Subtotal. Default true.');
            }
        });
    }

    public function down()
    {
        if (!Schema::connection('tenant')->hasTable('quotation_proposals')) {
            return;
        }

        Schema::connection('tenant')->table('quotation_proposals', function (Blueprint $table) {
            if (Schema::connection('tenant')->hasColumn('quotation_proposals', 'aplica_igv')) {
                $table->dropColumn('aplica_igv');
            }
        });
    }
}
