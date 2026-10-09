<?php

namespace Modules\Dispatch\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\CoreFacturalo\Facturalo;
use App\Http\Resources\Tenant\DispatchCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use App\Models\Tenant\Dispatch;
use Modules\ApiPeruDev\Http\Controllers\ServiceDispatchController;


class DispatchCarrierController extends Controller
{
    public function __construct()
    {
        $this->middleware('input.request:dispatch,api', ['only' => ['store']]);
    }

    public function store(Request $request)
    {
        // dd($request->all());
        $fact = DB::connection('tenant')->transaction(function () use ($request) {
            $facturalo = new Facturalo();
            $facturalo->save($request->all());
            $document = $facturalo->getDocument();
            $data = (new ServiceDispatchController())->getData($document->id);
            $facturalo->setXmlUnsigned((new ServiceDispatchController())->createXmlUnsigned($data));
            $service_pse_xml = $facturalo->servicePseSendXml();
            $facturalo->signXmlUnsigned($service_pse_xml['xml_signed']);
            $facturalo->createPdf();
            return $facturalo;
        });

        $document = $fact->getDocument();

        return [
            'success' => true,
            'data' => [
                'number' => $document->number_full,
                'filename' => $document->filename,
                'external_id' => $document->external_id,
            ],
        ];
    }
    public function records(Request $request)
    {
        $input = $request->input;
        $records = Dispatch::query()
            ->where('document_type_id', '31')
            ->when($input, function ($query) use ($input) {
                return $query
                    ->where('series', 'like', '%' . $input . '%')
                    ->orWhere('number', 'like', '%' . $input . '%');
            })
            ->latest();

        return new DispatchCollection($records->paginate(config('tenant.items_per_page')));
    }
}