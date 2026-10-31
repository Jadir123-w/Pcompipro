<?php

namespace Modules\Marketing\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
use Modules\Marketing\Models\FeriaReceived;
use Modules\Marketing\Models\Grid;
use Modules\Marketing\Services\MKT03Orchestrator;

/**
 * MKT03FeriaController — API de ferias (MKT-03).
 *
 * Cubre el Modo F: creación de contenido para ferias (skeleton — el
 * "agente de ferias" como sistema externo no está construido aún).
 *
 * Este controller acepta registros manuales de ferias y expone un endpoint
 * para que un futuro agente externo pueda notificar ferias vía API.
 */
class MKT03FeriaController extends Controller
{
    /**
     * GET /marketing/mkt03/ferias
     */
    public function index(Request $request)
    {
        $query = FeriaReceived::query()
            ->with(['gridItems'])
            ->orderBy('fecha');

        if ($request->filled('estado')) {
            $query->where('estado', $request->input('estado'));
        }
        if ($request->filled('source')) {
            $query->where('source', $request->input('source'));
        }
        if ($request->filled('desde')) {
            $query->where('fecha', '>=', $request->input('desde'));
        }
        if ($request->filled('hasta')) {
            $query->where('fecha', '<=', $request->input('hasta'));
        }

        $ferias = $query->paginate($request->input('per_page', 20));

        return response()->json(['ok' => true, 'ferias' => $ferias]);
    }

    /**
     * GET /marketing/mkt03/ferias/{id}
     */
    public function show($id)
    {
        $feria = FeriaReceived::with(['gridItems.pieces'])->findOrFail($id);
        return response()->json(['ok' => true, 'feria' => $feria]);
    }

    /**
     * POST /marketing/mkt03/ferias
     *
     * Registrar una feria manualmente (o via payload del futuro agente de ferias).
     */
    public function store(Request $request)
    {
        $v = Validator::make($request->all(), [
            'nombre'                   => ['required', 'string', 'max:255'],
            'fecha'                    => ['required', 'date'],
            'sector'                   => ['nullable', 'string', 'max:150'],
            'ciudad'                   => ['nullable', 'string', 'max:100'],
            'tipo_participacion'       => ['nullable', 'string', 'max:100'],
            'objetivo_comunicacional'  => ['nullable', 'string'],
            'enfoque_sugerido'         => ['nullable', 'string'],
            'contexto_interno'         => ['nullable', 'string'],
            'redes_sociales_target'    => ['nullable', 'array'],
            'redes_sociales_target.*'  => ['string', 'in:facebook,instagram,linkedin,tiktok'],
            'source'                   => ['nullable', 'string', 'in:manual,agente_ferias'],
            'external_feria_id'        => ['nullable', 'string', 'max:100'],
        ]);

        if ($v->fails()) {
            return response()->json(['ok' => false, 'errors' => $v->errors()], 422);
        }

        $data = $v->validated();
        $data['source'] = $data['source'] ?? FeriaReceived::SOURCE_MANUAL;
        $data['estado'] = FeriaReceived::ESTADO_ACTIVA;

        $feria = FeriaReceived::create($data);

        // Calcular fase editorial actual automáticamente
        $fase = $feria->calcularFaseActual();
        if ($fase) {
            $feria->update(['fase_editorial_actual' => $fase]);
        }

        return response()->json(['ok' => true, 'feria' => $feria->fresh()], 201);
    }

