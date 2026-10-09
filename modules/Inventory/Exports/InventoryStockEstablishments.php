<?php
namespace Modules\Inventory\Exports;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromView;

class InventoryStockEstablishments implements FromView 
{

    use Exportable;

    public function view(): View
    {
        return view('inventory::inventory.stock_establishments', [
            'records' => $this->records
        ]);
    }

    public function records($record)
    {
        $this->records = $record;
        return $this;
    }
}