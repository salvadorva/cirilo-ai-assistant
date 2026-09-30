<?php

namespace App\Http\Controllers;

use App\Services\Tasks\TaskService;
use App\Services\Tasks\TodaySummary;
use Illuminate\Http\Request;

/**
 * F6: vista «Hoy», pendientes y preferencias del resumen diario (web). La API móvil equivalente
 * está en Api\MobileTodayController.
 */
class TodayController extends Controller
{
    public function __construct(private TaskService $tasks, private TodaySummary $today) {}

    public function index(Request $request)
    {
        return view('today.index', ['today' => $this->today->for($request->user()), 'user' => $request->user()]);
    }

    public function data(Request $request)
    {
        return response()->json($this->today->for($request->user()));
    }

    public function store(Request $request)
    {
        $data = $request->validate(['title' => 'required|string|max:255', 'due_date' => 'nullable|date_format:Y-m-d', 'notes' => 'nullable|string|max:2000']);
        [$status, $task] = $this->tasks->create($request->user(), $data['title'], $data['due_date'] ?? null, 'web', null, $data['notes'] ?? null);

        return response()->json(['status' => $status, 'task' => TaskService::describe($task)], $status === 'created' ? 201 : 200);
    }

    public function update(Request $request, int $id)
    {
        $data = $request->validate(['action' => 'nullable|in:'.implode(',', TaskService::ACTIONS), 'until' => 'nullable|date_format:Y-m-d',
            'title' => 'sometimes|string|max:255', 'due_date' => 'sometimes|nullable|date_format:Y-m-d']);
        $task = $this->tasks->owned($request->user(), $id);
        abort_unless($task, 404);

        return response()->json(['task' => TaskService::describe($this->tasks->update($request->user(), $task, $data))]);
    }

    public function preferences(Request $request)
    {
        $data = $request->validate(['enabled' => 'required|boolean', 'time' => 'required|date_format:H:i',
            'channel' => 'required|in:internal,telegram,email', 'days' => 'required|in:daily,weekdays']);
        $request->user()->forceFill(['daily_summary_enabled' => $data['enabled'], 'daily_summary_time' => $data['time'],
            'daily_summary_channel' => $data['channel'], 'daily_summary_days' => $data['days']])->save();

        return response()->json(['ok' => true] + $data);
    }
}
