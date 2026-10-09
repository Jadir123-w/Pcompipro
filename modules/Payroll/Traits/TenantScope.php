<?php

namespace Modules\Payroll\Traits;

use Hyn\Tenancy\Environment;
use Hyn\Tenancy\Models\Website;

trait TenantScope
{
    /**
     * Obtener el website_id del tenant actual
     */
    public static function getCurrentWebsiteId()
    {
        try {
            $environment = app(Environment::class);
            $tenant = $environment->tenant();

            if ($tenant instanceof Website) {
                return $tenant->id;
            }

            // Para desarrollo o fallback
            if (app()->environment('local')) {
                $website = Website::first();
                if ($website) {
                    return $website->id;
                }
            }

            return null;

        } catch (\Exception $e) {
            logger()->error('Error getting website_id: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Boot method for the trait
     */
    protected static function bootTenantScope()
    {
        // Usar una variable estática para capturar el websiteId
        $websiteId = static::getCurrentWebsiteId();

        static::addGlobalScope('tenant', function ($builder) use ($websiteId) {
            if ($websiteId) {
                $builder->where('website_id', $websiteId);
            }
        });
    }
}
