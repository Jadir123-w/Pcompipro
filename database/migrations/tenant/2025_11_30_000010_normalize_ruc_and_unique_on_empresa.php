<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class NormalizeRucAndUniqueOnEmpresa extends Migration
{
    public function up()
    {
        // 1) Quitar índice duplicado si existe
        $idxDup = DB::selectOne("
            SELECT INDEX_NAME 
            FROM information_schema.STATISTICS 
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'empresa'
              AND INDEX_NAME = 'uq_empresa_ruc'
            LIMIT 1
        ");
        if ($idxDup) {
            DB::statement("ALTER TABLE empresa DROP INDEX `uq_empresa_ruc`");
        }

        // 2) Detectar nulabilidad actual para respetarla
        $col = DB::selectOne("
            SELECT IS_NULLABLE 
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'empresa'
              AND COLUMN_NAME = 'ruc'
            LIMIT 1
        ");

        // Sanitizar espacios por si acaso
        DB::statement("UPDATE empresa SET ruc = TRIM(ruc) WHERE ruc IS NOT NULL");

        // ¡OJO! Si hay rucs con >11 caracteres, este ALTER fallará.
        // Verifica antes con:
        // SELECT id, ruc FROM empresa WHERE ruc IS NOT NULL AND CHAR_LENGTH(ruc) > 11;
        $notNullClause = ($col && strtoupper($col->IS_NULLABLE) === 'NO') ? ' NOT NULL' : ' NULL';
        DB::statement("ALTER TABLE empresa MODIFY ruc VARCHAR(11){$notNullClause}");

        // 3) Asegurar índice único estándar si no existe
        $idxMain = DB::selectOne("
            SELECT INDEX_NAME 
            FROM information_schema.STATISTICS 
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'empresa'
              AND INDEX_NAME = 'empresa_ruc_unique'
            LIMIT 1
        ");
        if (!$idxMain) {
            DB::statement("ALTER TABLE empresa ADD UNIQUE KEY `empresa_ruc_unique` (`ruc`)");
        }
    }

    public function down()
    {
        // Revertir longitud a 14 (respetando nulabilidad)
        $col = DB::selectOne("
            SELECT IS_NULLABLE 
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'empresa'
              AND COLUMN_NAME = 'ruc'
            LIMIT 1
        ");
        $notNullClause = ($col && strtoupper($col->IS_NULLABLE) === 'NO') ? ' NOT NULL' : ' NULL';
        DB::statement("ALTER TABLE empresa MODIFY ruc VARCHAR(14){$notNullClause}");

        // Quitar índice estándar si existe
        $idxMain = DB::selectOne("
            SELECT INDEX_NAME 
            FROM information_schema.STATISTICS 
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'empresa'
              AND INDEX_NAME = 'empresa_ruc_unique'
            LIMIT 1
        ");
        if ($idxMain) {
            DB::statement("ALTER TABLE empresa DROP INDEX `empresa_ruc_unique`");
        }

        // (Opcional) Restaurar el índice anterior
        // DB::statement("ALTER TABLE empresa ADD UNIQUE KEY `uq_empresa_ruc` (`ruc`)");
    }
}
