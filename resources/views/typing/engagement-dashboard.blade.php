@extends('layout.app')

@section('title', 'Dashboard de Engagement - TypeMaster AI')

@section('content')
<div class="container-fluid py-4">
    <div class="row">
        <!-- Header -->
        <div class="col-12 mb-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h2 class="mb-1">📊 Tu Engagement Dashboard</h2>
                            <p class="text-muted mb-0">Seguimiento de tu actividad y compromiso con TypeMaster AI</p>
                        </div>
                        <a href="{{ route('typing.index') }}" class="btn btn-outline-primary">
                            <i class="fas fa-arrow-left"></i> Volver
                        </a>
                    </div>
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
                        <svg width="180" height="180" viewBox="0 0 180 180">
                            <circle cx="90" cy="90" r="70" fill="none" stroke="#e9ecef" stroke-width="12"/>
                            <circle cx="90" cy="90" r="70" fill="none" 
                                stroke="{{ $progress->engagement_score >= 70 ? '#28a745' : ($progress->engagement_score >= 40 ? '#ffc107' : '#dc3545') }}" 
                                stroke-width="12"
                                stroke-dasharray="{{ 2 * 3.14159 * 70 }}"
                                stroke-dashoffset="{{ 2 * 3.14159 * 70 * (1 - $progress->engagement_score / 100) }}"
                                transform="rotate(-90 90 90)"
                                stroke-linecap="round"/>
                        </svg>
                        <div class="position-absolute top-50 start-50 translate-middle">
                            <h2 class="mb-0 fw-bold">{{ max(0, round($progress->engagement_score)) }}</h2>
                            <small class="text-muted">de 100</small>
                        </div>
                    </div>
                    
                    @if($progress->engagement_score >= 70)
                        <div class="alert alert-success mb-3">
                            <i class="fas fa-fire"></i> ¡Excelente compromiso!
                        </div>
                    @elseif($progress->engagement_score >= 40)
                        <div class="alert alert-warning mb-3">
                            <i class="fas fa-chart-line"></i> Buen progreso, ¡sigue así!
                        </div>
                    @else
                        <div class="alert alert-danger mb-3">
                            <i class="fas fa-exclamation-triangle"></i> Necesitas más actividad
                        </div>
                    @endif
                    
                    <!-- Cómo Subir tu Score -->
                    <div class="card bg-light border-0">
                        <div class="card-body p-3">
                            <h6 class="fw-bold mb-2" style="font-size: 0.875rem;">
                                <i class="fas fa-lightbulb text-warning"></i> ¿Cómo subir tu score?
                            </h6>
                            <ul class="list-unstyled mb-0 small text-start" style="font-size: 0.813rem; line-height: 1.6;">
                                <li class="mb-1">
                                    <strong>Actividad (40%)</strong>: Juega hoy para ganar hasta 40 puntos
                                </li>
                                <li class="mb-1">
                                    <strong>Frecuencia (30%)</strong>: Haz 5-7 sesiones esta semana
                                </li>
                                <li class="mb-1">
                                    <strong>Racha (20%)</strong>: Mantén 7+ días consecutivos
                                </li>
                                <li class="mb-0">
                                    <strong>Nivel (10%)</strong>: Sube de nivel practicando
                                </li>
                            </ul>
                        </div>
                    </div>
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
                            <p class="text-muted">¡Sigues muy activo!</p>
                        @elseif($progress->churn_risk_level === 'medium')
                            <div class="display-4 text-warning mb-2">⚠</div>
                            <h4 class="text-warning">Riesgo Medio</h4>
                            <p class="text-muted">Intenta ser más constante</p>
                        @elseif($progress->churn_risk_level === 'high')
                            <div class="display-4 text-orange mb-2">⚡</div>
                            <h4 class="text-orange">Alto Riesgo</h4>
                            <p class="text-muted">¡Te extrañamos!</p>
                        @else
                            <div class="display-4 text-danger mb-2">❌</div>
                            <h4 class="text-danger">Riesgo Crítico</h4>
                            <p class="text-muted">Hace mucho que no practicas</p>
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
                            <strong class="text-primary">
                                {{ max(0, (int)$progress->current_streak) }} días
                            </strong>
                        </div>
                        <div class="d-flex justify-content-between align-items-center p-2 bg-light rounded">
                            <span><i class="fas fa-trophy"></i> Mejor racha</span>
                            <strong class="text-success">
                                {{ max(0, (int)$progress->longest_streak) }} días
                            </strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Stats Card -->
        <div class="col-md-4 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <h5 class="card-title mb-4">Estadísticas Rápidas</h5>
                    
                    <div class="d-grid gap-3">
                        <div class="text-center p-3 bg-primary bg-opacity-10 rounded">
                            <div class="display-6 text-primary mb-2">{{ max(0, (int)$progress->total_sessions) }}</div>
                            <small class="text-muted">Sesiones Totales</small>
                        </div>
                        
                        <div class="text-center p-3 bg-success bg-opacity-10 rounded">
                            <div class="display-6 text-success mb-2">{{ number_format(max(0, $progress->total_xp)) }}</div>
                            <small class="text-muted">XP Total Ganado</small>
                        </div>
                        
                        <div class="text-center p-3 bg-warning bg-opacity-10 rounded">
                            <div class="display-6 text-warning mb-2">{{ max(0, (int)$progress->sessions_this_week) }}</div>
                            <small class="text-muted">Sesiones Esta Semana</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Weekly Progress Chart -->
        <div class="col-lg-8 mb-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h5 class="card-title mb-4">Progreso Semanal</h5>
                    
                    <canvas id="weeklyProgressChart" height="80"></canvas>
                </div>
            </div>
        </div>

        <!-- Activity Quality Distribution -->
        <div class="col-lg-4 mb-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h5 class="card-title mb-4">Calidad de Sesiones</h5>
                    
                    <div style="position: relative; height: 200px; max-height: 200px;">
                        <canvas id="qualityChart"></canvas>
                    </div>
                    
                    <div class="mt-3">
                        <small class="text-muted d-block mb-2">Distribución de calidad:</small>
                        <div class="d-flex justify-content-between mb-1">
                            <span class="badge bg-success">Excellent</span>
                            <span>{{ $qualityDistribution->get('excellent', 0) }}</span>
                        </div>
                        <div class="d-flex justify-content-between mb-1">
                            <span class="badge bg-info">Good</span>
                            <span>{{ $qualityDistribution->get('good', 0) }}</span>
                        </div>
                        <div class="d-flex justify-content-between mb-1">
                            <span class="badge bg-warning">Fair</span>
                            <span>{{ $qualityDistribution->get('fair', 0) }}</span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span class="badge bg-danger">Poor</span>
                            <span>{{ $qualityDistribution->get('poor', 0) }}</span>
                        </div>
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
                                        <th>Tipo</th>
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
                                            <span class="badge bg-primary">
                                                {{ ucfirst($activity->activity_type) }}
                                            </span>
                                        </td>
                                        <td>{{ max(0, (int)round($activity->duration_seconds / 60)) }} min</td>
                                        <td><strong class="text-success">+{{ max(0, (int)$activity->xp_earned) }} XP</strong></td>
                                        <td>
                                            @if($activity->session_quality === 'excellent')
                                                <span class="badge bg-success">Excelente</span>
                                            @elseif($activity->session_quality === 'good')
                                                <span class="badge bg-info">Buena</span>
                                            @elseif($activity->session_quality === 'fair')
                                                <span class="badge bg-warning">Regular</span>
                                            @else
                                                <span class="badge bg-secondary">{{ ucfirst($activity->session_quality) }}</span>
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
                            <p class="text-muted">No hay actividades registradas aún</p>
                            <a href="{{ route('typing.play') }}" class="btn btn-primary">
                                Comenzar a Practicar
                            </a>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Activity Types Distribution -->
        <div class="col-md-6 mb-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h5 class="card-title mb-4">Distribución por Tipo de Actividad</h5>
                    
                    <canvas id="activityTypesChart" height="150"></canvas>
                </div>
            </div>
        </div>

        <!-- Tips & Recommendations -->
        <div class="col-md-6 mb-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h5 class="card-title mb-4">💡 Recomendaciones</h5>
                    
                    @if($progress->days_inactive > 7)
                        <div class="alert alert-warning">
                            <strong>¡Te extrañamos!</strong> Has estado inactivo por {{ max(0, (int)$progress->days_inactive) }} días. 
                            Practica 10 minutos hoy para recuperar tu racha.
                        </div>
                    @endif
                    
                    @if($progress->current_streak >= 3)
                        <div class="alert alert-success">
                            <strong>¡Racha activa!</strong> Llevas {{ max(0, (int)$progress->current_streak) }} días consecutivos. 
                            ¡No la pierdas!
                        </div>
                    @endif
                    
                    @if($progress->sessions_this_week < 3)
                        <div class="alert alert-info">
                            <strong>Objetivo semanal:</strong> Intenta hacer al menos 5 sesiones esta semana para mejorar tu engagement.
                        </div>
                    @endif
                    
                    <div class="card bg-light border-0 mt-3">
                        <div class="card-body">
                            <h6 class="fw-bold mb-2">Tips para mejorar tu engagement:</h6>
                            <ul class="mb-0 small">
                                <li>Practica al menos 10 minutos diarios</li>
                                <li>Mantén tu racha activa</li>
                                <li>Completa sesiones de calidad (15+ minutos)</li>
                                <li>Prueba diferentes modos de juego</li>
                                <li>Establece objetivos semanales</li>
                            </ul>
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

// Quality Distribution Chart
const qualityCtx = document.getElementById('qualityChart').getContext('2d');
new Chart(qualityCtx, {
    type: 'doughnut',
    data: {
        labels: ['Excellent', 'Good', 'Fair', 'Poor'],
        datasets: [{
            data: [
                {{ $qualityDistribution->get('excellent', 0) }},
                {{ $qualityDistribution->get('good', 0) }},
                {{ $qualityDistribution->get('fair', 0) }},
                {{ $qualityDistribution->get('poor', 0) }}
            ],
            backgroundColor: [
                'rgba(40, 167, 69, 0.8)',
                'rgba(23, 162, 184, 0.8)',
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
                display: false
            }
        }
    }
});

// Activity Types Chart
const activityTypesCtx = document.getElementById('activityTypesChart').getContext('2d');
new Chart(activityTypesCtx, {
    type: 'pie',
    data: {
        labels: @json($activityTypes->keys()),
        datasets: [{
            data: @json($activityTypes->values()),
            backgroundColor: [
                'rgba(54, 162, 235, 0.8)',
                'rgba(255, 99, 132, 0.8)',
                'rgba(255, 206, 86, 0.8)',
                'rgba(75, 192, 192, 0.8)'
            ]
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
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
