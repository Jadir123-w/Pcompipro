<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTableContactoEmailsTelefonos extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('contacto_emails', function (Blueprint $table) {
          $table->bigIncrements('id');
          $table->unsignedBigInteger('contacto_id');
          $table->foreign('contacto_id')->references('id')->on('contacto');
          $table->string('telefono',50)->nullable();
          $table->string('email',200)->nullable();
          $table->timestamps();
          $table->softDeletes();
        });

        DB::statement('ALTER TABLE contacto_emails ADD FULLTEXT contacto_emails_telefono_index(telefono);');
        DB::statement('ALTER TABLE contacto_emails ADD FULLTEXT contacto_emails_email_index(email);');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
      Schema::dropIfExists('contacto_emails');
    }
}
