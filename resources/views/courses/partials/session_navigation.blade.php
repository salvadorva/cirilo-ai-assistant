{{-- Navegación entre sesiones --}}
<div class="card session-navigation mb-4">
    <div class="card-body">
        <h5 class="card-title">
            <i class="fas fa-map-signs me-2 text-primary"></i>Navegación
        </h5>
        
        <div class="d-flex justify-content-between mt-3">
            @if($previousSession)
                <a href="{{ route('courses.session', ['course' => $course->id, 'session' => $previousSession->id]) }}" 
                   class="btn btn-light btn-sm">
                    <i class="fas fa-arrow-left me-1"></i>Sesión anterior
                </a>
            @else
                <button class="btn btn-light btn-sm" disabled>
                    <i class="fas fa-arrow-left me-1"></i>Sesión anterior
                </button>
            @endif
            
            <a href="{{ route('courses.show', $course->id) }}" class="btn btn-outline-primary btn-sm">
                <i class="fas fa-th-list me-1"></i>Índice del curso
            </a>
            
            @if($nextSession)
                <a href="{{ route('courses.session', ['course' => $course->id, 'session' => $nextSession->id]) }}" 
                   class="btn btn-primary btn-sm">
                    Siguiente sesión<i class="fas fa-arrow-right ms-1"></i>
                </a>
            @else
                <button class="btn btn-primary btn-sm" disabled>
                    Siguiente sesión<i class="fas fa-arrow-right ms-1"></i>
                </button>
            @endif
        </div>
    </div>
</div>
