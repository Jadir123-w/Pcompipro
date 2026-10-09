<?php

namespace Modules\Finance\Http\Controllers;

use App\Models\Tenant\Company;
use App\Models\Tenant\Person;
use App\Models\Tenant\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Finance\Http\Resources\SmallCashCollection;
use Modules\Finance\Models\SmallCash;
use Modules\Finance\Models\SmallCashTransaction;
use App\Models\Tenant\PaymentMethodType;
use Carbon\Carbon;
use Exception;
use Barryvdh\DomPDF\Facade as PDF;
use Maatwebsite\Excel\Facades\Excel;

class SmallCashController extends Controller
{
  /**
   * Display a listing of the resource.
   */
  public function index()
  {
    $type_user = auth()->user()->type;
    return view('finance::small_cash.index', compact('type_user'));
  }

  /**
   * Get records for datatable
   */
  public function records(Request $request)
  {
    $records = SmallCash::with(['user'])
//      ->when($request->user_id, function($query) use($request) {
//        return $query->where('user_id', $request->user_id);
//      })
//      ->when($request->state, function($query) use($request) {
//        return $query->where('state', $request->state);
//      })
      ->orderBy('id', 'desc');

    if ($request->get('status')) {
      $records->where('state', $request->get('status'));
    }

    if ($request->get('user_id')) {
      $records->where('user_id', $request->get('user_id'));
    }

    if ($request->get('column') && $request->get('value')) {
      $column = $request->get('column');
      $value = $request->get('value');

      if ($column === 'reference_number') {
        $records->whereRaw("MATCH($column) AGAINST (? IN BOOLEAN MODE)", [$value. '*']);
      } elseif ($column === 'date_opening') {
        $records->whereDate($column, $value);
      }
    }

    return new SmallCashCollection($records->paginate(config('tenant.items_per_page')));
  }

  /**
   * Get users list
   */
  public function users()
  {
    /*$users = User::query()->whereIn('type', ['admin', 'integrator'])
      ->select('id', 'name', 'email')
      ->orderBy('name')
      ->get();*/

    $user = auth()->user();
    $type = $user->type;

    switch($type) {
      case 'admin':
        //$users = User::where('type', 'seller')->get();
        $users = User::query()->whereKeyNot($user->id)
          ->select('id', 'name', 'email')
          ->orderBy('name')
          ->get();
        $users->push($user);
        break;
      default:
        $users = User::query()->where('id', $user->id)
          ->select('id', 'name', 'email')
          ->orderBy('name')
          ->get();
        break;
    }

    return response()->json([
      'success' => true,
      'data' => $users
    ]);
  }

  /**
   * Store a newly created resource in storage.
   */
  public function store(Request $request)
  {
    $this->validate($request, [
      'date_opening' => 'required|date',
      'beginning_balance' => 'required|numeric|min:0',
    ]);

    $data = $request->all();
    try {
      $result = DB::connection('tenant')->transaction(function () use ($data) {
        $data['user_id'] = auth()->id();
        $data['code'] = SmallCash::generateCode();
        $data['state'] = 'open';
        $data['final_balance'] = $data['beginning_balance'];

        return SmallCash::create($data);
      });

      return response()->json([
        'success' => true,
        'message' => 'Caja chica aperturada correctamente',
        'data' => [
          'id' => $result->id,
        ]
      ]);
    } catch (Exception $e) {
      return response()->json([
        'success' => false,
        'message' => $e->getMessage()
      ], 500);
    }
  }

  /**
   * Show the specified resource.
   */
  public function show($id)
  {
    $smallCash = SmallCash::with(['user', 'transactions.user', 'transactions.paymentMethodType'])
      ->findOrFail($id);

    return response()->json([
      'success' => true,
      'data' => $smallCash
    ]);
  }

  /**
   * Update the specified resource in storage.
   */
  public function update(Request $request, $id)
  {
    $this->validate($request, [
      'description' => 'nullable|string',
    ]);

    try {
      $smallCash = SmallCash::findOrFail($id);

      if ($smallCash->state === 'closed') {
        return response()->json([
          'success' => false,
          'message' => 'No se puede editar una caja cerrada'
        ], 400);
      }

      $smallCash->update($request->only(['description', 'reference_number']));

      return response()->json([
        'success' => true,
        'message' => 'Caja chica actualizada correctamente',
        'data' => $smallCash->load('user')
      ]);
    } catch (Exception $e) {
      return response()->json([
        'success' => false,
        'message' => $e->getMessage()
      ], 500);
    }
  }

  /**
   * Close small cash
   */
  public function close(Request $request, $id)
  {
    try {
      DB::connection('tenant')->beginTransaction();

      $smallCash = SmallCash::findOrFail($id);

      if ($smallCash->state === 'closed') {
        return response()->json([
          'success' => false,
          'message' => 'La caja ya está cerrada'
        ], 400);
      }

      $smallCash->update([
        'state' => 'closed',
        'date_closed' => Carbon::now()->format('Y-m-d')
      ]);

      DB::connection('tenant')->commit();

      return response()->json([
        'success' => true,
        'message' => 'Caja chica cerrada correctamente',
        'data' => $smallCash->load('user')
      ]);
    } catch (Exception $e) {
      DB::connection('tenant')->rollBack();

      return response()->json([
        'success' => false,
        'message' => $e->getMessage()
      ], 500);
    }
  }

