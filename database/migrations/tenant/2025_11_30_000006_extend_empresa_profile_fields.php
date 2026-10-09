<?php
// Modules/Comercial/Database/Migrations/2025_10_10_000000_extend_empresa_profile_fields.php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class ExtendEmpresaProfileFields extends Migration
{
    public function up()
    {
        Schema::table('empresa', function (Blueprint $table) {
            // Básicos
            if (!Schema::hasColumn('empresa', 'rubro')) {
                $table->string('rubro', 191)->nullable()->after('razon_social');
            }
            if (!Schema::hasColumn('empresa', 'celular')) {
                $table->string('celular', 50)->nullable()->after('telefono');
            }
            if (!Schema::hasColumn('empresa', 'n_trabajadores')) {
                $table->unsignedInteger('n_trabajadores')->nullable()->after('email');
            }
            if (!Schema::hasColumn('empresa', 'aniversario')) {
                $table->date('aniversario')->nullable()->after('n_trabajadores');
            }

            // Tipo empresa
            if (!Schema::hasColumn('empresa', 'tipo_empresa')) {
                $table->enum('tipo_empresa', ['PROSPECTO','FRECUENTE','TERCERISTA','POTENCIAL','OTRO'])
                      ->nullable()->after('website');
                $table->index('tipo_empresa');
            }

            // Ubicación
            if (!Schema::hasColumn('empresa', 'country_id')) {
              $table->char('country_id', 2)->default('PE')->after('tipo_empresa');
              $table->index('country_id');
            }
            if (!Schema::hasColumn('empresa', 'department_id')) {
              $table->char('department_id', 2)->nullable()->after('country_id');
              $table->index('department_id');
            }
            if (!Schema::hasColumn('empresa', 'province_id')) {
              $table->char('province_id', 4)->nullable()->after('department_id');
              $table->index('province_id');
            }
            if (!Schema::hasColumn('empresa', 'district_id')) {
              $table->char('district_id', 6)->nullable()->after('province_id');
              $table->index('district_id');
            }

            if (!Schema::hasColumn('empresa', 'referencia')) {
                $table->text('referencia')->nullable()->after('direccion');
            }

            // PerfilEmpresa
            if (!Schema::hasColumn('empresa', 'politica_pago')) {
                $table->text('politica_pago')->nullable()->after('referencia');
            }
            if (!Schema::hasColumn('empresa', 'trabaja_con_proveedores')) {
                $table->boolean('trabaja_con_proveedores')->default(0)->after('politica_pago');
            }
            if (!Schema::hasColumn('empresa', 'procedimiento_especial')) {
                $table->text('procedimiento_especial')->nullable()->after('trabaja_con_proveedores');
            }
            if (!Schema::hasColumn('empresa', 'frecuencia_compra')) {
                $table->string('frecuencia_compra', 50)->nullable()->after('procedimiento_especial');
            }
            if (!Schema::hasColumn('empresa', 'adicionales')) {
                $table->text('adicionales')->nullable()->after('frecuencia_compra');
            }

            // SUNAT
            if (!Schema::hasColumn('empresa', 'estado_contribuyente')) {
                $table->enum('estado_contribuyente', ['ACTIVO','BAJA TEMPORAL','SUSPENDIDO'])
                      ->nullable()->after('district_id');
                $table->index('estado_contribuyente');
            }
            if (!Schema::hasColumn('empresa', 'condicion_contribuyente')) {
                $table->enum('condicion_contribuyente', ['HABIDO','NO HABIDO'])
                      ->nullable()->after('estado_contribuyente');
                $table->index('condicion_contribuyente');
            }

            // Índices ya existentes:
            // ruc unique, email index — se asume creados en migración base
        });
    }

    public function down()
    {
        Schema::table('empresa', function (Blueprint $table) {
            $drops = [
              'rubro',
              'celular',
              'n_trabajadores',
              'aniversario',
              'tipo_empresa',
              'ciudad',
              'referencia',
              'distrito',
              'pais',
              'politica_pago',
              'trabaja_con_proveedores',
              'procedimiento_especial',
              'frecuencia_compra',
              'adicionales',
              'estado_contribuyente',
              'condicion_contribuyente',
            ];
            foreach ($drops as $col) {
                if (Schema::hasColumn('empresa', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
}
