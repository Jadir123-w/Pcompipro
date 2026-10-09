<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mostrar u ocultar el bloque de totales en el PDF de la propuesta.
 *
 * Punto 13 de agosto 2026, texto del cliente:
 *
 *   "En Cotización Final, permitir mostrar u ocultar el subtotal y el total
 *    consolidado, para que el PDF pueda generarse sin ellos."
 *
 * Sirve para las cotizaciones por escala: cuando el mismo producto se presenta a
 * 50, 100 y 300 unidades, un total consolidado que suma las tres alternativas no
 * significa nada y confunde al cliente, porque va a comprar una sola.
 *
 * Sigue la familia `incluye_*` que ya existe en esta tabla
 * (`incluye_datos_empresa`, `incluye_datos_bancarios`, `incluye_sostenibilidad`):
 * mismo tipo, mismo criterio, mismo lugar en la pantalla.
 *
 * Default TRUE: todas las propuestas existentes y las nuevas siguen mostrando los
 * totales como hasta ahora. Ocultarlos es la excepción y hay que pedirla.
 *
 * Idempotente: solo agrega la columna si falta.
 */
class AddIncluyeTotalesToQuotationProposals extends Migration
{
    public function up()
    {
        if (!Schema::connection('tenant')->hasTable('quotation_proposals')) {
            return;
        }

        if (Schema::connection('tenant')->hasColumn('quotation_proposals', 'incluye_totales')) {
            return;
        }

        Schema::connection('tenant')->table('quotation_proposals', function (Blueprint $table) {
            $table->boolean('incluye_totales')
                  ->default(true)
                  ->after('incluye_sostenibilidad')
                  ->comment('Mostrar el bloque de subtotal y total en el PDF');
        });
    }

    public function down()
    {
        if (!Schema::connection('tenant')->hasTable('quotation_proposals')) {
            return;
        }

        if (Schema::connection('tenant')->hasColumn('quotation_proposals', 'incluye_totales')) {
            Schema::connection('tenant')->table('quotation_proposals', function (Blueprint $table) {
                $table->dropColumn('incluye_totales');
            });
        }
    }
}
