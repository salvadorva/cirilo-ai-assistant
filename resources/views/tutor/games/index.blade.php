@extends('layout.app')

@section('title', 'Juegos de Inglés')

@section('content')
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />
<style>
    .game-card {
        border: none;
        box-shadow: 0 0.5rem 1.5rem 0.5rem rgba(0, 0, 0, 0.075);
        transition: all 0.3s ease;
        cursor: pointer;
        height: 100%;
    }
    .game-card:hover {
        transform: translateY(-10px);
        box-shadow: 0 1rem 3rem rgba(0, 0, 0, 0.175);
    }
    .game-icon {
        width: 80px;
        height: 80px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 2.5rem;
        margin: 0 auto 1rem;
    }
    .stats-badge {
        font-size: 0.85rem;
        padding: 0.25rem 0.5rem;
    }
    .gradient-bg-1 { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
    .gradient-bg-2 { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); }
    .gradient-bg-3 { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); }
    .gradient-bg-4 { background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); }
    .gradient-bg-5 { background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); }
</style>

<!-- Breadcrumb -->
<div class="mb-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('tutor') }}">Centro de Aprendizaje</a></li>
            <li class="breadcrumb-item"><a href="{{ route('tutor.english') }}">Curso de Inglés</a></li>
            <li class="breadcrumb-item active" aria-current="page">Juegos</li>
        </ol>
    </nav>
</div>

<!-- Header -->
<div class="card mb-4">
    <div class="card-body text-center py-5">
        <h1 class="display-4 fw-bold mb-3">🎮 Juegos de Inglés</h1>
        <p class="lead text-muted mb-4">Aprende inglés mientras te diviertes. Cada juego te ayudará a mejorar diferentes habilidades.</p>
        <div class="row justify-content-center">
            <div class="col-md-3 mb-2">
                <div class="card bg-primary text-white">
                    <div class="card-body py-3">
                        <h3 class="mb-0">{{ $progress->level }}</h3>
                        <small>Tu Nivel</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-2">
                <div class="card bg-success text-white">
                    <div class="card-body py-3">
                        <h3 class="mb-0">{{ number_format($progress->total_xp) }}</h3>
                        <small>XP Total</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Lista de Juegos -->
