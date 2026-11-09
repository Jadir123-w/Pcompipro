<?php

namespace Modules\Marketing\Services;

use Illuminate\Support\Facades\Log;
use Modules\Marketing\Contracts\AIProviderInterface;
use Modules\Marketing\Exceptions\AIProviderException;
use Modules\Marketing\Models\Grid;
use Modules\Marketing\Models\GridItem;
use Modules\Marketing\Models\LearningPattern;
use Modules\Marketing\Models\ExtraordinaryContent;
use Modules\Marketing\Models\PlatformConfig;
use Modules\Marketing\Models\FeriaReceived;

/**
 * GridGeneratorEngine — Generador de grillas editoriales (MKT-03)
 *
 * Construye grillas nuevas combinando patrones aprendidos (70%) con
 * mejoras editoriales (30%). Adapta por red social y respeta
 * la configuración de cada plataforma.
 */
class GridGeneratorEngine
{
    /** @var AIProviderInterface */
    private $provider;

    /** @var string */
    private $systemPromptTemplate;

    /** @var EditorialGuidelinesService|null */
    private $guidelines;

    public function __construct(AIProviderInterface $provider, EditorialGuidelinesService $guidelines = null)
    {
        $this->provider = $provider;
        $this->guidelines = $guidelines ?: new EditorialGuidelinesService();
        $this->systemPromptTemplate = $this->loadSystemPrompt();
    }

    /**
     * Generar una grilla para un rango de fechas y una o varias redes.
     *
     * @param  array  $params   [
     *     'fecha_desde'    => 'YYYY-MM-DD',
     *     'fecha_hasta'    => 'YYYY-MM-DD',
     *     'redes'          => ['facebook', 'instagram', ...],
     *     'tipo_periodo'   => 'mensual' | 'trimestral' | ...,
     *     'items_existentes' => [] (opcional, para evitar duplicados),
     * ]
     * @return array              Respuesta parseada del LLM
     */
    public function generateGrid(array $params)
    {
        $fechaDesde = isset($params['fecha_desde']) ? $params['fecha_desde'] : null;
        $fechaHasta = isset($params['fecha_hasta']) ? $params['fecha_hasta'] : null;
        $redes      = isset($params['redes']) ? $params['redes'] : [];

        if (!$fechaDesde || !$fechaHasta || empty($redes)) {
            return $this->fallbackResponse('Parámetros incompletos (fecha_desde, fecha_hasta y redes son obligatorios)');
        }

        // Construir contexto: patrones + configs + ferias
        $context = $this->buildContext($params);

        $messages = [
            [
                'role'    => 'user',
                'content' => $context,
            ],
        ];

        // Inyectar las guidelines editoriales consolidadas (M4) al system prompt.
        // Si la solicitud cubre 1 sola red, agregamos su sección específica;
        // si cubre múltiples, se agrega solo el bloque global (cada red maneja
        // su sección a través de PieceProducerEngine en el momento de producir).
        $redForAddendum = (count($redes) === 1) ? $redes[0] : null;
        $effectiveSystemPrompt = $this->systemPromptTemplate
            . $this->guidelines->buildSystemPromptAddendum($redForAddendum);

        try {
            $rawResponse = $this->provider->sendMessage($effectiveSystemPrompt, $messages, [
                'temperature' => 0.4, // Algo de creatividad, pero consistente
                // El JSON de grilla es voluminoso (un objeto por cada día del periodo) y los modelos
                // 2.5+ con thinking tokens internos consumen más espacio del esperado. Con 4000
                // se cortaba el JSON a la mitad. 16000 deja margen seguro para 90 días × 2 redes.
                'max_tokens'  => 16000,
            ]);
        } catch (AIProviderException $e) {
            Log::error('GridGeneratorEngine: error del proveedor', [
                'provider' => $e->getProvider(),
                'message'  => $e->getMessage(),
            ]);
            return $this->fallbackResponse('Error del proveedor IA: ' . $e->getMessage());
        }

        $parsed = $this->parseResponse($rawResponse);
        if ($parsed === null) {
            Log::warning('GridGeneratorEngine: respuesta no es JSON válido, reintentando');
            $parsed = $this->retryWithCorrection($messages, $rawResponse, $effectiveSystemPrompt);
        }

        if ($parsed === null) {
            return $this->fallbackResponse('No se pudo generar grilla');
        }

        Log::info('GridGeneratorEngine: grilla generada', [
            'fecha_desde'    => $fechaDesde,
            'fecha_hasta'    => $fechaHasta,
            'redes'          => $redes,
            'items_generados' => isset($parsed['items_generados']) ? count($parsed['items_generados']) : 0,
        ]);

        return $parsed;
    }

