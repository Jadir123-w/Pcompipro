<?php

namespace App\Traits;

use App\CoreFacturalo\Facturalo;
use App\Http\Controllers\Tenant\VoidedController;
use App\Models\Tenant\Configuration;
use App\Models\Tenant\Document;
use App\Models\Tenant\Summary;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use DB;

trait SummaryTrait
{
    public function save($request) {
        $validate = $this->validateVoided($request);

        if (!$validate['success']) return $validate;
        if (Facturalo::validateCertificate()) return $this->generalResponse(false, 'Ocurrió un error: Certificado digital no encontrado.');

        $fact = DB::connection('tenant')->transaction(function () use($request) {
            $facturalo = new Facturalo();
            $facturalo->save($request->all());
            $facturalo->createXmlUnsigned();
            $service_pse_xml = $facturalo->servicePseSendXml();
            $facturalo->signXmlUnsigned($service_pse_xml['xml_signed']);
            $facturalo->senderXmlSignedSummary();

            return $facturalo;
        });

        $document = $fact->getDocument();

        return [
            'success' => true,
            'message' => "El resumen {$document->identifier} fue creado correctamente",
        ];
    }
    /**
     * Validaciones previas
     *
     * @param VoidedRequest $request
     * @return array
     */
    public function validateVoided($request)
    {

        $configuration = Configuration::select('restrict_voided_send', 'shipping_time_days_voided')->firstOrFail();
        $voided_date_of_issue = Carbon::parse($request->date_of_issue);

        if($configuration->restrict_voided_send)
        {
            foreach ($request->documents as $row)
            {
                $document = Document::whereFilterWithOutRelations()->select('date_of_issue')->findOrFail($row['document_id']);

                $difference_days = $configuration->shipping_time_days_voided - $document->getDiffInDaysDateOfIssue($voided_date_of_issue);

                if($difference_days < 0)
                {
                    return [
                        'success' => false,
                        'message' => "El documento excede los {$configuration->shipping_time_days_voided} días válidos para ser anulado."
                    ];
                }
            }
        }

        return [
            'success' => true,
            'message' => null
        ];

    }

    public function query($id) {
        $document = Summary::find($id);

        $fact = DB::connection('tenant')->transaction(function () use($document) {
            $facturalo = new Facturalo();
            $facturalo->setDocument($document);
            $facturalo->setType('summary');
            $hasPseSend = $facturalo->hasPseSend();
            if($hasPseSend){
                $facturalo->pseQuerySummary();
            } else {
                $facturalo->statusSummary($document->ticket);
            }
            return $facturalo;
        });

        $response = $fact->getResponse();

        return [
            'success' => ($response['status_code'] === 99) ? false : true,
            'message' => $response['description'],
        ];
    }


    public function getCustomErrorMessage($message, $exception) {

        $this->setCustomErrorLog($exception);

        return [
            'success' => false,
            'message' => $message
        ];

    }

    public function setCustomErrorLog($exception)
    {
        Log::error("Code: {$exception->getCode()} - Line: {$exception->getLine()} - Message: {$exception->getMessage()} - File: {$exception->getFile()}");
    }

    public function updateUnknownErrorStatus($id, $exception) {

        Summary::findOrFail($id)->update([
            'unknown_error_status_response' => true,
            'error_manually_regularized' => [
                'message' => $exception->getMessage(),
            ],
        ]);

    }


}
