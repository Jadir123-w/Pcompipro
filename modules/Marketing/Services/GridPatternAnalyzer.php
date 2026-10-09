<?php

namespace Modules\Marketing\Services;

use Illuminate\Support\Facades\Log;
use Modules\Marketing\Contracts\AIProviderInterface;
use Modules\Marketing\Exceptions\AIProviderException;
use Modules\Marketing\Models\HistoricalGrid;
use Modules\Marketing\Models\LearningPattern;

/**
 * GridPatternAnalyzer — Analizador de patrones editoriales (MKT-03)
 *
 * Analiza grillas históricas importadas desde Excel y extrae patrones
 * aprovechables para generar nuevas grillas (frecuencias, temporadas,
 * líneas comerciales, tipos de contenido, etc.).
 *
 * Reutiliza la capa de IA (AIProviderInterface) del módulo.
 */
class GridPatternAnalyzer
{
    /** @var AIProviderInterface */
    private $provider;

    /** @var string */
    private $systemPromptTemplate;

    public function __construct(AIProviderInterface $provider)
    {
        $this->provider = $provider;
        $this->systemPromptTemplate = $this->loadSystemPrompt();
    }

    /**
     * Analizar una grilla histórica y extraer patrones.
     *
     * @param  HistoricalGrid  $historicalGrid
     * @return array                            Respuesta parseada del LLM
     */
    public function analyzeHistoricalGrid(HistoricalGrid $historicalGrid)
    {
        $contenido = $historicalGrid->contenido_normalizado ?: [];

        if (empty($contenido)) {
            return $this->fallbackResponse('Grilla histórica sin contenido normalizado');
        }

        // Preparar resumen conciso del contenido para no exceder el contexto del LLM
        $resumen = $this->buildSummaryForLLM($historicalGrid);

        $messages = [
            [
                'role'    => 'user',
                'content' => "Analiza los siguientes datos de una grilla histórica de " . $historicalGrid->anio . ":\n\n" . $resumen,
            ],
        ];

        try {
            $rawResponse = $this->provider->sendMessage($this->systemPromptTemplate, $messages, [
                'temperature' => 0.2, // Determinista para análisis
                // El JSON de respuesta del analyzer es extenso (resumen + N patrones + líneas + hashtags + recomendaciones).
                // Además, los modelos Gemini 2.5+ usan "thinking tokens" internos que cuentan contra max_tokens.
                // Con 3000 se cortaba a media respuesta; con 8000 a veces también para grillas grandes.
                // 16000 deja margen suficiente para el thinking + JSON completo.
                'max_tokens'  => 16000,
            ]);
        } catch (AIProviderException $e) {
            Log::error('GridPatternAnalyzer: error del proveedor', [
                'provider' => $e->getProvider(),
                'message'  => $e->getMessage(),
            ]);
            return $this->fallbackResponse('Error del proveedor IA: ' . $e->getMessage());
        }

        $parsed = $this->parseResponse($rawResponse);
        if ($parsed === null) {
            Log::warning('GridPatternAnalyzer: respuesta no es JSON válido', [
                'raw_preview' => mb_substr((string) $rawResponse, 0, 1000),
                'raw_length'  => strlen((string) $rawResponse),
            ]);
            return $this->fallbackResponse('Respuesta no parseable');
        }

        Log::info('GridPatternAnalyzer: análisis completado', [
            'historical_grid_id' => $historicalGrid->id,
            'anio'               => $historicalGrid->anio,
            'patrones_detectados' => isset($parsed['patrones_detectados']) ? count($parsed['patrones_detectados']) : 0,
        ]);

        return $parsed;
    }

    /**
     * Persistir patrones detectados en la tabla mkt_learning_patterns.
     *
     * @param  array            $analysisResult  Resultado de analyzeHistoricalGrid
     * @param  HistoricalGrid   $historicalGrid
     * @return int                               Cantidad de patrones guardados
     */
    public function savePatterns(array $analysisResult, HistoricalGrid $historicalGrid)
    {
        if (!isset($analysisResult['patrones_detectados'])) {
            return 0;
        }

        $saved = 0;
        foreach ($analysisResult['patrones_detectados'] as $p) {
            try {
                LearningPattern::create([
                    'fuente'              => LearningPattern::FUENTE_HISTORICO,
                    'historical_grid_id'  => $historicalGrid->id,
                    'tipo_patron'         => isset($p['tipo_patron']) ? $p['tipo_patron'] : LearningPattern::TIPO_FRECUENCIA,
                    'red_social'          => isset($p['red_social']) ? $p['red_social'] : null,
                    'titulo'              => isset($p['titulo']) ? $p['titulo'] : 'Patrón sin título',
                    'descripcion'         => isset($p['descripcion']) ? $p['descripcion'] : null,
                    'datos_patron'        => isset($p['datos_patron']) ? json_encode($p['datos_patron']) : null,
                    'ocurrencias'         => isset($p['ocurrencias']) ? (int) $p['ocurrencias'] : 1,
                    'confianza'           => isset($p['confianza']) ? (float) $p['confianza'] : 50,
                    'active'              => true,
                    'aprobado_por_usuario' => false,
                ]);
                $saved++;
            } catch (\Exception $e) {
                Log::warning('GridPatternAnalyzer: error guardando patrón', [
                    'error' => $e->getMessage(),
                    'patron' => $p,
                ]);
            }
        }

        Log::info('GridPatternAnalyzer: patrones guardados', [
            'historical_grid_id' => $historicalGrid->id,
            'total_guardados'    => $saved,
        ]);

        return $saved;
    }

