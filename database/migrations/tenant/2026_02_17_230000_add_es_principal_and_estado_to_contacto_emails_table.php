<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddEsPrincipalAndEstadoToContactoEmailsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('contacto_emails', function (Blueprint $table) {
            if (!Schema::hasColumn('contacto_emails', 'es_principal')) {
                $table->tinyInteger('es_principal')->default(0);
            }
        });

        Schema::table('contacto_emails', function (Blueprint $table) {
            if (!Schema::hasColumn('contacto_emails', 'estado')) {
                $table->tinyInteger('estado')->default(1)->index();
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
        Schema::table('contacto_emails', function (Blueprint $table) {
            if (Schema::hasColumn('contacto_emails', 'estado')) {
                $table->dropColumn('estado');
            }
            if (Schema::hasColumn('contacto_emails', 'es_principal')) {
                $table->dropColumn('es_principal');
            }
        });
    }
}
