<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class AddIntroductionToUsersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('introduction')->nullable()->after('whatsapp_number');
        });

        DB::table('users')->update([
            'introduction' => '<p><strong>¿Cómo podemos ayudarte?</strong></p>' .
                             '<p>Nuestro equipo de soporte técnico está listo para resolver todas tus dudas sobre facturación electrónica, configuración del sistema y más.</p>' .
                             '<p><strong>⏰Horarios de Atención</strong></p>' .
                             '<p><strong>Lunes a Viernes:</strong> 9:00 AM - 6:00 PM</p>' .
                             '<p><strong>Sábados:</strong> 9:00 AM - 1:00 PM</p>'
        ]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('introduction');
        });
    }
}
