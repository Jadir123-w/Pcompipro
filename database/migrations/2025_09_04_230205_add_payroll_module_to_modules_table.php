<?php

use App\Models\System\Module;
use App\Models\System\ModuleLevel;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddPayrollModuleToModulesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('modules', function (Blueprint $table) {
            // Crear el módulo Payroll
            $module = new Module();
            $module->value = 'payroll';
            $module->description = 'Planillas';

            $lastModule = Module::orderBy('sort', 'desc')->first();
            $module->sort = $lastModule ? $lastModule->sort + 1 : 1;

            $module->push();

            // Crear los niveles del módulo
            $levels = [
                ['value' => 'employees', 'description' => 'Empleados'],
                ['value' => 'payrolls', 'description' => 'Planillas'],
                ['value' => 'reports', 'description' => 'Reportes'],
                ['value' => 'configuration', 'description' => 'Configuración'],
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
            $module = Module::where('value', 'payroll')->first();

            if ($module) {
                // Eliminar niveles
                ModuleLevel::where('module_id', $module->id)->delete();

                // Eliminar módulo
                $module->delete();
            }
        });
    }
}
