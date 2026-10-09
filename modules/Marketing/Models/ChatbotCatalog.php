<?php

namespace Modules\Marketing\Models;

use App\Models\Tenant\ModelTenant;

class ChatbotCatalog extends ModelTenant
{
    protected $table = 'chatbot_catalogs';

    protected $fillable = [
        'line_name',
        'catalog_url',
        'is_general',
        'active',
        'sort_order',
    ];

    protected $casts = [
        'is_general' => 'boolean',
        'active'     => 'boolean',
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

    public function scopeGeneral($query)
    {
        return $query->where('is_general', true);
    }

    public function scopeByLine($query, $lineName)
    {
        return $query->where('line_name', 'LIKE', "%{$lineName}%")
                     ->where('is_general', false);
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    /**
     * Obtener catálogos activos formateados para inyectar en el system prompt.
     *
     * @return array  [['line' => 'Textil', 'url' => '...', 'is_general' => false], ...]
     */
    public static function getForPrompt()
    {
        return static::active()
            ->orderBy('is_general', 'desc')
            ->orderBy('sort_order')
            ->get()
            ->map(function ($c) {
                return [
                    'line'       => $c->line_name,
                    'url'        => $c->catalog_url,
                    'is_general' => $c->is_general,
                ];
            })
            ->toArray();
    }
}
