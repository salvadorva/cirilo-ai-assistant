<?php

namespace App\Jobs;

use App\Models\CalendarEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendEventReminderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $event;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    /**
     * Hora de inicio al encolar. Si el evento se movió, el job ya no aplica. Los jobs encolados antes
     * de este cambio no la tienen y tampoco envían: los avisos los gestiona agenda:schedule-notifications (F3).
     */
    protected ?string $scheduledStart = null;

    public function __construct(CalendarEvent $event)
    {
        $this->event = $event;
        $this->scheduledStart = $event->start_date?->toIso8601String();
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        // Verificar que el evento aún existe y no ha sido cancelado
        $event = CalendarEvent::find($this->event->id);

        if (! $event || $event->status === 'cancelled') {
            return;
        }

        if ($this->scheduledStart === null || $event->start_date?->toIso8601String() !== $this->scheduledStart) {
            return;
        }

        // Verificar que el evento no ha sido notificado ya
        if ($event->notified) {
            return;
        }

        // Marcar como notificado
        $event->update(['notified' => true]);

        // Enviar correo electrónico de recordatorio
        $user = $event->user;

        if ($user && $user->email) {
            // Verificar si el usuario tiene habilitadas las notificaciones por email
            if (! $user->email_notifications_enabled) {
                \Log::info("Usuario {$user->id} ({$user->email}) tiene deshabilitadas las notificaciones por email - Evento: {$event->title}");

                return;
            }

            Mail::send('emails.event_reminder', ['event' => $event, 'user' => $user], function ($message) use ($user, $event) {
                $message->to($user->email)
                    ->subject('Recordatorio: '.$event->title);
            });
        }
    }
}
