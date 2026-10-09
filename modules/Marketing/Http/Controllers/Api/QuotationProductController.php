<?php

namespace Modules\Marketing\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Modules\Marketing\Models\PreQuoteItem;
use Modules\Marketing\Services\ProductMatchingService;
use Modules\Marketing\Services\QuotationCalculationEngine;

/**
 * QuotationProductController — COM-02 S1
 *
 * Endpoints relacionados al matching de productos del catálogo ERP contra
 * los ítems detectados por el LLM en una precotización.
 *
 * Endpoints:
 *   GET  /marketing/quotation/products/search?q=...&limit=20
 *        Búsqueda libre del catálogo, devuelve top-N candidatos con score.
 *        Usado por la UI cuando la ejecutiva quiere buscar manualmente.
 *
 *   GET  /marketing/quotation/prequote-items/{id}/suggestions
 *        Devuelve las sugerencias guardadas + el mejor match actual del ítem.
 *
 *   POST /marketing/quotation/prequote-items/{id}/match
 *        Body: { item_id: <int|null> }
 *        Asocia manualmente el ítem a un producto del catálogo, o "ninguno"
 *        si nada corresponde (item_id=null).
 *
 *   POST /marketing/quotation/prequote-items/{id}/rematch
 *        Re-ejecuta el matching automático con el productName actual
 *        (útil si la ejecutiva editó el producto del ítem).
 */
class QuotationProductController extends Controller
{
    /** @var ProductMatchingService */
    private $matcher;

    /** @var QuotationCalculationEngine */
    private $calcEngine;

    public function __construct(ProductMatchingService $matcher, QuotationCalculationEngine $calcEngine)
    {
        $this->matcher    = $matcher;
        $this->calcEngine = $calcEngine;
    }

    /**
     * GET /products/search
     */
    public function search(Request $request)
    {
        $v = Validator::make($request->all(), [
            'q'            => ['required', 'string', 'min:2', 'max:255'],
            'limit'        => ['nullable', 'integer', 'min:1', 'max:50'],
            'item_type_id' => ['nullable', 'string', 'max:5'],
        ]);
        if ($v->fails()) {
            return response()->json(['ok' => false, 'errors' => $v->errors()], 422);
        }

        $context = [];
        if ($request->filled('item_type_id')) {
            $context['item_type_id'] = $request->input('item_type_id');
        }

        $results = $this->matcher->search(
            $request->input('q'),
            $context,
            (int) $request->input('limit', 20)
        );

        return response()->json([
            'ok'      => true,
            'query'   => $request->input('q'),
            'count'   => count($results),
            'results' => $results,
        ]);
    }

    /**
     * GET /prequote-items/{id}/suggestions
     */
    public function suggestions($id)
    {
        $item = PreQuoteItem::with('catalogItem')->find($id);
        if (!$item) {
            return response()->json(['ok' => false, 'message' => 'Ítem no encontrado.'], 404);
        }

        return response()->json([
            'ok'   => true,
            'item' => [
                'id'                       => $item->id,
                'prequote_id'              => $item->prequote_id,
                'product_name'             => $item->product_name,
                'product_description'      => $item->product_description,
                'item_id'                  => $item->item_id,
                'catalog_item_name'        => $item->catalogItem ? $item->catalogItem->name : null,
                'product_match_confidence' => $item->product_match_confidence,
                'needs_product_review'     => $item->needs_product_review,
                'suggestions'              => $item->product_suggestions ?: [],
            ],
        ]);
    }

