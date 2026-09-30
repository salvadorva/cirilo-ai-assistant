<?php

namespace App\Services\Agenda;

use App\Models\AgendaOperation;
use App\Models\CalendarEvent;
use App\Models\User;
use App\Services\NextcloudCalendarService;
use App\Support\AiLog as Log;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * F2-01: validación, propiedad y persistencia de la agenda, compartidas por chat, web y API móvil.
 *
 * Entrada normalizada (cada cliente traduce su formato):
 *   title, description, location, category, color, reminder_minutes_before,
 *   start (Carbon), end (?Carbon), all_day (bool), recurrence_type, recurrence_end_date,
 *   series: ['type' => daily|weekdays|weekly|custom, 'days' => [...], 'until' => ?Carbon] (solo chat).
 */
class AgendaService
{
    /** Una misma cita (título y hora) creada hace menos de esto se trata como posible duplicado. */
    public const DUPLICATE_WINDOW_MINUTES = 10;

    public const MAX_SERIES_DAYS = 90;

    public const MAX_REMINDER_MINUTES = 10080;

    private const DAY_MAP = [
        'monday' => 1, 'tuesday' => 2, 'wednesday' => 3, 'thursday' => 4, 'friday' => 5, 'saturday' => 6, 'sunday' => 0,
        'lunes' => 1, 'martes' => 2, 'miércoles' => 3, 'miercoles' => 3, 'jueves' => 4, 'viernes' => 5, 'sábado' => 6, 'sabado' => 6, 'domingo' => 0,
    ];

    public function owned(User $user, int|string $id): ?CalendarEvent
    {
        return CalendarEvent::where('user_id', $user->id)->find($id);
    }

    public function between(User $user, CarbonInterface $start, CarbonInterface $end, int $limit = 20): Collection
    {
        return CalendarEvent::where('user_id', $user->id)
            ->where('status', '!=', 'cancelled')
            ->where(fn ($q) => $q->whereBetween('start_date', [$start, $end])
                ->orWhere(fn ($q2) => $q2->where('start_date', '<=', $start)->where('end_date', '>=', $end)))
            ->orderBy('start_date')
            ->limit($limit)
            ->get();
    }

    public function operation(User $user, string $scope, string $key): ?AgendaOperation
    {
        return AgendaOperation::where(['user_id' => $user->id, 'scope' => $scope, 'idempotency_key' => $key])->first();
    }

    /** Resultado previo de una operación con la misma clave, o conflicto si la clave se reutilizó para otra cosa. */
    public function replay(AgendaOperation $operation, string $requestHash): AgendaResult
    {
        if (! hash_equals($operation->request_hash, $requestHash)) {
            return new AgendaResult(AgendaResult::CONFLICT, message: 'La clave de idempotencia ya se usó para otra operación.');
        }

        $events = CalendarEvent::where('user_id', $operation->user_id)->whereIn('id', (array) $operation->event_ids)->orderBy('start_date')->get()->all();

        return new AgendaResult(AgendaResult::REPLAYED, $events);
    }

    /**
     * Crea un evento o una serie. Con clave de idempotencia, un reintento devuelve el resultado previo.
     * Todo ocurre en una transacción: una serie se guarda completa o no se guarda.
     */
    public function create(User $user, array $attributes, ?string $idempotencyKey = null, string $scope = 'api', ?string $requestHash = null): AgendaResult
    {
        $requestHash ??= hash('sha256', json_encode($this->fingerprint($attributes)));
        if ($idempotencyKey !== null && ($operation = $this->operation($user, $scope, $idempotencyKey))) {
            return $this->replay($operation, $requestHash);
        }

        try {
            $attributes = $this->validated($attributes);
        } catch (AgendaValidationException $e) {
            return new AgendaResult(AgendaResult::INVALID, message: $e->getMessage());
        }

        if (empty($attributes['series']) && ($existing = $this->recentDuplicate($user, $attributes))) {
            return new AgendaResult(AgendaResult::DUPLICATE, [$existing], 'Ese evento ya estaba en la agenda.');
        }

        try {
            return DB::transaction(function () use ($user, $attributes, $idempotencyKey, $scope, $requestHash) {
                $operation = $idempotencyKey === null ? null : AgendaOperation::create([
                    'user_id' => $user->id, 'idempotency_key' => $idempotencyKey, 'scope' => $scope,
                    'request_hash' => $requestHash, 'status' => 'pending',
                ]);

                [$events, $summary] = $this->persist($user, $attributes);
                $operation?->update(['status' => AgendaResult::CREATED, 'event_ids' => array_map(fn ($e) => $e->id, $events)]);

                return new AgendaResult(AgendaResult::CREATED, $events, recurrenceSummary: $summary);
            });
        } catch (QueryException $e) {
            // Otra petición con la misma clave ganó la carrera: devolver su resultado.
            if ($idempotencyKey !== null && ($operation = $this->operation($user, $scope, $idempotencyKey))) {
                return $this->replay($operation, $requestHash);
            }
            Log::error('AgendaService: no se pudo guardar el evento', ['user_id' => $user->id, 'error' => $e->getMessage()]);

            return new AgendaResult(AgendaResult::FAILED, message: 'No se pudo guardar el evento.');
        } catch (\Throwable $e) {
            Log::error('AgendaService: no se pudo guardar el evento', ['user_id' => $user->id, 'error' => $e->getMessage()]);

            return new AgendaResult(AgendaResult::FAILED, message: 'No se pudo guardar el evento.');
        }
    }

