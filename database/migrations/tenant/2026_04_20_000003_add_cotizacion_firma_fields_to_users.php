<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

/**
 * Agrega campos a `users` para que cada ejecutiva tenga su firma personalizada
 * en las cotizaciones del COM-02 (PDF externo al cliente).
 *
 * Campos:
 *  - cotizacion_firma_cargo: cargo visible debajo del nombre en la firma
 *    (ej: "COMERCIAL - MARKETING", "JEFE DE VENTAS").
 *  - cotizacion_firma_celular: celular personal que se muestra arriba del
 *    pie corporativo (los PDFs reales lo incluyen: ej (51) 982 154 908).
 *
 * No se agrega `cotizacion_prefijo_personal` porque, por decisión del cliente
 * del 20-abr-2026, el prefijo NO es por ejecutiva (se configura a nivel
 * general o por cotización individual).
 */
class AddCotizacionFirmaFieldsToUsers extends Migration
{
    public function up()
    {
        Schema::connection('tenant')->table('users', function (Blueprint $table) {
            $table->string('cotizacion_firma_cargo', 100)->nullable()
                ->comment('Cargo a mostrar en la firma del PDF de cotización COM-02');

            $table->string('cotizacion_firma_celular', 30)->nullable()
                ->comment('Celular personal visible en cotizaciones COM-02');
        });
    }

    public function down()
    {
        Schema::connection('tenant')->table('users', function (Blueprint $table) {
            $table->dropColumn(['cotizacion_firma_cargo', 'cotizacion_firma_celular']);
        });
    }
}
