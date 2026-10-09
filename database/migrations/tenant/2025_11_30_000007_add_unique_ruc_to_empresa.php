<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddUniqueRucToEmpresa extends Migration
{
    public function up()
    {
        Schema::table('empresa', function (Blueprint $table) {
            // Crea índice único con nombre amigable
            $table->unique('ruc', 'uq_empresa_ruc');
        });
    }

    public function down()
    {
        /*Schema::table('empresa', function (Blueprint $table) {
            // Elimina el índice por su nombre
            $table->dropUnique('uq_empresa_ruc');
        });*/
    }
}
