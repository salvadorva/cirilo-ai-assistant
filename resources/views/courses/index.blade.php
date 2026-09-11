@extends('layout.app')

@section('title', 'Mis Cursos Personalizados')

@section('content')
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />
<style>
    .card { border: none; box-shadow: 0 0.5rem 1.5rem 0.5rem rgba(0, 0, 0, 0.075); }
    .card-header { background-color: #fff; border-bottom: 1px solid #eff2f5; }
    .btn-primary { background-color: #009ef7; border-color: #009ef7; }
    .btn-primary:hover { background-color: #0095e8; border-color: #0095e8; }
    .btn-success { background-color: #50cd89; border-color: #50cd89; }
    .course-card { transition: transform 0.2s; cursor: pointer; }
    .course-card:hover { transform: translateY(-5px); }
    .progress-circle { width: 50px; height: 50px; border-radius: 50%; display: flex; align-items: center; 
                      justify-content: center; font-weight: bold; color: white; font-size: 0.9rem; }
    .level-badge { font-size: 1rem; padding: 0.4rem 0.8rem; }
    .create-course-card { border: 2px dashed #e4e6ef; background-color: #f8f9fa; }
    .create-course-card:hover { border-color: #009ef7; background-color: #f1faff; }
</style>

<!-- Breadcrumb -->
<div class="mb-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('tutor') }}">Centro de Aprendizaje</a></li>
            <li class="breadcrumb-item active" aria-current="page">Cursos Personalizados</li>
        </ol>
    </nav>
</div>

<!-- Mensajes de alerta -->
@if(session('success'))
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <i class="fas fa-check-circle me-2"></i> {{ session('success') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
@endif

@if(session('error'))
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <i class="fas fa-exclamation-circle me-2"></i> {{ session('error') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
@endif

<!-- Header -->
<div class="card mb-5">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h2 class="card-title fw-bold mb-0">
            <i class="fas fa-graduation-cap me-2"></i>Mis Cursos Personalizados
        </h2>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createCourseModal">
            <i class="fas fa-plus me-2"></i>Crear Nuevo Curso
        </button>
    </div>
    <div class="card-body">
        <p class="lead mb-0">Crea cursos personalizados sobre cualquier tema usando inteligencia artificial. Cada curso se adapta a tu nivel y objetivos de aprendizaje.</p>
    </div>
</div>

<!-- Lista de cursos -->
<div class="row">
    <!-- Tarjeta para crear nuevo curso -->
    <div class="col-lg-4 col-md-6 mb-4">
        <div class="card h-100 create-course-card course-card" data-bs-toggle="modal" data-bs-target="#createCourseModal">
            <div class="card-body text-center d-flex flex-column justify-content-center" style="min-height: 250px;">
                <i class="fas fa-plus-circle fa-4x text-primary mb-3"></i>
                <h4 class="text-primary">Crear Nuevo Curso</h4>
                <p class="text-muted">Genera un curso personalizado sobre cualquier tema con IA</p>
            </div>
        </div>
    </div>

    <!-- Cursos existentes -->
    @forelse($courses as $course)
        @php
            $completedSessions = $course->sessions->where('progress.is_completed', true)->count();
            $totalSessions = $course->sessions->count();
            $progressPercent = $totalSessions > 0 ? round(($completedSessions / $totalSessions) * 100) : 0;
        @endphp
        <div class="col-lg-4 col-md-6 mb-4">
            <div class="card h-100 course-card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div class="flex-grow-1">
                            <h5 class="card-title mb-2">{{ $course->title }}</h5>
                            <div class="d-flex align-items-center mb-2">
                                <span class="badge bg-primary level-badge me-2">{{ $course->level }}</span>
                                @if($course->created_by_ai)
                                    <span class="badge bg-info">
                                        <i class="fas fa-robot me-1"></i>IA
                                    </span>
                                @endif
                            </div>
                        </div>
                        <div class="progress-circle" style="background-color: {{ $progressPercent >= 100 ? '#50cd89' : ($progressPercent >= 50 ? '#ffc700' : '#009ef7') }};">
                            {{ $progressPercent }}%
                        </div>
                    </div>
                    
                    <p class="card-text text-muted mb-3">{{ Str::limit($course->description, 100) }}</p>
                    
                    <div class="row text-center mb-3">
                        <div class="col-4">
                            <small class="text-muted d-block">Sesiones</small>
                            <strong>{{ $totalSessions }}</strong>
                        </div>
                        <div class="col-4">
                            <small class="text-muted d-block">Completadas</small>
                            <strong class="text-success">{{ $completedSessions }}</strong>
                        </div>
                        <div class="col-4">
                            <small class="text-muted d-block">Progreso</small>
                            <strong class="text-primary">{{ $progressPercent }}%</strong>
                        </div>
                    </div>
                    
                    <div class="progress mb-3" style="height: 6px;">
                        <div class="progress-bar" role="progressbar" style="width: {{ $progressPercent }}%; background-color: {{ $progressPercent >= 100 ? '#50cd89' : ($progressPercent >= 50 ? '#ffc700' : '#009ef7') }};" 
                             aria-valuenow="{{ $progressPercent }}" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                    
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="d-flex gap-2">
                            <a href="{{ route('courses.show', $course->id) }}" class="btn btn-primary">
                                <i class="fas fa-play me-1"></i>Continuar
                            </a>
                            <button type="button" class="btn btn-outline-danger btn-sm" 
                                    onclick="deleteCourse({{ $course->id }}, '{{ addslashes($course->title) }}')" 
                                    title="Eliminar curso">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                        <small class="text-muted">
                            Creado {{ $course->created_at->diffForHumans() }}
                        </small>
                    </div>
                </div>
            </div>
        </div>
    @empty
        <div class="col-12">
            <div class="text-center py-5">
                <i class="fas fa-book fa-4x text-muted mb-4"></i>
                <h4 class="text-muted">No tienes cursos personalizados aún</h4>
                <p class="text-muted mb-4">Crea tu primer curso personalizado usando inteligencia artificial</p>
                <button class="btn btn-primary btn-lg" data-bs-toggle="modal" data-bs-target="#createCourseModal">
                    <i class="fas fa-plus me-2"></i>Crear Mi Primer Curso
                </button>
            </div>
        </div>
    @endforelse
</div>

<!-- Estadísticas generales -->
@if($courses->count() > 0)
<div class="card mt-5">
    <div class="card-header">
        <h4 class="card-title mb-0">
            <i class="fas fa-chart-bar me-2"></i>Estadísticas Generales
        </h4>
    </div>
    <div class="card-body">
        <div class="row text-center">
            <div class="col-md-3">
                <h3 class="text-primary">{{ $courses->count() }}</h3>
                <p class="text-muted mb-0">Cursos Creados</p>
            </div>
            <div class="col-md-3">
                @php
                    $totalSessions = $courses->sum(function($course) { return $course->sessions->count(); });
                @endphp
                <h3 class="text-info">{{ $totalSessions }}</h3>
                <p class="text-muted mb-0">Sesiones Totales</p>
            </div>
            <div class="col-md-3">
                @php
                    $completedSessions = $courses->sum(function($course) { 
                        return $course->sessions->where('progress.is_completed', true)->count(); 
                    });
                @endphp
                <h3 class="text-success">{{ $completedSessions }}</h3>
                <p class="text-muted mb-0">Sesiones Completadas</p>
            </div>
            <div class="col-md-3">
                @php
                    $overallProgress = $totalSessions > 0 ? round(($completedSessions / $totalSessions) * 100) : 0;
                @endphp
                <h3 class="text-warning">{{ $overallProgress }}%</h3>
                <p class="text-muted mb-0">Progreso General</p>
            </div>
        </div>
    </div>
</div>
@endif

<!-- Modal para crear curso -->
<div class="modal fade" id="createCourseModal" tabindex="-1" aria-labelledby="createCourseModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="createCourseModalLabel">
                    <i class="fas fa-magic me-2"></i>Crear Curso Personalizado con IA
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="createCourseForm">
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        Nuestra IA creará un curso completo y estructurado sobre el tema que elijas, adaptado a tu nivel de conocimiento.
                    </div>
                    
                    <div class="mb-3">
                        <label for="courseTopic" class="form-label">
                            <i class="fas fa-lightbulb me-1"></i>Tema del Curso *
                        </label>
                        <input type="text" class="form-control" id="courseTopic" name="topic" required
                               placeholder="Ej: Programación en Python, Cocina Italiana, Marketing Digital...">
                        <div class="form-text">Describe el tema sobre el que quieres aprender</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="courseLevel" class="form-label">
                            <i class="fas fa-signal me-1"></i>Nivel de Conocimiento *
                        </label>
                        <select class="form-select" id="courseLevel" name="level" required>
                            <option value="">Selecciona tu nivel</option>
                            <option value="Principiante">Principiante - No tengo conocimientos previos</option>
                            <option value="Intermedio">Intermedio - Tengo conocimientos básicos</option>
                            <option value="Avanzado">Avanzado - Tengo experiencia en el tema</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="courseSessions" class="form-label">
                            <i class="fas fa-list-ol me-1"></i>Número de Sesiones *
                        </label>
                        <select class="form-select" id="courseSessions" name="sessions" required>
                            <option value="">Selecciona la duración</option>
                            <option value="3">3 sesiones - Curso corto</option>
                            <option value="5">5 sesiones - Curso estándar</option>
                            <option value="8">8 sesiones - Curso completo</option>
                            <option value="10">10 sesiones - Curso extenso</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="courseDescription" class="form-label">
                            <i class="fas fa-align-left me-1"></i>Descripción Adicional (Opcional)
                        </label>
                        <textarea class="form-control" id="courseDescription" name="description" rows="3"
                                  placeholder="Agrega detalles específicos sobre lo que quieres aprender o enfoques particulares..."></textarea>
                        <div class="form-text">Información adicional para personalizar mejor tu curso</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary" id="createCourseBtn">
                        <i class="fas fa-magic me-2"></i>Crear Curso con IA
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.getElementById('createCourseForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const button = document.getElementById('createCourseBtn');
    const originalText = button.innerHTML;
    
    // Mostrar estado de carga
    button.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Creando curso...';
    button.disabled = true;
    
    try {
        const response = await fetch('{{ route("courses.create") }}', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            // Mostrar mensaje de éxito
            const alert = document.createElement('div');
            alert.className = 'alert alert-success alert-dismissible fade show';
            alert.innerHTML = `
                <i class="fas fa-check-circle me-2"></i>${data.message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            document.querySelector('.container-fluid').insertBefore(alert, document.querySelector('.card'));
            
            // Cerrar modal y recargar página
            const modal = bootstrap.Modal.getInstance(document.getElementById('createCourseModal'));
            modal.hide();
            
            // Redirigir al curso creado
            setTimeout(() => {
                window.location.href = data.redirect_url;
            }, 1500);
            
        } else {
            throw new Error(data.message || 'Error al crear el curso');
        }
        
    } catch (error) {
        console.error('Error:', error);
        alert('Error al crear el curso: ' + error.message);
    } finally {
        button.innerHTML = originalText;
        button.disabled = false;
    }
});

// Limpiar formulario cuando se cierra el modal
document.getElementById('createCourseModal').addEventListener('hidden.bs.modal', function() {
    document.getElementById('createCourseForm').reset();
});

// Función para eliminar curso
function deleteCourse(courseId, courseTitle) {
    Swal.fire({
        title: '¿Eliminar curso?',
        html: `¿Estás seguro de que deseas eliminar el curso <strong>"${courseTitle}"</strong>?<br><br><small class="text-danger">Esta acción eliminará todas las sesiones, progreso y recursos asociados. No se puede deshacer.</small>`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar',
        reverseButtons: true
    }).then((result) => {
        if (result.isConfirmed) {
            // Mostrar loading
            Swal.fire({
                title: 'Eliminando curso...',
                text: 'Por favor espera mientras eliminamos el curso y sus recursos.',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            // Realizar petición DELETE
            fetch(`/cursos/${courseId}`, {
                method: 'DELETE',
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
                        title: '¡Curso eliminado!',
                        text: data.message,
                        confirmButtonText: 'Entendido'
                    }).then(() => {
                        // Recargar la página para actualizar la lista
                        location.reload();
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error al eliminar',
                        text: data.message || 'No se pudo eliminar el curso'
                    });
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Error de conexión',
                    text: 'No se pudo conectar con el servidor para eliminar el curso'
                });
            });
        }
    });
}
</script>
@endpush
@endsection
