<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla de evaluación de proveedores por proyecto (post-venta).
 *
 * Permite registrar, por cada proyecto, una calificación y comentario
 * sobre uno o más proveedores que participaron. Sirve como rating
 * histórico del desempeño postventa.
 */
class CreateProyectoEvaluacionProveedorTable extends Migration
{
    public function up()
    {
        Schema::create('proyecto_evaluacion_proveedor', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('proyecto_id');
            $table->unsignedInteger('proveedor_id'); // FK lógica → persons.id (type='suppliers')
            $table->string('proveedor_nombre', 255)->nullable(); // snapshot de nombre, por si proveedor cambia
            $table->tinyInteger('estrellas')->nullable(); // 1..5 (rating visual)
            $table->string('calificacion_label', 20)->nullable(); // 'Bueno' | 'Regular' | 'Malo'
            $table->text('comentario')->nullable();
            $table->unsignedInteger('user_id')->nullable();
            $table->timestamps();

            $table->foreign('proyecto_id')
                ->references('id')->on('proyecto')
                ->onDelete('cascade');

            $table->index('proyecto_id');
            $table->index('proveedor_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('proyecto_evaluacion_proveedor');
    }
}
