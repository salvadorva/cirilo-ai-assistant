@extends('layout.app')

@section('title', 'Estadísticas de Ejercicios')

@section('content')
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />
<style>
    .card { border: none; box-shadow: 0 0.5rem 1.5rem 0.5rem rgba(0, 0, 0, 0.075); }
    .card-header { background-color: #fff; border-bottom: 1px solid #eff2f5; }
    .btn-primary { background-color: #009ef7; border-color: #009ef7; }
    .btn-primary:hover { background-color: #0095e8; border-color: #0095e8; }
    .stat-card { transition: transform 0.2s; }
    .stat-card:hover { transform: translateY(-5px); }
    .badge-vocabulary { background-color: #3498db; }
    .badge-grammar { background-color: #e74c3c; }
    .badge-speaking { background-color: #2ecc71; }
    .badge-listening { background-color: #f39c12; }
</style>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h2 class="card-title fw-bold"><i class="fa-solid fa-chart-line me-2"></i>Estadísticas de Ejercicios</h2>
        <div>
            <a href="{{ route('tutor.exercise.history') }}" class="btn btn-sm btn-primary">
                <i class="fas fa-history me-2"></i>Ver Historial
            </a>
            <a href="{{ route('tutor.exercises') }}" class="btn btn-sm btn-outline-primary ms-2">
                <i class="fas fa-arrow-left me-2"></i>Volver a Ejercicios
            </a>
        </div>
    </div>
    <div class="card-body">
        <!-- Filtros -->
        <div class="row mb-4">
            <div class="col-md-12">
                <form action="{{ route('tutor.exercise.statistics') }}" method="GET" class="d-flex gap-3">
                    <div class="form-group">
                        <label for="period">Periodo</label>
                        <select name="period" id="period" class="form-select" onchange="this.form.submit()">
                            <option value="week" {{ request('period') == 'week' ? 'selected' : '' }}>Última semana</option>
                            <option value="month" {{ request('period') == 'month' ? 'selected' : '' }}>Último mes</option>
                            <option value="year" {{ request('period') == 'year' ? 'selected' : '' }}>Último año</option>
                            <option value="all" {{ request('period') == 'all' ? 'selected' : '' }}>Todo el tiempo</option>
                        </select>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Resumen de estadísticas -->
        <div class="row mb-4">
            <div class="col-md-3 mb-4">
                <div class="card bg-light stat-card h-100">
                    <div class="card-body text-center p-4">
                        <div class="display-4 fw-bold text-primary mb-2">{{ $stats['total_exercises'] }}</div>
                        <p class="text-muted mb-0">Ejercicios Completados</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-4">
                <div class="card bg-light stat-card h-100">
                    <div class="card-body text-center p-4">
                        <div class="display-4 fw-bold text-success mb-2">{{ $stats['average_score'] }}%</div>
                        <p class="text-muted mb-0">Puntuación Media</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-4">
                <div class="card bg-light stat-card h-100">
                    <div class="card-body text-center p-4">
                        <div class="display-4 fw-bold text-warning mb-2">{{ $stats['best_score'] }}%</div>
                        <p class="text-muted mb-0">Mejor Puntuación</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-4">
                <div class="card bg-light stat-card h-100">
                    <div class="card-body text-center p-4">
                        <div class="display-4 fw-bold text-info mb-2">{{ $stats['streak'] }}</div>
                        <p class="text-muted mb-0">Días Consecutivos</p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Gráfico de progreso -->
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title">Progreso de Puntuación</h4>
                    </div>
                    <div class="card-body">
                        <canvas id="scoreProgressChart" height="300"></canvas>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Distribución por tipo de ejercicio -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title">Distribución por Tipo</h4>
                    </div>
                    <div class="card-body">
                        <canvas id="exerciseTypeChart" height="300"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title">Puntuación por Tipo</h4>
                    </div>
                    <div class="card-body">
                        <canvas id="scoreByTypeChart" height="300"></canvas>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Desglose por nivel -->
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title">Puntuación por Nivel</h4>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Nivel</th>
                                        <th>Ejercicios</th>
                                        <th>Puntuación Media</th>
                                        <th>Mejor Puntuación</th>
                                        <th>Progreso</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($stats['levels'] as $level => $levelStats)
                                        <tr>
                                            <td><span class="badge bg-primary">{{ $level }}</span></td>
                                            <td>{{ $levelStats['count'] }}</td>
                                            <td>{{ $levelStats['average'] }}%</td>
                                            <td>{{ $levelStats['best'] }}%</td>
                                            <td>
                                                <div class="progress" style="height: 10px;">
                                                    <div class="progress-bar {{ $levelStats['average'] >= 80 ? 'bg-success' : ($levelStats['average'] >= 60 ? 'bg-warning' : 'bg-danger') }}" 
                                                         style="width: {{ $levelStats['average'] }}%"></div>
                                                </div>
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
</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Datos para los gráficos
        const progressData = @json($stats['progress_data']);
        const typeDistribution = @json($stats['type_distribution']);
        const scoreByType = @json($stats['score_by_type']);
        
        // Configurar colores
        const typeColors = {
            vocabulary: '#3498db',
            grammar: '#e74c3c',
            speaking: '#2ecc71',
            listening: '#f39c12'
        };
        
        // Gráfico de progreso
        const progressCtx = document.getElementById('scoreProgressChart').getContext('2d');
        new Chart(progressCtx, {
            type: 'line',
            data: {
                labels: progressData.labels,
                datasets: [{
                    label: 'Puntuación',
                    data: progressData.scores,
                    fill: false,
                    borderColor: '#009ef7',
                    tension: 0.1,
                    pointBackgroundColor: '#009ef7'
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 100,
                        title: {
                            display: true,
                            text: 'Puntuación'
                        }
                    },
                    x: {
                        title: {
                            display: true,
                            text: 'Fecha'
                        }
                    }
                }
            }
        });
        
        // Gráfico de distribución por tipo
        const typeCtx = document.getElementById('exerciseTypeChart').getContext('2d');
        new Chart(typeCtx, {
            type: 'doughnut',
            data: {
                labels: Object.keys(typeDistribution).map(type => {
                    switch(type) {
                        case 'vocabulary': return 'Vocabulario';
                        case 'grammar': return 'Gramática';
                        case 'speaking': return 'Speaking';
                        case 'listening': return 'Listening';
                        default: return type;
                    }
                }),
                datasets: [{
                    data: Object.values(typeDistribution),
                    backgroundColor: Object.keys(typeDistribution).map(type => typeColors[type])
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
        
        // Gráfico de puntuación por tipo
        const scoreTypeCtx = document.getElementById('scoreByTypeChart').getContext('2d');
        new Chart(scoreTypeCtx, {
            type: 'bar',
            data: {
                labels: Object.keys(scoreByType).map(type => {
                    switch(type) {
                        case 'vocabulary': return 'Vocabulario';
                        case 'grammar': return 'Gramática';
                        case 'speaking': return 'Speaking';
                        case 'listening': return 'Listening';
                        default: return type;
                    }
                }),
                datasets: [{
                    label: 'Puntuación Media',
                    data: Object.values(scoreByType),
                    backgroundColor: Object.keys(scoreByType).map(type => typeColors[type])
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 100,
                        title: {
                            display: true,
                            text: 'Puntuación Media (%)'
                        }
                    }
                }
            }
        });
    });
</script>
@endpush
@endsection
