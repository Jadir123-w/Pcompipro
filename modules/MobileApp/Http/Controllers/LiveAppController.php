<?php

namespace Modules\MobileApp\Http\Controllers;

use App\Models\Tenant\Establishment;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Modules\MobileApp\Http\Controllers\Api\ReportController;
use Mpdf\Mpdf;

class LiveAppController extends Controller
{
    public function index()
    {
        return view('mobileapp::mobile_app.index');
    }

    public function configuration()
    {
        return view('mobileapp::configuration.index');
    }

    public function premium()
    {
        return view('mobileapp::mobile_app_white.index');
    }

    public function getReportGeneralSale(Request $request, $format = null)
    {
        $establishment_id = $request->establishment_id;
        $data = app(ReportController::class)->reportGeneralSale($request);
        $establishment = Establishment::find($establishment_id);
        $filter = $request->all();

        $view = view('mobileapp::report.general_sale', compact('data', 'establishment', 'filter'));
        $html = $view->render();
        $path_html = app_path('CoreFacturalo'.DIRECTORY_SEPARATOR.'Templates'.
                                             DIRECTORY_SEPARATOR.'pdf'.
                                             DIRECTORY_SEPARATOR.'ticket_html.css');
        $ticket_html = file_get_contents($path_html);
        if ($format == 'html') {
            return "<style>".$ticket_html."</style>".$html;
        }

        $ancho = 76; // en mm
        $alto = 130; // en mm

        $pdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => [$ancho, $alto], // ancho x alto en mm
            'margin_left' => 3,
            'margin_right' => 3,
            'margin_top' => 3,
            'margin_bottom' => 3,
        ]);

        $pdf->WriteHTML($html);

        $output = $pdf->output('', 'S');

        return $output;

    }
    /**
     * Genera el reporte de ventas en formato PDF
     */
    public function pdfReportGeneralSale(Request $request)
    {
        $output = $this->getReportGeneralSale($request, $request->format);
        $format = $request->format;
        $temp = tempnam(sys_get_temp_dir(), 'cash_pdf_a4');
        file_put_contents($temp, $output);

        if($format == 'html') {
            return $output;
        }

        $headers = [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="Reporte"'
        ];

        return response()->file($temp, $headers);
    }
}
