<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class UpdateTipoEmpresaEnum extends Migration
{
    public function up()
    {
        // Cambiar ENUM a VARCHAR para soportar nuevos tipos de empresa
        DB::statement("ALTER TABLE empresa MODIFY COLUMN tipo_empresa VARCHAR(50) NULL DEFAULT NULL");
    }

    public function down()
    {
        DB::statement("ALTER TABLE empresa MODIFY COLUMN tipo_empresa ENUM('PROSPECTO','FRECUENTE','TERCERISTA','POTENCIAL','OTRO') NULL DEFAULT NULL");
    }
}
