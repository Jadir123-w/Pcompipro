<?php

namespace Modules\Marketing\Services;

use App\Models\Tenant\Item;
use Illuminate\Support\Facades\Log;

/**
 * ProductMatchingService — COM-02 S1
 *
 * Dado un nombre de producto + descripción libre que el LLM detectó en un
 * requerimiento, busca el producto más probable en la tabla `items` del ERP
 * y devuelve top-5 candidatos con un score de confianza.
 *
 * Estrategia (sin dependencias externas):
 *   1. Pre-filter SQL con LIKE %tokens% sobre name+description+text_filter
 *      para reducir candidatos a ~50 (evita escanear miles).
 *   2. Scoring fino en PHP combinando:
 *        - exact_match  (40%): query es substring del item.name
 *        - name_sim     (30%): similar_text(query, item.name) / 100
 *        - desc_sim     (10%): similar_text(query, item.description) / 100
 *        - token_overlap(20%): |tokens(q) ∩ tokens(item.fields)| / |tokens(q)|
 *   3. Ranking + decisión:
 *        - top_score >= 0.85 → high confidence, no review
 *        - top_score < 0.40  → no match (item_id=null), no review
 *        - de lo contrario → ambiguous, needs_review=true
 *        - también marca review si top1-top2 < 0.10 (empate cercano)
 *
 * Devuelve siempre estructura uniforme:
 *   [
 *     'best_match'   => null | ['item_id', 'name', 'description', 'internal_id', 'sale_unit_price'],
 *     'confidence'   => float (0-1),
 *     'needs_review' => bool,
 *     'suggestions'  => array<top-5 candidates con score y reason>,
 *     'query'        => string  (lo que se buscó, util para logging),
 *   ]
 */
class ProductMatchingService
{
    /** Score >= este → confianza alta, no review */
    const HIGH_CONFIDENCE_THRESHOLD = 0.85;

    /** Score < este → no se considera un match real */
    const NO_MATCH_THRESHOLD = 0.40;

    /** Si top1 - top2 < esta diferencia → review (empate cercano) */
    const TIE_GAP = 0.10;

    /** Cuántos candidatos sugerimos máximo */
    const MAX_SUGGESTIONS = 5;

    /** Pre-filter SQL: cuántos candidatos pasamos al scoring fino */
    const PREFILTER_LIMIT = 50;

