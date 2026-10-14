<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

/**
 * Comparativa de proveedores por ítem de pre-cotización (COM-02).
 *
 * Replica el mockup: cada ítem puede tener N proveedores cotizados y el
 * ganador define el Costo Base de la línea. Origen manual|catálogo lo
 * asigna el sistema (catálogo cuando la referencia viene de lista de
 * precios, manual si fue cotizado fuera del catálogo).
 */
class CreateQuotationPrequoteItemSuppliersTable extends Migration
{
    public function up()
    {
        Schema::create('quotation_prequote_item_suppliers', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedBigInteger('prequote_item_id')
                ->comment('FK a quotation_prequote_items');

            $table->unsignedBigInteger('supplier_id')->nullable()
                ->comment('persons.id del proveedor (NULL = por definir)');

            $table->string('supplier_name', 191)->default('Proveedor por definir');

            $table->string('source', 20)->default('manual')
                ->comment('manual|catálogo: lo asigna el sistema según origen de la referencia');

            $table->string('product_reference', 255)->nullable()
                ->comment('Producto / referencia cotizada por este proveedor');

            $table->decimal('costo_base', 12, 2)->default(0)
                ->comment('Costo base cotizado por este proveedor');

            $table->string('tiempo_entrega', 150)->nullable()
                ->comment('Ej: 15 días útiles');

            $table->text('observacion')->nullable();

            $table->boolean('es_ganador')->default(false)
                ->comment('El ganador define supplier_id/name + costo_base del ítem');

            $table->integer('orden')->default(0);

            $table->unsignedBigInteger('user_id')->nullable();

            $table->timestamps();

            $table->foreign('prequote_item_id')
                ->references('id')->on('quotation_prequote_items')
                ->onDelete('cascade');

            $table->index(['prequote_item_id', 'orden']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('quotation_prequote_item_suppliers');
    }
}
