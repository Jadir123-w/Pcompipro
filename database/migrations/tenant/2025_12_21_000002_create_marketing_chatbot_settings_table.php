<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMarketingChatbotSettingsTable extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('marketing_chatbot_settings')) {
            Schema::create('marketing_chatbot_settings', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->tinyInteger('enabled')->default(1);
                $table->string('greeting', 255)->nullable();
                $table->string('farewell', 255)->nullable();
                $table->text('prompt')->nullable();
                $table->timestamps();
            });

            \Illuminate\Support\Facades\DB::table('marketing_chatbot_settings')->insert([
                'id' => 1,
                'enabled' => 1,
                'greeting' => 'Hola! Soy el asistente de COMPIPRO. Te hare unas preguntas rapidas para ayudarte.',
                'farewell' => 'Gracias! Muy pronto un ejecutivo se pondra en contacto con Ud.',
                'prompt' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down()
    {
        Schema::dropIfExists('marketing_chatbot_settings');
    }
}
