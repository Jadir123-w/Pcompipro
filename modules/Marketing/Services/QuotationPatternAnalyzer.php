<?php

namespace Modules\Marketing\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Marketing\Models\PreQuoteItem;
use Modules\Marketing\Models\QuotationConfig;
use Modules\Marketing\Models\QuotationSystemSuggestion;

/**
 * QuotationPatternAnalyzer — COM-02 S4
 *
 * Detecta patrones recurrentes en el histórico de cotizaciones validadas
 * y propone sugerencias de mejora de configuración.
 *
 * Patrones detectados (sec 30 doc funcional):
 *   1. Técnica por categoría — si una técnica aparece >70% en items de
 *      una categoría y no es la sugerida actualmente.
 *   2. Empaque por categoría — mismo patrón que técnica.
 *   3. Margen general — si el margen promedio difiere del default >15%.
 *
 *   (COM08: el patrón de "tiempo por categoría" se retiró — tiempo_estimado_dias
 *    pasó a texto libre y la mediana numérica de días dejó de tener sentido.)
 *
 * Threshold de evidencia (defop_pattern_min_evidence, default 5): mínimo
 * de items que respaldan el patrón para que se genere una sugerencia.
 *
 * Idempotencia: si ya existe una sugerencia pending con el mismo
 * target_config_key + suggested_value, la actualiza (refresh evidence
 * count) en lugar de crear duplicado.
 */
class QuotationPatternAnalyzer
{
    const CATEGORIES = ['textil', 'impresos', 'eco', 'packs', 'otros'];

    /** Frecuencia mínima (0-1) para sugerir cambio de técnica/empaque */
    const FREQUENCY_THRESHOLD = 0.70;

    /** Diferencia mínima (porcentual) para sugerir cambio de margen */
    const MARGIN_DIFF_THRESHOLD = 0.15;

    /**
     * Ejecuta el análisis completo.
     *
     * @return array{
     *   detected: int,        suggestions generadas + actualizadas
     *   created: int,         nuevas
     *   updated: int,         existentes refrescadas
     *   patterns_examined: int,
     * }
     */
    public function analyze()
    {
        $stats = [
            'detected'          => 0,
            'created'           => 0,
            'updated'           => 0,
            'patterns_examined' => 0,
        ];

        $minEvidence = (int) QuotationConfig::getValue('defop_pattern_min_evidence', 5);
        if ($minEvidence < 1) $minEvidence = 5;

        // Por cada categoría, analizar 2 patrones (técnica + empaque).
        // COM08 — el patrón de "tiempo por categoría" se retiró: tiempo_estimado_dias
        // pasó a ser texto libre (ej. "Mié 02 Abril *"), por lo que calcular la
        // mediana numérica de días dejó de tener sentido.
        foreach (self::CATEGORIES as $cat) {
            $stats['patterns_examined'] += 2;

            $tecnica = $this->analyzeTecnicaForCategoria($cat, $minEvidence);
            if ($tecnica) $this->upsertSuggestion($tecnica, $stats);

            $empaque = $this->analyzeEmpaqueForCategoria($cat, $minEvidence);
            if ($empaque) $this->upsertSuggestion($empaque, $stats);
        }

        // Patrón de margen (1 sólo, general)
        $stats['patterns_examined']++;
        $margen = $this->analyzeMargenGeneral($minEvidence);
        if ($margen) $this->upsertSuggestion($margen, $stats);

        Log::info('QuotationPatternAnalyzer: análisis completado', $stats);
        return $stats;
    }

    /*
    |--------------------------------------------------------------------------
    | Detectores
    |--------------------------------------------------------------------------
    */

