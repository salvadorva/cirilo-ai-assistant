<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\TodayController;
use App\Models\Task;
use App\Services\Tasks\TaskService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** F6: «Hoy» y pendientes para la app (mismos servicios y validación que la web). */
class MobileTodayController extends Controller
{
    public function __construct(private TodayController $web, private TaskService $tasks) {}

    public function today(Request $request): JsonResponse
    {
        return $this->web->data($request);
    }

    /** GET /api/mobile/tasks?status=open|done|all */
    public function index(Request $request): JsonResponse
    {
        $status = $request->query('status', 'open');
        $query = Task::where('user_id', $request->user()->id)->latest('id')->limit(100);
        match ($status) {
            'open' => $query->whereIn('status', ['open', 'postponed']),
            'all' => null,
            default => $query->where('status', $status),
        };

        return response()->json(['data' => $query->get()->map(fn ($t) => TaskService::describe($t))->values()]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate(['title' => 'required|string|max:255', 'due_date' => 'nullable|date_format:Y-m-d', 'notes' => 'nullable|string|max:2000']);
        [$status, $task] = $this->tasks->create($request->user(), $data['title'], $data['due_date'] ?? null, 'mobile', null, $data['notes'] ?? null);

        return response()->json(['status' => $status, 'task' => TaskService::describe($task)], $status === 'created' ? 201 : 200);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        return $this->web->update($request, $id);
    }

    public function preferences(Request $request): JsonResponse
    {
        return $this->web->preferences($request);
    }
}
