<?php

namespace Modules\BusinessTurn\Models;
 
use App\Models\Tenant\ModelTenant;
use Illuminate\Support\Facades\DB;

class BusinessTurn extends ModelTenant
{
    protected $fillable = [ 
        'value',
        'name',
        'active', 
    ];
  
    public static function configurationTaps()
    {
        return collect(DB::connection('tenant')->table('configuration_taps')->get())->except(['id', 'created_at', 'updated_at'])->transform(function($row) {
            return [
                'save_plates_client' => (bool)$row->save_plates_client,
            ];
        });
    }

}