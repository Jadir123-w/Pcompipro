<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateMktHistoricalGridsTable extends Migration
{
    public function up()
    {
        Schema::create('mkt_historical_grids', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->integer('anio')
                ->comment('Año al que pertenece el histórico (2023, 2024, 2025, etc.)');

            $table->string('nombre', 255)
                ->comment('Nombre descriptivo del archivo histórico');

            $table->string('archivo_original', 500)->nullable()
                ->comment('Nombre del archivo Excel original');

            $table->string('archivo_storage_path', 500)->nullable()
                ->comment('Ruta de almacenamiento del archivo');

            // ── Datos interpretados ──
            $table->json('estructura_detectada')->nullable()
                ->comment('JSON: columnas y mapeo detectado del Excel');

            $table->json('contenido_normalizado')->nullable()
                ->comment('JSON: filas normalizadas del histórico');

            $table->integer('total_items')->default(0)
                ->comment('Cantidad total de ítems extraídos');

            // ── Redes detectadas ──
            $table->json('redes_detectadas')->nullable()
                ->comment('Lista de redes sociales detectadas en el histórico');

            // ── Estado ──
            $table->string('estado', 30)->default('importado')
                ->comment('importado, analizado, error');

            $table->text('error_message')->nullable();

            // ── Usuario que importó ──
            $table->unsignedInteger('user_id')->nullable();

            $table->timestamps();

            // ── Índices ──
            $table->index('anio');
            $table->index('estado');
            $table->index('user_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('mkt_historical_grids');
    }
}
