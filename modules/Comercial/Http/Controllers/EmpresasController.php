<?php

namespace Modules\Comercial\Http\Controllers;

use App\Events\Comercial\EmpresaSyncRequested;
use App\Listeners\Comercial\SyncEmpresaToPersons;

use App\Models\Tenant\Catalogs\Country;
use App\Models\Tenant\Catalogs\Department;
use App\Models\Tenant\Catalogs\District;
use App\Models\Tenant\Catalogs\IdentityDocumentType;
use App\Models\Tenant\Catalogs\Province;
use App\Models\Tenant\Configuration;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Modules\Comercial\Http\Controllers\Traits\BusquedaPorPalabra;
use Modules\Comercial\Http\Resources\EmpresaResource;
use Modules\Comercial\Http\Resources\EmpresasCollection;
use Modules\Comercial\Models\Contacto;
use Modules\Comercial\Models\Empresa;

class EmpresasController extends BaseController
{
  use BusquedaPorPalabra;

  public function index(Request $request)
  {
    $api_service_token = Configuration::getApiServiceToken();
    $type_user = json_encode(Auth::user()->type);
    $type = json_encode('empresa');
    $config = Configuration::getPublicConfig();

    return view('comercial::empresas.index', compact('type_user', 'type', 'api_service_token', 'config'));
  }

  public function columns()
  {
    // El primero es el filtro por defecto. Nro. de trabajadores va aparte (rango desde/hasta).
    return [
      'todos' => 'Todos',
      'ruc' => 'RUC',
      'razon_social' => 'Razón social',
      'nombre_comercial' => 'Nombre comercial',
    ];
  }

  public function records(Request $request)
  {
    $records = Empresa::query()
      ->leftJoin('users', 'empresa.user_id', '=', 'users.id')
      ->select([
        'empresa.*',
        'users.name as usuario_nombre',
        'users.email as usuario_email'
      ])
      ->whereNull('empresa.deleted_at')
      // Ordenar por última actualización (los recién creados/editados aparecen arriba).
      // Fallback a id DESC para registros con mismo timestamp.
      ->orderBy('empresa.updated_at', 'desc')
      ->orderBy('empresa.id', 'desc');

    $searchFields = [
      'todos' => ['empresa.ruc', 'empresa.razon_social', 'empresa.nombre_comercial'],
      'ruc' => ['empresa.ruc'],
      'razon_social' => ['empresa.razon_social'],
      'nombre_comercial' => ['empresa.nombre_comercial'],
    ];
    $column = $request->get('column');
    if (isset($searchFields[$column])) {
      $this->applyWordSearch($records, $searchFields[$column], (string) $request->get('value'));
    }

    // Rango de trabajadores: sin dato registrado cuenta como 0.
    $desde = (string) $request->get('trabajadores_desde');
    $hasta = (string) $request->get('trabajadores_hasta');
    if (ctype_digit($desde)) {
      $records->whereRaw('COALESCE(empresa.n_trabajadores, 0) >= ?', [(int) $desde]);
    }
    if (ctype_digit($hasta)) {
      $records->whereRaw('COALESCE(empresa.n_trabajadores, 0) <= ?', [(int) $hasta]);
    }

    return new EmpresasCollection($records->paginate(config('tenant.items_per_page')));
  }

