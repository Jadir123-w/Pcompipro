<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ajustes a las tablas `proyecto` y `proyecto_razones_sociales`
 * para soportar la nueva lógica de facturación (2026-04-20):
 *
 * - `proyecto.dias_credito` INT NULL → cuando la forma de pago es
 *   "50% adelanto 50% crédito", el usuario debe indicar días de crédito.
 *
 * - `proyecto_razones_sociales.tiene_oc` cambia de TINYINT a VARCHAR(20)
 *   para soportar los 3 estados: "Pendiente" | "No Aplica" | "Se tiene".
 *
 * - `proyecto_razones_sociales.oc_archivo` VARCHAR(255) → ruta relativa del PDF
 *   cargado (almacenamiento en storage/app/...).
 *
 * - `proyecto_razones_sociales.oc_archivo_original` VARCHAR(191) → nombre original
 *   del archivo subido (para descargar con nombre legible).
 */
class AddBillingFieldsToProyecto extends Migration
{
    public function up()
    {
        Schema::table('proyecto', function (Blueprint $table) {
            if (!Schema::hasColumn('proyecto', 'dias_credito')) {
                $table->integer('dias_credito')->nullable()->after('forma_pago');
            }
        });

        // tiene_oc: TINYINT → VARCHAR(20). Migrar valores numéricos a texto.
        if (Schema::hasColumn('proyecto_razones_sociales', 'tiene_oc')) {
            // 1 (checked) -> 'Se tiene', 0 (unchecked) -> 'Pendiente'
            DB::statement("ALTER TABLE proyecto_razones_sociales MODIFY COLUMN tiene_oc VARCHAR(20) NOT NULL DEFAULT 'Pendiente'");
            DB::statement("UPDATE proyecto_razones_sociales SET tiene_oc = CASE WHEN tiene_oc = '1' THEN 'Se tiene' WHEN tiene_oc = '0' THEN 'Pendiente' ELSE tiene_oc END");
        }

        Schema::table('proyecto_razones_sociales', function (Blueprint $table) {
            if (!Schema::hasColumn('proyecto_razones_sociales', 'oc_archivo')) {
                $table->string('oc_archivo', 255)->nullable()->after('oc_numero');
            }
            if (!Schema::hasColumn('proyecto_razones_sociales', 'oc_archivo_original')) {
                $table->string('oc_archivo_original', 191)->nullable()->after('oc_archivo');
            }
        });
    }

    public function down()
    {
        Schema::table('proyecto', function (Blueprint $table) {
            if (Schema::hasColumn('proyecto', 'dias_credito')) {
                $table->dropColumn('dias_credito');
            }
        });

        Schema::table('proyecto_razones_sociales', function (Blueprint $table) {
            if (Schema::hasColumn('proyecto_razones_sociales', 'oc_archivo_original')) {
                $table->dropColumn('oc_archivo_original');
            }
            if (Schema::hasColumn('proyecto_razones_sociales', 'oc_archivo')) {
                $table->dropColumn('oc_archivo');
            }
        });

        // tiene_oc: revertir a TINYINT (1 si 'Se tiene', 0 en otro caso)
        if (Schema::hasColumn('proyecto_razones_sociales', 'tiene_oc')) {
            DB::statement("UPDATE proyecto_razones_sociales SET tiene_oc = CASE WHEN tiene_oc = 'Se tiene' THEN '1' ELSE '0' END");
            DB::statement("ALTER TABLE proyecto_razones_sociales MODIFY COLUMN tiene_oc TINYINT(4) NOT NULL DEFAULT 0");
        }
    }
}
