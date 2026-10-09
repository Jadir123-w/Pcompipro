<?php

namespace Modules\MobileApp\Emails;

use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;
use Modules\MobileApp\Http\Controllers\LiveAppController;

class ReportGeneralSale extends Mailable
{
    use Queueable, SerializesModels;


    private $filter;
    public $company;
    public $establishment;
    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($filter, $company, $establishment)
    {
        $this->filter = $filter;
        $this->company = $company;
        $this->establishment = $establishment;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $pdf = app(LiveAppController::class)->getReportGeneralSale($this->filter);

        return $this->subject('Envio de reporte general de ventas')
            ->from(config('mail.username'), 'Reporte general de ventas')
            ->view('mobileapp::template.report')
            ->attachData($pdf, 'report_general_sale.pdf');
    }

}
