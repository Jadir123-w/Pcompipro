<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateMktExtraordinaryContentTable extends Migration
{
    public function up()
    {
        Schema::create('mkt_extraordinary_content', function (Blueprint $table) {
            $table->bigIncrements('id');

            // ── Datos básicos ──
            $table->date('fecha')
                ->comment('Fecha de publicación objetivo');

            $table->string('motivo', 255)
                ->comment('Motivo de la pieza extraordinaria');

            $table->string('red_social', 30)
                ->comment('Red social destino');

            $table->text('descripcion')->nullable()
                ->comment('Descripción de la solicitud');

            $table->string('clasificacion', 60)->nullable()
                ->comment('urgente, oportunidad, reactivo, promocional, etc.');

            // ── Decisión sobre uso futuro ──
            $table->string('estado', 40)->default('solicitado')
                ->comment('solicitado, en_desarrollo, entregado, aprobado_para_futuras, no_aprobado');

            $table->boolean('aprobado_para_futuras_grillas')->nullable()
                ->comment('Decisión del usuario sobre si esta pieza debe incorporarse como patrón futuro');

            $table->text('motivo_decision')->nullable()
                ->comment('Razón de la decisión sobre uso futuro');

            // ── Usuarios ──
            $table->unsignedInteger('solicitante_id')->nullable();
            $table->unsignedInteger('decisor_id')->nullable();
            $table->timestamp('fecha_decision')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // ── Índices ──
            $table->index('fecha');
            $table->index('red_social');
            $table->index('estado');
            $table->index('aprobado_para_futuras_grillas');
        });
    }

    public function down()
    {
        Schema::dropIfExists('mkt_extraordinary_content');
    }
}
