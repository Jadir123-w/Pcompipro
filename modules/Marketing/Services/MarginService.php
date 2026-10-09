<?php

namespace Modules\Marketing\Services;

use Illuminate\Support\Facades\Log;
use Modules\Marketing\Models\MarginRule;
use Modules\Marketing\Models\QuotationConfig;

/**
 * MarginService — Servicio de márgenes del cotizador COM-02
 *
 * Busca el margen correcto por prioridad:
 * 1. Regla específica por producto
 * 2. Regla por categoría + tramo de cantidad
 * 3. Margen general por defecto
 */
class MarginService
{
    /**
     * Obtener el porcentaje de margen para un ítem.
     *
     * @param  string      $category     Categoría del producto
     * @param  string|null $productName  Nombre del producto
     * @param  int         $quantity     Cantidad solicitada
     * @return float                     Porcentaje de margen
     */
    public function getMarginForItem($category, $productName, $quantity)
    {
        // Buscar regla de margen por prioridad
        $rule = MarginRule::findBestMatch($category, $productName, $quantity);

        if ($rule) {
            Log::info('MarginService: regla encontrada', [
                'rule_id'    => $rule->id,
                'category'   => $rule->category,
                'product'    => $rule->product_name,
                'margin'     => $rule->margin_percentage,
                'priority'   => $rule->priority,
            ]);

            return (float) $rule->margin_percentage;
        }

        // Fallback: margen por defecto desde configuración
        $default = QuotationConfig::getValue('default_margin_percentage', 30);

        Log::info('MarginService: usando margen default', [
            'margin' => $default,
        ]);

        return (float) $default;
    }

    /**
     * Obtener el margen MÍNIMO aceptable para un ítem (Fase B — alerta visual).
     *
     * Usa la MISMA lógica de prioridad que getMarginForItem (producto > categoría
     * > General), pero retorna `min_margin_percentage` en vez de `margin_percentage`.
     *
     * Retorna `null` cuando:
     *  - No hay regla que matchee.
     *  - La regla matcheada no tiene min_margin_percentage definido.
     *
     * El frontend usa este valor para mostrar una alerta visual si la ejecutiva
     * baja el margen por debajo del mínimo. No bloquea el guardado.
     *
     * @param  string      $category
     * @param  string|null $productName
     * @param  int         $quantity
     * @return float|null
     */
    public function getMinMarginForItem($category, $productName, $quantity)
    {
        $rule = MarginRule::findBestMatch($category, $productName, $quantity);

        if (!$rule || $rule->min_margin_percentage === null) {
            return null;
        }

        return (float) $rule->min_margin_percentage;
    }

    /**
     * Importar reglas de margen desde un array (CSV/Excel parseado).
     *
     * Formato esperado por fila:
     * ['category' => 'Textil', 'product_name' => null, 'quantity_from' => 1, 'quantity_to' => 100, 'margin_percentage' => 35]
     *
     * @param  array  $rows
     * @return array  ['imported' => int, 'errors' => array]
     */
    public function importFromArray(array $rows)
    {
        $imported = 0;
        $errors = [];

        foreach ($rows as $i => $row) {
            try {
                $category = isset($row['category']) ? trim($row['category']) : null;
                $margin = isset($row['margin_percentage']) ? (float) $row['margin_percentage'] : null;

                if (empty($category) || $margin === null) {
                    $errors[] = "Fila " . ($i + 1) . ": categoría o margen vacío";
                    continue;
                }

                $productName = isset($row['product_name']) ? trim($row['product_name']) : null;
                $priority = !empty($productName) ? 2 : 1;

                MarginRule::create([
                    'category'          => $category,
                    'product_name'      => $productName ?: null,
                    'quantity_from'     => isset($row['quantity_from']) ? (int) $row['quantity_from'] : 1,
                    'quantity_to'       => isset($row['quantity_to']) ? (int) $row['quantity_to'] : null,
                    'margin_percentage' => $margin,
                    'priority'          => $priority,
                    'active'            => true,
                ]);

                $imported++;

            } catch (\Exception $e) {
                $errors[] = "Fila " . ($i + 1) . ": " . $e->getMessage();
            }
        }

        Log::info('MarginService: importación completada', [
            'imported' => $imported,
            'errors'   => count($errors),
        ]);

        return [
            'imported' => $imported,
            'errors'   => $errors,
        ];
    }
}
