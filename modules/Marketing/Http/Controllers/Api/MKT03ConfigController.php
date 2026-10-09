<?php

namespace Modules\Marketing\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Modules\Marketing\Models\HistoricalGrid;
use Modules\Marketing\Models\LearningPattern;
use Modules\Marketing\Models\PlatformConfig;
use Modules\Marketing\Services\ExcelHistoricalImporter;
use Modules\Marketing\Services\GridPatternAnalyzer;

/**
 * MKT03ConfigController — Configuración y administración del agente MKT-03.
 *
 * Maneja 3 subrecursos:
 *  - platform-config: configuración por red social
 *  - patterns: patrones aprendidos (historial + aprobación)
 *  - historical-grids: importación y análisis de grillas históricas (Excel)
 */
class MKT03ConfigController extends Controller
{
    /** @var GridPatternAnalyzer */
    private $patternAnalyzer;

    /** @var ExcelHistoricalImporter */
    private $excelImporter;

    public function __construct(
        GridPatternAnalyzer $patternAnalyzer,
        ExcelHistoricalImporter $excelImporter
    ) {
        $this->patternAnalyzer = $patternAnalyzer;
        $this->excelImporter   = $excelImporter;
    }

    /*
    |--------------------------------------------------------------------------
    | PlatformConfig
    |--------------------------------------------------------------------------
    */

    /**
     * GET /marketing/mkt03/platform-config
     */
    public function platformConfigIndex()
    {
        $configs = PlatformConfig::orderBy('red_social')->get();
        return response()->json(['ok' => true, 'configs' => $configs]);
    }

    /**
     * GET /marketing/mkt03/platform-config/{red}
     */
    public function platformConfigShow($red)
    {
        $cfg = PlatformConfig::where('red_social', $red)->firstOrFail();
        return response()->json(['ok' => true, 'config' => $cfg]);
    }

    /**
     * PUT /marketing/mkt03/platform-config/{red}
     */
    public function platformConfigUpdate(Request $request, $red)
    {
        $cfg = PlatformConfig::where('red_social', $red)->firstOrFail();

        $editable = [
            'nombre_publico', 'funcion_editorial_principal', 'que_priorizar',
            'que_evitar', 'tono_recomendado', 'max_copy_length', 'max_hashtags',
            'formatos_soportados', 'aspect_ratio_default', 'aspect_ratios_soportados',
            'comparte_grilla_base', 'red_pareja', 'active',
        ];

        $updates = $request->only($editable);
        $cfg->update($updates);

        return response()->json(['ok' => true, 'config' => $cfg->fresh()]);
    }

    /*
    |--------------------------------------------------------------------------
    | LearningPatterns
    |--------------------------------------------------------------------------
    */

    /**
     * GET /marketing/mkt03/patterns
     */
    public function patternsIndex(Request $request)
    {
        $query = LearningPattern::query()->orderByDesc('confianza');

        if ($request->filled('tipo_patron')) {
            $query->where('tipo_patron', $request->input('tipo_patron'));
        }
        if ($request->filled('red_social')) {
            $query->where(function ($q) use ($request) {
                $red = $request->input('red_social');
                $q->where('red_social', $red)->orWhereNull('red_social');
            });
        }
        if ($request->filled('active')) {
            $query->where('active', filter_var($request->input('active'), FILTER_VALIDATE_BOOLEAN));
        }
        if ($request->filled('fuente')) {
            $query->where('fuente', $request->input('fuente'));
        }

        $patterns = $query->paginate($request->input('per_page', 50));

        return response()->json(['ok' => true, 'patterns' => $patterns]);
    }

    /**
     * GET /marketing/mkt03/patterns/{id}
     */
    public function patternsShow($id)
    {
        $pattern = LearningPattern::with(['historicalGrid', 'extraordinaryContent'])->findOrFail($id);
        return response()->json(['ok' => true, 'pattern' => $pattern]);
    }

    /**
     * PUT /marketing/mkt03/patterns/{id}/approve
     *
     * Aprobar o desactivar un patrón aprendido.
     */
    public function patternsApprove(Request $request, $id)
    {
        $pattern = LearningPattern::findOrFail($id);

        $v = Validator::make($request->all(), [
            'aprobado' => ['required', 'boolean'],
            'active'   => ['nullable', 'boolean'],
        ]);

        if ($v->fails()) {
            return response()->json(['ok' => false, 'errors' => $v->errors()], 422);
        }

        $updates = ['aprobado_por_usuario' => (bool) $request->input('aprobado')];
        if ($request->has('active')) {
            $updates['active'] = (bool) $request->input('active');
        }

        $pattern->update($updates);

        return response()->json(['ok' => true, 'pattern' => $pattern->fresh()]);
    }

    /**
     * DELETE /marketing/mkt03/patterns/{id}
     */
    public function patternsDestroy($id)
    {
        $pattern = LearningPattern::findOrFail($id);
        $pattern->delete();
        return response()->json(['ok' => true, 'message' => 'Patrón eliminado']);
    }

    /*
    |--------------------------------------------------------------------------
    | Historical Grids (Excel import)
    |--------------------------------------------------------------------------
    */

