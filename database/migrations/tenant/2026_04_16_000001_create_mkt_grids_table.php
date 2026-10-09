<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateMktGridsTable extends Migration
{
    public function up()
    {
        Schema::create('mkt_grids', function (Blueprint $table) {
            $table->bigIncrements('id');

            // ── Identificación del periodo ──
            $table->integer('anio')
                ->comment('Año al que pertenece la grilla');

            $table->string('periodo', 50)
                ->comment('Periodo: 2026, 2026-Q1, 2026-01, 2026-S1, campaña-X, etc.');

            $table->string('tipo_periodo', 30)
                ->comment('Tipos: anual, trimestral, semestral, mensual, campania, fecha');

            // ── Red social ──
            $table->string('red_social', 30)->nullable()
                ->comment('Red social específica: facebook, instagram, linkedin, tiktok, multired (FB+IG compartida), todas');

            // ── Versionado ──
            $table->integer('version')->default(1)
                ->comment('Versión de la grilla');

            // ── Estado ──
            $table->string('estado', 30)->default('borrador')
                ->comment('Estados: borrador, en_revision, validada, activa, cerrada');

            // ── Metadatos ──
            $table->string('titulo', 255)->nullable();
            $table->text('descripcion')->nullable();

            $table->unsignedBigInteger('fuente_base_historica_id')->nullable()
                ->comment('FK a mkt_historical_grids si la grilla se basó en una histórica');

            // ── Metadata opcional operativa ──
            $table->unsignedInteger('revisor_id')->nullable();
            $table->unsignedInteger('aprobador_id')->nullable();
            $table->string('estado_aprobacion', 30)->nullable();
            $table->timestamp('fecha_aprobacion')->nullable();
            $table->text('observaciones_operativas')->nullable();

            // ── Creador ──
            $table->unsignedInteger('user_id')->nullable();

            // ── IA usada ──
            $table->string('ai_provider', 30)->nullable();
            $table->string('ai_model', 60)->nullable();

            $table->timestamps();
            $table->softDeletes();

            // ── Índices ──
            $table->index('anio');
            $table->index('periodo');
            $table->index('tipo_periodo');
            $table->index('red_social');
            $table->index('estado');
            $table->index('user_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('mkt_grids');
    }
}
