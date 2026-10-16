<?php

namespace Modules\Marketing\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Modules\Marketing\Http\Controllers\Api\Concerns\InteractsWithQuotationImages;
use Modules\Marketing\Http\Controllers\Api\QuotationProposalController;
use Modules\Marketing\Models\PreQuote;
use Modules\Marketing\Models\PreQuoteHistory;
use Modules\Marketing\Models\ProposalKit;
use Modules\Marketing\Models\QuotationProposal;
use Modules\Marketing\Services\QuotationCalculationEngine;

/**
 * ProposalKitController — Kits/paquetes comerciales (mockup db).
 *
 * El kit se arma en pre-cotización (memoria) y se persiste con su propio
 * botón Guardar: en ese momento se asegura la propuesta en borrador 1:1
 * (mismo criterio del Validar del mockup) y el kit queda en BD. Al disolver
 * se borra de BD de inmediato (los productos sueltos quedan intactos).
 */
class ProposalKitController extends Controller
{
    use InteractsWithQuotationImages;

    /** @var QuotationCalculationEngine */
    private $calcEngine;

    public function __construct(QuotationCalculationEngine $calcEngine)
    {
        $this->calcEngine = $calcEngine;
    }

    /* ── helpers ─────────────────────────────────────────────── */

    private function guardEditable(PreQuote $prequote)
    {
        if (!$prequote->isEditable()) {
            return response()->json([
                'ok'      => false,
                'message' => 'La precotización no es editable en estado: ' . $prequote->estado,
            ], 422);
        }
        return null;
    }

    private function guardProposalDraft(QuotationProposal $proposal)
    {
        if ($proposal->estado !== QuotationProposal::ESTADO_BORRADOR) {
            return response()->json([
                'ok'      => false,
                'message' => 'La propuesta ya no está en borrador y los kits son de solo lectura.',
            ], 422);
        }
        return null;
    }

    private function proposalOf(PreQuote $prequote)
    {
        return QuotationProposal::where('prequote_id', $prequote->id)->first();
    }

    private function kitBelongsToPrequote(ProposalKit $kit, PreQuote $prequote)
    {
        return $kit->proposal
            && (int) $kit->proposal->prequote_id === (int) $prequote->id;
    }

    private function serializeKit(ProposalKit $kit)
    {
        $members = DB::connection('tenant')->table('quotation_proposal_kit_items as k')
            ->join('quotation_prequote_items as i', 'i.id', '=', 'k.prequote_item_id')
            ->where('k.kit_id', $kit->id)
            ->orderBy('i.item_order')
            ->orderBy('i.id')
            ->get([
                'i.id', 'i.product_name', 'i.quantity', 'i.costo_base',
                'i.valor_unitario', 'i.total_item', 'i.tipo_linea', 'k.cantidad as kit_cantidad',
            ]);

        $data = $kit->toArray();
        $data['members'] = $members;
        $data['members_count'] = count($members);
        return $data;
    }

    private function serializeKits(QuotationProposal $proposal = null)
    {
        if (!$proposal) {
            return [];
        }
        $out = [];
        foreach (ProposalKit::where('proposal_id', $proposal->id)->orderBy('id')->get() as $kit) {
            $out[] = $this->serializeKit($kit);
        }
        return $out;
    }

    private function kitPriceSnapshot($items)
    {
        $total = 0;
        foreach ($items as $it) {
            $total += ((float) ($it->quantity ?: 0)) * ((float) ($it->valor_unitario ?: 0));
        }
        return round($total, 2);
    }

    private function autoDescription($items)
    {
        $names = [];
        foreach ($items as $it) {
            $n = trim((string) ($it->product_name ?: ''));
            if ($n !== '') {
                $names[] = $n;
            }
        }
        return $names ? ('compuesto por ' . implode(', ', $names)) : null;
    }

    private function isComision($item)
    {
        return strtolower(trim((string) ($item->tipo_linea ?? ''))) === 'comision';
    }

