<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * COM08 — Tiempo de entrega del ítem: de número entero a texto libre.
 *
 * En las cotizaciones reales de Compipro el tiempo de entrega NO es un número
 * de días: es texto (una fecha objetivo, una frase, o un número con nota al
 * pie). Ejemplos reales: "Mié 02 Abril *", "1 día útil", "15 días útiles".
 *
 * Por eso `quotation_prequote_items.tiempo_estimado_dias` pasa de INT a
 * VARCHAR(150), para que la ejecutiva escriba exactamente lo que va en la
 * propuesta al cliente.
 *
 * Se mantiene el NOMBRE de la columna (`tiempo_estimado_dias`) aunque ahora
 * contenga texto: hay muchas referencias en motor, PDF, API y frontend, y
 * renombrar multiplicaría el cambio sin beneficio.
 *
 * Los datos existentes (enteros) quedan como su representación en texto
 * ("15"), lo cual es válido.
 *
 * Idempotente (MODIFY es seguro de re-ejecutar).
 */
class ChangeTiempoEstimadoDiasToStringInQuotationPrequoteItems extends Migration
{
    public function up()
    {
        if (!Schema::connection('tenant')->hasTable('quotation_prequote_items')) {
            return;
        }
        if (!Schema::connection('tenant')->hasColumn('quotation_prequote_items', 'tiempo_estimado_dias')) {
            return;
        }

        DB::connection('tenant')->statement(
            "ALTER TABLE quotation_prequote_items MODIFY tiempo_estimado_dias VARCHAR(150) NULL"
        );
    }

    public function down()
    {
        if (!Schema::connection('tenant')->hasTable('quotation_prequote_items')) {
            return;
        }
        if (!Schema::connection('tenant')->hasColumn('quotation_prequote_items', 'tiempo_estimado_dias')) {
            return;
        }

        // OJO: al revertir, los valores de texto no numéricos se truncan a 0.
        DB::connection('tenant')->statement(
            "ALTER TABLE quotation_prequote_items MODIFY tiempo_estimado_dias INT NULL"
        );
    }
}
