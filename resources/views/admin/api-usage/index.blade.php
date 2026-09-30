@extends('layout.app')

@section('title', 'Bitácora - Administración')

@section('content')
<div class="container-fluid py-4">
    <!-- Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h2 class="card-title mb-0">
                                <i class="fas fa-clipboard-list me-2"></i>
                                Bitácora de Uso de APIs
                            </h2>
                            <p class="text-muted mb-0">Monitoreo y estadísticas de uso de servicios de IA</p>
                        </div>
                        <div>
                            <span class="badge bg-danger">
                                <i class="fas fa-shield-alt me-1"></i>
                                Solo Administradores
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filtros -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <form method="GET" action="{{ route('admin.api-usage.index') }}" class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">Fecha Desde</label>
                            <input type="date" name="date_from" class="form-control" value="{{ $dateFrom }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Fecha Hasta</label>
                            <input type="date" name="date_to" class="form-control" value="{{ $dateTo }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Tipo de API</label>
                            <select name="api_type" class="form-select">
                                <option value="all" {{ $apiType == 'all' ? 'selected' : '' }}>Todos</option>
                                <option value="text_generation" {{ $apiType == 'text_generation' ? 'selected' : '' }}>Generación de Texto</option>
                                <option value="image_generation" {{ $apiType == 'image_generation' ? 'selected' : '' }}>Generación de Imágenes</option>
                                <option value="tts" {{ $apiType == 'tts' ? 'selected' : '' }}>Text-to-Speech</option>
                                <option value="stt" {{ $apiType == 'stt' ? 'selected' : '' }}>Speech-to-Text</option>
                                <option value="image_analysis" {{ $apiType == 'image_analysis' ? 'selected' : '' }}>Análisis de Imágenes</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Usuario</label>
                            <select name="user_id" class="form-select">
                                <option value="all" {{ $userId == 'all' ? 'selected' : '' }}>Todos</option>
                                @foreach($users as $user)
                                    <option value="{{ $user->id }}" {{ $userId == $user->id ? 'selected' : '' }}>
                                        {{ $user->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-filter me-1"></i> Filtrar
                            </button>
                            <a href="{{ route('admin.api-usage.export', request()->all()) }}" class="btn btn-success">
                                <i class="fas fa-download me-1"></i> Exportar CSV
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Estadísticas Generales -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <h6 class="card-subtitle mb-2 opacity-75">Total Solicitudes</h6>
                    <h2 class="card-title mb-0">{{ number_format($stats['total_requests']) }}</h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h6 class="card-subtitle mb-2 opacity-75">Estimación conocida (USD), no factura</h6>
                    <h2 class="card-title mb-0">${{ number_format($stats['total_cost'], 4) }}</h2>
                    <p class="mb-0">{{ $stats['unknown_costs'] }} solicitudes con costo desconocido; no incluidas en la suma.</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <h6 class="card-subtitle mb-2 opacity-75">Total Tokens</h6>
                    <h2 class="card-title mb-0">{{ number_format($stats['total_tokens']) }}</h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-white">
                <div class="card-body">
                    <h6 class="card-subtitle mb-2 opacity-75">Tasa de Éxito</h6>
                    <h2 class="card-title mb-0">{{ number_format($stats['success_rate'], 1) }}%</h2>
                </div>
            </div>
        </div>
    </div>

    <div class="alert alert-info">
        Valoraciones voluntarias (fecha y usuario; independiente del filtro de API): {{ $feedback->count() }}.
        Útiles: {{ $feedback->where('useful', true)->count() }};
        tareas logradas: {{ $feedback->where('task_achieved', true)->count() }};
        correcciones declaradas: {{ $feedback->sum('corrections') }}.
        Sin valoración no significa fracaso ni éxito. XP no mide utilidad.
    </div>
    <!-- Uso por Tipo de API -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Uso por Tipo de API</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Tipo</th>
                                    <th class="text-end">Solicitudes</th>
                                    <th class="text-end">Costo</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($usageByType as $usage)
                                <tr>
                                    <td>
                                        @switch($usage->api_type)
                                            @case('text_generation')
                                                <i class="fas fa-comment-dots text-primary"></i> Generación de Texto
                                                @break
                                            @case('image_generation')
                                                <i class="fas fa-image text-success"></i> Generación de Imágenes
                                                @break
                                            @case('tts')
                                                <i class="fas fa-volume-up text-info"></i> Text-to-Speech
                                                @break
                                            @case('stt')
                                                <i class="fas fa-microphone text-warning"></i> Speech-to-Text
                                                @break
                                            @case('image_analysis')
                                                <i class="fas fa-eye text-danger"></i> Análisis de Imágenes
                                                @break
                                        @endswitch
                                    </td>
                                    <td class="text-end">{{ number_format($usage->count) }}</td>
                                    <td class="text-end">${{ number_format($usage->cost, 4) }} conocidos<br>{{ $usage->unknown_count }} desconocidos</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Top Usuarios -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Top 10 Usuarios</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Usuario</th>
                                    <th class="text-end">Solicitudes</th>
                                    <th class="text-end">Costo</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($topUsers as $userUsage)
                                <tr>
                                    <td>
                                        <i class="fas fa-user text-muted me-1"></i>
                                        {{ $userUsage->user->name ?? 'Usuario Eliminado' }}
                                    </td>
                                    <td class="text-end">{{ number_format($userUsage->count) }}</td>
                                    <td class="text-end">${{ number_format($userUsage->cost, 4) }} conocidos<br>{{ $userUsage->unknown_count }} desconocidos</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Gráfica de Uso Diario -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Uso Diario</h5>
                </div>
                <div class="card-body">
                    <canvas id="dailyUsageChart" height="80"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Logs Recientes -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Logs Recientes (últimos 50)</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>Fecha</th>
                                    <th>Usuario</th>
                                    <th>Tipo</th>
                                    <th>Modelo</th>
                                    <th class="text-end">Tokens</th>
                                    <th class="text-end">Costo</th>
                                    <th class="text-end">Tiempo</th>
                                    <th>Estado</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($recentLogs as $log)
                                <tr>
                                    <td><a href="{{ route('admin.api-usage.show', $log->id) }}">{{ $log->created_at->format('d/m/Y H:i') }}</a></td>
                                    <td>{{ $log->user->name ?? 'N/A' }}</td>
                                    <td>
                                        <span class="badge bg-secondary">{{ $log->api_type }}</span>
                                    </td>
                                    <td>{{ $log->model }}</td>
                                    <td class="text-end">{{ number_format($log->total_tokens) }}</td>
                                    <td class="text-end">{{ $log->cost_status === 'estimated' && $log->estimated_cost !== null ? '$'.number_format($log->estimated_cost, 8) : 'Costo desconocido' }}</td>
                                    <td class="text-end">{{ $log->response_time_ms }}ms</td>
                                    <td>
                                        @if($log->status == 'success')
                                            <span class="badge bg-success">Éxito</span>
                                        @elseif($log->status == 'pending')
                                            <span class="badge bg-warning text-dark">Pendiente / incierto</span>
                                        @else
                                            <span class="badge bg-danger">Error</span>
                                        @endif
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<script>
    // Gráfica de uso diario
    const dailyData = @json($dailyUsage);
    const labels = dailyData.map(d => d.date);
    const counts = dailyData.map(d => d.count);
    const costs = dailyData.map(d => parseFloat(d.cost));

    const ctx = document.getElementById('dailyUsageChart').getContext('2d');
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'Solicitudes',
                data: counts,
                borderColor: 'rgb(75, 192, 192)',
                backgroundColor: 'rgba(75, 192, 192, 0.2)',
                yAxisID: 'y',
            }, {
                label: 'Estimación conocida (USD; parcial)',
                data: costs,
                borderColor: 'rgb(255, 99, 132)',
                backgroundColor: 'rgba(255, 99, 132, 0.2)',
                yAxisID: 'y1',
            }]
        },
        options: {
            responsive: true,
            interaction: {
                mode: 'index',
                intersect: false,
            },
            scales: {
                y: {
                    type: 'linear',
                    display: true,
                    position: 'left',
                    title: {
                        display: true,
                        text: 'Solicitudes'
                    }
                },
                y1: {
                    type: 'linear',
                    display: true,
                    position: 'right',
                    title: {
                        display: true,
                        text: 'Costo (USD)'
                    },
                    grid: {
                        drawOnChartArea: false,
                    },
                }
            }
        }
    });
</script>
@endpush
@endsection