    /**
     * GET /marketing/mkt03/historical
     */
    public function historicalIndex(Request $request)
    {
        $query = HistoricalGrid::query()
            ->withCount('learningPatterns')
            ->orderByDesc('anio');

        if ($request->filled('estado')) {
            $query->where('estado', $request->input('estado'));
        }

        $historicals = $query->paginate($request->input('per_page', 20));

        return response()->json(['ok' => true, 'historicals' => $historicals]);
    }

    /**
     * GET /marketing/mkt03/historical/{id}
     */
    public function historicalShow($id)
    {
        $hg = HistoricalGrid::with(['learningPatterns'])->findOrFail($id);
        return response()->json(['ok' => true, 'historical' => $hg]);
    }

    /**
     * POST /marketing/mkt03/historical/import
     *
     * Importar un Excel histórico. El parseo real lo hará el
     * ExcelHistoricalImporter en Fase 5. Aquí solo registramos el archivo.
     */
    public function historicalImport(Request $request)
    {
        $v = Validator::make($request->all(), [
            'anio'      => ['required', 'integer', 'min:2020', 'max:2100'],
            'nombre'    => ['required', 'string', 'max:255'],
            'archivo'   => ['required', 'file', 'max:204800'], // 200 MB max
        ]);

        if ($v->fails()) {
            return response()->json(['ok' => false, 'errors' => $v->errors()], 422);
        }

        $disk = config('marketing.chatbot.mkt03.image_storage_disk', 'public');
        $filename = 'historical_' . $request->input('anio') . '_' . date('YmdHis') . '.' . $request->file('archivo')->getClientOriginalExtension();
        $storagePath = $request->file('archivo')->storeAs('mkt03/historical', $filename, $disk);

        $hg = HistoricalGrid::create([
            'anio'                  => $request->input('anio'),
            'nombre'                => $request->input('nombre'),
            'archivo_original'      => $request->file('archivo')->getClientOriginalName(),
            'archivo_storage_path'  => $storagePath,
            'estado'                => HistoricalGrid::ESTADO_IMPORTADO,
            'user_id'               => $request->user() ? $request->user()->id : null,
        ]);

        // Disparar parsing automático si el flag está activo
        $autoParse = filter_var($request->input('auto_parse', true), FILTER_VALIDATE_BOOLEAN);
        $parseResult = null;
        if ($autoParse) {
            $parseResult = $this->excelImporter->import($hg);
        }

        return response()->json([
            'ok'            => true,
            'historical'    => $hg->fresh(),
            'parse_result'  => $parseResult,
            'note'          => $autoParse
                ? 'Archivo almacenado y parseado. Ahora puede analizar patrones con IA.'
                : 'Archivo almacenado. Ejecutar /parse para extraer el contenido.',
        ], 201);
    }

    /**
     * POST /marketing/mkt03/historical/{id}/parse
     *
     * Re-ejecutar el parser sobre un archivo ya almacenado.
     */
    public function historicalParse($id)
    {
        $hg = HistoricalGrid::findOrFail($id);

        if (empty($hg->archivo_storage_path)) {
            return response()->json([
                'ok'      => false,
                'message' => 'La grilla histórica no tiene archivo asociado.',
            ], 422);
        }

        try {
            $result = $this->excelImporter->import($hg);
            return response()->json([
                'ok'            => $result['success'],
                'result'        => $result,
                'historical'    => $hg->fresh(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'ok'      => false,
                'message' => 'Error parseando: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * POST /marketing/mkt03/historical/{id}/analyze
     *
     * Analizar patrones de una grilla histórica ya importada (requiere que
     * tenga `contenido_normalizado` poblado).
     */
    public function historicalAnalyze($id)
    {
        $hg = HistoricalGrid::findOrFail($id);

        if (empty($hg->contenido_normalizado)) {
            return response()->json([
                'ok'      => false,
                'message' => 'La grilla histórica no tiene contenido normalizado. Importe el Excel y normalice primero (Fase 5).',
            ], 422);
        }

        try {
            $result = $this->patternAnalyzer->analyzeHistoricalGrid($hg);
            $saved  = $this->patternAnalyzer->savePatterns($result, $hg);

            $hg->update(['estado' => HistoricalGrid::ESTADO_ANALIZADO]);

            return response()->json([
                'ok'                 => true,
                'analysis'           => $result,
                'patterns_saved'     => $saved,
                'historical_grid_id' => $hg->id,
            ]);
        } catch (\Exception $e) {
            $hg->update([
                'estado'        => HistoricalGrid::ESTADO_ERROR,
                'error_message' => $e->getMessage(),
            ]);
            return response()->json([
                'ok'      => false,
                'message' => 'Error analizando: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * DELETE /marketing/mkt03/historical/{id}
     */
    public function historicalDestroy($id)
    {
        $hg = HistoricalGrid::findOrFail($id);

        // Eliminar archivo físico si existe
        if ($hg->archivo_storage_path) {
            $disk = config('marketing.chatbot.mkt03.image_storage_disk', 'public');
            try {
                Storage::disk($disk)->delete($hg->archivo_storage_path);
            } catch (\Exception $e) {
                // ignorar si no existe
            }
        }

        $hg->delete();

        return response()->json(['ok' => true, 'message' => 'Histórico eliminado']);
    }
}
