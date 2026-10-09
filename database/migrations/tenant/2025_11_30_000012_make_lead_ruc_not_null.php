<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class MakeLeadRucNotNull extends Migration
{
    public function up()
    {
        // Verifica que no existan NULL antes de forzar NOT NULL
        $nulls = DB::selectOne("SELECT COUNT(*) AS c FROM `lead` WHERE ruc IS NULL");
        if (($nulls->c ?? 0) > 0) {
            // si hay nulos, decide: o corrige manualmente o ponlos a '' para no romper.
            // Aquí optamos por corregir fallando explícitamente:
            throw new \RuntimeException("No se puede poner NOT NULL: existen {$nulls->c} filas con ruc NULL en `lead`.");
        }

        DB::statement("ALTER TABLE `lead` MODIFY ruc VARCHAR(11) NOT NULL");
    }

    public function down()
    {
        DB::statement("ALTER TABLE `lead` MODIFY ruc VARCHAR(11) NULL");
    }
}
