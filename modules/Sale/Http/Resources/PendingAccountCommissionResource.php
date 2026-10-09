<?php

namespace Modules\Sale\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PendingAccountCommissionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'seller_id' => $this->seller_id,
            'amount' => $this->amount,
            'commission_type' => $this->commission_type,
        ];
    }
}