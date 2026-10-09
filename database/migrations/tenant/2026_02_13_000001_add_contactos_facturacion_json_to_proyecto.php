<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddContactosFacturacionJsonToProyecto extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasColumn('proyecto', 'contactos_facturacion')) {
            Schema::table('proyecto', function (Blueprint $table) {
                $table->text('contactos_facturacion')->nullable()->after('contacto_facturacion')
                    ->comment('JSON array de contactos de facturación');
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
        if (Schema::hasColumn('proyecto', 'contactos_facturacion')) {
            Schema::table('proyecto', function (Blueprint $table) {
                $table->dropColumn('contactos_facturacion');
            });
        }
    }
}
