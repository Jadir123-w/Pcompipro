<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateMktGridItemsTable extends Migration
{
    public function up()
    {
        Schema::create('mkt_grid_items', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedBigInteger('grid_id')
                ->comment('FK a mkt_grids');

            // ── Planificación ──
            $table->date('fecha_publicacion')
                ->comment('Fecha planificada de publicación');

            $table->string('red_social', 30)
                ->comment('facebook, instagram, linkedin, tiktok');

            // ── Contenido temático ──
            $table->string('tematica', 100)->nullable()
                ->comment('Tema central de la publicación');

            $table->string('campania', 100)->nullable()
                ->comment('Campaña asociada (ej: Black Friday, Día del Padre)');

            $table->string('temporada', 50)->nullable()
                ->comment('Temporada editorial');

            $table->string('evento', 100)->nullable()
                ->comment('Evento o efeméride');

            $table->string('objetivo', 100)->nullable()
                ->comment('visibilidad, posicionamiento, recordacion, campania, conversion, etc.');

            $table->string('linea_comercial', 100)->nullable()
                ->comment('Línea o producto de COMPIPRO asociado');

            $table->string('tipo_contenido', 60)->nullable()
                ->comment('post, reel, story, video, carousel, etc.');

            $table->string('prioridad', 20)->default('media')
                ->comment('alta, media, baja');

            $table->string('origen', 50)->default('grilla')
                ->comment('grilla, extraordinario, feria, puntual');

            // ── Si viene de fuentes externas ──
            $table->unsignedBigInteger('feria_id')->nullable()
                ->comment('FK a mkt_ferias_received si el ítem proviene de una feria');

            $table->unsignedBigInteger('extraordinary_id')->nullable()
                ->comment('FK a mkt_extraordinary_content si proviene de extraordinario');

            $table->string('fase_feria', 20)->nullable()
                ->comment('Para ítems de feria: 2m, 1m, 15d');

            // ── Orden visual ──
            $table->integer('item_order')->default(0);

            // ── Observaciones ──
            $table->text('observaciones')->nullable();

            $table->timestamps();

            // ── Índices ──
            $table->index('grid_id');
            $table->index('fecha_publicacion');
            $table->index('red_social');
            $table->index('tematica');
            $table->index('campania');
            $table->index('origen');
            $table->index('feria_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('mkt_grid_items');
    }
}
