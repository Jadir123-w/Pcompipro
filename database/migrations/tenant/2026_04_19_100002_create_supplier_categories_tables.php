<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tablas maestras de categorización de proveedores.
 *
 * - supplier_categories: categoría principal (ej: IMPRENTA, TEXTIL, CERAMICA)
 * - supplier_subcategories: subcategoría que pertenece a una categoría
 *   (ej: TAZAS, CHOP, VASOS dentro de CERAMICA)
 *
 * Una categoría puede tener N subcategorías (1:N).
 * En `persons` se guarda la FK a ambas.
 */
class CreateSupplierCategoriesTables extends Migration
{
    public function up()
    {
        Schema::create('supplier_categories', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('nombre', 120)->unique();
            $table->string('descripcion', 255)->nullable();
            $table->integer('orden')->default(0);
            $table->tinyInteger('estado')->default(1); // 1=activo, 0=inactivo
            $table->timestamps();
            $table->softDeletes();

            $table->index('estado');
        });

        Schema::create('supplier_subcategories', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('supplier_category_id');
            $table->string('nombre', 120);
            $table->string('descripcion', 255)->nullable();
            $table->integer('orden')->default(0);
            $table->tinyInteger('estado')->default(1);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('supplier_category_id')
                ->references('id')->on('supplier_categories')
                ->onDelete('cascade');

            $table->unique(['supplier_category_id', 'nombre'], 'unq_subcategoria_nombre');
            $table->index('estado');
        });
    }

    public function down()
    {
        Schema::dropIfExists('supplier_subcategories');
        Schema::dropIfExists('supplier_categories');
    }
}
