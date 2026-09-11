<?php

namespace App\Console\Commands;

use App\Models\CalendarEvent;
use App\Models\Notification;
use App\Services\FcmService;
use App\Services\TelegramNotificationService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ScheduleEventNotifications extends Command
{
    protected $signature = 'agenda:schedule-notifications';

    protected $description = 'Envía emails de recordatorio para eventos próximos del calendario';

    // Ventana forward en minutos para anticipar recordatorios próximos
    const WINDOW_MINUTES = 6;
    // Ventana de recuperación hacia atrás: si el scheduler falla un ciclo, reintenta
    const CATCHUP_MINUTES = 20;

    public function __construct(private FcmService $fcm)
    {
        parent::__construct();
    }

    public function handle()
    {
        $now = Carbon::now();
        $this->info("Verificando eventos [{$now->format('Y-m-d H:i:s')}]...");

        $this->sendReminders($now);
        $this->sendStartNotifications($now);

        $this->info('Verificación completada.');
    }

    /**
     * Envía recordatorios cuyo momento cae dentro de la ventana actual.
     * Mira hasta CATCHUP_MINUTES atrás para recuperar recordatorios que el scheduler
     * pudo haber perdido (reinicio del server, lag, etc.).
     */
    private function sendReminders(Carbon $now): void
    {
        $windowEnd   = $now->copy()->addMinutes(self::WINDOW_MINUTES);
        $windowStart = $now->copy()->subMinutes(self::CATCHUP_MINUTES);

        $events = CalendarEvent::with('user')
            ->where('status', '!=', 'cancelled')
            ->where('notified', false)
            ->where('reminder_minutes_before', '>', 0)
            ->where('start_date', '>', $windowStart)
            ->get()
            ->filter(function ($event) use ($windowStart, $windowEnd) {
                $reminderAt = Carbon::parse($event->start_date)
                    ->subMinutes($event->reminder_minutes_before);
                return $reminderAt->between($windowStart, $windowEnd);
            });

        foreach ($events as $event) {
            $notified = false;

            // Canal email (respeta horario 7-18h)
            if ($this->isWithinEmailHours($now)) {
                $sent = $this->sendMail($event, 'emails.event_reminder', 'Recordatorio: '.$event->title);
                if ($sent) {
                    $notified = true;
                    $this->line("Recordatorio email enviado: {$event->title}");
                    Log::info("Recordatorio de agenda enviado por email", ['event_id' => $event->id, 'title' => $event->title]);
                }
            }

            // Canal Telegram (sin restricción horaria — el usuario lo controla)
            $start = Carbon::parse($event->start_date);
            $telegramMsg = "⏰ *Recordatorio de Agenda*\n\n"
                . "📅 *{$event->title}*\n"
                . "🕐 A las " . $start->format('H:i') . "\n"
                . "⏳ Comienza en {$event->reminder_minutes_before} minutos";

            $telegramSent = TelegramNotificationService::send(
                $event->user,
                $telegramMsg,
                ['event_title' => $event->title, 'event_start' => $event->start_date, 'reminder_minutes' => $event->reminder_minutes_before, 'type' => 'reminder']
            );

            if ($telegramSent) {
                $notified = true;
                $this->line("Recordatorio Telegram enviado: {$event->title}");
            }

            // Canal FCM (app móvil): push notification al teléfono
            $minutesText = $event->reminder_minutes_before >= 60
                ? ($event->reminder_minutes_before / 60) . ' hora(s)'
                : $event->reminder_minutes_before . ' minutos';

            $fcmSent = $this->fcm->sendToUser(
                $event->user_id,
                'Recordatorio: ' . $event->title,
                'Comienza en ' . $minutesText . ' a las '
                    . Carbon::parse($event->start_date)
                        ->setTimezone('America/Guatemala')
                        ->format('H:i'),
                [
                    'type'     => 'event_reminder',
                    'event_id' => $event->id,
                ]
            );
            if ($fcmSent) {
                $this->line("Recordatorio FCM enviado: {$event->title}");
                Log::info("Recordatorio de agenda enviado por FCM", ['event_id' => $event->id, 'title' => $event->title]);
            }

            // Canal in-app: siempre, independiente de email/Telegram/FCM
            Notification::create([
                'user_id'      => $event->user_id,
                'type'         => 'reminder',
                'title'        => 'Recordatorio: ' . $event->title,
                'message'      => 'Comienza en ' . $minutesText . ' — '
                    . Carbon::parse($event->start_date)
                        ->setTimezone('America/Guatemala')
                        ->format('H:i'),
                'icon'         => 'fas fa-calendar-alt',
                'color'        => $event->color ?? '#3788d8',
                'is_important' => true,
                'action_url'   => '/agenda',
            ]);
            $notified = true; // la notificación in-app siempre cuenta
            $this->line("Notificación in-app creada: {$event->title}");
            Log::info("Recordatorio de agenda enviado (in-app)", ['event_id' => $event->id, 'title' => $event->title]);

            if ($notified) {
                $event->update(['notified' => true]);
            }
        }
    }

    /**
     * Envía notificación de inicio para eventos que comienzan en la ventana actual.
     * Mira WINDOW_MINUTES hacia atrás para no perder eventos cuando el scheduler
     * corre con retraso (ej: sleep 40 en crontab → llega 40s después del minuto exacto).
     */
    private function sendStartNotifications(Carbon $now): void
    {
        $windowEnd   = $now->copy()->addMinutes(self::WINDOW_MINUTES);
        $windowStart = $now->copy()->subMinutes(self::WINDOW_MINUTES);

        $events = CalendarEvent::with('user')
            ->where('status', '!=', 'cancelled')
            ->where('notified', false)
            ->whereBetween('start_date', [$windowStart, $windowEnd])
            ->get();

        foreach ($events as $event) {
            $notified = false;

            // Canal email (respeta horario 7-18h)
            if ($this->isWithinEmailHours($now)) {
                $sent = $this->sendMail($event, 'emails.event_started', '¡Comienza ahora: '.$event->title.'!');
                if ($sent) {
                    $notified = true;
                    $this->line("Notificación de inicio email enviada: {$event->title}");
                    Log::info("Notificación de inicio de agenda enviada por email", ['event_id' => $event->id, 'title' => $event->title]);
                }
            }

            // Canal Telegram (sin restricción horaria)
            $start = Carbon::parse($event->start_date);
            $telegramMsg = "🚀 *¡Comienza ahora!*\n\n"
                . "📅 *{$event->title}*\n"
                . "🕐 " . $start->format('H:i');

            $telegramSent = TelegramNotificationService::send(
                $event->user,
                $telegramMsg,
                ['event_title' => $event->title, 'event_start' => $event->start_date, 'type' => 'start']
            );

            if ($telegramSent) {
                $notified = true;
                $this->line("Notificación de inicio Telegram enviada: {$event->title}");
            }

            // Canal FCM: push de inicio al teléfono
            $fcmSent = $this->fcm->sendToUser(
                $event->user_id,
                '¡Comienza ahora: ' . $event->title . '!',
                'El evento inicia a las '
                    . Carbon::parse($event->start_date)
                        ->setTimezone('America/Guatemala')
                        ->format('H:i'),
                [
                    'type'     => 'event_start',
                    'event_id' => $event->id,
                ]
            );
            if ($fcmSent) {
                $this->line("Inicio FCM enviado: {$event->title}");
                Log::info("Notificación de inicio enviada por FCM", ['event_id' => $event->id, 'title' => $event->title]);
            }

            // Canal in-app: siempre
            Notification::create([
                'user_id'      => $event->user_id,
                'type'         => 'reminder',
                'title'        => '¡Comienza ahora: ' . $event->title . '!',
                'message'      => 'El evento inicia a las '
                    . Carbon::parse($event->start_date)
                        ->setTimezone('America/Guatemala')
                        ->format('H:i'),
                'icon'         => 'fas fa-calendar-check',
                'color'        => $event->color ?? '#3788d8',
                'is_important' => true,
                'action_url'   => '/agenda',
            ]);
            $notified = true;

            if ($notified) {
                $event->update(['notified' => true]);
            }
        }
    }

    private function sendMail(CalendarEvent $event, string $view, string $subject): bool
    {
        $user = $event->user;

        if (! $user || ! $user->email) {
            return false;
        }

        if (! $user->agenda_reminders_enabled) {
            Log::info("Recordatorios de agenda desactivados para usuario {$user->id} — evento: {$event->title}");
            return false;
        }

        try {
            Mail::send($view, ['event' => $event, 'user' => $user], function ($message) use ($user, $subject) {
                $message->to($user->email)->subject($subject);
            });
            return true;
        } catch (\Exception $e) {
            Log::error("Error enviando email de agenda: {$e->getMessage()}", ['event_id' => $event->id]);
            $this->error("Error al enviar email: {$e->getMessage()}");
            return false;
        }
    }

    private function isWithinEmailHours(Carbon $dateTime): bool
    {
        return $dateTime->hour >= 7 && $dateTime->hour < 18;
    }
}
