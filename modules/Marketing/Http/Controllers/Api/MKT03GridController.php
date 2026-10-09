<?php

namespace Modules\Marketing\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
use Modules\Marketing\Models\Grid;
use Modules\Marketing\Models\GridItem;
use Modules\Marketing\Services\MKT03Orchestrator;

/**
 * MKT03GridController — API de grillas editoriales (MKT-03).
 *
 * Cubre los modos A, B y C del agente:
 *   Modo A — Crear nueva grilla (con IA)
 *   Modo B — Consultar grilla existente
 *   Modo C — Ampliar grilla parcial
 *
 * Además maneja CRUD de items de grilla.
 */
class MKT03GridController extends Controller
{
    /** @var MKT03Orchestrator */
    private $orchestrator;

    public function __construct(MKT03Orchestrator $orchestrator)
    {
        $this->orchestrator = $orchestrator;
    }

    /*
    |--------------------------------------------------------------------------
    | Modo A — Crear nueva grilla
    |--------------------------------------------------------------------------
    */

    /**
     * POST /marketing/mkt03/grids/generate
     *
     * Genera una nueva grilla con IA para un rango de fechas y una o varias redes.
     */
    public function generate(Request $request)
    {
        $v = Validator::make($request->all(), [
            'anio'          => ['required', 'integer', 'min:2020', 'max:2100'],
            'periodo'       => ['required', 'string', 'max:50'],
            'tipo_periodo'  => ['required', 'string', 'in:anual,trimestral,semestral,mensual,campania,fecha'],
            'redes'         => ['required', 'array', 'min:1'],
            'redes.*'       => ['string', 'in:facebook,instagram,linkedin,tiktok,multired'],
            'fecha_desde'   => ['required', 'date'],
            'fecha_hasta'   => ['required', 'date', 'after_or_equal:fecha_desde'],
            'titulo'        => ['nullable', 'string', 'max:255'],
            'descripcion'   => ['nullable', 'string'],
        ]);

        if ($v->fails()) {
            return response()->json(['ok' => false, 'errors' => $v->errors()], 422);
        }

        try {
            $params = $request->only([
                'anio', 'periodo', 'tipo_periodo', 'redes', 'fecha_desde', 'fecha_hasta',
                'titulo', 'descripcion',
            ]);
            $params['user_id'] = $request->user() ? $request->user()->id : null;

            $result = $this->orchestrator->createGrid($params);

            if (isset($result['error'])) {
                return response()->json(['ok' => false, 'message' => $result['error']], 500);
            }

            return response()->json([
                'ok'          => true,
                'grids'       => $result['grids'],
                'items_count' => $result['items_count'],
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'ok'      => false,
                'message' => 'Error generando grilla: ' . $e->getMessage(),
            ], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Modo B — Consultar grillas
    |--------------------------------------------------------------------------
    */

    /**
     * GET /marketing/mkt03/grids
     *
     * Listar grillas con filtros opcionales.
     */
    public function index(Request $request)
    {
        $query = Grid::query()
            ->withCount('items')
            ->orderByDesc('anio')
            ->orderBy('periodo');

        if ($request->filled('anio')) {
            $query->where('anio', $request->input('anio'));
        }
        if ($request->filled('red_social')) {
            $query->where('red_social', $request->input('red_social'));
        }
        if ($request->filled('estado')) {
            $query->where('estado', $request->input('estado'));
        }
        if ($request->filled('tipo_periodo')) {
            $query->where('tipo_periodo', $request->input('tipo_periodo'));
        }

        $grids = $query->paginate($request->input('per_page', 20));

        return response()->json(['ok' => true, 'grids' => $grids]);
    }

    /**
     * GET /marketing/mkt03/grids/{id}
     *
     * Detalle de una grilla con sus ítems.
     */
    public function show($id)
    {
        $grid = Grid::with(['items', 'historicalBase'])
            ->findOrFail($id);

        return response()->json(['ok' => true, 'grid' => $grid]);
    }

    /**
     * GET /marketing/mkt03/grids/consult
     *
     * Consultar grillas con parámetros (Modo B explícito).
     */
    public function consult(Request $request)
    {
        $params = $request->only([
            'anio', 'periodo', 'red_social', 'fecha_desde', 'fecha_hasta',
        ]);

        $result = $this->orchestrator->consultGrid($params);

        return response()->json([
            'ok'    => true,
            'found' => $result['found'],
            'grids' => $result['grids'],
        ]);
    }

    /**
     * PUT /marketing/mkt03/grids/{id}/status
     *
     * Cambiar estado de la grilla (borrador, validada, activa, cerrada, etc.).
     */
    public function updateStatus(Request $request, $id)
    {
        $grid = Grid::findOrFail($id);

        $v = Validator::make($request->all(), [
            'estado' => ['required', 'string', 'in:' . implode(',', Grid::ESTADOS)],
        ]);

        if ($v->fails()) {
            return response()->json(['ok' => false, 'errors' => $v->errors()], 422);
        }

        $updates = ['estado' => $request->input('estado')];

        if ($request->input('estado') === Grid::ESTADO_VALIDADA) {
            $updates['revisor_id']        = $request->user() ? $request->user()->id : null;
            $updates['estado_aprobacion'] = 'validada';
        }
        if ($request->input('estado') === Grid::ESTADO_ACTIVA) {
            $updates['aprobador_id']      = $request->user() ? $request->user()->id : null;
            $updates['estado_aprobacion'] = 'activa';
            $updates['fecha_aprobacion']  = now();
        }

        $grid->update($updates);

        return response()->json(['ok' => true, 'grid' => $grid->fresh()]);
    }

    /**
     * DELETE /marketing/mkt03/grids/{id}
     *
     * Eliminar grilla (soft delete).
     */
    public function destroy($id)
    {
        $grid = Grid::findOrFail($id);
        $grid->delete();

        return response()->json(['ok' => true, 'message' => 'Grilla eliminada']);
    }

    /*
    |--------------------------------------------------------------------------
    | Modo C — Ampliar grilla parcial + continuidad automática (§11.5)
    |--------------------------------------------------------------------------
    */

    /**
     * POST /marketing/mkt03/grids/{id}/extend
     *
     * Amplía una grilla con un nuevo rango de fechas, evitando duplicados.
     */
    public function extend(Request $request, $id)
    {
        $grid = Grid::findOrFail($id);

        $v = Validator::make($request->all(), [
            'fecha_desde' => ['required', 'date'],
            'fecha_hasta' => ['required', 'date', 'after_or_equal:fecha_desde'],
        ]);

        if ($v->fails()) {
            return response()->json(['ok' => false, 'errors' => $v->errors()], 422);
        }

        $result = $this->orchestrator->extendGrid($grid, [
            'fecha_desde' => $request->input('fecha_desde'),
            'fecha_hasta' => $request->input('fecha_hasta'),
        ]);

        if (!$result['success']) {
            return response()->json(['ok' => false, 'message' => $result['error']], 422);
        }

        return response()->json([
            'ok'          => true,
            'items_added' => $result['items_added'],
            'grid'        => $grid->fresh()->load('items'),
        ]);
    }

    /**
     * GET /marketing/mkt03/continuity/status
     *
     * Lista las grillas candidatas a continuidad automática (Modo C — §11.5).
     * Soporta query param `dias` para overridear el umbral default (14).
     *
     * Respuesta incluye, por cada candidata: grilla, última fecha cubierta,
     * días restantes (negativos si está agotada) y tramo sugerido (§11.5.4).
     */
    public function continuityStatus(Request $request)
    {
        $umbralDias = $request->filled('dias') ? (int) $request->input('dias') : null;

        $candidates = $this->orchestrator->detectContinuityCandidates($umbralDias);

        $payload = array_map(function ($c) {
            $g = $c['grid'];
            return [
                'grid_id'         => $g->id,
                'red_social'      => $g->red_social,
                'titulo'          => $g->titulo,
                'estado'          => $g->estado,
                'tipo_periodo'    => $g->tipo_periodo,
                'ultima_fecha'    => $c['ultima_fecha'],
                'dias_restantes'  => $c['dias_restantes'],
                'agotada'         => $c['agotada'],
                'razon'           => $c['razon'],
                'tramo_sugerido'  => $c['tramo_sugerido'],
            ];
        }, $candidates);

        return response()->json([
            'ok'         => true,
            'candidates' => $payload,
            'count'      => count($payload),
        ]);
    }

    /**
     * POST /marketing/mkt03/continuity/run
     *
     * Ejecuta la extensión automática (Modo C — §11.5) sobre todas las grillas
     * candidatas. Body opcional:
     *   - dias: int (umbral)
     *   - dry_run: bool (solo retorna el plan sin escribir; default false)
     */
    public function continuityRun(Request $request)
    {
        $umbralDias = $request->filled('dias') ? (int) $request->input('dias') : null;
        $dryRun     = filter_var($request->input('dry_run', false), FILTER_VALIDATE_BOOLEAN);

        $result = $this->orchestrator->extendAllNeedingContinuity($umbralDias, $dryRun);

        return response()->json([
            'ok'      => true,
            'dry_run' => $dryRun,
            'summary' => [
                'total_candidates' => $result['total_candidates'],
                'processed'        => $result['processed'],
            ],
            'results' => $result['results'],
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | CRUD de items
    |--------------------------------------------------------------------------
    */

    /**
     * POST /marketing/mkt03/grids/{id}/items
     *
     * Agregar un ítem manual a una grilla.
     */
    public function storeItem(Request $request, $id)
    {
        $grid = Grid::findOrFail($id);

        $v = Validator::make($request->all(), [
            'fecha_publicacion' => ['required', 'date'],
            'red_social'        => ['required', 'string', 'in:facebook,instagram,linkedin,tiktok'],
            'tematica'          => ['nullable', 'string', 'max:100'],
            'campania'          => ['nullable', 'string', 'max:100'],
            'temporada'         => ['nullable', 'string', 'max:50'],
            'evento'            => ['nullable', 'string', 'max:100'],
            'objetivo'          => ['nullable', 'string', 'max:100'],
            'linea_comercial'   => ['nullable', 'string', 'max:100'],
            'tipo_contenido'    => ['nullable', 'string', 'max:60'],
            'prioridad'         => ['nullable', 'string', 'in:alta,media,baja'],
            'observaciones'     => ['nullable', 'string'],
        ]);

        if ($v->fails()) {
            return response()->json(['ok' => false, 'errors' => $v->errors()], 422);
        }

        $data = $v->validated();
        $data['grid_id'] = $grid->id;
        $data['origen']  = GridItem::ORIGEN_PUNTUAL;

        $item = GridItem::create($data);

        return response()->json(['ok' => true, 'item' => $item], 201);
    }

    /**
     * PUT /marketing/mkt03/grids/{gridId}/items/{itemId}
     *
     * Editar un ítem de grilla.
     */
    public function updateItem(Request $request, $gridId, $itemId)
    {
        $item = GridItem::where('grid_id', $gridId)->where('id', $itemId)->firstOrFail();

        $editableFields = [
            'fecha_publicacion', 'tematica', 'campania', 'temporada', 'evento',
            'objetivo', 'linea_comercial', 'tipo_contenido', 'prioridad',
            'observaciones', 'item_order',
        ];

        $updates = $request->only($editableFields);
        $item->update($updates);

        return response()->json(['ok' => true, 'item' => $item->fresh()]);
    }

    /**
     * DELETE /marketing/mkt03/grids/{gridId}/items/{itemId}
     *
     * Eliminar un ítem de grilla.
     */
    public function destroyItem($gridId, $itemId)
    {
        $item = GridItem::where('grid_id', $gridId)->where('id', $itemId)->firstOrFail();
        $item->delete();

        return response()->json(['ok' => true, 'message' => 'Ítem eliminado']);
    }
}
