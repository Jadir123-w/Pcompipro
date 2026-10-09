<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use App\Models\System\Module;
use App\Models\System\ModuleLevel;
class AddModuleMarketingToModulesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('modules', function (Blueprint $table) {
          // Crear el módulo Comercial
          $module = new Module();
          $module->value = 'marketing';
          $module->description = 'Marketing';

          $lastModule = Module::orderBy('sort', 'desc')->first();
          $module->sort = $lastModule ? $lastModule->sort + 1 : 1;

          $module->push();

          // Crear los niveles del módulo
          $levels = [
            ['value' => 'chatbot', 'description' => 'Chatbot'],
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

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('modules', function (Blueprint $table) {
          // Eliminar el módulo Payroll y sus niveles
          $module = Module::where('value', 'marketing')->first();

          if ($module) {
            // Eliminar niveles
            ModuleLevel::where('module_id', $module->id)->delete();

            // Eliminar módulo
            $module->delete();
          }
        });
    }
}
