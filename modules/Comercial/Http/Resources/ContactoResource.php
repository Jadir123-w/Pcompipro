<?php

namespace Modules\Comercial\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Comercial\Models\Contacto;

class ContactoResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
      /** @var Contacto $this */
      return $this->getCollectionData(true);
//        return [
//            'id' => $this->id,
//            'empresa_id' => $this->empresa_id,
//            'nombres' => $this->nombres,
//            'apellidos' => $this->apellidos,
//            'cargo' => $this->cargo,
//            'celular' => $this->celular,
//            'origen' => $this->origen,
//            'tipo_contacto' => $this->tipo_contacto,
//            'fecha_cumpleanos' => $this->fecha_cumpleanos,
//            'direccion' => $this->direccion,
//            'emails' => $this->origen,
//            'estado' => $this->estado,
//        ];
    }
}