<div class="row g-4 mb-5">
    <!-- Word Match Rush -->
    <div class="col-md-6 col-lg-4">
        <div class="card game-card" onclick="window.location='{{ route('games.word-match-rush') }}'">
            <div class="card-body text-center p-4">
                <div class="game-icon gradient-bg-1 text-white">
                    🎯
                </div>
                <h3 class="fw-bold mb-2">Word Match Rush</h3>
                <p class="text-muted mb-3">Empareja palabras con sus traducciones contra el reloj</p>
                
                @if(isset($englishStats['word-match']))
                <div class="d-flex justify-content-around mb-3">
                    <div>
                        <span class="badge bg-light text-dark stats-badge">
                            <i class="fas fa-gamepad me-1"></i> {{ $englishStats['word-match']->sessions_played }}
                        </span>
                    </div>
                    <div>
                        <span class="badge bg-light text-dark stats-badge">
                            <i class="fas fa-star me-1"></i> {{ $englishStats['word-match']->best_score }}
                        </span>
                    </div>
                    <div>
                        <span class="badge bg-light text-dark stats-badge">
                            <i class="fas fa-bullseye me-1"></i> {{ number_format($englishStats['word-match']->avg_accuracy, 1) }}%
                        </span>
                    </div>
                </div>
                @endif
                
                <span class="badge bg-primary">Vocabulario</span>
                <span class="badge bg-warning text-dark">Velocidad</span>
            </div>
        </div>
    </div>
    
    <!-- Sentence Builder -->
    <div class="col-md-6 col-lg-4">
        <div class="card game-card" onclick="window.location='{{ route('games.sentence-builder') }}'">
            <div class="card-body text-center p-4">
                <div class="game-icon gradient-bg-2 text-white">
                    🧩
                </div>
                <h3 class="fw-bold mb-2">Sentence Builder</h3>
                <p class="text-muted mb-3">Construye oraciones correctas arrastrando palabras</p>
                
                @if(isset($englishStats['sentence-builder']))
                <div class="d-flex justify-content-around mb-3">
                    <div>
                        <span class="badge bg-light text-dark stats-badge">
                            <i class="fas fa-gamepad me-1"></i> {{ $englishStats['sentence-builder']->sessions_played }}
                        </span>
                    </div>
                    <div>
                        <span class="badge bg-light text-dark stats-badge">
                            <i class="fas fa-star me-1"></i> {{ $englishStats['sentence-builder']->best_score }}
                        </span>
                    </div>
                    <div>
                        <span class="badge bg-light text-dark stats-badge">
                            <i class="fas fa-bullseye me-1"></i> {{ number_format($englishStats['sentence-builder']->avg_accuracy, 1) }}%
                        </span>
                    </div>
                </div>
                @endif
                
                <span class="badge bg-info">Gramática</span>
                <span class="badge bg-success">Estructura</span>
            </div>
        </div>
    </div>
    
    <!-- Vocabulary Shooter -->
    <div class="col-md-6 col-lg-4">
        <div class="card game-card" onclick="window.location='{{ route('games.vocabulary-shooter') }}'">
            <div class="card-body text-center p-4">
                <div class="game-icon gradient-bg-3 text-white">
                    🎮
                </div>
                <h3 class="fw-bold mb-2">Vocabulary Shooter</h3>
                <p class="text-muted mb-3">Dispara a la traducción correcta antes de que caiga</p>
                
                @if(isset($englishStats['vocabulary-shooter']))
                <div class="d-flex justify-content-around mb-3">
                    <div>
                        <span class="badge bg-light text-dark stats-badge">
                            <i class="fas fa-gamepad me-1"></i> {{ $englishStats['vocabulary-shooter']->sessions_played }}
                        </span>
                    </div>
                    <div>
                        <span class="badge bg-light text-dark stats-badge">
                            <i class="fas fa-star me-1"></i> {{ $englishStats['vocabulary-shooter']->best_score }}
                        </span>
                    </div>
                    <div>
                        <span class="badge bg-light text-dark stats-badge">
                            <i class="fas fa-bullseye me-1"></i> {{ number_format($englishStats['vocabulary-shooter']->avg_accuracy, 1) }}%
                        </span>
                    </div>
                </div>
                @endif
                
                <span class="badge bg-primary">Vocabulario</span>
                <span class="badge bg-danger">Acción</span>
            </div>
        </div>
    </div>
    
    <!-- Grammar Runner -->
    <div class="col-md-6 col-lg-4">
        <div class="card game-card" onclick="window.location='{{ route('games.grammar-runner') }}'">
            <div class="card-body text-center p-4">
                <div class="game-icon gradient-bg-4 text-white">
                    🏃
                </div>
                <h3 class="fw-bold mb-2">Grammar Runner</h3>
                <p class="text-muted mb-3">Corre y salta eligiendo las opciones correctas</p>
                
                @if(isset($englishStats['grammar-runner']))
                <div class="d-flex justify-content-around mb-3">
                    <div>
                        <span class="badge bg-light text-dark stats-badge">
                            <i class="fas fa-gamepad me-1"></i> {{ $englishStats['grammar-runner']->sessions_played }}
                        </span>
                    </div>
                    <div>
                        <span class="badge bg-light text-dark stats-badge">
                            <i class="fas fa-star me-1"></i> {{ $englishStats['grammar-runner']->best_score }}
                        </span>
                    </div>
                    <div>
                        <span class="badge bg-light text-dark stats-badge">
                            <i class="fas fa-bullseye me-1"></i> {{ number_format($englishStats['grammar-runner']->avg_accuracy, 1) }}%
                        </span>
                    </div>
                </div>
                @endif
                
                <span class="badge bg-info">Gramática</span>
                <span class="badge bg-warning text-dark">Plataformas</span>
            </div>
        </div>
    </div>
    
    <!-- Listening Challenge -->
    <div class="col-md-6 col-lg-4">
        <div class="card game-card" onclick="window.location='{{ route('games.listening-challenge') }}'">
            <div class="card-body text-center p-4">
                <div class="game-icon gradient-bg-5 text-white">
                    🎧
                </div>
                <h3 class="fw-bold mb-2">Listening Challenge</h3>
                <p class="text-muted mb-3">Escucha y selecciona la respuesta correcta</p>
                
                @if(isset($englishStats['listening']))
                <div class="d-flex justify-content-around mb-3">
                    <div>
                        <span class="badge bg-light text-dark stats-badge">
                            <i class="fas fa-gamepad me-1"></i> {{ $englishStats['listening']->sessions_played }}
                        </span>
                    </div>
                    <div>
                        <span class="badge bg-light text-dark stats-badge">
                            <i class="fas fa-star me-1"></i> {{ $englishStats['listening']->best_score }}
                        </span>
                    </div>
                    <div>
                        <span class="badge bg-light text-dark stats-badge">
                            <i class="fas fa-bullseye me-1"></i> {{ number_format($englishStats['listening']->avg_accuracy, 1) }}%
                        </span>
                    </div>
                </div>
                @endif
                
                <span class="badge bg-secondary">Listening</span>
                <span class="badge bg-primary">Comprensión</span>
            </div>
        </div>
    </div>
    
    <!-- Próximamente... -->
    <div class="col-md-6 col-lg-4">
        <div class="card game-card" style="opacity: 0.6; cursor: not-allowed;">
            <div class="card-body text-center p-4">
                <div class="game-icon" style="background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%);">
                    🔒
                </div>
                <h3 class="fw-bold mb-2">Más Juegos</h3>
                <p class="text-muted mb-3">Próximamente nuevos juegos emocionantes</p>
                <span class="badge bg-secondary">Próximamente</span>
            </div>
        </div>
    </div>
</div>

<!-- Consejos -->
<div class="card">
    <div class="card-header">
        <h4 class="card-title mb-0"><i class="fas fa-lightbulb me-2"></i>Consejos para Jugar</h4>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-4">
                <h5><i class="fas fa-clock text-warning me-2"></i>Practica Regularmente</h5>
                <p class="text-muted">Juega 15-20 minutos diarios para mejores resultados</p>
            </div>
            <div class="col-md-4">
                <h5><i class="fas fa-chart-line text-success me-2"></i>Sube de Nivel</h5>
                <p class="text-muted">Los juegos se adaptan a tu nivel actual</p>
            </div>
            <div class="col-md-4">
                <h5><i class="fas fa-trophy text-primary me-2"></i>Gana XP</h5>
                <p class="text-muted">Cada juego te otorga experiencia y logros</p>
            </div>
        </div>
    </div>
</div>

@endsection
