<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProyectoRazonesSocialesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * Crea la tabla proyecto_razones_sociales para cuando se factura 
     * a múltiples razones sociales en un mismo proyecto.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('proyecto_razones_sociales')) {
            Schema::create('proyecto_razones_sociales', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedInteger('proyecto_id')->comment('FK proyecto');
                $table->string('razon_social', 255);
                $table->string('ruc', 11)->nullable();
                $table->decimal('monto_facturar', 12, 2)->nullable();
                $table->tinyInteger('tiene_oc')->default(0);
                $table->string('oc_numero', 100)->nullable();
                $table->text('observaciones')->nullable();
                $table->timestamps();

                // Índices
                $table->index('proyecto_id', 'idx_proyecto_razones_sociales_proyecto');
                $table->index('ruc', 'idx_proyecto_razones_sociales_ruc');

                // Foreign key a proyecto
                $table->foreign('proyecto_id', 'fk_proyecto_razones_sociales_proyecto')
                      ->references('id')->on('proyecto')
                      ->onDelete('cascade');
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('proyecto_razones_sociales');
    }
}
