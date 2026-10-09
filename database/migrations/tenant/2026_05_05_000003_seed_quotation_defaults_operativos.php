<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * COM-02 S3 — Seed de "Defaults Operativos" en quotation_config.
 *
 * Doc Funcional §21: "Configuración general del cotizador" debe permitir
 * administrar tiempos base, técnicas sugeridas, empaques sugeridos, reglas
 * de inferencia, parámetros de cálculo y prioridades de búsqueda histórico.
 *
 * Estas claves alimentan al QuotationOrchestrator/AIEngine cuando hay que
 * inferir un atributo faltante de un ítem (sec 12).  Los valores por defecto
 * abajo son sensatos para Compipro pero TODOS son editables desde la UI
 * (tab "Defaults Operativos" en quotation-settings).
 *
 * Migración aditiva e idempotente: solo inserta si la key no existe.
 *
 * Categorías cubiertas: Textil, Impresos, Eco, Packs, Otros.
 */
class SeedQuotationDefaultsOperativos extends Migration
{
    public function up()
    {
        if (!Schema::connection('tenant')->hasTable('quotation_config')) {
            return;
        }

        $now = date('Y-m-d H:i:s');

        $rows = [
            // ── Tiempos base por categoría (días hábiles de producción) ──
            ['defop_tiempo_textil_dias',   '15', 'integer', 'Días hábiles de producción para productos textiles (mochilas, polos, casacas).'],
            ['defop_tiempo_impresos_dias',  '7', 'integer', 'Días hábiles para productos impresos (libretas, agendas, cuadernos).'],
            ['defop_tiempo_eco_dias',      '15', 'integer', 'Días hábiles para productos ecológicos (bolsas eco, bambú, productos reciclados).'],
            ['defop_tiempo_packs_dias',    '10', 'integer', 'Días hábiles para packs y kits corporativos.'],
            ['defop_tiempo_otros_dias',    '15', 'integer', 'Días hábiles default para productos sin categoría específica.'],

            // ── Técnicas sugeridas por categoría ──
            ['defop_tecnica_textil',     'bordado',     'string', 'Técnica de personalización sugerida para productos textiles.'],
            ['defop_tecnica_impresos',   'full color',  'string', 'Técnica sugerida para productos impresos.'],
            ['defop_tecnica_eco',        'serigrafía',  'string', 'Técnica sugerida para productos ecológicos.'],
            ['defop_tecnica_packs',      'serigrafía',  'string', 'Técnica sugerida para packs corporativos.'],

            // ── Empaques sugeridos por categoría ──
            ['defop_empaque_textil',     'bolsa individual',     'string', 'Empaque por defecto para productos textiles.'],
            ['defop_empaque_impresos',   'caja x 50 unidades',   'string', 'Empaque por defecto para productos impresos.'],
            ['defop_empaque_eco',        'bolsa kraft individual','string', 'Empaque por defecto para productos ecológicos.'],
            ['defop_empaque_packs',      'caja personalizada',   'string', 'Empaque por defecto para packs.'],

            // ── Reglas de inferencia adicionales ──
            // (Los default_colores=1 y default_lados=1 ya existen del seed inicial.)
            ['defop_default_material',  'estándar', 'string',  'Material por defecto cuando el LLM no lo infiere del texto.'],
            ['defop_default_unidad',    'UND',      'string',  'Unidad de medida por defecto (UND, KG, etc.).'],

            // ── Histórico como contexto de inferencia ──
            ['defop_historico_enabled',         '1',  'boolean', 'Si está activo, el AIEngine inyecta cotizaciones previas similares al prompt como referencia para inferir proveedor, costo y tiempo.'],
            ['defop_historico_meses_lookback', '12',  'integer', 'Meses hacia atrás que se consultan en el histórico al buscar casos similares.'],
            ['defop_historico_min_score',     '0.6',  'decimal', 'Score mínimo (0–1) para considerar una cotización histórica como similar al ítem actual.'],
        ];

        foreach ($rows as $row) {
            list($key, $value, $type, $desc) = $row;

            $exists = DB::connection('tenant')
                ->table('quotation_config')
                ->where('config_key', $key)
                ->exists();

            if ($exists) continue;

            DB::connection('tenant')->table('quotation_config')->insert([
                'config_key'   => $key,
                'config_value' => $value,
                'config_type'  => $type,
                'description'  => $desc,
                'created_at'   => $now,
                'updated_at'   => $now,
            ]);
        }
    }

    public function down()
    {
        if (!Schema::connection('tenant')->hasTable('quotation_config')) {
            return;
        }

        DB::connection('tenant')
            ->table('quotation_config')
            ->where('config_key', 'LIKE', 'defop\\_%')
            ->delete();
    }
}
