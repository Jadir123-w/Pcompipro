<?php

namespace Modules\Marketing\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Marketing\Models\ExtraordinaryContent;
use Modules\Marketing\Models\FeriaReceived;
use Modules\Marketing\Models\Grid;
use Modules\Marketing\Models\GridItem;
use Modules\Marketing\Models\Piece;

/**
 * MKT03Orchestrator — Coordinador central del agente MKT-03.
 *
 * Gestiona los 6 modos de operación editorial:
 *   A - Crear nueva grilla
 *   B - Consultar grilla existente
 *   C - Ampliar grilla parcial (continuidad automática)
 *   D - Producir pieza final desde ítem
 *   E - Contenido extraordinario
 *   F - Contenido para feria
 */
class MKT03Orchestrator
{
    /** @var GridPatternAnalyzer */
    private $patternAnalyzer;

    /** @var GridGeneratorEngine */
    private $generator;

    /** @var PieceProducerEngine */
    private $producer;

    /** @var GridContinuityService|null  Inyectado por el container; opcional para retro-compat */
    private $continuity;

    public function __construct(
        GridPatternAnalyzer $patternAnalyzer,
        GridGeneratorEngine $generator,
        PieceProducerEngine $producer,
        GridContinuityService $continuity = null
    ) {
        $this->patternAnalyzer = $patternAnalyzer;
        $this->generator = $generator;
        $this->producer = $producer;
        $this->continuity = $continuity ?: new GridContinuityService();
    }

    /* ================================================================
     *  MODO A — Crear nueva grilla
     * ================================================================ */

