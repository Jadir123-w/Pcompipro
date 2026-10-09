<?php

namespace Modules\Marketing\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Modules\Marketing\Models\PreQuote;
use Modules\Marketing\Models\PreQuoteItem;

/**
 * QuotationImageController — COM-02 S2
 *
 * Sube/lee/borra la imagen de referencia de una precotización (a nivel
 * prequote completo) o de un ítem específico. La imagen viaja después al
 * LLM multimodal (Gemini/Claude/OpenAI) cuando se interpreta el
 * requerimiento, mejorando la inferencia de material, técnica, medidas y
 * variantes (sec 22 Doc Funcional).
 *
 * Endpoints:
 *   POST   /quotation/prequotes/{id}/reference-image          subir imagen general
 *   GET    /quotation/prequotes/{id}/reference-image          ver imagen (auth)
 *   DELETE /quotation/prequotes/{id}/reference-image          quitar imagen
 *
 *   POST   /quotation/prequote-items/{id}/reference-image     subir imagen de un ítem
 *   GET    /quotation/prequote-items/{id}/reference-image     ver imagen del ítem
 *   DELETE /quotation/prequote-items/{id}/reference-image     quitar imagen del ítem
 *
 * Validación estricta (igual al ChatbotUploadController del MKT-01):
 *   - MIME real con finfo (no Content-Type del cliente)
 *   - Whitelist [JPG, PNG, WEBP, GIF]
 *   - Tamaño max configurable (general_max_upload_mb, default 5MB)
 *   - Storage: quotation-images/{tenant_uuid}/{type}/{entity_id}/{uuid}.{ext}
 */
class QuotationImageController extends Controller
{
    // Validación + persistencia de imágenes (MIME real, tamaño, path), compartida
    // con QuotationPreQuoteController (alta de ítems por referencia).
    use \Modules\Marketing\Http\Controllers\Api\Concerns\InteractsWithQuotationImages;

    /*
    |--------------------------------------------------------------------------
    | Prequote-level
    |--------------------------------------------------------------------------
    */

    public function uploadPrequoteImage(Request $request, $id)
    {
        $prequote = PreQuote::find($id);
        if (!$prequote) {
            return response()->json(['ok' => false, 'message' => 'Prequote no encontrada.'], 404);
        }

        if (!$prequote->isEditable()) {
            return response()->json([
                'ok'      => false,
                'message' => 'No se puede subir imagen a una prequote en estado: ' . $prequote->estado,
            ], 422);
        }

        $result = $this->validateAndStoreImage($request, 'prequotes', $prequote->id);
        if (!$result['ok']) {
            return response()->json($result, $result['_status']);
        }

        // Borrar imagen anterior si existía
        if (!empty($prequote->reference_image_path)) {
            $this->safelyDelete($prequote->reference_image_path);
        }

        $prequote->update([
            'reference_image_path' => $result['stored_path'],
            'reference_image_mime' => $result['mime_type'],
        ]);

        return response()->json([
            'ok'      => true,
            'message' => 'Imagen guardada.',
            'image'   => [
                'path'      => $prequote->reference_image_path,
                'mime'      => $prequote->reference_image_mime,
                'size'      => $result['size_bytes'],
                'human_size'=> $this->humanSize($result['size_bytes']),
            ],
        ], 201);
    }

    public function showPrequoteImage($id)
    {
        $prequote = PreQuote::find($id);
        if (!$prequote || empty($prequote->reference_image_path)) {
            return response()->json(['ok' => false, 'message' => 'Sin imagen.'], 404);
        }

        if (!Storage::disk('local')->exists($prequote->reference_image_path)) {
            return response()->json(['ok' => false, 'message' => 'El archivo ya no está disponible.'], 404);
        }

        return response()->file(Storage::disk('local')->path($prequote->reference_image_path), [
            'Content-Type'        => $prequote->reference_image_mime ?: 'image/jpeg',
            'Cache-Control'       => 'private, max-age=300',
            'Content-Disposition' => 'inline',
        ]);
    }

    public function deletePrequoteImage($id)
    {
        $prequote = PreQuote::find($id);
        if (!$prequote) {
            return response()->json(['ok' => false, 'message' => 'Prequote no encontrada.'], 404);
        }

        if (!empty($prequote->reference_image_path)) {
            $this->safelyDelete($prequote->reference_image_path);
        }

        $prequote->update(['reference_image_path' => null, 'reference_image_mime' => null]);

        return response()->json(['ok' => true, 'message' => 'Imagen eliminada.']);
    }