    /**
     * Persistir los ítems generados en la grilla objetivo.
     *
     * @param  array  $generationResult  Resultado de generateGrid()
     * @param  Grid   $grid              Grilla a la que se asocian los ítems
     * @return int                        Cantidad de ítems guardados
     */
    public function saveGeneratedItems(array $generationResult, Grid $grid)
    {
        if (!isset($generationResult['items_generados'])) {
            return 0;
        }

        $saved = 0;
        foreach ($generationResult['items_generados'] as $idx => $item) {
            try {
                GridItem::create([
                    'grid_id'            => $grid->id,
                    'fecha_publicacion'  => isset($item['fecha_publicacion']) ? $item['fecha_publicacion'] : null,
                    'red_social'         => isset($item['red_social']) ? $item['red_social'] : null,
                    'tematica'           => isset($item['tematica']) ? $item['tematica'] : null,
                    'campania'           => isset($item['campania']) ? $item['campania'] : null,
                    'temporada'          => isset($item['temporada']) ? $item['temporada'] : null,
                    'evento'             => isset($item['evento']) ? $item['evento'] : null,
                    'objetivo'           => isset($item['objetivo']) ? $item['objetivo'] : null,
                    'linea_comercial'    => isset($item['linea_comercial']) ? $item['linea_comercial'] : null,
                    'tipo_contenido'     => isset($item['tipo_contenido']) ? $item['tipo_contenido'] : null,
                    'prioridad'          => isset($item['prioridad']) ? $item['prioridad'] : 'media',
                    'origen'             => isset($item['origen']) ? $item['origen'] : GridItem::ORIGEN_GRILLA,
                    'fase_feria'         => isset($item['fase_feria']) ? $item['fase_feria'] : null,
                    'item_order'         => isset($item['item_order']) ? (int) $item['item_order'] : ($idx + 1),
                    'observaciones'      => isset($item['observaciones']) ? $item['observaciones'] : null,
                ]);
                $saved++;
            } catch (\Exception $e) {
                Log::warning('GridGeneratorEngine: error guardando ítem', [
                    'error' => $e->getMessage(),
                    'item'  => $item,
                ]);
            }
        }

        return $saved;
    }

