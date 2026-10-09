<?php
// Modules/Comercial/Database/Migrations/2025_10_09_000005_create_proyecto_table.php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateProyectoTable extends Migration
{
    public function up()
    {
        Schema::create('proyecto', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('empresa_id')->index();
            $table->unsignedInteger('requerimiento_id')->nullable()->index();
            $table->string('codigo',50)->unique();
            $table->string('nombre',191);
            $table->string('descripcion',1000)->nullable();
            $table->decimal('monto_total',12,2)->nullable();
            $table->date('fecha_inicio')->nullable();
            $table->date('fecha_fin')->nullable();
            $table->string('etapa',50)->nullable();   // ejecución, en pausa, cerrado
            $table->string('estado',50)->default('activo');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('empresa_id')->references('id')->on('empresa')->onDelete('cascade');
            $table->foreign('requerimiento_id')->references('id')->on('requerimiento')->onDelete('set null');
        });
    }
    public function down() { Schema::dropIfExists('proyecto'); }
}
