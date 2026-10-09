<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Plazo ofrecido al cliente, separado del plazo del proveedor.
 *
 * Punto 9 de agosto 2026, texto del cliente:
 *
 *   "En la pestaña Proveedores, cambiar 'Tiempo de entrega' por 'Tiempo de
 *    Producción'. El plazo ofrecido al cliente NO ES EL MISMO que necesita cada
 *    proveedor, y cada producto puede tener un plazo distinto."
 *
 * La primera entrega del punto cambió solo el rótulo, y eso dejaba el sistema
 * contradiciendo la propia premisa: la pestaña Proveedores y la pestaña Preview
 * escribían las dos en `tiempo_estimado_dias`, así que al editar una se movía la
 * otra y era imposible tener 19 días de producción y 20 a 22 de entrega al mismo
 * tiempo. Dos rótulos distintos sobre un mismo dato confunden más que uno malo.
 *
 * Con esta columna quedan separados:
 *
 *   · `tiempo_estimado_dias` → Tiempo de PRODUCCIÓN. Lo que tarda el proveedor.
 *     Se ve en la pestaña Proveedores y lo propone la IA al sugerir proveedores.
 *
 *   · `tiempo_entrega` (esta) → Plazo OFRECIDO AL CLIENTE. Se ve en el Preview y
 *     es el que sale en el PDF de la propuesta. Suele ser mayor, porque suma
 *     logística, control de calidad y despacho.
 *
 * Nullable a propósito: vacío significa "todavía no se definió uno propio" y la
 * pantalla muestra el de producción como valor heredado. Así ninguna cotización
 * existente cambia de comportamiento — siguen mostrando lo mismo que hoy — y el
 * plazo propio aparece recién cuando alguien lo escribe.
 *
 * VARCHAR(150) para igualar a `tiempo_estimado_dias`, de donde hereda: con menos,
 * un valor largo se truncaría al copiarse. Es texto libre, no un número de días:
 * se usa tanto "20 a 22 días útiles" como fechas del tipo "Mié 02 Abril".
 *
 * Idempotente: solo agrega la columna si falta.
 */
class AddTiempoEntregaToQuotationPrequoteItems extends Migration
{
    public function up()
    {
        if (!Schema::connection('tenant')->hasTable('quotation_prequote_items')) {
            return;
        }

        if (Schema::connection('tenant')->hasColumn('quotation_prequote_items', 'tiempo_entrega')) {
            return;
        }

        Schema::connection('tenant')->table('quotation_prequote_items', function (Blueprint $table) {
            $table->string('tiempo_entrega', 150)
                  ->nullable()
                  ->after('tiempo_estimado_dias')
                  ->comment('Plazo ofrecido al cliente (Preview y PDF). Vacio = hereda el tiempo de produccion');
        });
    }

    public function down()
    {
        if (!Schema::connection('tenant')->hasTable('quotation_prequote_items')) {
            return;
        }

        if (Schema::connection('tenant')->hasColumn('quotation_prequote_items', 'tiempo_entrega')) {
            Schema::connection('tenant')->table('quotation_prequote_items', function (Blueprint $table) {
                $table->dropColumn('tiempo_entrega');
            });
        }
    }
}
