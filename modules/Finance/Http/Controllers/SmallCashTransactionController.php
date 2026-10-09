<?php

namespace Modules\Finance\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Modules\Finance\Models\SmallCash;
use Modules\Finance\Models\SmallCashTransaction;
use Exception;

class SmallCashTransactionController extends Controller
{
  /**
   * Get transactions by small cash
   */
  public function records(Request $request)
  {
    $smallCashId = $request->small_cash_id;

    $transactions = SmallCashTransaction::with(['user', 'paymentMethodType'])
      ->where('small_cash_id', $smallCashId)
      ->when($request->type, function($query) use($request) {
        return $query->where('type', $request->type);
      })
      ->orderBy('date', 'desc')
      ->orderBy('id', 'desc')
      ->get();

    return response()->json([
      'success' => true,
      'data' => $transactions
    ]);
  }

  /**
   * Store a newly created resource in storage.
   */
  public function store(Request $request)
  {
    $this->validate($request, [
      //'small_cash_id' => 'required|exists:small_cash,id',
      'small_cash_id' => 'required',
      'date' => 'required|date',
      'type' => 'required|in:income,expense',
      'amount' => 'required|numeric|min:0.01',
      'description' => 'required|string',
    ]);

    $existSmallCash = SmallCash::query()->where('id', '=', $request->request->get('small_cash_id'))->exists();
    if (!$existSmallCash) {
      return response()->json([
        'success' => false,
        'message' => 'La caja chica no existe'
      ], 400);
    }

    try {
      DB::connection('tenant')->beginTransaction();

      $smallCash = SmallCash::findOrFail($request->small_cash_id);

      if ($smallCash->state === 'closed') {
        return response()->json([
          'success' => false,
          'message' => 'No se pueden registrar movimientos en una caja cerrada'
        ], 400);
      }

      // Validar que haya saldo suficiente para egresos
      if ($request->type === 'expense') {
        $currentBalance = $smallCash->calculateBalance();
        if ($request->amount > $currentBalance) {
          return response()->json([
            'success' => false,
            'message' => 'Saldo insuficiente en caja chica'
          ], 400);
        }
      }

      $data = $request->all();
      $data['user_id'] = auth()->id();

      $transaction = SmallCashTransaction::create($data);

      DB::connection('tenant')->commit();

      return response()->json([
        'success' => true,
        'message' => 'Movimiento registrado correctamente',
        'data' => $transaction->load(['user', 'paymentMethodType'])
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
   * Show the specified resource.
   */
  public function show($id)
  {
    $transaction = SmallCashTransaction::with(['user', 'paymentMethodType', 'smallCash'])
      ->findOrFail($id);

    return response()->json([
      'success' => true,
      'data' => $transaction
    ]);
  }
  public function update(Request $request, $id)
  {
    $this->validate($request, [
      'date' => 'required|date',
      'type' => 'required|in:income,expense',
      'amount' => 'required|numeric|min:0.01',
      'description' => 'required|string',
    ]);

    try {
      DB::connection('tenant')->beginTransaction();

      $transaction = SmallCashTransaction::findOrFail($id);
      $smallCash = $transaction->smallCash;

      if ($smallCash->state === 'closed') {
        return response()->json([
          'success' => false,
          'message' => 'No se pueden editar movimientos de una caja cerrada'
        ], 400);
      }

      // Validar saldo si es egreso y cambió el monto
      if ($request->type === 'expense') {
        $smallCash->updateTotals();
        $currentBalance = $smallCash->calculateBalance();
        $difference = $request->amount - $transaction->amount;

        if ($difference > 0 && $difference > $currentBalance) {
          return response()->json([
            'success' => false,
            'message' => 'Saldo insuficiente en caja chica'
          ], 400);
        }
      }

      $transaction->update($request->all());

      DB::connection('tenant')->commit();

      return response()->json([
        'success' => true,
        'message' => 'Movimiento actualizado correctamente',
        'data' => $transaction->load(['user', 'paymentMethodType'])
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
   * Remove the specified resource from storage.
   */
  public function destroy($id)
  {
    try {
      DB::connection('tenant')->beginTransaction();

      $transaction = SmallCashTransaction::findOrFail($id);
      $smallCash = $transaction->smallCash;

      if ($smallCash->state === 'closed') {
        return response()->json([
          'success' => false,
          'message' => 'No se pueden eliminar movimientos de una caja cerrada'
        ], 400);
      }

      $transaction->delete();

      DB::connection('tenant')->commit();

      return response()->json([
        'success' => true,
        'message' => 'Movimiento eliminado correctamente'
      ]);
    } catch (Exception $e) {
      DB::connection('tenant')->rollBack();

      return response()->json([
        'success' => false,
        'message' => $e->getMessage()
      ], 500);
    }
  }
}
