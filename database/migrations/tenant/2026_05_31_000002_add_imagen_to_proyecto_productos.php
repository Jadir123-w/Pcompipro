<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega la columna `imagen` a `proyecto_productos`.
 *
 * Guarda la ruta (relativa al disco `local`) de la imagen de referencia de
 * cada producto del proyecto. Puede contener:
 *  - una ruta propia subida desde el proyecto: project-product-images/{tenant}/{id}/{uuid}.ext
 *  - una ruta heredada (snapshot) de la pre-cotización: quotation-images/{tenant}/...
 *
 * - imagen  VARCHAR(500)  NULL
 */
class AddImagenToProyectoProductos extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('proyecto_productos', function (Blueprint $table) {
            if (!Schema::hasColumn('proyecto_productos', 'imagen')) {
                $table->string('imagen', 500)->nullable()->after('personalizacion');
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
            if (Schema::hasColumn('proyecto_productos', 'imagen')) {
                $table->dropColumn('imagen');
            }
        });
    }
}
