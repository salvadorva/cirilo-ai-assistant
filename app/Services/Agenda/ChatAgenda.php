<?php

namespace App\Services\Agenda;

use App\Models\User;
use App\Services\InteractionTracker;
use Carbon\Carbon;

/**
 * F2: agenda desde el chat (web y móvil). Decide si hay algo que agendar, completa un borrador
 * entre turnos, persiste con AgendaService y dice al modelo exactamente qué pasó, para que nunca
 * confirme algo que no quedó guardado.
 */
class ChatAgenda
{
    public const NONE = 'none';

    public const NEEDS_INPUT = 'needs_input';

    public const DRAFT_CANCELLED = 'draft_cancelled';

    private const MISSING_LABELS = ['title' => 'el nombre del evento', 'start_date' => 'la fecha', 'start_time' => 'la hora (o si es todo el día)'];

    public function __construct(
        private AgendaService $agenda,
        private AgendaDrafts $drafts,
        private AgendaExtractor $extractor,
    ) {}

    public function handle(User $user, string $prompt, array $history, ?int $conversationId, ?string $sessionId, ?string $idempotencyKey): array
    {
        $scopes = AgendaDrafts::scopes($conversationId, $sessionId);
        $draft = $this->drafts->find($user, $scopes);

        if ($draft && AgendaIntent::abandons($prompt)) {
            $this->drafts->discard($user, $scopes);

            return $this->outcome(self::DRAFT_CANCELLED);
        }

        $wantsToCreate = AgendaIntent::wantsToCreate($prompt);
        if (! $wantsToCreate && ! $draft) {
            return $this->outcome(self::NONE);
        }

        $requestHash = hash('sha256', ($conversationId ?? '-').'|'.trim($prompt));
        if ($idempotencyKey !== null && ($operation = $this->agenda->operation($user, 'chat', $idempotencyKey))) {
            return $this->fromResult($this->agenda->replay($operation, $requestHash));
        }

        $collected = $draft ? (array) $draft->data : [];
        $context = $collected ? 'Datos ya reunidos: '.json_encode($collected, JSON_UNESCAPED_UNICODE)."\n" : '';
        foreach (array_slice($history, -6) as $message) {
            $context .= (($message['role'] ?? '') === 'user' ? 'Usuario' : 'Asistente').': '.($message['content'] ?? '')."\n";
        }
        $context .= "Usuario: {$prompt}";

        $extracted = app(InteractionTracker::class)->measure('agenda_extract', fn () => $this->extractor->extract($context));
        if ($extracted === null) {
            return $this->outcome(AgendaResult::FAILED, message: 'No pude interpretar los datos del evento.');
        }

        $new = array_filter($extracted, fn ($value, $key) => $value !== null && $value !== '' && ! ($key === 'all_day' && $value === false)
            && ! ($key === 'recurrence_type' && $value === 'none'), ARRAY_FILTER_USE_BOTH);
        // Con un borrador abierto, un mensaje que no aporta nada del evento es un cambio de tema.
        if ($draft && ! $wantsToCreate && array_diff_assoc(array_map('json_encode', $new), array_map('json_encode', $collected)) === []) {
            $this->drafts->discard($user, $scopes);

            return $this->outcome(self::NONE);
        }

        $data = array_merge($collected, $new);
        $missing = $this->missing($data);
        if ($missing !== []) {
            $this->drafts->save($user, $scopes[0] ?? 'user', $data, $missing);

            return $this->outcome(self::NEEDS_INPUT, missing: $missing);
        }

        $result = app(InteractionTracker::class)->measure('agenda_persist',
            fn () => $this->agenda->create($user, $this->attributes($data), $idempotencyKey, 'chat', $requestHash));
        if ($result->persisted() || $result->status === AgendaResult::CONFLICT) {
            $this->drafts->discard($user, $scopes);
        }

        return $this->fromResult($result);
    }

    /** Datos del extractor → entrada normalizada de AgendaService. */
    public function attributes(array $data): array
    {
        $tz = config('app.timezone');
        $allDay = (bool) ($data['all_day'] ?? false);
        $start = Carbon::parse($data['start_date'].' '.($allDay ? '00:00' : ($data['start_time'] ?? '00:00')), $tz);
        $end = null;
        if (! $allDay && ! empty($data['end_time'])) {
            $end = Carbon::parse(($data['end_date'] ?? $data['start_date']).' '.$data['end_time'], $tz);
        } elseif ($allDay && ! empty($data['end_date'])) {
            $end = Carbon::parse($data['end_date'], $tz);
        }

        $recurrence = $data['recurrence_type'] ?? 'none';

        return [
            'title' => $data['title'],
            'description' => $data['description'] ?? '',
            'location' => $data['location'] ?? '',
            'start' => $start,
            'end' => $end,
            'all_day' => $allDay,
            'reminder_minutes_before' => $data['reminder_minutes_before'] ?? 30,
            'series' => in_array($recurrence, ['daily', 'weekdays', 'weekly', 'custom'], true) ? [
                'type' => $recurrence,
                'days' => $data['recurrence_days'] ?? [],
                'until' => ! empty($data['recurrence_end_date']) ? Carbon::parse($data['recurrence_end_date'], $tz) : null,
            ] : null,
        ];
    }

