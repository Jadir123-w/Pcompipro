<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Migrations\Migration;

class AddModuleLogisticaToTenancyTableModule extends Migration
{
    public function up()
    {
        $exists = DB::table('modules')->where('value', 'logistica')->exists();
        if (! $exists) {
            $moduleId = DB::table('modules')->insertGetId([
                'value'       => 'logistica',
                'description' => 'Logística',
                'order_menu'  => 30,
            ]);

            $userIds = DB::table('users')->pluck('id');
            foreach ($userIds as $userId) {
                DB::table('module_user')->insert([
                    'module_id' => $moduleId,
                    'user_id'   => $userId,
                ]);
            }
        }
    }

    public function down()
    {
        $moduleId = DB::table('modules')->where('value', 'logistica')->value('id');
        if ($moduleId) {
            DB::table('module_user')->where('module_id', $moduleId)->delete();
            DB::table('modules')->where('id', $moduleId)->delete();
        }
    }
}
