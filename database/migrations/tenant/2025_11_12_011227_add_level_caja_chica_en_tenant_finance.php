<?php

use App\Models\Tenant\Module;
use Illuminate\Database\Migrations\Migration;
use Modules\LevelAccess\Models\ModuleLevel;

class AddLevelCajaChicaEnTenantFinance extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
      $moduleFinance = Module::query()->where('value', 'finance')->first();
      if ($moduleFinance) {
        ModuleLevel::query()->insert([
          'value' => 'finances_small_cash',
          'module_id' => $moduleFinance->id,
          'description' => 'Caja Chica',
        ]);
      }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
      ModuleLevel::query()->where('value', 'small_cash')->delete();
    }
}
