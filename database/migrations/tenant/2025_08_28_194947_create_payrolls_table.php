<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreatePayrollsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('payrolls', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('website_id');
            $table->unsignedBigInteger('employee_id');
            $table->date('periodo');
            $table->integer('dias_trabajados')->default(30);
            $table->decimal('horas_extras', 8, 2)->default(0);
            $table->decimal('bonificaciones', 10, 2)->default(0);
            $table->decimal('otros_ingresos', 10, 2)->default(0);
            $table->decimal('adelantos', 10, 2)->default(0);
            $table->decimal('otros_descuentos', 10, 2)->default(0);

            // Descuentos legales Perú
            $table->decimal('essalud', 10, 2)->default(0);
            $table->decimal('afp_fondo', 10, 2)->default(0);
            $table->decimal('afp_seguro', 10, 2)->default(0);
            $table->decimal('afp_comision', 10, 2)->default(0);
            $table->decimal('quinta_categoria', 10, 2)->default(0);

            // Totales
            $table->decimal('total_ingresos', 10, 2)->default(0);
            $table->decimal('total_descuentos', 10, 2)->default(0);
            $table->decimal('neto_pagable', 10, 2)->default(0);

            $table->text('observaciones')->nullable();
            $table->boolean('pagado')->default(false);
            $table->date('fecha_pago')->nullable();
            $table->timestamps();
            $table->softDeletes();


            // Índices
            $table->index('website_id');
            $table->index('employee_id');
            $table->index('periodo');
            $table->index('pagado');
            $table->index(['website_id', 'periodo']);
            $table->index(['employee_id', 'periodo']);
            $table->unique(['website_id', 'employee_id', 'periodo']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('payrolls');
    }
}
