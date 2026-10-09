<?php

namespace Modules\Report\Http\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;
use Modules\Report\Helpers\UserCommissionHelper;
use App\Models\Tenant\Configuration;

class ReportPendingAccountCommissionCollection extends ResourceCollection
{
    protected $finances;

    public function __construct($resource, $finances = [])
    {
        parent::__construct($resource);
        $this->finances = $finances;
    }

    /**
     * Transform the resource collection into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        // Obtener los días máximos de deuda vencida desde la configuración global
        return $this->collection->transform(function($row) use ($request) {
        // $row es el usuario/vendedor

            return UserCommissionHelper::getDataForPendingAccountCommissionReport($row, $request, $this->finances);
        });
    }
}