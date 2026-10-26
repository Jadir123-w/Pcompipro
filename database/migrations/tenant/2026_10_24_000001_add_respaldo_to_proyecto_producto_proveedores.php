<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Proveedores de respaldo del producto del proyecto: los demás proveedores que se
 * cotizaron en la comparativa de la pre-cotización. No suman al costo (activo = 0)
 * y no son historial: se pueden usar en lugar del proveedor que respaldan.
 *
 *   es_respaldo    1 = respaldo (aún no se usa).
 *   respalda_a_id  fila del proveedor al que reemplazaría (el ganador de la comparativa).
 *   cotizado_el    fecha en que el proveedor cotizó (referencia del precio).
 *
 * Idempotente.
 */
class AddRespaldoToProyectoProductoProveedores extends Migration
{
    public function up()
    {
        Schema::table('proyecto_producto_proveedores', function (Blueprint $table) {
            if (!Schema::hasColumn('proyecto_producto_proveedores', 'es_respaldo')) {
                $table->boolean('es_respaldo')->default(0)->after('activo')
                    ->comment('1 = proveedor de respaldo de la comparativa (no suma al costo)');
            }
            if (!Schema::hasColumn('proyecto_producto_proveedores', 'respalda_a_id')) {
                $table->unsignedBigInteger('respalda_a_id')->nullable()->after('es_respaldo')
                    ->comment('Proveedor del producto al que reemplazaría este respaldo');
            }
            if (!Schema::hasColumn('proyecto_producto_proveedores', 'cotizado_el')) {
                $table->date('cotizado_el')->nullable()->after('respalda_a_id')
                    ->comment('Fecha en que el proveedor cotizó (comparativa)');
            }
        });
    }

    public function down()
    {
        Schema::table('proyecto_producto_proveedores', function (Blueprint $table) {
            foreach (['cotizado_el', 'respalda_a_id', 'es_respaldo'] as $columna) {
                if (Schema::hasColumn('proyecto_producto_proveedores', $columna)) {
                    $table->dropColumn($columna);
                }
            }
        });
    }
}
