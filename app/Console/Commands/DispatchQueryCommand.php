<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use App\Models\Tenant\{Configuration, Dispatch};
use Modules\ApiPeruDev\Http\Controllers\ServiceDispatchController;

class DispatchQueryCommand extends Command
{
    protected $signature = 'dispatch:query';
    protected $description = 'Query status of dispatches sent to SUNAT';

    public function handle()
    {
        if (Configuration::firstOrFail()->cron) {
            $this->info('Querying dispatch status...');

            $dispatches = Dispatch::query()
                ->where('state_type_id', '03')
                ->whereNotNull('ticket')
                ->whereNull('qr_url')
                ->get();

            if ($dispatches->isEmpty()) {
                $this->info('No dispatches pending status query');
                return;
            }

            $this->info('Cantidad de guias para consultar: ' . $dispatches->count());

            $serviceController = new ServiceDispatchController();

            foreach ($dispatches as $dispatch) {
                try {
                    $result = $serviceController->statusTicket($dispatch->external_id, true);
                    Log::info("aqui Dispatch {$dispatch->series}-{$dispatch->number} status query response: ", $result);

                    if ($result['success']) {
                        $status = $result['data']['state_type_id'];
                        $statusText = $this->getStatusText($status);

                        $this->info("Dispatch {$dispatch->series}-{$dispatch->number}: {$statusText} - {$result['message']}");
                    } else {
                        $this->error("Failed to query dispatch {$dispatch->series}-{$dispatch->number}: {$result['message']}");
                    }
                } catch (\Exception $e) {
                    Log::error("Error querying dispatch {$dispatch->series}-{$dispatch->number}: " . $e->getMessage());
                    $this->error("Error querying dispatch {$dispatch->series}-{$dispatch->number}: " . $e->getMessage());
                }
                sleep(1);
            }
        } else {
            $this->info('The crontab is disabled');
        }

        $this->info('The command is finished');
    }

    private function getStatusText($stateTypeId)
    {
        $statuses = [
            '01' => 'Registrado',
            '03' => 'Enviado',
            '05' => 'Aceptado',
            '09' => 'Rechazado'
        ];

        return $statuses[$stateTypeId] ?? 'Desconocido';
    }
}
