<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CalendarEvent;
use App\Services\Agenda\AgendaResult;
use App\Services\Agenda\AgendaService;
use App\Services\Agenda\AgendaValidationException;
use App\Services\Agenda\EventNotifier;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MobileAgendaController extends Controller
{
    /**
     * GET /api/mobile/agenda/events?from=2026-05-01&to=2026-05-31
     * Si no se pasan parámetros, devuelve próximos 30 días.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $from = $request->input('from')
            ? Carbon::parse($request->input('from'))->startOfDay()
            : Carbon::today();
        $to = $request->input('to')
            ? Carbon::parse($request->input('to'))->endOfDay()
            : Carbon::today()->addDays(30)->endOfDay();

        $events = CalendarEvent::where('user_id', $user->id)
            ->where(function ($q) use ($from, $to) {
                $q->whereBetween('start_date', [$from, $to])
                  ->orWhereBetween('end_date', [$from, $to])
                  ->orWhere(function ($qq) use ($from, $to) {
                      $qq->where('start_date', '<=', $from)
                         ->where('end_date', '>=', $to);
                  });
            })
            ->orderBy('start_date')
            ->get()
            ->map(fn (CalendarEvent $e) => $this->format($e));

        return response()->json(['events' => $events]);
    }

    /**
     * GET /api/mobile/agenda/upcoming
     * Próximos 5 eventos desde ahora.
     */
    public function upcoming(Request $request): JsonResponse
    {
        $events = CalendarEvent::where('user_id', $request->user()->id)
            ->upcoming()
            ->orderBy('start_date')
            ->limit(5)
            ->get()
            ->map(fn (CalendarEvent $e) => $this->format($e));

        return response()->json(['events' => $events]);
    }

    /**
     * GET /api/mobile/agenda/events/{id}
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $event = CalendarEvent::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (! $event) {
            return response()->json(['message' => 'No encontrado'], 404);
        }

        return response()->json($this->format($event) + ['notifications' => EventNotifier::summary($event)]);
    }

    /**
     * POST /api/mobile/agenda/events
     */
    public function store(Request $request, AgendaService $agenda): JsonResponse
    {
        $data = $this->validatePayload($request);
        $key = $request->header('Idempotency-Key');
        $result = $agenda->create($request->user(), $this->attributes($data), is_string($key) && $key !== '' ? mb_substr($key, 0, 100) : null, 'mobile_api');

        return match ($result->status) {
            AgendaResult::CREATED => response()->json($this->format($result->first()), 201),
            AgendaResult::REPLAYED => response()->json($this->format($result->first()))->header('Idempotent-Replay', 'true'),
            AgendaResult::DUPLICATE => response()->json($this->format($result->first()) + ['duplicate' => true]),
            AgendaResult::CONFLICT => response()->json(['message' => $result->message, 'code' => 'idempotency_conflict'], 409),
            AgendaResult::INVALID => response()->json(['message' => $result->message, 'errors' => ['start_date' => [$result->message]]], 422),
            default => response()->json(['message' => 'No se pudo guardar el evento.'], 500),
        };
    }

    /**
     * PUT /api/mobile/agenda/events/{id}
     */
    public function update(Request $request, int $id, AgendaService $agenda): JsonResponse
    {
        $event = CalendarEvent::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (! $event) {
            return response()->json(['message' => 'No encontrado'], 404);
        }

        try {
            $agenda->update($request->user(), $event, $this->attributes($this->validatePayload($request, $partial = true)));
        } catch (AgendaValidationException $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => ['start_date' => [$e->getMessage()]]], 422);
        }

        return response()->json($this->format($event->fresh()));
    }

    /**
     * DELETE /api/mobile/agenda/events/{id}
     */
    public function destroy(Request $request, int $id, AgendaService $agenda): JsonResponse
    {
        $event = CalendarEvent::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (! $event) {
            return response()->json(['message' => 'No encontrado'], 404);
        }

        $agenda->delete($request->user(), $event);

        return response()->json(['ok' => true]);
    }

    private function validatePayload(Request $request, bool $partial = false): array
    {
        $rules = [
            'title'                   => ($partial ? 'sometimes|' : 'required|').'string|max:255',
            'description'             => 'nullable|string|max:5000',
            'location'                => 'nullable|string|max:255',
            'category'                => 'nullable|string|max:50',
            'color'                   => 'nullable|string|max:20',
            'start_date'              => ($partial ? 'sometimes|' : 'required|').'date',
            'end_date'                => 'nullable|date',
            'all_day'                 => 'sometimes|boolean',
            'reminder_minutes_before' => 'nullable|integer|min:0|max:10080',
            'recurrence_type'         => 'nullable|in:daily,weekly,monthly,yearly',
            'recurrence_end_date'     => 'nullable|date',
        ];

        return $request->validate($rules);
    }

    /** Payload validado → entrada de AgendaService. Las fechas conservan el desfase que envía el teléfono. */
    private function attributes(array $data): array
    {
        $map = ['start_date' => 'start', 'end_date' => 'end'];
        $attributes = [];
        foreach ($data as $key => $value) {
            $attributes[$map[$key] ?? $key] = in_array($key, ['start_date', 'end_date', 'recurrence_end_date'], true) && $value !== null ? Carbon::parse($value) : $value;
        }

        return $attributes;
    }

    private function format(CalendarEvent $event): array
    {
        return [
            'id'                      => $event->id,
            'title'                   => $event->title,
            'description'             => $event->description,
            'location'                => $event->location,
            'category'                => $event->category,
            'color'                   => $event->color,
            'start_date'              => $event->start_date?->toIso8601String(),
            'end_date'                => $event->end_date?->toIso8601String(),
            'all_day'                 => (bool) $event->all_day,
            'reminder_minutes_before' => (int) ($event->reminder_minutes_before ?? 0),
            'status'                  => $event->status,
            'notified'                => (bool) $event->notified,
            'recurrence_type'         => $event->recurrence_type,
            'recurrence_end_date'     => $event->recurrence_end_date?->toIso8601String(),
            'series_id'               => $event->series_id,
            'nextcloud_synced'        => (bool) ($event->nextcloud_synced ?? false),
        ];
    }
}
