<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateLogConversionLead extends Migration
{
    public function up()
    {
        Schema::create('log_conversion_lead', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->unsignedBigInteger('lead_id');
            $t->unsignedBigInteger('usuario_id');
            $t->dateTime('fecha_conversion');
            $t->unsignedBigInteger('empresa_final_id');
            $t->unsignedBigInteger('contacto_final_id');
            $t->string('tipo_conflicto',40)->nullable(); // sin_conflicto|conflicto_empresa|sin_coincidencia
            $t->string('accion_tomada',40);              // crear_nuevo_contacto|reasignar_contacto|asignar_manual
            $t->text('comentario_usuario')->nullable();

            $t->index('lead_id'); $t->index('usuario_id'); $t->index('empresa_final_id'); $t->index('contacto_final_id');
        });
    }
    public function down() { Schema::dropIfExists('log_conversion_lead'); }
}