    /*
    |--------------------------------------------------------------------------
    | Item-level
    |--------------------------------------------------------------------------
    */

    public function uploadItemImage(Request $request, $itemId)
    {
        $item = PreQuoteItem::find($itemId);
        if (!$item) {
            return response()->json(['ok' => false, 'message' => 'Ítem no encontrado.'], 404);
        }

        $result = $this->validateAndStoreImage($request, 'prequote-items', $item->id);
        if (!$result['ok']) {
            return response()->json($result, $result['_status']);
        }

        // Borrar imagen anterior
        if (!empty($item->product_reference_image)) {
            $this->safelyDelete($item->product_reference_image);
        }

        $item->update(['product_reference_image' => $result['stored_path']]);

        return response()->json([
            'ok'      => true,
            'message' => 'Imagen del ítem guardada.',
            'image'   => [
                'path'       => $item->product_reference_image,
                'mime'       => $result['mime_type'],
                'size'       => $result['size_bytes'],
                'human_size' => $this->humanSize($result['size_bytes']),
            ],
        ], 201);
    }

    public function showItemImage($itemId)
    {
        $item = PreQuoteItem::find($itemId);
        if (!$item) {
            return response()->json(['ok' => false, 'message' => 'Ítem no encontrado.'], 404);
        }

        // 1) Imagen propia del ítem (subida por la ejecutiva), si existe.
        if (!empty($item->product_reference_image)
            && Storage::disk('local')->exists($item->product_reference_image)) {
            $ext = strtolower(pathinfo($item->product_reference_image, PATHINFO_EXTENSION));
            $mime = ($ext === 'png') ? 'image/png' : (($ext === 'webp') ? 'image/webp' : (($ext === 'gif') ? 'image/gif' : 'image/jpeg'));

            return response()->file(Storage::disk('local')->path($item->product_reference_image), [
                'Content-Type'        => $mime,
                'Cache-Control'       => 'private, max-age=300',
                'Content-Disposition' => 'inline',
            ]);
        }

        // 2) Fallback (punto C): si el ítem matcheó con el catálogo y no tiene
        //    imagen propia, servir la imagen del catálogo (items.image). Así los
        //    productos matcheados traen su foto automáticamente, sin duplicar
        //    archivos. El centinela 'imagen-no-disponible.jpg' = sin imagen.
        if ($item->item_id) {
            try {
                $catItem = \App\Models\Tenant\Item::find($item->item_id);
                if ($catItem
                    && !empty($catItem->image)
                    && $catItem->image !== 'imagen-no-disponible.jpg') {
                    $abs = storage_path('app/public/uploads/items/' . $catItem->image);
                    if (is_file($abs)) {
                        $ext = strtolower(pathinfo($catItem->image, PATHINFO_EXTENSION));
                        $mime = ($ext === 'png') ? 'image/png' : (($ext === 'webp') ? 'image/webp' : (($ext === 'gif') ? 'image/gif' : 'image/jpeg'));

                        return response()->file($abs, [
                            'Content-Type'        => $mime,
                            'Cache-Control'       => 'private, max-age=300',
                            'Content-Disposition' => 'inline',
                        ]);
                    }
                }
            } catch (\Exception $e) {
                // sin imagen — cae al 404 de abajo
            }
        }

        return response()->json(['ok' => false, 'message' => 'Sin imagen.'], 404);
    }

    public function deleteItemImage($itemId)
    {
        $item = PreQuoteItem::find($itemId);
        if (!$item) {
            return response()->json(['ok' => false, 'message' => 'Ítem no encontrado.'], 404);
        }
        if (!empty($item->product_reference_image)) {
            $this->safelyDelete($item->product_reference_image);
        }
        $item->update(['product_reference_image' => null]);
        return response()->json(['ok' => true, 'message' => 'Imagen del ítem eliminada.']);
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers privados
    |--------------------------------------------------------------------------
    */

    private function safelyDelete($path)
    {
        try {
            if (Storage::disk('local')->exists($path)) {
                Storage::disk('local')->delete($path);
            }
        } catch (\Exception $e) {
            Log::warning('QuotationImageController: no se pudo borrar archivo', [
                'path'  => $path,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function humanSize($bytes)
    {
        $bytes = (int) $bytes;
        if ($bytes >= 1048576) return number_format($bytes / 1048576, 1) . ' MB';
        if ($bytes >= 1024)    return number_format($bytes / 1024, 0) . ' KB';
        return $bytes . ' B';
    }
}
