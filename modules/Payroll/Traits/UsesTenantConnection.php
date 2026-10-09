<?php

namespace Modules\Payroll\Traits;

trait UsesTenantConnection
{
    public function getConnectionName()
    {
        // hyn/multi-tenant usa la conexión 'tenant'
        return 'tenant';
    }
}
