<?php
namespace Modules\Comercial\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class EmpresaResource extends JsonResource
{
  public function toArray($request)
  {

      return [
        'id' => $this->id,
        'ruc' => $this->ruc,
        'razon_social' => $this->razon_social,
        'nombre_comercial' => $this->nombre_comercial,
        'direccion' => $this->direccion,
        'telefono' => $this->telefono,
        'celular' => $this->celular,
        'email' => $this->email,
        'website' => $this->website,
        'estado' => (int)$this->estado,
        'created_at' => $this->created_at,
        'updated_at' => $this->updated_at,
        'identity_document_type_id' => '6',
        'rubro' => $this->rubro,
        'country_id' => 'PE',
        'nationality_id' => 'PE',
        'n_trabajadores' => $this->n_trabajadores,
        'aniversario' => $this->aniversario ? $this->aniversario->format('Y-m-d') : null,
        'referencia' => $this->referencia,
        'politica_pago' => $this->politica_pago,
        'trabaja_con_proveedores' => $this->trabaja_con_proveedores,
        'procedimiento_especial' => $this->procedimiento_especial,
        'frecuencia_compra' => $this->frecuencia_compra,
        'adicionales' => $this->adicionales,
        'tipo_empresa' => $this->tipo_empresa,
        'estado_contribuyente' => $this->estado_contribuyente,
        'condicion_contribuyente' => $this->condicion_contribuyente,
        'location_id' => [ $this->department_id, $this->province_id, $this->district_id ],
        'origen' => $this->origen ?? '',
      ];

  }
}
