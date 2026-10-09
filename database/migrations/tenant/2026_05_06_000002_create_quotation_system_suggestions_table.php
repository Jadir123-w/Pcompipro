<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * COM-02 S4 — Sugerencias del sistema (sec 30-31 Doc Funcional).
 *
 * Cada vez que el QuotationPatternAnalyzer detecta un patrón recurrente
 * en cotizaciones validadas (ej: "para textiles la ejecutiva siempre
 * cambia el tiempo de 15 a 18 días"), genera una entrada en esta tabla
 * con la sugerencia de actualización de configuración. La ejecutiva la
 * revisa desde la UI y decide aplicar/rechazar/snoozear.
 *
 * Tipos soportados (extensibles):
 *   tiempo_categoria   — defop_tiempo_<cat>_dias
 *   tecnica_categoria  — defop_tecnica_<cat>
 *   empaque_categoria  — defop_empaque_<cat>
 *   margen_categoria   — default_margin_percentage o margin rule
 *
 * Migración aditiva e idempotente.
 *
 * También seedea la nueva config key `defop_pattern_min_evidence` que el
 * analyzer usa como threshold mínimo para generar una sugerencia.
 */
class CreateQuotationSystemSuggestionsTable extends Migration
{
    public function up()
    {
        if (!Schema::connection('tenant')->hasTable('quotation_system_suggestions')) {
            Schema::connection('tenant')->create('quotation_system_suggestions', function (Blueprint $table) {
                $table->bigIncrements('id');

                $table->string('type', 40)
                    ->comment('tiempo_categoria | tecnica_categoria | empaque_categoria | margen_categoria');

                $table->string('category', 30)->nullable()
                    ->comment('textil | impresos | eco | packs | otros (cuando aplica)');

                $table->string('target_config_key', 100)
                    ->comment('Key de quotation_config que se actualizaría al aplicar la sugerencia');

                $table->text('current_value')->nullable()
                    ->comment('Valor actual del config en el momento de generar la sugerencia');

                $table->text('suggested_value')
                    ->comment('Valor que el analyzer propone');

                $table->unsignedInteger('evidence_count')->default(0)
                    ->comment('Cuántas prequotes validadas/aprobadas respaldan la sugerencia');

                $table->decimal('confidence', 3, 2)->default(0)
                    ->comment('0-1; basado en consistencia de los casos analizados');

                $table->text('reasoning')->nullable()
                    ->comment('Explicación humana del patrón detectado, para mostrar a la ejecutiva');

                $table->enum('status', [
                    'pending',
                    'applied',
                    'rejected',
                    'snoozed',
                ])->default('pending');

                $table->unsignedInteger('applied_by_user_id')->nullable();
                $table->timestamp('applied_at')->nullable();
                $table->string('rejection_reason', 255)->nullable();
                $table->timestamp('snoozed_until')->nullable();

                $table->timestamps();

                $table->index('status');
                $table->index('type');
                $table->index(['target_config_key', 'status'], 'idx_target_status');
            });
        }

        // Seed: threshold mínimo de evidencia para que el analyzer genere sugerencia
        if (Schema::connection('tenant')->hasTable('quotation_config')) {
            $exists = DB::connection('tenant')
                ->table('quotation_config')
                ->where('config_key', 'defop_pattern_min_evidence')
                ->exists();

            if (!$exists) {
                DB::connection('tenant')->table('quotation_config')->insert([
                    'config_key'   => 'defop_pattern_min_evidence',
                    'config_value' => '5',
                    'config_type'  => 'integer',
                    'description'  => 'Cantidad mínima de prequotes validadas que respaldan un patrón antes de que el analyzer lo proponga como sugerencia. Default 5.',
                    'created_at'   => now(),
                    'updated_at'   => now(),
                ]);
            }
        }
    }

    public function down()
    {
        Schema::connection('tenant')->dropIfExists('quotation_system_suggestions');

        if (Schema::connection('tenant')->hasTable('quotation_config')) {
            DB::connection('tenant')
                ->table('quotation_config')
                ->where('config_key', 'defop_pattern_min_evidence')
                ->delete();
        }
    }
}
