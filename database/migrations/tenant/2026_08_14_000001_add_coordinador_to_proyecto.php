<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Coordinador del proyecto.
 *
 * Pedido del cliente (punto 17 de agosto 2026): el equipo necesita registrar
 * quién coordina cada proyecto, para identificar rápido al responsable del
 * seguimiento.
 *
 * Se guarda como texto libre y no como referencia a `users`: el coordinador
 * puede ser alguien que no tiene usuario en el sistema, y el pedido habla de
 * "colocar el nombre". Mismo criterio que ya se usó para el responsable de
 * recepción de mercancía en la pestaña Despacho.
 *
 * Idempotente: solo agrega la columna si falta.
 */
class AddCoordinadorToProyecto extends Migration
{
    public function up()
    {
        if (!Schema::connection('tenant')->hasTable('proyecto')) {
            return;
        }

        if (Schema::connection('tenant')->hasColumn('proyecto', 'coordinador')) {
            return;
        }

        Schema::connection('tenant')->table('proyecto', function (Blueprint $table) {
            $table->string('coordinador', 191)
                  ->nullable()
                  ->after('nombre')
                  ->comment('Nombre del coordinador a cargo del proyecto');
        });
    }

    public function down()
    {
        if (!Schema::connection('tenant')->hasTable('proyecto')) {
            return;
        }

        if (Schema::connection('tenant')->hasColumn('proyecto', 'coordinador')) {
            Schema::connection('tenant')->table('proyecto', function (Blueprint $table) {
                $table->dropColumn('coordinador');
            });
        }
    }
}
