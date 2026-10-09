<?php

namespace Modules\Finance\Http\Resources;

use App\Models\Tenant\User;
use Illuminate\Http\Resources\Json\ResourceCollection;

class SmallCashCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Support\Collection
     */
    public function toArray($request)
    {
        return $this->collection->transform(function ($row, $key) {
            return [
              'id' => $row->id,
              'code' => $row->code,
              'user_id' => $row->user_id,
              'user' => [
                'id' => $row->user->id,
                'name' => $row->user->name,
                'type' => User::TYPE_USER[$row->user->type],
              ],
              'date_opening' => $row->date_opening->format('d-m-Y'),
              'date_closed' => $row->date_closed ? $row->date_closed->format('d-m-Y') : null,
              'beginning_balance' => $row->beginning_balance,
              'income' => $row->income,
              'expense' => $row->expense,
              'final_balance' => $row->final_balance,
              'state' => $row->state,
              'reference_number' => $row->reference_number,
              'description' => $row->description,
            ];
        });
    }

}
