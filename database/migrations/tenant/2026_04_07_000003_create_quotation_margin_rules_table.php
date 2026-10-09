<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateQuotationMarginRulesTable extends Migration
{
    public function up()
    {
        Schema::create('quotation_margin_rules', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->string('category', 100)
                ->comment('Categoría del producto: Textil, Impresos, Ecológica, Packs, General');

            $table->string('product_name', 255)->nullable()
                ->comment('Nombre específico del producto (null = aplica a toda la categoría)');

            $table->integer('quantity_from')->default(1)
                ->comment('Cantidad mínima del tramo');

            $table->integer('quantity_to')->nullable()
                ->comment('Cantidad máxima del tramo (null = sin límite superior)');

            $table->decimal('margin_percentage', 5, 2)->default(30)
                ->comment('Porcentaje de margen para este tramo');

            $table->integer('priority')->default(0)
                ->comment('Prioridad: mayor número = más específica. 0=default, 1=categoría, 2=producto');

            $table->boolean('active')->default(true);

            $table->timestamps();

            // ── Índices ──
            $table->index('category');
            $table->index('active');
            $table->index('priority');
        });
    }

    public function down()
    {
        Schema::dropIfExists('quotation_margin_rules');
    }
}
