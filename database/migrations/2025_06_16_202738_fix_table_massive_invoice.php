<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class FixTableMassiveInvoice extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('massive_invoices', function (Blueprint $table) {
            $table->string('ruc_emisor', 500)->change();
            $table->string('ruc', 500)->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('massive_invoices', function (Blueprint $table) {
            $table->string('ruc_emisor', 20)->change();
            $table->string('ruc', 20)->change();
        });
    }
}
