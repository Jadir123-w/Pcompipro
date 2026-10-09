<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddMissingFieldsToRequerimientoTable extends Migration
{
    /**
     * Run the migrations.
     *
     * Esta migración agrega 19 campos faltantes a la tabla requerimiento
     * según especificación funcional del módulo CRM.
     */
    public function up()
    {
        Schema::table('requerimiento', function (Blueprint $table) {
            // RELACIONES
            // Contacto principal asociado al requerimiento
            if (!Schema::hasColumn('requerimiento', 'contacto_id')) {
                // `contacto.id` es BIGINT UNSIGNED: esta columna debe coincidir o la
                // clave foránea fk_requerimiento_contacto no se puede crear y quedaba
                // descartada en silencio por addForeignKeyIfNotExists().
                $table->unsignedBigInteger('contacto_id')->nullable()->after('empresa_id');
            }

            // DATOS DE NEGOCIO
            if (!Schema::hasColumn('requerimiento', 'origen')) {
                $table->string('origen', 50)->default('manual')->after('descripcion')
                      ->comment('Canal: manual, web, whatsapp, email, llamada');
            }

            if (!Schema::hasColumn('requerimiento', 'nivel_prioridad')) {
                $table->enum('nivel_prioridad', ['Muy alto', 'Alto', 'Medio', 'Bajo'])
                      ->default('Medio')->after('origen')
                      ->comment('Prioridad calculada por sistema de scoring');
            }

            if (!Schema::hasColumn('requerimiento', 'fecha_ingreso_etapa_actual')) {
                $table->timestamp('fecha_ingreso_etapa_actual')
                      ->useCurrent()->after('etapa')
                      ->comment('Para ordenar por antigüedad en cada etapa');
            }

            if (!Schema::hasColumn('requerimiento', 'tipo_condicion_proyecto')) {
                $table->string('tipo_condicion_proyecto', 100)->nullable()->after('fecha_ingreso_etapa_actual')
                      ->comment('Estándar, Urgente, Muy urgente');
            }

            if (!Schema::hasColumn('requerimiento', 'motivo_compra')) {
                $table->string('motivo_compra', 255)->nullable()->after('tipo_condicion_proyecto')
                      ->comment('Evento, Campaña, Reposición, etc.');
            }

            if (!Schema::hasColumn('requerimiento', 'forma_pago')) {
                $table->string('forma_pago', 100)->nullable()->after('motivo_compra')
                      ->comment('50/50, Crédito 30 días, Contado, etc.');
            }

            if (!Schema::hasColumn('requerimiento', 'nivel_tentativo')) {
                $table->string('nivel_tentativo', 50)->nullable()->after('forma_pago')
                      ->comment('Alto, Medio, Bajo - Probabilidad de aprobación');
            }

            if (!Schema::hasColumn('requerimiento', 'categoria_productos')) {
                $table->string('categoria_productos', 100)->nullable()->after('nivel_tentativo')
                      ->comment('Imprenta, Publicidad, Textil, Importados');
            }

            if (!Schema::hasColumn('requerimiento', 'usuario_apoyo_cotizacion')) {
                $table->unsignedInteger('usuario_apoyo_cotizacion')->nullable()->after('categoria_productos');
            }

            // CONTROL DE ESTADOS
            if (!Schema::hasColumn('requerimiento', 'cotizacion_aprobada')) {
                $table->boolean('cotizacion_aprobada')->default(false)->after('usuario_apoyo_cotizacion')
                      ->comment('TRUE cuando cliente aprueba cotización');
            }

            if (!Schema::hasColumn('requerimiento', 'fecha_aprobacion')) {
                $table->timestamp('fecha_aprobacion')->nullable()->after('cotizacion_aprobada')
                      ->comment('Fecha cuando se aprueba la cotización');
            }

            if (!Schema::hasColumn('requerimiento', 'escalado_a_proyecto')) {
                $table->boolean('escalado_a_proyecto')->default(false)->after('fecha_aprobacion')
                      ->comment('TRUE cuando se crea proyecto desde este requerimiento');
            }

            if (!Schema::hasColumn('requerimiento', 'fecha_escalado_proyecto')) {
                $table->timestamp('fecha_escalado_proyecto')->nullable()->after('escalado_a_proyecto')
                      ->comment('Fecha cuando se escala a proyecto');
            }

            if (!Schema::hasColumn('requerimiento', 'activo_en_crm')) {
                $table->boolean('activo_en_crm')->default(true)->after('fecha_escalado_proyecto')
                      ->comment('FALSE cuando se escala a proyecto (se oculta del kanban)');
            }

            // ARCHIVOS Y SEGUIMIENTO
            if (!Schema::hasColumn('requerimiento', 'archivos_cliente')) {
                $table->text('archivos_cliente')->nullable()->after('activo_en_crm')
                      ->comment('JSON: [{"nombre": "...", "ruta": "...", "fecha": "..."}]');
            }

            if (!Schema::hasColumn('requerimiento', 'usuario_ultima_interaccion')) {
                $table->unsignedInteger('usuario_ultima_interaccion')->nullable()->after('archivos_cliente')
                      ->comment('Usuario que registró la última interacción');
            }

            if (!Schema::hasColumn('requerimiento', 'fecha_ultima_interaccion')) {
                $table->timestamp('fecha_ultima_interaccion')->nullable()->after('usuario_ultima_interaccion')
                      ->comment('Fecha de última comunicación/interacción');
            }

            if (!Schema::hasColumn('requerimiento', 'fecha_seguimiento')) {
                $table->timestamp('fecha_seguimiento')->nullable()->after('fecha_ultima_interaccion')
                      ->comment('Fecha programada para próximo contacto con cliente');
            }
        });

        // ÍNDICES - agregar solo si no existen
        $this->addIndexIfNotExists('requerimiento', 'origen', 'idx_requerimiento_origen');
        $this->addIndexIfNotExists('requerimiento', 'nivel_prioridad', 'idx_requerimiento_nivel_prioridad');
        $this->addIndexIfNotExists('requerimiento', 'fecha_ingreso_etapa_actual', 'idx_requerimiento_fecha_ingreso_etapa');
        $this->addIndexIfNotExists('requerimiento', 'activo_en_crm', 'idx_requerimiento_activo_en_crm');
        $this->addIndexIfNotExists('requerimiento', 'cotizacion_aprobada', 'idx_requerimiento_cotizacion_aprobada');
        $this->addIndexIfNotExists('requerimiento', 'escalado_a_proyecto', 'idx_requerimiento_escalado_a_proyecto');

        // CLAVES FORÁNEAS - agregar solo si no existen
        $this->addForeignKeyIfNotExists('requerimiento', 'contacto_id', 'contacto', 'id', 'fk_requerimiento_contacto');
        $this->addForeignKeyIfNotExists('requerimiento', 'usuario_apoyo_cotizacion', 'users', 'id', 'fk_requerimiento_usuario_apoyo');
        $this->addForeignKeyIfNotExists('requerimiento', 'usuario_ultima_interaccion', 'users', 'id', 'fk_requerimiento_usuario_interaccion');
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::table('requerimiento', function (Blueprint $table) {
            // Eliminar claves foráneas primero
            try { $table->dropForeign('fk_requerimiento_contacto'); } catch (\Exception $e) {}
            try { $table->dropForeign('fk_requerimiento_usuario_apoyo'); } catch (\Exception $e) {}
            try { $table->dropForeign('fk_requerimiento_usuario_interaccion'); } catch (\Exception $e) {}

            // Eliminar índices
            try { $table->dropIndex('idx_requerimiento_origen'); } catch (\Exception $e) {}
            try { $table->dropIndex('idx_requerimiento_nivel_prioridad'); } catch (\Exception $e) {}
            try { $table->dropIndex('idx_requerimiento_fecha_ingreso_etapa'); } catch (\Exception $e) {}
            try { $table->dropIndex('idx_requerimiento_activo_en_crm'); } catch (\Exception $e) {}
            try { $table->dropIndex('idx_requerimiento_cotizacion_aprobada'); } catch (\Exception $e) {}
            try { $table->dropIndex('idx_requerimiento_escalado_a_proyecto'); } catch (\Exception $e) {}
        });

        // Eliminar columnas
        $columnsToRemove = [
            'contacto_id', 'origen', 'nivel_prioridad', 'fecha_ingreso_etapa_actual',
            'tipo_condicion_proyecto', 'motivo_compra', 'forma_pago', 'nivel_tentativo',
            'categoria_productos', 'usuario_apoyo_cotizacion', 'cotizacion_aprobada',
            'fecha_aprobacion', 'escalado_a_proyecto', 'fecha_escalado_proyecto',
            'activo_en_crm', 'archivos_cliente', 'usuario_ultima_interaccion',
            'fecha_ultima_interaccion', 'fecha_seguimiento',
        ];

        foreach ($columnsToRemove as $column) {
            if (Schema::hasColumn('requerimiento', $column)) {
                Schema::table('requerimiento', function (Blueprint $table) use ($column) {
                    $table->dropColumn($column);
                });
            }
        }
    }

    /**
     * Agregar índice si no existe
     */
    private function addIndexIfNotExists($tableName, $columnName, $indexName)
    {
        try {
            $indexes = collect(\DB::select("SHOW INDEX FROM {$tableName} WHERE Key_name = '{$indexName}'"));
            if ($indexes->isEmpty()) {
                Schema::table($tableName, function (Blueprint $table) use ($columnName, $indexName) {
                    $table->index($columnName, $indexName);
                });
            }
        } catch (\Exception $e) {
            \Log::warning("No se pudo crear índice {$indexName}: " . $e->getMessage());
        }
    }

    /**
     * Agregar foreign key si no existe
     */
    private function addForeignKeyIfNotExists($tableName, $columnName, $refTable, $refColumn, $fkName)
    {
        try {
            $fks = collect(\DB::select("
                SELECT CONSTRAINT_NAME 
                FROM information_schema.TABLE_CONSTRAINTS 
                WHERE CONSTRAINT_TYPE = 'FOREIGN KEY' 
                AND TABLE_NAME = '{$tableName}' 
                AND CONSTRAINT_NAME = '{$fkName}'
            "));
            
            if ($fks->isEmpty() && Schema::hasTable($refTable)) {
                Schema::table($tableName, function (Blueprint $table) use ($columnName, $refTable, $refColumn, $fkName) {
                    $table->foreign($columnName, $fkName)
                          ->references($refColumn)->on($refTable)
                          ->onDelete('set null');
                });
            }
        } catch (\Exception $e) {
            \Log::warning("No se pudo crear foreign key {$fkName}: " . $e->getMessage());
        }
    }
}
