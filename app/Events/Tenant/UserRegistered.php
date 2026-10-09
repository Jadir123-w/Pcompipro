<?php

namespace App\Events\Tenant;

use App\Models\Tenant\User;
use Hyn\Tenancy\Environment;
use Hyn\Tenancy\Models\Hostname;
use Illuminate\Foundation\Events\Dispatchable;

class UserRegistered
{
  use Dispatchable;

  protected $tenantFqdn;
  public $tenantConnection;
  public $userId;
  public $userName;
  public $userEmail;
  public $userType;
  public $userEstablishmentDescription;
  public $userUrlFqdn;



  public function __construct(User $user)
  {
    $this->userId = $user->id;
    $this->userName = $user->name;
    $this->userEmail = $user->email;
    $this->userType = $user->type;
    $this->userEstablishmentDescription = optional($user->establishment)->description ?: 'N/A';

    // Guardar el FQDN del tenant actual
    $tenancy = app(Environment::class);
    $hostname = $tenancy->hostname();
    $this->tenantFqdn = $hostname ? $hostname->fqdn : null;

    // Guardar el nombre de la conexión
    $this->tenantConnection = $user->getConnectionName();
  }

  /**
   * Obtener el usuario completo cuando se necesite
   */
  public function getUser()
  {
    if ($this->tenantFqdn) {
      $hostname = Hostname::on('system')->where('fqdn', $this->tenantFqdn)->first();

      if ($hostname) {
        $tenancy = app(Environment::class);
        $tenancy->tenant($hostname->website);
      }
    }

    return User::on($this->tenantConnection)->find($this->userId);
  }

  public function getTenantFQDN()
  {
    return $this->tenantFqdn;
  }

}
