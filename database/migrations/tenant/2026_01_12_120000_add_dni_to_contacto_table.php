<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddDniToContactoTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasColumn('contacto', 'dni')) {
            Schema::table('contacto', function (Blueprint $table) {
                $table->string('dni', 20)->nullable()->after('apellidos')->comment('DNI/documento de identidad del contacto');
                $table->index('dni', 'idx_contacto_dni');
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('contacto', function (Blueprint $table) {
            if (Schema::hasColumn('contacto', 'dni')) {
                $table->dropIndex('idx_contacto_dni');
                $table->dropColumn('dni');
            }
        });
    }
}
