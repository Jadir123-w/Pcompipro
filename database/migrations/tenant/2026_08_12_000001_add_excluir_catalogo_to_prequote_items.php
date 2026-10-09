<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marca por ítem de pre-cotización que NO debe registrarse como producto
 * propio en el catálogo de Facturador.
 *
 * Pedido del cliente (punto 2 de agosto 2026): los kits y el servicio de
 * armado no son productos como tales. Un kit son varios productos agrupados
 * solo para esa cotización, y el armado es un servicio puntual. Registrarlos
 * en `items` ensucia el catálogo con entradas que nadie va a volver a usar.
 *
 * NO se puede simplemente omitir el registro: `quotation_items.item_id` es
 * NOT NULL y tiene llave foránea contra `items`, así que toda línea de
 * cotización necesita un producto de catálogo detrás. La salida es apuntar
 * estos ítems a un único producto genérico compartido, en lugar de crear uno
 * por cada kit. Lo que ve el cliente no se ve afectado: la línea de la
 * cotización guarda su propia copia del nombre (`quotation_items.item` en
 * JSON y `name_product_pdf`), que se arma desde el nombre de la
 * pre-cotización y no desde el catálogo.
 *
 * Se resuelve con una marca explícita y no adivinando por el nombre: si se
 * dedujera de que el texto contenga "KIT" o "SERVICIO DE ARMADO", fallaría con
 * "Set de ..." o "Armado de ..." y dependería de cómo lo escriba cada
 * ejecutiva. La ejecutiva es quien sabe si eso es un producto real.
 *
 * Idempotente: solo agrega la columna si falta.
 */
class AddExcluirCatalogoToPrequoteItems extends Migration
{
    public function up()
    {
        if (!Schema::connection('tenant')->hasTable('quotation_prequote_items')) {
            return;
        }

        if (Schema::connection('tenant')->hasColumn('quotation_prequote_items', 'excluir_catalogo')) {
            return;
        }

        Schema::connection('tenant')->table('quotation_prequote_items', function (Blueprint $table) {
            $table->boolean('excluir_catalogo')
                  ->default(false)
                  ->after('item_id')
                  ->comment('1 = kit o servicio: no se crea producto propio en el catalogo');
        });
    }

    public function down()
    {
        if (!Schema::connection('tenant')->hasTable('quotation_prequote_items')) {
            return;
        }

        if (Schema::connection('tenant')->hasColumn('quotation_prequote_items', 'excluir_catalogo')) {
            Schema::connection('tenant')->table('quotation_prequote_items', function (Blueprint $table) {
                $table->dropColumn('excluir_catalogo');
            });
        }
    }
}