    /**
     * Buscar el producto más probable.
     *
     * @param  string       $productName        nombre detectado por el LLM
     * @param  string|null  $productDescription descripción libre opcional
     * @param  array        $context            ['categoria_id' => int, 'item_type_id' => string]
     * @return array  ver doc del bloque
     */
    public function match($productName, $productDescription = null, array $context = [])
    {
        $primary = trim((string) $productName);
        $primaryNorm = $this->normalize($primary);

        // El bag de tokens incluye también la descripción (si existe) para
        // ampliar el overlap, pero el matching exacto y similar_text se hacen
        // contra el productName limpio para no penalizar.
        $enrichedRaw = $primary;
        if ($productDescription) {
            $enrichedRaw .= ' ' . trim((string) $productDescription);
        }
        $enrichedTokens = $this->tokenize($this->normalize($enrichedRaw));

        if ($primaryNorm === '' || empty($enrichedTokens)) {
            return $this->buildResponse(null, 0.0, false, [], $enrichedRaw);
        }

        // 1) Pre-filter SQL para reducir candidatos
        $candidates = $this->prefilterFromDB($enrichedRaw, $context);
        if ($candidates->isEmpty()) {
            return $this->buildResponse(null, 0.0, false, [], $enrichedRaw);
        }

        // 2) Score fino en PHP
        $scored = [];
        foreach ($candidates as $item) {
            $scoreData = $this->scoreItem($item, $primaryNorm, $enrichedTokens, $productName);
            $scored[] = [
                'item'     => $item,
                'score'    => $scoreData['score'],
                'breakdown' => $scoreData['breakdown'],
                'reason'   => $scoreData['reason'],
            ];
        }

        // 3) Ordenar desc por score
        usort($scored, function ($a, $b) {
            if ($a['score'] === $b['score']) return 0;
            return $a['score'] < $b['score'] ? 1 : -1;
        });

        $top = array_slice($scored, 0, self::MAX_SUGGESTIONS);
        $topScore = $top[0]['score'];
        $top2Score = isset($top[1]) ? $top[1]['score'] : 0.0;

        // 4) Decidir
        $needsReview = false;
        $bestMatch   = null;
        $confidence  = $topScore;

        if ($topScore >= self::HIGH_CONFIDENCE_THRESHOLD) {
            // Match claro
            $bestMatch   = $this->itemSummary($top[0]['item']);
            $needsReview = false;
        } elseif ($topScore < self::NO_MATCH_THRESHOLD) {
            // No hay un match razonable
            $bestMatch   = null;
            $needsReview = false;  // No tiene sentido que la ejecutiva revise sugerencias malas
            $confidence  = $topScore;
        } else {
            // Score medio: ambiguo → revisar
            $bestMatch   = $this->itemSummary($top[0]['item']);
            $needsReview = true;
        }

        // Caso especial: top score alto pero el siguiente está MUY cerca → review igual
        if ($topScore >= self::HIGH_CONFIDENCE_THRESHOLD && ($topScore - $top2Score) < self::TIE_GAP && $top2Score >= self::NO_MATCH_THRESHOLD) {
            $needsReview = true;
        }

        // Construir suggestions array (top-5)
        $suggestions = [];
        foreach ($top as $entry) {
            if ($entry['score'] < self::NO_MATCH_THRESHOLD) continue;
            $sum = $this->itemSummary($entry['item']);
            $sum['score']     = round($entry['score'], 3);
            $sum['reason']    = $entry['reason'];
            $sum['breakdown'] = $entry['breakdown'];
            $suggestions[] = $sum;
        }

        Log::info('ProductMatchingService: match resuelto', [
            'query'        => $primary,
            'top_score'    => $topScore,
            'top2_score'   => $top2Score,
            'needs_review' => $needsReview,
            'best_id'      => $bestMatch ? $bestMatch['item_id'] : null,
            'candidates'   => count($candidates),
        ]);

        return $this->buildResponse($bestMatch, $confidence, $needsReview, $suggestions, $primary);
    }

    /**
     * Búsqueda manual desde la UI: dada una query libre, devolver candidatos
     * sin la lógica de auto-match. Usado por el endpoint /products/search
     * cuando la ejecutiva quiere buscar otro producto que la IA no propuso.
     *
     * @param  string  $query
     * @param  array   $context
     * @param  int     $limit
     * @return array  Lista plana de candidatos con score
     */
    public function search($query, array $context = [], $limit = 20)
    {
        $query = trim((string) $query);
        if ($query === '' || mb_strlen($query) < 2) {
            return [];
        }

        $candidates = $this->prefilterFromDB($query, $context, max($limit * 2, 30));
        if ($candidates->isEmpty()) {
            return [];
        }

        $primaryNorm = $this->normalize($query);
        $tokens      = $this->tokenize($primaryNorm);

        $scored = [];
        foreach ($candidates as $item) {
            $scoreData = $this->scoreItem($item, $primaryNorm, $tokens, $query);
            // Para búsqueda manual, devolvemos también scores bajos pero ordenados
            $sum = $this->itemSummary($item);
            $sum['score']  = round($scoreData['score'], 3);
            $sum['reason'] = $scoreData['reason'];
            $scored[] = $sum;
        }

        usort($scored, function ($a, $b) {
            if ($a['score'] === $b['score']) return 0;
            return $a['score'] < $b['score'] ? 1 : -1;
        });

        return array_slice($scored, 0, $limit);
    }

    /*
    |--------------------------------------------------------------------------
    | Internals
    |--------------------------------------------------------------------------
    */

