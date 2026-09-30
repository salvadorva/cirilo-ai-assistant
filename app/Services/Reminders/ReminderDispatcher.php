<?php

namespace App\Services\Reminders;

use App\Jobs\DispatchReminderDelivery;
use App\Models\ContextualReminder;
use App\Models\DeviceToken;
use App\Models\FocusSlot;
use App\Models\ReminderDelivery;
use App\Services\AiTelemetry;
use App\Services\Reminders\Push\PushResult;
use App\Services\Reminders\Push\PushTransport;
use App\Services\TtsService;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Despacho durable (RC2). El escáner corre cada minuto y es idempotente:
 * crea despachos únicos por ocurrencia/revisión/destino, recupera claims
 * abandonados y re-encola lo que no llegó a la cola. Cada job reclama su
 * despacho con lease, revalida el origen y guarda el resultado con fencing.
 * Ninguna transacción queda abierta durante la llamada de red.
 */
class ReminderDispatcher
{
    public function __construct(
        private PushTransport $push,
        private ReminderDestinations $destinations,
        private TtsService $tts,
        private ContextualReminderAudio $audio,
    ) {}

    public function contextualEnabled(): bool
    {
        return (bool) config('reminders.contextual_dispatch_enabled');
    }

    public function routinesEnabled(): bool
    {
        return (bool) config('reminders.routine_dispatch_enabled');
    }

    /** Un ciclo del scheduler. Devuelve cuántos despachos se encolaron. */
    public function scan(): int
    {
        $now = CarbonImmutable::now('UTC');
        if ($this->contextualEnabled()) {
            $this->expireReminders($now);
            $this->supersedeStaleContextual();
        }
        $this->skipExpiredDeliveries($now);
        $this->recoverAbandonedClaims($now);
        if ($this->contextualEnabled()) {
            $this->createContextualDeliveries($now);
        }
        if ($this->routinesEnabled()) {
            $this->createRoutineDeliveries($now);
        }

        return $this->enqueueReady($now);
    }

    /** Ejecutado por el job. Sin efecto si otro worker ya reclamó o el despacho no está listo. */
    public function deliver(int $deliveryId): void
    {
        $claim = (string) Str::uuid();
        $now = CarbonImmutable::now('UTC');
        $claimed = ReminderDelivery::whereKey($deliveryId)
            ->whereIn('status', ReminderDelivery::WAITING)
            ->where('next_attempt_at', '<=', $this->db($now))
            ->update(['status' => ReminderDelivery::PROCESSING, 'claim_token' => $claim, 'in_flight_since' => null,
                'lease_expires_at' => $this->db($now->addSeconds(config('reminders.dispatch.lease_seconds'))), 'updated_at' => now()]);
        if ($claimed !== 1) {
            return;
        }
        $delivery = ReminderDelivery::findOrFail($deliveryId);

        if (! $delivery->expires_at->gt($now)) {
            $this->finish($delivery, $claim, ['status' => ReminderDelivery::SKIPPED, 'error_category' => 'expired']);

            return;
        }
        [$device, $data] = $delivery->source_type === 'contextual'
            ? $this->prepareContextual($delivery)
            : $this->prepareRoutine($delivery);
        if ($device === null) {
            $this->finish($delivery, $claim, ['status' => ReminderDelivery::SKIPPED, 'error_category' => $data]);

            return;
        }

        // Inicio del intento persistido antes de la red: un claim abandonado desde aquí es incierto.
        $started = ReminderDelivery::whereKey($deliveryId)->where('claim_token', $claim)->update([
            'attempts' => $delivery->attempts + 1, 'attempted_at' => $this->db($now), 'in_flight_since' => $this->db($now), 'updated_at' => now(),
        ]);
        if ($started !== 1) {
            return;
        }
        $delivery->attempts++;

        $ttl = max(0, $delivery->expires_at->getTimestamp() - $now->getTimestamp());
        try {
            $result = $this->push->send($device, $data, $ttl);
        } catch (\Throwable $e) {
            Log::error('reminder_delivery.transport_exception', ['delivery_id' => $deliveryId, 'exception' => class_basename($e)]);
            $result = PushResult::uncertain('transport_exception');
        }
        $this->applyResult($delivery, $claim, $device, $result);
    }

