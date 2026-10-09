<?php

namespace Modules\Payroll\Http\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;
use Modules\Payroll\Models\PayrollEmployee;


class PayrollEmployeeCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Support\Collection
     */
    public function toArray($request)
    {
        return $this->collection->transform(function(PayrollEmployee $row) {
            return $row->getCollectionData();
        });
    }

}
