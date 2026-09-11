@extends('layout.app')

@section('title', 'Curso de Inglés')

@section('content')
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />
<style>
    .card { border: none; box-shadow: 0 0.5rem 1.5rem 0.5rem rgba(0, 0, 0, 0.075); }
    .card-header { background-color: #fff; border-bottom: 1px solid #eff2f5; }
    .btn-primary { background-color: #009ef7; border-color: #009ef7; }
    .btn-primary:hover { background-color: #0095e8; border-color: #0095e8; }
    .btn-success { background-color: #50cd89; border-color: #50cd89; }
    .feature-icon { width: 55px; height: 55px; border-radius: 8px; background-color: #f1faff; 
                   color: #009ef7; display: flex; align-items: center; justify-content: center; 
                   font-size: 1.5rem; margin-bottom: 1rem; }
    .level-badge { font-size: 1.5rem; padding: 0.5rem 1rem; }
    .action-card { transition: transform 0.2s; cursor: pointer; }
    .action-card:hover { transform: translateY(-5px); }
</style>

<!-- Mensajes de alerta -->
@if(session('success'))
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <i class="fas fa-check-circle me-2"></i> {{ session('success') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
@endif

<!-- Breadcrumb -->
<div class="mb-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('tutor') }}">Centro de Aprendizaje</a></li>
            <li class="breadcrumb-item active" aria-current="page">Curso de Inglés</li>
        </ol>
    </nav>
</div>

<div class="card mb-5">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h2 class="card-title fw-bold mb-0"><i class="fa-solid fa-graduation-cap me-2"></i>Modo Tutor de Inglés</h2>
        @if(isset($level))
            <span class="badge bg-primary level-badge">Nivel {{ $level }}</span>
        @endif
    </div>
    <div class="card-body">
        <p class="lead mb-4">Bienvenido al Modo Tutor de Inglés. Aquí podrás evaluar tu nivel actual y seguir un plan personalizado para mejorar tus habilidades.</p>
        
        <!-- Características principales -->
        <div class="row mb-5">
            <div class="col-md-4 mb-4">
                <div class="feature-icon">
                    <i class="fas fa-chart-line"></i>
                </div>
                <h4>Evaluación Personalizada</h4>
                <p class="text-muted">Determina tu nivel actual de inglés (A1-C2) mediante pruebas interactivas de comprensión, vocabulario, gramática y expresión oral.</p>
            </div>
            <div class="col-md-4 mb-4">
                <div class="feature-icon">
                    <i class="fas fa-route"></i>
                </div>
                <h4>Plan de Aprendizaje</h4>
                <p class="text-muted">Recibe un roadmap personalizado con metas semanales y ejercicios adaptados a tu nivel y objetivos de aprendizaje.</p>
            </div>
            <div class="col-md-4 mb-4">
                <div class="feature-icon">
                    <i class="fas fa-trophy"></i>
                </div>
                <h4>Progreso Gamificado</h4>
                <p class="text-muted">Mantén la motivación con un sistema de puntos, medallas y desafíos que hacen el aprendizaje divertido y efectivo.</p>
            </div>
        </div>
        
        <!-- Acciones principales -->
        <div class="row">
            <div class="col-md-4 mb-4">
                <div class="card h-100 action-card">
                    <div class="card-body text-center p-5">
                        <i class="fas fa-spell-check mb-3" style="font-size: 3rem; color: #009ef7;"></i>
                        <h3 class="mb-3">Evaluación de Nivel</h3>
                        <p class="mb-4">Realiza una evaluación completa para determinar tu nivel actual de inglés y recibir recomendaciones personalizadas.</p>
                        <a href="{{ route('tutor.evaluation') }}" class="btn btn-primary btn-lg px-5">
                            <i class="fas fa-play-circle me-2"></i> Comenzar evaluación
                        </a>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-4">
                <div class="card h-100 action-card">
                    <div class="card-body text-center p-5">
                        <i class="fas fa-tasks mb-3" style="font-size: 3rem; color: #50cd89;"></i>
                        <h3 class="mb-3">Ejercicios Personalizados</h3>
                        <p class="mb-4">Accede a ejercicios diseñados específicamente para tu nivel y practica tus habilidades de forma estructurada.</p>
                        <a href="{{ route('tutor.exercises') }}" class="btn btn-success btn-lg px-5">
                            <i class="fas fa-book-open me-2"></i> Ver ejercicios
                        </a>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-4">
                <div class="card h-100 action-card">
                    <div class="card-body text-center p-5">
                        <i class="fas fa-gamepad mb-3" style="font-size: 3rem; color: #f1416c;"></i>
                        <h3 class="mb-3">Juegos Interactivos</h3>
                        <p class="mb-4">Aprende inglés jugando con desafíos divertidos y dinámicos que te mantendrán motivado.</p>
                        <a href="{{ route('games.index') }}" class="btn btn-danger btn-lg px-5">
                            <i class="fas fa-trophy me-2"></i> Jugar ahora
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Progreso del usuario -->
        @if(isset($englishProgress))
        <div class="card mt-4">
            <div class="card-header">
                <h4 class="card-title mb-0">Tu progreso actual</h4>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h5>Nivel: {{ $englishProgress['level'] }}</h5>
                        <div class="progress mb-3" style="height: 10px;">
                            @php
                                $levelProgress = 0;
                                if($englishProgress['level'] == 'A1') $levelProgress = 16;
                                elseif($englishProgress['level'] == 'A2') $levelProgress = 33;
                                elseif($englishProgress['level'] == 'B1') $levelProgress = 50;
                                elseif($englishProgress['level'] == 'B2') $levelProgress = 66;
                                elseif($englishProgress['level'] == 'C1') $levelProgress = 83;
                                elseif($englishProgress['level'] == 'C2') $levelProgress = 100;
                            @endphp
                            <div class="progress-bar bg-success" role="progressbar" style="width: {{ $levelProgress }}%" 
                                 aria-valuenow="{{ $levelProgress }}" aria-valuemin="0" aria-valuemax="100"></div>
                        </div>
                        <div class="d-flex justify-content-between text-muted small">
                            <span>A1</span>
                            <span>A2</span>
                            <span>B1</span>
                            <span>B2</span>
                            <span>C1</span>
                            <span>C2</span>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <h5>Puntuación por áreas</h5>
                        <div class="mb-2 d-flex align-items-center">
                            <span class="me-2" style="width: 100px;">Listening:</span>
                            <div class="progress flex-grow-1" style="height: 8px;">
                                <div class="progress-bar bg-primary" role="progressbar" style="width: {{ $englishProgress['listening_score'] }}%"></div>
                            </div>
                            <span class="ms-2">{{ $englishProgress['listening_score'] }}/100</span>
                        </div>
                        <div class="mb-2 d-flex align-items-center">
                            <span class="me-2" style="width: 100px;">Vocabulary:</span>
                            <div class="progress flex-grow-1" style="height: 8px;">
                                <div class="progress-bar bg-info" role="progressbar" style="width: {{ $englishProgress['vocabulary_score'] }}%"></div>
                            </div>
                            <span class="ms-2">{{ $englishProgress['vocabulary_score'] }}/100</span>
                        </div>
                        <div class="mb-2 d-flex align-items-center">
                            <span class="me-2" style="width: 100px;">Grammar:</span>
                            <div class="progress flex-grow-1" style="height: 8px;">
                                <div class="progress-bar bg-warning" role="progressbar" style="width: {{ $englishProgress['grammar_score'] }}%"></div>
                            </div>
                            <span class="ms-2">{{ $englishProgress['grammar_score'] }}/100</span>
                        </div>
                        <div class="mb-2 d-flex align-items-center">
                            <span class="me-2" style="width: 100px;">Speaking:</span>
                            <div class="progress flex-grow-1" style="height: 8px;">
                                <div class="progress-bar bg-danger" role="progressbar" style="width: {{ $englishProgress['speaking_score'] }}%"></div>
                            </div>
                            <span class="ms-2">{{ $englishProgress['speaking_score'] }}/100</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        @endif
    </div>
</div>
@endsection
