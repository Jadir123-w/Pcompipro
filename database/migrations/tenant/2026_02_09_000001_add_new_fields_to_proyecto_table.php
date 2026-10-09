<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddNewFieldsToProyectoTable extends Migration
{
    /**
     * Run the migrations.
     *
     * Agrega 35 campos nuevos a la tabla proyecto según especificación funcional.
     * Campos organizados por sección: Criterios, Facturación, Muestras, 
     * Logística, Despacho, Seguridad, Post Venta y Cobranza.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('proyecto', function (Blueprint $table) {
            // ================================================================
            // SECCIÓN: CRITERIOS A CONSIDERAR
            // ================================================================
            if (!Schema::hasColumn('proyecto', 'contacto_id')) {
                $table->unsignedBigInteger('contacto_id')->nullable()->after('empresa_id')
                      ->comment('FK contacto principal del proyecto');
            }
            if (!Schema::hasColumn('proyecto', 'tiempo_proyecto')) {
                $table->string('tiempo_proyecto', 100)->nullable()->after('notas')
                      ->comment('Ej: 12 a 15 días útiles');
            }
            if (!Schema::hasColumn('proyecto', 'politica_tiempos_cortos')) {
                $table->string('politica_tiempos_cortos', 100)->nullable()->after('tiempo_proyecto')
                      ->comment('No aplica, Aplica - Cargo adicional, etc.');
            }
            if (!Schema::hasColumn('proyecto', 'es_recompra')) {
                $table->string('es_recompra', 50)->nullable()->after('politica_tiempos_cortos')
                      ->comment('Sí, No, No Aplica');
            }
            if (!Schema::hasColumn('proyecto', 'tipo_cliente_1')) {
                $table->string('tipo_cliente_1', 50)->nullable()->after('es_recompra')
                      ->comment('Ocasional, Frecuente, Recurrente');
            }
            if (!Schema::hasColumn('proyecto', 'tipo_cliente_2')) {
                $table->string('tipo_cliente_2', 50)->nullable()->after('tipo_cliente_1')
                      ->comment('Final, Intermediario');
            }
            if (!Schema::hasColumn('proyecto', 'tiene_oc')) {
                $table->string('tiene_oc', 50)->nullable()->after('tipo_cliente_2')
                      ->comment('Completo, Parcial, No');
            }

            // ================================================================
            // SECCIÓN: FACTURACIÓN
            // ================================================================
            if (!Schema::hasColumn('proyecto', 'servicio_facturado')) {
                $table->string('servicio_facturado', 50)->nullable()->after('tiene_oc')
                      ->comment('Sí aplica, No aplica');
            }
            if (!Schema::hasColumn('proyecto', 'cantidad_razones_sociales')) {
                $table->integer('cantidad_razones_sociales')->nullable()->after('servicio_facturado')
                      ->comment('1, 2, 3...');
            }
            if (!Schema::hasColumn('proyecto', 'cada_rs_con_oc')) {
                $table->tinyInteger('cada_rs_con_oc')->default(0)->after('cantidad_razones_sociales')
                      ->comment('Cada razón social con O/C');
            }
            if (!Schema::hasColumn('proyecto', 'aplica_detraccion')) {
                $table->tinyInteger('aplica_detraccion')->default(0)->after('cada_rs_con_oc');
            }
            if (!Schema::hasColumn('proyecto', 'aplica_retencion')) {
                $table->tinyInteger('aplica_retencion')->default(0)->after('aplica_detraccion');
            }
            if (!Schema::hasColumn('proyecto', 'forma_pago')) {
                $table->string('forma_pago', 100)->nullable()->after('aplica_retencion')
                      ->comment('Crédito 15, Contado, 50/50, etc.');
            }
            if (!Schema::hasColumn('proyecto', 'pago_directo')) {
                $table->tinyInteger('pago_directo')->default(1)->after('forma_pago');
            }
            if (!Schema::hasColumn('proyecto', 'contacto_facturacion')) {
                $table->string('contacto_facturacion', 255)->nullable()->after('pago_directo')
                      ->comment('Nombre y datos del contacto');
            }
            if (!Schema::hasColumn('proyecto', 'correo_factura')) {
                $table->string('correo_factura', 255)->nullable()->after('contacto_facturacion');
            }
            if (!Schema::hasColumn('proyecto', 'requiere_formato_especial')) {
                $table->tinyInteger('requiere_formato_especial')->default(0)->after('correo_factura');
            }
            if (!Schema::hasColumn('proyecto', 'entrega_factura_fisica')) {
                $table->string('entrega_factura_fisica', 100)->nullable()->after('requiere_formato_especial')
                      ->comment('Sí, No, No aplica');
            }
            if (!Schema::hasColumn('proyecto', 'direccion_entrega_factura')) {
                $table->text('direccion_entrega_factura')->nullable()->after('entrega_factura_fisica');
            }
            if (!Schema::hasColumn('proyecto', 'documento_adicional_factura')) {
                $table->text('documento_adicional_factura')->nullable()->after('direccion_entrega_factura');
            }
            if (!Schema::hasColumn('proyecto', 'comentario_facturacion')) {
                $table->text('comentario_facturacion')->nullable()->after('documento_adicional_factura');
            }

            // ================================================================
            // SECCIÓN: APROBACIÓN DE MUESTRAS
            // ================================================================
            if (!Schema::hasColumn('proyecto', 'muestra_virtual')) {
                $table->tinyInteger('muestra_virtual')->default(0)->after('comentario_facturacion');
            }
            if (!Schema::hasColumn('proyecto', 'muestra_fisica')) {
                $table->tinyInteger('muestra_fisica')->default(0)->after('muestra_virtual');
            }
            if (!Schema::hasColumn('proyecto', 'prueba_color')) {
                $table->tinyInteger('prueba_color')->default(0)->after('muestra_fisica');
            }

            // ================================================================
            // SECCIÓN: LOGÍSTICA (Embalaje)
            // ================================================================
            if (!Schema::hasColumn('proyecto', 'tipo_embalaje')) {
                $table->string('tipo_embalaje', 255)->nullable()->after('prueba_color')
                      ->comment('Embalaje propuesta Compipro, etc.');
            }
            if (!Schema::hasColumn('proyecto', 'embalaje_grupal')) {
                $table->string('embalaje_grupal', 100)->nullable()->after('tipo_embalaje')
                      ->comment('Bolsa manga, Cajas, etc.');
            }
            if (!Schema::hasColumn('proyecto', 'rotulo_especial')) {
                $table->tinyInteger('rotulo_especial')->default(0)->after('embalaje_grupal');
            }
            if (!Schema::hasColumn('proyecto', 'sticker_compipro')) {
                $table->tinyInteger('sticker_compipro')->default(0)->after('rotulo_especial');
            }
            if (!Schema::hasColumn('proyecto', 'cantidad_sticker')) {
                $table->string('cantidad_sticker', 100)->nullable()->after('sticker_compipro')
                      ->comment('PLANCHA (55 unid), etc.');
            }

            // ================================================================
            // SECCIÓN: DESPACHO
            // ================================================================
            if (!Schema::hasColumn('proyecto', 'contacto_despacho')) {
                $table->string('contacto_despacho', 255)->nullable()->after('cantidad_sticker')
                      ->comment('Nombre, número, área');
            }
            if (!Schema::hasColumn('proyecto', 'tipo_entrega')) {
                $table->string('tipo_entrega', 100)->nullable()->after('contacto_despacho')
                      ->comment('ALMACEN, OFICINA, RECOJO, etc.');
            }
            if (!Schema::hasColumn('proyecto', 'direccion_entrega')) {
                $table->text('direccion_entrega')->nullable()->after('tipo_entrega');
            }
            if (!Schema::hasColumn('proyecto', 'horario_atencion_despacho')) {
                $table->string('horario_atencion_despacho', 255)->nullable()->after('direccion_entrega')
                      ->comment('8:00 am a 5:00 pm, etc.');
            }
            if (!Schema::hasColumn('proyecto', 'documentacion_adicional_despacho')) {
                $table->text('documentacion_adicional_despacho')->nullable()->after('horario_atencion_despacho');
            }
            if (!Schema::hasColumn('proyecto', 'tamano_movilidad')) {
                $table->string('tamano_movilidad', 100)->nullable()->after('documentacion_adicional_despacho')
                      ->comment('Moto, Auto, Camioneta, Camión');
            }
            if (!Schema::hasColumn('proyecto', 'requiere_programacion_despacho')) {
                $table->tinyInteger('requiere_programacion_despacho')->default(0)->after('tamano_movilidad');
            }
            if (!Schema::hasColumn('proyecto', 'confirmacion_ingreso')) {
                $table->tinyInteger('confirmacion_ingreso')->default(0)->after('requiere_programacion_despacho');
            }

            // ================================================================
            // SECCIÓN: IMPLEMENTOS DE SEGURIDAD
            // ================================================================
            if (!Schema::hasColumn('proyecto', 'sctr_personal_logistica')) {
                $table->tinyInteger('sctr_personal_logistica')->default(0)->after('confirmacion_ingreso');
            }
            if (!Schema::hasColumn('proyecto', 'epps_logistica')) {
                $table->tinyInteger('epps_logistica')->default(0)->after('sctr_personal_logistica');
            }
            if (!Schema::hasColumn('proyecto', 'sctr_conductor')) {
                $table->tinyInteger('sctr_conductor')->default(0)->after('epps_logistica');
            }
            if (!Schema::hasColumn('proyecto', 'epps_conductor')) {
                $table->tinyInteger('epps_conductor')->default(0)->after('sctr_conductor');
            }
            if (!Schema::hasColumn('proyecto', 'documentacion_transportista')) {
                $table->tinyInteger('documentacion_transportista')->default(0)->after('epps_conductor');
            }

            // ================================================================
            // SECCIÓN: POST VENTA
            // ================================================================
            if (!Schema::hasColumn('proyecto', 'carta_conformidad')) {
                $table->tinyInteger('carta_conformidad')->default(0)->after('documentacion_transportista');
            }
            if (!Schema::hasColumn('proyecto', 'solicitud_promocion')) {
                $table->tinyInteger('solicitud_promocion')->default(0)->after('carta_conformidad');
            }
            if (!Schema::hasColumn('proyecto', 'acepta_solicitud_promocion')) {
                $table->tinyInteger('acepta_solicitud_promocion')->default(0)->after('solicitud_promocion');
            }
            if (!Schema::hasColumn('proyecto', 'tiene_datos_cumpleanos')) {
                $table->tinyInteger('tiene_datos_cumpleanos')->default(0)->after('acepta_solicitud_promocion');
            }

            // ================================================================
            // SECCIÓN: ESTATUS PROVEEDORES
            // ================================================================
            if (!Schema::hasColumn('proyecto', 'estatus_proveedores')) {
                $table->text('estatus_proveedores')->nullable()->after('tiene_datos_cumpleanos');
            }
            if (!Schema::hasColumn('proyecto', 'resumen_proyecto')) {
                $table->text('resumen_proyecto')->nullable()->after('estatus_proveedores');
            }

            // ================================================================
            // SECCIÓN: COBRANZA
            // ================================================================
            if (!Schema::hasColumn('proyecto', 'fecha_envio_factura')) {
                $table->date('fecha_envio_factura')->nullable()->after('resumen_proyecto');
            }
            if (!Schema::hasColumn('proyecto', 'fecha_ingreso_factura')) {
                $table->date('fecha_ingreso_factura')->nullable()->after('fecha_envio_factura');
            }
            if (!Schema::hasColumn('proyecto', 'fecha_tentativa_pago')) {
                $table->date('fecha_tentativa_pago')->nullable()->after('fecha_ingreso_factura');
            }
            if (!Schema::hasColumn('proyecto', 'metodo_politica_pago')) {
                $table->text('metodo_politica_pago')->nullable()->after('fecha_tentativa_pago')
                      ->comment('Solo los Viernes, Transferencia, etc.');
            }
            if (!Schema::hasColumn('proyecto', 'pago_conforme')) {
                $table->tinyInteger('pago_conforme')->nullable()->after('metodo_politica_pago')
                      ->comment('NULL=pendiente, 1=sí, 0=no');
            }
            if (!Schema::hasColumn('proyecto', 'observaciones_cobranza')) {
                $table->text('observaciones_cobranza')->nullable()->after('pago_conforme');
            }
        });

        // ================================================================
        // ÍNDICES
        // ================================================================
        $this->addIndexIfNotExists('proyecto', 'contacto_id', 'idx_proyecto_contacto');
        $this->addIndexIfNotExists('proyecto', 'tipo_cliente_1', 'idx_proyecto_tipo_cliente_1');
        $this->addIndexIfNotExists('proyecto', 'fecha_tentativa_pago', 'idx_proyecto_fecha_tentativa_pago');

        // ================================================================
        // FOREIGN KEY para contacto_id
        // ================================================================
        $this->addForeignKeyIfNotExists('proyecto', 'contacto_id', 'contacto', 'id', 'fk_proyecto_contacto');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('proyecto', function (Blueprint $table) {
            // Eliminar foreign key
            try { $table->dropForeign('fk_proyecto_contacto'); } catch (\Exception $e) {}

            // Eliminar índices
            try { $table->dropIndex('idx_proyecto_contacto'); } catch (\Exception $e) {}
            try { $table->dropIndex('idx_proyecto_tipo_cliente_1'); } catch (\Exception $e) {}
            try { $table->dropIndex('idx_proyecto_fecha_tentativa_pago'); } catch (\Exception $e) {}
        });

        // Lista de columnas a eliminar
        $columns = [
            'contacto_id', 'tiempo_proyecto', 'politica_tiempos_cortos', 'es_recompra',
            'tipo_cliente_1', 'tipo_cliente_2', 'tiene_oc', 'servicio_facturado',
            'cantidad_razones_sociales', 'cada_rs_con_oc', 'aplica_detraccion',
            'aplica_retencion', 'forma_pago', 'pago_directo', 'contacto_facturacion',
            'correo_factura', 'requiere_formato_especial', 'entrega_factura_fisica',
            'direccion_entrega_factura', 'documento_adicional_factura', 'comentario_facturacion',
            'muestra_virtual', 'muestra_fisica', 'prueba_color', 'tipo_embalaje',
            'embalaje_grupal', 'rotulo_especial', 'sticker_compipro', 'cantidad_sticker',
            'contacto_despacho', 'tipo_entrega', 'direccion_entrega', 'horario_atencion_despacho',
            'documentacion_adicional_despacho', 'tamano_movilidad', 'requiere_programacion_despacho',
            'confirmacion_ingreso', 'sctr_personal_logistica', 'epps_logistica',
            'sctr_conductor', 'epps_conductor', 'documentacion_transportista',
            'carta_conformidad', 'solicitud_promocion', 'acepta_solicitud_promocion',
            'tiene_datos_cumpleanos', 'estatus_proveedores', 'resumen_proyecto',
            'fecha_envio_factura', 'fecha_ingreso_factura', 'fecha_tentativa_pago',
            'metodo_politica_pago', 'pago_conforme', 'observaciones_cobranza',
        ];

        foreach ($columns as $column) {
            if (Schema::hasColumn('proyecto', $column)) {
                Schema::table('proyecto', function (Blueprint $table) use ($column) {
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
            if ($indexes->isEmpty() && Schema::hasColumn($tableName, $columnName)) {
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
            
            if ($fks->isEmpty() && Schema::hasTable($refTable) && Schema::hasColumn($tableName, $columnName)) {
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