    /**
     * POST /marketing/mkt03/ferias/{id}/generate-items
     *
     * Modo F: Generar los ítems 2m/1m/15d antes de la feria.
     *
     * Body opcional:
     *   - redes: array de redes (default: redes_sociales_target de la feria)
     *   - grid_id: int (default: se crea/asocia grilla automáticamente)
     *   - produce_pieces: bool (default false) — si true, además produce las
     *     piezas finales (copy + CTA + hashtags + brief visual + imagen).
     *     Atención: produce_pieces=true consume tokens IA por cada pieza.
     */
    public function generateItems(Request $request, $id)
    {
        $feria = FeriaReceived::findOrFail($id);

        $v = Validator::make($request->all(), [
            'redes'           => ['nullable', 'array'],
            'redes.*'         => ['string', 'in:facebook,instagram,linkedin,tiktok'],
            'grid_id'         => ['nullable', 'integer', 'exists:mkt_grids,id'],
            'produce_pieces'  => ['nullable', 'boolean'],
        ]);

        if ($v->fails()) {
            return response()->json(['ok' => false, 'errors' => $v->errors()], 422);
        }

        $redes        = $request->input('redes', []);
        $grid         = $request->filled('grid_id') ? Grid::find($request->input('grid_id')) : null;
        $producePieces = filter_var($request->input('produce_pieces', false), FILTER_VALIDATE_BOOLEAN);

        try {
            $result = app(MKT03Orchestrator::class)->generateFeriaItems($feria, $redes, $grid, $producePieces);
            return response()->json([
                'ok'             => true,
                'items_creados'  => $result['items_creados'],
                'piezas_creadas' => $result['piezas_creadas'] ?? 0,
                'redes'          => $result['redes'],
                'fases'          => $result['fases'],
                'feria'          => $feria->fresh(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'ok'      => false,
                'message' => 'Error generando ítems de feria: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * POST /marketing/mkt03/ferias/receive
     *
     * Endpoint dedicado para que el AGENTE DE FERIAS externo (sistema futuro)
     * notifique una feria a MKT-03. Es un wrapper sobre `store()` con:
     *
     *   1. `source` forzado a 'agente_ferias'
     *   2. `external_feria_id` requerido (para idempotencia: si ya existe una
     *      feria con ese external_feria_id, no se duplica)
     *   3. Opción `auto_generate_items` (default true) que dispara
     *      generateFeriaItems() automáticamente al recibir la feria
     *
     * Documentado en §12.3 del Documento Funcional.
     */
    public function receiveFromExternalAgent(Request $request)
    {
        $v = Validator::make($request->all(), [
            'external_feria_id'        => ['required', 'string', 'max:100'],
            'nombre'                   => ['required', 'string', 'max:255'],
            'fecha'                    => ['required', 'date'],
            'sector'                   => ['nullable', 'string', 'max:150'],
            'ciudad'                   => ['nullable', 'string', 'max:100'],
            'tipo_participacion'       => ['nullable', 'string', 'max:100'],
            'objetivo_comunicacional'  => ['nullable', 'string'],
            'enfoque_sugerido'         => ['nullable', 'string'],
            'contexto_interno'         => ['nullable', 'string'],
            'redes_sociales_target'    => ['nullable', 'array'],
            'redes_sociales_target.*'  => ['string', 'in:facebook,instagram,linkedin,tiktok'],
            'auto_generate_items'      => ['nullable', 'boolean'],
        ]);

        if ($v->fails()) {
            return response()->json(['ok' => false, 'errors' => $v->errors()], 422);
        }

        $data = $v->validated();
        $autoGenerate = filter_var($data['auto_generate_items'] ?? true, FILTER_VALIDATE_BOOLEAN);
        unset($data['auto_generate_items']);

        // Idempotencia: si ya existe una feria con este external_feria_id, no duplicar
        $feria = FeriaReceived::where('source', FeriaReceived::SOURCE_AGENTE_FERIAS)
            ->where('external_feria_id', $data['external_feria_id'])
            ->first();

        $created = false;
        if (!$feria) {
            $data['source'] = FeriaReceived::SOURCE_AGENTE_FERIAS;
            $data['estado'] = FeriaReceived::ESTADO_ACTIVA;
            $feria = FeriaReceived::create($data);
            $created = true;

            // Calcular fase editorial actual automáticamente
            $fase = $feria->calcularFaseActual();
            if ($fase) {
                $feria->update(['fase_editorial_actual' => $fase]);
            }
        }

        // Disparar generación automática si se pidió y la feria es nueva (o no tiene items aún)
        $itemsResult = null;
        if ($autoGenerate && $feria->gridItems()->count() === 0) {
            try {
                $itemsResult = app(MKT03Orchestrator::class)->generateFeriaItems($feria, [], null, false);
            } catch (\Exception $e) {
                // No fallar el endpoint si la generación tarda — la feria queda creada
                $itemsResult = ['error' => $e->getMessage()];
            }
        }

        return response()->json([
            'ok'             => true,
            'created'        => $created,
            'feria'          => $feria->fresh(),
            'items_result'   => $itemsResult,
        ], $created ? 201 : 200);
    }

    /**
     * PUT /marketing/mkt03/ferias/{id}/status
     */
    public function updateStatus(Request $request, $id)
    {
        $feria = FeriaReceived::findOrFail($id);

        $v = Validator::make($request->all(), [
            'estado' => ['required', 'string', 'in:activa,en_cobertura,concluida,cancelada'],
        ]);

        if ($v->fails()) {
            return response()->json(['ok' => false, 'errors' => $v->errors()], 422);
        }

        $feria->update(['estado' => $request->input('estado')]);

        return response()->json(['ok' => true, 'feria' => $feria->fresh()]);
    }

    /**
     * DELETE /marketing/mkt03/ferias/{id}
     */
    public function destroy($id)
    {
        $feria = FeriaReceived::findOrFail($id);
        $feria->delete();

        return response()->json(['ok' => true, 'message' => 'Feria eliminada']);
    }
}
