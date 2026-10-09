<?php
namespace App\Http\Controllers\Tenant;

use App\Models\Tenant\Company;
use App\Models\Tenant\SoapType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\CompanyRequest;
use App\Http\Resources\Tenant\CompanyResource;
use Illuminate\Http\Request;
use App\Http\Requests\Tenant\CompanyPseRequest;
use App\Http\Requests\Tenant\CompanyWhatsAppApiRequest;
use App\Models\Tenant\Dispatch;
use App\Models\Tenant\Document;
use App\Models\Tenant\SaleNote;
use Illuminate\Support\Facades\Storage;
use Modules\Finance\Helpers\UploadFileHelper;
use Modules\PseService\Models\PseProvider;

/**
 * Class CompanyController
 *
 * @package App\Http\Controllers\Tenant
 * @mixin  Controller
 */
class CompanyController extends Controller
{
    public function create()
    {
        return view('tenant.companies.form');
    }

    public function tables()
    {
        $soap_sends = config('tables.system.soap_sends');
        $soap_types = SoapType::all();

        $models = [
            Document::class,
            SaleNote::class,
            Dispatch::class
        ];

        $message = [
            'tiene documentos que fueron creados en demo, por favor borrarlos para evitar conflictos en producción'
        ];

        $verifyDocumentsInDemo =  [
            'success' => false,
        ];

        foreach ($models as $index => $model) {
            $count = $model::where('soap_type_id', '01')->count();
            if ($count > 0) {
                if ($model === Document::class) {
                   array_unshift($message, 'Factura/Boleta o Nota de Crédito o Nota de Débito'); 
                } else if ($model === SaleNote::class) {
                   array_unshift($message, 'Nota de Venta'); 
                } else if ($model === Dispatch::class) {
                   array_unshift($message, 'Guía de Remisión'); 
                }

                $verifyDocumentsInDemo['success'] = true;
            } 
        }

        $verifyDocumentsInDemo['message'] = implode(', ', $message);

        return compact('soap_types', 'soap_sends', 'verifyDocumentsInDemo');
    }

    public function getPseProviders()
    {
        $providers = PseProvider::select('id', 'name', 'description', 'active')
            ->where('active', true) // Filtrar solo los proveedores activos
            ->get();

        return response()->json($providers);
    }

    public function record()
    {
        $company = Company::active();
        $record = new CompanyResource($company);

        return $record;
    }

    public function store(CompanyRequest $request)
    {
        $id = $request->input('id');
        $company = Company::find($id);
        $company->fill($request->all());
        $company->save();

        return [
            'success' => true,
            'message' => 'Empresa actualizada'
        ];
    }