    /**
     * Técnica: si una técnica de impresión aparece en >70% de items de
     * la categoría y no coincide con el default configurado.
     */
    private function analyzeTecnicaForCategoria($cat, $minEvidence)
    {
        $items = $this->getValidatedItems()
            ->whereNotNull('tecnica_impresion')
            ->where('tecnica_impresion', '<>', '');

        $items = $this->filterByCategoryHeuristic($items, $cat);
        if ($items->count() < $minEvidence) return null;

        $tecnicas = $items->pluck('tecnica_impresion')->map(function ($t) {
            return mb_strtolower(trim($t));
        })->all();

        $modeData = $this->modeWithRatio($tecnicas);
        if ($modeData['ratio'] < self::FREQUENCY_THRESHOLD) return null;

        $configKey = 'defop_tecnica_' . $cat;
        $current = mb_strtolower(trim((string) QuotationConfig::getValue($configKey, '')));
        if ($current === $modeData['value']) return null;  // ya coincide
        if ($modeData['value'] === '') return null;

        return [
            'type'              => QuotationSystemSuggestion::TYPE_TECNICA_CATEGORIA,
            'category'          => $cat,
            'target_config_key' => $configKey,
            'current_value'     => $current,
            'suggested_value'   => $modeData['value'],
            'evidence_count'    => count($tecnicas),
            'confidence'        => round($modeData['ratio'], 2),
            'reasoning'         => sprintf(
                'En %d cotizaciones validadas de categoría "%s", la técnica "%s" aparece en %.0f%% de los casos, pero el default actual es "%s".',
                count($tecnicas), $cat, $modeData['value'], $modeData['ratio'] * 100, $current ?: '(sin default)'
            ),
        ];
    }

    /**
     * Empaque: mismo patrón que técnica.
     */
    private function analyzeEmpaqueForCategoria($cat, $minEvidence)
    {
        $items = $this->getValidatedItems()
            ->whereNotNull('empaque')
            ->where('empaque', '<>', '');

        $items = $this->filterByCategoryHeuristic($items, $cat);
        if ($items->count() < $minEvidence) return null;

        $empaques = $items->pluck('empaque')->map(function ($e) {
            return mb_strtolower(trim($e));
        })->all();

        $modeData = $this->modeWithRatio($empaques);
        if ($modeData['ratio'] < self::FREQUENCY_THRESHOLD) return null;

        $configKey = 'defop_empaque_' . $cat;
        $current = mb_strtolower(trim((string) QuotationConfig::getValue($configKey, '')));
        if ($current === $modeData['value']) return null;
        if ($modeData['value'] === '') return null;

        return [
            'type'              => QuotationSystemSuggestion::TYPE_EMPAQUE_CATEGORIA,
            'category'          => $cat,
            'target_config_key' => $configKey,
            'current_value'     => $current,
            'suggested_value'   => $modeData['value'],
            'evidence_count'    => count($empaques),
            'confidence'        => round($modeData['ratio'], 2),
            'reasoning'         => sprintf(
                'En %d cotizaciones validadas de categoría "%s", el empaque "%s" aparece en %.0f%% de los casos, pero el default actual es "%s".',
                count($empaques), $cat, $modeData['value'], $modeData['ratio'] * 100, $current ?: '(sin default)'
            ),
        ];
    }

