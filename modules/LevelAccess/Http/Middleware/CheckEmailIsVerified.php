<?php

namespace Modules\LevelAccess\Http\Middleware;

use Closure;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\URL;
use App\Models\Tenant\Configuration;


class CheckEmailIsVerified
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse|null
     */
    public function handle($request, Closure $next)
    {
        if ($request->ajax()) return $next($request);

        if(Configuration::getDataToCheckGuestEmail()->applyCheckGuestEmail())
        {
            if($request->user()->isNotVerifiedUserEmail()) return redirect()->route('tenant.not-verified-email.index');
        }

        return $next($request);
    }

}
