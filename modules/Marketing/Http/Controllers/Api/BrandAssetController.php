<?php

namespace Modules\Marketing\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
use Modules\Marketing\Models\BrandAsset;
use Modules\Marketing\Services\BrandAssetService;

/**
 * BrandAssetController — CRUD del Brand Asset Library (BAL).
 *
 * Endpoints (todos protegidos por auth + tenant + email verified):
 *   GET    /marketing/brand-assets             listado paginable con filtros
 *   POST   /marketing/brand-assets             crear (multipart si tiene archivo)
 *   GET    /marketing/brand-assets/{id}        detalle
 *   PUT    /marketing/brand-assets/{id}        actualizar (sin archivo)
 *   POST   /marketing/brand-assets/{id}/file   reemplazar archivo
 *   DELETE /marketing/brand-assets/{id}        soft delete
 */
class BrandAssetController extends Controller
{
    /** @var BrandAssetService */
    private $service;

    public function __construct(BrandAssetService $service)
    {
        $this->service = $service;
    }

    /**
     * GET /marketing/brand-assets
     */
    public function index(Request $request)
    {
        $query = BrandAsset::query();

        // Filtros
        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }
        if ($request->filled('agent')) {
            $query->forAgent($request->input('agent'));
        }
        if ($request->filled('active')) {
            $active = filter_var($request->input('active'), FILTER_VALIDATE_BOOLEAN);
            $query->where('active', $active);
        }
        if ($request->filled('q')) {
            $q = $request->input('q');
            $query->where(function ($w) use ($q) {
                $w->where('name', 'LIKE', "%{$q}%")
                  ->orWhere('description', 'LIKE', "%{$q}%");
            });
        }
        if ($request->filled('tag')) {
            $query->byTags([$request->input('tag')]);
        }

        $query->ordered();

        $perPage = (int) $request->input('per_page', 24);
        $perPage = max(1, min(100, $perPage));

        $assets = $query->paginate($perPage);

        return response()->json([
            'ok'     => true,
            'assets' => $assets,
        ]);
    }

    /**
     * GET /marketing/brand-assets/{id}
     */
    public function show($id)
    {
        $asset = BrandAsset::findOrFail($id);
        return response()->json(['ok' => true, 'asset' => $asset]);
    }

    /**
     * POST /marketing/brand-assets
     *
     * Multipart cuando se sube archivo. JSON cuando es color/text.
     */
    public function store(Request $request)
    {
        $v = Validator::make($request->all(), [
            'type'          => 'required|string|in:' . implode(',', BrandAsset::$TYPES),
            'name'          => 'required|string|max:150',
            'description'   => 'nullable|string',
            'hex_color'     => 'nullable|string|max:9',
            'text_value'    => 'nullable|string',
            'usage_context' => 'nullable|string',
            'tags'          => 'nullable',
            'use_in_mkt01'  => 'nullable',
            'use_in_com02'  => 'nullable',
            'use_in_mkt03'  => 'nullable',
            'active'        => 'nullable',
            'sort_order'    => 'nullable|integer',
            'file'          => 'nullable|file|max:10240', // 10MB
        ]);

        if ($v->fails()) {
            return response()->json(['ok' => false, 'errors' => $v->errors()], 422);
        }

        $data = $request->all();

        // tags pueden venir como string CSV o como JSON string desde formulario
        if (isset($data['tags']) && is_string($data['tags'])) {
            $decoded = json_decode($data['tags'], true);
            if (is_array($decoded)) {
                $data['tags'] = $decoded;
            }
        }

        try {
            $userId = $request->user() ? $request->user()->id : null;
            $file   = $request->file('file');

            $asset = $this->service->create($data, $file, $userId);

            return response()->json(['ok' => true, 'asset' => $asset], 201);

        } catch (\InvalidArgumentException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            return response()->json(['ok' => false, 'message' => 'Error creando asset: ' . $e->getMessage()], 500);
        }
    }

    /**
     * PUT /marketing/brand-assets/{id}
     *
     * Actualización de campos (sin archivo). Para reemplazar archivo, usar replaceFile().
     */
    public function update(Request $request, $id)
    {
        $asset = BrandAsset::findOrFail($id);

        $v = Validator::make($request->all(), [
            'name'          => 'nullable|string|max:150',
            'description'   => 'nullable|string',
            'hex_color'     => 'nullable|string|max:9',
            'text_value'    => 'nullable|string',
            'usage_context' => 'nullable|string',
            'tags'          => 'nullable',
            'use_in_mkt01'  => 'nullable',
            'use_in_com02'  => 'nullable',
            'use_in_mkt03'  => 'nullable',
            'active'        => 'nullable',
            'sort_order'    => 'nullable|integer',
        ]);

        if ($v->fails()) {
            return response()->json(['ok' => false, 'errors' => $v->errors()], 422);
        }

        $data = $request->all();
        if (isset($data['tags']) && is_string($data['tags'])) {
            $decoded = json_decode($data['tags'], true);
            if (is_array($decoded)) {
                $data['tags'] = $decoded;
            }
        }

        try {
            $asset = $this->service->update($asset, $data);
            return response()->json(['ok' => true, 'asset' => $asset]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            return response()->json(['ok' => false, 'message' => 'Error actualizando asset: ' . $e->getMessage()], 500);
        }
    }

    /**
     * POST /marketing/brand-assets/{id}/file
     *
     * Reemplazar archivo de un asset file-based.
     */
    public function replaceFile(Request $request, $id)
    {
        $asset = BrandAsset::findOrFail($id);

        if (!$asset->isFileBased()) {
            return response()->json([
                'ok'      => false,
                'message' => 'Este tipo de asset no soporta reemplazo de archivo',
            ], 422);
        }

        $v = Validator::make($request->all(), [
            'file' => 'required|file|max:10240',
        ]);

        if ($v->fails()) {
            return response()->json(['ok' => false, 'errors' => $v->errors()], 422);
        }

        try {
            $asset = $this->service->update($asset, [], $request->file('file'));
            return response()->json(['ok' => true, 'asset' => $asset]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            return response()->json(['ok' => false, 'message' => 'Error reemplazando archivo: ' . $e->getMessage()], 500);
        }
    }

    /**
     * DELETE /marketing/brand-assets/{id}
     */
    public function destroy($id)
    {
        $asset = BrandAsset::findOrFail($id);
        $this->service->delete($asset);
        return response()->json(['ok' => true, 'message' => 'Asset eliminado']);
    }
}
