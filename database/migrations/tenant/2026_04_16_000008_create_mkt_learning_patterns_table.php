<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateMktLearningPatternsTable extends Migration
{
    public function up()
    {
        Schema::create('mkt_learning_patterns', function (Blueprint $table) {
            $table->bigIncrements('id');

            // ── Origen del patrón ──
            $table->string('fuente', 30)->default('historico')
                ->comment('historico, extraordinario_aprobado, manual');

            $table->unsignedBigInteger('historical_grid_id')->nullable()
                ->comment('FK a mkt_historical_grids si proviene de histórico');

            $table->unsignedBigInteger('extraordinary_id')->nullable()
                ->comment('FK a mkt_extraordinary_content si proviene de extraordinario aprobado');

            // ── Clasificación del patrón ──
            $table->string('tipo_patron', 60)
                ->comment('frecuencia, temporada, campania, distribucion_red, tono, tipo_pieza, ritmo, festividad, comunicacion_recurrente');

            $table->string('red_social', 30)->nullable()
                ->comment('null si aplica a todas las redes');

            $table->string('titulo', 255)
                ->comment('Nombre descriptivo del patrón');

            $table->text('descripcion')->nullable()
                ->comment('Descripción detallada del patrón detectado');

            // ── Datos del patrón ──
            $table->json('datos_patron')->nullable()
                ->comment('JSON: datos específicos del patrón (fechas, frecuencia, etc.)');

            $table->integer('ocurrencias')->default(1)
                ->comment('Cantidad de veces que aparece este patrón en el histórico');

            $table->decimal('confianza', 5, 2)->default(0)
                ->comment('Nivel de confianza (0-100) del patrón detectado');

            // ── Estado ──
            $table->boolean('active')->default(true)
                ->comment('Si el patrón está activo para usar en nuevas grillas');

            $table->boolean('aprobado_por_usuario')->default(false)
                ->comment('Si el usuario aprobó explícitamente este patrón');

            $table->timestamps();

            // ── Índices ──
            $table->index('fuente');
            $table->index('tipo_patron');
            $table->index('red_social');
            $table->index('active');
            $table->index('historical_grid_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('mkt_learning_patterns');
    }
}
