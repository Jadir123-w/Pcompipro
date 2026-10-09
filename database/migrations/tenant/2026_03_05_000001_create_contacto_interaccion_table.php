<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateContactoInteraccionTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('contacto_interaccion')) {
            return;
        }

        Schema::create('contacto_interaccion', function (Blueprint $table) {
            $table->bigIncrements('id');
            
            // Relación con contacto (sin foreign key para compatibilidad multi-tenant)
            $table->unsignedBigInteger('contacto_id');
            
            // Datos de la interacción
            $table->string('canal', 100)->nullable();
            $table->text('resumen')->nullable();
            
            // Seguimiento
            $table->tinyInteger('requiere_seguimiento')->default(0);
            $table->timestamp('proximo_seguimiento')->nullable();
            
            // Archivo adjunto
            $table->text('archivo_adjunto')->nullable();
            
            // Usuario que registró (sin foreign key para compatibilidad multi-tenant)
            $table->unsignedBigInteger('user_id')->nullable();
            
            // Timestamps y soft delete
            $table->timestamps();
            $table->softDeletes();
            
            // Índices
            $table->index('contacto_id');
            $table->index('user_id');
            $table->index('canal');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('contacto_interaccion');
    }
}
