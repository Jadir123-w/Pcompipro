<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class NormalizeRucOnLead extends Migration
{
    public function up()
    {
        // Detectar nulabilidad actual de lead.ruc para respetarla
        $col = DB::selectOne("
            SELECT IS_NULLABLE 
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'lead'
              AND COLUMN_NAME = 'ruc'
            LIMIT 1
        ");

        // Sanitizar espacios por si acaso
        DB::statement("UPDATE `lead` SET ruc = TRIM(ruc) WHERE ruc IS NOT NULL");

        // ¡OJO! Si hay rucs > 11, este ALTER fallará. Revisa antes.
        $notNullClause = ($col && strtoupper($col->IS_NULLABLE) === 'NO') ? ' NOT NULL' : ' NULL';
        DB::statement("ALTER TABLE `lead` MODIFY ruc VARCHAR(11){$notNullClause}");
    }

    public function down()
    {
        // Revertir a VARCHAR(14), respetando nulabilidad
        $col = DB::selectOne("
            SELECT IS_NULLABLE 
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'lead'
              AND COLUMN_NAME = 'ruc'
            LIMIT 1
        ");
        $notNullClause = ($col && strtoupper($col->IS_NULLABLE) === 'NO') ? ' NOT NULL' : ' NULL';
        DB::statement("ALTER TABLE `lead` MODIFY ruc VARCHAR(14){$notNullClause}");
    }
}
