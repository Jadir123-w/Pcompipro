<?php

namespace Modules\LevelAccess\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Helpers\SecretLoginHelper;


class SecretLoginController extends Controller
{

    
    /**
     *
     * @param  string $hash
     */
    public function checkSecretLogin($hash)
    {
        $helper = new SecretLoginHelper();
        
        $user = $helper->getFirstUser();

        $hostname_id = $helper->getHostnameId();

        $client = $helper->getClientByHostname($hostname_id);

        $current_hash = $helper->createHash($user, $client);
        
        if (!hash_equals($current_hash, (string) $hash)) $helper->isUnauthorized();

        $helper->updateSecretLoginTime($user);
        $helper->loginById($user);

        return redirect()->route('tenant.dashboard.index');
    }

    
}
