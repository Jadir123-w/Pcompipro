<?php

namespace App\Providers;

use App\Events\Tenant\UserRegistered;
use App\Listeners\Tenant\SendWelcomeEmailNotification;
use App\Listeners\TenantCreatedListener;
use Hyn\Tenancy\Events\Websites\Created;
use Illuminate\Support\Facades\Event;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array
     */
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],
        Created::class => [
            TenantCreatedListener::class,
        ],
        UserRegistered::class => [
          SendWelcomeEmailNotification::class,
        ],
        \App\Events\Comercial\EmpresaSyncRequested::class => [
            \App\Listeners\Comercial\SyncEmpresaToPersons::class,
        ],
    ];

    /**
     * Register any events for your application.
     *
     * @return void
     */
    public function boot()
    {
        parent::boot();

        //
    }
}