    /** Actualiza un evento propio. Si cambia la hora, el recordatorio vuelve a quedar pendiente. */
    public function update(User $user, CalendarEvent $event, array $changes): CalendarEvent
    {
        abort_unless($event->user_id === $user->id, 404);

        $merged = $this->validated(array_merge([
            'title' => $event->title, 'start' => $event->start_date, 'end' => $event->end_date, 'all_day' => (bool) $event->all_day,
        ], $changes));

        $fill = [];
        foreach (['title', 'description', 'location', 'category', 'color', 'reminder_minutes_before', 'recurrence_type', 'recurrence_end_date'] as $key) {
            if (array_key_exists($key, $changes)) {
                $fill[$key] = $merged[$key] ?? ($key === 'description' || $key === 'location' ? '' : null);
            }
        }
        if (array_intersect_key($changes, array_flip(['start', 'end', 'all_day']))) {
            $fill += ['start_date' => $merged['start'], 'end_date' => $merged['end'], 'all_day' => $merged['all_day']];
        }
        $event->fill($fill);
        if ($event->isDirty(['start_date', 'end_date'])) {
            $event->notified = false;
        }
        $event->save();

        return $event;
    }

    /** Eventos de la serie desde este (incluido) en adelante, sin los cancelados. */
    public function seriesFrom(CalendarEvent $event): Collection
    {
        if (! $event->series_id) {
            return collect([$event]);
        }

        return CalendarEvent::where('user_id', $event->user_id)->where('series_id', $event->series_id)
            ->where('status', '!=', 'cancelled')->where('start_date', '>=', $event->start_date)->orderBy('start_date')->get();
    }

    /**
     * Cambia título, lugar y hora del día en la serie (desde este evento), conservando cada fecha y duración.
     *
     * @return CalendarEvent[]
     */
    public function updateSeries(User $user, CalendarEvent $event, array $changes): array
    {
        abort_unless($event->user_id === $user->id, 404);

        return DB::transaction(function () use ($user, $event, $changes) {
            $updated = [];
            foreach ($this->seriesFrom($event) as $item) {
                $itemChanges = array_intersect_key($changes, array_flip(['title', 'location']));
                if (isset($changes['time'])) {
                    $start = Carbon::instance($item->start_date)->setTimeFromTimeString($changes['time']);
                    $itemChanges['start'] = $start;
                    $itemChanges['end'] = $start->copy()->addSeconds(Carbon::instance($item->start_date)->diffInSeconds($item->end_date ?? $item->start_date));
                }
                $updated[] = $this->update($user, $item, $itemChanges);
            }

            return $updated;
        });
    }

    /**
     * Cancela (no borra) un evento o la serie desde él: queda en el historial y ya no avisa.
     *
     * @return CalendarEvent[]
     */
    public function cancel(User $user, CalendarEvent $event, bool $series = false): array
    {
        abort_unless($event->user_id === $user->id, 404);
        $events = $series ? $this->seriesFrom($event) : collect([$event]);

        return DB::transaction(fn () => $events->each(fn (CalendarEvent $item) => $item->update(['status' => 'cancelled']))->all());
    }

    /** Borra un evento propio y, si estaba sincronizado, intenta quitarlo de Nextcloud. */
    public function delete(User $user, CalendarEvent $event): void
    {
        abort_unless($event->user_id === $user->id, 404);

        if ($event->nextcloud_synced && $user->hasNextcloud()) {
            try {
                (new NextcloudCalendarService($user))->deleteEvent($event);
            } catch (\Throwable $e) {
                Log::warning('No se pudo eliminar evento de Nextcloud: '.$e->getMessage());
            }
        }

        $event->delete();
    }

    /** @throws AgendaValidationException */
    public function validated(array $attributes): array
    {
        $tz = config('app.timezone');
        $title = trim((string) ($attributes['title'] ?? ''));
        if ($title === '' || in_array(mb_strtolower($title), ['string', 'null', 'none', 'n/a', 'evento', 'evento sin título'], true)) {
            throw new AgendaValidationException('Falta el título del evento.');
        }
        if (mb_strlen($title) > 255) {
            throw new AgendaValidationException('El título es demasiado largo.');
        }
        if (! ($attributes['start'] ?? null) instanceof CarbonInterface) {
            throw new AgendaValidationException('Falta la fecha del evento.');
        }

        $allDay = (bool) ($attributes['all_day'] ?? false);
        $start = Carbon::instance($attributes['start'])->setTimezone($tz);
        $end = ($attributes['end'] ?? null) instanceof CarbonInterface ? Carbon::instance($attributes['end'])->setTimezone($tz) : null;
        if ($allDay) {
            $start = $start->copy()->startOfDay();
            $end = ($end ?? $start)->copy()->startOfDay();
        } else {
            $end ??= $start->copy()->addHour();
        }
        if ($end->lt($start)) {
            throw new AgendaValidationException('La hora de fin no puede ser anterior al inicio.');
        }

        $reminder = $attributes['reminder_minutes_before'] ?? null;
        if ($reminder !== null && ((int) $reminder < 0 || (int) $reminder > self::MAX_REMINDER_MINUTES)) {
            throw new AgendaValidationException('El recordatorio debe estar entre 0 minutos y 7 días antes.');
        }

        return array_merge($attributes, ['title' => $title, 'start' => $start, 'end' => $end, 'all_day' => $allDay,
            'reminder_minutes_before' => $reminder === null ? null : (int) $reminder]);
    }

