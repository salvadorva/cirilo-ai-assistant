<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Ejecutar el comando para programar notificaciones cada minuto
        $schedule->command('agenda:schedule-notifications')->everyMinute();

        // Limpiar audios dinámicos antiguos diariamente a las 3:00 AM
        $schedule->command('audio:clean --days=7')->dailyAt('03:00');

        // Sumarizar conversaciones inactivas +2h con mensajes sin resumir
        $schedule->command('memory:summarize-stale')->hourly();
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
