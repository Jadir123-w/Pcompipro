<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tiempo de Entrega por proveedor, en el Proyecto.
 *
 * Pedido del cliente (punto 20 de agosto 2026). Conviven DOS plazos distintos que
 * hasta ahora se llamaban casi igual, y ese era el problema:
 *
 *   · Tiempo de PRODUCCIÓN  (`quotation_prequote_items.tiempo_estimado_dias`)
 *     Lo que tarda el proveedor en fabricar. Se define en la Pre-Cotización y en
 *     agosto 2026 se le cambió el rótulo justamente para separarlo (punto 9).
 *
 *   · Tiempo de ENTREGA     (esta columna)
 *     Lo que se le promete al cliente. Arranca heredando el de producción, pero
 *     el usuario lo ajusta: normalmente es mayor, porque suma logística,
 *     control de calidad y despacho.
 *
 * Se guarda por FILA DE PROVEEDOR y no a nivel de producto ni de proyecto: un
 * producto puede tener varios proveedores y cada uno responde con su propio
 * plazo. Además, al reemplazar un proveedor la fila anterior queda como
 * historial (`activo = 0`), así que el plazo con el que se trabajó en su momento
 * se conserva junto a ella.
 *
 * VARCHAR(150) para igualar exactamente a `tiempo_estimado_dias`, que es de
 * donde hereda: con menos, un valor largo se truncaría al copiarse. Es texto
 * libre, no un número de días: el cliente usa tanto "15 días útiles" como
 * fechas concretas del tipo "Mié 02 Abril".
 *
 * Idempotente: solo agrega la columna si falta.
 */
class AddTiempoEntregaToProyectoProductoProveedores extends Migration
{
    public function up()
    {
        if (!Schema::connection('tenant')->hasTable('proyecto_producto_proveedores')) {
            return;
        }

        if (Schema::connection('tenant')->hasColumn('proyecto_producto_proveedores', 'tiempo_entrega')) {
            return;
        }

        Schema::connection('tenant')->table('proyecto_producto_proveedores', function (Blueprint $table) {
            $table->string('tiempo_entrega', 150)
                  ->nullable()
                  ->after('concepto')
                  ->comment('Plazo prometido al cliente. Hereda el tiempo de produccion de la pre-cotizacion y es editable');
        });
    }

    public function down()
    {
        if (!Schema::connection('tenant')->hasTable('proyecto_producto_proveedores')) {
            return;
        }

        if (Schema::connection('tenant')->hasColumn('proyecto_producto_proveedores', 'tiempo_entrega')) {
            Schema::connection('tenant')->table('proyecto_producto_proveedores', function (Blueprint $table) {
                $table->dropColumn('tiempo_entrega');
            });
        }
    }
}
