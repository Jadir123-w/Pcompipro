<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class AddMarketingToTenantModules extends Migration
{
    public function up()
    {
        // Evitar duplicado
        $exists = DB::table('modules')->where('value', 'marketing')->first();
        if ($exists) {
            // Asegurar que exista el level chatbot
            $levelExists = DB::table('module_levels')
                ->where('module_id', $exists->id)
                ->where('value', 'chatbot')
                ->first();

            if (!$levelExists) {
                DB::table('module_levels')->insert([
                    'module_id'    => $exists->id,
                    'value'        => 'chatbot',
                    'description'  => 'Chatbot',
                ]);
            }
            return;
        }

        // Insert módulo en tenant.modules
        $moduleId = DB::table('modules')->insertGetId([
            'value'       => 'marketing',
            'description' => 'Marketing',
            // Si tu tabla usa order_menu (según DevOps), lo seteamos.
            // Si en tu tenant no existe ese campo, me avisas y lo ajusto.
            'order_menu'  => 29,
        ]);

        // Insert level en tenant.module_levels
        DB::table('module_levels')->insert([
            'module_id'   => $moduleId,
            'value'       => 'chatbot',
            'description' => 'Chatbot',
        ]);
    }

    public function down()
    {
        $module = DB::table('modules')->where('value', 'marketing')->first();
        if ($module) {
            DB::table('module_levels')->where('module_id', $module->id)->delete();
            DB::table('modules')->where('id', $module->id)->delete();
        }
    }
}
