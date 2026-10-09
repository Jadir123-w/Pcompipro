<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * COM-07 — Responsable comercial del contacto.
 *
 * Hoy el sistema no sabe qué ejecutiva es dueña de cada cliente: `empresa.user_id`
 * guarda quién editó el registro por última vez y se sobrescribe en cada
 * actualización, así que no sirve como asignación. Sin este dato, COM-07 no puede
 * determinar a quién entregarle cada oportunidad de recuperación.
 *
 * La Especificación Puntual lo pide en su punto 4.2, con dos precisiones que
 * explican por qué esto es una columna y no un módulo:
 *
 *   - No requiere pantalla de administración: la carga inicial la realiza
 *     COMPIPRO sobre los contactos que correspondan.
 *   - Es una referencia operativa mientras no exista la funcionalidad formal de
 *     Carteras Comerciales, y debe quedar preparada para integrarse con ella.
 *
 * NULL significa "pendiente de asignación": la oportunidad existe y es visible
 * para supervisión, pero no entra al panel de ninguna ejecutiva. Cuando el campo
 * está vacío se aplica el criterio de respaldo (ver Com07ResponsableResolver),
 * que busca la última ejecutiva que gestionó al contacto.
 *
 * `unsignedInteger` para igualar `users.id`, que es INT y no BIGINT.
 * Sin foreign key, como el resto de las tablas del ERP en tenant.
 * Idempotente: el CI corre `tenancy:migrate` en Pruebas y Producción.
 */
class AddResponsableComercialToContacto extends Migration
{
    public function up()
    {
        if (!Schema::connection('tenant')->hasTable('contacto')) {
            return;
        }

        if (Schema::connection('tenant')->hasColumn('contacto', 'responsable_comercial_id')) {
            return;
        }

        Schema::connection('tenant')->table('contacto', function (Blueprint $table) {
            $table->unsignedInteger('responsable_comercial_id')
                  ->nullable()
                  ->after('empresa_id')
                  ->comment('Ejecutiva responsable de la cuenta. NULL = pendiente de asignacion');

            $table->index('responsable_comercial_id');
        });
    }

    public function down()
    {
        if (!Schema::connection('tenant')->hasTable('contacto')) {
            return;
        }

        if (!Schema::connection('tenant')->hasColumn('contacto', 'responsable_comercial_id')) {
            return;
        }

        Schema::connection('tenant')->table('contacto', function (Blueprint $table) {
            $table->dropIndex(['responsable_comercial_id']);
            $table->dropColumn('responsable_comercial_id');
        });
    }
}
