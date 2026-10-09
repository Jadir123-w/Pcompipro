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
     * Corrige fechas inválidas '0000-00-00' en la tabla empresa
     * que causan errores en MySQL strict mode al agregar nuevas columnas.
     *
     * @return void
     */
    public function up()
    {
        // Solo ejecutar si la tabla existe
        if (!Schema::hasTable('empresa')) {
            return;
        }

        // Desactivar temporalmente el modo estricto de SQL para permitir la actualización
        DB::statement("SET SESSION sql_mode = ''");

        // Lista de columnas de tipo fecha que podrían tener valores inválidos
        $dateColumns = ['aniversario', 'fecha_nacimiento', 'fecha_constitucion', 'fecha_registro'];

        foreach ($dateColumns as $column) {
            if (Schema::hasColumn('empresa', $column)) {
                DB::statement("UPDATE empresa SET {$column} = NULL WHERE {$column} = '0000-00-00' OR {$column} = '0000-00-00 00:00:00'");
            }
        }

        // Corregir created_at si tiene valores inválidos
        if (Schema::hasColumn('empresa', 'created_at')) {
            DB::statement("UPDATE empresa SET created_at = NOW() WHERE created_at = '0000-00-00 00:00:00' OR created_at IS NULL");
        }

        // Corregir updated_at si tiene valores inválidos
        if (Schema::hasColumn('empresa', 'updated_at')) {
            DB::statement("UPDATE empresa SET updated_at = NOW() WHERE updated_at = '0000-00-00 00:00:00' OR updated_at IS NULL");
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
