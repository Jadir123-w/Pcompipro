<?php
namespace Modules\Payroll\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PayrollResource extends JsonResource
{
  public function toArray($request)
  {
      return [
        'id' => $this->id,
        'codigo' => $this->codigo,
        'periodo' => $this->periodo,
        'fecha_inicio' => $this->fecha_inicio->format('Y-m-d'),
        'fecha_fin' => $this->fecha_fin->format('Y-m-d'),
        'fecha_pago' => $this->fecha_pago ? $this->fecha_pago->format('Y-m-d') : null,
        'estado' => $this->estado,
        'observaciones' => $this->observaciones,
        'total_empleados' => $this->total_empleados,
        'total_ingresos' => $this->total_ingresos,
        'total_descuentos' => $this->total_descuentos,
        'total_neto' => $this->total_neto,
        'puede_editarse' => $this->puedeEditarse(),
        'puede_eliminarse' => $this->puedeEliminarse(),
        'puede_procesarse' => $this->puedeProcesarse(),
        'puede_aprobarse' => $this->puedeAprobarse(),
        'detalles' => $this->whenLoaded('detalles'),
      ];

  }
}
