<?php

namespace Modules\Report\Http\Controllers;

use App\Http\Controllers\Controller;
use Barryvdh\DomPDF\Facade as PDF;
use Modules\Report\Exports\PendingAccountCommissionExport;
use Illuminate\Http\Request;
use App\Models\Tenant\Establishment;
use App\Models\Tenant\User;
use App\Models\Tenant\Company;
use Carbon\Carbon;
use Modules\Report\Http\Resources\ReportPendingAccountCommissionCollection;

class ReportPendingAccountCommissionController extends Controller
{
    public function filter()
    {
        $establishments = Establishment::all()->transform(function($row) {
            return [
                'id' => $row->id,
                'name' => $row->description
            ];
        });
        $sellers = $this->getSellers();

        return compact('establishments', 'sellers');
    }

    public function index()
    {
        return view('report::pending-account-commissions.index');
    }

    public function records(Request $request)
    {
        $records = $this->getRecords($request->all(), User::class);

        // Obtén el valor de días máximos de deuda vencida
        $config = \App\Models\Tenant\Configuration::first();
        $finances = $config->finances ?? [];

        // Pásalo a la colección
        return new ReportPendingAccountCommissionCollection(
            $records->paginate(config('tenant.items_per_page')),
            $finances
        );
    }

    public function getRecords($request, $model)
    {
        $establishment_id = $request['establishment_id'] ?? null;
        $period = $request['period'] ?? null;
        $date_start = $request['date_start'] ?? null;
        $date_end = $request['date_end'] ?? null;
        $month_start = $request['month_start'] ?? null;
        $month_end = $request['month_end'] ?? null;
        $user_type = $request['user_type'] ?? null;
        $user_seller_id = $request['user_seller_id'] ?? null;

        $d_start = null;
        $d_end = null;

        switch ($period) {
            case 'month':
                $d_start = Carbon::parse($month_start.'-01')->format('Y-m-d');
                $d_end = Carbon::parse($month_start.'-01')->endOfMonth()->format('Y-m-d');
                break;
            case 'between_months':
                $d_start = Carbon::parse($month_start.'-01')->format('Y-m-d');
                $d_end = Carbon::parse($month_end.'-01')->endOfMonth()->format('Y-m-d');
                break;
            case 'date':
                $d_start = $date_start;
                $d_end = $date_start;
                break;
            case 'between_dates':
                $d_start = $date_start;
                $d_end = $date_end;
                break;
        }

        // Igual que en comisiones: pasa todos los parámetros
        $records = $this->data($establishment_id, $d_start, $d_end, $model, $user_seller_id, $user_type);

        return $records;
    }

    private function data($establishment_id, $date_start, $date_end, $model, $user_seller_id, $user_type)
    {
        $data = $model::query();

        // if ($establishment_id) {
        //     $data = $data->where('establishment_id', $establishment_id);
        // }

        if ($user_seller_id) {
            $data = $data->where('id', $user_seller_id);
        }

        // Siempre retorna un query Eloquent
        return $data->latest()->whereTypeUser();
    }

    public function pdf(Request $request)
    {
        $company = Company::first();
        $establishment = ($request->establishment_id) ? Establishment::findOrFail($request->establishment_id) : auth()->user()->establishment;
        $users = $this->getRecords($request->all(), User::class)->get();

        $configuration = \App\Models\Tenant\Configuration::first();
        $finances = $configuration->finances ?? [];
        // Calcula los datos finales para cada usuario
        $records = $users->map(function($user) use ($request, $finances) {
            return \Modules\Report\Helpers\UserCommissionHelper::getDataForPendingAccountCommissionReport($user, $request, $finances);
        });

        $pdf = PDF::loadView('report::pending-account-commissions.report_pdf', compact("records", "company", "establishment", "request"));

        $filename = 'Reporte_Cuentas_Pendientes_'.date('YmdHis');

        return $pdf->download($filename.'.pdf');
    }

    public function excel(Request $request)
    {
        $company = Company::first();
        $establishment = ($request->establishment_id) ? Establishment::findOrFail($request->establishment_id) : auth()->user()->establishment;
        $users = $this->getRecords($request->all(), User::class)->get();

        $configuration = \App\Models\Tenant\Configuration::first();
        $finances = $configuration->finances ?? [];

        $records = $users->map(function($user) use ($request, $finances) {
            return \Modules\Report\Helpers\UserCommissionHelper::getDataForPendingAccountCommissionReport($user, $request, $finances);
        });

        return (new PendingAccountCommissionExport)
            ->records($records)
            ->company($company)
            ->establishment($establishment)
            ->request($request)
            ->download('Reporte_Cuentas_Pendientes_'.now().'.xlsx');
    }
}