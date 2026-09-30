<?php

namespace App\Console\Commands;

use App\Services\Reminders\ReminderDispatcher;
use Illuminate\Console\Command;

class DispatchReminders extends Command
{
    protected $signature = 'reminders:dispatch';

    protected $description = 'Escanea recordatorios contextuales y rutinas (si están habilitados) y encola despachos durables';

    public function handle(ReminderDispatcher $dispatcher): int
    {
        if (! $dispatcher->contextualEnabled() && ! $dispatcher->routinesEnabled()) {
            $this->line('Despacho de recordatorios deshabilitado.');

            return self::SUCCESS;
        }
        $this->info('Despachos encolados: '.$dispatcher->scan());

        return self::SUCCESS;
    }
}
