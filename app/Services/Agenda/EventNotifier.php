<?php

namespace App\Services\Agenda;

use App\Models\CalendarEvent;
use App\Models\DeviceToken;
use App\Models\EventNotificationDelivery;
use App\Models\Notification;
use App\Services\FcmService;
use App\Services\TelegramNotificationService;
use App\Support\AiLog as Log;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * F3: avisos de agenda con estado por evento, tipo (recordatorio previo / inicio) y canal.
 *
 * Cada ciclo del scheduler:
 *  1. invalida lo pendiente de eventos cancelados o reprogramados;
 *  2. marca como inciertos los envíos que quedaron a medias (proveedor sin idempotencia);
 *  3. planifica los avisos cuya hora ya llegó (nunca antes) o que se pasaron hace poco;
 *  4. envía lo vencido con reclamo condicional, reintentos acotados y espera creciente.
 *
 * Se ejecuta dentro del comando (producción no tiene worker de colas). El éxito de un canal no
 * cierra los demás. `calendar_events.notified` se mantiene como resumen legado del recordatorio.
 */
class EventNotifier
{
    public const CHANNELS = ['internal', 'email', 'telegram', 'fcm'];

    /** Recuperación: un aviso vencido hace más de esto ya no se envía. */
    public const CATCHUP_MINUTES = 20;

    public const MAX_ATTEMPTS = 3;

    /** Espera antes del intento n+1: el siguiente ciclo, luego 5 minutos. */
    private const BACKOFF_SECONDS = [1 => 0, 2 => 300];

    private const LEASE_SECONDS = 120;

    /** Solo se registran como vencidos los avisos de las últimas 24 h (no se recorre el histórico). */
    private const LOOKBACK_HOURS = 24;

    public function __construct(private FcmService $fcm) {}

    /** @return array<string, int> Resumen del ciclo. */
    public function run(): array
    {
        $now = now();
        $summary = ['invalidated' => $this->invalidate(), 'uncertain' => $this->recoverAbandoned($now), 'planned' => $this->plan($now)];
        $summary['sent'] = $this->deliverDue($now);
        $summary['expired'] = EventNotificationDelivery::whereIn('status', EventNotificationDelivery::WAITING)
            ->where('due_at', '<', $now->copy()->subMinutes(self::CATCHUP_MINUTES))
            ->update(['status' => 'skipped', 'error_category' => 'expired', 'next_attempt_at' => null]);

        return $summary;
    }

    /** Estado de los avisos del horario vigente, para mostrar en el detalle del evento. */
    public static function summary(CalendarEvent $event): array
    {
        return EventNotificationDelivery::where('calendar_event_id', $event->id)->where('schedule_key', self::scheduleKey($event))
            ->orderBy('kind', 'desc')->orderBy('id')->get()
            ->map(fn (EventNotificationDelivery $d) => ['kind' => $d->kind, 'channel' => $d->channel, 'status' => $d->status, 'label' => $d->label(),
                'attempts' => $d->attempts, 'accepted_at' => $d->accepted_at?->toIso8601String()])->all();
    }

    public static function scheduleKey(CalendarEvent $event): string
    {
        return substr(sha1(Carbon::instance($event->start_date)->utc()->format('Y-m-d H:i').'|'.(int) $event->reminder_minutes_before), 0, 16);
    }

    private function invalidate(): int
    {
        $count = 0;
        EventNotificationDelivery::whereIn('status', EventNotificationDelivery::WAITING)->with('event')->get()
            ->each(function (EventNotificationDelivery $delivery) use (&$count) {
                $event = $delivery->event;
                $reason = match (true) {
                    ! $event || $event->status === 'cancelled' => 'cancelled',
                    self::scheduleKey($event) !== $delivery->schedule_key => 'rescheduled',
                    default => null,
                };
                if ($reason) {
                    $count += EventNotificationDelivery::whereKey($delivery->id)->whereIn('status', EventNotificationDelivery::WAITING)
                        ->update(['status' => 'skipped', 'error_category' => $reason, 'next_attempt_at' => null]);
                }
            });

        return $count;
    }

    /**
     * Un envío que quedó «processing» con el lease vencido: el proceso murió. El aviso interno se
     * reintenta (su escritura es atómica); en los proveedores externos no se sabe si llegó y no
     * ofrecen idempotencia, así que queda «uncertain» y no se reenvía (evita duplicados).
     */
    private function recoverAbandoned(Carbon $now): int
    {
        $stale = EventNotificationDelivery::where('status', 'processing')->where('lease_expires_at', '<', $now);
        $retried = (clone $stale)->where('channel', 'internal')->update(['status' => 'retry_wait', 'claim_token' => null, 'next_attempt_at' => $now]);

        return $retried + (clone $stale)->where('channel', '!=', 'internal')->update(['status' => 'uncertain', 'claim_token' => null, 'error_category' => 'lease_expired']);
    }

