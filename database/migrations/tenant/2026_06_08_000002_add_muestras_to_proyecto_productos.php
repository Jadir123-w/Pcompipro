<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega la columna `muestras` a `proyecto_productos`.
 *
 * Cantidad de muestras del producto (entero). Por defecto 0, ya que hay
 * productos que no llevan muestras. Es un dato propio del proyecto y se
 * edita manualmente (no se hereda de la pre-cotizacion).
 *
 * - muestras  INT  NOT NULL  DEFAULT 0
 */
class AddMuestrasToProyectoProductos extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('proyecto_productos', function (Blueprint $table) {
            if (!Schema::hasColumn('proyecto_productos', 'muestras')) {
                $table->integer('muestras')->default(0)->after('cantidad');
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
            if (Schema::hasColumn('proyecto_productos', 'muestras')) {
                $table->dropColumn('muestras');
            }
        });
    }
}
