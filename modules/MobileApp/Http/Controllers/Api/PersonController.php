<?php

namespace Modules\MobileApp\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use App\Models\Tenant\Person;
use Modules\MobileApp\Http\Resources\Api\{
	PersonCollection,
	PersonResource,
};
use App\Http\Controllers\Tenant\PersonController as PersonControllerWeb;
use App\Http\Requests\Tenant\PersonRequest;
use App\Models\Tenant\Consigned;

class PersonController extends Controller
{

	
    /**
     * 
     * Obtener registros paginados
     *
     * @param  Request $request
     * @return array
     */
	public function records($type, Request $request)
	{
        $records = Person::whereFilterRecordsApi($request->input, $type);

		return new PersonCollection($records->paginate(config('tenant.items_per_page')));
	}

	
    /**
     * obtener registro
     *
     * @param  int $id
     * @return PersonResource
     * 
     */
    public function record($id)
    {
        return new PersonResource(Person::findOrFail($id));
    }
    
    
    /**
     * 
     * Actualizar registro
     *
     * @param  PersonRequest $request
     * @return array
     */
    public function update(PersonRequest $request)
    {

        $record = Person::findOrFail($request->id);
        $record->fill($request->only('identity_document_type_id', 'number', 'name', 'trade_name', 'address', 'telephone', 'email'));

        $location_id = $request->input('location_id');
        if (is_array($location_id) && count($location_id) === 3) {
            $record->district_id = $location_id[2];
            $record->province_id = $location_id[1];
            $record->department_id = $location_id[0];
        }
        $addresses = $request->input('addresses');
        $record->addresses()->delete();
        if (isset($addresses)) {
            foreach ($addresses as $row) {
                if (is_array($row["location_id"]) && count($row["location_id"]) === 3 && $row["address"]) {
                    $row["district_id"] = $row["location_id"][2];
                    $row["province_id"] = $row["location_id"][1];
                    $row["department_id"] = $row["location_id"][0];
                } else {
                    continue;
                }
                if($row['has_consigned']){
                    if($row['consigned_id']){
                        $consigned = Consigned::where('id', $row['consigned_id'])->first();
                        $consigned->telephone = $row["phone"];
                        $consigned->save();
                    }
                }else{
                    $row['consigned_id'] = null;
                }
                $record->addresses()->updateOrCreate(['id' => $row['id']], $row);
            }
        }
        $record->update();

        return [
            'success' => true,
            'message' => $record->getTitlePersonDescription().' actualizado',
        ];

    }


    /**
     * 
     * Obtener cliente por defecto configurado en establecimiento o clientes varios
     *
     * Usado en:
     * App
     * 
     * @return array
     */
    public function getDefaultCustomer($document_type_id = null)
    {
        $customer = null;

        // se retorna clientes varios por defecto para modo pos
        if(in_array($document_type_id, ['03', '80'], true))
        {
            $customer = Person::whereFilterVariousClients()->first();
        }
        else
        {
            $establishment = auth()->user()->establishment;
            if($establishment->customer_id) $customer = Person::findOrFail($establishment->customer_id);
        }

        if($customer)
        {
            return [
                'success' => true,
                'data' => $customer->getApiRowResource() 
            ];
        }

		return [
			'success' => false,
			'data' => null     
		];
    }


    /**
     * 
     * Activar/Desactivar registro
     *
     * @param  int $id
     * @param  bool $enabled
     * @return array
     */
    public function changeEnabled($id, $enabled)
    {
        $record = Person::findOrFail($id);
        $record->enabled = $enabled;
        $record->save();
		$type = $record->getTitlePersonDescription();
        
        return [
            'success' => true,
            'message' => $enabled ? $type.' habilitado con éxito' : $type.' inhabilitado con éxito'
        ];
    }

	
    /**
     * 
     * Eliminar registro, usa método del proceso por web
     *
     * @param  int $id
     * @return array
     */
    public function destroy($id)
    {
        return app(PersonControllerWeb::class)->destroy($id);
    }

}
