<?php

namespace Modules\Marketing\Services;

use App\Models\Tenant\ItemUnitType;
use Illuminate\Support\Facades\Log;

/**
 * VolumePricingService — Fase C (COM-02): precios por volumen.
 *
 * COMPIPRO usa la tabla `item_unit_types` como ESCALONES DE PRECIO POR VOLUMEN
 * (no como presentaciones, que es el uso histórico de COMPIPRO ERP). Cada fila
 * define un tramo:
 *   - quantity_unit → cantidad mínima del escalón
 *   - price1        → precio unitario en ese escalón
 *   - price2/3/default → no se usan en COMPIPRO
 *
 * Lógica de selección (mayorista clásica): para una cantidad Q cotizada,
 * aplica el escalón cuya `quantity_unit` sea el MÁXIMO valor menor o igual a Q.
 *
 *   Escalones (50, 100, 500):
 *     Q=30   → null         (no llega al menor escalón, cae al fallback)
 *     Q=50   → escalón 50
 *     Q=75   → escalón 50   (el último alcanzado)
 *     Q=100  → escalón 100
 *     Q=499  → escalón 100
 *     Q=500  → escalón 500
 *     Q=1000 → escalón 500
 *
 * El servicio NO decide qué hacer si Q está bajo el menor escalón — retorna null
 * y deja al caller usar su fallback (purchase_unit_price del item, etc.).
 */
class VolumePricingService
{
    /**
     * Obtener el costo unitario para una cantidad dada según los escalones del
     * producto del catálogo.
     *
     * @param  int  $itemId
     * @param  int  $quantity
     * @return array|null  ['cost' => float, 'tier' => ['quantity_unit', 'price1', 'description']]
     *                     null si no hay escalones aplicables.
     */
    public function getCostForVolume($itemId, $quantity)
    {
        if (empty($itemId) || (int) $quantity <= 0) {
            return null;
        }

        try {
            // Traer todos los escalones del ítem ordenados ASC por cantidad.
            $tiers = ItemUnitType::where('item_id', (int) $itemId)
                ->orderBy('quantity_unit', 'ASC')
                ->get(['id', 'item_id', 'unit_type_id', 'quantity_unit', 'price1', 'description']);
        } catch (\Exception $e) {
            Log::warning('VolumePricingService: error leyendo item_unit_types', [
                'item_id' => $itemId,
                'error'   => $e->getMessage(),
            ]);
            return null;
        }

        if ($tiers->isEmpty()) {
            return null;
        }

        // Encontrar el MÁXIMO quantity_unit que sea <= quantity (lógica mayorista).
        $applied = null;
        foreach ($tiers as $tier) {
            if ((float) $tier->quantity_unit <= (float) $quantity) {
                $applied = $tier;  // sigue acumulando hasta el último que cumpla
            } else {
                break;  // como vienen ordenados ASC, en cuanto pasamos podemos cortar
            }
        }

        if (!$applied) {
            // La cantidad solicitada está por debajo del menor escalón disponible.
            // Retornamos null y el caller decide qué fallback usar.
            return null;
        }

        return [
            'cost' => (float) $applied->price1,
            'tier' => [
                'id'            => (int) $applied->id,
                'quantity_unit' => (float) $applied->quantity_unit,
                'price1'        => (float) $applied->price1,
                'description'   => $applied->description,
                'unit_type_id'  => $applied->unit_type_id,
            ],
        ];
    }

    /**
     * Obtener TODOS los escalones disponibles de un ítem (para mostrar en la UI
     * la tabla informativa).
     *
     * @param  int  $itemId
     * @return array  Lista de escalones; cada uno con quantity_unit, price1,
     *                description. Vacío si el ítem no tiene escalones.
     */
    public function getAvailableTiers($itemId)
    {
        if (empty($itemId)) {
            return [];
        }

        try {
            $tiers = ItemUnitType::where('item_id', (int) $itemId)
                ->orderBy('quantity_unit', 'ASC')
                ->get(['id', 'unit_type_id', 'quantity_unit', 'price1', 'description']);
        } catch (\Exception $e) {
            Log::warning('VolumePricingService: error listando escalones', [
                'item_id' => $itemId,
                'error'   => $e->getMessage(),
            ]);
            return [];
        }

        return $tiers->map(function ($t) {
            return [
                'id'            => (int) $t->id,
                'quantity_unit' => (float) $t->quantity_unit,
                'price1'        => (float) $t->price1,
                'description'   => $t->description,
                'unit_type_id'  => $t->unit_type_id,
            ];
        })->toArray();
    }
}
