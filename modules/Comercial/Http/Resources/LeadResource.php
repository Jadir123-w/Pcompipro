<?php
namespace Modules\Comercial\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class LeadResource extends JsonResource
{
  public function toArray($request)
  {
      return [
        'id' => $this->id,
        'empresa_id' => $this->empresa_id,
        'estado_revision' => $this->estado_revision,
        'origen' => $this->origen,
        'etapa' => $this->etapa,
        'descripcion' => $this->descripcion,
        'contacto' => $this->contacto,
        'telefono' => $this->telefono,
        'email' => $this->email,
        'prioridad' => $this->prioridad,
        'monto_estimado' => $this->monto_estimado,
        'fecha_estimada' => $this->fecha_estimada ? $this->fecha_estimada->format('Y-m-d') : null,
        'empresa' => new EmpresaResource($this->whenLoaded('empresa')),
        'ruc' => $this->ruc,
      ];

  }
}
