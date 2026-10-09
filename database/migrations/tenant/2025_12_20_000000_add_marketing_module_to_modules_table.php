<?php

use App\Models\System\Module;
use App\Models\System\ModuleLevel;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddMarketingModuleToModulesTable extends Migration
{
    public function up()
    {
        Schema::table('modules', function (Blueprint $table) {

            // Evitar duplicados si ya existe (por si se corre en otro ambiente)
            $exists = Module::where('value', 'marketing')->first();
            if ($exists) {
                return;
            }

            // Crear el módulo Marketing
            $module = new Module();
            $module->value = 'marketing';
            $module->description = 'Marketing';

            $lastModule = Module::orderBy('sort', 'desc')->first();
            $module->sort = $lastModule ? $lastModule->sort + 1 : 1;

            $module->push();

            // Crear los niveles del módulo (submódulos)
            $levels = [
                ['value' => 'chatbot', 'description' => 'ChatBot'],
            ];

            foreach ($levels as $levelData) {
                $level = new ModuleLevel();
                $level->value = $levelData['value'];
                $level->description = $levelData['description'];
                $level->module_id = $module->id;

                if (method_exists($level, 'setLastSortInt')) {
                    $level->setLastSortInt();
                }

                $level->push();
            }
        });
    }

    public function down()
    {
        Schema::table('modules', function (Blueprint $table) {

            $module = Module::where('value', 'marketing')->first();

            if ($module) {
                ModuleLevel::where('module_id', $module->id)->delete();
                $module->delete();
            }
        });
    }
}
