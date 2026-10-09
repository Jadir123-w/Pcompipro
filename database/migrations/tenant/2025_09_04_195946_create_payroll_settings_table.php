<?php

use Hyn\Tenancy\Models\Website;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreatePayrollSettingsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('payroll_settings', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('website_id');
            $table->decimal('uit', 10, 2)->default(4950);
            $table->decimal('porcentaje_essalud', 5, 2)->default(9.00);
            $table->decimal('porcentaje_afp_fondo', 5, 2)->default(10.00);
            $table->decimal('porcentaje_afp_seguro', 5, 2)->default(1.37);
            $table->decimal('porcentaje_afp_comision', 5, 2)->default(0.77);
            $table->decimal('tasa_hora_extra', 5, 2)->default(1.25);
            $table->integer('dias_mes_laboral')->default(30);
            $table->integer('horas_dia_laboral')->default(8);
            $table->timestamps();

            // Índices
            $table->index('website_id');
            $table->unique('website_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('payroll_settings');
    }
}
