<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateSmallCashTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('small_cash', function (Blueprint $table) {
          $table->increments('id');
          $table->unsignedInteger('user_id');
          $table->string('code')->unique();
          $table->date('date_opening');
          $table->date('date_closed')->nullable();
          $table->decimal('beginning_balance', 12, 2);
          $table->decimal('final_balance', 12, 2)->default(0);
          $table->decimal('income', 12, 2)->default(0);
          $table->decimal('expense', 12, 2)->default(0);
          $table->enum('state', ['open', 'closed'])->default('open');
          $table->text('reference_number')->nullable();
          $table->text('description')->nullable();
          $table->timestamps();

          $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('small_cash', function (Blueprint $table) {
          $table->dropForeign(['user_id']);
        });
        Schema::dropIfExists('small_cash');
    }
}
