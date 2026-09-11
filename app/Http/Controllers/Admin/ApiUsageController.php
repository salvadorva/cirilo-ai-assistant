<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ApiUsageLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ApiUsageController extends Controller
{
    /**
     * Mostrar dashboard de uso de APIs
     */
    public function index(Request $request)
    {
        // Filtros
        $dateFrom = $request->input('date_from', Carbon::now()->subDays(30)->format('Y-m-d'));
        $dateTo = $request->input('date_to', Carbon::now()->format('Y-m-d'));
        $apiType = $request->input('api_type', 'all');
        $userId = $request->input('user_id', 'all');

        // Query base - Agregar tiempo completo a las fechas
        $dateFromFull = Carbon::parse($dateFrom)->startOfDay();
        $dateToFull = Carbon::parse($dateTo)->endOfDay();

        $query = ApiUsageLog::with('user')
            ->whereBetween('created_at', [$dateFromFull, $dateToFull]);

        if ($apiType !== 'all') {
            $query->where('api_type', $apiType);
        }

        if ($userId !== 'all') {
            $query->where('user_id', $userId);
        }

        // Estadísticas generales
        $stats = [
            'total_requests' => $query->count(),
            'total_cost' => $query->sum('estimated_cost'),
            'total_tokens' => $query->sum('total_tokens'),
            'avg_response_time' => $query->avg('response_time_ms'),
            'success_rate' => $query->where('status', 'success')->count() / max($query->count(), 1) * 100,
        ];

        // Uso por tipo de API
        $usageByType = ApiUsageLog::select('api_type', DB::raw('count(*) as count'), DB::raw('sum(estimated_cost) as cost'))
            ->whereBetween('created_at', [$dateFromFull, $dateToFull])
            ->groupBy('api_type')
            ->get();

        // Top usuarios
        $topUsers = ApiUsageLog::select('user_id', DB::raw('count(*) as count'), DB::raw('sum(estimated_cost) as cost'))
            ->whereBetween('created_at', [$dateFromFull, $dateToFull])
            ->whereNotNull('user_id')
            ->groupBy('user_id')
            ->orderBy('count', 'desc')
            ->limit(10)
            ->with('user')
            ->get();

        // Uso diario
        $dailyUsage = ApiUsageLog::select(
            DB::raw('DATE(created_at) as date'),
            DB::raw('count(*) as count'),
            DB::raw('sum(estimated_cost) as cost')
        )
            ->whereBetween('created_at', [$dateFromFull, $dateToFull])
            ->groupBy('date')
            ->orderBy('date', 'asc')
            ->get();

        // Logs recientes
        $recentLogs = ApiUsageLog::with('user')
            ->whereBetween('created_at', [$dateFromFull, $dateToFull])
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get();

        // Lista de usuarios para filtro
        $users = User::orderBy('name')->get();

        return view('admin.api-usage.index', compact(
            'stats',
            'usageByType',
            'topUsers',
            'dailyUsage',
            'recentLogs',
            'users',
            'dateFrom',
            'dateTo',
            'apiType',
            'userId'
        ));
    }

    /**
     * Exportar datos a CSV
     */
    public function export(Request $request)
    {
        $dateFrom = $request->input('date_from', Carbon::now()->subDays(30)->format('Y-m-d'));
        $dateTo = $request->input('date_to', Carbon::now()->format('Y-m-d'));

        $logs = ApiUsageLog::with('user')
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->orderBy('created_at', 'desc')
            ->get();

        $filename = 'api_usage_'.$dateFrom.'_to_'.$dateTo.'.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ];

        $callback = function () use ($logs) {
            $file = fopen('php://output', 'w');

            // Encabezados
            fputcsv($file, [
                'ID',
                'Fecha',
                'Usuario',
                'Proveedor',
                'Tipo API',
                'Modelo',
                'Tokens',
                'Costo (USD)',
                'Tiempo (ms)',
                'Estado',
                'IP',
            ]);

            // Datos
            foreach ($logs as $log) {
                fputcsv($file, [
                    $log->id,
                    $log->created_at->format('Y-m-d H:i:s'),
                    $log->user ? $log->user->name : 'N/A',
                    $log->api_provider,
                    $log->api_type,
                    $log->model,
                    $log->total_tokens,
                    number_format((float) $log->estimated_cost, 6),
                    $log->response_time_ms,
                    $log->status,
                    $log->ip_address,
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Ver detalles de un log específico
     */
    public function show($id)
    {
        $log = ApiUsageLog::with('user')->findOrFail($id);

        return view('admin.api-usage.show', compact('log'));
    }

    /**
     * Obtener estadísticas en formato JSON para gráficas
     */
    public function stats(Request $request)
    {
        $dateFrom = $request->input('date_from', Carbon::now()->subDays(30)->format('Y-m-d'));
        $dateTo = $request->input('date_to', Carbon::now()->format('Y-m-d'));

        $data = [
            'daily' => ApiUsageLog::select(
                DB::raw('DATE(created_at) as date'),
                'api_type',
                DB::raw('count(*) as count')
            )
                ->whereBetween('created_at', [$dateFrom, $dateTo])
                ->groupBy('date', 'api_type')
                ->orderBy('date', 'asc')
                ->get(),

            'by_type' => ApiUsageLog::select('api_type', DB::raw('count(*) as count'))
                ->whereBetween('created_at', [$dateFrom, $dateTo])
                ->groupBy('api_type')
                ->get(),

            'by_provider' => ApiUsageLog::select('api_provider', DB::raw('count(*) as count'))
                ->whereBetween('created_at', [$dateFrom, $dateTo])
                ->groupBy('api_provider')
                ->get(),
        ];

        return response()->json($data);
    }
}
