<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiInteraction;
use App\Models\ApiUsageLog;
use App\Models\User;
use App\Services\ApiUsageReport;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ApiUsageController extends Controller
{
    private function filters(Request $request): array
    {
        $request->mergeIfMissing(['date_from' => now()->subDays(30)->toDateString(), 'date_to' => now()->toDateString(), 'api_type' => 'all', 'user_id' => 'all']);
        $data = $request->validate([
            'date_from' => 'sometimes|required|date_format:Y-m-d',
            'date_to' => 'sometimes|required|date_format:Y-m-d|after_or_equal:date_from',
            'api_type' => ['sometimes', 'required', Rule::in(['all', 'text_generation', 'image_generation', 'image_analysis', 'tts', 'stt'])],
            'user_id' => ['sometimes', 'required', 'regex:/^(all|[1-9][0-9]*)$/'],
        ]);

        return $data;
    }

    public function index(Request $request, ApiUsageReport $report)
    {
        $filters = $this->filters($request);
        $query = $report->query($filters);
        $stats = $report->stats($query);
        $usageByType = $report->grouped($query, 'api_type')->groupBy('api_type')->get();
        $topUsers = $report->grouped($query, 'user_id')->whereNotNull('user_id')->groupBy('user_id')->orderByDesc('count')->limit(10)->with('user')->get();
        $dailyUsage = $report->grouped($query, 'DATE(created_at) as date')->groupBy('date')->orderBy('date')->get();
        $recentLogs = (clone $query)->with('user')->latest()->limit(50)->get();
        $users = User::orderBy('name')->get(['id', 'name']);
        // Feedback is per interaction, not per API call; it has no api_type dimension.
        $feedback = AiInteraction::whereDate('created_at', '>=', $filters['date_from'])->whereDate('created_at', '<=', $filters['date_to'])
            ->when($filters['user_id'] !== 'all', fn ($q) => $q->where('user_id', $filters['user_id']))
            ->whereNotNull('useful')->get(['useful', 'task_achieved', 'corrections']);

        return view('admin.api-usage.index', compact('stats', 'usageByType', 'topUsers', 'dailyUsage', 'recentLogs', 'users', 'feedback') + [
            'dateFrom' => $filters['date_from'], 'dateTo' => $filters['date_to'], 'apiType' => $filters['api_type'], 'userId' => $filters['user_id'],
        ]);
    }

    public function export(Request $request, ApiUsageReport $report)
    {
        $filters = $this->filters($request);
        $query = $report->query($filters)->with('user')->latest();

        return response()->streamDownload(function () use ($query) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['ID', 'Fecha', 'Usuario', 'Proveedor', 'Tipo API', 'Modelo', 'Tokens', 'Estimación USD (no factura)', 'Estado costo', 'Tarifa fecha', 'Tiempo ms', 'Estado', 'Interacción', 'Etapa'], ',', '"', '');
            foreach ($query->lazy(500) as $log) {
                $row = [$log->id, $log->created_at->format('Y-m-d H:i:s'), $log->user?->name ?? 'N/A', $log->api_provider,
                    $log->api_type, $log->model, $log->total_tokens,
                    $log->cost_status === 'estimated' && $log->estimated_cost !== null ? $log->estimated_cost : 'Costo desconocido',
                    $log->cost_status, $log->pricing_date, $log->response_time_ms, $log->status, $log->interaction_id, $log->stage];
                // Spreadsheet formula injection protection, including user-controlled names.
                $row = array_map(fn ($value) => is_string($value) && preg_match('/^[\\s]*[=+@-]/u', $value) ? "'".$value : $value, $row);
                fputcsv($file, $row, ',', '"', '');
            }
            fclose($file);
        }, 'api_usage_'.$filters['date_from'].'_to_'.$filters['date_to'].'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function show($id)
    {
        $log = ApiUsageLog::with('user')->findOrFail($id);
        $interaction = $log->interaction_id ? AiInteraction::find($log->interaction_id) : null;
        $attempts = $log->interaction_id ? ApiUsageLog::where('interaction_id', $log->interaction_id)->orderBy('id')->get() : collect([$log]);

        return view('admin.api-usage.show', compact('log', 'interaction', 'attempts'));
    }

    public function stats(Request $request, ApiUsageReport $report)
    {
        $query = $report->query($this->filters($request));

        return response()->json([
            'summary' => $report->stats($query),
            'daily' => $report->grouped($query, 'DATE(created_at) as date, api_type')->groupBy('date', 'api_type')->orderBy('date')->get(),
            'by_type' => $report->grouped($query, 'api_type')->groupBy('api_type')->get(),
            'by_provider' => $report->grouped($query, 'api_provider')->groupBy('api_provider')->get(),
        ]);
    }
}
