<?php

namespace App\Http\Controllers\Tenant\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Tenant\ConsignedCollection;
use App\Models\Tenant\Consigned;
use App\Models\Tenant\PersonAddress;
use Exception;
use Illuminate\Http\Request;


class ConsignedController extends Controller
{
    public function tables()
    {
        $identity_document_types = func_get_identity_document_types();
        $countries = func_get_countries();
        $locations = func_get_locations();

        return compact('identity_document_types','countries','locations');
    }

    public function records(Request $request)
    {
        $records = Consigned::all();

        return new ConsignedCollection($records->paginate(config('tenant.items_per_page')));
    }

    public function record($id)
    {
        $record = Consigned::findOrFail($id);

        return $record;
    }

    public function store(Request $request)
    {
        $id = $request->input('id');
        $consigned = Consigned::firstOrNew(['id' => $id]);
        $consigned->fill($request->all());
        $consigned->save();

        return [
            'id' => $consigned->id,
            'success' => true,
            'message' => ($id)?'Consignado editado con éxito':'Consignado registrado con éxito',
        ];
    }

    public function data()
    {
        $consigneds = Consigned::query()->get();

        return response()->json([
            'success' => true,
            'data' => $consigneds,
        ], 200);
    }

    public function searchByCustomer($id)
    {
        $consigned_ids = PersonAddress::where('person_id', $id)
            ->whereNotNull('consigned_id')
            ->pluck('consigned_id')
            ->unique();

        $consigneds = Consigned::whereIn('id', $consigned_ids)
            ->get()
            ->unique('id')
            ->transform(function ($row) {
                return [
                    'id' => $row->id,
                    'description' => $row->number . ' - ' . $row->name,
                    'name' => $row->name,
                    'number' => $row->number,
                    'identity_document_type_id' => $row->identity_document_type_id
                ];
            });

        return response()->json([
            'success' => true,
            'consigneds' => $consigneds,
        ], 200);
    }

    public function consignedAddresses(Request $request)
    {
        $consigned_id = $request->query('consigned_id');
        $person_id = $request->query('person_id');
        $consigned_addresses = PersonAddress::where('consigned_id', $consigned_id)
            ->where('person_id', $person_id)
            ->get()
            ->transform(function ($row) {
                return [
                    'id' => $row->id,
                    'district_id' => $row->district_id,
                    'address' => $row->address
                ];
            });

        return response()->json([
            'success' => true,
            'consigned_addresses' => $consigned_addresses,
        ], 200);
    }

    public function storeDocument(Request $request)
    {
        
        $consignedFound = Consigned::where('number',$request->number)->first();
        $location_id = $request->input('location_id');
        if (!(is_array($location_id) && count($location_id) === 3)) {
            return [
                'success' => false,
                'message' => 'Debe ingresar ubigeo',
            ];
        }
        $addressFound = PersonAddress::where('district_id', $location_id[2])
            ->where('person_id', $request->person_id)
            ->where('address', $request->address)->first();

        if($addressFound){
            return [
                'success' => false,
                'message' => 'Dirección ya existe',
            ];
        }

        $consigned = null;
        if (is_null($consignedFound)) {
            $consigned = new Consigned();
            $consigned->fill($request->only([
            'identity_document_type_id',
            'number',
            'name',
            'telephone',
            ]));
            $consigned->save();
        }

        $person_address = new PersonAddress();
        $person_address->person_id = $request->person_id;
        $person_address->country_id = $request->country_id;
        $person_address->department_id = $location_id[0];
        $person_address->province_id = $location_id[1];
        $person_address->district_id = $location_id[2];
        $person_address->address = $request->address;
        $person_address->phone = $request->telephone;
        $person_address->main = 0;
        $person_address->establishment_code = $request->establishment_code;
        $person_address->has_consigned = 1 ; 
        $person_address->consigned_id = $consignedFound ? $consignedFound->id : $consigned->id;
        $person_address->save();

        return [
            'id' => $consignedFound ? $consignedFound->id : $consigned->id,
            'success' => true,
            'message' => 'Consignado registrado con éxito',
        ];
    }

}
