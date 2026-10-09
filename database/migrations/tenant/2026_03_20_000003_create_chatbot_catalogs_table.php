<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateChatbotCatalogsTable extends Migration
{
    public function up()
    {
        Schema::create('chatbot_catalogs', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->string('line_name', 100)
                ->comment('Nombre de la línea: Textil, Impresos, Ecológica, Packs, etc.');

            $table->string('catalog_url', 500)
                ->comment('URL del catálogo PDF o página web');

            $table->boolean('is_general')->default(false)
                ->comment('true = catálogo general; false = catálogo de línea específica');

            $table->boolean('active')->default(true);

            $table->integer('sort_order')->default(0)
                ->comment('Orden para mostrar al agente');

            $table->timestamps();

            // ── Índices ──
            $table->index('active');
            $table->index('is_general');
        });
    }

    public function down()
    {
        Schema::dropIfExists('chatbot_catalogs');
    }
}
