<?php

namespace Modules\Report\Jobs;

use App\CoreFacturalo\Helpers\Storage\StorageDocument;
use App\Traits\JobReportTrait;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Barryvdh\DomPDF\Facade as PDF;
use Hyn\Tenancy\Environment;
use Illuminate\Support\Facades\Log;
use Modules\Report\Exports\GeneralItemExport;

class ProcessReportGeneralItems implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, StorageDocument, JobReportTrait;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public $tray_id;
    public $website_id;
    public $request;
    public $user_id;

    public function __construct($tray_id, $website_id,$request, $user_id)
    {
        $this->tray_id = $tray_id;
        $this->website_id = $website_id;
        $this->request = $request;
        $this->user_id = $user_id;

    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        try {
        $website = $this->findWebsite($this->website_id);
        $tenancy = app(Environment::class);
        $tenancy->tenant($website);

        $this->login($this->user_id);

        $controller = new \Modules\Report\Http\Controllers\ReportGeneralItemController();
        $records = $controller->getRecordsItems($this->request)->latest('id')->get() ;

        $document_type_id = $this->request['document_type_id'];
        $request_apply_conversion_to_pen = $this->request['apply_conversion_to_pen'];
        $type = $this->request['type'];
        $type_prefix = ($type == 'sale') ? 'ventas_' : 'compras_';


        $tray = $this->findDownloadTray($this->tray_id);
        $path = $this->getReportPath($this->request['format']);
        $format = $this->request['format'];
        $filename = $type_prefix . 'report_general_items_'.date('YmdHis').'-' .$tray->user_id;

        if ($format == 'pdf') {
            $pdf = PDF::loadView('report::general_items.report_pdf', compact("records", "type", "document_type_id", "request_apply_conversion_to_pen"))->setPaper('a4', 'landscape');
            $tray->file_name = $filename;
            $this->uploadStorage($filename, $pdf->output(), $path);
            $tray->date_end = date('Y-m-d H:i:s');
            $tray->status = 'FINISHED';
            $tray->path = $path;
            $tray->save();

        } else if ($format== 'xlsx') {
            $generalItemExport= new GeneralItemExport();
            $generalItemExport
                ->records($records)
                ->type($type)
                ->document_type_id($document_type_id)
                ->request_apply_conversion_to_pen($request_apply_conversion_to_pen);
            
            $generalItemExport->store(DIRECTORY_SEPARATOR.$path.DIRECTORY_SEPARATOR.$filename.'.'.$format, 'tenant');
            
            $tray->file_name = $filename;
            $tray->date_end = date('Y-m-d H:i:s');
            $tray->status = 'FINISHED';
            $tray->path = $path;
            $tray->save();
        }

        $this->logout();

        } catch (\Throwable $th) {
            Log::info($th->getMessage());
            Log::error("ProcessReportGeneralItems Error: " . $th->getTraceAsString());
        }
    }
}
