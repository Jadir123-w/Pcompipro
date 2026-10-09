<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * En el Proyecto, el plazo por proveedor es el de PRODUCCIÓN, no el de entrega.
 *
 * El punto 20 pedía "agregar el campo Tiempo de Entrega por proveedor" y así se
 * implementó. Al revisarlo, el cliente aclaró que en ese bloque el plazo que
 * corresponde es el del proveedor —cuánto tarda en entregarle el producto a
 * COMPIPRO— y pidió cambiar el rótulo a "Tiempo de Producción".
 *
 * Tiene sentido: la sección se llama "Proveedores y costos del producto". El
 * plazo que se le promete al CLIENTE vive en la cotización (punto 9,
 * `quotation_prequote_items.tiempo_entrega`), no acá.
 *
 * Se renombra la columna y no solo la etiqueta de pantalla porque dejar una
 * `tiempo_entrega` que en realidad guarda el tiempo de producción sería una
 * trampa: en `quotation_prequote_items` existe otra columna con ESE MISMO nombre
 * que significa exactamente lo contrario. Dos columnas homónimas con sentidos
 * opuestos es el tipo de detalle que hace perder una tarde dentro de seis meses.
 *
 * Sin riesgo: la columna se creó el 2026-08-18, solo llegó al ambiente de Pruebas
 * y todavía no está en Producción. La tabla no tiene columnas `enum`, así que el
 * renombrado vía doctrine/dbal no tropieza con la limitación conocida de Laravel.
 *
 * Idempotente en los dos sentidos: si ya está renombrada, no hace nada.
 */
class RenameTiempoEntregaToTiempoProduccionInProyectoProductoProveedores extends Migration
{
    public function up()
    {
        if (!Schema::connection('tenant')->hasTable('proyecto_producto_proveedores')) {
            return;
        }

        // Ya renombrada, o nunca existió la vieja: no hay nada que hacer.
        if (Schema::connection('tenant')->hasColumn('proyecto_producto_proveedores', 'tiempo_produccion')) {
            return;
        }

        if (!Schema::connection('tenant')->hasColumn('proyecto_producto_proveedores', 'tiempo_entrega')) {
            return;
        }

        Schema::connection('tenant')->table('proyecto_producto_proveedores', function (Blueprint $table) {
            $table->renameColumn('tiempo_entrega', 'tiempo_produccion');
        });
    }

    public function down()
    {
        if (!Schema::connection('tenant')->hasTable('proyecto_producto_proveedores')) {
            return;
        }

        if (Schema::connection('tenant')->hasColumn('proyecto_producto_proveedores', 'tiempo_entrega')) {
            return;
        }

        if (!Schema::connection('tenant')->hasColumn('proyecto_producto_proveedores', 'tiempo_produccion')) {
            return;
        }

        Schema::connection('tenant')->table('proyecto_producto_proveedores', function (Blueprint $table) {
            $table->renameColumn('tiempo_produccion', 'tiempo_entrega');
        });
    }
}
