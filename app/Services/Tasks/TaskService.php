<?php

namespace App\Services\Tasks;

use App\Models\Task;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * F6-02/F6-03: pendientes con estado. Crear detecta duplicados abiertos; las sugerencias de los
 * resúmenes no son compromisos hasta que el usuario las acepta.
 */
class TaskService
{
    public const ACTIONS = ['complete', 'postpone', 'dismiss', 'reopen', 'accept'];

    /** @return array{0: string, 1: Task} [created|duplicate, tarea] */
    public function create(User $user, string $title, ?string $dueDate = null, string $source = 'web', ?int $conversationId = null, ?string $notes = null): array
    {
        $title = $this->title($title);
        if ($existing = $this->openWithTitle($user, $title)) {
            return ['duplicate', $existing];
        }

        return ['created', Task::create(['user_id' => $user->id, 'title' => $title, 'notes' => $notes, 'status' => 'open',
            'due_date' => $this->date($dueDate, 'due_date'), 'source' => $source, 'source_conversation_id' => $conversationId])];
    }

    /** Puntos pendientes de un resumen → sugerencias, sin repetir lo ya propuesto, aceptado o descartado. */
    public function suggest(User $user, array $items, ?int $conversationId): int
    {
        $known = Task::where('user_id', $user->id)->where('created_at', '>=', now()->subDays(60))->pluck('title')
            ->map(fn ($t) => $this->key($t))->all();
        $created = 0;
        foreach (array_slice($items, 0, 5) as $item) {
            if (! is_string($item) || trim($item) === '' || in_array($this->key($item), $known, true)) {
                continue;
            }
            Task::create(['user_id' => $user->id, 'title' => $this->title($item), 'status' => 'suggested', 'source' => 'summary',
                'source_conversation_id' => $conversationId]);
            $known[] = $this->key($item);
            $created++;
        }

        return $created;
    }

    public function owned(User $user, int|string $id): ?Task
    {
        return Task::where('user_id', $user->id)->find($id);
    }

    /** Aplica una acción de estado y/o cambios de título y fecha. */
    public function update(User $user, Task $task, array $input): Task
    {
        abort_unless($task->user_id === $user->id, 404);

        if (isset($input['title'])) {
            $task->title = $this->title($input['title']);
        }
        if (array_key_exists('due_date', $input)) {
            $task->due_date = $this->date($input['due_date'], 'due_date');
        }
        match ($input['action'] ?? null) {
            'complete' => $task->fill(['status' => 'done', 'completed_at' => now(), 'postponed_until' => null]),
            'postpone' => $task->fill(['status' => 'postponed', 'postponed_until' => $this->futureDate($input['until'] ?? null)]),
            'dismiss' => $task->fill(['status' => 'dismissed', 'postponed_until' => null]),
            'reopen', 'accept' => $task->fill(['status' => 'open', 'completed_at' => null, 'postponed_until' => null]),
            null => null,
            default => throw ValidationException::withMessages(['action' => 'Acción no válida.']),
        };
        $task->save();

        return $task;
    }

    /** Pendientes para hoy: abiertos sin fecha o vencidos/que vencen hoy, y pospuestos cuyo día llegó. */
    public function forToday(User $user, ?Carbon $today = null): Collection
    {
        $today = ($today ?? now())->copy()->setTimezone(config('app.timezone'))->toDateString();

        return Task::where('user_id', $user->id)
            ->where(fn ($q) => $q->where(fn ($q2) => $q2->where('status', 'open')->where(fn ($q3) => $q3->whereNull('due_date')->orWhereDate('due_date', '<=', $today)))
                ->orWhere(fn ($q2) => $q2->where('status', 'postponed')->whereDate('postponed_until', '<=', $today)))
            ->orderByRaw('due_date is null')->orderBy('due_date')->orderBy('id')
            ->get();
    }

    public function suggestions(User $user): Collection
    {
        return Task::where('user_id', $user->id)->where('status', 'suggested')->latest('id')->limit(5)->get();
    }

    public static function describe(Task $task): array
    {
        return ['id' => $task->id, 'title' => $task->title, 'status' => $task->status, 'due_date' => $task->due_date?->toDateString(),
            'postponed_until' => $task->postponed_until?->toDateString(), 'source' => $task->source, 'url' => '/hoy#tarea-'.$task->id];
    }

    private function openWithTitle(User $user, string $title): ?Task
    {
        return Task::where('user_id', $user->id)->whereIn('status', ['open', 'postponed'])->get()
            ->first(fn (Task $task) => $this->key($task->title) === $this->key($title));
    }

    private function title(string $title): string
    {
        $title = trim(preg_replace('/\s+/u', ' ', $title), " \t.,;:");
        if ($title === '' || mb_strlen($title) > 255) {
            throw ValidationException::withMessages(['title' => 'El pendiente necesita un título de hasta 255 caracteres.']);
        }

        return mb_strtoupper(mb_substr($title, 0, 1)).mb_substr($title, 1);
    }

    private function key(string $title): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', $title), " \t.,;:"));
    }

    private function date(?string $value, string $field): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) || ! strtotime($value)) {
            throw ValidationException::withMessages([$field => 'Usa una fecha AAAA-MM-DD.']);
        }

        return Carbon::parse($value, config('app.timezone'))->startOfDay();
    }

    private function futureDate(?string $value): Carbon
    {
        $date = $this->date($value, 'until') ?? now()->setTimezone(config('app.timezone'))->addDay()->startOfDay();
        if ($date->lte(now()->setTimezone(config('app.timezone'))->startOfDay())) {
            throw ValidationException::withMessages(['until' => 'Posponer requiere una fecha futura.']);
        }

        return $date;
    }
}
