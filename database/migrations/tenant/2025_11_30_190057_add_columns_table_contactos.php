<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddColumnsTableContactos extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('contacto', function (Blueprint $table) {
          $table->string('celular')->nullable()->after('cargo');
          $table->string('origen')->nullable(false)->after('estado');
          $table->string('tipo_contacto')->nullable()->after('origen');
          $table->date('fecha_cumpleanos')->nullable()->after('tipo_contacto');
          $table->string('direccion')->nullable()->after('fecha_cumpleanos');
        });

        DB::statement('ALTER TABLE contacto ADD FULLTEXT contacto_celular_index(celular);');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('contacto', function (Blueprint $table) {
          $table->dropIndex('contacto_celular_index');

          $table->dropColumn('celular');
          $table->dropColumn('origen');
          $table->dropColumn('tipo_contacto');
          $table->dropColumn('fecha_cumpleanos');
          $table->dropColumn('direccion');
        });

    }
}
