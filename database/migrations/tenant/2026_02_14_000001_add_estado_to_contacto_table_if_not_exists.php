<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddEstadoToContactoTableIfNotExists extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('contacto') && !Schema::hasColumn('contacto', 'estado')) {
            Schema::table('contacto', function (Blueprint $table) {
                $table->tinyInteger('estado')->default(1)->after('celular');
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
        if (Schema::hasColumn('contacto', 'estado')) {
            Schema::table('contacto', function (Blueprint $table) {
                $table->dropColumn('estado');
            });
        }
    }
}
