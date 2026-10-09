<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class AddUniqueIndexOnRequerimientoLeadId extends Migration
{
    public function up()
    {
        // 1) Verifica duplicados antes de crear el índice único
        $dups = DB::select("
            SELECT lead_id, COUNT(*) AS c
            FROM requerimiento
            WHERE lead_id IS NOT NULL
            GROUP BY lead_id
            HAVING COUNT(*) > 1
        ");

        if (!empty($dups)) {
            $ejemplos = array_slice(array_map(function($r){
                return "{$r->lead_id} (x{$r->c})";
            }, $dups), 0, 5);
            $msg = "No se puede crear UNIQUE en requerimiento.lead_id: existen duplicados. "
                . "Ejemplos: " . implode(', ', $ejemplos) . ". "
                . "Corrige manualmente y vuelve a ejecutar la migración.";
            throw new \RuntimeException($msg);
        }

        // 2) Crea el índice único (si no existe)
        //    NOTA: algunos motores requieren usar nombre de índice estable
        Schema::table('requerimiento', function (Blueprint $table) {
            // Si ya existiese un índice único previo con otro nombre, primero elimínalo manualmente.
            $table->unique('lead_id', 'uq_requerimiento_lead');
        });
    }

    public function down()
    {
        Schema::table('requerimiento', function (Blueprint $table) {
            // dropUnique acepta el nombre del índice
            $table->dropUnique('uq_requerimiento_lead');
        });
    }
}
