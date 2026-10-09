<?php

namespace App\Models\Tenant;

use App\Models\Tenant\Catalogs\AffectationIgvType;
use App\Models\Tenant\Catalogs\CurrencyType;
use App\Models\Tenant\Catalogs\SystemIscType;
use App\Models\Tenant\Catalogs\UnitType;

/**
 * App\Models\Tenant\ItemUnitType
 *
 * @property-read \App\Models\Tenant\Item $item
 * @property-read UnitType $unit_type
 * @method static \Illuminate\Database\Eloquent\Builder|ItemUnitType newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|ItemUnitType newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|ItemUnitType query()
 * @mixin \Eloquent
 */
class ItemUnitType extends ModelTenant
{
     protected $with = ['unit_type'];
    public $timestamps = false;

    protected $fillable = [
        'description',
        'item_id',
        'unit_type_id',
        'quantity_unit',
        'price1',
        'price2',
        'price3',
        'price_default',
        'barcode'
    ];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function unit_type() {
        return $this->belongsTo(UnitType::class, 'unit_type_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function item() {
        return $this->belongsTo(Item::class);
    }

    /**
     * Generar una descripción legible para una presentación cuando el usuario
     * no la escribió. La columna `description` es NOT NULL, así que sin esto
     * un registro sin descripción provoca un error 500 (SQLSTATE 23000).
     *
     * Ej: quantity_unit=100 + unit_type_id='NIU' ("Unidades") => "100 Unidades".
     *
     * @param  mixed   $quantityUnit  Factor / cantidad de unidades
     * @param  string  $unitTypeId    Código de unidad (ej 'NIU', 'BX')
     * @return string
     */
    public static function generateDescription($quantityUnit, $unitTypeId)
    {
        // Formatear el factor sin decimales innecesarios:
        //   100.0000 -> "100"    1.5000 -> "1.5"
        $qty = $quantityUnit !== null && $quantityUnit !== '' ? (float) $quantityUnit : 0;
        $qtyStr = rtrim(rtrim(number_format($qty, 4, '.', ''), '0'), '.');
        if ($qtyStr === '' || $qtyStr === '0') {
            $qtyStr = '1';
        }

        // Nombre legible de la unidad: 'NIU' -> "Unidades". Fallback al código.
        $unitName = (string) $unitTypeId;
        if (!empty($unitTypeId)) {
            try {
                $unit = UnitType::find($unitTypeId);
                if ($unit && !empty($unit->description)) {
                    $unitName = $unit->description;
                }
            } catch (\Exception $e) {
                // Catálogo no disponible — usamos el código tal cual
            }
        }

        $desc = trim($qtyStr . ' ' . $unitName);
        return $desc !== '' ? $desc : 'Presentación';
    }


    /**
     * Retorna un standar de nomenclatura para el modelo
     *
     * @param int $decimal_units
     *
     * @return array
     */
    public function getCollectionData($decimal_units = 2){

        return [
            'id'            => $this->id,
            'description'   => "{$this->description}",
            'item_id'       => $this->item_id,
            'unit_type_id'  => $this->unit_type_id,
            'quantity_unit' => number_format($this->quantity_unit, $decimal_units, '.', ''),
            'price1'        => number_format($this->price1, $decimal_units, '.', ''),
            'price2'        => number_format($this->price2, $decimal_units, '.', ''),
            'price3'        => number_format($this->price3, $decimal_units, '.', ''),
            'price_default' => $this->price_default,
            'barcode'       => $this->barcode,
            /*
            'price1' => $row->price1,
            'price2' => $row->price2,
            'price3' => $row->price3,
            */


        ];
    }

}
