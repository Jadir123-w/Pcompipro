<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreatePayrollEmployeesTable extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('payroll_employees')) {
            Schema::create('payroll_employees', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('website_id');
                $table->string('dni', 8);
                $table->string('nombres', 100);
                $table->string('apellidos', 100);
                $table->date('fecha_nacimiento');
                $table->string('direccion', 200);
                $table->string('telefono', 15)->nullable();
                $table->string('email', 100);
                $table->date('fecha_ingreso');
                $table->enum('tipo_contrato', ['CAS', 'Practicante', 'Aprendiz', 'Otros'])->default('CAS');
                $table->decimal('sueldo_basico', 10, 2)->default(0);
                $table->string('banco', 50)->nullable();
                $table->string('numero_cuenta', 20)->nullable();
                $table->boolean('activo')->default(true);
                $table->timestamps();
                $table->softDeletes();

                // Índices
                $table->index('website_id');
                $table->index('dni');
                $table->index('email');
                $table->index('activo');
                $table->unique(['website_id', 'dni']);
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('payroll_employees');
    }
}
