<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProyectoProductosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * Crea la tabla proyecto_productos para manejar los productos/items 
     * asociados al proyecto (relación N:M).
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('proyecto_productos')) {
            Schema::create('proyecto_productos', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedInteger('proyecto_id')->comment('FK proyecto');
                $table->unsignedBigInteger('item_id')->nullable()->comment('FK items (si existe)');
                $table->string('descripcion', 500)->comment('Descripción del producto');
                $table->decimal('cantidad', 12, 2)->default(1);
                $table->decimal('precio_unitario', 12, 2)->nullable();
                $table->decimal('subtotal', 12, 2)->nullable();
                $table->text('observaciones')->nullable();
                $table->timestamps();

                // Índices
                $table->index('proyecto_id', 'idx_proyecto_productos_proyecto');
                $table->index('item_id', 'idx_proyecto_productos_item');

                // Foreign key a proyecto
                $table->foreign('proyecto_id', 'fk_proyecto_productos_proyecto')
                      ->references('id')->on('proyecto')
                      ->onDelete('cascade');
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('proyecto_productos');
    }
}
