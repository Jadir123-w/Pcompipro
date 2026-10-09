<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class TenantAddHotelRentOrderIdToHotelRentItems extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('hotel_rent_items', function (Blueprint $table) {
            $table->unsignedInteger('hotel_rent_order_id')->nullable()->after('payment_status');
            $table->foreign('hotel_rent_order_id')->references('id')->on('hotel_rent_orders');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('hotel_rent_items', function (Blueprint $table) {
            $table->dropForeign(['hotel_rent_order_id']);
            $table->dropColumn('hotel_rent_order_id');
        });
    }
}