    /**
     * Cargar el system prompt desde archivo.
     *
     * @return string
     */
    private function loadSystemPrompt()
    {
        $path = __DIR__ . '/../Prompts/mkt03-grid-analyzer-prompt.md';
        if (!file_exists($path)) {
            Log::error('GridPatternAnalyzer: mkt03-grid-analyzer-prompt.md no encontrado');
            return 'Eres un analizador de patrones editoriales. Responde en JSON válido.';
        }
        return file_get_contents($path);
    }

    /**
     * Construir un resumen conciso del histórico para enviar al LLM.
     *
     * El contenido de un planner puede ser muy grande; aquí producimos un
     * resumen estructurado con conteos y muestras para no saturar el contexto.
     *
     * @param  HistoricalGrid  $hg
     * @return string
     */
    private function buildSummaryForLLM(HistoricalGrid $hg)
    {
        $contenido = $hg->contenido_normalizado ?: [];
        $redes     = $hg->redes_detectadas ?: [];

        $summary  = "Año: {$hg->anio}\n";
        $summary .= "Total ítems: " . count($contenido) . "\n";
        $summary .= "Redes detectadas: " . implode(', ', $redes) . "\n\n";

        // Agrupar por red y mostrar conteos
        $porRed = [];
        foreach ($contenido as $item) {
            $red = isset($item['red_social']) ? $item['red_social'] : 'desconocida';
            if (!isset($porRed[$red])) {
                $porRed[$red] = ['count' => 0, 'samples' => []];
            }
            $porRed[$red]['count']++;
            if (count($porRed[$red]['samples']) < 15) {
                $porRed[$red]['samples'][] = $item;
            }
        }

        foreach ($porRed as $red => $info) {
            $summary .= "---\nRed: {$red} ({$info['count']} ítems)\n";
            $summary .= "Muestras (primeras 15):\n";
            foreach ($info['samples'] as $i => $s) {
                $fecha   = isset($s['fecha']) ? $s['fecha'] : '';
                $tema    = isset($s['tematica']) ? substr($s['tematica'], 0, 60) : '';
                $tipo    = isset($s['tipo_contenido']) ? $s['tipo_contenido'] : '';
                $titulo  = isset($s['titulo']) ? substr($s['titulo'], 0, 60) : '';
                $summary .= "  " . ($i + 1) . ". {$fecha} | {$tipo} | {$tema} | {$titulo}\n";
            }
        }

        return $summary;
    }

    /**
     * Parsear respuesta JSON del LLM.
     *
     * Estrategia tolerante (los providers a veces envuelven el JSON con
     * prefacio/postfacio o code-fences en distintas posiciones):
     *
     *   1. Strip code-fences ```json … ``` o ``` … ``` aunque estén en medio.
     *   2. Intentar json_decode tal cual.
     *   3. Si falla, buscar el primer "{" y el último "}" y parsear ese substring.
     *
     * @param  string  $raw
     * @return array|null
     */
    private function parseResponse($raw)
    {
        $text = trim((string) $raw);
        if ($text === '') {
            return null;
        }

        // 1) Si toda la respuesta está envuelta en una fence, sacarla.
        if (preg_match('/^```(?:json)?\s*(.+?)\s*```$/is', $text, $m)) {
            $text = trim($m[1]);
        } else {
            // 1b) O si hay una fence de json en medio, extraerla
            if (preg_match('/```json\s*(\{.*?\})\s*```/is', $text, $m)) {
                $text = trim($m[1]);
            } elseif (preg_match('/```\s*(\{.*?\})\s*```/is', $text, $m)) {
                $text = trim($m[1]);
            }
        }

        // 2) Intentar parsear tal cual
        $decoded = json_decode($text, true);

        // 3) Si falla, intentar recortar al primer { y último } balanceados
        if (json_last_error() !== JSON_ERROR_NONE) {
            $first = strpos($text, '{');
            $last  = strrpos($text, '}');
            if ($first !== false && $last !== false && $last > $first) {
                $candidate = substr($text, $first, $last - $first + 1);
                $decoded = json_decode($candidate, true);
            }
        }

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            return null;
        }

        if (!isset($decoded['patrones_detectados'])) {
            return null;
        }

        return $decoded;
    }

    /**
     * @param  string  $message
     * @return array
     */
    private function fallbackResponse($message)
    {
        return [
            'resumen_analisis'     => $message,
            'patrones_detectados'  => [],
            'warnings'             => [$message],
            '_fallback'            => true,
        ];
    }
}