    private function applyResult(ReminderDelivery $delivery, string $claim, DeviceToken $device, PushResult $result): void
    {
        $now = CarbonImmutable::now('UTC');
        $base = ['http_status' => $result->httpStatus, 'error_category' => $result->category];

        $changes = match ($result->outcome) {
            PushResult::ACCEPTED => ['status' => ReminderDelivery::ACCEPTED, 'accepted_at' => $this->db($now),
                'provider_message_id' => $result->messageId, 'error_category' => null] + $base,
            PushResult::RETRYABLE => $this->retryOrFail($delivery, $now, $result->retryAfter) + $base,
            PushResult::UNCERTAIN => $this->uncertainOutcome($delivery, $now) + $base,
            default => ['status' => ReminderDelivery::FAILED] + $base,
        };
        if (! $this->finish($delivery, $claim, $changes)) {
            Log::warning('reminder_delivery.result_fenced', ['delivery_id' => $delivery->id, 'outcome' => $result->outcome]);

            return;
        }

        if ($result->outcome === PushResult::UNREGISTERED) {
            // Deshabilitar solo ese token; el recordatorio sigue pendiente hasta caducar.
            $device->forceFill(['disabled_at' => now()])->save();
        }
        if ($result->outcome === PushResult::AUTH) {
            Log::critical('reminder_delivery.auth_failure', ['delivery_id' => $delivery->id, 'http_status' => $result->httpStatus]);
        }
        if ($result->outcome === PushResult::ACCEPTED && $delivery->source_type === 'routine') {
            // last_sent_at representa ahora la última aceptación FCM de la rutina.
            FocusSlot::whereKey($delivery->focus_slot_id)
                ->where(fn ($q) => $q->whereNull('last_sent_at')->orWhere('last_sent_at', '<', now()))
                ->update(['last_sent_at' => now()]);
        }
    }

    /** Guarda el resultado solo si el claim sigue siendo nuestro (fencing). */
    private function finish(ReminderDelivery $delivery, string $claim, array $changes): bool
    {
        $changes += ['claim_token' => null, 'lease_expires_at' => null, 'in_flight_since' => null, 'updated_at' => now()];
        $updated = ReminderDelivery::whereKey($delivery->id)->where('claim_token', $claim)->update($changes);
        if ($updated === 1) {
            Log::info('reminder_delivery.'.$changes['status'], $this->logContext($delivery) + ['error_category' => $changes['error_category'] ?? null]);
        }

        return $updated === 1;
    }

    private function retryOrFail(ReminderDelivery $delivery, CarbonImmutable $now, ?int $retryAfter): array
    {
        $next = $now->addSeconds($this->backoff($delivery->attempts, $retryAfter));
        if ($delivery->attempts < config('reminders.dispatch.max_attempts') && $next->lt($delivery->expires_at)) {
            return ['status' => ReminderDelivery::RETRY_WAIT, 'next_attempt_at' => $this->db($next), 'enqueued_at' => null];
        }

        return ['status' => ReminderDelivery::FAILED];
    }

    /** Como máximo un reintento incierto dentro del presupuesto total; Android deduplica. */
    private function uncertainOutcome(ReminderDelivery $delivery, CarbonImmutable $now): array
    {
        $next = $now->addSeconds($this->backoff($delivery->attempts, null));
        if ($delivery->uncertain_count === 0 && $delivery->attempts < config('reminders.dispatch.max_attempts') && $next->lt($delivery->expires_at)) {
            return ['status' => ReminderDelivery::RETRY_WAIT, 'next_attempt_at' => $this->db($next), 'enqueued_at' => null,
                'uncertain_count' => $delivery->uncertain_count + 1];
        }

        return ['status' => ReminderDelivery::UNCERTAIN];
    }

