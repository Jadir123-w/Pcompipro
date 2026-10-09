<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class UpdateTableSmallCashIndexes extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
      if (DB::table('small_cash')->exists()) {
        DB::statement('ALTER TABLE small_cash ADD INDEX small_cash_date_opening_index(date_opening);');
        DB::statement('ALTER TABLE small_cash ADD FULLTEXT small_cash_reference_number_index(reference_number);');
        DB::statement('ALTER TABLE small_cash ADD INDEX small_cash_state_index(state);');
      }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
      if (DB::table('small_cash')->exists()) {
        Schema::table('small_cash', function (Blueprint $table) {
          $table->dropIndex('small_cash_date_opening_index');
          $table->dropIndex('small_cash_reference_number_index');
          $table->dropIndex('small_cash_state_index');
        });
      }
    }
}
