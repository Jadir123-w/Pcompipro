<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega la columna `referencia` (VARCHAR 255 NULL) a la tabla `contacto`.
 *
 * El campo `referencia` ("Referencia oficina") ya estaba declarado en el
 * modelo Contacto (en $fillable), se envía desde el formulario y se expone en
 * getCollectionData(), pero la columna NUNCA se creó vía migración en este
 * repo. Algunos tenants la tenían agregada manualmente (ahí guardar contactos
 * funcionaba); en los tenants que no la tienen, guardar un contacto fallaba con
 * "SQLSTATE[42S22] Unknown column 'referencia' in 'field list'".
 *
 * Esta migración la formaliza para que exista de manera consistente en todos
 * los tenants. Se deja al final de la tabla (la posición no es funcional) y se
 * usa el mismo tipo que `direccion` por coherencia.
 *
 * Idempotente: si la columna ya existe, no hace nada.
 */
class AddReferenciaToContacto extends Migration
{
    public function up()
    {
        if (!Schema::connection('tenant')->hasTable('contacto')) {
            return;
        }

        if (!Schema::connection('tenant')->hasColumn('contacto', 'referencia')) {
            Schema::connection('tenant')->table('contacto', function (Blueprint $table) {
                $table->string('referencia', 255)->nullable()
                    ->comment('Referencia de la oficina/ubicación del contacto.');
            });
        }
    }

    public function down()
    {
        if (Schema::connection('tenant')->hasColumn('contacto', 'referencia')) {
            Schema::connection('tenant')->table('contacto', function (Blueprint $table) {
                $table->dropColumn('referencia');
            });
        }
    }
}
