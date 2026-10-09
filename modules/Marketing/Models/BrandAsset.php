<?php

namespace Modules\Marketing\Models;

use App\Models\Tenant\ModelTenant;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Modules\Marketing\Support\PublicUrl;

/**
 * BrandAsset — Asset del Brand Asset Library (BAL).
 *
 * Tabla: marketing_brand_assets
 * Compartido entre MKT-01, COM-02 y MKT-03.
 *
 * Helpers principales:
 *   - BrandAsset::forAgent('mkt03')                   → assets activos para MKT-03
 *   - BrandAsset::byTags(['hero','primary'])         → filtrado por tags
 *   - BrandAsset::primaryLogo()                       → el logo principal
 *   - BrandAsset::primaryColor()                      → el color principal
 *   - BrandAsset::brandGuidelinesText()               → texto concatenado de guidelines
 *   - $asset->getAbsolutePath()                       → ruta absoluta para gpt-image-2
 *   - $asset->getPublicUrl()                          → URL pública para mostrar en UI
 */
class BrandAsset extends ModelTenant
{
    use SoftDeletes;

    protected $table = 'marketing_brand_assets';

    /*
    |--------------------------------------------------------------------------
    | Constantes de tipos
    |--------------------------------------------------------------------------
    */
    const TYPE_LOGO            = 'logo';
    const TYPE_COLOR           = 'color';
    const TYPE_PRODUCT_PHOTO   = 'product_photo';
    const TYPE_BRAND_GUIDELINE = 'brand_guideline';
    const TYPE_ICON            = 'icon';
    const TYPE_FONT            = 'font';

    public static $TYPES = [
        self::TYPE_LOGO,
        self::TYPE_COLOR,
        self::TYPE_PRODUCT_PHOTO,
        self::TYPE_BRAND_GUIDELINE,
        self::TYPE_ICON,
        self::TYPE_FONT,
    ];

    public static $TYPES_FILE = [
        self::TYPE_LOGO,
        self::TYPE_PRODUCT_PHOTO,
        self::TYPE_ICON,
    ];

    public static $TYPES_TEXT = [
        self::TYPE_BRAND_GUIDELINE,
        self::TYPE_FONT,
    ];

    /*
    |--------------------------------------------------------------------------
    | Mass assignment & casts
    |--------------------------------------------------------------------------
    */
    protected $fillable = [
        'type',
        'name',
        'description',
        'storage_disk',
        'storage_path',
        'mime_type',
        'file_size',
        'width',
        'height',
        'hex_color',
        'text_value',
        'tags',
        'usage_context',
        'use_in_mkt01',
        'use_in_com02',
        'use_in_mkt03',
        'active',
        'sort_order',
        'created_by',
    ];

    protected $casts = [
        'tags'         => 'array',
        'use_in_mkt01' => 'boolean',
        'use_in_com02' => 'boolean',
        'use_in_mkt03' => 'boolean',
        'active'       => 'boolean',
        'file_size'    => 'integer',
        'width'        => 'integer',
        'height'       => 'integer',
        'sort_order'   => 'integer',
    ];

    protected $appends = ['public_url'];

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    public function scopeOfType($query, $type)
    {
        return $query->where('type', $type);
    }

    public function scopeForAgent($query, $agent)
    {
        $col = 'use_in_' . strtolower($agent);
        if (!in_array($col, ['use_in_mkt01', 'use_in_com02', 'use_in_mkt03'])) {
            return $query;
        }
        return $query->where($col, true);
    }

    /**
     * Filtrar por tags (al menos uno debe matchear).
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  array  $tags
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByTags($query, array $tags)
    {
        if (empty($tags)) {
            return $query;
        }
        return $query->where(function ($q) use ($tags) {
            foreach ($tags as $tag) {
                // JSON_CONTAINS funciona en MariaDB 10.2+ y MySQL 5.7+
                $q->orWhereRaw('JSON_CONTAINS(tags, ?)', [json_encode($tag)]);
            }
        });
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    /*
    |--------------------------------------------------------------------------
    | Accessors
    |--------------------------------------------------------------------------
    */

