<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class TenantChangeDistrict080918ToDistricts extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
            
        DB::table('districts')->where('id', '=', '080918')->delete();
        DB::table('districts')->where('id', '=', '080919')->update([
            'id' =>'080918',
        ]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::table('districts')->where('id', '=', '080918')->update([
            'id' => '080919' ,
        ]);

        DB::table('districts')->insert([
            'id' => '080918',
            'province_id' => '0809',
            'description' => 'Megantoni',
            'active' => true
        ]);
    }
}
