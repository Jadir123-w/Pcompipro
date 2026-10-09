<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * COM-02 Fase B — Margen mínimo configurable + alerta visual.
 *
 * Agrega la columna `min_margin_percentage` a `quotation_margin_rules`. Es
 * nullable: si una regla no define mínimo, no se dispara alerta. Si lo define
 * y el margen ingresado por la ejecutiva está por debajo, el frontend muestra
 * una alerta visual (input rojo + tooltip) pero NO bloquea el guardado, para
 * mantener flexibilidad operativa.
 *
 * Ejemplo de uso:
 *   category=Textil, margin_percentage=80 (sugerido), min_margin_percentage=50
 *   → la IA propone 80% al cotizar
 *   → si la ejecutiva baja a 40%, ve la alerta
 *   → si baja a 60%, no ve nada (está dentro del rango aceptable)
 *
 * Idempotente.
 */
class AddMinMarginToQuotationMarginRules extends Migration
{
    public function up()
    {
        if (!Schema::connection('tenant')->hasTable('quotation_margin_rules')) {
            return;
        }

        Schema::connection('tenant')->table('quotation_margin_rules', function (Blueprint $table) {
            if (!Schema::connection('tenant')->hasColumn('quotation_margin_rules', 'min_margin_percentage')) {
                $table->decimal('min_margin_percentage', 5, 2)->nullable()->after('margin_percentage')
                    ->comment('Margen mínimo aceptable (alerta visual si el margen ingresado es menor). Null = sin alerta.');
            }
        });
    }

    public function down()
    {
        if (!Schema::connection('tenant')->hasTable('quotation_margin_rules')) {
            return;
        }

        Schema::connection('tenant')->table('quotation_margin_rules', function (Blueprint $table) {
            if (Schema::connection('tenant')->hasColumn('quotation_margin_rules', 'min_margin_percentage')) {
                $table->dropColumn('min_margin_percentage');
            }
        });
    }
}