    private function recentDuplicate(User $user, array $attributes): ?CalendarEvent
    {
        return CalendarEvent::where('user_id', $user->id)
            ->where('status', '!=', 'cancelled')
            ->where('start_date', $attributes['start'])
            ->where('created_at', '>=', now()->subMinutes(self::DUPLICATE_WINDOW_MINUTES))
            ->get()
            ->first(fn (CalendarEvent $event) => mb_strtolower(trim($event->title)) === mb_strtolower($attributes['title']));
    }

    /** @return array{0: CalendarEvent[], 1: ?string} */
    private function persist(User $user, array $attributes): array
    {
        $series = $attributes['series'] ?? null;
        $dates = $series ? $this->seriesDates($attributes['start'], $series) : [];
        if (count($dates) < 2) {
            return [[$this->insert($user, $attributes, null)], null];
        }

        $seriesId = Str::uuid()->toString();
        $duration = $attributes['start']->diffInSeconds($attributes['end']);
        $events = [];
        foreach ($dates as $date) {
            $start = $attributes['start']->copy()->setDate($date->year, $date->month, $date->day);
            $events[] = $this->insert($user, array_merge($attributes, ['start' => $start, 'end' => $start->copy()->addSeconds($duration)]), $seriesId);
        }

        $label = ['daily' => 'todos los días', 'weekdays' => 'lunes a viernes'][$series['type']]
            ?? implode(', ', array_map('ucfirst', (array) ($series['days'] ?? [])));
        $until = end($dates)->locale('es')->isoFormat('D [de] MMMM [de] YYYY');

        return [$events, "{$label} hasta el {$until}"];
    }

    /** @return Carbon[] */
    private function seriesDates(Carbon $start, array $series): array
    {
        $targetDays = match ($series['type'] ?? 'none') {
            'daily' => [0, 1, 2, 3, 4, 5, 6],
            'weekdays' => [1, 2, 3, 4, 5],
            'weekly', 'custom' => array_values(array_unique(array_filter(
                array_map(fn ($d) => self::DAY_MAP[mb_strtolower(trim((string) $d))] ?? null, (array) ($series['days'] ?? [])),
                fn ($d) => $d !== null
            ))),
            default => [],
        };
        if ($targetDays === []) {
            return [];
        }

        $first = $start->copy()->startOfDay();
        $until = ($series['until'] ?? null) instanceof CarbonInterface ? Carbon::instance($series['until'])->startOfDay() : $first->copy()->addWeeks(4);
        $until = $until->min($first->copy()->addDays(self::MAX_SERIES_DAYS));

        $dates = [];
        for ($day = $first->copy(); $day->lte($until); $day->addDay()) {
            if (in_array($day->dayOfWeek, $targetDays, true)) {
                $dates[] = $day->copy();
            }
        }

        return $dates;
    }

    private function insert(User $user, array $attributes, ?string $seriesId): CalendarEvent
    {
        $event = new CalendarEvent($this->columns($attributes));
        $event->user_id = $user->id;
        $event->series_id = $seriesId;
        $event->status = 'pending';
        $event->notified = false;
        $event->save();

        return $event;
    }

    private function columns(array $attributes): array
    {
        return array_filter([
            'title' => $attributes['title'],
            'description' => $attributes['description'] ?? '',
            'location' => $attributes['location'] ?? '',
            'category' => $attributes['category'] ?? 'general',
            'color' => $attributes['color'] ?? '#3788d8',
            'start_date' => $attributes['start'],
            'end_date' => $attributes['end'],
            'all_day' => $attributes['all_day'],
            'reminder_minutes_before' => $attributes['reminder_minutes_before'] ?? null,
            'recurrence_type' => $attributes['recurrence_type'] ?? null,
            'recurrence_end_date' => $attributes['recurrence_end_date'] ?? null,
        ], fn ($value, $key) => $value !== null || in_array($key, ['end_date'], true), ARRAY_FILTER_USE_BOTH);
    }

    /** Huella estable de la petición para comparar reintentos. */
    private function fingerprint(array $attributes): array
    {
        return array_map(fn ($value) => $value instanceof CarbonInterface ? $value->toIso8601String() : $value, $attributes);
    }
}
