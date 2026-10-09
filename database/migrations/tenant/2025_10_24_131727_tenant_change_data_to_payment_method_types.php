<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class TenantChangeDataToPaymentMethodTypes extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::table('payment_method_types')->whereIn('id', ['02', '03', '06'])->update([
            'is_cash' => true,
        ]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::table('payment_method_types')->whereIn('id', ['02', '03', '06'])->update([
            'is_cash' => false,
        ]);
    }
}
