<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateRequerimientoInteraccionTable extends Migration
{
    public function up()
    {
        Schema::create('requerimiento_interaccion', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedBigInteger('requerimiento_id');
            $table->string('canal', 100)->nullable();
            $table->text('resumen')->nullable();
            $table->boolean('requiere_seguimiento')->default(false);
            $table->timestamp('proximo_seguimiento')->nullable();
            $table->text('archivo_adjunto')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Índices
            $table->index('requerimiento_id', 'req_interaccion_requerimiento_id_idx');
            $table->index('user_id', 'req_interaccion_user_id_idx');

            // 🔐 Si el tipo de columna coincide con la tabla requerimiento y users,
            // puedes activar estos foreign keys (si da error, coméntalos):
            // $table->foreign('requerimiento_id')
            //       ->references('id')->on('requerimiento')
            //       ->onDelete('cascade');
            //
            // $table->foreign('user_id')
            //       ->references('id')->on('users')
            //       ->onDelete('set null');
        });
    }

    public function down()
    {
        Schema::dropIfExists('requerimiento_interaccion');
    }
}