    /** Exponencial desde un minuto, con jitter y respetando Retry-After. */
    private function backoff(int $attempts, ?int $retryAfter): int
    {
        $delay = max($retryAfter ?? 0, config('reminders.dispatch.backoff_base_seconds') * 2 ** max(0, $attempts - 1));

        return $delay + random_int(0, (int) floor($delay * config('reminders.dispatch.jitter_ratio')));
    }

    // ---- Preparación y revalidación por origen ----

    /** @return array{0: ?DeviceToken, 1: array|string} destino y payload, o null y motivo */
    private function prepareContextual(ReminderDelivery $delivery): array
    {
        $reminder = $delivery->reminder;
        if (! $reminder || $reminder->state !== ContextualReminder::PENDING || $reminder->version !== $delivery->revision) {
            return [null, 'superseded'];
        }
        if ($reminder->isPastExpiry()) {
            return [null, 'expired'];
        }
        $device = $delivery->device;
        if (! $device || ! $this->destinations->isContextualDestination($device, $reminder->user_id)) {
            return [null, 'destination_unavailable'];
        }

        return [$device, self::contextualPayload($reminder, $this->audio->ready($reminder))];
    }

    /** Payload data-only v1: genérico por privacidad; el detalle se consulta autenticado. */
    public static function contextualPayload(ContextualReminder $reminder, bool $audioReady = false): array
    {
        return [
            'type' => 'contextual_reminder',
            'schema_version' => '1',
            'reminder_id' => $reminder->id,
            'occurrence_id' => $reminder->id,
            'version' => (string) $reminder->version,
            'scheduled_at' => ContextualReminderService::utc($reminder->scheduled_at),
            'expires_at' => ContextualReminderService::utc($reminder->expires_at),
            'title' => 'Cirilo',
            'body' => 'Tienes un recordatorio acordado',
            // Disponibilidad al enviar; terminar el TTS después no genera otro push.
            'audio_ready' => $audioReady ? 'true' : 'false',
        ];
    }

    private function prepareRoutine(ReminderDelivery $delivery): array
    {
        $slot = $delivery->slot;
        if (! $slot || ! $slot->enabled) {
            return [null, 'superseded'];
        }
        $occurrence = $this->routineOccurrence($slot, CarbonImmutable::instance($delivery->scheduled_at));
        if ($occurrence === null || ! $occurrence->equalTo($delivery->scheduled_at)) {
            return [null, 'superseded']; // hora o días editados después de crear el despacho
        }
        $device = $delivery->device;
        if (! $device || $device->user_id !== $slot->user_id || $device->disabled_at) {
            return [null, 'destination_unavailable'];
        }

        return [$device, [
            'type' => 'focus_message',
            'slot_id' => (string) $slot->id,
            'audio_url' => $this->routineAudioUrl($slot) ?? '',
            'title' => $slot->title,
            'body' => $slot->message,
        ]];
    }

    /** Misma política de audio cacheado que focus:send-messages; si TTS falla, solo texto. */
    private function routineAudioUrl(FocusSlot $slot): ?string
    {
        if (! $slot->with_audio) {
            return null;
        }
        if ($slot->audio_path && Storage::disk('public')->exists($slot->audio_path)) {
            return Storage::disk('public')->url($slot->audio_path);
        }
        $path = sprintf('audio/focus/slot-%d-%s.mp3', $slot->id, substr(md5($slot->message.'|'.$slot->voice), 0, 8));
        $url = app(AiTelemetry::class)->forUser($slot->user_id, fn () => $this->tts->generateMp3($slot->message, $slot->voice, $path));
        if ($url) {
            $slot->update(['audio_path' => $path]);
        } else {
            Log::warning('reminder_delivery.routine_tts_failed', ['slot_id' => $slot->id]);
        }

        return $url ?: null;
    }

    /** Ocurrencia de la rutina en el día local de $reference, o null si ese día no aplica. */
    private function routineOccurrence(FocusSlot $slot, CarbonImmutable $reference): ?CarbonImmutable
    {
        $tz = config('reminders.routine.timezone');
        $local = $reference->setTimezone($tz);
        if (! in_array($local->isoWeekday(), $slot->days ?? [], true)) {
            return null;
        }

        return CarbonImmutable::createFromFormat('Y-m-d H:i', $local->format('Y-m-d').' '.$slot->time, $tz)->utc();
    }

