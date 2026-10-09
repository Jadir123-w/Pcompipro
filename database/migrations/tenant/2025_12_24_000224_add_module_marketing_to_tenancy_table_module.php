<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Migrations\Migration;

class AddModuleMarketingToTenancyTableModule extends Migration
{
    public function up()
    {
        $moduleExists = DB::table('modules')->where('value', 'marketing')->first();
        
        if (!$moduleExists) {
            DB::table('modules')->insert([
                ['id' => 56, 'value' => 'marketing', 'description' => 'Marketing', 'order_menu' => 29],
            ]);
        }

        $levelExists = DB::table('module_levels')
            ->where('module_id', 56)
            ->where('value', 'chatbot')
            ->first();
            
        if (!$levelExists) {
            DB::table('module_levels')->insert([
                ['value' => 'chatbot', 'description' => 'Chatbot', 'module_id' => 56],
            ]);
        }
    }

    public function down()
    {
        DB::table('module_levels')->where('module_id', '=', 56)->delete();
        DB::table('modules')->where('value', '=', 'marketing')->delete();
    }
}
