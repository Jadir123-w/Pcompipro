<?php

namespace Modules\Sale\Http\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;

class PendingAccountCommissionCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return $this->collection->transform(function($row, $key) {
            return [
                'id' => $row->id,
                'seller_name' => $row->seller->name,
                'commission_type' => ($row->commission_type == 'monto') ? 'Monto' : 'Porcentaje',
                'amount' => $row->amount,
            ];
        });
    }
}