@extends('layout.app')

@section('title', $pageTitle ?? 'Estadísticas - TypeMaster AI')

@section('content')
<div class="container-fluid px-4">
    <!-- Header Section -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-0 bg-gradient-primary text-white">
                <div class="card-body p-4">
                    <div class="row align-items-center">
                        <div class="col-lg-8">
                            <h1 class="display-6 fw-bold mb-3">
                                <i class="fas fa-chart-line me-3"></i>Mis Estadísticas
                            </h1>
                            <p class="lead mb-3">
                                Progreso detallado de tu entrenamiento en TypeMaster AI
                            </p>
                            <div class="d-flex flex-wrap gap-3">
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-level-up-alt me-2"></i>
                                    <span>Nivel {{ $gameProgress->level ?? 1 }}</span>
                                </div>
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-star me-2"></i>
                                    <span>{{ number_format($gameProgress->total_xp ?? 0) }} XP</span>
                                </div>
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-fire me-2"></i>
                                    <span>{{ $stats['total_sessions'] ?? 0 }} sesiones</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-4 text-center">
                            <div class="display-1">
                                <i class="fas fa-trophy"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- XP Progress -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h5 class="card-title mb-3">
                        <i class="fas fa-trophy me-2 text-warning"></i>Progreso de Nivel
                    </h5>
                    <div class="row align-items-center">
                        <div class="col-lg-8">
                            <div class="d-flex justify-content-between mb-2">
                                <span>Nivel {{ $gameProgress->level ?? 1 }}</span>
                                <span>Nivel {{ ($gameProgress->level ?? 1) + 1 }}</span>
                            </div>
                            <div class="progress mb-2" style="height: 20px;">
                                @php
                                    $currentXP = $gameProgress->current_xp ?? 0;
                                    $requiredXP = $gameProgress->required_xp ?? 250;
                                    $percentage = $requiredXP > 0 ? ($currentXP / $requiredXP) * 100 : 0;
                                @endphp
                                <div class="progress-bar bg-warning progress-bar-striped progress-bar-animated" 
                                     role="progressbar" 
                                     style="width: {{ min(100, $percentage) }}%">
                                    {{ $currentXP }}/{{ $requiredXP }} XP
                                </div>
                            </div>
                            <small class="text-muted">
                                Te faltan {{ max(0, $requiredXP - $currentXP) }} XP para subir de nivel
                            </small>
                        </div>
                        <div class="col-lg-4 text-center">
                            <h2 class="text-warning mb-0">{{ number_format($gameProgress->total_xp ?? 0) }}</h2>
                            <small class="text-muted">XP Total Ganado</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Stats Overview -->
    <div class="row mb-4">
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card h-100 border-0 shadow-sm text-center">
                <div class="card-body">
                    <div class="text-primary mb-2">
                        <i class="fas fa-tachometer-alt fa-2x"></i>
                    </div>
                    <h5 class="card-title">Mejor Velocidad</h5>
                    <h3 class="text-primary mb-0">{{ $stats['best_wpm'] ?? 0 }}</h3>
                    <small class="text-muted">WPM</small>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card h-100 border-0 shadow-sm text-center">
                <div class="card-body">
                    <div class="text-success mb-2">
                        <i class="fas fa-bullseye fa-2x"></i>
                    </div>
                    <h5 class="card-title">Precisión Promedio</h5>
                    <h3 class="text-success mb-0">{{ number_format($stats['avg_accuracy'] ?? 0, 1) }}%</h3>
                    <small class="text-muted">Últimos 30 días</small>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card h-100 border-0 shadow-sm text-center">
                <div class="card-body">
                    <div class="text-info mb-2">
                        <i class="fas fa-clock fa-2x"></i>
                    </div>
                    <h5 class="card-title">Tiempo Total</h5>
                    <h3 class="text-info mb-0">{{ number_format(($stats['total_time_minutes'] ?? 0) / 60, 1) }}h</h3>
                    <small class="text-muted">Practicando</small>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card h-100 border-0 shadow-sm text-center">
                <div class="card-body">
                    <div class="text-warning mb-2">
                        <i class="fas fa-fire fa-2x"></i>
                    </div>
                    <h5 class="card-title">Sesiones</h5>
                    <h3 class="text-warning mb-0">{{ $stats['total_sessions'] ?? 0 }}</h3>
                    <small class="text-muted">Completadas</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Mode Statistics -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-transparent border-0">
                    <h5 class="mb-0">
                        <i class="fas fa-gamepad me-2"></i>Estadísticas por Modo
                    </h5>
                </div>
                <div class="card-body">
                    @if($modeStats && $modeStats->count() > 0)
                        <div class="row">
                            @foreach($modeStats as $mode)
                                <div class="col-lg-3 col-md-6 mb-3">
                                    <div class="card bg-light border-0">
                                        <div class="card-body text-center">
                                            @php
                                                $modeInfo = [
                                                    'training' => ['icon' => 'fas fa-dumbbell', 'name' => 'Entrenamiento', 'color' => 'primary'],
                                                    'arcade' => ['icon' => 'fas fa-gamepad', 'name' => 'Arcade', 'color' => 'info'],
                                                    'survival' => ['icon' => 'fas fa-shield-alt', 'name' => 'Supervivencia', 'color' => 'danger'],
                                                    'zen' => ['icon' => 'fas fa-leaf', 'name' => 'Zen', 'color' => 'success'],
                                                    'practice' => ['icon' => 'fas fa-keyboard', 'name' => 'Práctica', 'color' => 'secondary']
                                                ];
                                                $info = $modeInfo[$mode->mode] ?? ['icon' => 'fas fa-keyboard', 'name' => ucfirst($mode->mode), 'color' => 'secondary'];
                                            @endphp
                                            <div class="text-{{ $info['color'] }} mb-2">
                                                <i class="{{ $info['icon'] }} fa-2x"></i>
                                            </div>
                                            <h6 class="card-title">{{ $info['name'] }}</h6>
                                            <div class="row text-center">
                                                <div class="col-6">
                                                    <small class="text-muted d-block">Sesiones</small>
                                                    <strong>{{ $mode->sessions }}</strong>
                                                </div>
                                                <div class="col-6">
                                                    <small class="text-muted d-block">WPM Prom.</small>
                                                    <strong>{{ number_format($mode->avg_wpm, 1) }}</strong>
                                                </div>
                                            </div>
                                            <div class="row text-center mt-2">
                                                <div class="col-6">
                                                    <small class="text-muted d-block">Precisión</small>
                                                    <strong>{{ number_format($mode->avg_accuracy, 1) }}%</strong>
                                                </div>
                                                <div class="col-6">
                                                    <small class="text-muted d-block">XP Ganado</small>
                                                    <strong>{{ number_format($mode->total_xp) }}</strong>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-center py-4 text-muted">
                            <i class="fas fa-gamepad fa-3x mb-3"></i>
                            <p>¡Aún no has completado sesiones en ningún modo!</p>
                            <a href="{{ route('typing.modes') }}" class="btn btn-primary">
                                <i class="fas fa-play me-2"></i>Comenzar a Jugar
                            </a>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Sessions -->
    <div class="row">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-transparent border-0">
                    <h5 class="mb-0">
                        <i class="fas fa-history me-2"></i>Sesiones Recientes
                    </h5>
                </div>
                <div class="card-body">
                    @if($recentSessions && $recentSessions->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Fecha</th>
                                        <th>Modo</th>
                                        <th>WPM</th>
                                        <th>Precisión</th>
                                        <th>Tiempo</th>
                                        <th>XP Ganado</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($recentSessions as $session)
                                        <tr>
                                            <td>
                                                <small>{{ $session->created_at->format('d/m/Y H:i') }}</small>
                                            </td>
                                            <td>
                                                @php
                                                    $modeInfo = [
                                                        'training' => ['name' => 'Entrenamiento', 'color' => 'primary'],
                                                        'arcade' => ['name' => 'Arcade', 'color' => 'info'],
                                                        'survival' => ['name' => 'Supervivencia', 'color' => 'danger'],
                                                        'zen' => ['name' => 'Zen', 'color' => 'success'],
                                                        'practice' => ['name' => 'Práctica', 'color' => 'secondary']
                                                    ];
                                                    $info = $modeInfo[$session->mode] ?? ['name' => ucfirst($session->mode), 'color' => 'secondary'];
                                                @endphp
                                                <span class="badge bg-{{ $info['color'] }}">{{ $info['name'] }}</span>
                                            </td>
                                            <td><strong>{{ number_format($session->wpm, 1) }}</strong></td>
                                            <td>
                                                <span class="text-{{ $session->accuracy >= 95 ? 'success' : ($session->accuracy >= 85 ? 'warning' : 'danger') }}">
                                                    {{ number_format($session->accuracy, 1) }}%
                                                </span>
                                            </td>
                                            <td>{{ gmdate('i:s', $session->duration_seconds ?? 0) }}</td>
                                            <td>
                                                <span class="text-warning">
                                                    <i class="fas fa-star me-1"></i>{{ $session->earned_xp ?? 0 }}
                                                </span>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="text-center py-4 text-muted">
                            <i class="fas fa-keyboard fa-3x mb-3"></i>
                            <p>¡Comienza tu primera sesión de práctica!</p>
                            <a href="{{ route('typing.index') }}" class="btn btn-primary">
                                <i class="fas fa-play me-2"></i>Ir a TypeMaster AI
                            </a>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center">
                    <h5 class="card-title mb-3">
                        <i class="fas fa-rocket me-2"></i>Acciones Rápidas
                    </h5>
                    <div class="d-flex flex-wrap justify-content-center gap-3">
                        <a href="{{ route('typing.index') }}" class="btn btn-primary">
                            <i class="fas fa-home me-2"></i>Inicio
                        </a>
                        <a href="{{ route('typing.modes') }}" class="btn btn-info">
                            <i class="fas fa-gamepad me-2"></i>Modos de Juego
                        </a>
                        <a href="{{ route('typing.lessons') }}" class="btn btn-success">
                            <i class="fas fa-book-open me-2"></i>Lecciones
                        </a>
                        <a href="{{ route('typing.play') }}" class="btn btn-warning">
                            <i class="fas fa-keyboard me-2"></i>Práctica Libre
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.bg-gradient-primary {
    background: linear-gradient(45deg, #3a29f9, #6c5ce7);
}

.card {
    border-radius: 15px;
}

.btn {
    border-radius: 10px;
}

.progress {
    border-radius: 10px;
}

.table th {
    border-top: none;
    font-weight: 600;
    color: #495057;
}

.table td {
    vertical-align: middle;
}

.badge {
    font-size: 0.75em;
}
</style>
@endsection