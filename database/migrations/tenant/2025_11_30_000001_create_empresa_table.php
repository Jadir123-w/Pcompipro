<?php
// Modules/Comercial/Database/Migrations/2025_10_09_000001_create_empresa_table.php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateEmpresaTable extends Migration
{
    public function up()
    {
        Schema::create('empresa', function (Blueprint $table) {
            $table->increments('id');
            $table->string('ruc', 14)->unique();
            $table->string('razon_social', 191);
            $table->string('nombre_comercial', 191)->nullable();
            $table->string('telefono', 50)->nullable();
            $table->string('email', 191)->nullable()->index();
            $table->string('direccion', 255)->nullable();
            $table->string('website', 191)->nullable();
            $table->tinyInteger('estado')->default(1);
            $table->timestamps();
            $table->softDeletes();
        });
    }
    public function down() { Schema::dropIfExists('empresa'); }
}