    /**
     * POST /prequote-items/{id}/match
     *
     * Body: { item_id: int|null }
     */
    public function manualMatch(Request $request, $id)
    {
        $v = Validator::make($request->all(), [
            'item_id' => ['nullable', 'integer', 'min:1'],
        ]);
        if ($v->fails()) {
            return response()->json(['ok' => false, 'errors' => $v->errors()], 422);
        }

        $item = PreQuoteItem::find($id);
        if (!$item) {
            return response()->json(['ok' => false, 'message' => 'Ítem no encontrado.'], 404);
        }

        // La prequote debe estar editable: matchear un producto reprecia el ítem
        // (trae el costo del catálogo y recalcula toda la prequote). No se toca una
        // precotización congelada (validada/aprobada/convertida), igual que
        // updateItem / updateSctr / recalculate.
        if ($item->prequote && !$item->prequote->isEditable()) {
            return response()->json([
                'ok'      => false,
                'message' => 'La precotización no es editable en estado: ' . $item->prequote->estado,
            ], 422);
        }

        $itemId = $request->input('item_id');

        // Si pasaron item_id, validar que existe Y leer el producto para
        // poder copiar el sale_unit_price al costo_base del prequote_item
        // (cuando este último esté en 0 — no sobrescribir ediciones manuales).
        $catalogItem = null;
        if ($itemId !== null) {
            $catalogItem = \App\Models\Tenant\Item::where('id', $itemId)
                ->where('active', true)
                ->first();
            if (!$catalogItem) {
                return response()->json([
                    'ok' => false,
                    'message' => 'El producto seleccionado no existe o está inactivo.',
                ], 422);
            }
        }

        $item->manualMatchTo($itemId);

        // Auto-traer el costo desde el catálogo cuando:
        //   1. Se asoció un producto del catálogo (itemId != null), y
        //   2. El catálogo tiene un sale_unit_price > 0.
        //
        // Decisión del cliente (2026-05-18): el catálogo SIEMPRE manda — incluso
        // si la ejecutiva había escrito un costo manualmente, se sobrescribe
        // con el precio del catálogo. Excepción: si el catálogo no tiene
        // precio (sale_unit_price = 0), no tocamos lo que tenga el ítem (no
        // hay valor útil con qué reemplazarlo).
        $costoTraido = null;
        $igvRemovido = false;
        if ($catalogItem !== null) {
            $catalogPrice = (float) $catalogItem->sale_unit_price;

            if ($catalogPrice > 0.0) {
                // El costo_base del cotizador es SIN IGV (el motor de cálculo
                // le suma el IGV al final). Pero en el catálogo el precio puede
                // venir con o sin IGV según el flag `has_igv`:
                //   - has_igv = 1 → sale_unit_price YA incluye IGV → se lo
                //     quitamos antes de usarlo como costo_base (evita IGV doble).
                //   - has_igv = 0 → sale_unit_price ya es base → se usa directo.
                $costoSinIgv = $catalogPrice;
                $catalogHasIgv = (bool) $catalogItem->has_igv;
                if ($catalogHasIgv) {
                    $igvPct = (float) \Modules\Marketing\Models\QuotationConfig::getValue('igv_percentage', 18);
                    $igvFactor = 1 + ($igvPct / 100);
                    if ($igvFactor > 0) {
                        $costoSinIgv = $catalogPrice / $igvFactor;
                        $igvRemovido = true;
                    }
                }

                $item->costo_base = round($costoSinIgv, 2);
                $item->save();
                $costoTraido = round($costoSinIgv, 2);

                // Recalcular este ítem (valor_unitario, IGV, total) con el nuevo costo
                $this->calcEngine->recalculateItem($item);
                // Y los totales de la prequote padre
                if ($item->prequote) {
                    $this->calcEngine->recalculate($item->prequote);
                }

                Log::info('QuotationProductController::manualMatch — costo traído del catálogo', [
                    'prequote_item_id' => $item->id,
                    'catalog_item_id'  => $catalogItem->id,
                    'sale_unit_price'  => $catalogPrice,
                    'catalog_has_igv'  => $catalogHasIgv,
                    'costo_base_sin_igv' => $costoTraido,
                ]);
            }
        }

        $fresh = $item->fresh(['catalogItem']);
        $message = $itemId !== null
            ? 'Producto asignado correctamente.'
            : 'Ítem marcado como "sin producto del catálogo".';
        if ($costoTraido !== null) {
            $message .= ' Costo base actualizado desde catálogo: S/ ' . number_format($costoTraido, 2);
            if ($igvRemovido) {
                $message .= ' (precio del catálogo incluía IGV; se registró el valor sin IGV)';
            }
        }

        $response = [
            'ok'           => true,
            'item'         => $fresh,
            'costo_traido' => $costoTraido,  // null si no se trajo (catálogo sin precio). Ya es SIN IGV.
            'igv_removido' => $igvRemovido,  // true si el precio del catálogo incluía IGV y se le descontó
            'message'      => $message,
        ];

        // Si se actualizó el costo, devolver también la prequote con totales
        // recalculados para que el frontend refresque subtotal / IGV / total
        // sin necesidad de una segunda llamada.
        if ($costoTraido !== null && $item->prequote) {
            $response['prequote'] = $item->prequote->fresh(['items']);
        }

        return response()->json($response);
    }

    /**
     * POST /prequote-items/{id}/rematch
     *
     * Re-ejecuta el matching automático contra el catálogo. Útil si la
     * ejecutiva editó el product_name o product_description y quiere que el
     * sistema vuelva a sugerir.
     */
    public function rematch($id)
    {
        $item = PreQuoteItem::find($id);
        if (!$item) {
            return response()->json(['ok' => false, 'message' => 'Ítem no encontrado.'], 404);
        }

        $result = $this->matcher->match($item->product_name, $item->product_description);
        $item->applyMatchResult($result);

        return response()->json([
            'ok'     => true,
            'item'   => $item->fresh(['catalogItem']),
            'result' => [
                'best_match'   => $result['best_match'],
                'confidence'   => $result['confidence'],
                'needs_review' => $result['needs_review'],
                'suggestions'  => $result['suggestions'],
            ],
        ]);
    }
}
