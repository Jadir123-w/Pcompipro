<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddOrigenToEmpresaTable extends Migration
{
    public function up()
    {
        Schema::table('empresa', function (Blueprint $table) {
            if (!Schema::hasColumn('empresa', 'origen')) {
                $table->string('origen', 50)
                    ->nullable()
                    ->after('website'); // justo después de website
            }
        });
    }

    public function down()
    {
        Schema::table('empresa', function (Blueprint $table) {
            if (Schema::hasColumn('empresa', 'origen')) {
                $table->dropColumn('origen');
            }
        });
    }
}