    /**
     * Pre-filtro SQL: traer ~50 candidatos que matchean por LIKE.
     * Más rápido que escanear toda la tabla en PHP.
     *
     * @param  string  $query
     * @param  array   $context
     * @param  int     $limit
     * @return \Illuminate\Database\Eloquent\Collection
     */
    private function prefilterFromDB($query, array $context = [], $limit = self::PREFILTER_LIMIT)
    {
        $tokens = $this->tokenize($this->normalize($query));
        if (empty($tokens)) {
            return collect();
        }

        $q = Item::query()
            ->where('active', true)
            ->select(['id', 'name', 'second_name', 'description', 'text_filter', 'internal_id', 'item_code', 'sale_unit_price', 'item_type_id', 'unit_type_id']);

        // OR cualquiera de los tokens contra cualquiera de los campos textuales
        $q->where(function ($outer) use ($tokens) {
            foreach ($tokens as $token) {
                if (mb_strlen($token) < 2) continue;
                $like = '%' . $token . '%';
                $outer->orWhere('name', 'LIKE', $like)
                      ->orWhere('second_name', 'LIKE', $like)
                      ->orWhere('description', 'LIKE', $like)
                      ->orWhere('text_filter', 'LIKE', $like)
                      ->orWhere('internal_id', 'LIKE', $like);
            }
        });

        if (!empty($context['item_type_id'])) {
            $q->where('item_type_id', $context['item_type_id']);
        }

        return $q->limit($limit)->get();
    }

    /**
     * Calcular score de un item respecto a la query.
     *
     * IMPORTANTE: $primaryNorm es el productName limpio (sin descripción) para
     * calcular exact y similar_text. $enrichedTokens incluye la descripción
     * para ampliar el overlap.
     *
     * @param  Item    $item
     * @param  string  $primaryNorm     productName normalizado
     * @param  array   $enrichedTokens  tokens de productName + description
     * @param  string  $rawProductName  nombre original (para logs)
     * @return array  ['score' => float, 'breakdown' => array, 'reason' => string]
     */
    private function scoreItem($item, $primaryNorm, array $enrichedTokens, $rawProductName)
    {
        $itemNameNorm = $this->normalize((string) $item->name);
        $itemDescNorm = $this->normalize((string) $item->description);
        $itemFullNorm = trim($itemNameNorm . ' ' . $itemDescNorm . ' ' . $this->normalize((string) $item->text_filter));

        $itemTokens = $this->tokenize($itemFullNorm);

        // 1) Exact match — productName es substring del item.name, o viceversa
        // Esta es la señal más fuerte: si "polo" aparece literalmente en el
        // nombre del item, alta probabilidad de match.
        $exact = 0.0;
        if ($itemNameNorm !== '' && $primaryNorm !== '') {
            if (strpos($itemNameNorm, $primaryNorm) !== false) {
                // Toda la query está dentro del nombre del item.
                // Boost extra si la query es sustancial (>=4 chars).
                $exact = mb_strlen($primaryNorm) >= 4 ? 1.0 : 0.85;
            } elseif (strpos($primaryNorm, $itemNameNorm) !== false && mb_strlen($itemNameNorm) >= 4) {
                // El nombre del item es contenido en la query (ej: query="100 mochilas eco" contiene "mochila")
                $exact = 0.90;
            }
        }

        // 2) similar_text contra el nombre del item (productName vs item.name)
        $nameSim = 0.0;
        if ($itemNameNorm !== '' && $primaryNorm !== '') {
            similar_text($primaryNorm, $itemNameNorm, $pct);
            $nameSim = $pct / 100.0;
        }

        // 3) similar_text contra la descripción
        $descSim = 0.0;
        if ($itemDescNorm !== '' && $primaryNorm !== '') {
            similar_text($primaryNorm, $itemDescNorm, $pctd);
            $descSim = $pctd / 100.0;
        }

        // 4) Token overlap (usa los tokens enriquecidos con descripción)
        $tokenOverlap = 0.0;
        if (!empty($enrichedTokens) && !empty($itemTokens)) {
            $itemSet = array_flip($itemTokens);
            $matches = 0;
            foreach ($enrichedTokens as $t) {
                if (isset($itemSet[$t])) $matches++;
            }
            $tokenOverlap = $matches / count($enrichedTokens);
        }

        // Weighted combination — exact pesa más porque es la señal más fuerte
        $score = (0.50 * $exact) + (0.20 * $nameSim) + (0.05 * $descSim) + (0.25 * $tokenOverlap);
        $score = max(0.0, min(1.0, $score));

        // Razón legible para mostrar en UI
        $reasons = [];
        if ($exact >= 1.0) $reasons[] = 'nombre coincide exactamente';
        elseif ($exact >= 0.85) $reasons[] = 'nombre coincide en gran parte';
        if ($nameSim >= 0.7 && $exact < 0.85) $reasons[] = 'nombre muy similar';
        if ($tokenOverlap >= 0.7 && $exact < 0.85) $reasons[] = 'comparten varias palabras clave';
        if (empty($reasons) && $score > 0) $reasons[] = 'similitud parcial';
        $reason = implode(' · ', $reasons) ?: 'baja similitud';

        return [
            'score'     => $score,
            'breakdown' => [
                'exact'         => round($exact, 3),
                'name_sim'      => round($nameSim, 3),
                'desc_sim'      => round($descSim, 3),
                'token_overlap' => round($tokenOverlap, 3),
            ],
            'reason' => $reason,
        ];
    }

