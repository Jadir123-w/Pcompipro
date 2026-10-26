<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Comparativa de proveedores por ítem de pre-cotización (quotation_items_supplier).
 *
 * Cada fila es la cotización de un proveedor para un ítem; la marcada como seleccionada
 * define el costo base del ítem. El precio de venta no se guarda: se calcula con
 * costo_base x (1 + margen / 100).
 *
 * Idempotente: en algunas empresas la tabla ya se creó con el script manual.
 */
class CreateQuotationItemsSupplierTable extends Migration
{
    public function up()
    {
        if (Schema::hasTable('quotation_items_supplier')) {
            return;
        }

        Schema::create('quotation_items_supplier', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('quotation_items_id')
                ->comment('FK a quotation_prequote_items.id (cascade delete)');
            $table->unsignedBigInteger('supplier_id')->nullable()
                ->comment('persons.id del proveedor (type=suppliers). NULL = por definir');
            $table->string('supplier_name', 191)->default('Proveedor por definir')
                ->comment('Nombre desnormalizado del proveedor para mostrar en pantalla');
            $table->string('origen', 20)->default('manual');
            $table->string('product_reference', 255)->nullable()
                ->comment('Referencia cotizada por el proveedor (texto libre)');
            $table->decimal('costo_base', 12, 2)->default(0)
                ->comment('Costo base cotizado por este proveedor');
            $table->string('tiempo_estimado_dias', 150)->nullable()
                ->comment('Tiempo de entrega, texto libre (ej. 15 días útiles)');
            $table->text('observaciones')->nullable();
            $table->boolean('seleccionado')->default(false)
                ->comment('1 = proveedor elegido (define el costo base del ítem); 0 = respaldo');
            $table->unsignedBigInteger('user_id')->nullable()
                ->comment('Usuario que creó el registro');
            $table->timestamps();

            $table->index('quotation_items_id', 'idx_qis_item');
            $table->index('supplier_id', 'idx_qis_supplier');
            $table->foreign('quotation_items_id', 'fk_qis_item')
                ->references('id')->on('quotation_prequote_items')
                ->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('quotation_items_supplier');
    }
}
