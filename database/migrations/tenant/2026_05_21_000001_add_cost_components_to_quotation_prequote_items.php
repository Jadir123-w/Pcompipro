<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * COM-02 Fase A — Motor de costeo ampliado.
 *
 * Agrega los componentes de costo separados que el cotizador real de COMPIPRO
 * maneja (validado contra el Excel ProcesoProyecto_BSF, pestaña Cotizacion1):
 *
 *   Cadena de cálculo por ítem:
 *     T    = costo_base × (1 + markup%)                      [solo producto lleva markup]
 *     TP   = (Q × T) + costo_muestra + costo_movilidad       [+ costos_adicionales JSON]
 *     SCTR = sctr% × (TP + impresión + empaque)
 *     Total = TP + impresión + empaque + SCTR
 *     costo_unit_sin_igv = Total / Q
 *
 * Clasificación de costos (ingeniería de costos):
 *   - costo_muestra, costo_movilidad : FIJOS del pedido (global, 1 vez, sin markup)
 *   - costo_impresion, costo_empaque : pueden ser unitarios (× Q) o globales,
 *     según los flags impresion_por_unidad / empaque_por_unidad.
 *   - SCTR : overhead = sctr% × base (configurable, default 1.5%)
 *
 * Idempotente.
 */
class AddCostComponentsToQuotationPrequoteItems extends Migration
{
    public function up()
    {
        if (Schema::connection('tenant')->hasTable('quotation_prequote_items')) {
            Schema::connection('tenant')->table('quotation_prequote_items', function (Blueprint $table) {
                if (!Schema::connection('tenant')->hasColumn('quotation_prequote_items', 'costo_muestra')) {
                    $table->decimal('costo_muestra', 12, 2)->default(0)->after('costos_adicionales')
                        ->comment('Costo de muestra. FIJO del pedido (no se multiplica por cantidad).');
                }
                if (!Schema::connection('tenant')->hasColumn('quotation_prequote_items', 'costo_movilidad')) {
                    $table->decimal('costo_movilidad', 12, 2)->default(0)->after('costo_muestra')
                        ->comment('Costo de movilidad/flete. FIJO del pedido. Reglas: gratis > S/550, +S/30 si menor.');
                }
                if (!Schema::connection('tenant')->hasColumn('quotation_prequote_items', 'costo_impresion')) {
                    $table->decimal('costo_impresion', 12, 2)->default(0)->after('costo_movilidad')
                        ->comment('Costo de impresión/personalización. Unitario o global según impresion_por_unidad.');
                }
                if (!Schema::connection('tenant')->hasColumn('quotation_prequote_items', 'costo_empaque')) {
                    $table->decimal('costo_empaque', 12, 2)->default(0)->after('costo_impresion')
                        ->comment('Costo de empaque. Unitario o global según empaque_por_unidad.');
                }
                if (!Schema::connection('tenant')->hasColumn('quotation_prequote_items', 'impresion_por_unidad')) {
                    $table->boolean('impresion_por_unidad')->default(false)->after('costo_empaque')
                        ->comment('Si true, costo_impresion se multiplica por la cantidad (variable). Si false, es global.');
                }
                if (!Schema::connection('tenant')->hasColumn('quotation_prequote_items', 'empaque_por_unidad')) {
                    $table->boolean('empaque_por_unidad')->default(false)->after('impresion_por_unidad')
                        ->comment('Si true, costo_empaque se multiplica por la cantidad (variable). Si false, es global.');
                }
            });
        }

        // Seed sctr_porcentaje en quotation_config (default 1.5%). Idempotente.
        if (Schema::connection('tenant')->hasTable('quotation_config')) {
            $exists = DB::connection('tenant')
                ->table('quotation_config')
                ->where('config_key', 'sctr_porcentaje')
                ->exists();

            if (!$exists) {
                DB::connection('tenant')->table('quotation_config')->insert([
                    'config_key'   => 'sctr_porcentaje',
                    'config_value' => '1.5',
                    'config_type'  => 'decimal',
                    'description'  => 'Porcentaje del Seguro Complementario de Trabajo de Riesgo (SCTR). Se aplica sobre (Total Producto + impresión + empaque). Default 1.5%.',
                    'created_at'   => date('Y-m-d H:i:s'),
                    'updated_at'   => date('Y-m-d H:i:s'),
                ]);
            }
        }
    }

    public function down()
    {
        if (Schema::connection('tenant')->hasTable('quotation_prequote_items')) {
            Schema::connection('tenant')->table('quotation_prequote_items', function (Blueprint $table) {
                foreach ([
                    'costo_muestra', 'costo_movilidad', 'costo_impresion',
                    'costo_empaque', 'impresion_por_unidad', 'empaque_por_unidad',
                ] as $col) {
                    if (Schema::connection('tenant')->hasColumn('quotation_prequote_items', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }

        if (Schema::connection('tenant')->hasTable('quotation_config')) {
            DB::connection('tenant')->table('quotation_config')
                ->where('config_key', 'sctr_porcentaje')
                ->delete();
        }
    }
}
