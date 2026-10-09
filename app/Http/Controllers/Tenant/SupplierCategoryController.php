<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\SupplierCategory;
use App\Models\Tenant\SupplierSubcategory;
use Illuminate\Http\Request;

/**
 * CRUD básico para Categorías y Subcategorías de Proveedor.
 * Usado desde el form del proveedor como dropdown "creatable".
 *
 * Rutas (src/routes/web.php):
 *   GET  /persons/suppliers/categories              -> index (todas activas con subcats)
 *   POST /persons/suppliers/categories              -> storeCategory (crear al vuelo)
 *   POST /persons/suppliers/subcategories           -> storeSubcategory (crear al vuelo)
 */
class SupplierCategoryController extends Controller
{
    /**
     * Lista categorías activas con sus subcategorías activas.
     */
    public function index()
    {
        $categories = SupplierCategory::active()
            ->with(['subcategories' => function ($q) { $q->where('estado', 1)->orderBy('orden')->orderBy('nombre'); }])
            ->orderBy('orden')
            ->orderBy('nombre')
            ->get()
            ->map(function ($c) {
                return [
                    'id' => $c->id,
                    'nombre' => $c->nombre,
                    'orden' => $c->orden,
                    'subcategorias' => $c->subcategories->map(function ($s) {
                        return [
                            'id' => $s->id,
                            'nombre' => $s->nombre,
                            'supplier_category_id' => $s->supplier_category_id,
                            'orden' => $s->orden,
                        ];
                    }),
                ];
            });

        return response()->json(['ok' => true, 'data' => $categories]);
    }

    /**
     * Crea una categoría al vuelo (desde el form del proveedor).
     */
    public function storeCategory(Request $request)
    {
        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:120'],
            'descripcion' => ['nullable', 'string', 'max:255'],
        ]);

        $nombre = trim(mb_strtoupper($data['nombre']));
        $existing = SupplierCategory::where('nombre', $nombre)->first();
        if ($existing) {
            return response()->json([
                'success' => true,
                'message' => 'La categoría ya existía',
                'id' => $existing->id,
                'data' => ['id' => $existing->id, 'nombre' => $existing->nombre],
            ]);
        }

        $cat = SupplierCategory::create([
            'nombre' => $nombre,
            'descripcion' => $data['descripcion'] ?? null,
            'orden' => 500,
            'estado' => 1,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Categoría creada',
            'id' => $cat->id,
            'data' => ['id' => $cat->id, 'nombre' => $cat->nombre],
        ]);
    }

    /**
     * Crea una subcategoría al vuelo.
     */
    public function storeSubcategory(Request $request)
    {
        $data = $request->validate([
            'supplier_category_id' => ['required', 'integer', 'exists:supplier_categories,id'],
            'nombre' => ['required', 'string', 'max:120'],
            'descripcion' => ['nullable', 'string', 'max:255'],
        ]);

        $existing = SupplierSubcategory::where('supplier_category_id', $data['supplier_category_id'])
            ->where('nombre', $data['nombre'])
            ->first();

        if ($existing) {
            return response()->json([
                'success' => true,
                'message' => 'La subcategoría ya existía',
                'id' => $existing->id,
                'data' => ['id' => $existing->id, 'nombre' => $existing->nombre],
            ]);
        }

        $sub = SupplierSubcategory::create([
            'supplier_category_id' => $data['supplier_category_id'],
            'nombre' => $data['nombre'],
            'descripcion' => $data['descripcion'] ?? null,
            'orden' => 500,
            'estado' => 1,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Subcategoría creada',
            'id' => $sub->id,
            'data' => ['id' => $sub->id, 'nombre' => $sub->nombre, 'supplier_category_id' => $sub->supplier_category_id],
        ]);
    }
}
