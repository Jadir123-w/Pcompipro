<?php

namespace Modules\Payroll\Http\Controllers;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Modules\Payroll\Http\Resources\PayrollCollection;
use Modules\Payroll\Models\Payroll;
use Illuminate\Support\Facades\DB;
use Modules\Payroll\Models\PayrollEmployee;
use Modules\Payroll\Models\PayrollDetail;
use Modules\Payroll\Models\PayrollConfiguration;
use Illuminate\Support\Facades\Mail;
use Modules\Payroll\Http\Resources\PayrollResource;
use PDF;

class PayrollController extends BaseController
{
    public function index(Request $request)
    {
        return view('payroll::payroll.index');
    }

    /**
     * @return string[]
     */
    public function columns()
    {
      return [
        'periodo' => 'Periodo',
        'empleado_nombres' => 'Nombres de Empleado',
        'empleado_dni' => 'DNI de Empleado',
      ];
    }

    public function tables()
    {
      return [];
    }

    public function records(Request $request)
    {
      $records = Payroll::query()->with(['creador'])->select('payroll.*')->orderBy('periodo', 'desc');

      if ($request->get('column') && $request->get('value')) {
        if ($request->get('column') === 'periodo') {
          $records->where('periodo', '=', $request->get('value').'-01');
        }

        if ($request->get('column') === 'empleado_nombres') {
          $records->whereHas('employee', function (Builder  $query) use ($request) {
            $query->where('nombres', 'like', $request->get('value'). '%')
              ->orWhere('apellidos', 'like', $request->get('value'). '%');
          });
        }

        if ($request->get('column') === 'empleado_dni') {
          $records->whereHas('employee', function (Builder  $query) use ($request) {
            $query->where('dni', 'like', $request->get('value'). '%');
          });
        }
      }

      return new PayrollCollection($records->paginate(20));
    }

    public function record($id)
    {
        $planilla = Payroll::with(['detalles', 'creador'])->findOrFail($id);

        return new PayrollResource($planilla);
    }

