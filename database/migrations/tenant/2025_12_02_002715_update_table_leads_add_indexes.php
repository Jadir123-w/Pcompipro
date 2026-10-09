<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class UpdateTableLeadsAddIndexes extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
      DB::statement('ALTER TABLE lead ADD FULLTEXT lead_origen_index(origen);');
      DB::statement('ALTER TABLE lead ADD FULLTEXT lead_etapa_index(etapa);');
      DB::statement('ALTER TABLE lead ADD FULLTEXT lead_contacto_index(contacto);');
      DB::statement('ALTER TABLE lead ADD FULLTEXT lead_prioridad_index(prioridad);');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('lead', function (Blueprint $table) {
          $table->dropIndex('lead_origen_index');
          $table->dropIndex('lead_etapa_index');
          $table->dropIndex('lead_contacto_index');
          $table->dropIndex('lead_prioridad_index');
        });
    }
}
