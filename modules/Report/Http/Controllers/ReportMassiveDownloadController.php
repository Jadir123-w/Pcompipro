<?php

namespace Modules\Report\Http\Controllers;

use App\Models\Tenant\Catalogs\DocumentType;
use App\Http\Controllers\Controller;
use App\Models\System\Client;
use Barryvdh\DomPDF\Facade as PDF;
use Modules\Report\Exports\DocumentHotelExport;
use Illuminate\Http\Request;
use Modules\Report\Traits\ReportTrait;
use App\Models\Tenant\Establishment;
use App\Models\Tenant\Configuration;
use App\Models\Tenant\Company;
use App\Models\Tenant\{
    Document,
    SaleNote,
    Dispatch,
    DownloadTray
};
use Carbon\Carbon;
use Hyn\Tenancy\Models\Hostname;
use Modules\Report\Jobs\ProcessReportMassiveDocuments;
use Modules\Report\Traits\MassiveDownloadTrait;

class ReportMassiveDownloadController extends Controller
{

    use ReportTrait, MassiveDownloadTrait;

    public function index()
    {
        return view('report::massive-downloads.index');
    }


    public function filter() {

        $document_types = DocumentType::whereIn('id', ['01', '03','80', '09'])->get();
        $sellers = $this->getSellers();
        $series = $this->getSeries($document_types);

        $persons = $this->getPersons('customers');

        return compact('document_types','persons','sellers','series');
    }


    public function records(Request $request)
    {

        $params = json_decode($request->form);
        $document_types = $params->document_types;

        if(count($document_types) == 0){
            $document_types = ['all'];
        }

        return [
            'total' => $this->getTotals($document_types, $params)
        ];

    }


    public function pdf(Request $request) {
        $host = $request->getHost();
        $tray = DownloadTray::create([
            'user_id' => auth()->user()->id,
            'module' => 'INVENTORY',
            'format' => 'pdf',
            'date_init' => date('Y-m-d H:i:s'),
            'type' => 'Descarga masiva de documentos'
        ]);
        $trayId = $tray->id;
        $hostname = Hostname::where('fqdn',$host)->first();
        if(empty($hostname)) {
            $company = Company::active();
            $number = $company->number;
            $client = Client::where('number', $number)->first();
            $website_id = $client->hostname->website_id;
        }else{
            $website_id = $hostname->website_id;
        }
        $params = json_decode($request->form);

        $document_types = $params->document_types;

        if(count($document_types) == 0){
            $document_types = ['all'];
        }
        ProcessReportMassiveDocuments::dispatch($trayId, $website_id,$document_types, $params);

        return  [
            'success' => true,
            'message' => 'El reporte se esta procesando; puede ver el proceso en bandeja de descargas.'
        ];

    }


}
