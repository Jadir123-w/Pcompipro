<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class TenantChangeDescriptionGretToCatDocumentTypes extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::connection('tenant')->table('cat_document_types')->where('id', '31')
            ->update(['description' => 'GUÍA DE REMISIÓN TRANSPORTISTA']);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::connection('tenant')->table('cat_document_types')->where('id', '31')
            ->update(['description' => 'Guía de remisión transportista']);
    }
}
