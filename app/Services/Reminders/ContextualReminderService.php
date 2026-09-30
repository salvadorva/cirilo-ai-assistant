<?php

namespace App\Services\Reminders;

use App\Jobs\GenerateContextualReminderAudio;
use App\Models\ContextualReminder;
use App\Models\DeviceToken;
use App\Models\ReminderDelivery;
use App\Models\ReminderIntegration;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * Reglas de dominio de recordatorios contextuales, compartidas por la API de
 * Hermes y la API móvil. No envía nada: el despacho durable es RC2.
 */
class ContextualReminderService
{
    public const CREATE_FIELDS = ['title', 'context', 'next_action', 'scheduled_at', 'expires_at', 'timezone',
        'with_audio', 'voice', 'confirmed_by_user', 'confirmed_at'];

    private const ISO_OFFSET = '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(:\d{2}(\.\d{1,6})?)?(Z|[+-]\d{2}:\d{2})$/';

    public function __construct(private ReminderCommandStore $commands, private ReminderDestinations $destinations) {}

    /** Alta idempotente desde una integración autenticada. */
    public function create(ReminderIntegration $integration, array $input, ?string $key): array
    {
        $data = $this->validateCreate($input);

        return $this->commands->run('integration', $integration->id, $key, 'create', null, $input, function () use ($integration, $data) {
            $this->assertCreateTimes($data);
            $this->assertQuotas($integration->user_id);
            if (config('reminders.require_ready_device') && $this->destinations->contextual($integration->user_id)->isEmpty()) {
                // No se persiste: Hermes no debe afirmar que quedó programado.
                throw new ReminderApiException(409, 'device_not_ready', 'No hay un teléfono compatible listo para recibir el recordatorio.');
            }

            $reminder = ContextualReminder::create($data + [
                'user_id' => $integration->user_id,
                'reminder_integration_id' => $integration->id,
                'state' => ContextualReminder::PENDING,
                'version' => 1,
            ]);
            Log::info('contextual_reminder.created', $this->logContext($reminder));
            if ($reminder->with_audio) {
                GenerateContextualReminderAudio::dispatch($reminder->id)->afterCommit();
            }

            return [201, $this->summary($reminder)];
        });
    }

    /**
     * Hecho / Cancelar / Posponer con versión esperada.
     *
     * @param  array{0: string, 1: int}  $actor  tipo e id del actor para idempotencia
     */
    public function transition(ContextualReminder $reminder, string $action, array $input, array $actor, ?string $key, string $closedBy): array
    {
        $allowed = $action === 'snooze' ? ['expected_version', 'minutes', 'scheduled_at'] : ['expected_version'];
        $rules = ['expected_version' => 'required|integer|min:1'];
        if ($action === 'snooze') {
            $rules += [
                'minutes' => ['required_without:scheduled_at', 'prohibits:scheduled_at', 'integer', 'in:'.implode(',', config('reminders.snooze_minutes'))],
                'scheduled_at' => ['required_without:minutes', 'string', 'regex:'.self::ISO_OFFSET],
            ];
        }
        $this->validate($input, $rules, $allowed);

        return $this->commands->run($actor[0], $actor[1], $key, $action, $reminder->id, $input,
            fn () => $this->applyTransition($reminder->id, $action, $input, $closedBy));
    }

