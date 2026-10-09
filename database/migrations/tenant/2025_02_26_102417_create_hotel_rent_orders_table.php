<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateHotelRentOrdersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('hotel_rent_orders', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('hotel_rent_id');
            $table->foreign('hotel_rent_id')->references('id')->on('hotel_rents');
            $table->integer('order_number');
            $table->string('order_status');
            $table->unsignedInteger('sale_note_id')->nullable();
            $table->foreign('sale_note_id')->references('id')->on('sale_notes');
            $table->unsignedInteger('establishment_id')->nullable();
            $table->foreign('establishment_id')->references('id')->on('establishments');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('hotel_rent_orders');
    }
}
