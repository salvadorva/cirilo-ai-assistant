<?php

namespace App\Services\Agenda;

use App\Models\CalendarEvent;
use App\Models\Task;
use App\Models\User;
use App\Services\Tasks\TaskService;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * F2-02/F2-07: herramientas de agenda para el modelo (Responses API, esquemas estrictos).
 *
 * El modelo decide qué hacer; el servidor valida, verifica propiedad y ejecuta con AgendaService.
 * Cada resultado dice exactamente qué pasó, y el turno acumula un resumen para el contrato
 * `agenda` de la respuesta. Una instancia por turno.
 */
class AgendaTools
{
    public const MAX_QUERY_DAYS = 31;

    private const NULLABLE_STRING = ['type' => ['string', 'null']];

    private const DATE = ['type' => ['string', 'null'], 'description' => 'YYYY-MM-DD'];

    private const TIME = ['type' => ['string', 'null'], 'description' => 'HH:MM en 24 h'];

    /** @var array<int, array{name: string, status: string, events: CalendarEvent[], candidates?: array, missing?: array, message?: ?string}> */
    private array $actions = [];

    private int $calls = 0;

    /** @var CalendarEvent[] Resultado de la última consulta del turno (candidatos si el modelo pregunta en texto). */
    private array $lastQuery = [];

    // Petición de mover, cambiar o cancelar algo ya agendado.
    private const MODIFY = '/\b(mu[eé]ve(la|lo|las|los)?|mover|cambia(la|lo|r)?|pasa(la|lo)|reprograma(la|lo|r)?|cancela(la|lo|r)?|borra(la|lo|r)?|'
        .'elimina(la|lo|r)?|quita(la|lo|r)?|adelanta(la|lo|r)?|atrasa(la|lo|r)?|pospon(la|lo|er)?|corre(la|lo))\b/u';

    public function __construct(
        private AgendaService $agenda,
        private User $user,
        private ?string $idempotencyKey = null,
    ) {}