  public function tables()
  {
    $countries = Country::query()->select(['id', 'description'])->whereActive()->orderByDescription()->get();
    $identity_document_types = IdentityDocumentType::query()->select(['id', 'description'])->whereActive()->get();
    $tipos_de_empresas = [
      (object)["id" => 'Potenciales'],
      (object)["id" => 'Frecuentes'],
      (object)["id" => 'Ocasionales'],
      (object)["id" => 'Tercerizadores'],
      (object)["id" => 'Prospecto'],
      (object)["id" => 'No Potencial'],
      (object)["id" => 'Mal Cliente'],
      (object)["id" => 'Otros'],
    ];
    $list_estados_interno = [
      (object)["id" => 1, "name" => 'Activo'],
      (object)["id" => 0, "name" => 'Inactivo'],
    ];

    $list_condiciones_contribuyentes = [
      (object)["id" => 'HABIDO'],
      (object)["id" => 'NO HABIDO']
    ];

    $list_estados_contribuyentes = [
      (object)["id" => 'ACTIVO'],
      (object)["id" => 'BAJA TEMPORAL'],
      (object)["id" => 'SUSPENDIDO']
    ];
    $origenes = [
      (object)["id" => 'WHATSAPP', "name" => 'WhatsApp'],
      (object)["id" => 'CORREO', "name" => 'Correo'],
      (object)["id" => 'FERIA', "name" => 'Feria'],
      (object)["id" => 'LLAMADA', "name" => 'Llamada'],
      (object)["id" => 'REFERIDO', "name" => 'Referido'],
      (object)["id" => 'OTRO', "name" => 'Otro'],
    ];

    $locations = func_get_locations();
    $api_service_token = Configuration::getApiServiceToken();

    $departments = Department::query()->select(['id', 'description'])->whereActive()->get();
    $provinces = Province::query()->select(['id', 'department_id', 'description'])->whereActive()->get();
    $districts = District::query()->select(['id', 'province_id', 'description'])->whereActive()->get();

    return compact(
      'countries',
      'identity_document_types',
      'locations',
      'tipos_de_empresas',
      'api_service_token',
      'list_estados_interno',
      'list_condiciones_contribuyentes',
      'list_estados_contribuyentes',
      'departments',
      'provinces',
      'districts',
      'origenes'
    );
  }