    /**
     * Crear una nueva grilla para un rango de fechas y una o varias redes.
     *
     * @param  array  $params
     * @return array            ['grid' => Grid|null, 'items_count' => int, 'result' => array]
     */
    public function createGrid(array $params)
    {
        $anio        = isset($params['anio']) ? (int) $params['anio'] : date('Y');
        $periodo     = isset($params['periodo']) ? $params['periodo'] : $anio;
        $tipoPeriodo = isset($params['tipo_periodo']) ? $params['tipo_periodo'] : Grid::TIPO_ANUAL;
        $redes       = isset($params['redes']) ? $params['redes'] : [];
        $userId      = isset($params['user_id']) ? $params['user_id'] : null;

        // Crear una grilla por cada red solicitada (o una multired si se indica)
        $grids = [];
        $totalItems = 0;

        DB::beginTransaction();
        try {
            foreach ($redes as $red) {
                // Verificar si ya existe
                $existing = Grid::where('anio', $anio)
                    ->where('periodo', $periodo)
                    ->where('red_social', $red)
                    ->first();

                if ($existing) {
                    Log::info('MKT03Orchestrator[ModeA]: grilla ya existe, skip', [
                        'grid_id' => $existing->id,
                        'red'     => $red,
                    ]);
                    $grids[] = $existing;
                    continue;
                }

                // Crear la grilla
                $grid = Grid::create([
                    'anio'          => $anio,
                    'periodo'       => $periodo,
                    'tipo_periodo'  => $tipoPeriodo,
                    'red_social'    => $red,
                    'version'       => 1,
                    'estado'        => Grid::ESTADO_BORRADOR,
                    'titulo'        => isset($params['titulo']) ? $params['titulo'] : "Grilla {$red} {$periodo}",
                    'descripcion'   => isset($params['descripcion']) ? $params['descripcion'] : null,
                    'user_id'       => $userId,
                    'ai_provider'   => config('marketing.chatbot.ai_provider'),
                ]);

                // Generar ítems con IA
                $generation = $this->generator->generateGrid([
                    'fecha_desde'      => isset($params['fecha_desde']) ? $params['fecha_desde'] : null,
                    'fecha_hasta'      => isset($params['fecha_hasta']) ? $params['fecha_hasta'] : null,
                    'redes'            => [$red],
                    'tipo_periodo'     => $tipoPeriodo,
                    'items_existentes' => [],
                ]);

                $saved = $this->generator->saveGeneratedItems($generation, $grid);
                $totalItems += $saved;
                $grids[] = $grid->fresh();

                Log::info('MKT03Orchestrator[ModeA]: grilla creada', [
                    'grid_id'      => $grid->id,
                    'red'          => $red,
                    'items_saved'  => $saved,
                ]);
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('MKT03Orchestrator[ModeA]: error creando grilla', [
                'error' => $e->getMessage(),
            ]);
            return [
                'grids'       => [],
                'items_count' => 0,
                'error'       => $e->getMessage(),
            ];
        }

        return [
            'grids'       => $grids,
            'items_count' => $totalItems,
        ];
    }

    /* ================================================================
     *  MODO B — Consultar grilla existente
     * ================================================================ */

    /**
     * @param  array  $params  ['anio', 'periodo', 'red_social', 'fecha_desde', 'fecha_hasta']
     * @return array
     */
    public function consultGrid(array $params)
    {
        $query = Grid::query()->with('items');

        if (isset($params['anio'])) {
            $query->where('anio', $params['anio']);
        }
        if (isset($params['periodo'])) {
            $query->where('periodo', $params['periodo']);
        }
        if (isset($params['red_social'])) {
            $query->where('red_social', $params['red_social']);
        }

        $grids = $query->get();

        // Filtrar ítems por rango de fecha si se pidió
        if (isset($params['fecha_desde']) || isset($params['fecha_hasta'])) {
            foreach ($grids as $grid) {
                $items = $grid->items;
                if (isset($params['fecha_desde'])) {
                    $items = $items->where('fecha_publicacion', '>=', $params['fecha_desde']);
                }
                if (isset($params['fecha_hasta'])) {
                    $items = $items->where('fecha_publicacion', '<=', $params['fecha_hasta']);
                }
                $grid->setRelation('items', $items->values());
            }
        }

        return [
            'found' => $grids->count() > 0,
            'grids' => $grids,
        ];
    }

    /* ================================================================
     *  MODO C — Ampliar grilla parcial (continuidad automática)
     * ================================================================ */

    /**
     * @param  Grid   $grid
     * @param  array  $params  ['fecha_desde', 'fecha_hasta']
     * @return array
     */
    public function extendGrid(Grid $grid, array $params)
    {
        if (!$grid->isEditable() && $grid->estado !== Grid::ESTADO_ACTIVA) {
            return [
                'success' => false,
                'error'   => 'La grilla no está en estado editable (estado actual: ' . $grid->estado . ')',
            ];
        }

        // Traer ítems existentes de la grilla para evitar duplicados
        $existing = $grid->items()->get()->map(function ($i) {
            return [
                'fecha_publicacion' => $i->fecha_publicacion ? $i->fecha_publicacion->format('Y-m-d') : null,
                'red_social'        => $i->red_social,
                'tematica'          => $i->tematica,
            ];
        })->toArray();

        $generation = $this->generator->generateGrid([
            'fecha_desde'      => isset($params['fecha_desde']) ? $params['fecha_desde'] : null,
            'fecha_hasta'      => isset($params['fecha_hasta']) ? $params['fecha_hasta'] : null,
            'redes'            => [$grid->red_social],
            'tipo_periodo'     => $grid->tipo_periodo,
            'items_existentes' => $existing,
        ]);

        $saved = $this->generator->saveGeneratedItems($generation, $grid);

        return [
            'success'     => true,
            'items_added' => $saved,
            'generation'  => $generation,
        ];
    }

    /* ================================================================
     *  MODO C — Continuidad automática (§11.5)
     *
     *  Estos métodos implementan las 5 sub-reglas del documento funcional:
     *    §11.5    Si grilla parcial está por agotarse, ampliar tramo siguiente.
     *    §11.5.1  Proactividad editorial inteligente.
     *    §11.5.2  Expansión progresiva (trimestre por defecto).
     *    §11.5.3  Orden de construcción por red (FB → IG → LI → TT).
     *    §11.5.4  Criterio cuánto ampliar: trimestre por defecto, semestre/anual
     *             solo si claramente conviene.
     *    §11.5.5  No contradicción con configuración antigua.
     * ================================================================ */

    /**
     * Detectar grillas que necesitan continuidad. Lee, no escribe.
     *
     * @param  int|null  $umbralDias  Días antes de agotarse para considerar candidata
     * @return array                   Lista ordenada por §11.5.3 con sugerencias de tramo
     */
    public function detectContinuityCandidates($umbralDias = null)
    {
        return $this->continuity->detectCandidates($umbralDias);
    }

    /**
     * Si la grilla necesita extensión según §11.5, la amplía con el tramo
     * sugerido por §11.5.4 (trimestre por defecto). Si no, no hace nada.
     *
     * @param  Grid       $grid
     * @param  bool       $force  Si true, fuerza la extensión aunque la grilla
     *                            todavía tenga cobertura suficiente.
     * @return array
     */
    public function extendGridIfNeeded(Grid $grid, $force = false)
    {
        $eval = $this->continuity->evaluateGrid($grid);

        if (!$eval['needs_extension'] && !$force) {
            return [
                'success'   => false,
                'skipped'   => true,
                'reason'    => $eval['razon'],
                'evaluation'=> $eval,
            ];
        }

        // §11.5.5 — la configuración antigua nunca prohíbe permanentemente
        if ($this->continuity->isContinuityForbidden($grid)) {
            return [
                'success'   => false,
                'skipped'   => true,
                'reason'    => 'continuity_forbidden_by_config',
                'evaluation'=> $eval,
            ];
        }

        $tramo = $eval['tramo_sugerido'] ?: $this->continuity->suggestNextTramo($grid, $eval['ultima_fecha']);

        $extendResult = $this->extendGrid($grid, [
            'fecha_desde' => $tramo['fecha_desde'],
            'fecha_hasta' => $tramo['fecha_hasta'],
        ]);

        Log::info('MKT03Orchestrator[ModeC]: extensión por continuidad', [
            'grid_id'      => $grid->id,
            'red'          => $grid->red_social,
            'razon'        => $eval['razon'],
            'tramo'        => $tramo,
            'items_added'  => isset($extendResult['items_added']) ? $extendResult['items_added'] : 0,
        ]);

        return array_merge($extendResult, [
            'evaluation' => $eval,
            'tramo'      => $tramo,
        ]);
    }

    /**
     * Procesar TODAS las grillas que necesitan continuidad.
     *
     * Respeta §11.5.3 — orden FB → IG → LinkedIn → TikTok. Si una grilla
     * falla, sigue con las demás.
     *
     * @param  int|null  $umbralDias
     * @param  bool      $dryRun     Si true, solo retorna el plan sin escribir
     * @return array
     */
    public function extendAllNeedingContinuity($umbralDias = null, $dryRun = false)
    {
        $candidates = $this->continuity->detectCandidates($umbralDias);

        $results = [];
        foreach ($candidates as $cand) {
            $grid = $cand['grid'];

            if ($dryRun) {
                $results[] = [
                    'grid_id'    => $grid->id,
                    'red'        => $grid->red_social,
                    'titulo'     => $grid->titulo,
                    'razon'      => $cand['razon'],
                    'tramo'      => $cand['tramo_sugerido'],
                    'dry_run'    => true,
                ];
                continue;
            }

            try {
                $r = $this->extendGridIfNeeded($grid, true);
                $results[] = [
                    'grid_id'      => $grid->id,
                    'red'          => $grid->red_social,
                    'titulo'       => $grid->titulo,
                    'razon'        => $cand['razon'],
                    'tramo'        => $cand['tramo_sugerido'],
                    'items_added'  => isset($r['items_added']) ? $r['items_added'] : 0,
                    'success'      => isset($r['success']) ? $r['success'] : false,
                ];
            } catch (\Exception $e) {
                Log::error('MKT03Orchestrator[ModeC]: error ampliando grilla', [
                    'grid_id' => $grid->id,
                    'error'   => $e->getMessage(),
                ]);
                $results[] = [
                    'grid_id' => $grid->id,
                    'red'     => $grid->red_social,
                    'error'   => $e->getMessage(),
                ];
            }
        }

        return [
            'total_candidates' => count($candidates),
            'processed'        => count($results),
            'results'          => $results,
        ];
    }

    /* ================================================================
     *  MODO D — Producir pieza desde ítem
     * ================================================================ */

    /**
     * @param  GridItem  $gridItem
     * @param  bool      $generateImage
     * @return Piece|null
     */
    public function producePiece(GridItem $gridItem, $generateImage = true)
    {
        return $this->producer->producePiece($gridItem, $generateImage);
    }

    /* ================================================================
     *  MODO E — Contenido extraordinario
     * ================================================================ */

    /**
     * @param  array  $params  ['fecha', 'motivo', 'red_social', 'descripcion', 'clasificacion', ...]
     * @return array
     */
    public function createExtraordinary(array $params)
    {
        $extra = ExtraordinaryContent::create([
            'fecha'          => isset($params['fecha']) ? $params['fecha'] : date('Y-m-d'),
            'motivo'         => isset($params['motivo']) ? $params['motivo'] : 'Sin motivo especificado',
            'red_social'     => isset($params['red_social']) ? $params['red_social'] : 'facebook',
            'descripcion'    => isset($params['descripcion']) ? $params['descripcion'] : null,
            'clasificacion'  => isset($params['clasificacion']) ? $params['clasificacion'] : null,
            'estado'         => ExtraordinaryContent::ESTADO_EN_DESARROLLO,
            'solicitante_id' => isset($params['solicitante_id']) ? $params['solicitante_id'] : null,
        ]);

        // Crear un GridItem temporal (sin grid_id) y producir la pieza directamente
        // Nota: en este modo, la pieza no se asocia a una grilla
        // Se genera con un GridItem virtual usando los campos del extraordinario

        $virtualItem = new GridItem();
        $virtualItem->id = 0; // No guardado
        $virtualItem->fecha_publicacion = $extra->fecha;
        $virtualItem->red_social = $extra->red_social;
        $virtualItem->tematica = $extra->motivo;
        $virtualItem->tipo_contenido = 'Extraordinario';
        $virtualItem->prioridad = GridItem::PRIORIDAD_ALTA;
        $virtualItem->origen = GridItem::ORIGEN_EXTRAORDINARIO;
        $virtualItem->observaciones = $extra->descripcion;

        // Nota: por simplicidad, aquí devolvemos el extraordinario creado.
        // La producción de la pieza puede invocarse por separado llamando a
        // producePiece() con un GridItem guardado (patrón alternativo).

        $extra->update(['estado' => ExtraordinaryContent::ESTADO_ENTREGADO]);

        return [
            'extraordinary' => $extra,
            'note'          => 'Extraordinario creado. Para producir pieza, crear un GridItem con origen=extraordinario y llamar producePiece().',
        ];
    }

    /**
     * Marcar un extraordinario como aprobado (o no) para futuras grillas.
     *
     * @param  ExtraordinaryContent  $extra
     * @param  bool                  $approved
     * @param  int|null              $decisorId
     * @param  string|null           $motivo
     * @return ExtraordinaryContent
     */
    public function decideExtraordinaryForFuture(
        ExtraordinaryContent $extra,
        $approved,
        $decisorId = null,
        $motivo = null
    ) {
        $extra->update([
            'aprobado_para_futuras_grillas' => $approved,
            'motivo_decision'               => $motivo,
            'decisor_id'                    => $decisorId,
            'fecha_decision'                => now(),
            'estado' => $approved
                ? ExtraordinaryContent::ESTADO_APROBADO_PARA_FUTURAS
                : ExtraordinaryContent::ESTADO_NO_APROBADO,
        ]);

        return $extra->fresh();
    }

    /* ================================================================
     *  MODO F — Contenido para feria (§12, §13.6)
     *
     *  Genera 3 momentos editoriales por feria (§12.4):
     *    - 2m antes: anuncio / expectativa / presencia / industria
     *    - 1m antes: valor del evento / contexto / interés / cercanía
     *    - 15d antes: recordatorio / activación directa / enfoque
     *
     *  Las plantillas de cada fase respetan §12.5 (evolución del enfoque)
     *  y §12.6 (la información interna no se traslada al mensaje público).
     * ================================================================ */

    /**
     * Configuración de cada fase editorial de feria, alineada con §12.5
     * del Documento Funcional. Cada fase tiene:
     *   - tipo_contenido: el tipo a usar en el GridItem
     *   - objetivo: alineado con la lógica editorial
     *   - tematica_template: cómo titular la pieza (sin contexto interno)
     *   - briefing: instrucciones para el productor de piezas
     *
     * @param  FeriaReceived  $feria
     * @param  string         $fase    FeriaReceived::FASE_2M | FASE_1M | FASE_15D
     * @return array
     */
    private function feriaFaseConfig(FeriaReceived $feria, $fase)
    {
        $nombre = $feria->nombre;
        $sector = $feria->sector ?: 'el sector';
        $ciudad = $feria->ciudad ? " en {$feria->ciudad}" : '';

        switch ($fase) {
            case FeriaReceived::FASE_2M:
                return [
                    'tipo_contenido'    => 'Expectativa',
                    'objetivo'          => 'visibilidad',
                    'tematica_template' => "Anuncio: {$nombre}{$ciudad}",
                    'briefing'          => "Generar pieza de ANUNCIO/EXPECTATIVA para la feria '{$nombre}' (60 días antes). " .
                                           "Enfoque: presencia de COMPIPRO en {$sector}, conexión con la industria, " .
                                           "tono profesional pero cercano. NO mencionar detalles internos de participación " .
                                           "(stand, productos a llevar, etc.) — eso es contexto estratégico interno (§12.6).",
                ];

            case FeriaReceived::FASE_1M:
                return [
                    'tipo_contenido'    => 'Contenido de valor',
                    'objetivo'          => 'posicionamiento',
                    'tematica_template' => "Valor del evento: {$nombre}",
                    'briefing'          => "Generar pieza de VALOR DEL EVENTO para la feria '{$nombre}' (30 días antes). " .
                                           "Enfoque: por qué el evento es relevante para {$sector}, qué tendencias se " .
                                           "discutirán, qué oportunidades trae, invitación a participar. " .
                                           "Tono educativo, generar interés. NO promocionar productos directamente.",
                ];

            case FeriaReceived::FASE_15D:
                return [
                    'tipo_contenido'    => 'Recordatorio',
                    'objetivo'          => 'campania',
                    'tematica_template' => "Recordatorio: nos vemos en {$nombre}",
                    'briefing'          => "Generar pieza de RECORDATORIO + ACTIVACIÓN para la feria '{$nombre}' " .
                                           "(15 días antes). Enfoque: nos vemos en el evento, fecha exacta, " .
                                           "ubicación si es público, CTA claro (visitarnos / agendar reunión / registrarse). " .
                                           "Mayor claridad de enfoque comercial sin caer en venta dura.",
                ];

            default:
                return [
                    'tipo_contenido'    => 'Feria',
                    'objetivo'          => 'visibilidad',
                    'tematica_template' => "Feria: {$nombre}",
                    'briefing'          => "Pieza para feria {$nombre}",
                ];
        }
    }

    /**
     * Generar ítems 2m, 1m, 15d antes de una feria en las redes indicadas.
     *
     * Si `$producePieces` es true, además llama al PieceProducerEngine para
     * producir la pieza final (copy + CTA + hashtags + brief visual + imagen).
     * Si false, solo crea los GridItems placeholders y el usuario puede correr
     * `producePiece` después manualmente.
     *
     * @param  FeriaReceived  $feria
     * @param  array          $redes          Redes a cubrir (si vacío, usa las de la feria)
     * @param  Grid|null      $grid           Grilla objetivo (si null, se crea/asocia automáticamente)
     * @param  bool           $producePieces  Si true, produce la pieza final con el producer
     * @return array
     */
    public function generateFeriaItems(FeriaReceived $feria, array $redes = [], Grid $grid = null, $producePieces = false)
    {
        if (empty($redes)) {
            $redes = $feria->redes_sociales_target ?: ['facebook', 'instagram', 'linkedin'];
        }

        $feriaDate = $feria->fecha->format('Y-m-d');
        $fase2m  = date('Y-m-d', strtotime($feriaDate . ' -60 days'));
        $fase1m  = date('Y-m-d', strtotime($feriaDate . ' -30 days'));
        $fase15d = date('Y-m-d', strtotime($feriaDate . ' -15 days'));

        $itemsCreados  = 0;
        $piezasCreadas = 0;

        foreach ($redes as $red) {
            // Asegurar que haya una grilla para la red y año de la feria
            $targetGrid = $grid;
            if (!$targetGrid) {
                $targetGrid = Grid::firstOrCreate(
                    [
                        'anio'       => (int) $feria->fecha->format('Y'),
                        'red_social' => $red,
                        'periodo'    => $feria->fecha->format('Y'),
                    ],
                    [
                        'tipo_periodo' => Grid::TIPO_ANUAL,
                        'estado'      => Grid::ESTADO_ACTIVA,
                        'titulo'      => "Grilla {$red} " . $feria->fecha->format('Y'),
                    ]
                );
            }

            // Crear los 3 ítems con tipo_contenido y briefing específicos por fase (§12.5)
            $fases = [
                ['fecha' => $fase2m,  'fase' => FeriaReceived::FASE_2M],
                ['fecha' => $fase1m,  'fase' => FeriaReceived::FASE_1M],
                ['fecha' => $fase15d, 'fase' => FeriaReceived::FASE_15D],
            ];

            foreach ($fases as $f) {
                $cfg = $this->feriaFaseConfig($feria, $f['fase']);

                $item = GridItem::create([
                    'grid_id'            => $targetGrid->id,
                    'fecha_publicacion'  => $f['fecha'],
                    'red_social'         => $red,
                    'tematica'           => $cfg['tematica_template'],
                    'evento'             => $feria->nombre,
                    'objetivo'           => $cfg['objetivo'],
                    'tipo_contenido'     => $cfg['tipo_contenido'],
                    'prioridad'          => GridItem::PRIORIDAD_ALTA,
                    'origen'             => GridItem::ORIGEN_FERIA,
                    'feria_id'           => $feria->id,
                    'fase_feria'         => $f['fase'],
                    'observaciones'      => $cfg['briefing'],
                ]);
                $itemsCreados++;

                // Si se pidió producir piezas, llamar al producer ahora
                if ($producePieces) {
                    try {
                        $piece = $this->producer->producePiece($item, true);
                        if ($piece) {
                            $piezasCreadas++;
                        }
                    } catch (\Exception $e) {
                        Log::warning('MKT03Orchestrator[ModeF]: error produciendo pieza de feria', [
                            'item_id'  => $item->id,
                            'feria_id' => $feria->id,
                            'fase'     => $f['fase'],
                            'error'    => $e->getMessage(),
                        ]);
                    }
                }
            }
        }

        // Marcar feria como con piezas generadas
        $feria->update([
            'piezas_2m_generadas'  => true,
            'piezas_1m_generadas'  => true,
            'piezas_15d_generadas' => true,
            'estado'               => FeriaReceived::ESTADO_EN_COBERTURA,
        ]);

        return [
            'success'        => true,
            'items_creados'  => $itemsCreados,
            'piezas_creadas' => $piezasCreadas,
            'redes'          => $redes,
            'fases'          => [$fase2m, $fase1m, $fase15d],
        ];
    }
}