    /**
     * URL pública del asset (solo aplica a tipos con file).
     *
     * @return string|null
     */
    public function getPublicUrlAttribute()
    {
        return $this->getPublicUrl();
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers de instancia
    |--------------------------------------------------------------------------
    */

    /**
     * @return bool
     */
    public function isFileBased()
    {
        return in_array($this->type, self::$TYPES_FILE);
    }

    /**
     * @return bool
     */
    public function isColor()
    {
        return $this->type === self::TYPE_COLOR;
    }

    /**
     * Obtener URL pública para mostrar en UI.
     *
     * @return string|null
     */
    public function getPublicUrl()
    {
        if (!$this->isFileBased() || !$this->storage_path) {
            return null;
        }
        return PublicUrl::forDisk($this->storage_disk, $this->storage_path);
    }

    /**
     * Obtener ruta ABSOLUTA del archivo en filesystem (necesaria para
     * gpt-image-2.editImage() que requiere file paths reales).
     *
     * @return string|null
     */
    public function getAbsolutePath()
    {
        if (!$this->isFileBased() || !$this->storage_path) {
            return null;
        }
        $disk = $this->storage_disk ?: 'public';
        try {
            return Storage::disk($disk)->path($this->storage_path);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * @return bool
     */
    public function hasTag($tag)
    {
        return is_array($this->tags) && in_array($tag, $this->tags);
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers estáticos (atajos para los agentes)
    |--------------------------------------------------------------------------
    */

    /**
     * Logo principal (uno solo) — para widget/PDF.
     *
     * @return BrandAsset|null
     */
    public static function primaryLogo()
    {
        return static::active()
            ->ofType(self::TYPE_LOGO)
            ->byTags(['primary'])
            ->ordered()
            ->first()
        ?: static::active()->ofType(self::TYPE_LOGO)->ordered()->first();
    }

    /**
     * Color primario — primer color marcado como 'primary' o el primero ordenado.
     *
     * @return BrandAsset|null
     */
    public static function primaryColor()
    {
        return static::active()
            ->ofType(self::TYPE_COLOR)
            ->byTags(['primary'])
            ->ordered()
            ->first()
        ?: static::active()->ofType(self::TYPE_COLOR)->ordered()->first();
    }

    /**
     * Texto concatenado de todos los brand_guideline activos.
     * Ideal para inyectar en system prompts.
     *
     * @return string
     */
    public static function brandGuidelinesText()
    {
        $guidelines = static::active()
            ->ofType(self::TYPE_BRAND_GUIDELINE)
            ->ordered()
            ->get();

        if ($guidelines->isEmpty()) {
            return '';
        }

        $lines = [];
        foreach ($guidelines as $g) {
            $lines[] = '— ' . $g->name . ': ' . $g->text_value;
        }
        return implode("\n", $lines);
    }

    /**
     * Rutas absolutas de assets de tipo product_photo activos para MKT-03.
     * Usado por PieceProducerEngine para inyectar referencias visuales
     * automáticamente al gpt-image-2.editImage().
     *
     * @param  array  $tags  Tags adicionales para filtrar (opcional)
     * @return array         Lista de rutas absolutas
     */
    public static function mkt03ReferenceImagePaths(array $tags = [])
    {
        $query = static::active()
            ->ofType(self::TYPE_PRODUCT_PHOTO)
            ->forAgent('mkt03');

        if (!empty($tags)) {
            $query->byTags($tags);
        }

        $assets = $query->ordered()->get();

        $paths = [];
        foreach ($assets as $a) {
            $abs = $a->getAbsolutePath();
            if ($abs && file_exists($abs)) {
                $paths[] = $abs;
            }
        }
        return $paths;
    }
}
