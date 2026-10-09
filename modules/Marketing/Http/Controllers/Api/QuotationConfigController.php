<?php

namespace Modules\Marketing\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
use Modules\Marketing\Models\QuotationConfig;
use Modules\Marketing\Models\MarginRule;
use Modules\Marketing\Services\MarginService;

/**
 * QuotationConfigController — Configuración del cotizador COM-02
 *
 * CRUD de configuración paramétrica y reglas de margen.
 */
class QuotationConfigController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Configuración general
    |--------------------------------------------------------------------------
    */

    /**
     * Listar toda la configuración.
     *
     * GET /api/marketing/quotation/config
     */
    public function configIndex()
    {
        $configs = QuotationConfig::orderBy('config_key')->get();

        return response()->json([
            'ok'      => true,
            'configs' => $configs,
        ]);
    }

    /**
     * Actualizar un valor de configuración.
     *
     * PUT /api/marketing/quotation/config/{key}
     */
    public function configUpdate(Request $request, $key)
    {
        $config = QuotationConfig::where('config_key', $key)->firstOrFail();

        $v = Validator::make($request->all(), [
            'config_value' => ['required'],
        ]);

        if ($v->fails()) {
            return response()->json(['ok' => false, 'errors' => $v->errors()], 422);
        }

        $config->update([
            'config_value' => (string) $request->input('config_value'),
        ]);

        return response()->json([
            'ok'     => true,
            'config' => $config->fresh(),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Reglas de margen
    |--------------------------------------------------------------------------
    */

    /**
     * Listar reglas de margen.
     *
     * GET /api/marketing/quotation/margins
     */
    public function marginsIndex(Request $request)
    {
        $query = MarginRule::orderByDesc('priority')
            ->orderBy('category')
            ->orderBy('quantity_from');

        if ($request->filled('category')) {
            $query->byCategory($request->input('category'));
        }

        if ($request->filled('active')) {
            $active = filter_var($request->input('active'), FILTER_VALIDATE_BOOLEAN);
            $query->where('active', $active);
        }

        $margins = $query->get();

        return response()->json([
            'ok'      => true,
            'margins' => $margins,
        ]);
    }

    /**
     * Crear regla de margen.
     *
     * POST /api/marketing/quotation/margins
     */
    public function marginsStore(Request $request)
    {
        $v = Validator::make($request->all(), [
            'category'              => ['required', 'string', 'max:100'],
            'product_name'          => ['nullable', 'string', 'max:255'],
            'quantity_from'         => ['required', 'integer', 'min:1'],
            'quantity_to'           => ['nullable', 'integer'],
            'margin_percentage'     => ['required', 'numeric', 'min:0', 'max:999'],
            'min_margin_percentage' => ['nullable', 'numeric', 'min:0', 'max:999'],
        ]);

        if ($v->fails()) {
            return response()->json(['ok' => false, 'errors' => $v->errors()], 422);
        }

        // Coherencia: el mínimo no puede ser mayor al sugerido (Fase B).
        $minMargin = $request->input('min_margin_percentage');
        $margin    = (float) $request->input('margin_percentage');
        if ($minMargin !== null && $minMargin !== '' && (float) $minMargin > $margin) {
            return response()->json([
                'ok'      => false,
                'message' => 'El margen mínimo no puede ser mayor al margen sugerido.',
            ], 422);
        }

        $productName = $request->input('product_name');
        $priority = !empty($productName) ? 2 : 1;

        $margin = MarginRule::create([
            'category'              => $request->input('category'),
            'product_name'          => $productName ?: null,
            'quantity_from'         => $request->input('quantity_from'),
            'quantity_to'           => $request->input('quantity_to'),
            'margin_percentage'     => $request->input('margin_percentage'),
            'min_margin_percentage' => ($minMargin === null || $minMargin === '') ? null : $minMargin,
            'priority'              => $priority,
            'active'                => true,
        ]);

        return response()->json(['ok' => true, 'margin' => $margin], 201);
    }

    /**
     * Editar regla de margen.
     *
     * PUT /api/marketing/quotation/margins/{id}
     */
    public function marginsUpdate(Request $request, $id)
    {
        $margin = MarginRule::findOrFail($id);

        $v = Validator::make($request->all(), [
            'category'              => ['sometimes', 'string', 'max:100'],
            'product_name'          => ['nullable', 'string', 'max:255'],
            'quantity_from'         => ['sometimes', 'integer', 'min:1'],
            'quantity_to'           => ['nullable', 'integer'],
            'margin_percentage'     => ['sometimes', 'numeric', 'min:0', 'max:999'],
            'min_margin_percentage' => ['nullable', 'numeric', 'min:0', 'max:999'],
            'active'                => ['sometimes', 'boolean'],
        ]);

        if ($v->fails()) {
            return response()->json(['ok' => false, 'errors' => $v->errors()], 422);
        }

        // Coherencia: el mínimo no puede ser mayor al sugerido. Usamos el valor
        // efectivo después del update (lo que viene en el request o lo actual).
        $newMargin = $request->has('margin_percentage')
            ? (float) $request->input('margin_percentage')
            : (float) $margin->margin_percentage;
        $newMin = $request->has('min_margin_percentage')
            ? $request->input('min_margin_percentage')
            : $margin->min_margin_percentage;
        if ($newMin !== null && $newMin !== '' && (float) $newMin > $newMargin) {
            return response()->json([
                'ok'      => false,
                'message' => 'El margen mínimo no puede ser mayor al margen sugerido.',
            ], 422);
        }

        $updates = $request->only([
            'category', 'product_name', 'quantity_from', 'quantity_to',
            'margin_percentage', 'min_margin_percentage', 'active',
        ]);

        // Normalizar: string vacío → null (Element UI envía '' cuando se limpia el input).
        if (array_key_exists('min_margin_percentage', $updates) && $updates['min_margin_percentage'] === '') {
            $updates['min_margin_percentage'] = null;
        }

        $margin->update($updates);

        // Recalcular prioridad
        if ($request->has('product_name')) {
            $margin->update([
                'priority' => !empty($margin->product_name) ? 2 : 1,
            ]);
        }

        return response()->json(['ok' => true, 'margin' => $margin->fresh()]);
    }

    /**
     * Eliminar regla de margen.
     *
     * DELETE /api/marketing/quotation/margins/{id}
     */
    public function marginsDestroy($id)
    {
        $margin = MarginRule::findOrFail($id);
        $margin->delete();

        return response()->json(['ok' => true, 'message' => 'Regla de margen eliminada.']);
    }

    /**
     * Importar reglas de margen desde CSV/JSON.
     *
     * POST /api/marketing/quotation/margins/import
     *
     * Body JSON: { "rows": [{ "category": "Textil", "quantity_from": 1, "quantity_to": 100, "margin_percentage": 35 }, ...] }
     */
    public function marginsImport(Request $request)
    {
        $v = Validator::make($request->all(), [
            'rows'                      => ['required', 'array', 'min:1'],
            'rows.*.category'           => ['required', 'string'],
            'rows.*.margin_percentage'  => ['required', 'numeric'],
        ]);

        if ($v->fails()) {
            return response()->json(['ok' => false, 'errors' => $v->errors()], 422);
        }

        $marginService = app(MarginService::class);
        $result = $marginService->importFromArray($request->input('rows'));

        return response()->json([
            'ok'       => true,
            'imported' => $result['imported'],
            'errors'   => $result['errors'],
        ]);
    }
}
