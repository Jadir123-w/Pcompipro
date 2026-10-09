<?php

namespace Modules\Report\Http\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;

class ReportSalesByBrandCollection extends ResourceCollection
{
    /**
     * Transforma la colección de recursos en un array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return $this->collection->transform(function($row) {
            return [
                'id' => $row['id'], // id de la marca
                'brand_name' => $row['brand_name'], // nombre de la marca
                'quantity_sold' => (float) $row['quantity_sold'], // cantidad vendida
                'total_sale_value' => (float) $row['total_sale_value'],
                'total_profit' => (float) $row['total_profit'], // utilidad generada
            ];
        });
    }
}