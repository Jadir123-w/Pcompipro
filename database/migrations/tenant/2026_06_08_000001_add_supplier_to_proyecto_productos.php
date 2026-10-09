<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega las columnas de proveedor a `proyecto_productos`.
 *
 * Permiten heredar (snapshot) el proveedor asignado a cada producto en la
 * pre-cotización cuando un requerimiento se convierte en proyecto, para la
 * gestión de compras posterior.
 *
 * - supplier_id    BIGINT UNSIGNED  NULL  (referencia lógica a persons.id, type=suppliers)
 * - supplier_name  VARCHAR(255)     NULL  (nombre del proveedor, snapshot)
 */
class AddSupplierToProyectoProductos extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('proyecto_productos', function (Blueprint $table) {
            if (!Schema::hasColumn('proyecto_productos', 'supplier_id')) {
                $table->unsignedBigInteger('supplier_id')->nullable()->after('item_id');
            }
            if (!Schema::hasColumn('proyecto_productos', 'supplier_name')) {
                $table->string('supplier_name', 255)->nullable()->after('supplier_id');
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
            if (Schema::hasColumn('proyecto_productos', 'supplier_name')) {
                $table->dropColumn('supplier_name');
            }
            if (Schema::hasColumn('proyecto_productos', 'supplier_id')) {
                $table->dropColumn('supplier_id');
            }
        });
    }
}
