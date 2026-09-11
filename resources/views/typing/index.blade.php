@extends('layout.app')

@section('title', $pageTitle ?? 'TypeMaster AI')

@section('content')
<div class="container-fluid px-4">
    <!-- Header Section -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-0 bg-gradient-primary text-white">
                <div class="card-body p-4">
                    <div class="row align-items-center">
                        <div class="col-lg-8">
                            <h1 class="display-5 fw-bold mb-3">
                                <i class="fas fa-keyboard me-3"></i>TypeMaster AI
                            </h1>
                            <p class="lead mb-3">
                                Aprende mecanografía con inteligencia artificial. Mejora tu velocidad y precisión con textos generados dinámicamente.
                            </p>
                            <div class="d-flex flex-wrap gap-3">
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-brain me-2"></i>
                                    <span>Contenido IA</span>
                                </div>
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-chart-line me-2"></i>
                                    <span>Progreso en tiempo real</span>
                                </div>
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-trophy me-2"></i>
                                    <span>Sistema de logros</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-4 text-center">
                            <div class="display-1">
                                <i class="fas fa-rocket"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Stats Row -->
    <div class="row mb-4">
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body text-center">
                    <div class="text-primary mb-2">
                        <i class="fas fa-tachometer-alt fa-2x"></i>
                    </div>
                    <h5 class="card-title">Velocidad</h5>
                    <h3 class="text-primary mb-0" id="best-wpm">0</h3>
                    <small class="text-muted">WPM Máximo</small>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body text-center">
                    <div class="text-success mb-2">
                        <i class="fas fa-bullseye fa-2x"></i>
                    </div>
                    <h5 class="card-title">Precisión</h5>
                    <h3 class="text-success mb-0" id="avg-accuracy">0%</h3>
                    <small class="text-muted">Promedio</small>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body text-center">
                    <div class="text-info mb-2">
                        <i class="fas fa-clock fa-2x"></i>
                    </div>
                    <h5 class="card-title">Tiempo Total</h5>
                    <h3 class="text-info mb-0" id="total-time">0h</h3>
                    <small class="text-muted">Practicando</small>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body text-center">
                    <div class="text-warning mb-2">
                        <i class="fas fa-fire fa-2x"></i>
                    </div>
                    <h5 class="card-title">Sesiones</h5>
                    <h3 class="text-warning mb-0" id="total-sessions">0</h3>
                    <small class="text-muted">Completadas</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Game Modes -->
    <div class="row">
        <div class="col-12">
            <h3 class="mb-4">
                <i class="fas fa-gamepad me-2"></i>Modos de Juego
            </h3>
        </div>
    </div>

    <div class="row mb-4">
        <!-- Modo Práctica Libre -->
        <div class="col-lg-4 col-md-6 mb-4">
            <div class="card h-100 border-0 shadow-sm hover-card">
                <div class="card-body text-center p-4">
                    <div class="text-primary mb-3">
                        <i class="fas fa-play-circle fa-3x"></i>
                    </div>
                    <h5 class="card-title">Práctica Libre</h5>
                    <p class="card-text text-muted">
                        Practica con textos generados por IA. Elige el nivel y tema que prefieras.
                    </p>
                    <div class="mb-3">
                        <select class="form-select mb-2" id="free-practice-level">
                            <option value="beginner">Principiante</option>
                            <option value="intermediate">Intermedio</option>
                            <option value="advanced">Avanzado</option>
                        </select>
                        <select class="form-select" id="free-practice-theme">
                            <option value="general">General</option>
                            <option value="tech">Tecnología</option>
                            <option value="literature">Literatura</option>
                            <option value="science">Ciencia</option>
                            <option value="business">Negocios</option>
                        </select>
                    </div>
                    <button class="btn btn-primary btn-lg w-100" onclick="startFreePractice()">
                        <i class="fas fa-keyboard me-2"></i>Comenzar
                    </button>
                </div>
            </div>
        </div>

        <!-- Modo Lecciones -->
        <div class="col-lg-4 col-md-6 mb-4">
            <div class="card h-100 border-0 shadow-sm hover-card">
                <div class="card-body text-center p-4">
                    <div class="text-success mb-3">
                        <i class="fas fa-graduation-cap fa-3x"></i>
                    </div>
                    <h5 class="card-title">Lecciones Guiadas</h5>
                    <p class="card-text text-muted">
                        Sigue un curso estructurado desde principiante hasta experto.
                    </p>
                    <div class="mb-3">
                        <div class="progress mb-2">
                            <div class="progress-bar bg-success" role="progressbar" style="width: 25%"></div>
                        </div>
                        <small class="text-muted">Progreso: Lección 3 de 12</small>
                    </div>
                    <button class="btn btn-success btn-lg w-100" onclick="startLessons()">
                        <i class="fas fa-book-open me-2"></i>Continuar
                    </button>
                </div>
            </div>
        </div>

        <!-- Sprint 3: Modos de Juego Avanzados -->
        <div class="col-lg-4 col-md-6 mb-4">
            <div class="card h-100 border-0 shadow-sm hover-card">
                <div class="card-body text-center p-4">
                    <div class="text-info mb-3">
                        <i class="fas fa-gamepad fa-3x"></i>
                    </div>
                    <h5 class="card-title">Modos de Juego</h5>
                    <p class="card-text text-muted">
                        Entrenamiento, Arcade, Supervivencia y Zen. Múltiples desafíos para dominar el teclado.
                    </p>
                    <div class="mb-3">
                        <span class="badge bg-info text-white fs-6">¡Nuevo!</span>
                        <div class="mt-2">
                            <small class="text-muted">4 modos únicos disponibles</small>
                        </div>
                    </div>
                    <a href="{{ route('typing.modes') }}" class="btn btn-info btn-lg w-100">
                        <i class="fas fa-fire me-2"></i>Explorar Modos
                    </a>
                </div>
            </div>
        </div>

        <!-- Modo Competitivo -->
        <div class="col-lg-4 col-md-6 mb-4">
            <div class="card h-100 border-0 shadow-sm hover-card">
                <div class="card-body text-center p-4">
                    <div class="text-warning mb-3">
                        <i class="fas fa-trophy fa-3x"></i>
                    </div>
                    <h5 class="card-title">Modo Competitivo</h5>
                    <p class="card-text text-muted">
                        Compite contra otros usuarios y sube en el ranking global.
                    </p>
                    <div class="mb-3">
                        <span class="badge bg-warning text-dark fs-6">Próximamente</span>
                    </div>
                    <button class="btn btn-warning btn-lg w-100" disabled>
                        <i class="fas fa-lock me-2"></i>En Desarrollo
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Activity -->
    <div class="row">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-transparent border-0 pb-0">
                    <h5 class="mb-0">
                        <i class="fas fa-history me-2"></i>Actividad Reciente
                    </h5>
                </div>
                <div class="card-body">
                    <div id="recent-activity">
                        <div class="text-center py-4 text-muted">
                            <i class="fas fa-keyboard fa-2x mb-3"></i>
                            <p>¡Comienza tu primera sesión de práctica!</p>
                        </div>
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

