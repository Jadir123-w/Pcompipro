<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class UpdateTableContactosIndexes extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::statement('ALTER TABLE contacto ADD FULLTEXT contacto_nombres_index(nombres);');
        DB::statement('ALTER TABLE contacto ADD FULLTEXT contacto_apellidos_index(apellidos);');
        DB::statement('ALTER TABLE contacto ADD FULLTEXT contacto_cargo_index(cargo);');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
      Schema::table('contacto', function (Blueprint $table) {
        $table->dropIndex('contacto_nombres_index');
        $table->dropIndex('contacto_apellidos_index');
        $table->dropIndex('contacto_cargo_index');
      });
    }
}