    /**
     * Margen: el margen promedio en cotizaciones validadas difiere del
     * default >15%.
     */
    private function analyzeMargenGeneral($minEvidence)
    {
        $items = $this->getValidatedItems()
            ->whereNotNull('margen_porcentaje')
            ->where('margen_porcentaje', '>', 0);

        if ($items->count() < $minEvidence) return null;

        $margenes = $items->pluck('margen_porcentaje')->map(function ($v) { return (float) $v; })->all();
        $promedio = array_sum($margenes) / count($margenes);

        $configKey = 'default_margin_percentage';
        $current = (float) QuotationConfig::getValue($configKey, 0);
        if ($current <= 0) return null;

        $diff = abs($promedio - $current) / $current;
        if ($diff < self::MARGIN_DIFF_THRESHOLD) return null;

        $confidence = min(1.0, $diff * 2);  // x2 porque el margen es más ruidoso
        return [
            'type'              => QuotationSystemSuggestion::TYPE_MARGEN_CATEGORIA,
            'category'          => null,
            'target_config_key' => $configKey,
            'current_value'     => (string) $current,
            'suggested_value'   => (string) round($promedio, 1),
            'evidence_count'    => count($margenes),
            'confidence'        => round($confidence, 2),
            'reasoning'         => sprintf(
                'En %d ítems validados, el margen promedio fue %.1f%%, %.0f%% distinto del default (%.1f%%).',
                count($margenes), $promedio, $diff * 100, $current
            ),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Internals
    |--------------------------------------------------------------------------
    */

    /**
     * Query base: items de prequotes validadas/aprobadas dentro del lookback.
     */
    private function getValidatedItems()
    {
        $lookbackMonths = (int) QuotationConfig::getValue('defop_historico_meses_lookback', 12);
        if ($lookbackMonths < 1) $lookbackMonths = 12;
        $threshold = now()->subMonths($lookbackMonths);

        return PreQuoteItem::query()
            ->whereHas('prequote', function ($q) use ($threshold) {
                $q->whereIn('estado', ['validada', 'aprobada'])
                  ->where('created_at', '>=', $threshold);
            });
    }

    /**
     * Heurística: filtrar items por categoría usando el mismo mapping del
     * Orchestrator (textil/impresos/eco/packs/otros) sobre product_name.
     *
     * Hace string LIKE de keywords típicas. No es 100% preciso pero alcanza
     * para detectar tendencias.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  string  $cat
     * @return \Illuminate\Support\Collection
     */
    private function filterByCategoryHeuristic($query, $cat)
    {
        $keywords = [
            'textil'   => ['textil', 'mochila', 'polo', 'casaca', 'gorr', 'pole', 'camiseta'],
            'impresos' => ['impreso', 'libreta', 'agenda', 'cuaderno', 'volante', 'flyer', 'tarjeta'],
            'eco'      => ['eco', 'reciclad', 'kraft', 'bambu', 'biodegrad'],
            'packs'    => ['pack', 'kit', 'combo'],
            'otros'    => null, // catch-all: items que NO matchean ninguno
        ];

        if ($cat === 'otros') {
            // Items que NO matchean ninguna keyword de las otras categorías
            $allOtherKeywords = [];
            foreach ($keywords as $k => $kws) {
                if ($k === 'otros' || !$kws) continue;
                foreach ($kws as $kw) $allOtherKeywords[] = $kw;
            }
            return $query->get()->filter(function ($item) use ($allOtherKeywords) {
                $name = mb_strtolower((string) $item->product_name);
                foreach ($allOtherKeywords as $kw) {
                    if (mb_strpos($name, $kw) !== false) return false;
                }
                return true;
            })->values();
        }

        $kws = $keywords[$cat] ?? [];
        if (empty($kws)) return collect();

        $query = $query->where(function ($q) use ($kws) {
            foreach ($kws as $kw) {
                $q->orWhere('product_name', 'LIKE', '%' . $kw . '%');
            }
        });

        return $query->get();
    }

    /**
     * Moda + ratio de aparición.
     *
     * @param  array  $values  String/normalized values
     * @return array  ['value' => string, 'ratio' => float, 'count' => int]
     */
    private function modeWithRatio(array $values)
    {
        if (empty($values)) return ['value' => '', 'ratio' => 0.0, 'count' => 0];

        $counts = array_count_values($values);
        arsort($counts);
        $top = array_key_first($counts);
        $topCount = $counts[$top];
        $ratio = $topCount / count($values);

        return ['value' => (string) $top, 'ratio' => $ratio, 'count' => $topCount];
    }

    /**
     * Inserta o actualiza una sugerencia idempotente:
     * Si existe una con el mismo target_config_key en estado pending → la
     * actualiza (refresh evidence).
     * Si existe una en estado applied/rejected con el mismo
     * suggested_value → no hace nada (ya fue decidida).
     * Si existe snoozed → la respeta hasta que pase el snoozed_until.
     */
    private function upsertSuggestion(array $data, array &$stats)
    {
        $existing = QuotationSystemSuggestion::query()
            ->where('target_config_key', $data['target_config_key'])
            ->where('suggested_value', $data['suggested_value'])
            ->orderBy('id', 'desc')
            ->first();

        if ($existing) {
            // Si ya está aplicada o rechazada con este mismo valor sugerido,
            // no insertamos otra duplicada.
            if (in_array($existing->status, [QuotationSystemSuggestion::STATUS_APPLIED, QuotationSystemSuggestion::STATUS_REJECTED], true)) {
                return;
            }
            // Si está snoozed y aún no venció, también la respetamos
            if ($existing->status === QuotationSystemSuggestion::STATUS_SNOOZED
                && $existing->snoozed_until && $existing->snoozed_until->isFuture()) {
                return;
            }
            // Caso restante (pending o snoozed-ya-vencido): actualizar
            $existing->update([
                'evidence_count' => $data['evidence_count'],
                'confidence'     => $data['confidence'],
                'reasoning'      => $data['reasoning'],
                'current_value'  => $data['current_value'],
                'status'         => QuotationSystemSuggestion::STATUS_PENDING,
                'snoozed_until'  => null,
            ]);
            $stats['updated']++;
            $stats['detected']++;
            return;
        }

        QuotationSystemSuggestion::create(array_merge($data, [
            'status' => QuotationSystemSuggestion::STATUS_PENDING,
        ]));
        $stats['created']++;
        $stats['detected']++;
    }
}
