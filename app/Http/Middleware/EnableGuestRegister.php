<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use App\Models\System\Configuration;

class EnableGuestRegister
{
    public function handle(Request $request, Closure $next)
    {
        if (!Schema::hasTable('configurations')) {
            return redirect()->route('guest.register.disabled');
        }
        $config = Configuration::first();
        if ($config && $config->enable_guest_register) {
            return $next($request);
        }
        // Redirige o muestra la vista de registro deshabilitado
        return redirect()->route('guest.register.disabled');
    }
}