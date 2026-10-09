<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega columna `adicionales` (TEXT NULL) a la tabla `contacto`.
 *
 * Requerimiento del cliente (Requerimientos_27_05_26.pdf, página 3):
 *
 *   "Adicional agregar un campo textarea denominado Adicionales que deberá
 *    mostrarse bajo Referencia oficina del contacto"
 *
 * Idempotente.
 */
class AddAdicionalesToContacto extends Migration
{
    public function up()
    {
        if (!Schema::connection('tenant')->hasTable('contacto')) {
            return;
        }

        if (!Schema::connection('tenant')->hasColumn('contacto', 'adicionales')) {
            // NOTA: NO se usa ->after('referencia') porque la columna `referencia`
            // está en el modelo y código pero NO se creó vía migración en este
            // repo. Algunos tenants la tienen (agregada manualmente vía SQL),
            // otros no — por eso esta migración falla con "Unknown column
            // 'referencia'" en los tenants que no la tienen. Dejamos la nueva
            // columna al final de la tabla (la posición no es funcional).
            Schema::connection('tenant')->table('contacto', function (Blueprint $table) {
                $table->text('adicionales')->nullable()
                    ->comment('Notas adicionales del contacto. Campo libre tipo textarea.');
            });
        }
    }

    public function down()
    {
        if (Schema::connection('tenant')->hasColumn('contacto', 'adicionales')) {
            Schema::connection('tenant')->table('contacto', function (Blueprint $table) {
                $table->dropColumn('adicionales');
            });
        }
    }
}
