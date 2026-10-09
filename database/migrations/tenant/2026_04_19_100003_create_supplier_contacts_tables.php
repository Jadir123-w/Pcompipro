<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tablas de contactos del proveedor.
 *
 * Siguen el patrón del módulo Comercial (tablas hijas para teléfonos y emails)
 * en lugar del JSON arrays de la propuesta del cliente, porque:
 *  - Permite buscar por teléfono/email directamente (indexable).
 *  - Consistente con `contacto_telefonos` y `contacto_emails` del módulo Comercial.
 *  - Mejor integridad referencial.
 *
 * Relaciones:
 *  supplier_contacts (N:1 persons)
 *    └─ supplier_contact_phones (N:1 supplier_contacts)
 *    └─ supplier_contact_emails (N:1 supplier_contacts)
 */
class CreateSupplierContactsTables extends Migration
{
    public function up()
    {
        Schema::create('supplier_contacts', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('person_id'); // FK → persons (type='suppliers')
            $table->string('nombres', 191);
            $table->string('apellidos', 191)->nullable();
            $table->string('cargo', 120)->nullable();
            $table->date('cumpleanios')->nullable();
            $table->string('origen', 60)->nullable();
            $table->tinyInteger('estado')->default(1); // 1=activo, 0=inactivo
            $table->unsignedInteger('user_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('person_id')
                ->references('id')->on('persons')
                ->onDelete('cascade');

            $table->index('person_id');
            $table->index('estado');
        });

        Schema::create('supplier_contact_phones', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('supplier_contact_id');
            $table->string('telefono', 50);
            $table->string('tipo', 20)->default('trabajo'); // trabajo | personal
            $table->tinyInteger('es_principal')->default(0);
            $table->tinyInteger('estado')->default(1);
            $table->timestamps();

            $table->foreign('supplier_contact_id')
                ->references('id')->on('supplier_contacts')
                ->onDelete('cascade');

            $table->index('supplier_contact_id');
        });

        Schema::create('supplier_contact_emails', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('supplier_contact_id');
            $table->string('email', 191);
            $table->string('tipo', 20)->default('trabajo');
            $table->tinyInteger('es_principal')->default(0);
            $table->tinyInteger('estado')->default(1);
            $table->timestamps();

            $table->foreign('supplier_contact_id')
                ->references('id')->on('supplier_contacts')
                ->onDelete('cascade');

            $table->index('supplier_contact_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('supplier_contact_emails');
        Schema::dropIfExists('supplier_contact_phones');
        Schema::dropIfExists('supplier_contacts');
    }
}
