<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * M2 — Gestión de proveedores por producto en el proyecto + M4 (costo total).
 *
 * 1) Crea `proyecto_producto_proveedores`: N proveedores por cada producto del
 *    proyecto, con su concepto/servicio, cantidad, precio informado, condición
 *    de IGV y los montos derivados. Soporta historial: al reemplazar un
 *    proveedor, el anterior se conserva con `activo = 0` (sigue visible).
 * 2) Agrega a `proyecto_productos` los costos operativos por producto
 *    (empaque, movilidad, SCTR) para consolidarlos en el resumen del proyecto.
 * 3) Agrega a `proyecto` el `costo_total_proyecto` (M4): costo real del
 *    proyecto tomado de la pestaña Productos.
 */
class CreateProyectoProductoProveedoresAndCosts extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // 1) Proveedores por producto -----------------------------------------
        if (!Schema::hasTable('proyecto_producto_proveedores')) {
            Schema::create('proyecto_producto_proveedores', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('proyecto_producto_id')->comment('FK proyecto_productos');
                $table->unsignedBigInteger('supplier_id')->nullable()->comment('Referencia lógica a persons.id (type=suppliers)');
                $table->string('supplier_name', 255)->nullable()->comment('Nombre del proveedor (snapshot)');
                $table->string('concepto', 500)->nullable()->comment('Concepto/servicio que realiza el proveedor');
                $table->decimal('cantidad', 12, 2)->default(1);
                $table->decimal('precio_unitario_informado', 12, 2)->default(0)->comment('Precio comunicado por el proveedor');
                $table->string('condicion_igv', 20)->default('pending')->comment('included|excluded|exempt|pending');
                $table->decimal('precio_unitario_neto', 12, 2)->default(0);
                $table->decimal('subtotal_neto', 12, 2)->default(0);
                $table->decimal('igv_monto', 12, 2)->default(0);
                $table->decimal('total_pagar', 12, 2)->default(0);
                $table->boolean('activo')->default(true)->comment('1=activo, 0=reemplazado (historial)');
                $table->unsignedBigInteger('reemplaza_a_id')->nullable()->comment('Proveedor al que reemplaza (historial)');
                $table->string('motivo_reemplazo', 255)->nullable();
                $table->integer('orden')->default(0);
                $table->timestamps();

                $table->index('proyecto_producto_id', 'idx_ppp_producto');
                $table->index('supplier_id', 'idx_ppp_supplier');

                $table->foreign('proyecto_producto_id', 'fk_ppp_producto')
                      ->references('id')->on('proyecto_productos')
                      ->onDelete('cascade');
            });
        }

        // 2) Costos operativos por producto -----------------------------------
        Schema::table('proyecto_productos', function (Blueprint $table) {
            if (!Schema::hasColumn('proyecto_productos', 'costo_empaque')) {
                $table->decimal('costo_empaque', 12, 2)->default(0)->after('personalizacion');
            }
            if (!Schema::hasColumn('proyecto_productos', 'costo_movilidad')) {
                $table->decimal('costo_movilidad', 12, 2)->default(0)->after('costo_empaque');
            }
            if (!Schema::hasColumn('proyecto_productos', 'costo_sctr')) {
                $table->decimal('costo_sctr', 12, 2)->default(0)->after('costo_movilidad');
            }
        });

        // 3) Costo total del proyecto (M4) ------------------------------------
        Schema::table('proyecto', function (Blueprint $table) {
            if (!Schema::hasColumn('proyecto', 'costo_total_proyecto')) {
                $table->decimal('costo_total_proyecto', 12, 2)->nullable()->after('monto_costo');
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
        Schema::dropIfExists('proyecto_producto_proveedores');

        Schema::table('proyecto_productos', function (Blueprint $table) {
            foreach (['costo_sctr', 'costo_movilidad', 'costo_empaque'] as $col) {
                if (Schema::hasColumn('proyecto_productos', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::table('proyecto', function (Blueprint $table) {
            if (Schema::hasColumn('proyecto', 'costo_total_proyecto')) {
                $table->dropColumn('costo_total_proyecto');
            }
        });
    }
}
