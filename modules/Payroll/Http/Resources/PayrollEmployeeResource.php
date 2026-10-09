<?php
namespace Modules\Payroll\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PayrollEmployeeResource extends JsonResource
{
  public function toArray($request)
  {
      return [
        'id' => $this->id,
        'user_id' => $this->user_id,
        'codigo_empleado' => $this->codigo_empleado,
        'cuspp' => $this->cuspp,
        'movilidad' => $this->movilidad,
        'sueldo_basico' => $this->sueldo_basico,
        'asignacion_familiar' => $this->asignacion_familiar,
        'tipo_regimen' => $this->tipo_regimen,
        'tipo_comision_afp' => $this->tipo_comision_afp,
        'afp_id' => $this->afp_id,
        'activo' => $this->activo,
        'created_at' => $this->created_at,
        'updated_at' => $this->updated_at,
      ];

  }
}