  /**
   * Get open small cash for current user
   */
  public function getOpenSmallCash()
  {
    $smallCash = SmallCash::where('user_id', auth()->id())
      ->where('state', 'open')
      ->with(['user', 'transactions'])
      ->first();

    return response()->json([
      'success' => true,
      'data' => $smallCash
    ]);
  }

  /**
   * Get payment methods
   */
  public function getPaymentMethods()
  {
    $paymentMethods = PaymentMethodType::all();

    return response()->json([
      'success' => true,
      'data' => $paymentMethods
    ]);
  }

  /**
   * Proveedores del maestro, para el buscador del formulario de egresos.
   * El campo es opcional: los gastos de movilidad, taxis, pasajes o ferias se
   * registran sin proveedor.
   */
  public function suppliers()
  {
    $suppliers = Person::where('type', 'suppliers')
      ->orderBy('name')
      ->get()
      ->transform(function ($row) {
        return [
          'id'          => $row->id,
          'name'        => $row->name,
          'number'      => $row->number,
          // Se muestra "RUC - Razón social" para poder buscar por cualquiera.
          'description' => trim($row->number . ' - ' . $row->name, ' -'),
        ];
      });

    return response()->json([
      'success' => true,
      'data' => $suppliers
    ]);
  }

  /**
   * Proyectos activos, para sugerirlos en el campo Proyecto del egreso.
   *
   * El campo admite además texto libre (por ejemplo "Feria Compipro") o quedar
   * vacío, por lo que esta lista es solo una ayuda de selección.
   *
   * Los proyectos son del módulo Comercial; si ese módulo no estuviera
   * disponible, se devuelve una lista vacía y el campo sigue funcionando como
   * texto libre.
   */
  public function proyectos()
  {
    $proyectos = [];

    try {
      if (class_exists('\Modules\Comercial\Models\Proyecto')) {
        $proyectos = \Modules\Comercial\Models\Proyecto::where('estado', 'activo')
          ->orderBy('nombre')
          ->get()
          ->transform(function ($row) {
            return [
              'id'     => $row->id,
              'codigo' => $row->codigo,
              'nombre' => $row->nombre,
            ];
          });
      }
    } catch (Exception $e) {
      Log::warning('Caja Chica: no se pudieron listar los proyectos: ' . $e->getMessage());
      $proyectos = [];
    }

    return response()->json([
      'success' => true,
      'data' => $proyectos
    ]);
  }

  public function columns()
  {
    return [
      'date_opening' => 'Fecha Apertura',
      'reference_number' => 'Numero de Referencia'
    ];
  }

  /**
   * Export small cash to Excel
   */
  public function exportExcel($id)
  {
    try {
      $smallCash = SmallCash::with(['user', 'transactions.user', 'transactions.paymentMethodType'])
        ->findOrFail($id);

      return Excel::download(
        new \Modules\Finance\Exports\SmallCashTransactionsExport($smallCash),
        'caja-chica-' . $smallCash->code . '-' . date('Y-m-d') . '.xlsx'
      );
    } catch (Exception $e) {
      return response()->json([
        'success' => false,
        'message' => $e->getMessage()
      ], 500);
    }
  }

  /**
   * Export small cash to PDF
   */
  public function exportPDF($id)
  {
    try {
      $smallCash = SmallCash::with(['user', 'transactions.user', 'transactions.paymentMethodType'])
        ->findOrFail($id);

      $company = Company::active();
      $transactions = $smallCash->transactions;

      // La hoja va apaisada: la rendición tiene 10 columnas y en vertical se
      // parten los importes. DomPDF ignora el "size: landscape" del CSS, por lo
      // que la orientación se fija acá.
      $pdf = PDF::loadView('finance::small_cash.report_pdf', compact('smallCash', 'company', 'transactions'))
        ->setPaper('a4', 'landscape');

      $filename = 'caja-chica-' . $smallCash->code . '-' . date('Y-m-d') . '.pdf';

      return $pdf->stream($filename);
    } catch (Exception $e) {
      return response()->json([
        'success' => false,
        'message' => $e->getMessage()
      ], 500);
    }
  }

  /**
   * Export all small cash list to Excel
   */
  public function exportListExcel(Request $request)
  {
    try {
      $records = SmallCash::with(['user'])
        ->when($request->user_id, function($query) use($request) {
          return $query->where('user_id', $request->user_id);
        })
        ->when($request->state, function($query) use($request) {
          return $query->where('state', $request->state);
        })
        ->orderBy('id', 'desc')
        ->get();

      return Excel::download(
        new \Modules\Finance\Exports\SmallCashExport($records),
        'listado-cajas-chicas-' . date('Y-m-d') . '.xlsx'
      );
    } catch (Exception $e) {
      return response()->json([
        'success' => false,
        'message' => $e->getMessage()
      ], 500);
    }
  }

}
