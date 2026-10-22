<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Bajas con fecha en teléfonos de contactos.
 *
 * - contacto_telefonos.deleted_at  TIMESTAMP NULL: fecha en que el teléfono se dio de baja.
 *   estado = 0 sin fecha = suspendido por inactivar el contacto (se recupera al activarlo).
 * - El índice único (contacto_id, telefono, estado) pasa a índice normal (contacto_id, telefono):
 *   un mismo número de un contacto se reactiva en lugar de duplicarse, y dos bajas del mismo
 *   número ya no chocan.
 * - Bajas antiguas (estado = 0 sin fecha) de teléfonos y correos toman su updated_at como fecha:
 *   antes de este cambio solo existían bajas individuales.
 */
class AddDeletedAtToContactoTelefonos extends Migration
{
    public function up()
    {
        Schema::table('contacto_telefonos', function (Blueprint $table) {
            if (!Schema::hasColumn('contacto_telefonos', 'deleted_at')) {
                $table->timestamp('deleted_at')->nullable()->after('estado');
            }
        });

        if ($this->hasIndex('contacto_telefonos', 'unique_contacto_telefono_activo')) {
            DB::statement('ALTER TABLE contacto_telefonos DROP INDEX unique_contacto_telefono_activo');
        }
        if (!$this->hasIndex('contacto_telefonos', 'idx_contacto_telefonos_contacto_telefono')) {
            DB::statement('ALTER TABLE contacto_telefonos ADD INDEX idx_contacto_telefonos_contacto_telefono (contacto_id, telefono)');
        }

        DB::statement('UPDATE contacto_telefonos SET deleted_at = COALESCE(updated_at, NOW()) WHERE estado = 0 AND deleted_at IS NULL');
        DB::statement('UPDATE contacto_emails SET deleted_at = COALESCE(updated_at, NOW()) WHERE estado = 0 AND deleted_at IS NULL');
    }

    /**
     * Revierte la estructura. El índice único solo se restaura si no hay números repetidos
     * por contacto y estado; las fechas de baja de correos se conservan.
     */
    public function down()
    {
        if ($this->hasIndex('contacto_telefonos', 'idx_contacto_telefonos_contacto_telefono')) {
            DB::statement('ALTER TABLE contacto_telefonos DROP INDEX idx_contacto_telefonos_contacto_telefono');
        }

        $repetidos = DB::table('contacto_telefonos')
            ->select('contacto_id', 'telefono', 'estado')
            ->groupBy('contacto_id', 'telefono', 'estado')
            ->havingRaw('COUNT(*) > 1')
            ->exists();
        if (!$repetidos && !$this->hasIndex('contacto_telefonos', 'unique_contacto_telefono_activo')) {
            DB::statement('ALTER TABLE contacto_telefonos ADD UNIQUE INDEX unique_contacto_telefono_activo (contacto_id, telefono, estado)');
        }

        Schema::table('contacto_telefonos', function (Blueprint $table) {
            if (Schema::hasColumn('contacto_telefonos', 'deleted_at')) {
                $table->dropColumn('deleted_at');
            }
        });
    }

    private function hasIndex($table, $index)
    {
        return !empty(DB::select("SHOW INDEX FROM {$table} WHERE Key_name = ?", [$index]));
    }
}
