<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use App\Jobs\CalcularMoraAutomatica;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Calcular mora automáticamente diariamente a las 00:01 AM
        $schedule->job(new CalcularMoraAutomatica())
            ->dailyAt('00:01')
            ->name('calcular-mora-automatica')
            ->onOneServer();

        // Opcionalmente: también ejecutar cada hora para mayor precisión si es necesario
        // $schedule->job(new CalcularMoraAutomatica())
        //     ->hourly()
        //     ->name('calcular-mora-automatica-hourly');
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
