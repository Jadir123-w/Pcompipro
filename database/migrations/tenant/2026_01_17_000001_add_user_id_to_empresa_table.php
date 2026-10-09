<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Migración para agregar tracking de usuario en tabla empresa
 * 
 * Agrega la columna user_id para rastrear qué usuario creó o actualizó
 * por última vez cada registro de empresa.
 */
class AddUserIdToEmpresaTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('empresa', function (Blueprint $table) {
            // Verificar si la columna ya existe
            if (!Schema::hasColumn('empresa', 'user_id')) {
                // Agregar columna user_id después de 'estado'.
                // `users.id` es INT UNSIGNED, de modo que esta columna debe serlo
                // también: con BIGINT la clave foránea fk_empresa_user no se puede
                // crear y quedaba descartada en silencio por el catch de abajo.
                $table->unsignedInteger('user_id')->nullable()->after('estado');
                
                // Agregar índice para mejorar performance en consultas
                $table->index('user_id', 'idx_empresa_user_id');
            }
        });
        
        // Intentar agregar foreign key solo si existe la tabla users
        // y si no causa conflicto
        try {
            if (Schema::hasTable('users')) {
                Schema::table('empresa', function (Blueprint $table) {
                    $table->foreign('user_id', 'fk_empresa_user')
                          ->references('id')
                          ->on('users')
                          ->onDelete('set null');
                });
            }
        } catch (\Exception $e) {
            // Si falla el foreign key, continuar sin él
            // La columna ya está creada y con índice
            \Log::warning('No se pudo crear foreign key en empresa.user_id: ' . $e->getMessage());
        }
        
        // Opcional: Actualizar registros existentes con el primer usuario admin
        try {
            DB::statement("
                UPDATE empresa 
                SET user_id = (SELECT id FROM users WHERE type = 'admin' ORDER BY id ASC LIMIT 1)
                WHERE user_id IS NULL
            ");
        } catch (\Exception $e) {
            \Log::warning('No se pudieron actualizar registros existentes: ' . $e->getMessage());
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('empresa', function (Blueprint $table) {
            // Intentar eliminar foreign key si existe
            try {
                $table->dropForeign('fk_empresa_user');
            } catch (\Exception $e) {
                // Si no existe, continuar
            }
            
            // Eliminar índice si existe
            try {
                $table->dropIndex('idx_empresa_user_id');
            } catch (\Exception $e) {
                // Si no existe, continuar
            }
            
            // Eliminar columna si existe
            if (Schema::hasColumn('empresa', 'user_id')) {
                $table->dropColumn('user_id');
            }
        });
    }
}
