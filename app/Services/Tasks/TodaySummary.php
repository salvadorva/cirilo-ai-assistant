<?php

namespace App\Services\Tasks;

use App\Models\CalendarEvent;
use App\Models\User;
use App\Services\Agenda\AgendaService;
use Carbon\Carbon;

/**
 * F6-01/F6-04: «Hoy» con datos reales (agenda + pendientes), sin depender de un modelo: si falla
 * cualquier generación de texto, la vista sigue mostrando los compromisos. Usa la misma consulta de
 * agenda que el chat, así «¿Qué tengo hoy?» y la vista coinciden.
 */
class TodaySummary
{
    public function __construct(private AgendaService $agenda, private TaskService $tasks) {}

    public function for(User $user): array
    {
        $now = now()->setTimezone(config('app.timezone'));
        $events = $this->agenda->between($user, $now->copy()->startOfDay(), $now->copy()->endOfDay(), 50);
        $tasks = $this->tasks->forToday($user, $now);

        return [
            'date' => $now->toDateString(),
            'date_label' => $now->locale('es')->isoFormat('dddd D [de] MMMM'),
            'summary' => $this->sentence($events->count(), $tasks->count()),
            'events' => $events->map(fn (CalendarEvent $e) => self::describeEvent($e))->values()->all(),
            'tasks' => $tasks->map(fn ($t) => TaskService::describe($t))->values()->all(),
            'suggestions' => $this->tasks->suggestions($user)->map(fn ($t) => TaskService::describe($t))->values()->all(),
            'preferences' => [
                'enabled' => (bool) $user->daily_summary_enabled,
                'time' => $user->daily_summary_time ?: '07:30',
                'channel' => $user->daily_summary_channel ?: 'internal',
                'days' => $user->daily_summary_days ?: 'weekdays',
            ],
        ];
    }

    /** Texto plano para el resumen proactivo (notificación, Telegram o correo). */
    public function text(User $user): string
    {
        $data = $this->for($user);
        $lines = ['Hoy, '.$data['date_label'].': '.$data['summary']];
        foreach ($data['events'] as $event) {
            $lines[] = '• '.($event['all_day'] ? 'Todo el día' : $event['time']).' — '.$event['title'];
        }
        foreach ($data['tasks'] as $task) {
            $lines[] = '☐ '.$task['title'].($task['due_date'] && $task['due_date'] < $data['date'] ? ' (vencido)' : '');
        }

        return implode("\n", $lines);
    }

    public static function describeEvent(CalendarEvent $event): array
    {
        $start = Carbon::instance($event->start_date)->setTimezone(config('app.timezone'));

        return ['id' => $event->id, 'title' => $event->title, 'time' => $event->all_day ? null : $start->format('H:i'), 'all_day' => (bool) $event->all_day,
            'location' => $event->location ?: null, 'url' => '/agenda?fecha='.$start->toDateString().'&evento='.$event->id];
    }

    private function sentence(int $events, int $tasks): string
    {
        $plural = fn (int $n, string $one, string $many) => $n === 1 ? "1 {$one}" : "{$n} {$many}";
        if ($events === 0 && $tasks === 0) {
            return 'No tienes compromisos ni pendientes para hoy.';
        }

        return 'Tienes '.$plural($events, 'compromiso', 'compromisos').' y '.$plural($tasks, 'pendiente', 'pendientes').'.';
    }
}
