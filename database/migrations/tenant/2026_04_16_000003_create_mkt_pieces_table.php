<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateMktPiecesTable extends Migration
{
    public function up()
    {
        Schema::create('mkt_pieces', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedBigInteger('grid_item_id')->nullable()
                ->comment('FK a mkt_grid_items (null si es pieza suelta de extraordinario)');

            $table->unsignedBigInteger('extraordinary_id')->nullable()
                ->comment('FK a mkt_extraordinary_content si es extraordinaria');

            $table->string('red_social', 30)
                ->comment('facebook, instagram, linkedin, tiktok');

            // ── Contenido textual final ──
            $table->text('copy')->nullable()
                ->comment('Texto final listo para publicar');

            $table->string('cta', 255)->nullable()
                ->comment('Call To Action');

            $table->text('hashtags')->nullable()
                ->comment('Hashtags separados por espacios');

            $table->string('formato', 60)->nullable()
                ->comment('post, reel, story, carousel, video_corto, etc.');

            // ── Contenido visual ──
            $table->text('enfoque_visual')->nullable()
                ->comment('Descripción del enfoque visual esperado');

            $table->text('referencia_visual')->nullable()
                ->comment('URLs o descripciones de referencia visual');

            $table->string('imagen_final_url', 500)->nullable()
                ->comment('URL de la imagen final generada por IA o subida manualmente');

            $table->text('imagen_prompt')->nullable()
                ->comment('Prompt usado para generar la imagen con IA');

            $table->string('imagen_source', 30)->default('ai_generated')
                ->comment('ai_generated, uploaded, template');

            // ── Estado ──
            $table->string('estado_pieza', 30)->default('pendiente')
                ->comment('pendiente, en_produccion, lista_publicar, publicada, observada, descartada');

            // ── Control de versiones de pieza ──
            $table->integer('version')->default(1);
            $table->boolean('is_current')->default(true)
                ->comment('Si es la versión vigente de la pieza para ese grid_item+red');

            // ── Fechas de publicación ──
            $table->timestamp('scheduled_at')->nullable()
                ->comment('Fecha/hora programada (Etapa 2)');

            $table->timestamp('published_at')->nullable()
                ->comment('Fecha/hora real de publicación (Etapa 2)');

            $table->string('external_post_id', 255)->nullable()
                ->comment('ID del post en la red social (Etapa 2)');

            // ── Metadata opcional ──
            $table->unsignedInteger('creator_id')->nullable();
            $table->unsignedInteger('approver_id')->nullable();
            $table->text('observaciones')->nullable();

            // ── IA usada ──
            $table->string('ai_text_provider', 30)->nullable();
            $table->string('ai_image_provider', 30)->nullable();
            $table->string('ai_image_model', 60)->nullable();

            $table->timestamps();
            $table->softDeletes();

            // ── Índices ──
            $table->index('grid_item_id');
            $table->index('extraordinary_id');
            $table->index('red_social');
            $table->index('estado_pieza');
            $table->index('scheduled_at');
            $table->index('published_at');
            $table->index('is_current');
        });
    }

    public function down()
    {
        Schema::dropIfExists('mkt_pieces');
    }
}