    private function plan(Carbon $now): int
    {
        $planned = 0;
        CalendarEvent::with('user')
            ->where('status', '!=', 'cancelled')
            ->whereBetween('start_date', [$now->copy()->subHours(self::LOOKBACK_HOURS), $now->copy()->addDays(8)])
            ->get()
            ->each(function (CalendarEvent $event) use ($now, &$planned) {
                $key = self::scheduleKey($event);
                $start = Carbon::instance($event->start_date);
                $moments = ['start' => $start];
                if ((int) $event->reminder_minutes_before > 0) {
                    $moments['reminder'] = $start->copy()->subMinutes((int) $event->reminder_minutes_before);
                }
                $hasHistory = EventNotificationDelivery::where('calendar_event_id', $event->id)->exists();

                foreach ($moments as $kind => $dueAt) {
                    if ($dueAt->gt($now) || $dueAt->lt($now->copy()->subHours(self::LOOKBACK_HOURS))) {
                        continue;
                    }
                    if (EventNotificationDelivery::where(['calendar_event_id' => $event->id, 'kind' => $kind, 'schedule_key' => $key])->exists()) {
                        continue;
                    }
                    // Corte (F3-05): el comando anterior ya avisó de este evento; no se reenvía lo vencido.
                    $skip = match (true) {
                        $event->notified && ! $hasHistory => 'legacy_notified',
                        $dueAt->lt($now->copy()->subMinutes(self::CATCHUP_MINUTES)) => 'expired',
                        default => null,
                    };
                    foreach (self::CHANNELS as $channel) {
                        $reason = $skip ?? $this->unavailable($event, $channel, $now);
                        $planned += (int) $this->createDelivery($event, $kind, $channel, $key, $dueAt, $reason);
                    }
                }
            });

        return $planned;
    }

    /** Por qué un canal no aplica para este usuario, o null si aplica. */
    private function unavailable(CalendarEvent $event, string $channel, Carbon $now): ?string
    {
        $user = $event->user;

        return match ($channel) {
            'internal' => null,
            'email' => ! $user?->email || ! $user->agenda_reminders_enabled ? 'preference'
                : ($now->copy()->setTimezone(config('app.timezone'))->hour < 7 || $now->copy()->setTimezone(config('app.timezone'))->hour >= 18 ? 'quiet_hours' : null),
            'telegram' => $user && TelegramNotificationService::available($user) ? null : 'preference',
            'fcm' => rescue(fn () => DeviceToken::where('user_id', $event->user_id)->exists(), false, false) ? null : 'preference',
        };
    }

    private function createDelivery(CalendarEvent $event, string $kind, string $channel, string $key, Carbon $dueAt, ?string $skipReason): bool
    {
        try {
            EventNotificationDelivery::create([
                'calendar_event_id' => $event->id, 'user_id' => $event->user_id, 'kind' => $kind, 'channel' => $channel,
                'schedule_key' => $key, 'due_at' => $dueAt, 'status' => $skipReason ? 'skipped' : 'pending',
                'error_category' => $skipReason, 'next_attempt_at' => $skipReason ? null : $dueAt,
            ]);

            return true;
        } catch (QueryException) {
            return false; // Otro ciclo lo planificó a la vez: la restricción única lo impide.
        }
    }

    private function deliverDue(Carbon $now): int
    {
        $sent = 0;
        $ids = EventNotificationDelivery::whereIn('status', EventNotificationDelivery::WAITING)
            ->where('next_attempt_at', '<=', $now)
            ->where('due_at', '>=', $now->copy()->subMinutes(self::CATCHUP_MINUTES))
            ->orderBy('due_at')->pluck('id');

        foreach ($ids as $id) {
            $claim = (string) Str::uuid();
            $claimed = EventNotificationDelivery::whereKey($id)->whereIn('status', EventNotificationDelivery::WAITING)
                ->where('next_attempt_at', '<=', $now)
                ->update(['status' => 'processing', 'claim_token' => $claim, 'lease_expires_at' => $now->copy()->addSeconds(self::LEASE_SECONDS),
                    'attempted_at' => $now, 'attempts' => DB::raw('attempts + 1')]);
            if ($claimed !== 1) {
                continue;
            }
            $delivery = EventNotificationDelivery::with('event.user')->find($id);
            $sent += (int) $this->deliver($delivery, $claim, $now);
        }

        return $sent;
    }

