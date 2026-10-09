<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class AddConstructionContractsToCatDetractionTypesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::table('cat_detraction_types')->insert([
            'id' => '030',
            'operation_type_id' => '1001',
            'active' => true,
            'percentage' => 4,
            'description' => 'Contratos de construcción'
        ]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::table('cat_detraction_types')->where('id', '030')->delete();
    }
}