    // ---- Pasos del escáner ----

    /** Vencimiento sin depender de una acción: cierra el pendiente e incrementa versión. */
    private function expireReminders(CarbonImmutable $now): void
    {
        ContextualReminder::where('state', ContextualReminder::PENDING)->where('expires_at', '<=', $this->db($now))
            ->limit(config('reminders.dispatch.batch'))->get()
            ->each(function (ContextualReminder $reminder) use ($now) {
                ContextualReminder::whereKey($reminder->id)->where('version', $reminder->version)->where('state', ContextualReminder::PENDING)
                    ->update(['state' => ContextualReminder::EXPIRED, 'version' => $reminder->version + 1, 'expired_at' => $this->db($now),
                        'closed_by' => 'system', 'updated_at' => now()]);
            });
    }

    private function supersedeStaleContextual(): void
    {
        ReminderDelivery::where('source_type', 'contextual')->whereIn('status', ReminderDelivery::WAITING)->with('reminder')
            ->limit(config('reminders.dispatch.batch'))->get()
            ->each(function (ReminderDelivery $delivery) {
                $reminder = $delivery->reminder;
                if (! $reminder || $reminder->state !== ContextualReminder::PENDING || $reminder->version !== $delivery->revision) {
                    $category = $reminder?->state === ContextualReminder::EXPIRED ? 'expired' : 'superseded';
                    $this->skipWaiting(ReminderDelivery::whereKey($delivery->id), $category);
                }
            });
    }

    private function skipExpiredDeliveries(CarbonImmutable $now): void
    {
        $this->skipWaiting(ReminderDelivery::where('expires_at', '<=', $this->db($now)), 'expired');
    }

    public function skipWaiting($query, string $category): int
    {
        return $query->whereIn('status', ReminderDelivery::WAITING)->update(['status' => ReminderDelivery::SKIPPED,
            'error_category' => $category, 'enqueued_at' => null, 'updated_at' => now()]);
    }

    private function recoverAbandonedClaims(CarbonImmutable $now): void
    {
        ReminderDelivery::where('status', ReminderDelivery::PROCESSING)->where('lease_expires_at', '<', $this->db($now))
            ->limit(config('reminders.dispatch.batch'))->get()
            ->each(function (ReminderDelivery $delivery) use ($now) {
                // Sin inicio de red registrado, nunca se envió: vuelve a la cola sin contar como incierto.
                $changes = $delivery->in_flight_since === null
                    ? ['status' => ReminderDelivery::PENDING, 'next_attempt_at' => $this->db($now), 'enqueued_at' => null]
                    : $this->uncertainOutcome($delivery, $now) + ['error_category' => 'abandoned_claim'];
                $this->finish($delivery, $delivery->claim_token, $changes);
            });
    }

    private function createContextualDeliveries(CarbonImmutable $now): void
    {
        ContextualReminder::where('state', ContextualReminder::PENDING)
            ->where('scheduled_at', '<=', $this->db($now))->where('expires_at', '>', $this->db($now))
            ->orderBy('scheduled_at')->limit(config('reminders.dispatch.batch'))->get()
            ->each(function (ContextualReminder $reminder) use ($now) {
                $devices = $this->destinations->contextual($reminder->user_id);
                if ($devices->isEmpty()) {
                    Log::warning('reminder_delivery.no_destination', ['reminder_id' => $reminder->id, 'version' => $reminder->version]);
                }
                foreach ($devices as $device) {
                    $this->createDelivery([
                        'source_type' => 'contextual', 'contextual_reminder_id' => $reminder->id, 'occurrence_key' => $reminder->id,
                        'revision' => $reminder->version, 'device_token_id' => $device->id, 'destination_key' => 'device:'.$device->id,
                        'scheduled_at' => $reminder->scheduled_at, 'expires_at' => $reminder->expires_at, 'next_attempt_at' => $now,
                    ]);
                }
            });
    }

