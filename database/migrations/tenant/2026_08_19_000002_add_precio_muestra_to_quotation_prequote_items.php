<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Precio unitario de la muestra, cuando difiere del costo base.
 *
 * Punto 4 de agosto 2026, texto del cliente:
 *
 *   "Junto a N° de muestras, agregar un checkbox con la ayuda 'Marca si el precio
 *    de la muestra es diferente al costo base'. Al marcarlo se habilita el campo
 *    Precio x Muestra, y el Costo Muestra pasa a calcularse como N° de muestras ×
 *    Precio x Muestra."
 *
 * Hasta ahora el costo de la muestra se derivaba siempre como
 * `numero_muestras × costo_base`, dando por hecho que una muestra cuesta lo mismo
 * que una unidad del pedido. En la práctica no es así: una muestra suelta suele
 * salir más cara que una unidad de una tirada de 500.
 *
 * UNA sola columna, y no un booleano más un precio. El checkbox que pide el
 * cliente es la PRESENCIA del valor:
 *
 *   NULL  → el precio de la muestra es el costo base (comportamiento histórico)
 *   0.00  → las muestras son gratis
 *   > 0   → ese es el precio unitario de la muestra
 *
 * Con un flag aparte, "marcado con precio 0" y "sin marcar" quedarían como dos
 * estados distintos que producen números distintos y son imposibles de
 * distinguir mirando la pantalla. Así no hay estado ambiguo posible.
 *
 * Nullable a propósito: todos los ítems existentes quedan en NULL y siguen
 * calculando exactamente igual que antes. Ninguna cotización ya emitida cambia.
 *
 * Idempotente: solo agrega la columna si falta.
 */
class AddPrecioMuestraToQuotationPrequoteItems extends Migration
{
    public function up()
    {
        if (!Schema::connection('tenant')->hasTable('quotation_prequote_items')) {
            return;
        }

        if (Schema::connection('tenant')->hasColumn('quotation_prequote_items', 'precio_muestra')) {
            return;
        }

        Schema::connection('tenant')->table('quotation_prequote_items', function (Blueprint $table) {
            $table->decimal('precio_muestra', 12, 2)
                  ->nullable()
                  ->after('numero_muestras')
                  ->comment('Precio unitario de la muestra. NULL = usa el costo base');
        });
    }

    public function down()
    {
        if (!Schema::connection('tenant')->hasTable('quotation_prequote_items')) {
            return;
        }

        if (Schema::connection('tenant')->hasColumn('quotation_prequote_items', 'precio_muestra')) {
            Schema::connection('tenant')->table('quotation_prequote_items', function (Blueprint $table) {
                $table->dropColumn('precio_muestra');
            });
        }
    }
}