    private function applyTransition(string $id, string $action, array $input, string $closedBy): array
    {
        $reminder = ContextualReminder::whereKey($id)->lockForUpdate()->firstOrFail();
        $now = CarbonImmutable::now('UTC');

        if ($reminder->state === ContextualReminder::PENDING && $reminder->isPastExpiry()) {
            $this->persist($reminder, ['state' => ContextualReminder::EXPIRED, 'expired_at' => $now, 'closed_by' => 'system']);

            return $this->conflict('reminder_expired', 'El recordatorio caducó; acuerda uno nuevo.', $reminder);
        }
        if ($reminder->state !== ContextualReminder::PENDING) {
            return $this->conflict('invalid_state', 'El recordatorio ya está cerrado.', $reminder);
        }
        if ((int) $input['expected_version'] !== $reminder->version) {
            return $this->conflict('version_conflict', 'El recordatorio cambió; consulta su estado actual.', $reminder);
        }

        $changes = match ($action) {
            'complete' => ['state' => ContextualReminder::COMPLETED, 'completed_at' => $now, 'closed_by' => $closedBy],
            'cancel' => ['state' => ContextualReminder::CANCELLED, 'cancelled_at' => $now, 'closed_by' => $closedBy],
            'snooze' => ['scheduled_at' => $this->snoozeTarget($input, $now, $reminder->timezone)],
        };
        if ($action === 'snooze' && ! $changes['scheduled_at']->lt($reminder->expires_at)) {
            return $this->conflict('snooze_exceeds_expiry', 'La nueva hora alcanza la caducidad; acuerda un nuevo recordatorio.', $reminder);
        }
        if (! $this->persist($reminder, $changes, (int) $input['expected_version'])) {
            return $this->conflict('version_conflict', 'El recordatorio cambió; consulta su estado actual.', $reminder->refresh());
        }
        // Cerrar o posponer invalida los despachos de la revisión anterior no iniciados.
        ReminderDelivery::where('contextual_reminder_id', $reminder->id)->where('revision', '<', $reminder->version)
            ->whereIn('status', ReminderDelivery::WAITING)
            ->update(['status' => ReminderDelivery::SKIPPED, 'error_category' => 'superseded', 'enqueued_at' => null, 'updated_at' => now()]);
        Log::info('contextual_reminder.'.$action, $this->logContext($reminder));

        return [200, $this->summary($reminder)];
    }

    private function snoozeTarget(array $input, CarbonImmutable $now, string $timezone): CarbonImmutable
    {
        if (isset($input['minutes'])) {
            return $now->addMinutes((int) $input['minutes'])->startOfSecond();
        }
        $target = CarbonImmutable::parse($input['scheduled_at']);
        if ($target->getOffset() !== $target->setTimezone($timezone)->getOffset()) {
            throw ReminderApiException::validation(['scheduled_at' => ['El offset no corresponde a la zona horaria del recordatorio.']]);
        }
        if (! $target->gt($now)) {
            throw ReminderApiException::validation(['scheduled_at' => ['La nueva hora debe ser futura.']]);
        }

        return $target->utc();
    }

    /** Actualización condicional por versión: una carrera tiene un solo ganador. */
    private function persist(ContextualReminder $reminder, array $changes, ?int $expectedVersion = null): bool
    {
        $reminder->fill($changes);
        $reminder->version = ($expectedVersion ?? $reminder->version) + 1;
        $updated = ContextualReminder::whereKey($reminder->id)
            ->where('version', $expectedVersion ?? $reminder->getOriginal('version'))
            ->update($reminder->getDirty() + ['updated_at' => now()]);
        $reminder->syncOriginal();

        return $updated === 1;
    }

    private function conflict(string $code, string $message, ContextualReminder $reminder): array
    {
        return [409, ReminderApiException::body($code, $message, ['current' => $this->summary($reminder)])];
    }

    private function validateCreate(array $input): array
    {
        $limits = config('reminders.limits');
        $plain = function (string $attribute, mixed $value, \Closure $fail) {
            if (is_string($value) && (preg_match('/[<>]/', $value) || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $value))) {
                $fail('Solo se admite texto plano.');
            }
        };
        $iso = ['required', 'string', 'regex:'.self::ISO_OFFSET];
        $this->validate($input, [
            'title' => ['required', 'string', 'max:'.$limits['title'], $plain],
            'context' => ['required', 'string', 'max:'.$limits['context'], $plain],
            'next_action' => ['required', 'string', 'max:'.$limits['next_action'], $plain],
            'scheduled_at' => $iso,
            'expires_at' => $iso,
            'timezone' => ['sometimes', 'string', 'timezone:all'],
            'with_audio' => ['sometimes', 'boolean'],
            'voice' => ['sometimes', 'nullable', 'in:'.implode(',', config('reminders.voices'))],
            'confirmed_by_user' => ['required', function ($attribute, $value, $fail) {
                if ($value !== true) {
                    $fail('Se requiere el acuerdo explícito del usuario.');
                }
            }],
            'confirmed_at' => $iso,
        ], self::CREATE_FIELDS);

