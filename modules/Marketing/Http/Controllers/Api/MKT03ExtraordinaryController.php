<?php

namespace Modules\Marketing\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
use Modules\Marketing\Models\ExtraordinaryContent;
use Modules\Marketing\Services\MKT03Orchestrator;

/**
 * MKT03ExtraordinaryController — API de contenido extraordinario (MKT-03).
 *
 * Cubre el Modo E: creación de piezas fuera de la grilla planificada.
 * El usuario debe decidir si el contenido alimenta futuras grillas.
 */
class MKT03ExtraordinaryController extends Controller
{
    /** @var MKT03Orchestrator */
    private $orchestrator;

    public function __construct(MKT03Orchestrator $orchestrator)
    {
        $this->orchestrator = $orchestrator;
    }

    /**
     * GET /marketing/mkt03/extraordinary
     */
    public function index(Request $request)
    {
        $query = ExtraordinaryContent::query()
            ->with(['pieces'])
            ->orderByDesc('created_at');

        if ($request->filled('estado')) {
            $query->where('estado', $request->input('estado'));
        }
        if ($request->filled('red_social')) {
            $query->where('red_social', $request->input('red_social'));
        }
        if ($request->filled('aprobado_para_futuras_grillas')) {
            $query->where(
                'aprobado_para_futuras_grillas',
                filter_var($request->input('aprobado_para_futuras_grillas'), FILTER_VALIDATE_BOOLEAN)
            );
        }

        $extras = $query->paginate($request->input('per_page', 20));

        return response()->json(['ok' => true, 'extraordinary_content' => $extras]);
    }

    /**
     * GET /marketing/mkt03/extraordinary/{id}
     */
    public function show($id)
    {
        $extra = ExtraordinaryContent::with(['pieces', 'solicitante', 'decisor'])->findOrFail($id);
        return response()->json(['ok' => true, 'extraordinary' => $extra]);
    }

    /**
     * POST /marketing/mkt03/extraordinary
     *
     * Modo E: Crear una solicitud de contenido extraordinario.
     */
    public function store(Request $request)
    {
        $v = Validator::make($request->all(), [
            'fecha'         => ['required', 'date'],
            'motivo'        => ['required', 'string', 'max:255'],
            'red_social'    => ['required', 'string', 'in:facebook,instagram,linkedin,tiktok'],
            'descripcion'   => ['nullable', 'string'],
            'clasificacion' => ['nullable', 'string', 'max:60'],
        ]);

        if ($v->fails()) {
            return response()->json(['ok' => false, 'errors' => $v->errors()], 422);
        }

        $params = $request->only(['fecha', 'motivo', 'red_social', 'descripcion', 'clasificacion']);
        $params['solicitante_id'] = $request->user() ? $request->user()->id : null;

        $result = $this->orchestrator->createExtraordinary($params);

        return response()->json([
            'ok'            => true,
            'extraordinary' => $result['extraordinary'],
            'note'          => $result['note'],
        ], 201);
    }

    /**
     * PUT /marketing/mkt03/extraordinary/{id}/decide
     *
     * Decidir si una pieza extraordinaria debe alimentar futuras grillas
     * (incorporarla como patrón aprendido).
     */
    public function decide(Request $request, $id)
    {
        $extra = ExtraordinaryContent::findOrFail($id);

        $v = Validator::make($request->all(), [
            'approved'        => ['required', 'boolean'],
            'motivo_decision' => ['nullable', 'string'],
        ]);

        if ($v->fails()) {
            return response()->json(['ok' => false, 'errors' => $v->errors()], 422);
        }

        $decisorId = $request->user() ? $request->user()->id : null;

        $updated = $this->orchestrator->decideExtraordinaryForFuture(
            $extra,
            (bool) $request->input('approved'),
            $decisorId,
            $request->input('motivo_decision')
        );

        return response()->json(['ok' => true, 'extraordinary' => $updated]);
    }

    /**
     * DELETE /marketing/mkt03/extraordinary/{id}
     */
    public function destroy($id)
    {
        $extra = ExtraordinaryContent::findOrFail($id);
        $extra->delete();

        return response()->json(['ok' => true, 'message' => 'Extraordinario eliminado']);
    }
}
