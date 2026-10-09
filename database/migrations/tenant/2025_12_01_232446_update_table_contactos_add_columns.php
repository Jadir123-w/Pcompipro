<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class UpdateTableContactosAddColumns extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
      Schema::table('contacto', function (Blueprint $table) {
        $table->string('telefono')->nullable()->after('direccion');
        $table->string('email')->nullable()->after('telefono');
      });
      DB::statement('ALTER TABLE contacto ADD FULLTEXT contacto_telefono_index(telefono);');
      DB::statement('ALTER TABLE contacto ADD FULLTEXT contacto_email_index(email);');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
      Schema::table('contacto', function (Blueprint $table) {
        $table->dropIndex('contacto_telefono_index');
        $table->dropIndex('contacto_email_index');

        $table->dropColumn('telefono');
        $table->dropColumn('email');
      });

    }
}
