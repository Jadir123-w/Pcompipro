<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCotizacionesRequerimientoTable extends Migration
{
    public function up()
    {
        Schema::create('cotizaciones_requerimiento', function (Blueprint $table) {
            $table->bigIncrements('id');

            // En el .sql original era int(10) UNSIGNED, lo dejamos como unsignedInteger
            $table->unsignedInteger('requerimiento_id');

            $table->string('version', 10)->nullable();
            $table->text('archivo_url')->nullable();
            $table->decimal('monto', 12, 2)->default(0.00);
            $table->enum('estado', [
                'Enviada',
                'Recotizada',
                'Aprobada',
                'Caida',
                'No cotizado',
            ])->default('Enviada');

            $table->text('motivo_caida')->nullable();
            $table->text('observaciones')->nullable();
            $table->unsignedBigInteger('usuario_id')->nullable();

            $table->timestamps();

            $table->index('requerimiento_id', 'cot_req_requerimiento_id_idx');

            // Aquí en el dump original NO había foreign key,
            // por eso no lo definimos para evitar problemas de tipos.
            // Si quieres, más adelante puedes ajustar tipos y agregar FKs.
        });
    }

    public function down()
    {
        Schema::dropIfExists('cotizaciones_requerimiento');
    }
}
