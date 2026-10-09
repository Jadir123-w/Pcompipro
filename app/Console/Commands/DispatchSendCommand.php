<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\Tenant\{Configuration, Company, Dispatch, User};
use Modules\Store\Helpers\StorageHelper;
use Modules\ApiPeruDev\Http\Controllers\ServiceDispatchController;
use Carbon\Carbon;
use Auth;

class DispatchSendCommand extends Command
{
    protected $signature = 'dispatch:send';
    protected $description = 'Automatic send of dispatches to SUNAT';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        $now = Carbon::now('America/Bogota');
        $this->info('The command was started');
        Auth::login(User::firstOrFail());

        if (Configuration::firstOrFail()->cron) {
            $configuration = Configuration::firstOrFail();
            $company = Company::firstOrFail();

            $dispatches = Dispatch::where('state_type_id', '01')
                ->whereNull('ticket')
                ->get();

            $this->info('Cantidad de guias para enviar: ' . $dispatches->count());

            if ($dispatches->isEmpty()) {
                $this->info('No dispatches pending to send');
            }

            $serviceController = new ServiceDispatchController();

            foreach ($dispatches as $dispatch) {
                try {
                    $xml_signed = (new StorageHelper())->getXmlSigned($dispatch->filename);
                    $res = $serviceController->getServiceInitial()->send(
                        $dispatch->filename,
                        $xml_signed
                    );
                    if ($res['success']) {
                        $data = $res['data'];
                        Log::info("Dispatch { $dispatch->filename } send response: ", $data);
                        if (is_array($data) && array_key_exists('numTicket', $data)) {
                            $ticket = $data['numTicket'];
                            $reception_date = $data['fecRecepcion'];
                            $updated = Dispatch::where('id', $dispatch->id)
                                ->update([
                                    'ticket' => $ticket,
                                    'reception_date' => $reception_date,
                                    'state_type_id' => '03'
                                ]);
                            $this->info("Se obtuvo el nro. de ticket correctamente. Ticket: {$ticket}, Fecha de recepción: {$reception_date}, guia: {$dispatch->series}-{$dispatch->number}");

                        } else {
                            Log::error('No se obtuvo el numero de ticket', $res);
                            $this->error('No se obtuvo el numero de ticket: '. json_encode($res));
                        }
                    } else {
                        Log::error('No se obtuvo respuesta esperada del envio', $res);
                        $this->error("No se obtuvo respuesta esperada del envio: {$dispatch->series}-{$dispatch->number}: verify logs");
                    }
                } catch (\Exception $e) {
                    $this->error("Error sending dispatch {$dispatch->series}-{$dispatch->number}: " . $e->getMessage());
                }
            }
        } else {
            $this->info('The crontab is disabled');
        }

        $this->info('The command is finished');
    }
}
