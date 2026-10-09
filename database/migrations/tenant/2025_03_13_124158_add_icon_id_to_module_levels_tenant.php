<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddIconIdToModuleLevelsTenant extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('module_levels', function (Blueprint $table) {
            $table->string('icon_id')->nullable()->after('label_menu');
        });

        $iconIds = [
            'new_document' => 'icon_001',
            'pos' => 'icon_002',
            'configuration_company' => 'icon_003',
            'users_establishments' => 'icon_004',
            'sale_notes' => 'icon_005',
            'order-note' => 'icon_006',
            'items' => 'icon_007',
            'inventory' => 'icon_008',
            'users' => 'icon_009',
            'purchases_create' => 'icon_010',
        ];

        foreach ($iconIds as $value => $iconId) {
            DB::table('module_levels')
                ->where('value', $value)
                ->update(['icon_id' => $iconId]);
        }
    }


    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('module_levels', function (Blueprint $table) {
            $table->dropColumn('icon_id');
        });
    }
}
