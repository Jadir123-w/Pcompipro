<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Migrations\Migration;

class TenantAddNewTypeUsers extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
      DB::statement("ALTER TABLE users CHANGE type type ENUM('admin', 'seller','integrator', 'client', 'comercial', 'finance', 'logistic') NOT NULL");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
      DB::statement("ALTER TABLE users CHANGE type type ENUM('admin', 'seller', 'integrator', 'client') NOT NULL");
    }
}