    /**
     * Construir el contexto del LLM: patrones + configs + ferias + items existentes.
     *
     * @param  array  $params
     * @return string
     */
    private function buildContext(array $params)
    {
        $fechaDesde = $params['fecha_desde'];
        $fechaHasta = $params['fecha_hasta'];
        $redes      = $params['redes'];
        $tipoPeriodo = isset($params['tipo_periodo']) ? $params['tipo_periodo'] : 'mensual';

        $ctx  = "# SOLICITUD DE GENERACIÓN DE GRILLA\n\n";
        $ctx .= "Rango de fechas: {$fechaDesde} a {$fechaHasta}\n";
        $ctx .= "Tipo de periodo: {$tipoPeriodo}\n";
        $ctx .= "Redes a cubrir: " . implode(', ', $redes) . "\n\n";

        // ── Configuración de las redes solicitadas ──
        $ctx .= "# CONFIGURACIÓN POR RED\n\n";
        foreach ($redes as $red) {
            $cfg = PlatformConfig::where('red_social', $red)->first();
            if ($cfg) {
                $ctx .= "## {$cfg->nombre_publico}\n";
                $ctx .= "- Función editorial: {$cfg->funcion_editorial_principal}\n";
                $ctx .= "- Priorizar: {$cfg->que_priorizar}\n";
                $ctx .= "- Evitar: {$cfg->que_evitar}\n";
                $ctx .= "- Tono: {$cfg->tono_recomendado}\n";
                $ctx .= "- Max copy: {$cfg->max_copy_length} | Max hashtags: {$cfg->max_hashtags}\n\n";
            }
        }

        // ── Patrones aprendidos activos ──
        $ctx .= "# PATRONES APRENDIDOS DEL HISTÓRICO\n\n";
        $patterns = LearningPattern::active()
            ->where(function ($q) use ($redes) {
                $q->whereNull('red_social')
                  ->orWhereIn('red_social', $redes);
            })
            ->conConfianza(50)
            ->orderByDesc('confianza')
            ->get();

        foreach ($patterns as $p) {
            $ctx .= "- [{$p->tipo_patron}]";
            if ($p->red_social) {
                $ctx .= " ({$p->red_social})";
            }
            $ctx .= " {$p->titulo} — confianza: {$p->confianza}%\n";
            if ($p->descripcion) {
                $ctx .= "  " . $p->descripcion . "\n";
            }
            if ($p->datos_patron) {
                $ctx .= "  Datos: " . json_encode($p->datos_patron, JSON_UNESCAPED_UNICODE) . "\n";
            }
        }
        $ctx .= "\n";

        // ── Ferias activas en el rango ──
        $ferias = FeriaReceived::where('estado', FeriaReceived::ESTADO_ACTIVA)
            ->whereBetween('fecha', [$fechaDesde, date('Y-m-d', strtotime($fechaHasta . ' +60 days'))])
            ->get();

        if ($ferias->count() > 0) {
            $ctx .= "# FERIAS ACTIVAS EN RANGO\n\n";
            foreach ($ferias as $f) {
                $ctx .= "- {$f->nombre} (fecha: {$f->fecha->format('Y-m-d')}, sector: {$f->sector}, participación: {$f->tipo_participacion})\n";
                if ($f->objetivo_comunicacional) {
                    $ctx .= "  Objetivo: {$f->objetivo_comunicacional}\n";
                }
            }
            $ctx .= "\n";
            $ctx .= "Genera ítems 2m, 1m y 15d antes de cada feria en las redes correspondientes.\n\n";
        }

        // ── Items existentes para evitar duplicados ──
        if (!empty($params['items_existentes'])) {
            $ctx .= "# ÍTEMS YA EXISTENTES (NO DUPLICAR)\n\n";
            foreach ($params['items_existentes'] as $e) {
                $fecha = isset($e['fecha_publicacion']) ? $e['fecha_publicacion'] : '';
                $red   = isset($e['red_social']) ? $e['red_social'] : '';
                $tema  = isset($e['tematica']) ? $e['tematica'] : '';
                $ctx .= "- {$fecha} | {$red} | {$tema}\n";
            }
            $ctx .= "\n";
        }

        $ctx .= "\n";

        // ── Extraordinarios aprobados para futuras grillas ──
        $extraordinarios = ExtraordinaryContent::where('aprobado_para_futuras_grillas', true)
            ->whereNotNull('aprobado_para_futuras_grillas')
            ->get();

        if ($extraordinarios->count() > 0) {
            $ctx .= "# EXTRAORDINARIOS APROBADOS PARA FUTURAS GRILLAS\n\n";
            foreach ($extraordinarios as $ec) {
                $ctx .= "- ID:{$ec->id} | Clasificación:{$ec->clasificacion}";
                if ($ec->motivo) $ctx .= " | Motivo:{$ec->motivo}";
                $ctx .= "\n";
            }
            $ctx .= "\n";
            $ctx .= "• La IA puede incorporar estos extraordinarios en la grilla generada\n";
            $ctx .= "• No hay bloqueo rígido por red social - pueden aparecer en cualquier red (FB, IG, LI, TT)\n";
            $ctx .= "• El objetivo es que 'alimenten futuras grillas' en general, no hay duplicados exactos forzados\n";
            $ctx .= "• Si un extraordinario ya fue usado en una red específica, la IA preferirá generar alternativas para esa red\n\n";
        }

        $ctx .= "Genera la grilla respetando todos los parámetros y devolviendo el JSON según el esquema indicado en las instrucciones del sistema.";

        return $ctx;
    }

