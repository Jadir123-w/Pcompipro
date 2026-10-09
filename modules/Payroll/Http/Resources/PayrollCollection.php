<?php

namespace Modules\Payroll\Http\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;
use Modules\Payroll\Models\Payroll;


class PayrollCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Support\Collection
     */
    public function toArray($request)
    {
        return $this->collection->transform(function( Payroll $row) {
            return $row->getCollectionData();
        });
    }

}
