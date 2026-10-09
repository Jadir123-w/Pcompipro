<?php

namespace App\Console;

use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Console\Scheduling\Schedule;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        //
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule) {
        $schedule->command('tenancy:run tenant:run')
            ->everyMinute();
        // Se ejecutara por hora guardando estado de cpu y memoria (windows/linux)
        $schedule->command('status:server')->hourly();
        // Llena las tablas para libro mayor - Se desactiva CMAR - buscar opcion de url
        // $schedule->command('account_ledger:fill')->hourly();

        //restaurar base de datos demo para restaurant
        // $schedule->command('database:restoredemo')->dailyAt('23:50');

        // Procesar la cola cada minuto
        $schedule->command('queue:work --queue=emails --stop-when-empty --tries=3')
          ->everyMinute()
          ->withoutOverlapping();

        // ──────────────────────────────────────────────────────────────────
        // MKT-01 Email Triage — orquestador (ET4)
        //
        // Cada 5 minutos: lee la bandeja IMAP de cada tenant + clasifica con
        // IA los correos nuevos + genera borradores cuando aplica.
        //
        // El comando itera todos los tenants registrados internamente, así
        // que NO necesita envolverse en `tenancy:run`.
        //
        // Notas de operación:
        //   - withoutOverlapping evita pisar runs si la IA está lenta
        //   - runInBackground permite que el scheduler siga corriendo otros
        //     jobs mientras este avanza
        //   - El log queda en storage/logs/email-triage.log para auditoría
        // ──────────────────────────────────────────────────────────────────
        $schedule->command('marketing:email-triage:run')
            ->everyFiveMinutes()
            ->withoutOverlapping(15)
            ->runInBackground()
            ->appendOutputTo(storage_path('logs/email-triage.log'));

        // ──────────────────────────────────────────────────────────────────
        // COM-07 — Detección diaria de oportunidades de recuperación
        //
        // Corre de madrugada para que las ejecutivas encuentren el panel al
        // día al empezar la jornada. Es idempotente: si un día falla y corre
        // dos veces al siguiente, no duplica oportunidades.
        // ──────────────────────────────────────────────────────────────────
        $schedule->command('marketing:com07:detectar')
            ->dailyAt('05:00')
            ->withoutOverlapping(60)
            ->runInBackground()
            ->appendOutputTo(storage_path('logs/com07-deteccion.log'));
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands() {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
