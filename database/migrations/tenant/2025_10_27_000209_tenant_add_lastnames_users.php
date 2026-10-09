<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class TenantAddLastnamesUsers extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
      Schema::table('users', function(Blueprint $table) {
        $table->string('paternal_lastname')->nullable()->after('name');
        $table->string('maternal_lastname')->nullable()->after('paternal_lastname');

        $table->dropColumn('last_names');
      });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
      Schema::table('users', function(Blueprint $table) {
        $table->dropColumn(['paternal_lastname', 'maternal_lastname']);

        $table->string('last_names')->nullable()->after('locked');
      });
    }
}
