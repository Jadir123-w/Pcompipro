<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * COM-07 — Campos del Modelo de Priorización Comercial.
 *
 * Guardan el resultado del cálculo acordado en la Conformidad a las
 * Precisiones: puntajes de Potencial (0-10), Urgencia (0-10 o NULL) y la
 * Prioridad combinada (40/60), más el sustento factor por factor que el panel
 * muestra en el detalle.
 *
 * `urgencia_puntaje` NULL significa "No aplica": la oportunidad no tiene una
 * necesidad actual (sin movimiento). Es distinto de 0, que es una necesidad
 * actual sin datos que sumen — la Especificación exige mantener esa
 * distinción en el indicador.
 *
 * Los NIVELES (Muy Alta, Alta...) no se guardan: se derivan de los puntajes
 * con los umbrales de la Especificación, para que no puedan quedar
 * desincronizados.
 */
class AddPriorizacionToCom07OportunidadesTable extends Migration
{
    public function up()
    {
        Schema::connection('tenant')->table('com07_oportunidades', function (Blueprint $table) {
            $table->unsignedTinyInteger('potencial_puntaje')->nullable()->after('monto_historico');
            $table->unsignedTinyInteger('urgencia_puntaje')->nullable()->after('potencial_puntaje');
            $table->decimal('prioridad_puntaje', 4, 1)->nullable()->after('urgencia_puntaje');
            $table->text('prioridad_sustento')->nullable()->after('prioridad_puntaje');

            // El panel ordena por prioridad en cada carga.
            $table->index('prioridad_puntaje', 'idx_com07_prioridad');
        });
    }

    public function down()
    {
        Schema::connection('tenant')->table('com07_oportunidades', function (Blueprint $table) {
            $table->dropIndex('idx_com07_prioridad');
            $table->dropColumn(['potencial_puntaje', 'urgencia_puntaje', 'prioridad_puntaje', 'prioridad_sustento']);
        });
    }
}
