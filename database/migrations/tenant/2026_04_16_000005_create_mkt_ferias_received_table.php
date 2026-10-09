<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateMktFeriasReceivedTable extends Migration
{
    public function up()
    {
        Schema::create('mkt_ferias_received', function (Blueprint $table) {
            $table->bigIncrements('id');

            // ── Datos del evento (del agente de ferias) ──
            $table->string('nombre', 255)
                ->comment('Nombre de la feria');

            $table->date('fecha')
                ->comment('Fecha de la feria');

            $table->string('sector', 150)->nullable()
                ->comment('Sector o temática del evento');

            $table->string('ciudad', 100)->nullable();

            $table->string('tipo_participacion', 100)->nullable()
                ->comment('Ej: stand, invitado, visitante, ponente, sponsor');

            $table->text('objetivo_comunicacional')->nullable()
                ->comment('Objetivo comunicacional para COMPIPRO');

            $table->text('enfoque_sugerido')->nullable()
                ->comment('Enfoque sugerido por el agente de ferias');

            $table->text('contexto_interno')->nullable()
                ->comment('Contexto estratégico interno (NO se publica automáticamente)');

            // ── Fase editorial activa ──
            $table->string('fase_editorial_actual', 20)->nullable()
                ->comment('2m, 1m, 15d, completada');

            // ── Generación de piezas ──
            $table->boolean('piezas_2m_generadas')->default(false);
            $table->boolean('piezas_1m_generadas')->default(false);
            $table->boolean('piezas_15d_generadas')->default(false);

            // ── Fuente (skeleton) ──
            $table->string('source', 30)->default('manual')
                ->comment('manual, agente_ferias (cuando exista)');

            $table->string('external_feria_id', 100)->nullable()
                ->comment('ID externo del agente de ferias (futuro)');

            // ── Redes sociales a cubrir ──
            $table->json('redes_sociales_target')->nullable()
                ->comment('Lista de redes donde se generará contenido');

            $table->string('estado', 30)->default('activa')
                ->comment('activa, en_cobertura, concluida, cancelada');

            $table->timestamps();
            $table->softDeletes();

            // ── Índices ──
            $table->index('fecha');
            $table->index('estado');
            $table->index('source');
            $table->index('external_feria_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('mkt_ferias_received');
    }
}
