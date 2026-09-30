<?php

namespace App\Jobs;

use App\Services\Reminders\ReminderDispatcher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Intenta un despacho de recordatorio. Solo lleva el ID: el contenido se lee
 * de la base al ejecutar. Los reintentos los gobierna reminder_deliveries,
 * no la cola, por eso tries = 1.
 */
class DispatchReminderDelivery implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout;

    public function __construct(public int $deliveryId)
    {
        $this->timeout = (int) config('reminders.dispatch.job_timeout', 45);
    }

    public function handle(ReminderDispatcher $dispatcher): void
    {
        $dispatcher->deliver($this->deliveryId);
    }
}
