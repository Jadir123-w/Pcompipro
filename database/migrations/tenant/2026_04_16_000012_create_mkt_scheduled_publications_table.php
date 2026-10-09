<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

/**
 * Skeleton para Etapa 2: Publicaciones programadas automáticamente.
 *
 * Esta tabla prepara MKT-03 para evolucionar desde Etapa 1 (producción asistida)
 * hacia Etapa 2 (gestión autónoma), cuando el agente programe y publique
 * automáticamente en las redes sociales vía APIs oficiales.
 *
 * En Etapa 1 la tabla queda vacía. En Etapa 2 se activa un job que:
 *   1. Detecta piezas en estado "lista_publicar"
 *   2. Crea un registro en esta tabla con fecha/hora programada
 *   3. Un scheduler ejecuta las publicaciones a la hora indicada
 *   4. Usa credenciales OAuth por red almacenadas en mkt_social_credentials
 */
class CreateMktScheduledPublicationsTable extends Migration
{
    public function up()
    {
        Schema::create('mkt_scheduled_publications', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedBigInteger('piece_id')
                ->comment('FK a mkt_pieces — la pieza a publicar');

            $table->string('red_social', 30)
                ->comment('Red destino: facebook, instagram, linkedin, tiktok');

            $table->timestamp('scheduled_at')
                ->comment('Fecha/hora programada para publicar');

            $table->string('estado', 30)->default('programada')
                ->comment('programada, en_proceso, publicada, fallida, cancelada');

            $table->timestamp('attempted_at')->nullable()
                ->comment('Último intento de publicación');

            $table->timestamp('published_at')->nullable()
                ->comment('Fecha/hora real de publicación exitosa');

            $table->integer('attempt_count')->default(0)
                ->comment('Número de intentos realizados');

            $table->string('external_post_id', 255)->nullable()
                ->comment('ID del post en la red (después de publicar)');

            $table->string('external_post_url', 500)->nullable()
                ->comment('URL pública del post');

            $table->text('last_error')->nullable()
                ->comment('Último mensaje de error si la publicación falló');

            $table->timestamps();

            // ── Índices ──
            $table->index('piece_id');
            $table->index('red_social');
            $table->index('estado');
            $table->index('scheduled_at');
        });

        // ── Tabla de credenciales OAuth (skeleton Etapa 2) ──
        Schema::create('mkt_social_credentials', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->string('red_social', 30)->unique()
                ->comment('facebook, instagram, linkedin, tiktok');

            // ── Credenciales (encriptadas en la aplicación) ──
            $table->text('access_token')->nullable();
            $table->text('refresh_token')->nullable();
            $table->timestamp('token_expires_at')->nullable();

            // ── Identidad ──
            $table->string('account_id', 100)->nullable()
                ->comment('ID de la cuenta/página en la red');
            $table->string('account_name', 255)->nullable()
                ->comment('Nombre visible de la cuenta');

            // ── Meta-info ──
            $table->json('scopes')->nullable()
                ->comment('Permisos OAuth otorgados');
            $table->json('extra_meta')->nullable()
                ->comment('Metadata específica por red (ej: page_id en Facebook)');

            $table->boolean('active')->default(false);

            $table->timestamps();

            // ── Índices ──
            $table->index('active');
        });
    }

    public function down()
    {
        Schema::dropIfExists('mkt_scheduled_publications');
        Schema::dropIfExists('mkt_social_credentials');
    }
}
