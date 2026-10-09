<?php

namespace Modules\Marketing\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Modules\Marketing\Models\QuotationConfig;
use Modules\Marketing\Models\QuotationSystemSuggestion;
use Modules\Marketing\Services\QuotationPatternAnalyzer;

/**
 * QuotationSystemSuggestionController — COM-02 S4
 *
 * CRUD de sugerencias generadas por el QuotationPatternAnalyzer.
 *
 * Endpoints (auth):
 *   GET  /quotation/suggestions                   listar (filtros: status)
 *   POST /quotation/suggestions/analyze-now       on-demand: corre el analyzer
 *   POST /quotation/suggestions/{id}/apply        aplica + actualiza quotation_config
 *   POST /quotation/suggestions/{id}/reject       rechaza con razón opcional
 *   POST /quotation/suggestions/{id}/snooze       posterga N días (default 30)
 */
class QuotationSystemSuggestionController extends Controller
{
    /** @var QuotationPatternAnalyzer */
    private $analyzer;

    public function __construct(QuotationPatternAnalyzer $analyzer)
    {
        $this->analyzer = $analyzer;
    }

    /**
     * GET /quotation/suggestions
     */
    public function index(Request $request)
    {
        $q = QuotationSystemSuggestion::query();

        $status = $request->input('status', 'pending_effective');
        if ($status === 'pending_effective') {
            $q->pending();
        } elseif (in_array($status, [
            QuotationSystemSuggestion::STATUS_PENDING,
            QuotationSystemSuggestion::STATUS_APPLIED,
            QuotationSystemSuggestion::STATUS_REJECTED,
            QuotationSystemSuggestion::STATUS_SNOOZED,
        ], true)) {
            $q->where('status', $status);
        }
        // 'all' no filtra

        if ($request->filled('type')) {
            $q->where('type', $request->input('type'));
        }
        if ($request->filled('category')) {
            $q->where('category', $request->input('category'));
        }

        $rows = $q->orderByDesc('confidence')
            ->orderByDesc('evidence_count')
            ->orderByDesc('created_at')
            ->limit(200)
            ->get();

        return response()->json([
            'ok'          => true,
            'suggestions' => $rows,
            'count'       => $rows->count(),
        ]);
    }

    /**
     * POST /quotation/suggestions/analyze-now
     */
    public function analyzeNow()
    {
        try {
            $stats = $this->analyzer->analyze();
            return response()->json([
                'ok'    => true,
                'stats' => $stats,
                'message' => sprintf(
                    'Análisis completado: %d patrones examinados, %d sugerencias detectadas (%d nuevas, %d actualizadas).',
                    $stats['patterns_examined'], $stats['detected'], $stats['created'], $stats['updated']
                ),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'ok'      => false,
                'message' => 'Error en el análisis: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * POST /quotation/suggestions/{id}/apply
     *
     * Actualiza la key de quotation_config y marca la sugerencia como applied.
     */
    public function apply($id)
    {
        $sug = QuotationSystemSuggestion::find($id);
        if (!$sug) {
            return response()->json(['ok' => false, 'message' => 'Sugerencia no encontrada.'], 404);
        }
        if ($sug->isTerminal()) {
            return response()->json([
                'ok'      => false,
                'message' => 'La sugerencia ya está en estado: ' . $sug->status,
            ], 422);
        }

        $userId = $this->resolveUserId();

        try {
            // Aplicar al config
            QuotationConfig::setValue($sug->target_config_key, $sug->suggested_value);

            // Marcar como aplicada
            $sug->markApplied($userId);

            return response()->json([
                'ok'         => true,
                'message'    => 'Sugerencia aplicada. Configuración actualizada.',
                'suggestion' => $sug->fresh(),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'ok'      => false,
                'message' => 'Error aplicando sugerencia: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * POST /quotation/suggestions/{id}/reject
     *
     * Body: { reason?: string }
     */
    public function reject(Request $request, $id)
    {
        $v = Validator::make($request->all(), [
            'reason' => ['nullable', 'string', 'max:255'],
        ]);
        if ($v->fails()) {
            return response()->json(['ok' => false, 'errors' => $v->errors()], 422);
        }

        $sug = QuotationSystemSuggestion::find($id);
        if (!$sug) {
            return response()->json(['ok' => false, 'message' => 'Sugerencia no encontrada.'], 404);
        }
        if ($sug->isTerminal()) {
            return response()->json([
                'ok'      => false,
                'message' => 'La sugerencia ya está en estado: ' . $sug->status,
            ], 422);
        }

        $sug->markRejected($this->resolveUserId(), $request->input('reason'));

        return response()->json([
            'ok'         => true,
            'message'    => 'Sugerencia rechazada.',
            'suggestion' => $sug->fresh(),
        ]);
    }

    /**
     * POST /quotation/suggestions/{id}/snooze
     *
     * Body: { days?: int (default 30) }
     */
    public function snooze(Request $request, $id)
    {
        $v = Validator::make($request->all(), [
            'days' => ['nullable', 'integer', 'min:1', 'max:365'],
        ]);
        if ($v->fails()) {
            return response()->json(['ok' => false, 'errors' => $v->errors()], 422);
        }

        $sug = QuotationSystemSuggestion::find($id);
        if (!$sug) {
            return response()->json(['ok' => false, 'message' => 'Sugerencia no encontrada.'], 404);
        }
        if ($sug->isTerminal()) {
            return response()->json([
                'ok'      => false,
                'message' => 'La sugerencia ya está en estado: ' . $sug->status,
            ], 422);
        }

        $days = (int) $request->input('days', 30);
        $sug->snoozeFor($days);

        return response()->json([
            'ok'         => true,
            'message'    => "Sugerencia pospuesta {$days} días.",
            'suggestion' => $sug->fresh(),
        ]);
    }

    private function resolveUserId()
    {
        $u = Auth::user();
        return $u && isset($u->id) ? (int) $u->id : 0;
    }
}
