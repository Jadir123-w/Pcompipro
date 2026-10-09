<?php

namespace Modules\Marketing\Models;

use App\Models\Tenant\ModelTenant;

/**
 * MarginRule — Regla de margen configurable (COM-02)
 *
 * Define márgenes por categoría, producto y tramo de cantidad.
 * Prioridad: regla específica (2) > categoría (1) > default (0)
 */
class MarginRule extends ModelTenant
{
    protected $table = 'quotation_margin_rules';

    protected $fillable = [
        'category',
        'product_name',
        'quantity_from',
        'quantity_to',
        'margin_percentage',
        'min_margin_percentage',
        'priority',
        'active',
    ];

    protected $casts = [
        'quantity_from'         => 'integer',
        'quantity_to'           => 'integer',
        'margin_percentage'     => 'decimal:2',
        'min_margin_percentage' => 'decimal:2',
        'priority'              => 'integer',
        'active'                => 'boolean',
    ];

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    public function scopeByCategory($query, $category)
    {
        return $query->where('category', $category);
    }

    public function scopeForQuantity($query, $quantity)
    {
        return $query->where('quantity_from', '<=', $quantity)
            ->where(function ($q) use ($quantity) {
                $q->whereNull('quantity_to')
                  ->orWhere('quantity_to', '>=', $quantity);
            });
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    /**
     * Buscar la mejor regla de margen para un producto/categoría/cantidad.
     *
     * Prioridad: producto específico > categoría > default general
     *
     * @param  string      $category
     * @param  string|null $productName
     * @param  int         $quantity
     * @return MarginRule|null
     */
    public static function findBestMatch($category, $productName, $quantity)
    {
        $query = static::active()
            ->forQuantity($quantity)
            ->orderByDesc('priority')
            ->orderByDesc('quantity_from');

        // Intentar match por producto específico
        if ($productName) {
            $specific = (clone $query)
                ->where('product_name', 'LIKE', '%' . $productName . '%')
                ->first();

            if ($specific) {
                return $specific;
            }
        }

        // Intentar match por categoría
        $byCategory = (clone $query)
            ->where('category', $category)
            ->whereNull('product_name')
            ->first();

        if ($byCategory) {
            return $byCategory;
        }

        // Fallback: regla general
        return (clone $query)
            ->where('category', 'General')
            ->first();
    }
}
