<?php

namespace App\Http\Resources\Tenant;

use App\Models\Tenant\Cash;
use App\Models\Tenant\PaymentMethodType;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Modules\Finance\Models\Income;
use Modules\Pos\Http\Controllers\CashController;

class CashCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return mixed
     */
    public function toArray($request)
    {
        return $this->collection->transform(function($row, $key) {
            $id_income = $row->user_id;
            $incomes = Income::query()->where('user_id', $id_income)->whereTypeUser();

            $income_total=0;
            if($row->date_closed){
                $incomes=$incomes->whereBetween('date_of_issue',[$row->date_opening,$row->date_closed]);
                $incomes=$incomes->whereBetween('time_of_issue',[$row->time_opening,$row->time_closed]);
                $incomes=$incomes->get();

                if (isset($incomes[0])) {
                    foreach ($incomes as $income) {
                        $income_total += app(CashController::class)->getIncomeEgressCashDestination($income->payments);

                    }
                }
            }


          $cash = Cash::query()->findOrFail($row->id);
          $data_payments = collect();

          foreach ($cash->cash_documents as $cash_document) {
            $model_associated = $cash_document->getDataModelAssociated();
            $payments = $model_associated->getCashPayments();

            $payments->each(function($payment) use($data_payments){
              $data_payments->push($payment);
            });
          }
          Log::info($data_payments);
          $total_egress = $data_payments->where('type_transaction', 'egress')->sum('payment');
          $total_ingress = $data_payments->where('type_transaction', 'income')->sum('payment');
          $total_total = bcadd($row->beginning_balance, $total_ingress, 2);
          $saldo_actual = bcsub($total_total, $total_egress, 2);


            return [
                'id' => $row->id,
                'user_id' => $row->user_id,
                'user' => $row->user->name,
                'date_opening' => $row->date_opening,
                'time_opening' => $row->time_opening,
                'opening' => "{$row->date_opening} {$row->time_opening}",
                'date_closed' => $row->date_closed,
                'time_closed' => $row->time_closed,
                'closed' => "{$row->date_closed} {$row->time_closed}",
                'beginning_balance' => number_format($row->beginning_balance, 2),
                'saldo_actual' => number_format($saldo_actual, 2),
                'final_balance' => number_format($row->final_balance + $income_total, 2),
                'income' => $row->income,
                'expense' => $row->expense,
                'filename' => $row->filename,
                'state' => (bool) $row->state,
                'state_description' => ($row->state) ? 'Aperturada':'Cerrada',
                'reference_number' => $row->reference_number,
                'type' => ucfirst($row->type),
                'total_ingreso' => number_format($total_ingress, 2),

            ];
        });
    }
}
