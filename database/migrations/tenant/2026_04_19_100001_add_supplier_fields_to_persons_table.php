<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega a la tabla `persons` los campos necesarios para el módulo de Proveedores
 * integrando la propuesta del cliente + datos del Excel organizativo.
 *
 * Decisiones de diseño:
 * - Campos filtrables/reportables como columnas (rubro, tipo, calificacion,
 *   categoria_id/subcategoria_id, condicion_pago, origen).
 * - Campos descriptivos libres como JSON (perfil_adicional, redes_sociales, catalogos).
 * - Número de cuenta bancaria como TEXT (texto multilínea libre).
 * - Categorías: FK a tablas maestras (supplier_categories/supplier_subcategories)
 *   creadas en la migración siguiente.
 */
class AddSupplierFieldsToPersonsTable extends Migration
{
    public function up()
    {
        Schema::table('persons', function (Blueprint $table) {
            // Filtros y datos principales
            $table->string('rubro_sector', 225)->nullable()->after('trade_name');
            $table->string('tipo_provee', 60)->nullable()->after('rubro_sector');
            $table->string('calificacion', 60)->nullable()->after('tipo_provee');

            // Categorización (FK agregado luego en la misma Up, tras crear las tablas maestras)
            $table->unsignedBigInteger('supplier_category_id')->nullable()->after('calificacion');
            $table->unsignedBigInteger('supplier_subcategory_id')->nullable()->after('supplier_category_id');

            // Política de pago y datos comerciales
            $table->string('condicion_pago', 120)->nullable()->after('supplier_subcategory_id');
            $table->string('descuentos', 255)->nullable()->after('condicion_pago');
            $table->text('numero_cuenta')->nullable()->after('descuentos');

            // Otros
            $table->date('aniversario')->nullable()->after('numero_cuenta');
            $table->string('origen', 100)->nullable()->after('aniversario');
            $table->string('referencia', 255)->nullable()->after('origen');

            // Datos descriptivos (JSON)
            $table->json('perfil_adicional')->nullable()->after('referencia');
            $table->json('redes_sociales')->nullable()->after('perfil_adicional');
            $table->json('catalogos')->nullable()->after('redes_sociales');

            $table->index('supplier_category_id');
            $table->index('supplier_subcategory_id');
            $table->index('tipo_provee');
        });
    }

    public function down()
    {
        Schema::table('persons', function (Blueprint $table) {
            $table->dropIndex(['supplier_category_id']);
            $table->dropIndex(['supplier_subcategory_id']);
            $table->dropIndex(['tipo_provee']);

            $table->dropColumn([
                'rubro_sector',
                'tipo_provee',
                'calificacion',
                'supplier_category_id',
                'supplier_subcategory_id',
                'condicion_pago',
                'descuentos',
                'numero_cuenta',
                'aniversario',
                'origen',
                'referencia',
                'perfil_adicional',
                'redes_sociales',
                'catalogos',
            ]);
        });
    }
}