    private function createRoutineDeliveries(CarbonImmutable $now): void
    {
        $cutover = config('reminders.routine_dispatch_cutover_at');
        if (! $cutover) {
            Log::warning('reminder_delivery.routine_cutover_missing');

            return;
        }
        $cutover = CarbonImmutable::parse($cutover)->utc();
        $window = config('reminders.routine.catchup_minutes');

        FocusSlot::where('enabled', true)->get()->each(function (FocusSlot $slot) use ($now, $cutover, $window) {
            $occurrence = $this->routineOccurrence($slot, $now);
            if ($occurrence === null || $occurrence->lt($cutover) || $now->lt($occurrence) || $now->gt($occurrence->addMinutes($window))) {
                return;
            }
            $key = 'slot:'.$slot->id.':'.ContextualReminderService::utc($occurrence);
            if (ReminderDelivery::where('source_type', 'routine')->where('occurrence_key', $key)->exists()) {
                return;
            }
            // Ya emitida por el camino legado antes del corte efectivo: no reenviar.
            if ($slot->last_sent_at && CarbonImmutable::instance($slot->last_sent_at)->gte($occurrence)) {
                return;
            }
            foreach ($this->destinations->routine($slot->user_id) as $device) {
                $this->createDelivery([
                    'source_type' => 'routine', 'focus_slot_id' => $slot->id, 'occurrence_key' => $key, 'revision' => 1,
                    'device_token_id' => $device->id, 'destination_key' => 'device:'.$device->id, 'scheduled_at' => $occurrence,
                    'expires_at' => $occurrence->addMinutes(config('reminders.routine.expiry_minutes')), 'next_attempt_at' => $now,
                ]);
            }
        });
    }

    /** La restricción única resuelve ticks concurrentes: el perdedor no duplica. */
    private function createDelivery(array $attributes): void
    {
        $unique = array_intersect_key($attributes, array_flip(['source_type', 'occurrence_key', 'revision', 'destination_key']));
        if (ReminderDelivery::where($unique)->exists()) {
            return;
        }
        try {
            ReminderDelivery::create($attributes + ['status' => ReminderDelivery::PENDING]);
        } catch (UniqueConstraintViolationException) {
            // Otro escáner lo creó primero.
        }
    }

    private function enqueueReady(CarbonImmutable $now): int
    {
        $stale = $this->db($now->subSeconds(config('reminders.dispatch.requeue_after_seconds')));
        $ready = ReminderDelivery::whereIn('status', ReminderDelivery::WAITING)->where('next_attempt_at', '<=', $this->db($now))
            ->where(fn ($q) => $q->whereNull('enqueued_at')->orWhere('enqueued_at', '<=', $stale))
            ->orderBy('next_attempt_at')->limit(config('reminders.dispatch.batch'))->pluck('id');

        foreach ($ready as $id) {
            // Marca condicional: dos escáneres no encolan el mismo despacho en el mismo ciclo.
            $marked = ReminderDelivery::whereKey($id)->whereIn('status', ReminderDelivery::WAITING)
                ->where(fn ($q) => $q->whereNull('enqueued_at')->orWhere('enqueued_at', '<=', $stale))
                ->update(['enqueued_at' => $this->db($now)]);
            if ($marked === 1) {
                DispatchReminderDelivery::dispatch($id)->afterCommit();
            }
        }

        return $ready->count();
    }

    private function db(CarbonImmutable $date): string
    {
        return $date->utc()->format('Y-m-d H:i:s');
    }

    private function logContext(ReminderDelivery $delivery): array
    {
        return ['delivery_id' => $delivery->id, 'source_type' => $delivery->source_type, 'reminder_id' => $delivery->contextual_reminder_id,
            'slot_id' => $delivery->focus_slot_id, 'revision' => $delivery->revision, 'device_token_id' => $delivery->device_token_id,
            'attempts' => $delivery->attempts];
    }
}
