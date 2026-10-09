<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class UpdateTableEmpresasIndexes extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
      DB::statement('ALTER TABLE empresa ADD FULLTEXT empresa_razon_social_index(razon_social);');
      DB::statement('ALTER TABLE empresa ADD FULLTEXT empresa_nombre_comercial_index(nombre_comercial);');
      DB::statement('ALTER TABLE empresa ADD INDEX empresa_n_trabajadores_index(n_trabajadores);');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
      Schema::table('empresa', function (Blueprint $table) {
        $table->dropIndex('empresa_razon_social_index');
        $table->dropIndex('empresa_nombre_comercial_index');
        $table->dropIndex('empresa_n_trabajadores_index');
      });
    }
}