    /**
     * @return string
     */
    private function loadSystemPrompt()
    {
        $path = __DIR__ . '/../Prompts/mkt03-grid-generator-prompt.md';
        if (!file_exists($path)) {
            Log::error('GridGeneratorEngine: mkt03-grid-generator-prompt.md no encontrado');
            return 'Eres un generador de grillas editoriales. Responde en JSON válido.';
        }
        return file_get_contents($path);
    }

    /**
     * Parsear respuesta JSON del LLM, tolerante a wrappers de texto.
     *
     * Maneja:
     *   - Code-fences ```json … ``` o ``` … ``` en cualquier posición
     *   - Prefacio textual antes del JSON ("Aquí va tu respuesta:\n{...}")
     *   - Postfacio después del JSON
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

        // Strip code fences que envuelven todo el texto
        if (preg_match('/^```(?:json)?\s*(.+?)\s*```$/is', $text, $m)) {
            $text = trim($m[1]);
        } elseif (preg_match('/```json\s*(\{.*?\})\s*```/is', $text, $m)) {
            $text = trim($m[1]);
        } elseif (preg_match('/```\s*(\{.*?\})\s*```/is', $text, $m)) {
            $text = trim($m[1]);
        }

        $decoded = json_decode($text, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            // Fallback: extraer { … } del texto (primer { hasta último })
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
        if (!isset($decoded['items_generados'])) {
            return null;
        }
        return $decoded;
    }

    /**
     * @param  array   $messages
     * @param  string  $badResponse
     * @param  string  $systemPrompt
     * @return array|null
     */
    private function retryWithCorrection($messages, $badResponse, $systemPrompt)
    {
        $corrected = $messages;
        $corrected[] = ['role' => 'assistant', 'content' => $badResponse];
        $corrected[] = [
            'role'    => 'user',
            'content' => 'Tu respuesta no es JSON válido. Responde SOLAMENTE con el JSON según el esquema. Sin texto adicional.',
        ];
        try {
            $retry = $this->provider->sendMessage($systemPrompt, $corrected, [
                'temperature' => 0.2,
                'max_tokens'  => 16000,
            ]);
            return $this->parseResponse($retry);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * @param  string  $message
     * @return array
     */
    private function fallbackResponse($message)
    {
        return [
            'resumen_generacion' => $message,
            'items_generados'    => [],
            'advertencias'       => [$message],
            '_fallback'          => true,
        ];
    }

    /**
     * Insertar un extraordinary en una grilla ya existente, asignándole un item_order
     * consecutivo (máximo actual + 1) sin reordenar los items existentes.
     *
     * Esto garantiza que la columna visual "#" sea exactamente igual al item_order de la BD,
     * sin necesidad de UPDATE masivo a los items existentes.
     *
     * @param int $gridId ID de la grilla existente
     * @param int $extraordinaryId ID del extraordinary a insertar
     * @return GridItem El item creado, o null en caso de error
     */
    public function insertExtraordinarioDespuesDeGrilla($gridId, $extraordinaryId)
    {
        // 1. Calcular el próximo item_order: máximo actual de esa grilla + 1
        $maxOrder = GridItem::where('grid_id', $gridId)
            ->max('item_order') ?? 0;

        $nuevoItemOrder = $maxOrder + 1;

        // 2. Obtener el extraordinary para extraer su fecha y otros datos
        $extra = ExtraordinaryContent::find($extraordinaryId);
        if (!$extra) {
            return null;
        }

        // 3. Insertar el nuevo GridItem con el item_order calculado
        GridItem::create([
            'grid_id'           => $gridId,
            'extraordinary_id'  => $extraordinaryId,
            'origen'            => 'extraordinario',
            'fecha_publicacion' => $extra->fecha,
            'item_order'        => $nuevoItemOrder,
            'red_social'        => $extra->red_social ?? 'facebook',
            'tematica'          => $extra->descripcion ? substr($extra->descripcion, 0, 50) : 'Contenido extraordinario',
            'objetivo'          => 'presencia_institucional',
            'linea_comercial'   => 'Kits Corporativos',
            'tipo_contenido'    => 'Promoción',
            'prioridad'         => 'media',
            'observaciones'     => $extra->motivo,
        ]);

    }
}
