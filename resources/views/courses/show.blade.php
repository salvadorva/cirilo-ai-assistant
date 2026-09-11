@extends('layout.app')

@section('title', $course->title)

@section('content')
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />
<style>
    .card { border: none; box-shadow: 0 0.5rem 1.5rem 0.5rem rgba(0, 0, 0, 0.075); }
    .card-header { background-color: #fff; border-bottom: 1px solid #eff2f5; }
    .btn-primary { background-color: #009ef7; border-color: #009ef7; }
    .btn-primary:hover { background-color: #0095e8; border-color: #0095e8; }
    .btn-success { background-color: #50cd89; border-color: #50cd89; }
    .session-card { transition: transform 0.2s; cursor: pointer; }
    .session-card:hover { transform: translateY(-2px); }
    .session-completed { background-color: #f8f9fa; border-left: 4px solid #50cd89; }
    .session-current { border-left: 4px solid #009ef7; }
    .progress-circle { width: 60px; height: 60px; border-radius: 50%; display: flex; align-items: center; 
                      justify-content: center; font-weight: bold; color: white; }
    .level-badge { font-size: 1.2rem; padding: 0.5rem 1rem; }
</style>

<!-- Breadcrumb -->
<div class="mb-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('tutor') }}">Centro de Aprendizaje</a></li>
            <li class="breadcrumb-item"><a href="{{ route('courses.index') }}">Cursos Personalizados</a></li>
            <li class="breadcrumb-item active" aria-current="page">{{ $course->title }}</li>
        </ol>
    </nav>
</div>

<!-- Información del curso -->
<div class="card mb-5">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h2 class="card-title fw-bold mb-0">
            <i class="fas fa-book me-2"></i>{{ $course->title }}
        </h2>
        <div class="d-flex align-items-center">
            <span class="badge bg-primary level-badge me-3">{{ $course->level }}</span>
            @if($course->created_by_ai)
                <span class="badge bg-info">
                    <i class="fas fa-robot me-1"></i>Generado por IA
                </span>
            @endif
        </div>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-8">
                <p class="lead mb-3">{{ $course->description }}</p>
                <div class="row">
                    <div class="col-md-4">
                        <div class="d-flex align-items-center mb-2">
                            <i class="fas fa-list-ol text-primary me-2"></i>
                            <span><strong>{{ $course->sessions_count }}</strong> sesiones</span>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="d-flex align-items-center mb-2">
                            <i class="fas fa-signal text-success me-2"></i>
                            <span>Nivel <strong>{{ $course->level }}</strong></span>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="d-flex align-items-center mb-2">
                            <i class="fas fa-calendar text-info me-2"></i>
                            <span>Creado {{ $course->created_at->diffForHumans() }}</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4 text-center">
                @php
                    $completedSessions = $course->sessions->filter(function($session) {
                        return $session->progress->first() && $session->progress->first()->completed;
                    })->count();
                    $totalSessions = $course->sessions->count();
                    $progressPercent = $totalSessions > 0 ? round(($completedSessions / $totalSessions) * 100) : 0;
                @endphp
                <div class="progress-circle mx-auto mb-3" style="background-color: {{ $progressPercent >= 100 ? '#50cd89' : ($progressPercent >= 50 ? '#ffc700' : '#009ef7') }};">
                    {{ $progressPercent }}%
                </div>
                <h5 class="mb-1">Progreso del Curso</h5>
                <p class="text-muted">{{ $completedSessions }} de {{ $totalSessions }} sesiones completadas</p>
                
                <!-- Botones de gestión de recursos -->
                <div class="mt-3">
                    <button type="button" class="btn btn-outline-primary btn-sm me-2" onclick="validateCourseCompleteness()">
                        <i class="fas fa-check-circle me-1"></i>Validar Curso
                    </button>
                    <button type="button" class="btn btn-outline-success btn-sm" onclick="completeResources()">
                        <i class="fas fa-magic me-1"></i>Completar Recursos
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Lista de sesiones -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title mb-0">
            <i class="fas fa-play-circle me-2"></i>Sesiones del Curso
        </h3>
    </div>
    <div class="card-body">
        @if($course->sessions->count() > 0)
            <div class="row">
                @foreach($course->sessions->sortBy('order') as $session)
                    @php
                        $isCompleted = $session->progress->first() && $session->progress->first()->is_completed;
                        $score = $session->progress->first() ? $session->progress->first()->score : 0;
                    @endphp
                    <div class="col-md-6 mb-4">
                        <div class="card session-card h-100 {{ $isCompleted ? 'session-completed' : 'session-current' }}">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <h5 class="card-title mb-0">
                                        <span class="badge bg-secondary me-2">{{ $session->order }}</span>
                                        {{ $session->title }}
                                    </h5>
                                    @if($isCompleted)
                                        <div class="text-success">
                                            <i class="fas fa-check-circle fa-lg"></i>
                                        </div>
                                    @endif
                                </div>
                                
                                <p class="card-text text-muted mb-3">{{ Str::limit($session->content, 100) }}</p>
                                
                                @if($isCompleted)
                                    <div class="mb-3">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <small class="text-muted">Puntuación obtenida:</small>
                                            <strong class="text-success">{{ $score }}/100</strong>
                                        </div>
                                        <div class="progress" style="height: 6px;">
                                            <div class="progress-bar bg-success" role="progressbar" 
                                                 style="width: {{ $score }}%" aria-valuenow="{{ $score }}" 
                                                 aria-valuemin="0" aria-valuemax="100"></div>
                                        </div>
                                    </div>
                                @endif
                                
                                <div class="d-flex justify-content-between align-items-center">
                                    <a href="{{ route('courses.session', [$course->id, $session->id]) }}" 
                                       class="btn {{ $isCompleted ? 'btn-outline-primary' : 'btn-primary' }}">
                                        <i class="fas fa-{{ $isCompleted ? 'eye' : 'play' }} me-1"></i>
                                        {{ $isCompleted ? 'Revisar' : 'Comenzar' }}
                                    </a>
                                    
                                    @if($isCompleted && $session->progress->completed_at)
                                        <small class="text-muted">
                                            Completado {{ $session->progress->completed_at->diffForHumans() }}
                                        </small>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="text-center py-5">
                <i class="fas fa-exclamation-triangle fa-3x text-warning mb-3"></i>
                <h4>No hay sesiones disponibles</h4>
                <p class="text-muted">Este curso aún no tiene sesiones configuradas.</p>
            </div>
        @endif
    </div>
</div>

<!-- Estadísticas del curso -->
@if($course->sessions->count() > 0)
    <div class="card mt-4">
        <div class="card-header">
            <h4 class="card-title mb-0">
                <i class="fas fa-chart-bar me-2"></i>Estadísticas de Progreso
            </h4>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-3 text-center">
                    <h3 class="text-primary">{{ $completedSessions }}</h3>
                    <p class="text-muted mb-0">Sesiones Completadas</p>
                </div>
                <div class="col-md-3 text-center">
                    <h3 class="text-info">{{ $totalSessions - $completedSessions }}</h3>
                    <p class="text-muted mb-0">Sesiones Pendientes</p>
                </div>
                <div class="col-md-3 text-center">
                    @php
                        $avgScore = $course->sessions->filter(function($session) {
                            return $session->progress->first() && $session->progress->first()->is_completed;
                        })->map(function($session) {
                            return $session->progress->first()->score;
                        })->avg() ?: 0;
                    @endphp
                    <h3 class="text-success">{{ round($avgScore) }}</h3>
                    <p class="text-muted mb-0">Puntuación Promedio</p>
                </div>
                <div class="col-md-3 text-center">
                    <h3 class="text-warning">{{ $progressPercent }}%</h3>
                    <p class="text-muted mb-0">Progreso Total</p>
                </div>
            </div>
        </div>
    </div>
@endif
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
function validateCourseCompleteness() {
    const courseId = {{ $course->id }};
    
    Swal.fire({
        title: 'Validando curso...',
        text: 'Verificando completitud de recursos',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    fetch(`/cursos/${courseId}/validar-completitud`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const validation = data.validation;
            let html = '<div class="text-start">';
            
            // Estado general
            html += `<div class="alert alert-${validation.is_complete ? 'success' : 'warning'} mb-3">
                        <h6><i class="fas fa-${validation.is_complete ? 'check-circle' : 'exclamation-triangle'} me-2"></i>
                        Estado: ${validation.is_complete ? 'Completo' : 'Incompleto'}</h6>
                     </div>`;
            
            // Detalles por sesión
            html += '<h6>Detalles por sesión:</h6>';
            validation.sessions.forEach((session, index) => {
                const sessionNum = index + 1;
                html += `<div class="mb-2">
                            <strong>Sesión ${sessionNum}:</strong>
                            <span class="badge bg-${session.is_complete ? 'success' : 'warning'} ms-2">
                                ${session.is_complete ? 'Completa' : 'Incompleta'}
                            </span>
                         </div>`;
                
                if (!session.is_complete) {
                    html += '<ul class="small text-muted mb-2">';
                    session.issues.forEach(issue => {
                        html += `<li>${issue}</li>`;
                    });
                    html += '</ul>';
                }
            });
            
            html += '</div>';
            
            Swal.fire({
                title: 'Validación de Curso',
                html: html,
                icon: validation.is_complete ? 'success' : 'warning',
                confirmButtonText: 'Entendido'
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: data.message || 'Error al validar el curso'
            });
        }
    })
    .catch(error => {
        console.error('Error:', error);
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Error de conexión al validar el curso'
        });
    });
}

function completeResources() {
    const courseId = {{ $course->id }};
    
    Swal.fire({
        title: '¿Completar recursos faltantes?',
        text: 'Esto generará imágenes y audios para las sesiones que no los tengan.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Sí, completar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#50cd89'
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                title: 'Completando recursos...',
                text: 'Generando imágenes y audios faltantes',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            fetch(`/cursos/${courseId}/completar-recursos`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: '¡Recursos completados!',
                        text: data.message,
                        confirmButtonText: 'Recargar página'
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: data.message || 'Error al completar recursos'
                    });
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Error de conexión al completar recursos'
                });
            });
        }
    });
}
</script>
@endpush
