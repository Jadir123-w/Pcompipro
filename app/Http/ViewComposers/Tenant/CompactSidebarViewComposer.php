<?php

namespace App\Http\ViewComposers\Tenant;

use App\Models\Tenant\Configuration;
use App\Models\System\User;

class CompactSidebarViewComposer
{
    public function compose($view)
    {
    	$configuration = Configuration::first();
        // $set = (new \App\Http\Controllers\Tenant\ConfigurationController)->getSystemPhone();

        // Obtener el número de WhatsApp del sistema
        $systemUser = User::first();
        $whatsappNumber = $systemUser ? $systemUser->whatsapp_number : null;
        
        $view->show_ws = $configuration->enable_whatsapp;
        $view->phone_whatsapp = $whatsappNumber ?: $configuration->phone_whatsapp; // Usar whatsapp_number del sistema, si no existe usar el de configuración
        $view->vc_compact_sidebar = $configuration;
        
        //variables para validar si se debe mostrar notificacion del cambio de contraseña
        $view->vc_check_last_password_update = (object)[
            'enabled_remember_change_password' => $configuration->enabled_remember_change_password,
            'quantity_month_remember_change_password' => $configuration->quantity_month_remember_change_password,
        ];

    }
}