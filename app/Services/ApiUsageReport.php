<?php

namespace App\Services;

use App\Models\ApiUsageLog;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

class ApiUsageReport
{
    public function query(array $filters): Builder
    {
        $query = ApiUsageLog::query()->whereBetween('created_at', [
            Carbon::parse($filters['date_from'])->startOfDay(), Carbon::parse($filters['date_to'])->endOfDay(),
        ]);
        foreach (['api_type', 'user_id'] as $key) {
            if (($filters[$key] ?? 'all') !== 'all') {
                $query->where($key, $filters[$key]);
            }
        }

        return $query;
    }

    public function stats(Builder $query): array
    {
        $count = (clone $query)->count();
        $known = (clone $query)->where('cost_status', 'estimated')->whereNotNull('estimated_cost');

        return [
            'total_requests' => $count, 'total_cost' => $known->sum('estimated_cost'),
            'unknown_costs' => $count - $known->count(), 'total_tokens' => (clone $query)->sum('total_tokens'),
            'avg_response_time' => (clone $query)->avg('response_time_ms'),
            'success_rate' => (clone $query)->where('status', 'success')->count() / max($count, 1) * 100,
        ];
    }

    public function grouped(Builder $query, string $column): Builder
    {
        // $column is chosen by the controller, never from request input.
        return (clone $query)->selectRaw($column)
            ->selectRaw('COUNT(*) as count, SUM(CASE WHEN cost_status = ? THEN COALESCE(estimated_cost, 0) ELSE 0 END) as cost', ['estimated'])
            ->selectRaw('SUM(CASE WHEN cost_status != ? OR estimated_cost IS NULL THEN 1 ELSE 0 END) as unknown_count', ['estimated']);
    }
}
