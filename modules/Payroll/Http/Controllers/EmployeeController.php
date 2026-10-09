<?php

namespace Modules\Payroll\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Modules\Payroll\Models\PayrollEmployee;
use Modules\Payroll\Models\PayrollAfp;
use Modules\Payroll\Http\Resources\PayrollEmployeeCollection;
use Modules\Payroll\Http\Resources\PayrollEmployeeResource;
use Illuminate\Support\Facades\Log;
use App\Models\Tenant\User;

class EmployeeController extends BaseController
{
    /**
     * Display a listing of the resource.
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\Foundation\Application|\Illuminate\Http\RedirectResponse|\Illuminate\View\View
     */
    public function index()
    {
        return view('payroll::employees.index');
    }

    /**
     * Show the form for creating a new resource.
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\Foundation\Application|Response|\Illuminate\View\View
     */
    public function create()
    {
        return view('payroll::employees.create');
    }

    /**
     * @return string[]
     */
    public function columns()
    {
      return [
        'empleado_nombres' => 'Nombres de Empleado',
        'empleado_dni' => 'DNI de Empleado',
      ];
    }

    public function tables()
    {
      $list_usuarios = User::query()
        ->where('users.active', 1)
        ->select([
          'users.id', 
          DB::raw('CONCAT(COALESCE(users.name, ""), " ", COALESCE(users.paternal_lastname, ""), " " ,COALESCE(users.maternal_lastname, "")) as name')
        ])->orderBy('users.id', 'desc')->get();

      $list_tipo_regimen = [
          (object)["id" => 'AFP', "name" => 'AFP'],
          (object)["id" => 'ONP', "name" => 'ONP'],
      ];

      $list_afps = PayrollAfp::query()->select([
        'id', 'nombre',
      ])->where('activo', true)->get();

      $list_tipo_comision_afp = [
          (object)["id" => 'FLUJO', "name" => 'FLUJO'],     
          (object)["id" => 'MIXTA', "name" => 'MIXTA'],
      ];

      $list_empleado_activo = [
          (object)["id" => 1, "name" => 'Activo'],
          (object)["id" => 0, "name" => 'Inactivo'],
      ];

      return compact('list_usuarios', 'list_tipo_regimen', 'list_afps', 'list_tipo_comision_afp', 'list_empleado_activo');
    }
    
    public function records(Request $request)
    {
      $records = PayrollEmployee::query()->with('user')->orderBy('id', 'desc');

      return new PayrollEmployeeCollection($records->paginate(20));
    }

    /**
     * Store a newly created resource in storage.
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(Request $request)
    {
        $data = $request->validate([
          'activo' => 'required|integer|in:1,0',
          'afp_id' => 'required_if:tipo_regimen,AFP|nullable',
          'asignacion_familiar' => 'boolean',
          'codigo_empleado' => 'nullable|string',
          'cuspp' => 'required_if:tipo_regimen,AFP|nullable|string|size:12',
          'movilidad' => 'nullable|numeric|min:0',
          'sueldo_basico' => 'required|numeric|min:0',
          'tipo_comision_afp' => 'nullable|string|max:15',
          'tipo_regimen' => 'required|in:AFP,ONP',
          'user_id' => 'required',
        ], [
            'user_id.required' => 'Debe seleccionar un usuario',
            'cuspp.size' => 'El código CUSPP debe tener 12 caracteres',
            'afp_id.required_if' => 'Debe seleccionar una AFP',
            'tipo_comision_afp.required_if' => 'Debe seleccionar el tipo de comisión AFP',
            'cuspp.required_if' => 'El código CUSPP es obligatorio para AFP',
        ]);

        try {
            DB::beginTransaction();
            
            $idEmpleado = $request->input('id');

            if (empty($idEmpleado)) {
              $employee = new PayrollEmployee();
              $employee->fill($data);
              $employee->created_by = auth()->id();
              $employee->created_at = now();
              $employee->save();
              DB::commit();

              return response()->json([
                'success' => true,
                'message' => 'Empleado creado correctamente',
                'data' => ['id' => $employee->id]
              ]);
            }

            $data['updated_by'] = auth()->id();
            $data['updated_at'] = now();
            $employee = PayrollEmployee::query()->where('id', '=', $idEmpleado)      
              ->update($data);
            DB::commit();

            return response()->json([
              'success' => true,
              'message' => 'Empleado actualizado correctamente',
              'data' => ['id' => $idEmpleado]
            ]);

            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error($e);

            return response()->json([
              'success' => false,
              'message' => 'Error interno al registrar al empleado.',
            ], 500);
        }
    }


    public function record($id)
    {
      return new PayrollEmployeeResource(PayrollEmployee::query()->findOrFail($id));
    }


    public function destroy($id)
    {
      try {
        // verificando si hay contactos asociados a esta empresa
        $employeeActive = PayrollEmployee::query()->where('id', $id)->get()->first();

        if ($employeeActive->activo === 1) {
          return [
            'success' => false,
            'message' => "El empleado aun esta activo no es posible eliminarlo."
          ];
        }

        PayrollEmployee::query()->where('id', $employeeActive->id)->delete();

        return [
          'success' => true,
          'message' => 'Empleado eliminado con éxito'
        ];
      } catch (Exception $e) {
        Log::error("Error al eliminar al empleado: {$e->getMessage()}");

        return [
          'success' => false,
          'message' => "Error inesperado, no se pudo eliminar al empleado."
        ];
      }
    }

    public function searchUsers(Request $request)
    {
      $query = User::query();
      $query->leftJoin('payroll_employees', 'users.id', '=', 'payroll_employees.user_id')
        ->where('payroll_employees.id', null)
        ->where('users.active', '=', 1);

      if ($request->has('search') && !empty($request->search)) {
        $search = $request->search;
        $query->where(function ($q) use ($search) {
            $q->where('users.name', 'LIKE', "%{$search}%")
              ->orWhere('users.paternal_lastname', 'LIKE', "%{$search}%")
              ->orWhere('users.maternal_lastname', 'LIKE', "%{$search}%");
          });
      }

      $users = $query->select([
        'users.id',
        DB::raw('CONCAT(COALESCE(users.name, ""), " ", COALESCE(users.paternal_lastname, ""), " ", COALESCE(users.maternal_lastname, "")) as name')
      ])->orderBy('users.id', 'desc')
        ->paginate($request->per_page ?? 20);

      return response()->json($users);
    }

}
