<?php

namespace Modules\Sale\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Modules\Sale\Http\Resources\PendingAccountCommissionCollection;
use Modules\Sale\Http\Resources\PendingAccountCommissionResource;
use Modules\Sale\Http\Requests\PendingAccountCommissionRequest;
use Modules\Sale\Models\PendingAccountCommission;
use App\Models\Tenant\User;

class PendingAccountCommissionController extends Controller
{
    public function index()
    {
        return view('sale::pending-accounts.index');
    }

    public function columns()
    {
        return [
            'id' => 'Número',
        ];
    }

    public function records(Request $request)
    {
        $records = $this->getRecords($request);

        return new PendingAccountCommissionCollection($records->paginate(config('tenant.items_per_page')));
    }

    private function getRecords($request)
    {
        if ($request->column == 'seller') {
            $records = PendingAccountCommission::whereHas('seller', function($query) use($request){
                $query->where('name', 'like', "%{$request->value}%");
            });
        } else {
            $records = PendingAccountCommission::where($request->column, 'like', "%{$request->value}%");
        }

        return $records->whereTypeSeller()->latest();
    }

    public function tables()
    {
        $users = User::get(['id', 'name']);
        return compact('users');
    }

    public function record($id)
    {
        $record = new PendingAccountCommissionResource(PendingAccountCommission::findOrFail($id));
        return $record;
    }

    public function store(PendingAccountCommissionRequest $request)
    {
        $id = $request->input('id');
        $commission = PendingAccountCommission::firstOrNew(['id' => $id]);
        $commission->fill($request->all());
        $commission->save();

        return [
            'success' => true,
            'message' => ($id) ? 'Comisión editada con éxito' : 'Comisión registrada con éxito'
        ];
    }

    public function destroy($id)
    {
        $record = PendingAccountCommission::findOrFail($id);
        $record->delete();

        return [
            'success' => true,
            'message' => 'Comisión eliminada con éxito'
        ];
    }
}