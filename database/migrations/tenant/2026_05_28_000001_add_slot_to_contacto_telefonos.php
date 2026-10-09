<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega columna `slot` a `contacto_telefonos` para identificar el rol del
 * teléfono dentro del contacto y resolver la ambigüedad del UI:
 *
 *   - 'celular_principal' → campo "Celular principal" del form
 *   - 'fijo'              → campo "Teléfono fijo" del form
 *   - 'adicional'         → entran en la sección "Teléfonos adicionales"
 *
 * Antes de este cambio, el form inferia el slot por posición:
 *   - celular_principal = el único con es_principal=1
 *   - fijo              = el primer no-principal por id (introducido en PR #249)
 *   - adicionales       = el resto
 *
 * Esa inferencia se rompía cuando el usuario eliminaba el celular principal:
 * el backend auto-promovía el primer no-principal a principal y todo "se
 * corría un slot" en el reload, confundiendo al usuario.
 *
 * Con la columna `slot` cada teléfono lleva su rol explícito y nada se mueve.
 * El campo `es_principal` se mantiene (lo lee el listado de contactos para
 * mostrar el celular en la columna del grid) pero queda derivado del slot:
 * `es_principal = 1` ⇔ `slot = 'celular_principal'`.
 *
 * Backfill de data existente:
 *   - es_principal=1                              → slot='celular_principal'
 *   - primer no-principal por contacto (min id)   → slot='fijo'
 *   - resto                                       → slot='adicional'
 *
 * Idempotente.
 */
class AddSlotToContactoTelefonos extends Migration
{
    public function up()
    {
        if (!Schema::connection('tenant')->hasTable('contacto_telefonos')) {
            return;
        }

        // 1. Agregar columna si no existe
        if (!Schema::connection('tenant')->hasColumn('contacto_telefonos', 'slot')) {
            Schema::connection('tenant')->table('contacto_telefonos', function (Blueprint $table) {
                $table->string('slot', 20)->nullable()->after('es_principal')
                    ->comment("Rol del teléfono dentro del contacto: 'celular_principal', 'fijo', 'adicional'");
                $table->index('slot');
            });
        }

        // 2. Backfill: principal → celular_principal
        DB::connection('tenant')
            ->table('contacto_telefonos')
            ->where('es_principal', 1)
            ->whereNull('slot')
            ->update(['slot' => 'celular_principal']);

        // 3. Backfill: el primer no-principal activo de cada contacto → fijo
        //    Se hace por contacto para evitar SQL específico de motor.
        $contactoIds = DB::connection('tenant')
            ->table('contacto_telefonos')
            ->where('es_principal', 0)
            ->where('estado', 1)
            ->whereNull('slot')
            ->distinct()
            ->pluck('contacto_id');

        foreach ($contactoIds as $contactoId) {
            $first = DB::connection('tenant')
                ->table('contacto_telefonos')
                ->where('contacto_id', $contactoId)
                ->where('es_principal', 0)
                ->where('estado', 1)
                ->whereNull('slot')
                ->orderBy('id')
                ->first();

            if ($first) {
                DB::connection('tenant')
                    ->table('contacto_telefonos')
                    ->where('id', $first->id)
                    ->update(['slot' => 'fijo']);
            }
        }

        // 4. Backfill: el resto (no-principales sin slot asignado en 3) → adicional
        DB::connection('tenant')
            ->table('contacto_telefonos')
            ->whereNull('slot')
            ->update(['slot' => 'adicional']);
    }

    public function down()
    {
        if (Schema::connection('tenant')->hasColumn('contacto_telefonos', 'slot')) {
            Schema::connection('tenant')->table('contacto_telefonos', function (Blueprint $table) {
                $table->dropIndex(['slot']);
                $table->dropColumn('slot');
            });
        }
    }
}
