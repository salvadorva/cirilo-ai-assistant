@extends('layout.app')

@section('content')
<div class="container">
    <div class="row">
        <div class="col-md-12">
            <h1>{{ $session->title }}</h1>
            
            <!-- Contenido de la sesión -->
            <div class="content-section">
                <h3 class="mb-3">
                    <i class="fas fa-book-open me-2 text-primary"></i>Contenido de la Sesión
                </h3>
                
                <!-- Sección de imagen -->
                @if(isset($session->image_description) && !empty($session->image_description))
                    <div class="card mb-4">
                        <div class="card-body">
                            <h5 class="card-title">
                                <i class="fas fa-image me-2 text-info"></i>Material Visual de Apoyo
                            </h5>
                            
                            <div class="alert alert-light border">
                                <div class="d-flex align-items-start">
                                    <i class="fas fa-camera fa-2x text-muted me-3 mt-1"></i>
                                    <div class="flex-grow-1">
                                        <h6 class="fw-bold mb-2">
                                            Descripción de la imagen educativa:
                                        </h6>
                                        <p class="mb-2">{{ $session->image_description }}</p>
                                        
                                        <div class="d-flex justify-content-between align-items-center">
                                            <small class="text-muted">
                                                <i class="fas fa-clock text-warning me-1"></i>
                                                <span id="image-status-{{ $session->id }}">
                                                    Imagen pendiente de generar
                                                </span>
                                            </small>
                                            
                                            <button type="button" class="btn btn-sm btn-outline-primary" 
                                                    onclick="generateSessionResources({{ $course->id }}, {{ $session->id }}, 'image')" 
                                                    id="generate-image-btn-{{ $session->id }}">
                                                <i class="fas fa-magic me-1"></i>Generar Imagen
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif
                
                <!-- Contenido principal -->
                <div class="session-content mb-4">
                    {!! nl2br(e($session->content)) !!}
                </div>
                
                <!-- Sección de audio -->
                @if(isset($session->audio_script) && !empty($session->audio_script))
                    <div class="audio-player card mb-4">
                        <div class="card-body">
                            <h5 class="card-title">
                                <i class="fas fa-volume-up me-2 text-primary"></i>Audio de la Sesión
                            </h5>
                            <p class="mb-3">{{ $session->audio_script }}</p>
                            
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <button class="btn btn-outline-primary btn-sm me-2" onclick="playAudioScript()">
                                        <i class="fas fa-play me-1"></i>Reproducir Audio
                                    </button>
                                    
                                    <small class="text-muted">
                                        <i class="fas fa-clock text-warning me-1"></i>
                                        <span id="audio-status-{{ $session->id }}">
                                            Audio pendiente de generar
                                        </span>
                                    </small>
                                </div>
                                
                                <button type="button" class="btn btn-sm btn-outline-success" 
                                        onclick="generateSessionResources({{ $course->id }}, {{ $session->id }}, 'audio')" 
                                        id="generate-audio-btn-{{ $session->id }}">
                                    <i class="fas fa-microphone me-1"></i>Generar Audio
                                </button>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
            
            <!-- Actividad práctica -->
            @if(isset($session->practice_activity) && !empty($session->practice_activity))
                <div class="activity-section">
                    <h3 class="mb-4">
                        <i class="fas fa-tasks me-2 text-success"></i>Actividad Práctica
                    </h3>
                    
                    @php
                        $activity = is_string($session->practice_activity) 
                            ? json_decode($session->practice_activity, true) 
                            : $session->practice_activity;
                    @endphp
                    
                    @if($activity)
                        <div class="card">
                            <div class="card-body">
                                @if(isset($activity['title']))
                                    <h4 class="card-title text-primary">{{ $activity['title'] }}</h4>
                                @endif
                                
                                @if(isset($activity['instructions']))
                                    <div class="alert alert-info">
                                        {{ $activity['instructions'] }}
                                    </div>
                                @endif
                                
                                @if(isset($activity['options']) && is_array($activity['options']))
                                    <div class="options-container mt-4">
                                        @foreach($activity['options'] as $index => $option)
                                            <div class="form-check mb-2">
                                                <input class="form-check-input" type="radio" name="quizOption" 
                                                       id="option{{ $index }}" value="{{ $index }}">
                                                <label class="form-check-label" for="option{{ $index }}">
                                                    {{ $option }}
                                                </label>
                                            </div>
                                        @endforeach
                                    </div>
                                    
                                    <div class="mt-3">
                                        <button class="btn btn-primary" onclick="submitQuizAnswer()">
                                            <i class="fas fa-check-circle me-1"></i>Enviar Respuesta
                                        </button>
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endif
                </div>
            @endif
        </div>
    </div>
</div>

<script>
// Función para generar recursos de forma asíncrona
function generateSessionResources(courseId, sessionId, resourceType) {
    const resourceName = resourceType === 'image' ? 'imagen' : 'audio';
    const buttonId = `generate-${resourceType}-btn-${sessionId}`;
    const statusId = `${resourceType}-status-${sessionId}`;
    const button = document.getElementById(buttonId);
    const statusElement = document.getElementById(statusId);
    
    // Mostrar estado de carga
    button.disabled = true;
    button.innerHTML = `<i class="fas fa-spinner fa-spin me-1"></i>Generando ${resourceName}...`;
    statusElement.innerHTML = `Generando ${resourceName}, por favor espera...`;
    
    // Realizar petición para generar recursos
    fetch(`{{ url('/') }}/cursos/${courseId}/sesion/${sessionId}/generar-recursos`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        },
        body: JSON.stringify({
            resource_type: resourceType
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Éxito - actualizar interfaz
            statusElement.innerHTML = `${resourceName.charAt(0).toUpperCase() + resourceName.slice(1)} generada exitosamente`;
            statusElement.className = 'text-success';
            
            // Ocultar botón y mostrar mensaje de éxito
            button.style.display = 'none';
            
            // Recargar página para mostrar el recurso generado
            alert(`¡${resourceName.charAt(0).toUpperCase() + resourceName.slice(1)} generada exitosamente! La página se recargará.`);
            location.reload();
        } else {
            // Error - restaurar estado
            button.disabled = false;
            button.innerHTML = `<i class="fas fa-${resourceType === 'image' ? 'magic' : 'microphone'} me-1"></i>Generar ${resourceName.charAt(0).toUpperCase() + resourceName.slice(1)}`;
            statusElement.innerHTML = `Error al generar ${resourceName}`;
            
            alert('Error: ' + (data.message || `Error al generar ${resourceName}`));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        
        // Error de conexión - restaurar estado
        button.disabled = false;
        button.innerHTML = `<i class="fas fa-${resourceType === 'image' ? 'magic' : 'microphone'} me-1"></i>Generar ${resourceName.charAt(0).toUpperCase() + resourceName.slice(1)}`;
        statusElement.innerHTML = `Error de conexión al generar ${resourceName}`;
        
        alert('Error de conexión. Por favor, intenta de nuevo.');
    });
}

function playAudioScript() {
    alert('Función para reproducir audio no implementada en esta versión de prueba.');
}

function submitQuizAnswer() {
    alert('Función para enviar respuesta no implementada en esta versión de prueba.');
}
</script>
@endsection
