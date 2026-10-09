<?php

namespace App\Traits;

use App\Models\System\{
    Client,
    Module,
    Plan
};
use App\Http\Requests\System\ClientRequest;
use Illuminate\Support\Facades\Log;
use App\Models\Tenant\Configuration;
use App\Models\Tenant\User;
use Hyn\Tenancy\Environment;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Exception;

trait GuestRegisterTrait
{    
    /**
     *
     * @param  User $user
     * @return array
     */
    private function updateDataVerifiedUser($user)
    {
        try
        {
            return DB::connection('tenant')->transaction(function () use ($user) {

                $user->email_verified_at = now();
                $user->save();
        
                $configuration = Configuration::firstOrFail();
                $configuration->was_verified_guest_user = true;
                $configuration->save();
        
                return [
                    'success' => true,
                    'message' => 'Datos actualizados.'
                ];
            });

        } 
        catch (Exception $e) 
        {
            $this->setErrorLog('Error al actualizar datos en verificación de email: '.$e->getMessage());

            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Get the email address that should be used for verification.
     *
     * @return string
     */
    public function getEmailForVerification($user)
    {
        return $user->email;
    }
        
    /**
     *
     * @param  User $user
     * @param  string $hash
     * @return void
     */
    private function validateHash($user, $hash)
    {
        if (!hash_equals(sha1($this->getEmailForVerification($user)), (string) $hash))
        {
            $this->setErrorLog('Error al validar correo: El hash es inválido.');
            $this->isUnauthorized();
        }
    }
    
    /**
     * validateUserKey
     *
     * @param  User $user
     * @param  int $id
     * @return void
     */
    private function validateUserKey($user, $id)
    {
        if(!hash_equals((string) $user->getKey(), (string) $id)) 
        {
            $this->setErrorLog('Error al validar correo: Usuario inválido.');
            $this->isUnauthorized();
        }
    }

    /**
     *
     * @param  string $decrypt_client_id
     * @return void
     */
    private function validateDecryptClient($decrypt_client_id)
    {
        if(is_null($decrypt_client_id))
        {
            $this->setErrorLog('Error al validar correo: Ocurrió un error al descifrar valor decrypt_client_id.');
            $this->isUnauthorized();
        }
    }

    /**
     *
     * @param  Request $request
     * @return void
     */
    private function validateSignedRoute(Request $request)
    {
        if(!$request->hasValidSignature()) 
        {
            $this->setErrorLog('Error al validar correo: La ruta no tiene una firma válida.');
            $this->isUnauthorized();
        }
    }

    /**
     *
     * @param  Client $client
     * @return void
     */
    private function changeClientConnection($client)
    {
        $tenancy = app(Environment::class);
        $tenancy->tenant($client->hostname->website);
    }

    /**
     *
     * @return User
     */
    public function getFirstUser()
    {
        return User::firstOrFail();
    }

    /**
     *
     * @param  int $client_id
     * @return Client
     */
    public function getClient($client_id)
    {
        return Client::findOrFail($client_id);
    }

    /**
     *
     * @return ClientRequest
     */
    private function inputsToRegister($request)
    {
        $basic_module_levels = $this->getBasicModuleLevels();

        $inputs = [
            'name' => $request->name,
            'email' => $request->email,
            'identity_document_type_id' => $request->identity_document_type_id,
            'number' => $request->number,
            'password' => $request->password,
            'subdomain' => $request->subdomain,
            'plan_id' => $this->firstPlanId(),
            'locked_emission' => false,
            'type' => 'admin',
            'config_system_env' => true,
            'soap_send_id' => '01',
            'soap_type_id' => '01',
            'soap_username' => null,
            'soap_password' => null,
            'soap_url' => null,
            'regex_password_client' => false,
            'modules' => $basic_module_levels['modules'],
            'levels' => $basic_module_levels['levels'],
            'from_guest_register' => true,
            'enable_list_product' => true
        ]; 

        return new ClientRequest($inputs);
    }
    
    /**
     *
     * @return int
     */
    private function firstPlanId()
    {
        $plan = Plan::select('id')->firstOrFail();

        return $plan->id;
    }

    /**
     *
     * Modulos del grupo basico
     * 
     * @return array
     */
    public function getBasicModuleLevels()
    {
        $modules_basic_group = $this->getModulesBasicGroup();

        $modules = $modules_basic_group->pluck('id')->toArray();
        $levels = collect();

        $modules_basic_group->each(function ($module) use(&$levels){
            $levels = $levels->concat($module->levels->pluck('id')->toArray());
        });

        return [
            'modules' => $modules, 
            'levels' => $levels->toArray()
        ];
    }

    /**
     *
     * @return array
     */
    public function getModulesBasicGroup()
    {
        return Module::with([
                    'levels' => function ($levels) {
                        $levels->select(['id', 'value', 'module_id']);
                }
                ])
                // ->whereIn('id', Module::BASIC_BUSINESS_TURN_IDS)
                ->orderBy('sort')
                ->select(['id', 'value'])
                ->get();
    }

    /**
     *
     * @param  string $message
     * @return void
     */
    private function setErrorLog($message)
    {
        Log::error($message);
    }

    /**
     *
     * @param  string $message
     * @return void
     */
    private function isUnauthorized()
    {
        abort(401);
    }
    
    /**
     *
     * @param  string $fqdn
     * @param  string $key
     * @return string
     */
    private function redirectUrl($fqdn, $key)
    {
        $protocol = config('tenant.force_https') ? 'https' : 'http';
        
        return "{$protocol}://".$fqdn."/guest-register/email-verification-valid/{$key}";
    }
}