.hover-card {
    transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
}

.hover-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.15) !important;
}

.card {
    border-radius: 15px;
}

.btn-lg {
    border-radius: 10px;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    loadUserStats();
});

function loadUserStats() {
    fetch('/typing/stats', {
        method: 'GET',
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const stats = data.stats;
            document.getElementById('best-wpm').textContent = stats.best_wpm || 0;
            document.getElementById('avg-accuracy').textContent = (stats.avg_accuracy || 0) + '%';
            document.getElementById('total-time').textContent = Math.floor((stats.total_time_minutes || 0) / 60) + 'h';
            document.getElementById('total-sessions').textContent = stats.total_sessions || 0;
            
            // Actualizar actividad reciente si hay sesiones
            if (stats.total_sessions > 0) {
                updateRecentActivity(stats);
            }
        }
    })
    .catch(error => {
        console.error('Error loading stats:', error);
    });
}

function updateRecentActivity(stats) {
    const recentActivity = document.getElementById('recent-activity');
    const improvementTrend = stats.improvement_trend || 0;
    const trendIcon = improvementTrend > 0 ? 'fas fa-arrow-up text-success' : 
                     improvementTrend < 0 ? 'fas fa-arrow-down text-danger' : 
                     'fas fa-minus text-muted';
    
    recentActivity.innerHTML = `
        <div class="row">
            <div class="col-md-6">
                <h6><i class="fas fa-chart-line me-2"></i>Progreso Reciente</h6>
                <p class="mb-1">Mejor WPM: <strong>${stats.best_wpm}</strong></p>
                <p class="mb-1">Precisión promedio: <strong>${stats.avg_accuracy}%</strong></p>
                <p class="mb-0">Tendencia: <i class="${trendIcon} me-1"></i><span>${Math.abs(improvementTrend)}</span></p>
            </div>
            <div class="col-md-6">
                <h6><i class="fas fa-clock me-2"></i>Tiempo de Práctica</h6>
                <p class="mb-1">Total sesiones: <strong>${stats.total_sessions}</strong></p>
                <p class="mb-1">Tiempo total: <strong>${stats.total_time_minutes}min</strong></p>
                <p class="mb-0">XP ganado: <strong>${stats.total_xp_earned}</strong></p>
            </div>
        </div>
    `;
}

function startFreePractice() {
    const level = document.getElementById('free-practice-level').value;
    const theme = document.getElementById('free-practice-theme').value;
    
    window.location.href = `/typing/play?mode=practice&level=${level}&theme=${theme}`;
}

function startLessons() {
    window.location.href = `{{ route('typing.lessons') }}`;
}
</script>
@endsection
