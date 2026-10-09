<?php

namespace Modules\Marketing\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
use Modules\Marketing\Models\ChatbotCatalog;

/**
 * ChatbotCatalogsController
 *
 * CRUD de catálogos desde el panel de administración del ERP.
 * Rutas protegidas con autenticación.
 */
class ChatbotCatalogsController extends Controller
{
    /**
     * Listar catálogos.
     */
    public function index()
    {
        $catalogs = ChatbotCatalog::orderBy('is_general', 'desc')
            ->orderBy('sort_order')
            ->orderBy('line_name')
            ->get();

        return response()->json([
            'ok'       => true,
            'catalogs' => $catalogs,
        ]);
    }

    /**
     * Crear catálogo.
     */
    public function store(Request $request)
    {
        $v = Validator::make($request->all(), [
            'line_name'   => ['required', 'string', 'max:100'],
            'catalog_url' => ['required', 'string', 'max:500'],
            'is_general'  => ['nullable', 'boolean'],
            'sort_order'  => ['nullable', 'integer'],
        ]);

        if ($v->fails()) {
            return response()->json(['ok' => false, 'errors' => $v->errors()], 422);
        }

        $catalog = ChatbotCatalog::create([
            'line_name'   => $request->input('line_name'),
            'catalog_url' => $request->input('catalog_url'),
            'is_general'  => $request->input('is_general', false),
            'active'      => true,
            'sort_order'  => $request->input('sort_order', 0),
        ]);

        return response()->json(['ok' => true, 'catalog' => $catalog], 201);
    }

    /**
     * Actualizar catálogo.
     */
    public function update(Request $request, $id)
    {
        $catalog = ChatbotCatalog::findOrFail($id);

        $v = Validator::make($request->all(), [
            'line_name'   => ['sometimes', 'string', 'max:100'],
            'catalog_url' => ['sometimes', 'string', 'max:500'],
            'is_general'  => ['sometimes', 'boolean'],
            'active'      => ['sometimes', 'boolean'],
            'sort_order'  => ['sometimes', 'integer'],
        ]);

        if ($v->fails()) {
            return response()->json(['ok' => false, 'errors' => $v->errors()], 422);
        }

        $catalog->update($request->only([
            'line_name', 'catalog_url', 'is_general', 'active', 'sort_order',
        ]));

        return response()->json(['ok' => true, 'catalog' => $catalog->fresh()]);
    }

    /**
     * Eliminar catálogo.
     */
    public function destroy($id)
    {
        $catalog = ChatbotCatalog::findOrFail($id);
        $catalog->delete();

        return response()->json(['ok' => true, 'message' => 'Catálogo eliminado.']);
    }
}