    private function deliver(EventNotificationDelivery $delivery, string $claim, Carbon $now): bool
    {
        $event = $delivery->event;
        [$title, $body] = $this->message($event, $delivery->kind, $now);

        try {
            if ($delivery->channel === 'internal') {
                return DB::transaction(function () use ($delivery, $claim, $event, $title, $body, $now) {
                    Notification::create(['user_id' => $event->user_id, 'type' => 'reminder', 'title' => $title, 'message' => $body,
                        'icon' => $delivery->kind === 'start' ? 'fas fa-calendar-check' : 'fas fa-calendar-alt',
                        'color' => $event->color ?: '#3788d8', 'is_important' => true, 'action_url' => '/agenda']);
                    $this->finish($delivery, $claim, ['status' => 'accepted', 'accepted_at' => $now]);
                    if ($delivery->kind === 'reminder') {
                        $event->forceFill(['notified' => true])->saveQuietly();
                    }

                    return true;
                });
            }

            [$outcome, $httpStatus] = match ($delivery->channel) {
                'email' => $this->sendEmail($event, $delivery->kind, $title),
                'telegram' => array_values(TelegramNotificationService::deliver($event->user, $this->telegramText($event, $delivery->kind, $body),
                    ['event_title' => $event->title, 'event_start' => $event->start_date, 'type' => $delivery->kind])),
                'fcm' => [$this->fcm->sendToUser($event->user_id, $title, $body, ['type' => $delivery->kind === 'start' ? 'event_start' : 'event_reminder',
                    'event_id' => $event->id]) ? 'accepted' : 'retry', null],
            };
        } catch (\Throwable $e) {
            Log::error('EventNotifier: fallo al enviar', ['delivery_id' => $delivery->id, 'channel' => $delivery->channel, 'error' => $e->getMessage()]);
            [$outcome, $httpStatus] = ['retry', null];
        }

        return $this->applyOutcome($delivery, $claim, $outcome, $httpStatus, $now);
    }

    private function applyOutcome(EventNotificationDelivery $delivery, string $claim, string $outcome, ?int $httpStatus, Carbon $now): bool
    {
        $changes = match ($outcome) {
            'accepted' => ['status' => 'accepted', 'accepted_at' => $now, 'error_category' => null],
            'skipped' => ['status' => 'skipped', 'error_category' => 'preference'],
            'rejected' => ['status' => 'failed', 'error_category' => 'rejected'],
            default => $delivery->attempts >= self::MAX_ATTEMPTS
                ? ['status' => 'failed', 'error_category' => 'exhausted']
                : ['status' => 'retry_wait', 'error_category' => 'transient', 'next_attempt_at' => $now->copy()->addSeconds(self::BACKOFF_SECONDS[$delivery->attempts] ?? 300)],
        };
        $this->finish($delivery, $claim, $changes + ['http_status' => $httpStatus]);
        if ($changes['status'] === 'failed') {
            Log::warning('EventNotifier: aviso no enviado', ['delivery_id' => $delivery->id, 'channel' => $delivery->channel, 'reason' => $changes['error_category']]);
        }

        return $outcome === 'accepted';
    }

    /** Cierre condicionado al reclamo: un ciclo que perdió el lease no pisa el resultado de otro. */
    private function finish(EventNotificationDelivery $delivery, string $claim, array $changes): void
    {
        EventNotificationDelivery::whereKey($delivery->id)->where('claim_token', $claim)
            ->update($changes + ['claim_token' => null, 'lease_expires_at' => null] + (isset($changes['next_attempt_at']) ? [] : ['next_attempt_at' => null]));
    }

    /** @return array{0: string, 1: ?int} */
    private function sendEmail(CalendarEvent $event, string $kind, string $subject): array
    {
        $user = $event->user;
        if (! $user?->email || ! $user->agenda_reminders_enabled) {
            return ['skipped', null];
        }
        Mail::send($kind === 'start' ? 'emails.event_started' : 'emails.event_reminder', ['event' => $event, 'user' => $user],
            fn ($message) => $message->to($user->email)->subject($subject));

        return ['accepted', null];
    }

    /** F3-06: «comienza en…» con el tiempo que realmente falta al momento de enviar. @return array{0: string, 1: string} */
    private function message(CalendarEvent $event, string $kind, Carbon $now): array
    {
        $start = Carbon::instance($event->start_date)->setTimezone(config('app.timezone'));
        if ($kind === 'start') {
            return ['¡Comienza ahora: '.$event->title.'!', 'El evento inicia a las '.$start->format('H:i')];
        }

        $minutes = (int) max(0, ceil($now->diffInSeconds($start, false) / 60));
        $remaining = match (true) {
            $minutes === 0 => 'Comienza ahora',
            $minutes >= 60 && $minutes % 60 === 0 => 'Comienza en '.($minutes / 60).' hora(s)',
            default => "Comienza en {$minutes} minutos",
        };

        return ['Recordatorio: '.$event->title, $remaining.' — '.$start->format('H:i')];
    }

    private function telegramText(CalendarEvent $event, string $kind, string $body): string
    {
        return $kind === 'start'
            ? "🚀 *¡Comienza ahora!*\n\n📅 *{$event->title}*\n🕐 ".Carbon::instance($event->start_date)->setTimezone(config('app.timezone'))->format('H:i')
            : "⏰ *Recordatorio de Agenda*\n\n📅 *{$event->title}*\n⏳ {$body}";
    }
}
