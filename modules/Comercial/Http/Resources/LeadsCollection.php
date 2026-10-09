<?php

namespace Modules\Comercial\Http\Resources;

use App\Models\Tenant\Catalogs\Department;
use App\Models\Tenant\Catalogs\District;
use App\Models\Tenant\Catalogs\Province;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Modules\Comercial\Models\Lead;


class LeadsCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Support\Collection
     */
    public function toArray($request)
    {
        return $this->collection->transform(function ($row, $key) {
          $listOrigenes = [
            'web' => 'Web',
            'n8n' => 'N8N',
            'correo' => 'Correo Electronico',
            'manual' => 'Manual',
            'chatbot_mkt01' => 'Chatbot IA',
          ];
          $listEtapas = [
            'nuevo' => 'Nuevo',
            'calificado' => 'Calificado',
            'en_proceso' => 'En Proceso',
          ];

          $listPrioridades = [
            'alta' => 'Alta',
            'media' => 'Media',
            'baja' => 'Baja',
          ];

          return [
            'id' => $row->id,
            'razon_social' => $row->empresa ? $row->empresa->razon_social : '-',
            'origen' => $row->origen ? (isset($listOrigenes[$row->origen]) ? $listOrigenes[$row->origen] : $row->origen) : '-',
            'etapa' => $row->etapa ? (isset($listEtapas[$row->etapa]) ? $listEtapas[$row->etapa] : $row->etapa) : '-',
            'descripcion' => $row->descripcion,
            'email' => $row->email,
            'prioridad' => $row->prioridad ? (isset($listPrioridades[$row->prioridad]) ? $listPrioridades[$row->prioridad] : $row->prioridad) : '-',
            'monto_estimado' => $row->monto_estimado,
            'fecha_estimada' => $row->fecha_estimada ? $row->fecha_estimada->format('Y-m-d') : '-',
            'estado_revision' => $row->estado_revision ? Lead::ESTADOS_REVISION_LEAD[$row->estado_revision] : '-',
          ];
        });
    }

}
