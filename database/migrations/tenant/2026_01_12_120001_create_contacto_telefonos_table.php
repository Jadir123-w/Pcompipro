<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateContactoTelefonosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('contacto_telefonos')) {
            Schema::create('contacto_telefonos', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedBigInteger('contacto_id')->comment('ID del contacto');
                $table->string('telefono', 50)->comment('Número de teléfono');
                $table->string('tipo', 50)->default('movil')->comment('Tipo: movil, fijo, trabajo, casa, otro');
                $table->boolean('es_principal')->default(false)->comment('1 = teléfono principal');
                $table->tinyInteger('estado')->default(1)->comment('1 = activo, 0 = inactivo (borrado lógico)');
                $table->timestamps();
                
                // Índices
                $table->index('contacto_id', 'idx_contacto_telefonos_contacto_id');
                $table->index('telefono', 'idx_contacto_telefonos_telefono');
                $table->index('estado', 'idx_contacto_telefonos_estado');
                
                // Índice único: contacto_id + telefono + estado
                $table->unique(['contacto_id', 'telefono', 'estado'], 'unique_contacto_telefono_activo');
                
                // Foreign key
                $table->foreign('contacto_id', 'fk_contacto_telefonos_contacto')
                      ->references('id')
                      ->on('contacto')
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
        Schema::dropIfExists('contacto_telefonos');
    }
}
