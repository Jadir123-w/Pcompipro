<?php

namespace Modules\Marketing\Models;

use App\Models\Tenant\ModelTenant;

/**
 * ChatbotFaq — Preguntas frecuentes del agente MKT-01.
 *
 * Tabla: marketing_chatbot_faqs
 *
 * El método estático getForPrompt() devuelve las FAQs activas formateadas
 * como string Markdown listo para inyectar en el system prompt vía
 * placeholder {{FAQS}}.
 */
class ChatbotFaq extends ModelTenant
{
    protected $table = 'marketing_chatbot_faqs';

    protected $fillable = [
        'question',
        'answer',
        'keywords',
        'category',
        'active',
        'sort_order',
    ];

    protected $casts = [
        'active'     => 'boolean',
        'sort_order' => 'integer',
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

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    public function scopeOfCategory($query, $category)
    {
        return $query->where('category', $category);
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    /**
     * Devuelve la lista de FAQs activas formateadas para inyectar en el
     * system prompt. Formato Markdown sencillo:
     *
     *   ### [Categoría]
     *   - **P:** ¿Pregunta?
     *     **R:** Respuesta.
     *     _kw: keyword1, keyword2_
     *
     * Si no hay FAQs activas devuelve un string vacío (el caller decide qué
     * hacer con el placeholder).
     *
     * @return string
     */
    public static function getForPrompt()
    {
        $faqs = static::active()->ordered()->get();
        if ($faqs->isEmpty()) {
            return '';
        }

        // Agrupar por categoría manteniendo orden
        $grouped = [];
        $sinCat  = [];
        foreach ($faqs as $f) {
            $cat = trim((string) $f->category);
            if ($cat === '') {
                $sinCat[] = $f;
            } else {
                if (!isset($grouped[$cat])) {
                    $grouped[$cat] = [];
                }
                $grouped[$cat][] = $f;
            }
        }
        if (!empty($sinCat)) {
            $grouped['Otras'] = $sinCat;
        }

        $lines = [];
        foreach ($grouped as $catName => $items) {
            $lines[] = '### ' . $catName;
            foreach ($items as $f) {
                $q = trim((string) $f->question);
                $a = trim((string) $f->answer);
                // Compactar saltos múltiples del answer para que no se rompa el formato
                $aOneLine = preg_replace('/\s+/u', ' ', $a);

                $lines[] = '- **P:** ' . $q;
                $lines[] = '  **R:** ' . $aOneLine;

                $kw = trim((string) $f->keywords);
                if ($kw !== '') {
                    $lines[] = '  _kw: ' . $kw . '_';
                }
            }
            $lines[] = '';
        }

        return rtrim(implode("\n", $lines));
    }

    /**
     * Lista de categorías distintas usadas hoy en la tabla. Útil para que la
     * UI sugiera autocompletado al usuario.
     *
     * @return array
     */
    public static function distinctCategories()
    {
        return static::whereNotNull('category')
            ->where('category', '<>', '')
            ->orderBy('category')
            ->pluck('category')
            ->unique()
            ->values()
            ->all();
    }
}
