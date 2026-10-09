<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class FixInvalidDatesInEmpresa extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Desactivar temporalmente el modo estricto de SQL para permitir la actualización
        DB::statement("SET SESSION sql_mode = ''");

        // Corregir fechas inválidas '0000-00-00' a NULL en la tabla empresa
        if (Schema::hasTable('empresa')) {
            // Lista de columnas de tipo fecha que podrían tener valores inválidos
            $dateColumns = ['aniversario', 'fecha_constitucion', 'fecha_registro'];

            foreach ($dateColumns as $column) {
                if (Schema::hasColumn('empresa', $column)) {
                    DB::table('empresa')
                        ->where($column, '0000-00-00')
                        ->update([$column => null]);

                    // También corregir datetime inválidos
                    DB::table('empresa')
                        ->where($column, '0000-00-00 00:00:00')
                        ->update([$column => null]);
                }
            }
        }

        // Restaurar el modo SQL
        DB::statement("SET SESSION sql_mode = 'STRICT_TRANS_TABLES,NO_ENGINE_SUBSTITUTION'");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // No se revierte - los valores NULL son válidos
        // Revertir a '0000-00-00' causaría el mismo problema
    }
}
