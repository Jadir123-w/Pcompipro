<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AlterProyectoAddOperativoFields extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('proyecto')) {
            return;
        }

        Schema::table('proyecto', function (Blueprint $table) {
            if (!Schema::hasColumn('proyecto', 'tipo')) {
                $table->string('tipo', 50)->nullable()->after('descripcion');
            }
            if (!Schema::hasColumn('proyecto', 'prioridad')) {
                $table->string('prioridad', 20)->nullable()->after('tipo');
            }
            if (!Schema::hasColumn('proyecto', 'origen')) {
                $table->string('origen', 50)->nullable()->after('prioridad');
            }
            if (!Schema::hasColumn('proyecto', 'ejecutivo_id')) {
                $table->unsignedBigInteger('ejecutivo_id')->nullable()->after('origen');
            }
            if (!Schema::hasColumn('proyecto', 'semaforo')) {
                $table->enum('semaforo', ['Verde','Amarillo','Rojo'])->nullable()->after('etapa');
            }
            if (!Schema::hasColumn('proyecto', 'porcentaje_avance')) {
                $table->decimal('porcentaje_avance', 5, 2)->default(0)->after('semaforo');
            }
            if (!Schema::hasColumn('proyecto', 'porcentaje_facturado')) {
                $table->decimal('porcentaje_facturado', 5, 2)->default(0)->after('porcentaje_avance');
            }
            if (!Schema::hasColumn('proyecto', 'porcentaje_cobrado')) {
                $table->decimal('porcentaje_cobrado', 5, 2)->default(0)->after('porcentaje_facturado');
            }
            if (!Schema::hasColumn('proyecto', 'monto_costo')) {
                $table->decimal('monto_costo', 12, 2)->nullable()->after('monto_total');
            }
            if (!Schema::hasColumn('proyecto', 'margen_pct')) {
                $table->decimal('margen_pct', 6, 3)->nullable()->after('monto_costo');
            }
            if (!Schema::hasColumn('proyecto', 'oc_numero')) {
                $table->string('oc_numero', 100)->nullable()->after('margen_pct');
            }
            if (!Schema::hasColumn('proyecto', 'oc_fecha')) {
                $table->date('oc_fecha')->nullable()->after('oc_numero');
            }
            if (!Schema::hasColumn('proyecto', 'arte_aprobado_at')) {
                $table->dateTime('arte_aprobado_at')->nullable()->after('oc_fecha');
            }
            if (!Schema::hasColumn('proyecto', 'adelanto_monto')) {
                $table->decimal('adelanto_monto', 12, 2)->nullable()->after('arte_aprobado_at');
            }
            if (!Schema::hasColumn('proyecto', 'adelanto_fecha')) {
                $table->date('adelanto_fecha')->nullable()->after('adelanto_monto');
            }
            if (!Schema::hasColumn('proyecto', 'notas')) {
                $table->text('notas')->nullable()->after('adelanto_fecha');
            }

            // Índices (si no existen; en 5.x no hay "if not exists" para drop así que en down los quitamos por nombre)
            $table->index('ejecutivo_id', 'idx_proyecto_ejecutivo');
            $table->index('semaforo', 'idx_proyecto_semaforo');
            $table->index('etapa', 'idx_proyecto_etapa');
            $table->index(['fecha_inicio', 'fecha_fin'], 'idx_proyecto_fechas');
        });
    }

    public function down()
    {
        if (!Schema::hasTable('proyecto')) {
            return;
        }

        Schema::table('proyecto', function (Blueprint $table) {
            // Quitar índices primero (ignora errores si no existen en tu versión)
            try { $table->dropIndex('idx_proyecto_ejecutivo'); } catch (\Throwable $e) {}
            try { $table->dropIndex('idx_proyecto_semaforo'); } catch (\Throwable $e) {}
            try { $table->dropIndex('idx_proyecto_etapa'); } catch (\Throwable $e) {}
            try { $table->dropIndex('idx_proyecto_fechas'); } catch (\Throwable $e) {}

            foreach ([
                'tipo','prioridad','origen','ejecutivo_id','semaforo','porcentaje_avance',
                'porcentaje_facturado','porcentaje_cobrado','monto_costo','margen_pct',
                'oc_numero','oc_fecha','arte_aprobado_at','adelanto_monto','adelanto_fecha','notas'
            ] as $col) {
                if (Schema::hasColumn('proyecto', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
}
