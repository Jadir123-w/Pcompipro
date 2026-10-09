<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class TenantCreateSaleNoteFeeTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('sale_note_fees', function(Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('sale_note_id');
            $table->date('date');
            $table->string('currency_type_id');
            $table->decimal('amount', 12, 2);
            $table->char('payment_method_type_id',2)->nullable()->comment('Relacion con el metodo de pago, Nulo es pago a cuotas');
            $table->foreign('sale_note_id')->references('id')->on('sale_notes')->onDelete('cascade');
            $table->foreign('currency_type_id')->references('id')->on('cat_currency_types');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists("sale_note_fee");
    }
}