    private function logKit(PreQuote $prequote, $action, array $changes, $userId)
    {
        PreQuoteHistory::log($prequote->id, $prequote->version, $action, $changes, $userId);
    }

    /* ── endpoints ───────────────────────────────────────────── */

    /**
     * GET — Kits de la pre-cotización (vía su propuesta; sin propuesta → []).
     */
    public function index($prequoteId)
    {
        try {
            $prequote = PreQuote::findOrFail($prequoteId);
            return response()->json([
                'ok'   => true,
                'kits' => $this->serializeKits($this->proposalOf($prequote)),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'ok'      => false,
                'message' => 'Error listando kits: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * POST — Guardar kit (su propio botón). Asegura el borrador 1:1 y crea
     * kit + miembros. Valida: ≥2 ítems, misma cantidad, sin comisiones y
     * que no estén ya en otro kit.
     */
    public function store(Request $request, $prequoteId)
    {
        try {
            $prequote = PreQuote::findOrFail($prequoteId);

            if ($err = $this->guardEditable($prequote)) {
                return $err;
            }

            $v = Validator::make($request->all(), [
                'nombre'       => ['required', 'string', 'max:191'],
                'descripcion'  => ['nullable', 'string'],
                'presentacion' => ['nullable', 'string', 'max:191'],
                'item_ids'     => ['required', 'array', 'min:2'],
                'item_ids.*'   => ['integer'],
            ]);
            if ($v->fails()) {
                return response()->json(['ok' => false, 'errors' => $v->errors()], 422);
            }

            $itemIds = array_values(array_unique(array_map('intval', $request->input('item_ids'))));
            $items = $prequote->items()->whereIn('id', $itemIds)->orderBy('item_order')->orderBy('id')->get();
            if ($items->count() !== count($itemIds)) {
                return response()->json([
                    'ok'      => false,
                    'message' => 'Algún producto no pertenece a esta pre-cotización.',
                ], 422);
            }
            foreach ($items as $it) {
                if ($this->isComision($it)) {
                    return response()->json([
                        'ok'      => false,
                        'message' => 'La comisión no entra a kits.',
                    ], 422);
                }
            }

            // Misma cantidad en todos (popup del mockup con el detalle).
            $expected = (float) $items[0]->quantity;
            $mismatches = [];
            foreach ($items as $it) {
                if ((float) $it->quantity !== $expected) {
                    $mismatches[] = ['id' => $it->id, 'product_name' => $it->product_name, 'quantity' => $it->quantity];
                }
            }
            if ($mismatches) {
                return response()->json([
                    'ok'                => false,
                    'message'           => 'No se puede agrupar como kit porque las cantidades no coinciden.',
                    'cantidad_esperada' => $expected,
                    'mismatches'        => $mismatches,
                ], 422);
            }

            // Solo ahora se asegura el borrador (nada que guardar → nada que crear).
            $proposal = $this->proposalOf($prequote);
            if ($proposal && ($err = $this->guardProposalDraft($proposal))) {
                return $err;
            }

            $kit = null;
            DB::connection('tenant')->transaction(function () use (
                &$kit, $prequote, $request, $items, $expected, $proposal
            ) {
                if (!$proposal) {
                    $proposal = app(QuotationProposalController::class)->ensureDraftProposal($prequote);
                }

                // Ningún miembro en otro kit de esta propuesta.
                $busy = DB::connection('tenant')->table('quotation_proposal_kit_items as k')
                    ->join('quotation_proposal_kits as p', 'p.id', '=', 'k.kit_id')
                    ->where('p.proposal_id', $proposal->id)
                    ->whereIn('k.prequote_item_id', $items->pluck('id')->all())
                    ->count();
                if ($busy > 0) {
                    throw new \Exception('Algún producto ya está en otro kit.');
                }

                $nombre = trim((string) $request->input('nombre'));
                $kit = ProposalKit::create([
                    'proposal_id'  => $proposal->id,
                    'nombre'       => $nombre,
                    'descripcion'  => $request->input('descripcion') ?: $this->autoDescription($items),
                    'presentacion' => $request->input('presentacion'),
                    'cantidad'     => (int) $expected,
                    'precio_kit'   => $this->kitPriceSnapshot($items),
                ]);

                foreach ($items as $it) {
                    DB::connection('tenant')->table('quotation_proposal_kit_items')->insert([
                        'kit_id'           => $kit->id,
                        'prequote_item_id' => $it->id,
                        'cantidad'         => $it->quantity,
                        'created_at'       => now(),
                        'updated_at'       => now(),
                    ]);
                }

                $this->logKit($prequote, 'kit_created', [
                    'kit_id'  => $kit->id,
                    'nombre'  => $nombre,
                    'item_ids' => $items->pluck('id')->all(),
                ], $request->user() ? $request->user()->id : null);
            });

            return response()->json([
                'ok'   => true,
                'kit'  => $this->serializeKit($kit->fresh()),
                'kits' => $this->serializeKits($this->proposalOf($prequote)),
                'message' => 'Kit guardado.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'ok'      => false,
                'message' => 'Error guardando el kit: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * PUT — Editar kit. Si cambia la cantidad, se propaga a todos los
     * miembros (mockup updateKitQty) y se recalcula la precotización.
     */
    public function update(Request $request, $prequoteId, $kitId)
    {
        try {
            $prequote = PreQuote::findOrFail($prequoteId);

            if ($err = $this->guardEditable($prequote)) {
                return $err;
            }

            $kit = ProposalKit::findOrFail($kitId);
            if (!$this->kitBelongsToPrequote($kit, $prequote)) {
                return response()->json(['ok' => false, 'message' => 'El kit no pertenece a esta pre-cotización.'], 422);
            }
            if ($err = $this->guardProposalDraft($kit->proposal)) {
                return $err;
            }

            $v = Validator::make($request->all(), [
                'nombre'       => ['sometimes', 'required', 'string', 'max:191'],
                'descripcion'  => ['nullable', 'string'],
                'presentacion' => ['nullable', 'string', 'max:191'],
                'cantidad'     => ['sometimes', 'required', 'integer', 'min:1', 'max:999999'],
            ]);
            if ($v->fails()) {
                return response()->json(['ok' => false, 'errors' => $v->errors()], 422);
            }

            DB::connection('tenant')->transaction(function () use ($prequote, $kit, $request) {
                foreach (['nombre', 'descripcion', 'presentacion'] as $field) {
                    if ($request->has($field)) {
                        $kit->$field = $request->input($field);
                    }
                }

                if ($request->has('cantidad') && (int) $request->input('cantidad') !== (int) $kit->cantidad) {
                    $qty = (int) $request->input('cantidad');
                    $kit->cantidad = $qty;
                    $memberIds = DB::connection('tenant')->table('quotation_proposal_kit_items')
                        ->where('kit_id', $kit->id)
                        ->pluck('prequote_item_id')->all();
                    foreach ($memberIds as $itemId) {
                        $item = $prequote->items()->where('id', $itemId)->first();
                        if ($item) {
                            $item->quantity = $qty;
                            $item->save();
                        }
                        DB::connection('tenant')->table('quotation_proposal_kit_items')
                            ->where('kit_id', $kit->id)
                            ->where('prequote_item_id', $itemId)
                            ->update(['cantidad' => $qty, 'updated_at' => now()]);
                    }
                    $kit->precio_kit = $this->kitPriceSnapshot(
                        $prequote->items()->whereIn('id', $memberIds)->get()
                    );
                    $this->calcEngine->recalculate($prequote);
                }

                $kit->save();

                $this->logKit($prequote, 'kit_updated', ['kit_id' => $kit->id], $request->user() ? $request->user()->id : null);
            });

            return response()->json([
                'ok'   => true,
                'kit'  => $this->serializeKit($kit->fresh()),
                'kits' => $this->serializeKits($this->proposalOf($prequote)),
                'message' => 'Kit actualizado.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'ok'      => false,
                'message' => 'Error actualizando el kit: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * POST — Agregar sueltos a un kit existente. Valida cantidad igual a la
     * del kit (popup del mockup) y que no sean comisión ni de otro kit.
     */
    public function addMembers(Request $request, $prequoteId, $kitId)
    {
        try {
            $prequote = PreQuote::findOrFail($prequoteId);

            if ($err = $this->guardEditable($prequote)) {
                return $err;
            }

            $kit = ProposalKit::findOrFail($kitId);
            if (!$this->kitBelongsToPrequote($kit, $prequote)) {
                return response()->json(['ok' => false, 'message' => 'El kit no pertenece a esta pre-cotización.'], 422);
            }
            if ($err = $this->guardProposalDraft($kit->proposal)) {
                return $err;
            }

            $v = Validator::make($request->all(), [
                'item_ids'   => ['required', 'array', 'min:1'],
                'item_ids.*' => ['integer'],
            ]);
            if ($v->fails()) {
                return response()->json(['ok' => false, 'errors' => $v->errors()], 422);
            }

            $itemIds = array_values(array_unique(array_map('intval', $request->input('item_ids'))));
            $items = $prequote->items()->whereIn('id', $itemIds)->get();
            if ($items->count() !== count($itemIds)) {
                return response()->json(['ok' => false, 'message' => 'Algún producto no pertenece a esta pre-cotización.'], 422);
            }
            foreach ($items as $it) {
                if ($this->isComision($it)) {
                    return response()->json(['ok' => false, 'message' => 'La comisión no entra a kits.'], 422);
                }
                if ((float) $it->quantity !== (float) $kit->cantidad) {
                    return response()->json([
                        'ok'                => false,
                        'message'           => 'No se puede agregar "' . $it->product_name . '" porque tiene cantidad ' . $it->quantity . '. Cantidad esperada del kit: ' . $kit->cantidad . '.',
                        'cantidad_esperada' => (int) $kit->cantidad,
                        'mismatches'        => [['id' => $it->id, 'product_name' => $it->product_name, 'quantity' => $it->quantity]],
                    ], 422);
                }
            }

            DB::connection('tenant')->transaction(function () use ($kit, $items, $prequote, $request) {
                $busy = DB::connection('tenant')->table('quotation_proposal_kit_items as k')
                    ->join('quotation_proposal_kits as p', 'p.id', '=', 'k.kit_id')
                    ->where('p.proposal_id', $kit->proposal_id)
                    ->where('k.kit_id', '!=', $kit->id)
                    ->whereIn('k.prequote_item_id', $items->pluck('id')->all())
                    ->count();
                if ($busy > 0) {
                    throw new \Exception('Algún producto ya está en otro kit.');
                }

                foreach ($items as $it) {
                    $exists = DB::connection('tenant')->table('quotation_proposal_kit_items')
                        ->where('kit_id', $kit->id)
                        ->where('prequote_item_id', $it->id)
                        ->exists();
                    if (!$exists) {
                        DB::connection('tenant')->table('quotation_proposal_kit_items')->insert([
                            'kit_id'           => $kit->id,
                            'prequote_item_id' => $it->id,
                            'cantidad'         => $it->quantity,
                            'created_at'       => now(),
                            'updated_at'       => now(),
                        ]);
                    }
                }

                $kit->descripcion = $this->autoDescription(
                    $this->memberItems($kit)
                );
                $kit->save();

                $this->logKit($prequote, 'kit_updated', [
                    'kit_id' => $kit->id,
                    'action' => 'members_added',
                    'item_ids' => $items->pluck('id')->all(),
                ], $request->user() ? $request->user()->id : null);
            });

            return response()->json([
                'ok'   => true,
                'kit'  => $this->serializeKit($kit->fresh()),
                'kits' => $this->serializeKits($this->proposalOf($prequote)),
                'message' => 'Producto(s) agregado(s) al kit.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'ok'      => false,
                'message' => 'Error agregando al kit: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * DELETE — Quitar un miembro (queda como producto suelto). Si el kit
     * quedaría con menos de 2, se exige disolver (must_dissolve).
     */
    public function removeMember(Request $request, $prequoteId, $kitId, $itemId)
    {
        try {
            $prequote = PreQuote::findOrFail($prequoteId);

            if ($err = $this->guardEditable($prequote)) {
                return $err;
            }

            $kit = ProposalKit::findOrFail($kitId);
            if (!$this->kitBelongsToPrequote($kit, $prequote)) {
                return response()->json(['ok' => false, 'message' => 'El kit no pertenece a esta pre-cotización.'], 422);
            }
            if ($err = $this->guardProposalDraft($kit->proposal)) {
                return $err;
            }

            $remaining = DB::connection('tenant')->table('quotation_proposal_kit_items')
                ->where('kit_id', $kit->id)
                ->where('prequote_item_id', '!=', (int) $itemId)
                ->count();
            if ($remaining < 2) {
                return response()->json([
                    'ok'            => false,
                    'must_dissolve' => true,
                    'message'       => 'El kit quedaría con menos de 2 productos: debes disolverlo.',
                ], 422);
            }

            DB::connection('tenant')->transaction(function () use ($kit, $itemId, $prequote, $request) {
                DB::connection('tenant')->table('quotation_proposal_kit_items')
                    ->where('kit_id', $kit->id)
                    ->where('prequote_item_id', (int) $itemId)
                    ->delete();

                $kit->descripcion = $this->autoDescription($this->memberItems($kit));
                $kit->save();

                $this->logKit($prequote, 'kit_updated', [
                    'kit_id' => $kit->id,
                    'action' => 'member_removed',
                    'item_id' => (int) $itemId,
                ], $request->user() ? $request->user()->id : null);
            });

            return response()->json([
                'ok'   => true,
                'kit'  => $this->serializeKit($kit->fresh()),
                'kits' => $this->serializeKits($this->proposalOf($prequote)),
                'message' => 'Producto suelto.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'ok'      => false,
                'message' => 'Error quitando del kit: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * DELETE — Disolver el kit: borra kit + enlaces de BD de inmediato (los
     * productos sueltos quedan intactos) + borra la foto.
     */
    public function destroy(Request $request, $prequoteId, $kitId)
    {
        try {
            $prequote = PreQuote::findOrFail($prequoteId);

            if ($err = $this->guardEditable($prequote)) {
                return $err;
            }

            $kit = ProposalKit::findOrFail($kitId);
            if (!$this->kitBelongsToPrequote($kit, $prequote)) {
                return response()->json(['ok' => false, 'message' => 'El kit no pertenece a esta pre-cotización.'], 422);
            }
            if ($err = $this->guardProposalDraft($kit->proposal)) {
                return $err;
            }

            $nombre = $kit->nombre;

            DB::connection('tenant')->transaction(function () use ($kit, $prequote, $request, $nombre) {
                $this->deleteKitPhotoFile($kit->imagen);

                DB::connection('tenant')->table('quotation_proposal_kit_items')
                    ->where('kit_id', $kit->id)->delete();
                $kit->delete();

                $this->logKit($prequote, 'kit_removed', ['kit_id' => (int) $kit->id, 'nombre' => $nombre],
                    $request->user() ? $request->user()->id : null);
            });

            return response()->json([
                'ok'   => true,
                'kits' => $this->serializeKits($this->proposalOf($prequote)),
                'message' => 'Kit disuelto. Sus componentes vuelven a productos sueltos.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'ok'      => false,
                'message' => 'Error disolviendo el kit: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * POST — Foto propia del kit (multipart image). Reemplaza y borra la
     * anterior, como guardar_kit_foto.php del mockup.
     */
    public function photo(Request $request, $prequoteId, $kitId)
    {
        try {
            $prequote = PreQuote::findOrFail($prequoteId);

            if ($err = $this->guardEditable($prequote)) {
                return $err;
            }

            $kit = ProposalKit::findOrFail($kitId);
            if (!$this->kitBelongsToPrequote($kit, $prequote)) {
                return response()->json(['ok' => false, 'message' => 'El kit no pertenece a esta pre-cotización.'], 422);
            }
            if ($err = $this->guardProposalDraft($kit->proposal)) {
                return $err;
            }

            $stored = $this->validateAndStoreImage($request, 'kit-photo', $kit->id);
            if (empty($stored['ok'])) {
                return response()->json([
                    'ok'      => false,
                    'message' => isset($stored['message']) ? $stored['message'] : 'Imagen inválida.',
                    'errors'  => isset($stored['errors']) ? $stored['errors'] : null,
                ], isset($stored['_status']) ? $stored['_status'] : 422);
            }

            $this->deleteKitPhotoFile($kit->imagen);
            $kit->imagen = $stored['stored_path'];
            $kit->save();

            return response()->json([
                'ok'     => true,
                'kit'    => $this->serializeKit($kit->fresh()),
                'kits'   => $this->serializeKits($this->proposalOf($prequote)),
                'imagen' => $kit->imagen,
            ], 201);
        } catch (\Exception $e) {
            Log::error('ProposalKitController::photo — error', ['kit_id' => $kitId, 'error' => $e->getMessage()]);
            return response()->json([
                'ok'      => false,
                'message' => 'Error subiendo la foto: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET — Sirve la foto del kit (sesión web, como las de ítems).
     */
    public function showPhoto($prequoteId, $kitId)
    {
        try {
            $prequote = PreQuote::findOrFail($prequoteId);
            $kit = ProposalKit::findOrFail($kitId);
            if (!$this->kitBelongsToPrequote($kit, $prequote) || empty($kit->imagen)) {
                abort(404);
            }
            if (!Storage::disk('local')->exists($kit->imagen)) {
                abort(404);
            }
            $content = Storage::disk('local')->get($kit->imagen);
            $mime = 'image/jpeg';
            try {
                $finfo = new \finfo(FILEINFO_MIME_TYPE);
                $detected = $finfo->buffer($content);
                if ($detected) {
                    $mime = $detected;
                }
            } catch (\Exception $e) {
                // mimetype por defecto
            }
            return response($content, 200, ['Content-Type' => $mime]);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            throw $e;
        } catch (\Exception $e) {
            return response()->json(['ok' => false, 'message' => 'Error sirviendo la foto.'], 500);
        }
    }

    /* ── internos ────────────────────────────────────────────── */

    private function memberItems(ProposalKit $kit)
    {
        $ids = DB::connection('tenant')->table('quotation_proposal_kit_items')
            ->where('kit_id', $kit->id)
            ->orderBy('prequote_item_id')
            ->pluck('prequote_item_id')->all();
        if (!$ids) {
            return collect();
        }
        return \Modules\Marketing\Models\PreQuoteItem::whereIn('id', $ids)->orderBy('item_order')->orderBy('id')->get();
    }

    private function deleteKitPhotoFile($path)
    {
        if (empty($path)) {
            return;
        }
        try {
            if (strpos($path, 'quotation-images/') === 0 && Storage::disk('local')->exists($path)) {
                Storage::disk('local')->delete($path);
            }
        } catch (\Exception $e) {
            Log::warning('ProposalKitController: no se pudo borrar foto anterior', ['path' => $path]);
        }
    }
}
