<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateChatbotSessionsTable extends Migration
{
    public function up()
    {
        Schema::create('chatbot_sessions', function (Blueprint $table) {
            $table->bigIncrements('id');

            // ── Identificación ──
            $table->string('session_id', 64)->unique()
                ->comment('UUID v4 generado por el widget o por WhatsApp service');

            $table->string('channel', 20)->default('web')
                ->comment('web / whatsapp / correo');

            $table->string('channel_identifier', 120)->nullable()
                ->comment('Número de WhatsApp del usuario o null para web');

            // ── Estado ──
            $table->enum('status', ['active', 'closed', 'timeout', 'escalated'])
                ->default('active');

            $table->string('closed_reason', 60)->nullable()
                ->comment('user_closed, timeout_inactivity, lead_registered, escalated_to_human, no_intent');

            // ── Contexto de origen ──
            $table->string('page_url', 500)->nullable()
                ->comment('URL de la página donde se abrió el chat');

            $table->string('utm_source', 100)->nullable();
            $table->string('utm_medium', 100)->nullable();
            $table->string('utm_campaign', 100)->nullable();

            // ── Relación con lead ──
            $table->unsignedBigInteger('lead_id')->nullable()
                ->comment('FK al lead creado desde esta sesión (si se creó)');

            // ── Timestamps ──
            $table->timestamp('started_at')->useCurrent();
            $table->timestamp('last_activity_at')->useCurrent();
            $table->timestamp('closed_at')->nullable();

            $table->timestamps(); // created_at, updated_at de Laravel

            // ── Índices ──
            $table->index('channel');
            $table->index('status');
            $table->index('channel_identifier');
            $table->index('lead_id');
            $table->index('last_activity_at');
        });
    }

    public function down()
    {
        Schema::dropIfExists('chatbot_sessions');
    }
}
