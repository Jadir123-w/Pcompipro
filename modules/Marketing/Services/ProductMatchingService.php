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
 *   2. Puntaje 0..1 contra el NOMBRE REAL del producto (items.description, p. ej.
 *      "CASACA CORTAVIENTO"; items.name suele ser la ficha larga). Es el mayor entre:
 *        - cobertura: palabras de la búsqueda presentes en el nombre real, en cualquier
 *          orden, sin tildes, mayúsculas ni plurales (las que solo están en la ficha
 *          larga valen la mitad);
 *        - parecido del nombre completo (similitudNombres).
 *   3. Solo cuenta lo que llega a MIN_SCORE (80%): por debajo no se muestra ni se
 *      asocia. Si hay más de uno arriba del mínimo y muy parejos → revisar.
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
    /** Mínimo para mostrar un producto o asociarlo (0..1). Más adelante, 0.90. */
    const MIN_SCORE = 0.80;

    /** Score >= este → coincidencia (sin banda intermedia: o llega al mínimo o no cuenta) */
    const HIGH_CONFIDENCE_THRESHOLD = self::MIN_SCORE;

    /** Score < este → no se considera un match real */
    const NO_MATCH_THRESHOLD = self::MIN_SCORE;

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
            // Solo los que llegan al mínimo (antes salían también los de 13%).
            if ($scoreData['score'] < self::MIN_SCORE) {
                continue;
            }
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
            ->select(['id', 'name', 'second_name', 'description', 'text_filter', 'internal_id', 'item_code', 'sale_unit_price', 'item_type_id', 'unit_type_id', 'image_small']);

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
        // Nombre real del producto: en este catálogo es items.description; items.name
        // suele traer la ficha larga. Si no hay descripción, se usa name.
        $nombreReal = trim((string) $item->description) !== '' ? (string) $item->description : (string) $item->name;
        $ficha = trim($item->name . ' ' . $item->second_name . ' ' . $item->text_filter);

        $buscadas = $this->palabrasSingular($primaryNorm);
        if (empty($buscadas)) {
            return ['score' => 0.0, 'breakdown' => [], 'reason' => 'sin palabras para comparar'];
        }
        $enNombre = array_flip($this->palabrasSingular($nombreReal));
        $enFicha = array_flip($this->palabrasSingular($ficha));

        // 1) Cobertura: palabras buscadas presentes en el nombre real (en la ficha, media).
        $puntos = 0.0;
        foreach ($buscadas as $palabra) {
            if (isset($enNombre[$palabra])) {
                $puntos += 1.0;
            } elseif (isset($enFicha[$palabra])) {
                $puntos += 0.5;
            }
        }
        $cobertura = $puntos / count($buscadas);

        // 2) Parecido del nombre completo (mismo criterio que lo ya cotizado).
        $parecido = $this->similitudNombres($rawProductName, $nombreReal);

        $score = max(0.0, min(1.0, max($cobertura, $parecido)));

        if ($cobertura >= 1.0) {
            $reason = 'todas las palabras coinciden';
        } elseif ($parecido >= self::MIN_SCORE) {
            $reason = 'nombre muy similar';
        } elseif ($score > 0) {
            $reason = 'similitud parcial';
        } else {
            $reason = 'baja similitud';
        }

        return [
            'score'     => $score,
            'breakdown' => [
                'cobertura' => round($cobertura, 3),
                'parecido'  => round($parecido, 3),
            ],
            'reason' => $reason,
        ];
    }

    /**
     * Palabras útiles del texto, normalizadas y en singular ("libretas" → "libreta").
     *
     * @param  string  $texto
     * @return array
     */
    private function palabrasSingular($texto)
    {
        return array_values(array_unique(array_map(function ($w) {
            if (mb_strlen($w) > 4 && substr($w, -2) === 'es') return substr($w, 0, -2);
            if (mb_strlen($w) > 3 && substr($w, -1) === 's') return substr($w, 0, -1);
            return $w;
        }, $this->tokenize($this->normalize($texto)))));
    }

    /**
     * Similitud (0..1) entre dos nombres de producto: sin tildes, mayúsculas ni signos, y
     * con las palabras en singular. Es la mayor entre la similitud de texto y la de
     * palabras en común (mismo nombre con las palabras en otro orden = 1).
     *
     * @param  string  $a
     * @param  string  $b
     * @return float
     */
    public function similitudNombres($a, $b)
    {
        $ta = $this->palabrasSingular($a);
        $tb = $this->palabrasSingular($b);
        if (empty($ta) || empty($tb)) return 0.0;

        sort($ta);
        sort($tb);
        similar_text(implode(' ', $ta), implode(' ', $tb), $pct);
        $comunes = count(array_intersect($ta, $tb));
        $jaccard = $comunes / count(array_unique(array_merge($ta, $tb)));

        return max($pct / 100.0, $jaccard);
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
            // Miniatura del catálogo para el buscador (null = sin foto).
            'image_url_small' => $this->catalogImageUrl(isset($item->image_small) ? $item->image_small : null),
        ];
    }

    /**
     * URL pública de la miniatura (misma convención que Ecommerce:
     * storage/uploads/items + placeholder = null).
     *
     * @param  string|null  $filename
     * @return string|null
     */
    private function catalogImageUrl($filename)
    {
        $filename = trim((string) $filename);
        if ($filename === '' || $filename === 'imagen-no-disponible.jpg') return null;
        return asset('storage/uploads/items/' . $filename);
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
