<?php

namespace Modules\Comercial\Http\Controllers;

use Hyn\Tenancy\Environment;
use Hyn\Tenancy\Models\Hostname;
use Hyn\Tenancy\Models\Website;
use Illuminate\Routing\Controller;
use Modules\Payroll\Traits\TenantScope;

class BaseController extends Controller
{
    use TenantScope;

    protected function getWebsiteId()
    {
        return self::getCurrentWebsiteId();
    }

    protected function getTenant()
    {
        return app(Environment::class)->tenant();
    }

    protected function getWebsite()
    {
        $tenant = $this->getTenant();

        if ($tenant instanceof Website) {
            return $tenant;
        }

        if ($tenant instanceof Hostname) {
            return $tenant->website;
        }

        return null;
    }

    protected function getHostname()
    {
        $tenant = $this->getTenant();

        if ($tenant instanceof Hostname) {
            return $tenant;
        }

        return null;
    }
}