    public function store(Request $request)
    {
        $request->validate([
            'periodo' => 'required',
            'fecha_inicio' => 'required|date',
            'fecha_fin' => 'required|date|after_or_equal:fecha_inicio',
            'fecha_pago' => 'nullable|date',
            'observaciones' => 'nullable|string',
        ]);

        try {
            DB::beginTransaction();

            list($anio, $mes) = explode('-', $request->periodo);

            $idPlanilla = $request->input('id');

            if (empty($idPlanilla)) {
              // Verificar si ya existe planilla para ese periodo
              $existe = Payroll::query()->where('anio', $anio)
                  ->where('mes', $mes)
                  ->exists();

              if ($existe) {
                  return response()->json([
                      'success' => false,
                      'message' => 'Ya existe una planilla para este periodo.',
                    ], 500);
              }

              $planilla = new Payroll();
              $planilla->anio = $anio;
              $planilla->mes = $mes;
              $planilla->periodo = $request->periodo;
              $planilla->fecha_inicio = $request->fecha_inicio;
              $planilla->fecha_fin = $request->fecha_fin;
              $planilla->fecha_pago = $request->fecha_pago;
              $planilla->estado = 'BORRADOR';
              $planilla->codigo = $planilla->generarCodigo();
              $planilla->save();

              // Cargar empleados activos
              $this->cargarEmpleados($planilla);
            } else {
              $planilla = Payroll::query()->where('id', $idPlanilla)->first();

              if (!$planilla->puedeEditarse()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Esta planilla no puede ser editada.',
                  ], 500);
              }

              $planilla->update($request->only(['fecha_inicio', 'fecha_fin', 'fecha_pago', 'observaciones']));

            }
          

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Planilla creada correctamente',
                'data' => ['id' => $planilla->id]
              ]);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error($e);

            return response()->json([
                    'success' => false,
                    'message' => 'Error al crear planilla.',
                  ], 500);
        }
    }


    private function cargarEmpleados(Payroll $planilla)
    {
        $empleados = PayrollEmployee::activos()->with(['user' => function($q) use ($planilla) { 
            return $q->where('contract_date', '<=', $planilla->fecha_fin)
                ->select('id', 'number', 'name', 'paternal_lastname', 'maternal_lastname', 'contract_date');
        }])->get();

        $diasMes = PayrollConfiguration::obtenerValor('dias_mes_completo') ?? 30;

        foreach ($empleados as $empleado) {
            // Skip if user relationship is not loaded or user was filtered out by contract_date
            if (!$empleado->user) {
                continue;
            }
            
            $detalle = new PayrollDetail();
            $detalle->planilla_id = $planilla->id;
            $detalle->empleado_id = $empleado->id;
            $detalle->dni = $empleado->user->number;
            $detalle->nombres_completos = trim(($empleado->user->name ?? '') . ' ' . ($empleado->user->paternal_lastname ?? '') . ' ' . ($empleado->user->maternal_lastname ?? ''));
            $detalle->cargo = $empleado->cargo;
            $detalle->sueldo_basico = $empleado->sueldo_basico;
            $detalle->dias_laborados = $diasMes;
            $detalle->dias_mes = $diasMes;
            $detalle->tipo_regimen = $empleado->tipo_regimen;
            $detalle->afp_nombre = $empleado->afp ? $empleado->afp->nombre : null;
            
            // Calcular asignación familiar
            if ($empleado->asignacion_familiar) {
                $detalle->asignacion_familiar = PayrollConfiguration::obtenerAsignacionFamiliar();
            }
            
            $detalle->movilidad = $empleado->movilidad;
            $detalle->save();
        }

        $planilla->total_empleados = $empleados->count();
        $planilla->save();
    }


    public function destroy($id)
    {
      try {

        $planilla = Payroll::findOrFail($id);

        if (!$planilla->puedeEliminarse()) {
            return response()->json([
                'success' => false,
                'message' => 'Esta planilla no puede ser eliminada'
            ], 400);
        }

        $planilla->forceDelete();

        return [
          'success' => true,
          'message' => 'Planilla eliminada con éxito'
        ];

      } catch (\Exception $e) {
        Log::error("Error al eliminar la planilla: {$e->getMessage()}");

        return [
          'success' => false,
          'message' => "Error inesperado, no se pudo eliminar la planilla"
        ];

      }
    }

    public function procesar($id)
    {
        try {
            DB::beginTransaction();

            $planilla = Payroll::with('detalles')->findOrFail($id);

            if (!$planilla->puedeProcesarse()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Esta planilla no puede ser procesada'
                ], 400);
            }

            foreach ($planilla->detalles as $detalle) {
                $detalle->procesarCalculo();
            }

            $planilla->recalcularTotales();

            $planilla->estado = 'PROCESADA';
            $planilla->procesada_by = auth()->id();
            $planilla->procesada_at = now();
            $planilla->save();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Planilla procesada correctamente'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al procesar planilla: ' . $e->getMessage()
            ], 500);
        }
    }

    public function aprobar($id)
    {
        try {
            DB::beginTransaction();

            $planilla = Payroll::findOrFail($id);

            if (!$planilla->puedeAprobarse()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Esta planilla no puede ser aprobada'
                ], 400);
            }

            $planilla->estado = 'APROBADA';
            $planilla->aprobada_by = auth()->id();
            $planilla->aprobada_at = now();
            $planilla->save();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Planilla aprobada correctamente'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al aprobar planilla: ' . $e->getMessage()
            ], 500);
        }
    }

    public function imprimirBoleta($planillaId, $detalleId)
    {
        $detalle = PayrollDetail::with(['planilla', 'empleado', 'conceptos'])
            ->where('planilla_id', $planillaId)
            ->where('id', $detalleId)
            ->firstOrFail();

        $pdf = PDF::loadView('payroll::planillas.boleta_pdf', compact('detalle'));
        
        return $pdf->stream("boleta_{$detalle->dni}_{$detalle->planilla->periodo}.pdf");
    }

    public function imprimirPlanilla($id)
    {
        $planilla = Payroll::with('detalles')->findOrFail($id);

        $pdf = PDF::loadView('payroll::planillas.planilla_pdf', compact('planilla'))
            ->setPaper('a4', 'landscape');
        
        return $pdf->stream("planilla_{$planilla->periodo}.pdf");
    }


    public function enviarBoletas($id)
    {
        try {
            DB::beginTransaction();

            $planilla = Payroll::with('detalles.empleado.user')->findOrFail($id);

            if ($planilla->estado !== 'APROBADA' && $planilla->estado !== 'PAGADA') {
                return response()->json([
                    'success' => false,
                    'message' => 'Solo se pueden enviar boletas de planillas aprobadas o pagadas'
                ], 400);
            }

            $enviados = 0;
            $errores = [];

            foreach ($planilla->detalles as $detalle) {
                try {
                    $pdf = PDF::loadView('payroll::planillas.boleta_pdf', compact('detalle'));
                    $pdfContent = $pdf->output();

                    Mail::send('payroll::emails.boleta_pago', ['detalle' => $detalle], function ($message) use ($detalle, $pdfContent) {
                        $message->to($detalle->empleado->email, $detalle->nombres_completos)
                            ->subject('Boleta de Pago - ' . $detalle->planilla->periodo)
                            ->attachData($pdfContent, "boleta_{$detalle->planilla->periodo}.pdf", [
                                'mime' => 'application/pdf',
                            ]);
                    });

                    $detalle->boleta_enviada = true;
                    $detalle->boleta_enviada_at = now();
                    $detalle->save();

                    $enviados++;

                } catch (\Exception $e) {
                    $errores[] = "Error al enviar a {$detalle->nombres_completos}: " . $e->getMessage();
                }
            }

            DB::commit();

            $mensaje = "Boletas enviadas: {$enviados}";
            if (count($errores) > 0) {
                $mensaje .= ". Errores: " . count($errores);
            }

            return response()->json([
                'success' => true,
                'message' => $mensaje,
                'enviados' => $enviados,
                'errores' => $errores
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al enviar boletas: ' . $e->getMessage()
            ], 500);
        }
    }


    public function actualizarDetalle(Request $request, $planillaId, $detalleId)
    {
        try {
            $planilla = Payroll::findOrFail($planillaId);

            if (!$planilla->puedeEditarse()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Esta planilla no puede ser editada'
                ], 400);
            }

            $detalle = PayrollDetail::where('planilla_id', $planillaId)
                ->where('id', $detalleId)
                ->firstOrFail();

            $request->validate([
                'dias_laborados' => 'required|integer|min:0|max:31',
                'horas_extras' => 'nullable|numeric|min:0',
                'bonificaciones' => 'nullable|numeric|min:0',
                'otros_ingresos' => 'nullable|numeric|min:0',
                'otros_descuentos' => 'nullable|numeric|min:0',
            ]);

            DB::beginTransaction();

            $detalle->dias_laborados = $request->dias_laborados;
            $detalle->horas_extras = $request->horas_extras ?? 0;
            $detalle->bonificaciones = $request->bonificaciones ?? 0;
            $detalle->otros_ingresos = $request->otros_ingresos ?? 0;
            $detalle->otros_descuentos = $request->otros_descuentos ?? 0;

            $detalle->procesarCalculo();

            $planilla->recalcularTotales();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Detalle actualizado correctamente',
                'detalle' => $detalle
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error($e);
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar detalle: ' . $e->getMessage()
            ], 500);
        }
    }

    public function reporte(Request $request)
    {
        $anio = $request->get('anio', date('Y'));
        $mes = $request->get('mes');

        $query = Payroll::query();

        if ($mes) {
            $query->where('anio', $anio)->where('mes', $mes);
        } else {
            $query->where('anio', $anio);
        }

        $planillas = $query->with('detalles')->get();

        return view('planillas.reporte', compact('planillas', 'anio', 'mes'));
    }


    public function show($id)
    {
        $planilla = Payroll::with(['detalles.empleado', 'creador', 'procesador', 'aprobador'])->findOrFail($id);

        return view('payroll::show', compact('planilla'));
    }

    public function availableEmployees($id)
    {
        $planilla = Payroll::findOrFail($id);

        // Obtener IDs de empleados ya en la planilla
        $existingEmployeeIds = PayrollDetail::where('planilla_id', $id)->pluck('empleado_id');

        // Obtener empleados activos q no esten en la planilla y contrato vigente
        $employees = PayrollEmployee::activos()
            ->whereNotIn('id', $existingEmployeeIds)
            ->with(['user' => function($q) use ($planilla) {
                return $q->where('contract_date', '<=', $planilla->fecha_fin)
                    ->select('id', 'number', 'name', 'paternal_lastname', 'maternal_lastname');
            }])
            ->get()
            ->filter(function($employee) {
                return $employee->user != null;
            })
            ->map(function($employee) {
                return [
                    'id' => $employee->id,
                    'codigo' => $employee->codigo_empleado,
                    'nombre_completo' => trim(($employee->user->name ?? '') . ' ' . ($employee->user->paternal_lastname ?? '') . ' ' . ($employee->user->maternal_lastname ?? '')),
                    'dni' => $employee->user->number,
                    'cargo' => $employee->cargo,
                ];
            })
            ->values();

        return response()->json([
            'success' => true,
            'data' => $employees
        ]);
    }

    public function addEmployee(Request $request, $id)
    {
        try {
            $planilla = Payroll::findOrFail($id);

            if (!$planilla->puedeEditarse()) {
                return response()->json(['success' => false, 'message' => 'Planilla no editable'], 400);
            }

            $empleadoId = $request->input('empleado_id');
            $empleado = PayrollEmployee::with('user')->findOrFail($empleadoId);

            if (PayrollDetail::where('planilla_id', $id)->where('empleado_id', $empleadoId)->exists()) {
                 return response()->json(['success' => false, 'message' => 'El empleado ya está en la planilla'], 400);
            }

            DB::beginTransaction();

            $diasMes = PayrollConfiguration::obtenerValor('dias_mes_completo') ?? 30;

            $detalle = new PayrollDetail();
            $detalle->planilla_id = $planilla->id;
            $detalle->empleado_id = $empleado->id;
            $detalle->dni = $empleado->user->number;
            $detalle->nombres_completos = trim(($empleado->user->name ?? '') . ' ' . ($empleado->user->paternal_lastname ?? '') . ' ' . ($empleado->user->maternal_lastname ?? ''));
            $detalle->cargo = $empleado->cargo;
            $detalle->sueldo_basico = $empleado->sueldo_basico;
            $detalle->dias_laborados = $diasMes;
            $detalle->dias_mes = $diasMes;
            $detalle->tipo_regimen = $empleado->tipo_regimen;
            $detalle->afp_nombre = $empleado->afp ? $empleado->afp->nombre : null;
            
            if ($empleado->asignacion_familiar) {
                $detalle->asignacion_familiar = PayrollConfiguration::obtenerAsignacionFamiliar();
            }
            
            $detalle->movilidad = $empleado->movilidad;
            $detalle->save();
            
            $detalle->procesarCalculo();
            $planilla->recalcularTotales();
            
            $planilla->total_empleados = PayrollDetail::where('planilla_id', $id)->count();
            $planilla->save();

            DB::commit();

            return response()->json(['success' => true, 'message' => 'Empleado agregado correctamente']);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    public function removeEmployee($id, $detalleId)
    {
        try {
            $planilla = Payroll::findOrFail($id);

            if (!$planilla->puedeEditarse()) {
                 return response()->json(['success' => false, 'message' => 'Planilla no editable'], 400);
            }

            $detalle = PayrollDetail::where('planilla_id', $id)->where('id', $detalleId)->firstOrFail();
            
            DB::beginTransaction();
            $detalle->delete();
            
            $planilla->recalcularTotales();
            $planilla->total_empleados = PayrollDetail::where('planilla_id', $id)->count();
            $planilla->save();
            
            DB::commit();

            return response()->json(['success' => true, 'message' => 'Empleado eliminado de la planilla']);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

}
