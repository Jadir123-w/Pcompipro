<?php

namespace Modules\Report\Http\Controllers;

use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade as PDF;
use App\Http\Controllers\Controller;
use App\Models\Tenant\DocumentItem;
use App\Models\Tenant\SaleNoteItem;
use App\Models\Tenant\Company;
use App\Models\Tenant\Establishment;
use Carbon\Carbon;
use Modules\Report\Http\Resources\ReportSalesByBrandCollection;
use Modules\Report\Exports\SalesByBrandExport;
use App\CoreFacturalo\Helpers\Template\ReportHelper;
use Modules\Item\Models\Brand;

class ReportSalesByBrandController extends Controller
{
    public function index()
    {
        return view('report::sales_by_brand.index');
    }

    public function filter(Request $request)
    {
        // Lógica para filtros
        $brands = $this->getBrandsFromItems();
        return compact('brands');
    }

    public function records(Request $request)
    {
        // Lógica para retornar los registros filtrados por marca
        $records = $this->getRecordsByBrand($request->all());
        if ($records->isEmpty()) {
            return new ReportSalesByBrandCollection(collect([]));
        }
        return new ReportSalesByBrandCollection($records);
    }

    public function excel(Request $request)
    {
        $company = Company::first();
        $establishment = ($request->establishment_id) ? Establishment::findOrFail($request->establishment_id) : auth()->user()->establishment;

        // Aplica la lógica de fechas igual que en el método pdf
        $input = $request->all();
        $date_start = null;
        $date_end = null;
        if (isset($input['period'])) {
            if ($input['period'] === 'month' && isset($input['month_start'])) {
                $date_start = Carbon::parse($input['month_start'].'-01')->startOfMonth()->toDateString();
                $date_end = Carbon::parse($input['month_start'].'-01')->endOfMonth()->toDateString();
            } elseif ($input['period'] === 'between_months' && isset($input['month_start']) && isset($input['month_end'])) {
                $date_start = Carbon::parse($input['month_start'].'-01')->startOfMonth()->toDateString();
                $date_end = Carbon::parse($input['month_end'].'-01')->endOfMonth()->toDateString();
            } elseif ($input['period'] === 'date' && isset($input['date_start'])) {
                $date_start = $input['date_start'];
                $date_end = $input['date_start'];
            } elseif ($input['period'] === 'between_dates' && isset($input['date_start']) && isset($input['date_end'])) {
                $date_start = $input['date_start'];
                $date_end = $input['date_end'];
            }
        }
        
        if (!$date_start || !$date_end) {
            $date_start = $input['date_start'] ?? null;
            $date_end = $input['date_end'] ?? null;
        }

        $records = $this->getRecordsByBrand($input);

        // Si quieres previsualizar en el navegador como HTML:
        if ($request->has('preview')) {
            return view('report::sales_by_brand.report_excel', compact("records", "company", "establishment", "date_start", "date_end"));
        }

        // Si quieres descargar como Excel:
        $filename = 'Reporte_Ventas_Por_Marca_'.Carbon::now()->format('Ymd_His').'.xlsx';

        return (new SalesByBrandExport)
            ->records($records)
            ->company($company)
            ->establishment($establishment)
            ->dateStart($date_start)
            ->dateEnd($date_end)
            ->download($filename);
    }

    public function pdf(Request $request)
    {
        $company = Company::first();
        $establishment = ($request->establishment_id) ? Establishment::findOrFail($request->establishment_id) : auth()->user()->establishment;

        // --- AÑADE ESTA LÓGICA ---
        $input = $request->all();
        if (isset($input['period'])) {
            if ($input['period'] === 'month' && isset($input['month_start'])) {
                $date_start = Carbon::parse($input['month_start'].'-01')->startOfMonth()->toDateString();
                $date_end = Carbon::parse($input['month_start'].'-01')->endOfMonth()->toDateString();
            } elseif ($input['period'] === 'between_months' && isset($input['month_start']) && isset($input['month_end'])) {
                $date_start = Carbon::parse($input['month_start'].'-01')->startOfMonth()->toDateString();
                $date_end = Carbon::parse($input['month_end'].'-01')->endOfMonth()->toDateString();
            } elseif ($input['period'] === 'date' && isset($input['date_start'])) {
                $date_start = $input['date_start'];
                $date_end = $input['date_start'];
            } elseif ($input['period'] === 'between_dates' && isset($input['date_start']) && isset($input['date_end'])) {
                $date_start = $input['date_start'];
                $date_end = $input['date_end'];
            }
        }
        if (!$date_start || !$date_end) {
            $date_start = $input['date_start'] ?? null;
            $date_end = $input['date_end'] ?? null;
        }
        
        // ------------------------

        $records = $this->getRecordsByBrand($input);

        $pdf = PDF::loadView(
            'report::sales_by_brand.report_pdf',
            compact("records", "company", "establishment", "date_start", "date_end")
        )->setPaper('a4', 'landscape');
        $filename = 'Reporte_Ventas_Por_Marca_'.Carbon::now();
        return $pdf->download($filename.'.pdf');
    }

