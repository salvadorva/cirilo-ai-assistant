@extends('layout.app')

@section('title', $pageTitle ?? 'Modos de Juego - TypeMaster AI')

@section('content')
<div class="container-fluid px-4">
    <!-- Header Section -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-0 bg-gradient-dark text-white">
                <div class="card-body p-4">
                    <div class="row align-items-center">
                        <div class="col-lg-8">
                            <h1 class="display-5 fw-bold mb-3">
                                <i class="fas fa-gamepad me-3"></i>Modos de Juego
                            </h1>
                            <p class="lead mb-3">
                                Elige tu modo favorito y domina el teclado con diferentes desafíos y mecánicas de juego.
                            </p>
                            <div class="d-flex flex-wrap gap-3">
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-trophy me-2"></i>
                                    <span>Múltiples desafíos</span>
                                </div>
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-chart-line me-2"></i>
                                    <span>Progreso único</span>
                                </div>
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-star me-2"></i>
                                    <span>Puntuaciones altas</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-4 text-center">
                            <div class="display-1">
                                <i class="fas fa-fire"></i>
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
                        <i class="fas fa-star fs-2"></i>
                    </div>
                    <h5 class="card-title">Nivel {{ $gameProgress->level }}</h5>
                    <p class="text-muted">{{ number_format($gameProgress->total_xp) }} XP Total</p>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body text-center">
                    <div class="text-success mb-2">
                        <i class="fas fa-keyboard fs-2"></i>
                    </div>
                    <h5 class="card-title">Sesiones</h5>
                    <p class="text-muted">{{ $user->typingSessions()->count() }} completadas</p>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body text-center">
                    <div class="text-warning mb-2">
                        <i class="fas fa-trophy fs-2"></i>
                    </div>
                    <h5 class="card-title">Score Total</h5>
                    <p class="text-muted">{{ number_format($gameProgress->total_score ?? 0) }}</p>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body text-center">
                    <div class="text-info mb-2">
                        <i class="fas fa-medal fs-2"></i>
                    </div>
                    <h5 class="card-title">Logros</h5>
                    <p class="text-muted">{{ $user->userAchievements()->count() }} obtenidos</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Game Modes Grid -->
    <div class="row">
        <!-- Modo Entrenamiento -->
        <div class="col-xl-3 col-lg-4 col-md-6 mb-4">
            <div class="card h-100 border-0 shadow-sm game-mode-card" data-mode="training">
                <div class="card-header bg-primary text-white text-center">
                    <i class="fas fa-dumbbell fs-1 mb-2"></i>
                    <h4 class="mb-0">Entrenamiento</h4>
                </div>
                <div class="card-body d-flex flex-column">
                    <p class="text-muted flex-grow-1">
                        Practica por filas del teclado y mejora técnica de dedos específicos.
                    </p>
                    <div class="mb-3">
                        <div class="d-flex justify-content-between text-sm">
                            <span><i class="fas fa-target"></i> Precisión</span>
                            <span><i class="fas fa-clock"></i> Sin límite</span>
                        </div>
                        <div class="d-flex justify-content-between text-sm mt-1">
                            <span><i class="fas fa-users"></i> Individual</span>
                            <span><i class="fas fa-star"></i> XP: 1.0x</span>
                        </div>
                    </div>
                    <div class="mb-3">
                        <small class="text-muted">
                            <strong>Mejor Score:</strong> 
                            {{ App\Models\TypingSession::getBestScoreByMode($user->id, 'training') }}
                        </small>
                    </div>
                    <a href="{{ route('typing.mode.training') }}" class="btn btn-primary w-100">
                        <i class="fas fa-play me-2"></i>Jugar
                    </a>
                </div>
            </div>
        </div>

        <!-- Modo Arcade -->
        <div class="col-xl-3 col-lg-4 col-md-6 mb-4">
            <div class="card h-100 border-0 shadow-sm game-mode-card" data-mode="arcade">
                <div class="card-header bg-success text-white text-center">
                    <i class="fas fa-rocket fs-1 mb-2"></i>
                    <h4 class="mb-0">Arcade</h4>
                </div>
                <div class="card-body d-flex flex-column">
                    <p class="text-muted flex-grow-1">
                        Palabras que caen estilo Tetris con power-ups y efectos visuales dinámicos.
                    </p>
                    <div class="mb-3">
                        <div class="d-flex justify-content-between text-sm">
                            <span><i class="fas fa-bolt"></i> Velocidad</span>
                            <span><i class="fas fa-magic"></i> Power-ups</span>
                        </div>
                        <div class="d-flex justify-content-between text-sm mt-1">
                            <span><i class="fas fa-fire"></i> Dinámico</span>
                            <span><i class="fas fa-star"></i> XP: 1.2x</span>
                        </div>
                    </div>
                    <div class="mb-3">
                        <small class="text-muted">
                            <strong>Mejor Score:</strong> 
                            {{ App\Models\TypingSession::getBestScoreByMode($user->id, 'arcade') }}
                        </small>
                    </div>
                    <a href="{{ route('typing.mode.arcade') }}" class="btn btn-success w-100">
                        <i class="fas fa-play me-2"></i>Jugar
                    </a>
                </div>
            </div>
        </div>

        <!-- Modo Supervivencia -->
        <div class="col-xl-3 col-lg-4 col-md-6 mb-4">
            <div class="card h-100 border-0 shadow-sm game-mode-card" data-mode="survival">
                <div class="card-header bg-danger text-white text-center">
                    <i class="fas fa-skull fs-1 mb-2"></i>
                    <h4 class="mb-0">Supervivencia</h4>
                </div>
                <div class="card-body d-flex flex-column">
                    <p class="text-muted flex-grow-1">
                        Sistema de vidas, dificultad progresiva y jefes finales con textos complejos.
                    </p>
                    <div class="mb-3">
                        <div class="d-flex justify-content-between text-sm">
                            <span><i class="fas fa-heart"></i> 3 Vidas</span>
                            <span><i class="fas fa-level-up-alt"></i> Progresivo</span>
                        </div>
                        <div class="d-flex justify-content-between text-sm mt-1">
                            <span><i class="fas fa-dragon"></i> Jefes</span>
                            <span><i class="fas fa-star"></i> XP: 1.5x</span>
                        </div>
                    </div>
                    <div class="mb-3">
                        <small class="text-muted">
                            <strong>Mejor Score:</strong> 
                            {{ App\Models\TypingSession::getBestScoreByMode($user->id, 'survival') }}
                        </small>
                    </div>
                    <a href="{{ route('typing.mode.survival') }}" class="btn btn-danger w-100">
                        <i class="fas fa-play me-2"></i>Jugar
                    </a>
                </div>
            </div>
        </div>

        <!-- Modo Zen -->
        <div class="col-xl-3 col-lg-4 col-md-6 mb-4">
            <div class="card h-100 border-0 shadow-sm game-mode-card" data-mode="zen">
                <div class="card-header bg-info text-white text-center">
                    <i class="fas fa-peace fs-1 mb-2"></i>
                    <h4 class="mb-0">Zen</h4>
                </div>
                <div class="card-body d-flex flex-column">
                    <p class="text-muted flex-grow-1">
                        Ambiente relajado con música, textos inspiracionales y visualizaciones calmantes.
                    </p>
                    <div class="mb-3">
                        <div class="d-flex justify-content-between text-sm">
                            <span><i class="fas fa-music"></i> Música</span>
                            <span><i class="fas fa-leaf"></i> Relajante</span>
                        </div>
                        <div class="d-flex justify-content-between text-sm mt-1">
                            <span><i class="fas fa-quote-left"></i> Inspiracional</span>
                            <span><i class="fas fa-star"></i> XP: 0.8x</span>
                        </div>
                    </div>
                    <div class="mb-3">
                        <small class="text-muted">
                            <strong>Mejor Score:</strong> 
                            {{ App\Models\TypingSession::getBestScoreByMode($user->id, 'zen') }}
                        </small>
                    </div>
                    <a href="{{ route('typing.mode.zen') }}" class="btn btn-info w-100">
                        <i class="fas fa-play me-2"></i>Jugar
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Access Section -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-light">
                    <h5 class="mb-0"><i class="fas fa-bolt me-2"></i>Acceso Rápido</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <a href="{{ route('typing.lessons') }}" class="btn btn-outline-primary w-100">
                                <i class="fas fa-book me-2"></i>Lecciones Estructuradas
                            </a>
                        </div>
                        <div class="col-md-4 mb-3">
                            <a href="{{ route('typing.play') }}" class="btn btn-outline-secondary w-100">
                                <i class="fas fa-keyboard me-2"></i>Práctica Libre
                            </a>
                        </div>
                        <div class="col-md-4 mb-3">
                            <a href="{{ route('typing.stats') }}" class="btn btn-outline-info w-100">
                                <i class="fas fa-chart-bar me-2"></i>Estadísticas
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.game-mode-card {
    transition: transform 0.3s ease, box-shadow 0.3s ease;
    cursor: pointer;
}

.game-mode-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.15);
}

.text-sm {
    font-size: 0.875rem;
}

.bg-gradient-dark {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Hacer que toda la tarjeta sea clickeable
    document.querySelectorAll('.game-mode-card').forEach(card => {
        card.addEventListener('click', function(e) {
            if (e.target.tagName !== 'A' && e.target.tagName !== 'BUTTON') {
                const link = this.querySelector('a[href]');
                if (link) {
                    window.location.href = link.href;
                }
            }
        });
    });
});
</script>
@endsection