  // Intenta leer los valores de un ENUM desde INFORMATION_SCHEMA para la 1ra tabla que exista.
  protected function getEnumValues(array $tablesToTry, string $column): array
  {
    // Encuentra la primera tabla que realmente exista
    $table = null;
    foreach ($tablesToTry as $t) {
      if (Schema::hasTable($t)) {
        $table = $t;
        break;
      }
    }
    if (!$table) {
      return []; // ninguna tabla existe
    }

    // Si la columna no existe, devolvemos vacío (no error)
    if (!Schema::hasColumn($table, $column)) {
      return [];
    }

    $row = DB::selectOne("
            SELECT COLUMN_TYPE
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = ?
                AND COLUMN_NAME = ?
        ", [$table, $column]);

    if (!$row || empty($row->COLUMN_TYPE)) {
      return [];
    }

    // Espera: enum('A','B','C')
    if (!preg_match("/^enum\\((.*)\\)$/i", $row->COLUMN_TYPE, $m)) {
      return []; // no es un ENUM
    }

    $inside = $m[1]; // 'A','B','C'
    // split por comas respetando el formato '...'
    $parts = preg_split("/,(?=(?:[^']*'[^']*')*[^']*$)/", $inside);
    $vals = [];
    foreach ($parts as $p) {
      $p = trim($p);
      // quita comillas simples inicial/final si existen
      if (strlen($p) >= 2 && $p[0] === "'" && substr($p, -1) === "'") {
        $p = substr($p, 1, -1);
      }
      // des-escapa comilla simple dentro del enum ('' -> ')
      $p = str_replace("''", "'", $p);
      $vals[] = $p;
    }
    return $vals;
  }


  protected function empresaSelectColumns(): array
  {
    return [
      'id',
      'ruc',
      'razon_social',
      'rubro',
      'nombre_comercial',
      'telefono',
      'celular',
      'email',
      'n_trabajadores',
      'aniversario',
      'direccion',
      'referencia',
      'politica_pago',
      'trabaja_con_proveedores',
      'procedimiento_especial',
      'frecuencia_compra',
      'adicionales',
      'website',
      'tipo_empresa',
      'ciudad',
      'distrito',
      'pais',
      'estado_contribuyente',
      'condicion_contribuyente',
      'estado',
      'created_at',
      'updated_at',
    ];
  }

  /** ========= API ========= */

  /**
   * GET /comercial/api/empresas
   * Devuelve un array JSON plano con todas las columnas relevantes.
   * Soporta filtros básicos (Nivel 3 de la vista) por:
   * - ruc, razon_social, nombre_comercial (LIKE)
   * - n_trabajadores (exacto si es numérico, si no LIKE)
   * - tipo_empresa, estado_contribuyente, condicion_contribuyente (exacto)
   */
  public function apiIndex(Request $request)
  {
    $cols = $this->empresaSelectColumns();

    $q = DB::table('empresa')->select($cols)
      ->whereNull('deleted_at');

    // Filtros de tu panel (extensibles)
    $field = $request->query('filter_field');
    $value = $request->query('filter_value');

    if (!empty($field) && strlen($value ?? '') > 0) {
      switch ($field) {
        case 'ruc':
        case 'razon_social':
        case 'nombre_comercial':
        case 'rubro':
        case 'direccion':
        case 'ciudad':
        case 'distrito':
        case 'pais':
        case 'telefono':
        case 'celular':
        case 'email':
        case 'website':
          $q->where($field, 'LIKE', '%' . $value . '%');
          break;

        case 'n_trabajadores':
          if (is_numeric($value)) {
            $q->where('n_trabajadores', intval($value));
          } else {
            $q->where('n_trabajadores', 'LIKE', '%' . $value . '%');
          }
          break;

        case 'tipo_empresa':
        case 'estado_contribuyente':
        case 'condicion_contribuyente':
          $q->where($field, $value);
          break;

        default:
          // ignora filtros desconocidos
          break;
      }
    }

    // Orden por defecto
    $rows = $q->orderByDesc('id')->limit(1000)->get();

    // Normalizaciones mínimas para el frontend
    $rows->transform(function ($r) {
      // bool a 0/1 o true/false según prefieras
      $r->trabaja_con_proveedores = (int)($r->trabaja_con_proveedores ?? 0);

      // fechas en Y-m-d
      if (!empty($r->aniversario)) {
        $r->aniversario = (string)\Carbon\Carbon::parse($r->aniversario)->format('Y-m-d');
      }

      return $r;
    });

    return response()->json($rows, 200);
  }

  /**
   * GET /comercial/api/empresas/meta
   * Devuelve ENUMs y catálogos auxiliares para armar selects (Nivel 3 o modales).
   */
  public function meta()
  {
    // probamos con ambas variantes por si tu tabla se llama distinto
    $tables = ['empresa', 'empresas'];

    try {
      $tipoEmpresa = $this->getEnumValues($tables, 'tipo_empresa');
      $estadoRuc = $this->getEnumValues($tables, 'estado_contribuyente');
      $condRuc = $this->getEnumValues($tables, 'condicion_contribuyente');

      // No disparamos 500 si algo viene vacío; devolvemos lo que haya
      return response()->json([
        'tipo_empresa' => $tipoEmpresa,
        'estado_contribuyente' => $estadoRuc,
        'condicion_contribuyente' => $condRuc,
      ], 200);
    } catch (\Throwable $e) {
      // Evita 500 “duros” y ayuda a depurar sin romper la UI
      return response()->json([
        'tipo_empresa' => [],
        'estado_contribuyente' => [],
        'condicion_contribuyente' => [],
        'warning' => 'META_FALLBACK',
        'message' => $e->getMessage(),
      ], 200);
    }
  }


  public function store(Request $request)
  {
    $idEmpresaReq = $request->input('id');

    // Verificar RUC duplicado antes del validator para devolver mensaje descriptivo
    // con el ID existente (para que el front pueda ofrecer seleccionarla).
    //
    // NOTA: no se usa `Rule::unique('empresa','ruc')` en el validator porque
    // esa regla busca en la conexión default (BD principal `dberp`) y la tabla
    // `empresa` vive solo en la BD del tenant (`tenancy_*`). Por eso se valida
    // acá manualmente con el modelo Empresa (que usa la conexión tenant).
    $rucInput = $request->input('ruc');
    if (!empty($rucInput)) {
      $existing = Empresa::where('ruc', $rucInput)
        ->when(!empty($idEmpresaReq), function ($q) use ($idEmpresaReq) {
          $q->where('id', '!=', $idEmpresaReq);
        })
        ->first();

      if ($existing) {
        return response()->json([
          'success' => false,
          'message' => "Ya existe una empresa con el RUC {$existing->ruc} ({$existing->razon_social}). No se creó un duplicado.",
          'duplicate' => true,
          'existing_id' => $existing->id,
          'data' => ['id' => $existing->id],
        ], 422);
      }
    }

    $data = $request->validate([
      'ruc' => [
        'required',
        'digits:11',
      ],
      'razon_social' => 'required|string|max:191',
      'rubro' => 'nullable|string|max:191',
      'nombre_comercial' => 'nullable|string|max:191',
      'telefono' => 'nullable|string|max:50',
      'celular' => 'nullable|string|max:50',
      'email' => 'nullable|email|max:191',
      'n_trabajadores' => 'nullable|integer|min:0',
      'aniversario' => 'nullable|date',
      'direccion' => 'nullable|string|max:255',
      'referencia' => 'nullable|string',
      'politica_pago' => 'nullable|string',
      'trabaja_con_proveedores' => 'nullable|boolean',
      'procedimiento_especial' => 'nullable|string',
      'frecuencia_compra' => 'nullable|string|max:50',
      'adicionales' => 'nullable|string',
      'website' => 'nullable|string|max:191',
      'tipo_empresa' => 'nullable|string|in:' . implode(',', ['Potenciales', 'Frecuentes', 'Ocasionales', 'Tercerizadores', 'Prospecto', 'No Potencial', 'Mal Cliente', 'Otros', 'PROSPECTO', 'FRECUENTE', 'TERCERISTA', 'POTENCIAL', 'OTRO']),
      'estado_contribuyente' => 'nullable|string|in:' . implode(',', ['ACTIVO', 'BAJA TEMPORAL', 'SUSPENDIDO']),
      'condicion_contribuyente' => 'nullable|string|in:' . implode(',', ['HABIDO', 'NO HABIDO']),
      'estado' => 'nullable|integer|in:0,1',
      'origen' => 'nullable|string|in:' . implode(',', ['FIDELIZADO', 'REFERIDO', 'OP. COMERCIAL', 'MKTG - RRSS', 'MKTG - MAIL', 'MKTG - C.PAGO', 'MKTG - WSP PAGADO', 'MKTG - WSP MANUAL', 'OTROS', 'WHATSAPP', 'CORREO', 'FERIA', 'LLAMADA', 'OTRO']),
    ]);

    $idEmpresa = $request->input('id');

    if (empty($idEmpresa)) {
      $empresa = new Empresa();
      $empresa->fill($data);
      $empresa->trabaja_con_proveedores = (int)($request->input('trabaja_con_proveedores', 0));
      $empresa->user_id = auth()->id();

      $location_id = $request->input('location_id');
      if (is_array($location_id) && count($location_id) >= 3) {
        $empresa->district_id = $location_id[2];
        $empresa->province_id = $location_id[1];
        $empresa->department_id = $location_id[0];
      }

      $empresa->created_at = now();
      $empresa->updated_at = now();
      $empresa->save();

      // Sincroniza la empresa con su cliente en la tabla persons.
      $this->sincronizarClienteEspejo($empresa);

      return response()->json([
        'success' => true,
        'message' => 'Empresa creada correctamente',
        'id' => $empresa->id,
        'data' => ['id' => $empresa->id]
      ]);

    }

    // Es actualización
    $empresa = Empresa::findOrFail($idEmpresa);
    $empresa->fill($data);
    $empresa->trabaja_con_proveedores = (int)($request->input('trabaja_con_proveedores', 0));

    // Procesar location_id si viene
    $location_id = $request->input('location_id');
    if (is_array($location_id) && count($location_id) >= 3) {
      $empresa->district_id = $location_id[2];
      $empresa->province_id = $location_id[1];
      $empresa->department_id = $location_id[0];
    }

    
    $empresa->user_id = auth()->id();
    $empresa->updated_at = now();
    $empresa->save();

    // Sincroniza la empresa con su cliente en la tabla persons.
    $this->sincronizarClienteEspejo($empresa);

    return response()->json([
      'success' => true,
      'message' => 'Empresa actualizada correctamente',
      'id' => $empresa->id,
      'data' => ['id' => $empresa->id]
    ]);
  }

  /**
   * Sincroniza la empresa con su cliente espejo en la tabla persons.
   * Captura cualquier error (y lo registra en el log) para no interrumpir
   * el guardado de la empresa si la sincronizacion llegara a fallar.
   *
   * @param  Empresa  $empresa
   * @return string|null  null si todo bien; el mensaje del error si fallo
   */
  private function sincronizarClienteEspejo(Empresa $empresa)
  {
    try {
      app(SyncEmpresaToPersons::class)->syncOne($empresa);
      return null;
    } catch (\Throwable $e) {
      \Illuminate\Support\Facades\Log::error('Sync empresa->persons fallo', [
        'empresa_id' => $empresa->id,
        'error' => $e->getMessage(),
      ]);
      return $e->getMessage();
    }
  }

  public function record($id)
  {
    return new EmpresaResource(Empresa::query()->findOrFail($id));
  }

  public function destroy($id)
  {
    try {
      // verificando si hay contactos asociados a esta empresa
      $hasContact = Contacto::query()->where('empresa_id', $id)->exists();
      if ($hasContact) {
        return [
          'success' => false,
          'message' => "La empresa tiene contactos asociados y no se puede eliminar."
        ];
      }

      Empresa::query()->where('id', $id)->update([
        'deleted_at' => now(),
        'updated_at' => now(),
        'estado' => 0,
      ]);

      return [
        'success' => true,
        'message' => 'Empresa eliminado con éxito'
      ];
    } catch (Exception $e) {
      Log::error("Error al eliminar la empresa: {$e->getMessage()}");
      return [
        'success' => false,
        'message' => "Error inesperado, no se pudo eliminar la empresa."
      ];
    }
  }

  public function searchEmpresas(Request $request)
  {
    $query = Empresa::query();

    if ($request->has('search') && !empty($request->search)) {
      $search = $request->search;
      // Buscar también por nombre_comercial (requerimiento del cliente):
      // los ejecutivos comerciales identifican las empresas por nombre
      // comercial más que por razón social.
      $query->where('estado', '=', 1)
        ->where(function ($q) use ($search) {
          $q->where('razon_social', 'LIKE', "%{$search}%")
            ->orWhere('nombre_comercial', 'LIKE', "%{$search}%")
            ->orWhere('ruc', 'LIKE', "%{$search}%");
        });
    }

    $empresas = $query->select('id', 'razon_social', 'ruc', 'nombre_comercial')
      ->orderBy('razon_social')
      ->paginate($request->per_page ?? 20);

    return response()->json($empresas);
  }


  public function show($id)
  {
    $cols = $this->empresaSelectColumns();

    // Si tu tabla usa soft delete y quieres que NO traiga “eliminados”, deja whereNull.
    // Si quieres poder editar registros “trashed”, quita whereNull('deleted_at').
    $row = DB::table('empresa')
      ->select($cols)
      ->where('id', $id)
      ->whereNull('deleted_at')
      ->first();

    if (!$row) {
      // Devuelve 404 controlado; si prefieres 200 con ok=false, dímelo y lo cambio
      return response()->json(['ok' => false, 'error' => 'Not found'], 404);
    }

    // Normalizaciones como en apiIndex
    $row->trabaja_con_proveedores = (int)($row->trabaja_con_proveedores ?? 0);
    if (!empty($row->aniversario)) {
      $row->aniversario = (string)\Carbon\Carbon::parse($row->aniversario)->format('Y-m-d');
    }

    return response()->json($row, 200);
  }

  // 👇 Endpoint usado por DataTables hijo: /comercial/api/empresas/{empresaId}/contactos
  public function contactosByEmpresa($empresaId)
  {
    try {
      // 1) Descubre tabla real de contactos
      $candidatos = [
        'contactos', 'contacto',
        'comercial_contactos', 'comercial_contacto',
        'contactos_cliente', 'clientes_contactos'
      ];
      $table = null;
      foreach ($candidatos as $t) {
        if (Schema::hasTable($t)) {
          $table = $t;
          break;
        }
      }
      if (!$table) {
        // como fallback: busca en information_schema una tabla que tenga empresa_id
        $posibles = DB::select("
                    SELECT TABLE_NAME
                    FROM INFORMATION_SCHEMA.COLUMNS
                    WHERE TABLE_SCHEMA = DATABASE()
                    AND COLUMN_NAME = 'empresa_id'
                ");
        foreach ($posibles as $p) {
          if (Schema::hasTable($p->TABLE_NAME)) {
            $table = $p->TABLE_NAME;
            break;
          }
        }
      }
      if (!$table) {
        return response()->json(['ok' => false, 'error' => 'No se encontró tabla de contactos'], 500);
      }

      // 2) Columnas existentes (evita 500 por nombres que no existen)
      $all = Schema::getColumnListing($table);
      $preferidas = [
        'id', 'empresa_id', 'nombres', 'apellidos', 'cargo',
        'telefono', 'celular', 'email', 'created_at', 'updated_at', 'deleted_at'
      ];
      $cols = array_values(array_intersect($preferidas, $all));
      if (!$cols) {
        $cols = ['*'];
      }

      // 3) Query segura + soft delete si existe
      $q = DB::table($table)->select($cols)
        ->where('empresa_id', $empresaId);
      if (in_array('deleted_at', $all, true)) {
        $q->whereNull('deleted_at');
      }

      // 4) Orden seguro
      $orderCol = in_array('id', $all, true) ? 'id' : (is_array($cols) ? $cols[0] : 'empresa_id');
      $rows = $q->orderByDesc($orderCol)->get();

      // 5) Nombre completo
      $rows->transform(function ($r) {
        if (isset($r->nombres) || isset($r->apellidos)) {
          $r->nombre_completo = trim(($r->nombres ?? '') . ' ' . ($r->apellidos ?? ''));
        }
        return $r;
      });

      // DataTables de tu vista acepta array plano o {data:[]}
      return response()->json($rows, 200);

    } catch (\Throwable $e) {
      // Devuelve el error en JSON para verlo en Network → Response
      return response()->json([
        'ok' => false,
        'error' => $e->getMessage(),
        'trace_class' => get_class($e)
      ], 500);
    }
  }

  public function options(Request $request)
  {
    try {
        $query = Empresa::query()
            ->whereNull('deleted_at')
            ->where('estado', 1); // Solo empresas activas

        // Filtro de búsqueda opcional (si se pasa parámetro 'q')
        if ($request->filled('q')) {
            $search = $request->input('q');
            $query->where(function ($q) use ($search) {
                $q->where('razon_social', 'like', "%{$search}%")
                  ->orWhere('ruc', 'like', "%{$search}%")
                  ->orWhere('nombre_comercial', 'like', "%{$search}%");
            });
        }

        // Obtener empresas ordenadas por razón social
        $empresas = $query
            ->orderBy('razon_social', 'asc')
            ->select('id', 'razon_social', 'ruc', 'nombre_comercial')
            ->get()
            ->map(function ($empresa) {
                return [
                    'id' => $empresa->id,
                    'razon_social' => $empresa->razon_social,
                    'ruc' => $empresa->ruc,
                    'nombre_comercial' => $empresa->nombre_comercial,
                ];
            });

        return response()->json([
            'ok' => true,
            'data' => $empresas,
        ]);

    } catch (\Exception $e) {
        \Log::error('Error en EmpresasController@options: ' . $e->getMessage());
        
        return response()->json([
            'ok' => false,
            'msg' => 'Error al cargar las empresas.',
            'error' => $e->getMessage(),
        ], 500);
    }
  }
}
