<?php
// Modules/Comercial/Database/Migrations/2025_10_09_000004_create_requerimiento_table.php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateRequerimientoTable extends Migration
{
    public function up()
    {
        Schema::create('requerimiento', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('empresa_id')->index();
            $table->unsignedInteger('lead_id')->nullable()->index();
            $table->string('titulo',191);
            $table->string('descripcion',1000)->nullable();
            $table->decimal('monto_estimado',12,2)->nullable();
            $table->date('fecha_requerida')->nullable();
            $table->string('etapa',50)->nullable();   // análisis, cotización, aprobado
            $table->string('estado',50)->default('abierto');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('empresa_id')->references('id')->on('empresa')->onDelete('cascade');
            $table->foreign('lead_id')->references('id')->on('lead')->onDelete('set null');
        });
    }
    public function down() { Schema::dropIfExists('requerimiento'); }
}