        if (! empty($input['with_audio']) && ! config('reminders.audio_enabled')) {
            throw ReminderApiException::validation(['with_audio' => ['El audio todavía no está disponible.']], 'audio_not_available', 'El audio todavía no está disponible; usa solo texto.');
        }

        $timezone = $input['timezone'] ?? config('reminders.default_timezone');
        $scheduled = CarbonImmutable::parse($input['scheduled_at']);
        $expires = CarbonImmutable::parse($input['expires_at']);
        $errors = [];
        foreach (['scheduled_at' => $scheduled, 'expires_at' => $expires] as $field => $date) {
            if ($date->getOffset() !== $date->setTimezone($timezone)->getOffset()) {
                $errors[$field][] = 'El offset no corresponde a la zona horaria indicada.';
            }
        }
        if (! $scheduled->lt($expires)) {
            $errors['expires_at'][] = 'La caducidad debe ser posterior a la hora programada.';
        } elseif ($expires->gt($scheduled->addMinutes(config('reminders.max_window_minutes')))) {
            $errors['expires_at'][] = 'La ventana de vigencia supera el máximo permitido.';
        }
        if ($errors) {
            throw ReminderApiException::validation($errors);
        }

        return [
            'title' => $input['title'],
            'context' => $input['context'],
            'next_action' => $input['next_action'],
            'scheduled_at' => $scheduled->utc(),
            'expires_at' => $expires->utc(),
            'timezone' => $timezone,
            'with_audio' => (bool) ($input['with_audio'] ?? false),
            'voice' => $input['voice'] ?? null,
            'confirmed_by_user' => true,
            'confirmed_at' => CarbonImmutable::parse($input['confirmed_at'])->utc(),
        ];
    }

    /** Reglas que dependen del reloj: se evalúan después de buscar un replay. */
    private function assertCreateTimes(array $data): void
    {
        $now = CarbonImmutable::now('UTC');
        $errors = [];
        if ($data['scheduled_at']->lt($now->addSeconds(config('reminders.min_lead_seconds')))) {
            $errors['scheduled_at'][] = 'La hora debe estar al menos un minuto en el futuro.';
        } elseif ($data['scheduled_at']->gt($now->addDays(config('reminders.max_horizon_days')))) {
            $errors['scheduled_at'][] = 'La hora supera el horizonte permitido.';
        }
        if ($data['confirmed_at']->gt($now->addSeconds(config('reminders.confirmation_skew_seconds')))) {
            $errors['confirmed_at'][] = 'La confirmación no puede ser futura.';
        }
        if ($errors) {
            throw ReminderApiException::validation($errors);
        }
    }

    private function assertQuotas(int $userId): void
    {
        $pending = ContextualReminder::ownedBy($userId)->where('state', ContextualReminder::PENDING)->where('expires_at', '>', now('UTC')->format('Y-m-d H:i:s'))->count();
        if ($pending >= config('reminders.max_pending')) {
            throw new ReminderApiException(429, 'pending_limit_reached', 'Hay demasiados recordatorios pendientes.', [], ['Retry-After' => '60']);
        }
        $dayStart = now(config('reminders.default_timezone'))->startOfDay();
        $today = ContextualReminder::ownedBy($userId)->where('created_at', '>=', $dayStart->copy()->setTimezone(config('app.timezone')))->count();
        if ($today >= config('reminders.max_daily_creates')) {
            $retry = (int) ceil(now()->diffInSeconds($dayStart->copy()->addDay(), true));
            throw new ReminderApiException(429, 'daily_limit_reached', 'Se alcanzó el máximo de recordatorios nuevos de hoy.', [], ['Retry-After' => (string) max(1, $retry)]);
        }
    }

    private function validate(array $input, array $rules, array $allowed): void
    {
        $validator = Validator::make($input, $rules);
        $errors = $validator->fails() ? $validator->errors()->toArray() : [];
        foreach (array_diff(array_keys($input), $allowed) as $field) {
            $errors[$field][] = 'Campo no admitido.';
        }
        if ($errors) {
            throw ReminderApiException::validation($errors);
        }
    }

    /** Listado paginado, orden scheduled_at + id; «pending» excluye vencidos aunque no haya corrido el scheduler. */
    public function list(Builder $query, array $input): array
    {
        $this->validate($input, [
            'state' => ['sometimes', 'in:pending,completed,cancelled,expired'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:'.config('reminders.max_per_page')],
            'page' => ['sometimes', 'integer', 'min:1'],
        ], ['state', 'per_page', 'page']);

        $now = now('UTC')->format('Y-m-d H:i:s');
        $state = $input['state'] ?? ContextualReminder::PENDING;
        match ($state) {
            ContextualReminder::PENDING => $query->where('state', $state)->where('expires_at', '>', $now),
            ContextualReminder::EXPIRED => $query->where(fn ($q) => $q->where('state', $state)
                ->orWhere(fn ($q) => $q->where('state', ContextualReminder::PENDING)->where('expires_at', '<=', $now))),
            default => $query->where('state', $state),
        };
        $page = $query->orderBy('scheduled_at')->orderBy('id')
            ->paginate((int) ($input['per_page'] ?? config('reminders.per_page')), ['*'], 'page', (int) ($input['page'] ?? 1));

        return [
            // Mínimo necesario para listar/reconciliar: el contexto solo viaja en el detalle.
            'data' => $page->getCollection()->map(fn ($reminder) => $this->summary($reminder) + ['title' => $reminder->title])->all(),
            'meta' => ['total' => $page->total(), 'per_page' => $page->perPage(), 'current_page' => $page->currentPage(), 'last_page' => $page->lastPage()],
        ];
    }

    /** Resultado mínimo confirmado: sin contexto ni siguiente acción. */
    public function summary(ContextualReminder $reminder): array
    {
        return [
            'id' => $reminder->id,
            'occurrence_id' => $reminder->id,
            'state' => $reminder->effectiveState(),
            'version' => $reminder->version,
            'scheduled_at' => self::utc($reminder->scheduled_at),
            'expires_at' => self::utc($reminder->expires_at),
            'timezone' => $reminder->timezone,
            'with_audio' => $reminder->with_audio,
            'dispatch' => $this->dispatchState($reminder),
        ];
    }

    /**
     * Estado de despacho de la revisión vigente, agregado entre destinos.
     * «accepted» = aceptado por FCM, no mostrado ni leído.
     */
    private function dispatchState(ContextualReminder $reminder): array
    {
        $priority = [ReminderDelivery::ACCEPTED, ReminderDelivery::PROCESSING, ReminderDelivery::RETRY_WAIT, ReminderDelivery::PENDING,
            ReminderDelivery::UNCERTAIN, ReminderDelivery::FAILED, ReminderDelivery::SKIPPED];
        $best = ReminderDelivery::where('contextual_reminder_id', $reminder->id)->where('revision', $reminder->version)
            ->get(['status', 'error_category'])
            ->sortBy(fn ($d) => array_search($d->status, $priority, true))->first();
        if (! $best) {
            return ['state' => 'not_started', 'error_category' => null];
        }

        return ['state' => $best->status, 'error_category' => $best->status === ReminderDelivery::ACCEPTED ? null : $best->error_category];
    }

    public function detail(ContextualReminder $reminder): array
    {
        return $this->summary($reminder) + [
            'title' => $reminder->title,
            'context' => $reminder->context,
            'next_action' => $reminder->next_action,
            'confirmed_at' => self::utc($reminder->confirmed_at),
            'completed_at' => self::utc($reminder->completed_at),
            'cancelled_at' => self::utc($reminder->cancelled_at),
            'expired_at' => self::utc($reminder->expired_at),
            'audio_ready' => app(ContextualReminderAudio::class)->ready($reminder),
            'actions' => $this->availableActions($reminder),
            'delivery_receipt' => $this->deliveryReceipt($reminder),
        ];
    }

    /**
     * Acciones que la app puede ofrecer ahora. Posponer solo lista opciones que
     * no alcanzan la caducidad; el servidor las valida de nuevo al recibirlas.
     */
    private function availableActions(ContextualReminder $reminder): array
    {
        $pending = $reminder->effectiveState() === ContextualReminder::PENDING;
        $now = CarbonImmutable::now('UTC');

        return [
            'complete' => $pending,
            'cancel' => $pending,
            'snooze_minutes' => $pending ? array_values(array_filter(config('reminders.snooze_minutes'),
                fn (int $minutes) => $now->addMinutes($minutes)->lt($reminder->expires_at))) : [],
        ];
    }

    /** Recibo informado por Android para la revisión vigente; null significa desconocido, no fallo. */
    private function deliveryReceipt(ContextualReminder $reminder): array
    {
        $deliveries = ReminderDelivery::where('contextual_reminder_id', $reminder->id)->where('revision', $reminder->version)
            ->get(['received_at', 'displayed_at']);

        return [
            'received_at' => self::utc($deliveries->pluck('received_at')->filter()->min()),
            'displayed_at' => self::utc($deliveries->pluck('displayed_at')->filter()->min()),
        ];
    }

    /**
     * Recibo mínimo e idempotente de la app (received/displayed). Solo diagnóstico:
     * no cambia estado ni versión y la primera marca de tiempo prevalece.
     */
    public function recordReceipt(ContextualReminder $reminder, int $userId, array $input): array
    {
        $this->validate($input, [
            'event' => ['required', 'in:received,displayed'],
            'version' => ['required', 'integer', 'min:1'],
            'installation_id' => ['required', 'string', 'max:100'],
        ], ['event', 'version', 'installation_id']);

        $device = DeviceToken::where('user_id', $userId)->where('installation_id', $input['installation_id'])->first();
        if (! $device) {
            throw ReminderApiException::validation(['installation_id' => ['Instalación no registrada para esta cuenta.']], 'unknown_installation', 'Instalación no registrada.');
        }
        $delivery = ReminderDelivery::where('contextual_reminder_id', $reminder->id)->where('revision', (int) $input['version'])
            ->where('device_token_id', $device->id)->first();
        if (! $delivery) {
            return ['recorded' => false, 'event' => $input['event'], 'version' => (int) $input['version']];
        }

        $now = CarbonImmutable::now('UTC')->format('Y-m-d H:i:s');
        ReminderDelivery::whereKey($delivery->id)->whereNull('received_at')->update(['received_at' => $now]);
        if ($input['event'] === 'displayed') {
            ReminderDelivery::whereKey($delivery->id)->whereNull('displayed_at')->update(['displayed_at' => $now]);
        }
        Log::info('contextual_reminder.receipt', ['reminder_id' => $reminder->id, 'delivery_id' => $delivery->id, 'event' => $input['event']]);

        return ['recorded' => true, 'event' => $input['event'], 'version' => (int) $input['version']];
    }

    public static function utc(?\DateTimeInterface $date): ?string
    {
        return $date ? CarbonImmutable::instance($date)->utc()->format('Y-m-d\TH:i:s\Z') : null;
    }

    private function logContext(ContextualReminder $reminder): array
    {
        return ['request_id' => request()->attributes->get('reminder_request_id'), 'reminder_id' => $reminder->id,
            'integration_id' => $reminder->reminder_integration_id, 'version' => $reminder->version, 'state' => $reminder->state];
    }
}