    /**
     * Normalizar texto: lowercase, strip tildes, remove punctuación.
     *
     * @param  string  $text
     * @return string
     */
    private function normalize($text)
    {
        $text = (string) $text;
        if ($text === '') return '';

        // Lowercase
        $text = mb_strtolower($text, 'UTF-8');

        // Strip tildes (NFD descomposición + remover marks)
        $text = $this->stripAccents($text);

        // Remover puntuación, dejar letras/números/espacios
        $text = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $text);

        // Colapsar espacios
        $text = preg_replace('/\s+/u', ' ', $text);

        return trim($text);
    }

    /**
     * Strip accents: á→a, é→e, ñ→n, etc.
     *
     * @param  string  $text
     * @return string
     */
    private function stripAccents($text)
    {
        $map = [
            'á'=>'a','à'=>'a','ä'=>'a','â'=>'a','ã'=>'a','å'=>'a',
            'é'=>'e','è'=>'e','ë'=>'e','ê'=>'e',
            'í'=>'i','ì'=>'i','ï'=>'i','î'=>'i',
            'ó'=>'o','ò'=>'o','ö'=>'o','ô'=>'o','õ'=>'o',
            'ú'=>'u','ù'=>'u','ü'=>'u','û'=>'u',
            'ñ'=>'n','ç'=>'c',
        ];
        return strtr($text, $map);
    }

    /**
     * Tokenizar texto normalizado en palabras útiles.
     * Filtra stopwords cortas y números muy genéricos.
     *
     * @param  string  $text
     * @return array
     */
    private function tokenize($text)
    {
        if ($text === '') return [];

        $stopwords = [
            'el', 'la', 'los', 'las', 'un', 'una', 'unos', 'unas',
            'de', 'del', 'al', 'a', 'en', 'con', 'sin', 'por', 'para',
            'y', 'o', 'u', 'e', 'que', 'es', 'son',
            'mi', 'tu', 'su', 'mis', 'tus', 'sus',
            'lo', 'le', 'les',
        ];

        $words = preg_split('/\s+/u', $text);
        $tokens = [];
        foreach ($words as $w) {
            $w = trim($w);
            if ($w === '') continue;
            if (mb_strlen($w) < 2) continue;
            if (in_array($w, $stopwords, true)) continue;
            $tokens[] = $w;
        }
        return array_values(array_unique($tokens));
    }

    /**
     * Resumen del item para el output (sin datos sensibles ni columnas extra).
     *
     * @param  Item  $item
     * @return array
     */
    private function itemSummary($item)
    {
        return [
            'item_id'         => (int) $item->id,
            'name'            => (string) $item->name,
            'description'     => (string) $item->description,
            'internal_id'     => $item->internal_id,
            'item_code'       => $item->item_code,
            'sale_unit_price' => (float) $item->sale_unit_price,
            'item_type_id'    => $item->item_type_id,
            'unit_type_id'    => $item->unit_type_id,
        ];
    }

    /**
     * Builder uniforme de respuesta.
     *
     * @param  array|null  $best
     * @param  float       $confidence
     * @param  bool        $needsReview
     * @param  array       $suggestions
     * @param  string      $query
     * @return array
     */
    private function buildResponse($best, $confidence, $needsReview, array $suggestions, $query)
    {
        return [
            'best_match'   => $best,
            'confidence'   => round($confidence, 3),
            'needs_review' => (bool) $needsReview,
            'suggestions'  => $suggestions,
            'query'        => $query,
        ];
    }
}
