<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * M3 — Apartado "Cambios e indicaciones" del proyecto.
 *
 * Bitácora de comentarios editables: guarda el autor y la fecha/hora del registro
 * (user_id + created_at) y, si se edita, el autor y la fecha/hora de la última
 * edición (edited_by + edited_at). No reemplaza la información del proyecto; es un
 * historial cronológico de cambios e indicaciones durante la ejecución.
 */
class CreateProyectoBitacoraTable extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('proyecto_bitacora')) {
            Schema::create('proyecto_bitacora', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedInteger('proyecto_id')->comment('FK proyecto');
                $table->text('contenido');
                $table->unsignedInteger('user_id')->nullable()->comment('Autor del registro');
                $table->unsignedInteger('edited_by')->nullable()->comment('Autor de la última edición');
                $table->dateTime('edited_at')->nullable()->comment('Fecha/hora de la última edición');
                $table->timestamps();

                $table->index('proyecto_id', 'idx_proyecto_bitacora_proyecto');
                $table->foreign('proyecto_id', 'fk_proyecto_bitacora_proyecto')
                      ->references('id')->on('proyecto')
                      ->onDelete('cascade');
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('proyecto_bitacora');
    }
}
