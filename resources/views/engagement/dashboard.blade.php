@extends('layout.app')

@section('title', 'Dashboard de Engagement')

@section('content')
<div class="container-fluid py-4">
    <div class="row">
        <!-- Header -->
        <div class="col-12 mb-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h2 class="mb-1">📊 Tu Dashboard de Engagement</h2>
                            <p class="text-muted mb-0">Seguimiento unificado de tu actividad en todos los juegos</p>
                        </div>
                        <div class="btn-group">
                            <a href="{{ route('typing.index') }}" class="btn btn-outline-primary btn-sm">
                                <i class="fas fa-keyboard"></i> TypeMaster
                            </a>
                            <a href="{{ route('games.index') }}" class="btn btn-outline-success btn-sm">
                                <i class="fas fa-language"></i> English Games
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Game Comparison Cards -->
        <div class="col-md-6 mb-4">
            <div class="card border-0 shadow-sm h-100" style="border-left: 4px solid #007bff !important;">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-keyboard text-primary"></i> TypeMaster AI
                        </h5>
                        <span class="badge bg-primary">Esta Semana</span>
                    </div>
                    
                    <div class="row text-center g-3">
                        <div class="col-4">
                            <div class="p-3 bg-light rounded">
                                <h3 class="mb-0 text-primary">{{ $typingStats['total_sessions'] }}</h3>
                                <small class="text-muted">Sesiones</small>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="p-3 bg-light rounded">
                                <h3 class="mb-0 text-success">{{ number_format($typingStats['total_xp']) }}</h3>
                                <small class="text-muted">XP</small>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="p-3 bg-light rounded">
                                <h3 class="mb-0 text-info">{{ $typingStats['total_minutes'] }}</h3>
                                <small class="text-muted">Minutos</small>
                            </div>
                        </div>
                    </div>
                    
                    @if($typingStats['total_sessions'] > 0)
                        <div class="mt-3 pt-3 border-top">
                            <div class="d-flex justify-content-between small">
                                <span class="text-muted">Mejor sesión:</span>
                                <strong>{{ $typingStats['best_session_xp'] ?? 0 }} XP</strong>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-md-6 mb-4">
            <div class="card border-0 shadow-sm h-100" style="border-left: 4px solid #28a745 !important;">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-language text-success"></i> English Games
                        </h5>
                        <span class="badge bg-success">Esta Semana</span>
                    </div>
                    
                    <div class="row text-center g-3">
                        <div class="col-4">
                            <div class="p-3 bg-light rounded">
                                <h3 class="mb-0 text-primary">{{ $englishStats['total_sessions'] }}</h3>
                                <small class="text-muted">Sesiones</small>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="p-3 bg-light rounded">
                                <h3 class="mb-0 text-success">{{ number_format($englishStats['total_xp']) }}</h3>
                                <small class="text-muted">XP</small>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="p-3 bg-light rounded">
                                <h3 class="mb-0 text-info">{{ $englishStats['total_minutes'] }}</h3>
                                <small class="text-muted">Minutos</small>
                            </div>
                        </div>
                    </div>
                    
                    @if($englishStats['total_sessions'] > 0)
                        <div class="mt-3 pt-3 border-top">
                            <div class="d-flex justify-content-between small mb-1">
                                <span class="text-muted">Mejor puntuación:</span>
                                <strong>{{ $englishStats['best_score'] ?? 0 }}</strong>
                            </div>
                            <div class="d-flex justify-content-between small">
                                <span class="text-muted">Precisión promedio:</span>
                                <strong>{{ number_format($englishStats['avg_accuracy'] ?? 0, 1) }}%</strong>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Engagement Score Card -->
        <div class="col-md-4 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center">
                    <h5 class="card-title mb-3">Score de Engagement</h5>
                    
                    <!-- Circular Progress -->
                    <div class="position-relative d-inline-block mb-3">
                        <svg width="160" height="160" viewBox="0 0 160 160">
                            <circle cx="80" cy="80" r="60" fill="none" stroke="#e9ecef" stroke-width="10"/>
                            <circle cx="80" cy="80" r="60" fill="none" 
                                stroke="{{ $progress->engagement_score >= 70 ? '#28a745' : ($progress->engagement_score >= 40 ? '#ffc107' : '#dc3545') }}" 
                                stroke-width="10"
                                stroke-dasharray="{{ 2 * 3.14159 * 60 }}"
                                stroke-dashoffset="{{ 2 * 3.14159 * 60 * (1 - $progress->engagement_score / 100) }}"
                                transform="rotate(-90 80 80)"
                                stroke-linecap="round"/>
                        </svg>
                        <div class="position-absolute top-50 start-50 translate-middle">
                            <h2 class="mb-0 fw-bold">{{ max(0, round($progress->engagement_score)) }}</h2>
                            <small class="text-muted">de 100</small>
                        </div>
                    </div>
                    
                    @if($progress->engagement_score >= 70)
                        <div class="alert alert-success mb-0">
                            <i class="fas fa-fire"></i> ¡Excelente compromiso!
                        </div>
                    @elseif($progress->engagement_score >= 40)
                        <div class="alert alert-warning mb-0">
                            <i class="fas fa-chart-line"></i> Buen progreso
                        </div>
                    @else
                        <div class="alert alert-danger mb-0">
                            <i class="fas fa-exclamation-triangle"></i> Más actividad
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Risk Level Card -->
        <div class="col-md-4 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <h5 class="card-title mb-4">Nivel de Riesgo</h5>
                    
                    <div class="text-center mb-4">
                        @if($progress->churn_risk_level === 'low')
                            <div class="display-4 text-success mb-2">✓</div>
                            <h4 class="text-success">Bajo Riesgo</h4>
                            <p class="text-muted mb-0">¡Sigues muy activo!</p>
                        @elseif($progress->churn_risk_level === 'medium')
                            <div class="display-4 text-warning mb-2">⚠</div>
                            <h4 class="text-warning">Riesgo Medio</h4>
                            <p class="text-muted mb-0">Más constancia</p>
                        @elseif($progress->churn_risk_level === 'high')
                            <div class="display-4 text-orange mb-2">⚡</div>
                            <h4 class="text-orange">Alto Riesgo</h4>
                            <p class="text-muted mb-0">¡Te extrañamos!</p>
                        @else
                            <div class="display-4 text-danger mb-2">❌</div>
                            <h4 class="text-danger">Riesgo Crítico</h4>
                            <p class="text-muted mb-0">Tiempo inactivo</p>
                        @endif
                    </div>
                    
                    <div class="d-grid gap-2">
                        <div class="d-flex justify-content-between align-items-center p-2 bg-light rounded">
                            <span><i class="fas fa-calendar-times"></i> Días inactivo</span>
                            <strong class="{{ $progress->days_inactive > 7 ? 'text-danger' : ($progress->days_inactive > 3 ? 'text-warning' : 'text-success') }}">
                                {{ max(0, (int)$progress->days_inactive) }}
                            </strong>
                        </div>
                        <div class="d-flex justify-content-between align-items-center p-2 bg-light rounded">
                            <span><i class="fas fa-fire"></i> Racha actual</span>
                            <strong class="text-primary">{{ max(0, (int)$progress->current_streak) }}</strong>
                        </div>
                        <div class="d-flex justify-content-between align-items-center p-2 bg-light rounded">
                            <span><i class="fas fa-trophy"></i> Mejor racha</span>
                            <strong class="text-success">{{ max(0, (int)$progress->longest_streak) }}</strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- XP Summary Card -->
        <div class="col-md-4 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body d-flex flex-column">
                    <h5 class="card-title mb-3">Progreso de XP</h5>

                    <div class="d-flex align-items-center mb-4">
                        <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center" style="width: 64px; height: 64px;">
                            <span class="h4 mb-0 fw-bold">{{ $xpSummary['level'] }}</span>
                        </div>
                        <div class="ms-3">
                            <div class="text-muted small">Nivel actual</div>
                            <div class="fw-semibold">{{ number_format($xpSummary['current_level_xp']) }} XP de {{ number_format($xpSummary['required_level_xp']) }}</div>
                            <div class="text-muted small">Faltan {{ number_format($xpSummary['xp_to_next_level']) }} XP para subir</div>
                        </div>
                    </div>

                    <div class="mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <small class="text-muted">Progreso hacia el siguiente nivel</small>
                            <small class="fw-semibold">{{ $xpSummary['level_progress_percent'] }}%</small>
                        </div>
                        <div class="progress" style="height: 10px;">
                            <div class="progress-bar bg-success" role="progressbar" style="width: {{ $xpSummary['level_progress_percent'] }}%;" aria-valuenow="{{ $xpSummary['level_progress_percent'] }}" aria-valuemin="0" aria-valuemax="100"></div>
                        </div>
                    </div>

                    <div class="d-grid gap-3 mt-auto">
                        <div class="d-flex justify-content-between align-items-center p-2 bg-primary bg-opacity-10 rounded">
                            <div>
                                <div class="text-muted small mb-1">XP ganado hoy</div>
                                <div class="fw-semibold text-primary">+{{ number_format($xpSummary['today_xp']) }} XP</div>
                            </div>
                            <i class="fas fa-sun text-primary"></i>
                        </div>

                        <div class="d-flex justify-content-between align-items-center p-2 bg-success bg-opacity-10 rounded">
                            <div>
                                <div class="text-muted small mb-1">XP esta semana</div>
                                <div class="fw-semibold text-success">+{{ number_format($xpSummary['weekly_xp']) }} XP</div>
                            </div>
                            <i class="fas fa-calendar-week text-success"></i>
                        </div>

                        <div class="d-flex justify-content-between align-items-center p-2 bg-warning bg-opacity-10 rounded">
                            <div>
                                <div class="text-muted small mb-1">XP total acumulada</div>
                                <div class="fw-semibold text-warning">{{ number_format($xpSummary['total_xp']) }} XP</div>
                            </div>
                            <i class="fas fa-trophy text-warning"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Weekly Progress Chart -->
        <div class="col-lg-8 mb-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h5 class="card-title mb-4">Progreso Semanal (Todos los Juegos)</h5>
                    
                    <canvas id="weeklyProgressChart" height="80"></canvas>
                </div>
            </div>
        </div>

        <!-- Activity Types Distribution -->
        <div class="col-lg-4 mb-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h5 class="card-title mb-4">Distribución de Actividad</h5>
                    
                    <div style="position: relative; height: 200px;">
                        <canvas id="activityTypesChart"></canvas>
                    </div>
                    
                    <div class="mt-3">
                        <small class="text-muted d-block mb-2">Por tipo de juego:</small>
                        @foreach($activityTypes as $type => $count)
                        <div class="d-flex justify-content-between mb-2">
                            <span class="badge bg-{{ $type === 'typing' ? 'primary' : 'success' }}">
                                {{ ucfirst($type) }}
                            </span>
                            <strong>{{ $count }} sesiones</strong>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Activities -->
        <div class="col-12 mb-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h5 class="card-title mb-4">Actividades Recientes</h5>
                    
                    @if($recentActivities->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Fecha</th>
                                        <th>Juego</th>
                                        <th>Duración</th>
                                        <th>XP Ganado</th>
                                        <th>Calidad</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($recentActivities as $activity)
                                    <tr>
                                        <td>{{ \Carbon\Carbon::parse($activity->session_start)->format('d/m/Y H:i') }}</td>
                                        <td>
                                            <span class="badge bg-{{ $activity->activity_type === 'typing' ? 'primary' : 'success' }}">
                                                {{ $activity->activity_type === 'typing' ? 'TypeMaster' : 'English Games' }}
                                            </span>
                                        </td>
                                        <td>{{ max(0, (int)round(($activity->session_duration_seconds ?? 0) / 60)) }} min</td>
                                        <td><strong class="text-success">+{{ max(0, (int)($activity->xp_earned ?? 0)) }} XP</strong></td>
                                        <td>
                                            @php($q = strtolower($activity->activity_quality ?? ''))
                                            @if($q === 'excellent')
                                                <span class="badge bg-success">Excelente</span>
                                            @elseif($q === 'productive')
                                                <span class="badge bg-primary">Productiva</span>
                                            @elseif($q === 'micro')
                                                <span class="badge bg-secondary">Micro</span>
                                            @else
                                                <span class="badge bg-secondary">-</span>
                                            @endif
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="text-center py-5">
                            <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                            <p class="text-muted mb-3">No hay actividades registradas aún</p>
                            <div class="btn-group">
                                <a href="{{ route('typing.play') }}" class="btn btn-primary">
                                    <i class="fas fa-keyboard"></i> TypeMaster
                                </a>
                                <a href="{{ route('games.index') }}" class="btn btn-success">
                                    <i class="fas fa-language"></i> English Games
                                </a>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Recommendations -->
        <div class="col-12 mb-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h5 class="card-title mb-4">💡 Recomendaciones Personalizadas</h5>
                    
                    <div class="row">
                        <div class="col-md-6">
                            @if($progress->days_inactive > 7)
                                <div class="alert alert-warning">
                                    <strong>¡Te extrañamos!</strong> Has estado inactivo por {{ max(0, (int)$progress->days_inactive) }} días. 
                                    Practica hoy para recuperar tu racha.
                                </div>
                            @endif
                            
                            @if($progress->current_streak >= 3)
                                <div class="alert alert-success">
                                    <strong>¡Racha activa!</strong> Llevas {{ max(0, (int)$progress->current_streak) }} días consecutivos. 
                                    ¡No la pierdas!
                                </div>
                            @endif
                            
                            @if($gameComparison['typing']['sessions'] === 0)
                                <div class="alert alert-info">
                                    <strong>Prueba TypeMaster:</strong> Mejora tu velocidad de escritura mientras ganas XP.
                                </div>
                            @endif
                            
                            @if($gameComparison['english']['sessions'] === 0)
                                <div class="alert alert-info">
                                    <strong>Prueba English Games:</strong> Aprende inglés de forma divertida con juegos interactivos.
                                </div>
                            @endif
                        </div>
                        
                        <div class="col-md-6">
                            <div class="card bg-light border-0">
                                <div class="card-body">
                                    <h6 class="fw-bold mb-3">Tips para mejorar tu engagement:</h6>
                                    <ul class="mb-0 small">
                                        <li class="mb-2">Practica al menos 10 minutos diarios en cualquier juego</li>
                                        <li class="mb-2">Mantén tu racha activa para ganar bonus de XP</li>
                                        <li class="mb-2">Alterna entre TypeMaster y English Games</li>
                                        <li class="mb-2">Completa sesiones de calidad (15+ minutos)</li>
                                        <li class="mb-0">Establece objetivos semanales realistas</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
// Weekly Progress Chart
const weeklyProgressCtx = document.getElementById('weeklyProgressChart').getContext('2d');
new Chart(weeklyProgressCtx, {
    type: 'bar',
    data: {
        labels: @json(array_column($dailyProgress, 'day')),
        datasets: [{
            label: 'Sesiones',
            data: @json(array_column($dailyProgress, 'sessions')),
            backgroundColor: 'rgba(54, 162, 235, 0.8)',
            borderColor: 'rgba(54, 162, 235, 1)',
            borderWidth: 1
        }, {
            label: 'XP Ganado',
            data: @json(array_column($dailyProgress, 'xp')),
            backgroundColor: 'rgba(75, 192, 192, 0.8)',
            borderColor: 'rgba(75, 192, 192, 1)',
            borderWidth: 1,
            yAxisID: 'y1'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        scales: {
            y: {
                beginAtZero: true,
                title: {
                    display: true,
                    text: 'Sesiones'
                }
            },
            y1: {
                beginAtZero: true,
                position: 'right',
                title: {
                    display: true,
                    text: 'XP'
                },
                grid: {
                    drawOnChartArea: false
                }
            }
        }
    }
});

// Activity Types Chart
const activityTypesCtx = document.getElementById('activityTypesChart').getContext('2d');
new Chart(activityTypesCtx, {
    type: 'doughnut',
    data: {
        labels: @json($activityTypes->keys()),
        datasets: [{
            data: @json($activityTypes->values()),
            backgroundColor: [
                'rgba(54, 162, 235, 0.8)',
                'rgba(40, 167, 69, 0.8)',
                'rgba(255, 193, 7, 0.8)',
                'rgba(220, 53, 69, 0.8)'
            ]
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom'
            }
        }
    }
});
</script>
@endpush
@endsection
