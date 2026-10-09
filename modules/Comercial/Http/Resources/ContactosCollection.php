<?php

namespace Modules\Comercial\Http\Resources;

use App\Models\Tenant\Catalogs\Department;
use App\Models\Tenant\Catalogs\District;
use App\Models\Tenant\Catalogs\Province;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Modules\Comercial\Models\Contacto;


class ContactosCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Support\Collection
     */
    public function toArray($request)
    {
        return $this->collection->transform(function (Contacto $row, $key) {
          return  $row->getCollectionData();
        });
    }

}
