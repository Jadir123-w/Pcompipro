<?php

namespace App\Listeners\Tenant;

use App\Events\Tenant\UserRegistered;
use Exception;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class SendWelcomeEmailNotification implements ShouldQueue
{
  use InteractsWithQueue;

  public $queue = 'emails';
  public $tries = 3;

  /**
   * @param UserRegistered $event
   * @return void
   */
  public function handle(UserRegistered $event)
  {
    try {
      Mail::send('tenant.mails.welcome', [
        'user' => (Object)[
          'userName' => $event->userName,
          'userEmail' => $event->userEmail,
          'userEstablishmentDescription' => $event->userEstablishmentDescription,
          'userType' => $event->userType,
          'userUrlFqdn' => $event->getTenantFQDN(),
        ]
      ], function ($message) use ($event) {
        $message->to($event->userEmail, $event->userName)
          ->subject('¡Bienvenido al Sistema!');
      });

      Log::info("Email de bienvenida enviado a: {$event->userEmail} de {$event->getTenantFQDN()}");
    } catch (Exception $e) {
      Log::error("Error al enviar email de bienvenida: " . $e->getMessage());
      throw $e;
    }
  }

  public function failed(UserRegistered $event, $exception)
  {
    Log::error("Falló el envío de email de bienvenida después de varios intentos", [
      'user_id' => $event->userId,
      'tenant_fqdn' => $event->getTenantFQDN(),
      'error' => $exception->getMessage()
    ]);
  }

}