    /** Lo que falta para poder crear: nombre, fecha válida y hora (salvo «todo el día»). */
    public function missing(array $data): array
    {
        $missing = [];
        $title = trim((string) ($data['title'] ?? ''));
        if ($title === '' || in_array(mb_strtolower($title), ['string', 'null', 'none', 'evento', 'evento sin título'], true)) {
            $missing[] = 'title';
        }
        if (empty($data['start_date']) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $data['start_date']) || ! strtotime($data['start_date'])) {
            $missing[] = 'start_date';
        }
        if (empty($data['all_day']) && (empty($data['start_time']) || ! preg_match('/^([01]?\d|2[0-3]):[0-5]\d$/', (string) $data['start_time']))) {
            $missing[] = 'start_time';
        }

        return $missing;
    }

    private function fromResult(AgendaResult $result): array
    {
        return $this->outcome($result->status, $result, $result->message);
    }

    /** @return array{status: string, result: ?AgendaResult, missing: array, message: ?string} */
    private function outcome(string $status, ?AgendaResult $result = null, ?string $message = null, array $missing = []): array
    {
        return ['status' => $status, 'result' => $result, 'missing' => $missing, 'message' => $message];
    }

    /** Contrato público `agenda` de la respuesta del chat (F2-06). */
    public static function contract(array $outcome): array
    {
        $events = $outcome['events'] ?? $outcome['result']?->events ?? [];
        $describe = fn ($event) => [
            'id' => $event->id,
            'series_id' => $event->series_id,
            'title' => $event->title,
            'start' => $event->start_date?->toIso8601String(),
            'end' => $event->end_date?->toIso8601String(),
            'all_day' => (bool) $event->all_day,
            'location' => $event->location ?: null,
            'status' => $event->status,
        ];

        return [
            'status' => $outcome['status'],
            'events' => array_map($describe, array_slice($events, 0, 1)),
            'count' => count($events),
            'missing' => $outcome['missing'],
            'message' => $outcome['message'],
            'candidates' => array_map($describe, $outcome['candidates'] ?? []),
        ];
    }

    /** Instrucciones para el modelo según lo que realmente ocurrió. */
    public static function instructions(array $outcome): string
    {
        $result = $outcome['result'];
        $event = $result?->first();
        $when = function ($event) {
            $start = Carbon::instance($event->start_date)->setTimezone(config('app.timezone'))->locale('es');

            return $start->isoFormat('dddd D [de] MMMM [de] YYYY').($event->all_day ? ', todo el día' : ' a las '.$start->format('H:i'));
        };

        return match ($outcome['status']) {
            AgendaResult::CREATED, AgendaResult::REPLAYED => "\n### Evento(s) creado(s) exitosamente:\n"
                ."Acabas de guardar en la agenda del usuario:\n- **Título:** {$event->title}\n"
                .(count($result->events) > 1 && $result->recurrenceSummary
                    ? "- **Recurrencia:** {$result->recurrenceSummary}\n- **Total de eventos creados:** ".count($result->events)."\n- **Primer evento:** ".$when($event)."\n"
                    : '- **Cuándo:** '.$when($event)."\n")
                .($event->location ? "- **Lugar:** {$event->location}\n" : '')
                .($event->reminder_minutes_before > 0 ? "- **Recordatorio:** {$event->reminder_minutes_before} minutos antes\n" : '')
                ."Confirma al usuario que quedó agendado y que puede verlo en su [Agenda](/agenda).\n",
            AgendaResult::DUPLICATE => "\n### Evento ya existente:\n"
                ."Ese evento ya estaba en la agenda (no se creó otro): **{$event->title}**, ".$when($event).".\n"
                ."Dile al usuario que ya estaba agendado. Si de verdad quiere uno repetido, que lo agregue desde la Agenda.\n",
            self::NEEDS_INPUT => "\n### Crear Evento en Agenda:\n"
                .'El usuario quiere agendar algo pero falta: '.implode(', ', array_map(fn ($m) => self::MISSING_LABELS[$m] ?? $m, $outcome['missing'])).'. '
                ."Pregunta SOLO por eso, de forma breve. No inventes datos (tampoco una hora por defecto) y no digas que ya quedó agendado.\n",
            AgendaResult::FAILED => "\n### Error al guardar en la agenda:\n"
                ."NO se pudo guardar el evento. No digas que quedó agendado. Explica que hubo un problema y sugiere intentarlo de nuevo o agregarlo desde la [Agenda](/agenda).\n",
            AgendaResult::INVALID => "\n### Datos del evento no válidos:\n"
                ."No se guardó nada: {$outcome['message']} Pide al usuario que lo corrija. No digas que quedó agendado.\n",
            AgendaResult::CONFLICT => "\n### Operación de agenda no ejecutada:\n"
                ."No se guardó nada: esta petición repite un identificador de otra operación. No digas que quedó agendado.\n",
            self::DRAFT_CANCELLED => "\n### Agenda:\nEl usuario desistió del evento que se estaba preparando. Confirma brevemente que no se agendó nada.\n",
            default => '',
        };
    }
}