    public function uploadFile(Request $request)
    {
        if ($request->hasFile('file')) {

            $company = Company::active();

            $type = $request->input('type');

            $file = $request->file('file');
            $ext = $file->getClientOriginalExtension();
            $name = $type . '_' . $company->number . '.' . $ext;


            if (($type === 'logo')) {
                $v = request()->validate(['file' => 'required|mimes:jpeg,png,jpg,gif,svg|max:2048']);

                UploadFileHelper::checkIfValidFile($name, $file->getPathName(), true);

                $file->storeAs(($type === 'logo') ? 'public/uploads/logos' : 'certificates', $name);
            }

            if (($type === 'logo_dark')) {
                $v = request()->validate(['file' => 'required|mimes:jpeg,png,jpg,gif,svg|max:2048']);
                UploadFileHelper::checkIfValidFile($name, $file->getPathName(), true);
                $file->storeAs('public/uploads/logos', $name);
            }

            // if (($type === 'logo_store')) {
            //     request()->validate(['file' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:2048']);
            //     $file->storeAs(($type === 'logo_store') ? 'public/uploads/logos' : 'certificates', $name);
            // }

            if (($type === 'favicon')) {
                request()->validate(['file' => 'required|image|mimes:png|max:1024']);
                $filename = time() . '.' . $ext;
                $name = 'storage/uploads/favicons/' . $filename;

                UploadFileHelper::checkIfValidFile($name, $file->getPathName(), true, 'png', ['image/png']);

                $file->storeAs('public/uploads/favicons', $filename);
            }

            if (($type === 'app_logo')) {
                request()->validate(['file' => 'required|mimes:jpeg,png,jpg,gif,svg|max:2048']);
                UploadFileHelper::checkIfValidFile($name, $file->getPathName(), true);
                $file->storeAs('public/uploads/logos', $name);
            }


            if (($type === 'img_firm')) {
                request()->validate(['file' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:2048']);
                UploadFileHelper::checkIfValidFile($name, $file->getPathName(), true);
                $file->storeAs('public/uploads/firms', $name);
            }

            // $file->storeAs(($type === 'img_firm') ? 'public/uploads/firms' : 'certificates', $name);

            $company->$type = $name;

            $company->save();

            return [
                'success' => true,
                'message' => __('app.actions.upload.success'),
                'name' => $name,
                'type' => $type
            ];
        }
        return [
            'success' => false,
            'message' => __('app.actions.upload.error'),
        ];
    }


    /**
     * Registrar datos de configuracion para enviar xml/cdr a PSE
     *
     * @param  Request $request
     * @return array
     */
    public function storeSendPse(CompanyPseRequest $request)
    {
        $company = Company::firstOrFail();
        $company->send_document_to_pse = $request->send_document_to_pse;
        $company->pse_provider_id = $request->pse_provider_id; // Guardar el proveedor seleccionado
        $company->url_signature_pse = $request->url_signature_pse;
        $company->url_send_cdr_pse = $request->url_send_cdr_pse;
        $company->client_id_pse = $request->client_id_pse;
        $company->url_login_pse = $request->url_login_pse;
        $company->user_pse = $request->user_pse;
        $company->password_pse = $request->password_pse ?? $company->password_pse;
        $company->save();

        return [
            'success' => true,
            'message' => 'Datos guardados correctamente'
        ];
    }


    /**
     * Obtener datos de configuracion de PSE
     *
     * @param  Request $request
     * @return array
     */
    public function recordSendPse()
    {

        $company = Company::firstOrFail();

        return [
            'send_document_to_pse' => $company->send_document_to_pse,
            'pse_provider_id' => $company->pse_provider_id,
            'url_signature_pse' => $company->url_signature_pse,
            'url_send_cdr_pse' => $company->url_send_cdr_pse,
            'client_id_pse' => $company->client_id_pse,
            'url_login_pse' => $company->url_login_pse,
            'user_pse' => $company->user_pse,
            'password_pse' => $company->password_pse,
        ];

    }


    /**
     * Registrar datos de configuracion para WhatsApp Api
     *
     * @param  CompanyWhatsAppApiRequest $request
     * @return array
     */
    public function storeWhatsAppApi(CompanyWhatsAppApiRequest $request)
    {
        $company = Company::active();
        $company->ws_api_token = $request->ws_api_token;
        $company->ws_api_phone_number_id = $request->ws_api_phone_number_id;
        $company->save();

        return [
            'success' => true,
            'message' => 'Datos guardados correctamente'
        ];
    }


    /**
     *
     * Obtener datos de configuracion de WhatsApp Api
     *
     * @param  Request $request
     * @return array
     */
    public function recordWhatsAppApi()
    {
        $company = Company::selectDataWhatsAppApi()->firstOrFail();

        return [
            'ws_api_token' => $company->ws_api_token,
            'ws_api_phone_number_id' => $company->ws_api_phone_number_id,
        ];
    }

    /**
     * Eliminar logo (modo claro o modo oscuro)
     *
     * @param Request $request
     * @return array
     */
    public function deleteLogo(Request $request)
    {
        $request->validate([
            'type' => 'required|in:logo,logo_dark'
        ]);

        $company = Company::active();
        $type = $request->input('type');
        
        if (!$company->$type) {
            return [
                'success' => false,
                'message' => 'No hay logo para eliminar'
            ];
        }

        $currentLogo = $company->$type;
        
        try {
            // Para logos (modo claro y oscuro), la ruta es public/uploads/logos/
            $filePath = 'public/uploads/logos/' . basename($currentLogo);

            // Eliminar el archivo físico si existe
            if (Storage::exists($filePath)) {
                Storage::delete($filePath);
            }

            // Limpiar el campo en la base de datos
            $company->$type = null;
            $company->save();

            $logoName = $type === 'logo' ? 'Logo (modo claro)' : 'Logo (modo oscuro)';

            return [
                'success' => true,
                'message' => $logoName . ' eliminado correctamente',
                'type' => $type
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Error al eliminar el logo: ' . $e->getMessage()
            ];
        }
    }


}
