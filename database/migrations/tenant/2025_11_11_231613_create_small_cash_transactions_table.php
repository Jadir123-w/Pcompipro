<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateSmallCashTransactionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('small_cash_transactions', function (Blueprint $table) {
          $table->increments('id');
          $table->unsignedInteger('small_cash_id');
          $table->unsignedInteger('user_id');
          $table->date('date');
          $table->enum('type', ['income', 'expense']);
          $table->char('payment_method_type_id', 2)->nullable();
          $table->decimal('amount', 12, 2);
          $table->text('description');
          $table->string('document_number')->nullable();
          $table->string('supplier_customer')->nullable();
          $table->string('category')->nullable();
          $table->timestamps();

          $table->foreign('small_cash_id')->references('id')->on('small_cash')->onDelete('cascade');
          $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
          $table->foreign('payment_method_type_id')->references('id')->on('payment_method_types');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('small_cash_transactions', function (Blueprint $table) {
          $table->dropForeign(['small_cash_id']);
          $table->dropForeign(['user_id']);
          $table->dropForeign(['payment_method_type_id']);
        });

        Schema::dropIfExists('small_cash_transactions');
    }
}
