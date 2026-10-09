<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class TenantCreateConfigurationTaps extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('configuration_taps', function (Blueprint $table) {
            $table->increments('id');
            $table->boolean('save_plates_client')->default(false);
            $table->timestamps();
        });

        DB::table('configuration_taps')->insert([
            'save_plates_client' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('configuration_taps');

        //DB::table('configuration_taps')->delete();
    }
}
