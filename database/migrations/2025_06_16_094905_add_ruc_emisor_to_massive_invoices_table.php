<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddRucEmisorToMassiveInvoicesTable extends Migration
{
    public function up()
    {
        Schema::table('massive_invoices', function (Blueprint $table) {
            $table->string('ruc_emisor', 20)->nullable()->after('fecha_vencimiento');
        });
    }

    public function down()
    {
        Schema::table('massive_invoices', function (Blueprint $table) {
            $table->dropColumn('ruc_emisor');
        });
    }
}
