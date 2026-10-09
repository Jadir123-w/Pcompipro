<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class TenantAddHasConsignedToPersonAddresses extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('person_addresses', function (Blueprint $table) {
            $table->boolean('has_consigned')->default(false);
            $table->unsignedInteger('consigned_id')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('person_addresses', function (Blueprint $table) {
            $table->dropColumn('has_consigned');
            $table->dropColumn('consigned_id');
        });
    }
}
