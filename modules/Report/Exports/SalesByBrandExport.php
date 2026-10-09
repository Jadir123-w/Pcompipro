<?php

namespace Modules\Report\Exports;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\Exportable;

class SalesByBrandExport implements FromView, ShouldAutoSize
{
    use Exportable;

    public function records($records) {
        $this->records = $records;
        return $this;
    }

    public function company($company) {
        $this->company = $company;
        return $this;
    }

    public function establishment($establishment) {
        $this->establishment = $establishment;
        return $this;
    }
    public function dateStart($date_start) {
        $this->date_start = $date_start;
        return $this;
    }

    public function dateEnd($date_end) {
        $this->date_end = $date_end;
        return $this;
    }

    public function view(): View {
        return view('report::sales_by_brand.report_excel', [
            'records' => $this->records,
            'company' => $this->company,
            'establishment' => $this->establishment,
            'date_start' => $this->date_start,
            'date_end' => $this->date_end
        ]);
    }
}