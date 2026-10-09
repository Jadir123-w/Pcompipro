<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateQuotationPrequoteItemsTable extends Migration
{
    public function up()
    {
        Schema::create('quotation_prequote_items', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedBigInteger('prequote_id')
                ->comment('FK a quotation_prequotes');

            $table->integer('item_order')->default(0)
                ->comment('Orden del ítem en la precotización');

            // ── Referencia al catálogo del ERP ──
            $table->unsignedInteger('item_id')->nullable()
                ->comment('FK a items del ERP si se encontró match');

            // ── Datos del producto ──
            $table->string('product_name', 255)
                ->comment('Nombre del producto (detectado o ingresado)');

            $table->text('product_description')->nullable()
                ->comment('Descripción detallada');

            $table->string('product_reference_image', 500)->nullable()
                ->comment('URL o path de imagen referencial');

            // ── Cantidad ──
            $table->integer('quantity')->default(1);

            $table->string('unit_type_id', 10)->nullable()
                ->comment('FK a cat_unit_types (UND, KG, etc.)');

            // ── Datos inferidos / configurables por ítem ──
            $table->string('material', 100)->nullable()
                ->comment('Material inferido o ingresado');

            $table->string('medida', 100)->nullable()
                ->comment('Medida inferida o ingresada');

            $table->string('tecnica_impresion', 100)->nullable()
                ->comment('Serigrafía, bordado, sublimación, full color, etc.');

            $table->integer('cantidad_colores')->default(1);

            $table->integer('cantidad_lados')->default(1);

            $table->string('empaque', 100)->nullable()
                ->comment('Tipo de empaque sugerido');

            $table->string('personalizacion', 255)->nullable()
                ->comment('Detalle de personalización: logo, texto, diseño');

            // ── Proveedor ──
            $table->unsignedBigInteger('supplier_id')->nullable()
                ->comment('FK a persons (type=suppliers)');

            $table->string('supplier_name', 191)->nullable()
                ->comment('Nombre del proveedor (desnormalizado para display)');

            // ── Costos y cálculo ──
            $table->decimal('costo_base', 12, 2)->default(0)
                ->comment('Costo base unitario del producto');

            $table->text('costos_adicionales')->nullable()
                ->comment('JSON: [{concepto, monto}] — impresión, muestra, empaque, movilidad');

            $table->decimal('margen_porcentaje', 5, 2)->default(0)
                ->comment('Porcentaje de margen aplicado');

            $table->decimal('valor_unitario', 12, 2)->default(0)
                ->comment('Precio unitario resultante (costo + adicionales + margen)');

            $table->decimal('valor_total', 12, 2)->default(0)
                ->comment('valor_unitario × quantity');

            $table->decimal('base_igv', 12, 2)->default(0)
                ->comment('Base imponible para IGV');

            $table->decimal('igv', 12, 2)->default(0)
                ->comment('Monto IGV del ítem');

            $table->decimal('total_item', 12, 2)->default(0)
                ->comment('Total final del ítem con IGV');

            // ── Tiempo ──
            $table->integer('tiempo_estimado_dias')->nullable()
                ->comment('Días estimados de producción');

            // ── Datos de trazabilidad ──
            $table->text('datos_explicitos')->nullable()
                ->comment('JSON: datos que el usuario proporcionó explícitamente');

            $table->text('datos_inferidos')->nullable()
                ->comment('JSON: datos que el sistema infirió automáticamente');

            $table->text('observaciones')->nullable();

            $table->boolean('is_inferred')->default(true)
                ->comment('true si el ítem fue generado por IA, false si fue manual');

            $table->timestamps();

            // ── Índices ──
            $table->index('prequote_id');
            $table->index('item_id');
            $table->index('supplier_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('quotation_prequote_items');
    }
}
