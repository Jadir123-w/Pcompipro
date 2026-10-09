<?php

namespace App\Http\ViewComposers\Tenant;

use App\Models\System\Configuration;
use App\Models\Tenant\Configuration as TenantConfiguration;
use App\Models\Tenant\Module;
use Modules\LevelAccess\Models\ModuleLevel;

class ModuleViewComposer
{
    public function compose($view)
    {
        $user = auth()->user();
        if (is_null($user)) {
            return redirect()->route('login');
        }

        $modules = $user->modules()->pluck('value')->toArray();
        $module_levels = $user->levels()->pluck('value')->toArray();
        /*
        $systemConfig = Configuration::select('use_login_global')->first();
        */
        $systemConfig = Configuration::getDataModuleViewComposer();

        if(count($modules) > 0 && count($module_levels)) {
            $view->vc_modules = $modules;
            $view->vc_modules_levels = $module_levels;
        } else {
            $view->vc_modules = Module::all()->pluck('value')->toArray();
            $view->vc_modules_levels = ModuleLevel::all()->pluck('value')->toArray();
        }
        $view->vc_configuration = TenantConfiguration::first();

        $view->useLoginGlobal = $systemConfig->use_login_global;

        $view->tenant_show_ads = $systemConfig->tenant_show_ads;
        $view->url_tenant_image_ads = $systemConfig->getUrlTenantImageAds();

    }
}
