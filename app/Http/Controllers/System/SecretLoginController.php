<?php

namespace App\Http\Controllers\System;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Helpers\SecretLoginHelper;


class SecretLoginController extends Controller
{
    
    /**
     *
     * @param  Request $request
     */
    public function secretLogin(Request $request)
    {
        $helper = new SecretLoginHelper();

        $client = $helper->getClient($request->client_id);
        $helper->setTenantConnection($client);

        $user = $helper->getFirstUser();
        $helper->updateSecretLoginTime($user, now());

        $hash = $helper->createHash($user, $client);

        return redirect()->to($helper->redirectUrl($client->hostname->fqdn, $hash));
    }

}
