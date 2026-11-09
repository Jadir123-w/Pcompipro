<?php

namespace Modules\Marketing\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
use Modules\Marketing\Models\GridItem;
use Modules\Marketing\Models\Piece;
use Modules\Marketing\Services\ImageGenerationService;
use Modules\Marketing\Services\MKT03Orchestrator;
use Modules\Marketing\Support\PublicUrl;

/**
 * MKT03PieceController — API de piezas finales (MKT-03).
 *
 * Cubre el Modo D: producir pieza final desde ítem de grilla.
 * También permite editar, re-generar imagen y cambiar estado de piezas.
 */
class MKT03PieceController extends Controller
{
    /**
     * POST /marketing/mkt03/items/{itemId}/produce
     *
     * Modo D: Produce una pieza final a partir de un ítem de grilla.
     */
    public function produce(Request $request, $itemId)
    {
        $gridItem = GridItem::findOrFail($itemId);

        if ($gridItem->pieces()->exists()) {
            return response()->json([
                'ok'      => false,
                'message' => 'Este ítem ya tiene una pieza producida.',
            ], 409);
        }

        $generateImage = $request->input(
            'generate_image',
            (bool) config('marketing.chatbot.mkt03.auto_generate_image', false)
        );

        try {
            $piece = app(MKT03Orchestrator::class)->producePiece($gridItem, (bool) $generateImage);

            if (!$piece) {
                return response()->json([
                    'ok'      => false,
                    'message' => 'No se pudo producir la pieza. Revisa los logs.',
                ], 500);
            }

            return response()->json(['ok' => true, 'piece' => $piece], 201);

        } catch (\Exception $e) {
            return response()->json([
                'ok'      => false,
                'message' => 'Error produciendo pieza: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /marketing/mkt03/pieces
     */
    public function index(Request $request)
    {
        $query = Piece::query()
            ->with(['gridItem:id,fecha_publicacion,red_social,tematica,grid_id'])
            ->orderByDesc('created_at');

        if ($request->filled('red_social')) {
            $query->where('red_social', $request->input('red_social'));
        }
        if ($request->filled('estado_pieza')) {
            $query->where('estado_pieza', $request->input('estado_pieza'));
        }
        if ($request->filled('grid_item_id')) {
            $query->where('grid_item_id', $request->input('grid_item_id'));
        }
        if ($request->filled('is_current')) {
            $query->where('is_current', filter_var($request->input('is_current'), FILTER_VALIDATE_BOOLEAN));
        }

        $pieces = $query->paginate($request->input('per_page', 20));

        return response()->json(['ok' => true, 'pieces' => $pieces]);
    }

    /**
     * GET /marketing/mkt03/pieces/{id}
     */
    public function show($id)
    {
        $piece = Piece::with(['gridItem', 'extraordinaryContent'])->findOrFail($id);
        return response()->json(['ok' => true, 'piece' => $piece]);
    }

    /**
     * PUT /marketing/mkt03/pieces/{id}
     *
     * Editar una pieza (copy, CTA, hashtags, enfoque visual, etc.).
     */
    public function update(Request $request, $id)
    {
        $piece = Piece::findOrFail($id);

        $v = Validator::make($request->all(), [
            'estado_pieza' => ['sometimes', 'required', 'string', 'in:' . implode(',', Piece::ESTADOS)],
        ]);

        if ($v->fails()) {
            return response()->json(['ok' => false, 'errors' => $v->errors()], 422);
        }

        $editable = [
            'copy', 'cta', 'hashtags', 'formato', 'enfoque_visual',
            'referencia_visual', 'texto_en_imagen_video', 'imagen_prompt',
            'observaciones',
        ];

        $updates = $request->only($editable);
        if ($request->has('estado_pieza')) {
            $newStatus = $request->input('estado_pieza');
            $updates['estado_pieza'] = $newStatus;

            if ($newStatus === Piece::ESTADO_PUBLICADA
                && $piece->estado_pieza !== Piece::ESTADO_PUBLICADA) {
                $updates['published_at'] = now();
            }
            if ($newStatus === Piece::ESTADO_LISTA_PUBLICAR
                && $piece->estado_pieza !== Piece::ESTADO_LISTA_PUBLICAR) {
                $updates['approver_id'] = $request->user() ? $request->user()->id : null;
            }
        }

        $piece->update($updates);

        return response()->json(['ok' => true, 'piece' => $piece->fresh()]);
    }

    /**
     * POST /marketing/mkt03/pieces/{id}/regenerate-image
     *
     * Re-generar imagen usando el prompt actual (o uno nuevo pasado por body).
     */
    public function regenerateImage(Request $request, $id)
    {
        $piece = Piece::findOrFail($id);

        $prompt      = $request->input('imagen_prompt', $piece->imagen_prompt);
        $aspectRatio = $request->input('aspect_ratio', '1:1');

        if (empty($prompt)) {
            return response()->json([
                'ok'      => false,
                'message' => 'La pieza no tiene imagen_prompt y no se pasó uno nuevo.',
            ], 422);
        }

        $result = app(ImageGenerationService::class)->generate($prompt, [
            'aspect_ratio' => $aspectRatio,
            'quality'      => $request->input('quality', 'standard'),
            'style'        => $request->input('style', 'vivid'),
        ]);

        if (!$result['success']) {
            return response()->json([
                'ok'      => false,
                'message' => 'Error generando imagen: ' . ($result['error'] ?? 'desconocido'),
            ], 500);
        }

        $piece->update([
            'imagen_prompt'      => $prompt,
            'imagen_final_url'   => $result['url'] ?? null,
            'ai_image_provider'  => $result['provider'] ?? null,
            'ai_image_model'     => $result['model'] ?? null,
            'imagen_source'      => Piece::IMAGEN_AI_GENERATED,
            'version'            => $piece->version + 1,
            'estado_pieza'       => Piece::ESTADO_LISTA_PUBLICAR,
        ]);

        return response()->json(['ok' => true, 'piece' => $piece->fresh()]);
    }

    /**
     * POST /marketing/mkt03/pieces/{id}/upload-image
     *
     * Subir una imagen manual que reemplaza la generada por IA.
     */
    public function uploadImage(Request $request, $id)
    {
        $piece = Piece::findOrFail($id);

        $v = Validator::make($request->all(), [
            'image' => ['required', 'file', 'image', 'max:10240'], // max 10MB
        ]);

        if ($v->fails()) {
            return response()->json(['ok' => false, 'errors' => $v->errors()], 422);
        }

        $disk = config('marketing.chatbot.mkt03.image_storage_disk', 'public');
        $path = config('marketing.chatbot.mkt03.image_storage_path', 'mkt03/pieces');

        $filename = 'piece_' . $piece->id . '_manual_' . date('YmdHis') . '.' . $request->file('image')->getClientOriginalExtension();
        $storagePath = $request->file('image')->storeAs($path, $filename, $disk);

        $url = PublicUrl::forDisk($disk, $storagePath);

        $piece->update([
            'imagen_final_url' => $url,
            'imagen_source'    => Piece::IMAGEN_UPLOADED,
            'estado_pieza'     => Piece::ESTADO_LISTA_PUBLICAR,
        ]);

        return response()->json(['ok' => true, 'piece' => $piece->fresh()]);
    }

    /**
     * PUT /marketing/mkt03/pieces/{id}/status
     */
    public function updateStatus(Request $request, $id)
    {
        $piece = Piece::findOrFail($id);

        $v = Validator::make($request->all(), [
            'estado_pieza' => ['required', 'string', 'in:' . implode(',', Piece::ESTADOS)],
        ]);

        if ($v->fails()) {
            return response()->json(['ok' => false, 'errors' => $v->errors()], 422);
        }

        $updates = ['estado_pieza' => $request->input('estado_pieza')];

        if ($request->input('estado_pieza') === Piece::ESTADO_PUBLICADA) {
            $updates['published_at'] = now();
        }
        if ($request->input('estado_pieza') === Piece::ESTADO_LISTA_PUBLICAR) {
            $updates['approver_id'] = $request->user() ? $request->user()->id : null;
        }

        $piece->update($updates);

        return response()->json(['ok' => true, 'piece' => $piece->fresh()]);
    }

    /**
     * DELETE /marketing/mkt03/pieces/{id}
     */
    public function destroy($id)
    {
        $piece = Piece::findOrFail($id);
        $piece->delete();

        return response()->json(['ok' => true, 'message' => 'Pieza eliminada']);
    }
}
