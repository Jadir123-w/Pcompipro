<?php

namespace Modules\LevelAccess\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Http\Controllers\Controller;
use App\Helpers\GuestRegisterHelper;
use App\Models\Tenant\User;
use App\Models\Tenant\Configuration;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;


class GuestRegisterController extends Controller
{
    
    public function notVerifiedEmail()
    {
        return view('tenant.guest-register.not-verified-email.index');
    }


    /**
     * 
     *
     * @param  string $key
     * @return array
     */
    public function emailVerificationValid($key)
    {
        list($email_verified_at, $id) = explode('_', (new GuestRegisterHelper)->decryptValue($key));

        $user = User::where('email_verified_at', $email_verified_at)->find($id);
        $was_verified_guest_user = Configuration::getRecordIndividualColumn('was_verified_guest_user');

        if(!is_null($user) && $was_verified_guest_user)
        {
            $this->loginById($user);
            
            return redirect()->route('tenant.dashboard.index');
        }

        Log::error('Intento de acceso no autorizado en verificación de email.');

        abort(401);
    }

    
    /**
     *
     * @param  User $user
     * @return void
     */
    public function loginById(User $user)
    {
        Auth::loginUsingId($user->id);
    }


}