    public static function definitions(): array
    {
        $function = fn (string $name, string $description, array $properties) => [
            'type' => 'function', 'name' => $name, 'description' => $description, 'strict' => true,
            'parameters' => ['type' => 'object', 'properties' => $properties, 'required' => array_keys($properties), 'additionalProperties' => false],
        ];

        return [
            $function('agenda_query_events', 'Consulta eventos del usuario entre dos fechas (máximo 31 días). Úsala antes de modificar o cancelar, para identificar el evento.', [
                'from' => ['type' => 'string', 'description' => 'YYYY-MM-DD'],
                'to' => ['type' => 'string', 'description' => 'YYYY-MM-DD'],
                'text' => self::NULLABLE_STRING + ['description' => 'Filtro opcional por texto del título'],
            ]),
            $function('agenda_create_event', 'Crea un evento. Requiere título, fecha y hora (o all_day=true solo si el usuario dijo todo el día). Si falta la hora, no la inventes: pregúntala.', [
                'title' => ['type' => 'string'],
                'date' => self::DATE,
                'time' => self::TIME,
                'end_time' => self::TIME,
                'all_day' => ['type' => 'boolean'],
                'location' => self::NULLABLE_STRING,
                'reminder_minutes_before' => ['type' => ['integer', 'null']],
                'recurrence' => ['type' => 'object', 'additionalProperties' => false, 'required' => ['type', 'days', 'until'], 'properties' => [
                    'type' => ['type' => 'string', 'enum' => ['none', 'daily', 'weekdays', 'weekly']],
                    'days' => ['type' => ['array', 'null'], 'items' => ['type' => 'string', 'enum' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday']]],
                    'until' => self::DATE,
                ]],
            ]),
            $function('agenda_update_event', 'Modifica un evento identificado por su id (de agenda_query_events). Solo cambia los campos no nulos. Para toda la serie, apply_to_series=true y series_change_confirmed=true solo si el usuario lo confirmó explícitamente.', [
                'event_id' => ['type' => 'integer'],
                'title' => self::NULLABLE_STRING,
                'date' => self::DATE,
                'time' => self::TIME,
                'end_time' => self::TIME,
                'location' => self::NULLABLE_STRING,
                'apply_to_series' => ['type' => 'boolean'],
                'series_change_confirmed' => ['type' => 'boolean'],
            ]),
            $function('agenda_cancel_event', 'Cancela un evento identificado por su id. Para toda la serie, apply_to_series=true y series_change_confirmed=true solo con confirmación explícita.', [
                'event_id' => ['type' => 'integer'],
                'apply_to_series' => ['type' => 'boolean'],
                'series_change_confirmed' => ['type' => 'boolean'],
            ]),
            $function('task_create', 'Guarda un pendiente (algo por hacer, sin ocupar tiempo en la agenda). due_date solo si el usuario dio una fecha límite; nunca inventes una.', [
                'title' => ['type' => 'string'],
                'due_date' => self::DATE,
                'notes' => self::NULLABLE_STRING,
            ]),
            $function('task_list', 'Lista los pendientes del usuario.', [
                'status' => ['type' => 'string', 'enum' => ['open', 'done', 'all']],
            ]),
            $function('task_update', 'Cambia el estado de un pendiente (id de task_list): complete, postpone (con until), dismiss o reopen.', [
                'task_id' => ['type' => 'integer'],
                'action' => ['type' => 'string', 'enum' => ['complete', 'postpone', 'dismiss', 'reopen']],
                'until' => self::DATE,
            ]),
            $function('agenda_request_clarification', 'Úsala cuando no esté claro a qué evento se refiere el usuario (varios candidatos) o qué quiere cambiar. No modifica nada.', [
                'question' => ['type' => 'string'],
                'candidate_event_ids' => ['type' => 'array', 'items' => ['type' => 'integer']],
            ]),
        ];
    }

    public static function instructions(): string
    {
        $now = now()->setTimezone(config('app.timezone'))->locale('es');

        return "\n### Agenda del usuario (herramientas):\n"
            .'Hoy es '.$now->isoFormat('dddd D [de] MMMM [de] YYYY, HH:mm')." (America/Guatemala).\n"
            ."- Para consultar, crear, modificar o cancelar eventos usa SIEMPRE las herramientas agenda_*; no digas que hiciste algo sin su resultado.\n"
            ."- Confirma solo lo que la herramienta devolvió como created, updated o cancelled. Si devolvió otra cosa, explica qué falta o qué falló.\n"
            ."- Si el usuario pide agendar algo, llama agenda_create_event aunque falte la hora (time=null): el resultado te dirá qué preguntar. No uses una hora por defecto; «todo el día» solo si el usuario lo dijo.\n"
            ."- Para modificar o cancelar, identifica el evento con agenda_query_events. Si hay más de un candidato posible, DEBES llamar agenda_request_clarification con sus ids antes de preguntar; no preguntes solo en texto.\n"
            ."- Cambiar o cancelar una serie completa requiere que el usuario lo confirme explícitamente.\n"
            ."- Algo por hacer sin hora («tengo que revisar la propuesta») es un pendiente: usa task_create, no un evento. Si dice que ya hizo algo de su lista, usa task_list y task_update.\n"
            ."- «No quiero agendar…» o pedir código (p. ej. «crea una función») no son peticiones de agenda.\n";
    }

    public function execute(string $name, string $arguments): array
    {
        $this->calls++;
        $args = json_decode($arguments, true);
        if (! is_array($args)) {
            return $this->record($name, 'invalid', message: 'Argumentos no válidos.');
        }

        try {
            return match ($name) {
                'agenda_query_events' => $this->query($args),
                'agenda_create_event' => $this->create($args),
                'agenda_update_event' => $this->updateEvent($args),
                'agenda_cancel_event' => $this->cancelEvent($args),
                'agenda_request_clarification' => $this->clarify($args),
                'task_create', 'task_list', 'task_update' => $this->task($name, $args),
                default => $this->record($name, 'invalid', message: 'Herramienta desconocida.'),
            };
        } catch (AgendaValidationException $e) {
            return $this->record($name, 'invalid', message: $e->getMessage());
        } catch (\Throwable $e) {
            report($e);

            return $this->record($name, 'failed', message: 'No se pudo completar la operación.');
        }
    }

    private function query(array $args): array
    {
        $tz = config('app.timezone');
        $from = $this->date($args['from'] ?? null) ?? now($tz)->startOfDay();
        $to = ($this->date($args['to'] ?? null) ?? $from->copy())->endOfDay();
        if ($to->lt($from) || $from->diffInDays($to) > self::MAX_QUERY_DAYS) {
            return $this->record('agenda_query_events', 'invalid', message: 'El rango debe ser de 0 a 31 días.');
        }

        $events = $this->agenda->between($this->user, $from, $to, 50);
        if (filled($args['text'] ?? null)) {
            $needle = mb_strtolower($args['text']);
            $events = $events->filter(fn (CalendarEvent $e) => str_contains(mb_strtolower($e->title), $needle))->values();
        }

        $this->lastQuery = $events->take(20)->all();

        return $this->record('agenda_query_events', 'ok', $this->lastQuery);
    }

    private function create(array $args): array
    {
        $allDay = (bool) ($args['all_day'] ?? false);
        $missing = array_values(array_filter([
            blank($args['title'] ?? null) ? 'title' : null,
            $this->date($args['date'] ?? null) ? null : 'date',
            $allDay || $this->validTime($args['time'] ?? null) ? null : 'time',
        ]));
        if ($missing) {
            return $this->record('agenda_create_event', 'needs_input', missing: $missing, message: 'Faltan datos: pregúntalos al usuario.');
        }

        $tz = config('app.timezone');
        $start = Carbon::parse($args['date'].' '.($allDay ? '00:00' : $args['time']), $tz);
        $recurrence = $args['recurrence'] ?? ['type' => 'none'];
        $key = $this->idempotencyKey !== null ? mb_substr($this->idempotencyKey, 0, 90).'#'.$this->calls : null;

        $result = $this->agenda->create($this->user, [
            'title' => $args['title'],
            'start' => $start,
            'end' => ! $allDay && $this->validTime($args['end_time'] ?? null) ? Carbon::parse($args['date'].' '.$args['end_time'], $tz) : null,
            'all_day' => $allDay,
            'location' => $args['location'] ?? '',
            'reminder_minutes_before' => $args['reminder_minutes_before'] ?? 30,
            'series' => ($recurrence['type'] ?? 'none') !== 'none' ? [
                'type' => $recurrence['type'], 'days' => $recurrence['days'] ?? [], 'until' => $this->date($recurrence['until'] ?? null),
            ] : null,
        ], $key, 'chat_tool');

        return $this->record('agenda_create_event', $result->status, $result->events, message: $result->message, summary: $result->recurrenceSummary);
    }

    private function updateEvent(array $args): array
    {
        $event = $this->target($args['event_id'] ?? null);
        if (! $event) {
            return $this->record('agenda_update_event', 'not_found', message: 'No existe ese evento en la agenda del usuario.');
        }
        if (($args['apply_to_series'] ?? false) && $event->series_id) {
            if (! ($args['series_change_confirmed'] ?? false)) {
                return $this->record('agenda_update_event', 'needs_confirmation', $this->agenda->seriesFrom($event)->all(),
                    message: 'Es una serie: pide confirmación explícita antes de cambiarla completa.');
            }
            if (filled($args['date'] ?? null)) {
                return $this->record('agenda_update_event', 'invalid', message: 'Para mover una serie a otro día, hazlo desde la Agenda.');
            }
            $changes = array_filter(['title' => $args['title'] ?? null, 'location' => $args['location'] ?? null,
                'time' => $this->validTime($args['time'] ?? null) ? $args['time'] : null], fn ($v) => $v !== null);

            return $this->record('agenda_update_event', 'updated', $this->agenda->updateSeries($this->user, $event, $changes));
        }

        $tz = config('app.timezone');
        $changes = array_filter(['title' => $args['title'] ?? null, 'location' => $args['location'] ?? null], fn ($v) => $v !== null);
        $date = $this->date($args['date'] ?? null);
        $time = $this->validTime($args['time'] ?? null) ? $args['time'] : null;
        if ($date || $time) {
            $current = Carbon::instance($event->start_date)->setTimezone($tz);
            $start = Carbon::parse(($date ?? $current)->format('Y-m-d').' '.($time ?? $current->format('H:i')), $tz);
            $changes['start'] = $start;
            $changes['end'] = $this->validTime($args['end_time'] ?? null)
                ? Carbon::parse($start->format('Y-m-d').' '.$args['end_time'], $tz)
                : $start->copy()->addSeconds($current->diffInSeconds($event->end_date ?? $event->start_date));
        }
        if ($changes === []) {
            return $this->record('agenda_update_event', 'needs_input', message: 'No se indicó ningún cambio.');
        }

        return $this->record('agenda_update_event', 'updated', [$this->agenda->update($this->user, $event, $changes)]);
    }

    private function cancelEvent(array $args): array
    {
        $event = $this->target($args['event_id'] ?? null);
        if (! $event) {
            return $this->record('agenda_cancel_event', 'not_found', message: 'No existe ese evento en la agenda del usuario.');
        }
        $series = ($args['apply_to_series'] ?? false) && $event->series_id;
        if ($series && ! ($args['series_change_confirmed'] ?? false)) {
            return $this->record('agenda_cancel_event', 'needs_confirmation', $this->agenda->seriesFrom($event)->all(),
                message: 'Es una serie: pide confirmación explícita antes de cancelarla completa.');
        }

        return $this->record('agenda_cancel_event', 'cancelled', $this->agenda->cancel($this->user, $event, $series));
    }

    /** @var array<int, array{name: string, status: string, tasks: Task[]}> */
    private array $taskActions = [];

    private function task(string $name, array $args): array
    {
        $tasks = app(TaskService::class);
        try {
            [$status, $items] = match ($name) {
                'task_create' => (function () use ($tasks, $args) {
                    [$status, $task] = $tasks->create($this->user, (string) ($args['title'] ?? ''), $args['due_date'] ?? null, 'chat', null, $args['notes'] ?? null);

                    return [$status, [$task]];
                })(),
                'task_list' => ['ok', Task::where('user_id', $this->user->id)
                    ->when(($args['status'] ?? 'open') === 'open', fn ($q) => $q->whereIn('status', ['open', 'postponed']))
                    ->when(($args['status'] ?? 'open') === 'done', fn ($q) => $q->where('status', 'done'))
                    ->latest('id')->limit(30)->get()->all()],
                'task_update' => ($task = $tasks->owned($this->user, (int) ($args['task_id'] ?? 0)))
                    ? ['updated', [$tasks->update($this->user, $task, ['action' => $args['action'] ?? null, 'until' => $args['until'] ?? null])]]
                    : ['not_found', []],
            };
        } catch (ValidationException $e) {
            [$status, $items] = ['invalid', []];
            $message = collect($e->errors())->flatten()->first();
        }
        $this->taskActions[] = ['name' => $name, 'status' => $status, 'tasks' => $items];

        return array_filter(['status' => $status, 'message' => $message ?? null,
            'tasks' => array_map(fn ($t) => TaskService::describe($t), $items)], fn ($v) => $v !== null);
    }

    /** Resumen de pendientes del turno para el contrato `tasks`. */
    public function taskOutcome(): array
    {
        $priority = ['invalid', 'not_found', 'created', 'duplicate', 'updated'];
        $actions = array_values(array_filter($this->taskActions, fn ($a) => $a['name'] !== 'task_list'));
        $rank = fn (array $action) => ($i = array_search($action['status'], $priority, true)) === false ? 99 : $i;
        usort($actions, fn ($a, $b) => $rank($a) <=> $rank($b));
        $main = $actions[0] ?? null;
        if (! $main) {
            return ['status' => $this->taskActions ? 'queried' : 'none', 'tasks' => []];
        }

        return ['status' => $main['status'] === 'not_found' ? 'invalid' : $main['status'], 'tasks' => $main['tasks']];
    }

    private function clarify(array $args): array
    {
        $candidates = collect((array) ($args['candidate_event_ids'] ?? []))
            ->map(fn ($id) => $this->target($id))->filter()->values()->all();

        return $this->record('agenda_request_clarification', 'clarification_requested', candidates: $candidates, message: (string) ($args['question'] ?? ''));
    }

    private function target(mixed $id): ?CalendarEvent
    {
        $event = is_numeric($id) ? $this->agenda->owned($this->user, (int) $id) : null;

        return $event && $event->status !== 'cancelled' ? $event : null;
    }

    private function date(mixed $value): ?Carbon
    {
        return is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) && strtotime($value)
            ? Carbon::parse($value, config('app.timezone'))->startOfDay() : null;
    }

    private function validTime(mixed $value): bool
    {
        return is_string($value) && (bool) preg_match('/^([01]?\d|2[0-3]):[0-5]\d$/', $value);
    }

    private function record(string $name, string $status, array $events = [], array $candidates = [], array $missing = [], ?string $message = null, ?string $summary = null): array
    {
        $this->actions[] = compact('name', 'status', 'events', 'candidates', 'missing', 'message');

        return array_filter([
            'status' => $status,
            'message' => $message,
            'recurrence' => $summary,
            'missing' => $missing ?: null,
            'events' => array_map(fn ($e) => self::describe($e), array_slice($events, 0, 20)),
            'count' => count($events) > 20 ? count($events) : null,
            'candidates' => $candidates ? array_map(fn ($e) => self::describe($e), $candidates) : null,
        ], fn ($value) => $value !== null);
    }

    public static function describe(CalendarEvent $event): array
    {
        $start = Carbon::instance($event->start_date)->setTimezone(config('app.timezone'));

        return ['id' => $event->id, 'title' => $event->title, 'date' => $start->format('Y-m-d'), 'time' => $event->all_day ? null : $start->format('H:i'),
            'all_day' => (bool) $event->all_day, 'series' => $event->series_id !== null, 'location' => $event->location ?: null, 'status' => $event->status];
    }

    /**
     * Resumen del turno para el contrato `agenda`: la acción más relevante que no sea una consulta.
     * Prioridad: fallo > aclaración/confirmación/falta de datos > cambios hechos.
     */
    public function outcome(?string $prompt = null, ?string $reply = null): array
    {
        $priority = ['failed', 'conflict', 'invalid', 'not_found', 'clarification_requested', 'needs_confirmation', 'needs_input',
            'created', 'replayed', 'duplicate', 'updated', 'cancelled'];
        $actions = array_filter($this->actions, fn ($a) => $a['name'] !== 'agenda_query_events');
        $rank = fn (array $action) => ($i = array_search($action['status'], $priority, true)) === false ? 99 : $i;
        usort($actions, fn ($a, $b) => $rank($a) <=> $rank($b));
        $main = $actions[0] ?? null;
        if (! $main) {
            // Respaldo: el modelo preguntó en texto sin usar la herramienta que corresponde (hallazgos S4/S5 del 29/09/2026).
            $asks = str_contains((string) $reply, '?');
            $text = mb_strtolower((string) $prompt);
            if ($asks && count($this->lastQuery) > 1 && preg_match(self::MODIFY, $text)) {
                return ['status' => 'needs_clarification', 'events' => [], 'candidates' => array_slice($this->lastQuery, 0, 10), 'missing' => [], 'message' => null];
            }
            if ($asks && AgendaIntent::wantsToCreate((string) $prompt)) {
                return ['status' => ChatAgenda::NEEDS_INPUT, 'events' => [], 'candidates' => [], 'missing' => [], 'message' => null];
            }

            return ['status' => $this->actions ? 'queried' : ChatAgenda::NONE, 'events' => [], 'candidates' => [], 'missing' => [], 'message' => null];
        }
        $status = match ($main['status']) {
            'clarification_requested' => 'needs_clarification',
            'needs_input' => ChatAgenda::NEEDS_INPUT,
            'not_found' => 'invalid',
            default => $main['status'],
        };

        return ['status' => $status, 'events' => $main['events'], 'candidates' => $main['candidates'], 'missing' => $main['missing'], 'message' => $main['message']];
    }
}
