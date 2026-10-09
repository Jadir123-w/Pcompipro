<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\Models\System\Configuration;
use App\Http\Requests\System\{
    GuestRegisterClientRequest,
    SendEmailGuestRegisterRequest,
};
use App\Traits\GuestRegisterTrait;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use App\Helpers\GuestRegisterHelper;
use App\Http\Controllers\System\ClientController;


class GuestRegisterController extends Controller
{
    use GuestRegisterTrait;

    private const DEFAULT_BACKGROUND_IMAGE_LOGIN = 'fondo-5.svg';

    public function index()
    {
        return view('system.guest-register.index', $this->getDataRegister());
    }

    public function disabled()
    {
        return view('system.guest-register.disabled', $this->getDataRegister());
    }
    
    /**
     *
     * @return array
     */
    private function getDataRegister()
    {
        $configuration = Configuration::select(['use_login_global', 'login'])->first();
        $use_login_global = $configuration->use_login_global;
        $login = $configuration->login;
        $default_background_image_login = asset('images/'.self::DEFAULT_BACKGROUND_IMAGE_LOGIN);
        $base_url = '.' . config('tenant.app_url_base');

        return compact('login', 'use_login_global', 'default_background_image_login', 'base_url');
    }

    /**
     * key = encrypt_client_id
     * 
     * @param  Request $request
     * @return array
     */
    public function resendEmail(SendEmailGuestRegisterRequest $request)
    {
        return (new GuestRegisterHelper())->sendEmail($request->user_id, $request->email, $request->key);
    }

    /**
     *
     * @param  string $id
     * @param  string $hash
     * @param  string $client_id
     * @param  Request $request
     * @return void
     */
    public function verifyGuestRegisteredEmail($id, $hash, $client_id, Request $request)
    {
        // $this->validateSignedRoute($request);

        $helper = new GuestRegisterHelper();
        $decrypt_client_id = $helper->decryptValue($client_id);
        $this->validateDecryptClient($decrypt_client_id);

        $client = $this->getClient($decrypt_client_id);
        $this->changeClientConnection($client);

        $user = $this->getFirstUser();
        
        $this->validateUserKey($user, $id);
        $this->validateHash($user, $hash);


        $data = $this->updateDataVerifiedUser($user);

        if($data['success'])
        {
            return redirect()->to($this->redirectUrl($client->hostname->fqdn, $helper->encryptValue("{$user->email_verified_at}_{$user->id}")));
        }

        return abort(500, 'Error al validar correo.');
    }

    /**
     * 
     * Crear cuenta
     *
     * @param  GuestRegisterClientRequest $request
     * @return array
     */
    public function register(GuestRegisterClientRequest $request)
    {
        try
        {
            $inputs = $this->inputsToRegister($request);
            
            $response = app(ClientController::class)->store($inputs);

            if(!$response['success']) return $response;

            return [
                'success' => true,
                'message' => 'Cuenta registrada correctamente.',
                'guest_register' => $response['guest_register']
            ];

        } 
        catch (Exception $e) 
        {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }


    }

}