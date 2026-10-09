<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateHistoriaActividadRequerimientoTable extends Migration
{
    public function up()
    {
        Schema::create('historia_actividad_requerimiento', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedBigInteger('requerimiento_id');
            $table->string('tipo_actividad', 100)->nullable();
            $table->text('detalle')->nullable();
            $table->string('etapa_anterior', 100)->nullable();
            $table->string('etapa_nueva', 100)->nullable();
            $table->string('usuario', 150)->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('requerimiento_id', 'hist_act_req_requerimiento_id_idx');

            // 🔐 Foreign key opcional (activar solo si el tipo coincide):
            // $table->foreign('requerimiento_id')
            //       ->references('id')->on('requerimiento')
            //       ->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('historia_actividad_requerimiento');
    }
}
