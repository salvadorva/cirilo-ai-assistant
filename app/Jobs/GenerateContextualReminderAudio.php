<?php

namespace App\Jobs;

use App\Services\Reminders\ContextualReminderAudio;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/** Genera el audio privado de un recordatorio sin bloquear el envío de texto. */
class GenerateContextualReminderAudio implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 45;

    public function __construct(public string $reminderId) {}

    public function handle(ContextualReminderAudio $audio): void
    {
        $audio->generate($this->reminderId);
    }
}
