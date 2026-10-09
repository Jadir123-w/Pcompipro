<?php

namespace Modules\MobileApp\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Http\Controllers\Controller;
use Modules\Dashboard\Helpers\DashboardData;
use App\CoreFacturalo\Helpers\Functions\FunctionsHelper;
use App\Http\Controllers\Tenant\EmailController;
use App\Models\Tenant\Company;
use App\Models\Tenant\Configuration;
use Modules\MobileApp\Http\Requests\Api\ReportGeneralSaleRequest;
use App\Models\Tenant\Establishment;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Modules\MobileApp\Emails\ReportGeneralSale;

class ReportController extends Controller
{
    
    /**
     *
     * @return array
     */
    public function filters()
    {
        $establishments = Establishment::filterDataForTables()->get();

        return compact('establishments');
    }
    

    /**
     * 
     * Reporte general de ventas
     * Totales incluye pedidos, notas de venta, cpe
     * Gráfico incluye notas de venta, cpe
     *
     * @param  ReportGeneralSaleRequest $request
     * @return array
     */
    public function reportGeneralSale(Request $request)
    {

        $establishment_id = $request['establishment_id'] ?? auth()->user()->establishment_id;
        $period = $request['period'];
        $month_start = $request['month_start'];
        $month_end = $request['month_end'];
        $d_start = null;
        $d_end = null;

        FunctionsHelper::setDateInPeriod($request, $d_start, $d_end);
        $download_report = FunctionsHelper::generatePathQueryByPeriod($request['period'], $request);

        return [
            'data' => (new DashboardData())->getGeneralTotals($establishment_id, $d_start, $d_end, $period, $month_start, $month_end),
            'download_report' => url('') . "/report-app/pdf-general-sale" . $download_report . "&establishment_id=" . $establishment_id
        ];

    }

    public function email(Request $request)
    {
        $email = $request->email;
        $company = Company::active();
        $establishment_id = $request['establishment_id'] ?? auth()->user()->establishment_id;
        $establishments = Establishment::find($establishment_id);
        $mailable = new ReportGeneralSale($request, $company, $establishments);
        $model = __FILE__.";;".__LINE__;
        $sendIt = EmailController::SendMail($email, $mailable, 0, $model);

        // Configuration::setConfigSmtpMail();
        // Mail::to($email)->send($mailable);

        return [
            'success' => true,
            'message'=> 'Email enviado correctamente.'
        ];
    }

    
}
