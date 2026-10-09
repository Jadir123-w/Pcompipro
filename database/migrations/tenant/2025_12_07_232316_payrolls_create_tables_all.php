<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class PayrollsCreateTablesAll extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('payroll_afps', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nombre', 100);
            $table->string('codigo', 20);
            $table->decimal('comision_flujo', 5, 2)->default(0);
            $table->decimal('comision_mixta', 5, 2)->default(0);
            $table->decimal('aporte_obligatorio', 5, 2)->default(10.00);
            $table->decimal('seguro_invalidez', 5, 2)->default(1.35);
            $table->boolean('activo')->default(true);
            $table->unsignedInteger('created_by')->nullable();
            $table->unsignedInteger('updated_by')->nullable();
            $table->unsignedInteger('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('deleted_by')->references('id')->on('users')->onDelete('set null');
            
            // Constraint único que incluye deleted_at para permitir re-registrar AFPs eliminadas
            $table->unique(['codigo', 'deleted_at']);
        });

        Schema::create('payroll_employees', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('user_id');
            $table->string('codigo_empleado', 20);
            $table->date('fecha_cese')->nullable();
            $table->enum('tipo_regimen', ['AFP', 'ONP'])->default('AFP');
            $table->unsignedInteger('afp_id')->nullable();
            $table->enum('tipo_comision_afp', ['FLUJO', 'MIXTA'])->nullable();
            $table->string('cuspp', 12)->nullable()->comment('Código CUSPP para AFP');
            $table->decimal('sueldo_basico', 10, 2)->default(0);
            $table->boolean('asignacion_familiar')->default(false);
            $table->decimal('movilidad', 10, 2)->default(0);
            $table->boolean('activo')->default(true);
            $table->unsignedInteger('created_by')->nullable();
            $table->unsignedInteger('updated_by')->nullable();
            $table->unsignedInteger('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('afp_id')->references('id')->on('payroll_afps')->onDelete('set null');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('deleted_by')->references('id')->on('users')->onDelete('set null');
            
            // Constraints únicos que incluyen deleted_at para permitir re-registrar empleados eliminados
            // Solo permite UN registro activo (deleted_at = NULL) por user_id y codigo_empleado
            $table->unique(['user_id', 'deleted_at']);
            $table->unique(['codigo_empleado', 'deleted_at']);
        });

        Schema::create('payroll_concepts_payroll', function (Blueprint $table) {
            $table->increments('id');
            $table->string('codigo', 20);
            $table->string('nombre', 100);
            $table->text('descripcion')->nullable();
            $table->enum('tipo', ['INGRESO', 'DESCUENTO'])->default('INGRESO');
            $table->enum('naturaleza', ['FIJO', 'VARIABLE'])->default('FIJO');
            $table->boolean('afecto_essalud')->default(true);
            $table->boolean('afecto_renta')->default(true);
            $table->boolean('afecto_afp_onp')->default(true);
            $table->boolean('sistema')->default(false)->comment('Conceptos del sistema no se pueden eliminar');
            $table->integer('orden')->default(0);
            $table->boolean('activo')->default(true);
            $table->unsignedInteger('created_by')->nullable();
            $table->unsignedInteger('updated_by')->nullable();
            $table->unsignedInteger('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('deleted_by')->references('id')->on('users')->onDelete('set null');
            
            // Constraint único que incluye deleted_at para permitir re-registrar conceptos eliminados
            $table->unique(['codigo', 'deleted_at']);
        });

        Schema::create('payroll_configurations', function (Blueprint $table) {
            $table->increments('id');
            $table->string('clave', 100)->unique();
            $table->string('nombre', 150);
            $table->string('valor', 50);
            $table->string('tipo', 20)->default('decimal')->comment('decimal, texto, entero, boolean');
            $table->text('descripcion')->nullable();
            $table->integer('anio')->default(2025);
            $table->unsignedInteger('created_by')->nullable();
            $table->unsignedInteger('updated_by')->nullable();
            $table->timestamps();
            
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');
        });

        Schema::create('payroll', function (Blueprint $table) {
            $table->increments('id');
            $table->string('codigo', 50);
            $table->string('periodo', 7)->comment('Formato: YYYY-MM');
            $table->integer('anio');
            $table->integer('mes');
            $table->date('fecha_inicio');
            $table->date('fecha_fin');
            $table->date('fecha_pago')->nullable();
            $table->enum('estado', ['BORRADOR', 'PROCESADA', 'APROBADA', 'PAGADA', 'ANULADA'])->default('BORRADOR');
            $table->decimal('total_ingresos', 12, 2)->default(0);
            $table->decimal('total_descuentos', 12, 2)->default(0);
            $table->decimal('total_aportaciones', 12, 2)->default(0);
            $table->decimal('total_neto', 12, 2)->default(0);
            $table->integer('total_empleados')->default(0);
            $table->text('observaciones')->nullable();
            $table->unsignedInteger('procesada_by')->nullable();
            $table->timestamp('procesada_at')->nullable();
            $table->unsignedInteger('aprobada_by')->nullable();
            $table->timestamp('aprobada_at')->nullable();
            $table->unsignedInteger('created_by')->nullable();
            $table->unsignedInteger('updated_by')->nullable();
            $table->unsignedInteger('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            $table->foreign('procesada_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('aprobada_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('deleted_by')->references('id')->on('users')->onDelete('set null');
            
            // Constraints únicos que incluyen deleted_at para permitir re-registrar planillas eliminadas
            // Solo permite UN registro activo (deleted_at = NULL) por codigo y por periodo
            $table->unique(['codigo', 'deleted_at']);
            $table->unique(['anio', 'mes', 'periodo', 'deleted_at']);
        });


        Schema::create('payroll_detail', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('planilla_id');
            $table->unsignedInteger('empleado_id');
            
            // Datos del empleado (snapshot)
            $table->string('dni', 8);
            $table->string('nombres_completos', 255);
            $table->string('cargo', 100)->nullable();
            $table->decimal('sueldo_basico', 10, 2);
            $table->integer('dias_laborados')->default(30);
            $table->integer('dias_mes')->default(30);
            
            // Ingresos
            $table->decimal('asignacion_familiar', 10, 2)->default(0);
            $table->decimal('horas_extras', 10, 2)->default(0);
            $table->decimal('bonificaciones', 10, 2)->default(0);
            $table->decimal('movilidad', 10, 2)->default(0);
            $table->decimal('otros_ingresos', 10, 2)->default(0);
            $table->decimal('total_ingresos', 10, 2)->default(0);
            $table->decimal('total_ingresos_afectos', 10, 2)->default(0);
            $table->decimal('total_ingresos_no_afectos', 10, 2)->default(0);
            
            // Descuentos
            $table->enum('tipo_regimen', ['AFP', 'ONP']);
            $table->string('afp_nombre', 100)->nullable();
            $table->decimal('aporte_afp_onp', 10, 2)->default(0);
            $table->decimal('comision_afp', 10, 2)->default(0);
            $table->decimal('seguro_afp', 10, 2)->default(0);
            $table->decimal('renta_quinta', 10, 2)->default(0);
            $table->decimal('otros_descuentos', 10, 2)->default(0);
            $table->decimal('total_descuentos', 10, 2)->default(0);
            
            // Aportaciones del empleador
            $table->decimal('essalud', 10, 2)->default(0);
            $table->decimal('total_aportaciones', 10, 2)->default(0);
            
            // Neto a pagar
            $table->decimal('neto_pagar', 10, 2)->default(0);
            
            // Control de envío
            $table->boolean('boleta_enviada')->default(false);
            $table->timestamp('boleta_enviada_at')->nullable();
            
            $table->unsignedInteger('created_by')->nullable();
            $table->unsignedInteger('updated_by')->nullable();
            $table->timestamps();
            
            $table->foreign('planilla_id')->references('id')->on('payroll')->onDelete('cascade');
            $table->foreign('empleado_id')->references('id')->on('payroll_employees')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');
            
            $table->unique(['planilla_id', 'empleado_id']);
        });

        Schema::create('payroll_concepts', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('planilla_detalle_id');
            $table->unsignedInteger('concepto_id');
            $table->string('concepto_codigo', 20);
            $table->string('concepto_nombre', 100);
            $table->enum('tipo', ['INGRESO', 'DESCUENTO']);
            $table->decimal('monto', 10, 2)->default(0);
            $table->string('observacion', 255)->nullable();
            $table->unsignedInteger('created_by')->nullable();
            $table->timestamps();
            
            $table->foreign('planilla_detalle_id')->references('id')->on('payroll_detail')->onDelete('cascade');
            $table->foreign('concepto_id')->references('id')->on('payroll_concepts_payroll')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
        });

        Schema::create('payroll_auditory', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('tabla', 100);
            $table->unsignedInteger('registro_id');
            $table->enum('accion', ['CREATE', 'UPDATE', 'DELETE', 'RESTORE'])->default('UPDATE');
            $table->unsignedInteger('usuario_id')->nullable();
            $table->string('usuario_nombre', 150)->nullable();
            $table->string('campo', 100)->nullable();
            $table->text('valor_anterior')->nullable();
            $table->text('valor_nuevo')->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('fecha_hora')->useCurrent();
            
            $table->foreign('usuario_id')->references('id')->on('users')->onDelete('set null');
            
            $table->index(['tabla', 'registro_id']);
            $table->index('usuario_id');
            $table->index('fecha_hora');
        });

    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('payroll_concepts');
        Schema::dropIfExists('payroll_detail');
        Schema::dropIfExists('payroll');
        Schema::dropIfExists('payroll_employees');
        Schema::dropIfExists('payroll_configurations');
        Schema::dropIfExists('payroll_concepts_payroll');
        Schema::dropIfExists('payroll_auditory');
        Schema::dropIfExists('payroll_afps');
        
    }
}
