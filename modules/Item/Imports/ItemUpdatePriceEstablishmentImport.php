<?php

namespace Modules\Item\Imports;

use App\Models\Tenant\Item;
use App\Models\Tenant\Warehouse;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\ToCollection;
use Modules\Item\Models\Category;
use Modules\Item\Models\Brand;
use App\Models\Tenant\ItemUnitType;
use App\Models\Tenant\ItemWarehousePrice;


class ItemUpdatePriceEstablishmentImport implements ToCollection
{
    use Importable;

    protected $data;

    public function collection(Collection $rows)
    {
            $total = count($rows);
            $registered = 0;
            unset($rows[0]);
            $records = Warehouse::all();

            foreach ($rows as $row)
            {

                $internal_id = $row[0] ?? null;
                $item = null;

                if($internal_id) $item = Item::whereFilterUpdatePrices($internal_id)->first();

                
                if($item) 
                {
                    foreach ($records as $record){

                        $item_warehouse_price = ItemWarehousePrice::where('item_id',$item->id)->where('warehouse_id',$record->id)->first();

                        $price = $row[$record->id] ?? null;

                        if (!$item_warehouse_price) {
                            if ($price) {
                                ItemWarehousePrice::create([
                                    'item_id' => $item->id,
                                    'warehouse_id' => $record->id,
                                    'price' => $price,
                                ]);
                            }
                        } else {
                            if ($price !== null) {
                                $item_warehouse_price->price = $price;
                                $item_warehouse_price->save();
                            }
                        }
                    }
                    
                    $registered += 1;
                } 

            }

            $this->data = compact('total', 'registered');

    }

    public function getData()
    {
        return $this->data;
    }
}
