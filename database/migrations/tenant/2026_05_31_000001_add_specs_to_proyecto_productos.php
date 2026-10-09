<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega 7 especificaciones técnicas a `proyecto_productos`, copiadas (snapshot)
 * desde la pre-cotización (quotation_prequote_items) al crear/guardar el proyecto.
 *
 * Estos campos se muestran en SOLO LECTURA en la pestaña Productos del proyecto:
 *  - material            VARCHAR(100)  NULL
 *  - medida              VARCHAR(100)  NULL
 *  - tecnica_impresion   VARCHAR(100)  NULL
 *  - cantidad_colores    INT           NULL
 *  - cantidad_lados      INT           NULL
 *  - empaque             VARCHAR(100)  NULL
 *  - personalizacion     VARCHAR(255)  NULL
 */
class AddSpecsToProyectoProductos extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('proyecto_productos', function (Blueprint $table) {
            if (!Schema::hasColumn('proyecto_productos', 'material')) {
                $table->string('material', 100)->nullable()->after('descripcion');
            }
            if (!Schema::hasColumn('proyecto_productos', 'medida')) {
                $table->string('medida', 100)->nullable()->after('material');
            }
            if (!Schema::hasColumn('proyecto_productos', 'tecnica_impresion')) {
                $table->string('tecnica_impresion', 100)->nullable()->after('medida');
            }
            if (!Schema::hasColumn('proyecto_productos', 'cantidad_colores')) {
                $table->integer('cantidad_colores')->nullable()->after('tecnica_impresion');
            }
            if (!Schema::hasColumn('proyecto_productos', 'cantidad_lados')) {
                $table->integer('cantidad_lados')->nullable()->after('cantidad_colores');
            }
            if (!Schema::hasColumn('proyecto_productos', 'empaque')) {
                $table->string('empaque', 100)->nullable()->after('cantidad_lados');
            }
            if (!Schema::hasColumn('proyecto_productos', 'personalizacion')) {
                $table->string('personalizacion', 255)->nullable()->after('empaque');
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('proyecto_productos', function (Blueprint $table) {
            if (Schema::hasColumn('proyecto_productos', 'personalizacion')) {
                $table->dropColumn('personalizacion');
            }
            if (Schema::hasColumn('proyecto_productos', 'empaque')) {
                $table->dropColumn('empaque');
            }
            if (Schema::hasColumn('proyecto_productos', 'cantidad_lados')) {
                $table->dropColumn('cantidad_lados');
            }
            if (Schema::hasColumn('proyecto_productos', 'cantidad_colores')) {
                $table->dropColumn('cantidad_colores');
            }
            if (Schema::hasColumn('proyecto_productos', 'tecnica_impresion')) {
                $table->dropColumn('tecnica_impresion');
            }
            if (Schema::hasColumn('proyecto_productos', 'medida')) {
                $table->dropColumn('medida');
            }
            if (Schema::hasColumn('proyecto_productos', 'material')) {
                $table->dropColumn('material');
            }
        });
    }
}
