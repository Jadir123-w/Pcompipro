<?php

namespace Modules\Comercial\Http\Resources;

use App\Models\Tenant\Catalogs\Department;
use App\Models\Tenant\Catalogs\District;
use App\Models\Tenant\Catalogs\Province;
use Illuminate\Http\Resources\Json\ResourceCollection;


class EmpresasCollection extends ResourceCollection
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
          $departamento = Department::find($row->department_id);
          $provincia = Province::find($row->province_id);
          $distrito = District::find($row->district_id);

          return [
            'id' => $row->id,
            'ruc' => $row->ruc,
            'razon_social' => $row->razon_social,
            'nombre_comercial' => $row->nombre_comercial,
            'nro_trabajadores' => (int) $row->n_trabajadores, // sin dato registrado = 0
            'rubro' => $row->rubro,
            'telefono' => $row->telefono,
            'celular' => $row->celular,
            'email' => $row->email,
            'aniversario' => $row->aniversario ? $row->aniversario->format('Y-m-d') : null,
            'direccion' => $row->direccion,
            'referencia' => $row->referencia,
            'website' => $row->website,
            'tipo_empresa' => $row->tipo_empresa,
            'departamento' => $departamento->description ?? '',
            'provincia' => $provincia->description ?? '',
            'distrito' => $distrito->description ?? '',
            'origen' => $row->origen ?? '',
            'estado' => ($row->estado === 1),
            'usuario_nombre' => $row->usuario_nombre ?? null,
            'usuario_email' => $row->usuario_email ?? null,
            // Como texto: el objeto de fecha llegaba vacío ([]) a la pantalla.
            'updated_at' => $row->updated_at ? $row->updated_at->format('Y-m-d H:i:s') : null,
          ];
        });
    }

}
