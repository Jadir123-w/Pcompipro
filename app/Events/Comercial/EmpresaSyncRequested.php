<?php

namespace App\Events\Comercial;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Comercial\Models\Empresa;

class EmpresaSyncRequested
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @var Empresa|null The empresa to sync (null for bulk sync)
     */
    public $empresa;

    /**
     * @var bool Whether to force re-sync even if person already exists
     */
    public $force;

    /**
     * Create a new event instance.
     *
     * @param Empresa|null $empresa
     * @param bool $force
     */
    public function __construct(Empresa $empresa, $force = false)
    {
        $this->empresa = $empresa;
        $this->force = $force;
    }
}
