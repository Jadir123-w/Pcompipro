<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateMassiveInvoicesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
       Schema::create('massive_invoices', function (Blueprint $table) {
            $table->increments('id');
            $table->date('fecha_emision');
            $table->date('fecha_vencimiento')->nullable();
            $table->string('tipo_comprobante');
            $table->string('serie_comprobante');
            $table->string('ruc', 20);
            $table->string('correo')->nullable();
            $table->string('moneda', 3);
            $table->string('forma_pago');
            $table->text('observacion')->nullable();
            $table->string('orden_compra')->nullable();
            $table->boolean('incluye_igv')->default(true);
            $table->boolean('incluye_detraccion')->default(false);
            $table->decimal('porcentaje_detraccion', 10, 2)->nullable();
            $table->string('servicio_detraccion')->nullable();
            $table->string('item');
            $table->string('descripcion_producto');
            $table->decimal('cantidad', 12, 2);
            $table->string('unidad_medida');
            $table->string('tipo_afectacion');
            $table->decimal('precio', 12, 2);
            $table->string('status')->default('PENDIENTE');
            $table->text('nota')->nullable();
            $table->string('external_id')->nullable();
            $table->text('pdf_link')->nullable();
            $table->text('xml_link')->nullable();
            $table->text('cdr_link')->nullable();
            $table->string('estado_sunat')->nullable();
            $table->text('mensaje_sunat')->nullable();
            $table->decimal('total_gravado', 12, 2)->default(0);
            $table->decimal('total_igv', 12, 2)->default(0);
            $table->decimal('total_venta', 12, 2)->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('massive_invoices');
    }
}
