<?php
// Modules/Comercial/Database/Migrations/2025_10_09_000002_create_contacto_table.php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateContactoTable extends Migration
{
    public function up()
    {
        Schema::create('contacto', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('empresa_id')->index();
            $table->string('nombres',191);
            $table->string('apellidos',191)->nullable();
            $table->string('cargo',191)->nullable();
            $table->tinyInteger('estado')->default(1);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('empresa_id')->references('id')->on('empresa')->onDelete('cascade');
        });
    }
    public function down() { Schema::dropIfExists('contacto'); }
}
