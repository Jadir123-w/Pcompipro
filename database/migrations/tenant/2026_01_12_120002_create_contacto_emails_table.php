<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateContactoEmailsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('contacto_emails')) {
            Schema::create('contacto_emails', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedBigInteger('contacto_id')->comment('ID del contacto');
                $table->string('email', 191)->comment('Correo electrónico');
                $table->boolean('es_principal')->default(false)->comment('1 = email principal');
                $table->tinyInteger('estado')->default(1)->comment('1 = activo, 0 = inactivo (borrado lógico)');
                $table->timestamps();
                
                // Índices
                $table->index('contacto_id', 'idx_contacto_emails_contacto_id');
                $table->index('email', 'idx_contacto_emails_email');
                $table->index('estado', 'idx_contacto_emails_estado');
                
                // Índice único: contacto_id + email + estado
                $table->unique(['contacto_id', 'email', 'estado'], 'unique_contacto_email_activo');
                
                // Foreign key
                $table->foreign('contacto_id', 'fk_contacto_emails_contacto')
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
        Schema::dropIfExists('contacto_emails');
    }
}
