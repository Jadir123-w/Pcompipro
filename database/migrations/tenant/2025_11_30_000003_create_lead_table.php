<?php
// Modules/Comercial/Database/Migrations/2025_10_09_000003_create_lead_table.php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateLeadTable extends Migration
{
    public function up()
    {
        Schema::create('lead', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('empresa_id')->nullable()->index();
            $table->string('origen',100);   // p.ej. web, whatsapp, referidos
            $table->string('etapa',100);    // p.ej. nuevo, calificado, en curso
            $table->string('descripcion',500)->nullable();
            $table->string('contacto',191)->nullable();
            $table->string('telefono',50)->nullable();
            $table->string('email',191)->nullable()->index();
            $table->string('prioridad',50)->nullable(); // baja/media/alta
            $table->decimal('monto_estimado',12,2)->nullable();
            $table->date('fecha_estimada')->nullable();
            $table->string('estado',50)->default('activo');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('empresa_id')->references('id')->on('empresa')->onDelete('set null');
        });
    }
    public function down() { Schema::dropIfExists('lead'); }
}