    private function getBrandsFromItems()
    {
        // Intenta obtener desde la tabla brands
        return Brand::orderBy('name')
        ->get()
        ->map(function($brand) {
            return [
                'id' => $brand->id,
                'name' => $brand->name
            ];
        })
        ->values();
    }

    private function getRecordsByBrand($request)
    {
        $date_start = null;
        $date_end = null;
        if (isset($request['period'])) {
            if ($request['period'] === 'month' && isset($request['month_start'])) {
                $date_start = Carbon::parse($request['month_start'].'-01')->startOfMonth()->toDateString();
                $date_end = Carbon::parse($request['month_start'].'-01')->endOfMonth()->toDateString();
            } elseif ($request['period'] === 'between_months' && isset($request['month_start']) && isset($request['month_end'])) {
                $date_start = Carbon::parse($request['month_start'].'-01')->startOfMonth()->toDateString();
                $date_end = Carbon::parse($request['month_end'].'-01')->endOfMonth()->toDateString();
            } elseif ($request['period'] === 'date' && isset($request['date_start'])) {
                $date_start = $request['date_start'];
                $date_end = $request['date_start']; // Mismo día
            } elseif ($request['period'] === 'between_dates' && isset($request['date_start']) && isset($request['date_end'])) {
                $date_start = $request['date_start'];
                $date_end = $request['date_end'];
            }
        }

        if (!$date_start || !$date_end) {
            $date_start = $request['date_start'] ?? null;
            $date_end = $request['date_end'] ?? null;
        }

        if (!$date_start || !$date_end) {
            return collect([]);
        }
        
        $brand_id = $request['brand_id'] ?? null;
        // Documentos
        $document_items = DocumentItem::whereHas('document', function($q) use ($date_start, $date_end) {
                $q->whereBetween('date_of_issue', [$date_start, $date_end])
                ->whereIn('document_type_id', ['01','03'])
                ->whereStateTypeAccepted();
            })
            ->whereHas('relation_item.brand', function($q) use ($brand_id) {
                if ($brand_id) {
                    $q->where('id', $brand_id);
                }
            })
            ->with('relation_item.brand')
            ->get();

        // Sale Notes
        $sale_note_items = SaleNoteItem::whereHas('sale_note', function($q) use ($date_start, $date_end) {
                $q->whereBetween('date_of_issue', [$date_start, $date_end])
                ->whereStateTypeAccepted()
                ->whereNotChanged();
            })
            ->whereHas('relation_item.brand', function($q) use ($brand_id) {
                if ($brand_id) {
                    $q->where('id', $brand_id);
                }
            })
            ->with('relation_item.brand')
            ->get();

        // Unimos ambos
        $all_items = $document_items->concat($sale_note_items);

        if ($all_items->isEmpty()) {
            return collect([]);
        }

        // Agrupamos solo los items que realmente existen en el rango de fechas y marca
        $grouped = $all_items->groupBy(function($item) {
            return optional($item->relation_item->brand)->id;
        });

        $records = $grouped->map(function($items, $brand_id) {
            $quantity_sold = 0;
            $total_profit = 0;
            $brand_name = '';
            $total_sale_value = 0;

            foreach ($items as $item) {
                $helper_values = ReportHelper::getValuesReportCommissionDetail($item);
                $quantity_sold += $helper_values->quantity;
                $total_profit += $helper_values->overall_profit;

                if (isset($item->total_value)) {
                    $total_sale_value += $item->total_value;
                } else {
                    // Si solo tienes el total con IGV:
                    $total = $item->total ?? 0;
                    $total_sale_value += $total / 1.18;
                }

                if (isset($item->relation_item->brand) && $item->relation_item->brand) {
                    $brand_name = $item->relation_item->brand->name;
                }
            }

            return [
                'id' => $brand_id,
                'brand_name' => $brand_name,
                'quantity_sold' => $quantity_sold,
                'total_sale_value' => $total_sale_value,
                'total_profit' => $total_profit,
            ];
        })
        // Solo marcas con ventas en el periodo
        ->filter(function($row) {
            return $row['quantity_sold'] > 0;
        })
        ->values();

        return $records;
    }
}