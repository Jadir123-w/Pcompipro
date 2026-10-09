<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class CreatePseProvidersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('pse_providers', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->string('description');
            $table->boolean('active');
        });

        // Insertar datos iniciales
        DB::table('pse_providers')->insert([
            ['id' => 1, 'name' => 'contaweb', 'description' => 'ContaWeb A&M', 'active' => false],
            ['id' => 2, 'name' => 'gior', 'description' => 'Gior Technology', 'active' => true],
            ['id' => 3, 'name' => 'qpse', 'description' => 'QPSE', 'active' => true],
            ['id' => 4, 'name' => 'sendfact', 'description' => 'SendFact', 'active' => true],
            ['id' => 5, 'name' => 'validapse', 'description' => 'Validapse', 'active' => true],
        ]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('pse_providers');
    }
};