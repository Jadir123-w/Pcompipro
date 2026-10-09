<?php

namespace Modules\Report\Jobs;

use App\CoreFacturalo\Helpers\Storage\StorageDocument;
use App\Models\Tenant\DownloadTray;
use Hyn\Tenancy\Environment;
use Hyn\Tenancy\Models\Website;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Log;
use Modules\Report\Traits\MassiveDownloadTrait;

class ProcessReportMassiveDocuments implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, MassiveDownloadTrait, StorageDocument;

    public $tray_id;
    public $website_id;
    public $document_types;
    public $params;
    public $user_id;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($tray_id, $website_id, $document_types, $params)
    {
        $this->tray_id = $tray_id;
        $this->website_id = $website_id;
        $this->document_types = $document_types;
        $this->params = $params;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        Log::debug("ProcessReportMassiveDocuments Start WebsiteId => " . $this->website_id);
        $website = Website::find($this->website_id);
        $tenancy = app(Environment::class);
        $tenancy->tenant($website);

        $tray = DownloadTray::find($this->tray_id);
        $this->params->user_id = $tray->user_id;
        // Por defecto unicamente esta en pdf
        if (empty($tray)) {
            Log::info("ProcessReportMassiveDocuments: Tray not found. TrayId: " . $tray->user_id);
        } else {
            try {
                $data = $this->getData($this->params->document_types, $this->params);
                $height = isset($this->params->height)?$this->params->height:'a4';
                $view =  $this->createPdf($data,$height,(array)$this->params);

                $filename = 'massive_documents_'.date('YmdHis').'-' .$tray->user_id;
                $tray->file_name = $filename;
                $this->uploadStorage($filename, $view, 'download_tray_pdf');
                $path = 'download_tray_pdf';
                $tray->date_end = date('Y-m-d H:i:s');
                $tray->status = 'FINISHED';
                $tray->path = $path;
                $tray->save();
                Log::debug("ProcessReportMassiveDocuments End WebsiteId => " . $this->website_id);

            } catch (\Throwable $th) {
                Log::error('ProcessReportMassiveDocuments Error: ', [
                    'mensaje' => $th->getMessage(),
                    'archivo' => $th->getFile(),
                    'linea'   => $th->getLine(),
                ]);
            }
       }

    }

}
