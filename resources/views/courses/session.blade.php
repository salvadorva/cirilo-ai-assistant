@extends('layout.app')

@section('title', $session->title . ' - ' . $course->title)

@section('content')
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />
<style>
    .card { border: none; box-shadow: 0 0.5rem 1.5rem 0.5rem rgba(0, 0, 0, 0.075); }
    .card-header { background-color: #fff; border-bottom: 1px solid #eff2f5; }
    .btn-primary { background-color: #009ef7; border-color: #009ef7; }
    .btn-primary:hover { background-color: #0095e8; border-color: #0095e8; }
    .btn-success { background-color: #50cd89; border-color: #50cd89; }
    .content-section { background-color: #f8f9fa; border-radius: 8px; padding: 2rem; margin-bottom: 2rem; }
    .activity-section { background-color: #fff; border: 2px solid #e4e6ef; border-radius: 8px; padding: 2rem; }
    .session-navigation { position: sticky; top: 20px; }
    .audio-player { background-color: #f1faff; border-radius: 8px; padding: 1rem; margin: 1rem 0; }
    .completion-badge { font-size: 1.1rem; padding: 0.5rem 1rem; }
</style>

@push('styles')
<style>
.option-card {
    transition: all 0.3s ease;
    border: 2px solid #e9ecef;
}

.option-card:hover {
    border-color: #007bff;
    box-shadow: 0 4px 8px rgba(0,123,255,0.15);
    transform: translateY(-2px);
}

.option-card.selected {
    border-color: #007bff !important;
    background-color: #f8f9fa !important;
    box-shadow: 0 4px 12px rgba(0,123,255,0.2);
}

.form-check-input:checked {
    background-color: #007bff;
    border-color: #007bff;
}

.activity-content .card {
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.progress {
    border-radius: 10px;
    overflow: hidden;
}

.progress-bar {
    transition: width 0.6s ease;
}

#evaluationResults {
    animation: fadeInUp 0.5s ease-out;
}

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
</style>
@endpush

<!-- Breadcrumb -->
<div class="mb-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('tutor') }}">Centro de Aprendizaje</a></li>
            <li class="breadcrumb-item"><a href="{{ route('courses.index') }}">Cursos Personalizados</a></li>
            <li class="breadcrumb-item"><a href="{{ route('courses.show', $course->id) }}">{{ $course->title }}</a></li>
            <li class="breadcrumb-item active" aria-current="page">{{ $session->title }}</li>
        </ol>
    </nav>
</div>

<div class="row">
    <!-- Contenido principal -->
    <div class="col-lg-8">
        <!-- Información de la sesión -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <div>
                    <h2 class="card-title fw-bold mb-1">
                        <span class="badge bg-secondary me-2">Sesión {{ $session->order }}</span>
                        {{ $session->title }}
                    </h2>
                    <p class="text-muted mb-0">{{ $course->title }} - Nivel {{ $course->level }}</p>
                </div>
                @if($progress && $progress->first() && $progress->first()->is_completed)
                    <span class="badge bg-success completion-badge">
                        <i class="fas fa-check-circle me-1"></i>Completada
                    </span>
                @endif
            </div>
        </div>

        <!-- Contenido de la sesión -->
        <div class="content-section">
            <h3 class="mb-3">
                <i class="fas fa-book-open me-2 text-primary"></i>Contenido de la Sesión
            </h3>
            
            @if(isset($imageDescription) && !empty($imageDescription))
                <div class="card mb-4">
                    <div class="card-body">
                        <h5 class="card-title">
                            <i class="fas fa-image me-2 text-info"></i>Material Visual de Apoyo
                        </h5>
                        
                        @php
                            $imagePath = "courses/{$userId}/{$course->id}/img/session_{$session->id}.png";
                            $imageUrl = $hasImage ? Storage::disk('public')->url($imagePath) : null;
                        @endphp

                        @if($hasImage)
                            <div class="text-center mb-3">
                                <img src="{{ $imageUrl }}" alt="Material educativo para {{ $session->title }}" 
                                     class="img-fluid rounded shadow" style="max-height: 400px;">
                            </div>
                        @endif

                        <div class="alert alert-light border">
                            <div class="d-flex align-items-start">
                                <i class="fas fa-camera fa-2x text-muted me-3 mt-1"></i>
                                <div class="flex-grow-1">
                                    <h6 class="fw-bold mb-2">
                                        Descripción de la imagen educativa:
                                    </h6>
                                    <p class="mb-2">{{ $imageDescription }}</p>
                                    
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

            <div class="session-content">
                @php
                    // Procesar Markdown básico
                    $content = $session->content;
                    
                    // Procesar encabezados
                    $content = preg_replace('/^### (.+)$/m', '<h4 class="mt-4 mb-3 fw-bold">$1</h4>', $content);
                    $content = preg_replace('/^#### (.+)$/m', '<h5 class="mt-3 mb-2 fw-bold">$1</h5>', $content);
                    
                    // Procesar negritas
                    $content = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $content);
                    
                    // Procesar código en línea
                    $content = preg_replace('/`(.+?)`/s', '<code class="bg-light px-1 rounded">$1</code>', $content);
                    
                    // Procesar bloques de código
                    $content = preg_replace('/```(.+?)```/s', '<pre class="bg-light p-3 rounded"><code>$1</code></pre>', $content);
                    
                    // Procesar listas no ordenadas - mejorado para múltiples elementos
                    $content = preg_replace('/^\* (.+)$/m', '<li>$1</li>', $content);
                    $content = preg_replace('/^- (.+)$/m', '<li>$1</li>', $content);
                    
                    // Envolver listas en etiquetas ul
                    $content = preg_replace('/((?:<li>.+?<\/li>\n?)+)/s', '<ul class="mb-3">$1</ul>', $content);
                    
                    // Procesar listas ordenadas - mejorado para múltiples elementos
                    $content = preg_replace('/^\d+\. (.+)$/m', '<li>$1</li>', $content);
                    
                    // Envolver listas ordenadas en etiquetas ol
                    $content = preg_replace('/((?:<li>.+?<\/li>\n?)+)/s', '<ol class="mb-3">$1</ol>', $content);
                    
                    // Procesar párrafos (líneas que no son parte de otros elementos)
                    $lines = explode("\n", $content);
                    $processedLines = [];
                    
                    foreach ($lines as $line) {
                        $trimmedLine = trim($line);
                        if (!empty($trimmedLine) && 
                            !preg_match('/^<(\/?(h[1-6]|ul|ol|li|pre|code))/i', $trimmedLine)) {
                            $processedLines[] = '<p class="mb-3">' . $trimmedLine . '</p>';
                        } else {
                            $processedLines[] = $line;
                        }
                    }
                    
                    $content = implode("\n", $processedLines);
                    
                    // Limpiar múltiples etiquetas de párrafo
                    $content = str_replace('<p class="mb-3"><ul', '<ul', $content);
                    $content = str_replace('<p class="mb-3"><ol', '<ol', $content);
                    $content = str_replace('<p class="mb-3"><h4', '<h4', $content);
                    $content = str_replace('<p class="mb-3"><h5', '<h5', $content);
                    $content = str_replace('<p class="mb-3"><pre', '<pre', $content);
                    $content = str_replace('</ul></p>', '</ul>', $content);
                    $content = str_replace('</ol></p>', '</ol>', $content);
                    $content = str_replace('</h4></p>', '</h4>', $content);
                    $content = str_replace('</h5></p>', '</h5>', $content);
                    $content = str_replace('</pre></p>', '</pre>', $content);
                    
                    // Convertir saltos de línea
                    $content = str_replace("\n", "<br>", $content);
                @endphp
                
                {!! $content !!}
            </div>

            @if(isset($audioScript) && !empty($audioScript))
                @php
                    $audioPath = "courses/{$userId}/{$course->id}/audio/session_{$session->id}.mp3";
                @endphp
                
                <div class="audio-player card mb-4">
                    <div class="card-body">
                        <h5 class="card-title">
                            <i class="fas fa-volume-up me-2 text-primary"></i>Audio de la Sesión
                        </h5>
                        <p class="mb-3">{{ $audioScript }}</p>
                        
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
        @if($session->practice_activity)
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
                    <div class="activity-content">
                        @if(isset($activity['title']))
                            <div class="mb-4">
                                <h4 class="text-primary">
                                    <i class="fas fa-clipboard-check me-2"></i>{{ $activity['title'] }}
                                </h4>
                            </div>
                        @endif

                        @if(isset($activity['type']))
                            <div class="mb-3">
                                <span class="badge bg-info fs-6">{{ ucfirst($activity['type']) }}</span>
                            </div>
                        @endif

                        @if(isset($activity['instructions']))
                            <div class="alert alert-primary mb-4">
                                <h5 class="alert-heading">
                                    <i class="fas fa-info-circle me-2"></i>Instrucciones
                                </h5>
                                <p class="mb-0">{!! nl2br(e($activity['instructions'])) !!}</p>
                            </div>
                        @endif

                        @if(isset($activity['question']))
                            <div class="mb-4">
                                <h5 class="fw-bold">Pregunta:</h5>
                                <div class="p-3 bg-light rounded">
                                    <p class="mb-0 fs-5">{!! nl2br(e($activity['question'])) !!}</p>
                                </div>
                            </div>
                        @endif

                        @if(isset($activity['options']) && is_array($activity['options']))
                            <div class="mb-4">
                                <h5 class="fw-bold">Opciones de Respuesta:</h5>
                                <div class="row">
                                    @foreach($activity['options'] as $index => $option)
                                        <div class="col-md-6 mb-2">
                                            <div class="card option-card h-100" style="cursor: pointer;" onclick="selectOption({{ $index }})">
                                                <div class="card-body d-flex align-items-center">
                                                    <div class="form-check me-3">
                                                        <input class="form-check-input" type="radio" name="quiz_option" id="option{{ $index }}" value="{{ $index }}">
                                                    </div>
                                                    <label class="form-check-label flex-grow-1" for="option{{ $index }}">
                                                        <strong>{{ chr(65 + $index) }}.</strong> {{ $option }}
                                                    </label>
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>

                            <div class="text-center mb-4">
                                <button class="btn btn-success btn-lg" onclick="submitQuizAnswer()">
                                    <i class="fas fa-check me-2"></i>Enviar Respuesta
                                </button>
                            </div>

                            <!-- Resultados de evaluación -->
                            <div id="evaluationResults" class="mt-4" style="display: none;">
                                <div class="card border-success">
                                    <div class="card-header bg-success text-white">
                                        <h5 class="mb-0">
                                            <i class="fas fa-star me-2"></i>Resultados de la Evaluación
                                        </h5>
                                    </div>
                                    <div class="card-body">
                                        <div id="scoreDisplay" class="text-center mb-4">
                                            <h2 class="mb-2"><span id="scoreValue">0</span>/100</h2>
                                            <div class="progress mb-2" style="height: 15px;">
                                                <div id="scoreBar" class="progress-bar" role="progressbar" style="width: 0%"></div>
                                            </div>
                                            <p class="text-muted mb-0">Tu puntuación en esta actividad</p>
                                        </div>
                                        
                                        <div id="feedbackContent" class="mb-4">
                                            <!-- El feedback se cargará aquí -->
                                        </div>

                                        <div class="text-center">
                                            <button class="btn btn-primary btn-lg" onclick="completeSession()">
                                                <i class="fas fa-check-circle me-2"></i>Completar Sesión
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endif

                        @if(isset($activity['exercise']))
                            <div class="mb-4">
                                <h5>Ejercicio:</h5>
                                <p>{{ $activity['exercise'] }}</p>
                            </div>
                        @endif
                    </div>
                @else
                    <p class="text-muted">No hay actividad práctica disponible para esta sesión.</p>
                @endif
            </div>
        @endif

        <!-- Botón para completar sin actividad -->
        @if(!$session->practice_activity && (!$progress || !$progress->first() || !$progress->first()->is_completed))
            <div class="text-center mt-4">
                <button class="btn btn-success btn-lg" onclick="completeSession()">
                    <i class="fas fa-check-circle me-2"></i>Marcar como Completada
                </button>
            </div>
        @endif
    </div>

    <!-- Navegación lateral -->
    <div class="col-lg-4">
        <div class="session-navigation">
            <!-- Progreso de la sesión -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-chart-line me-2"></i>Progreso
                    </h5>
                </div>
                <div class="card-body">
                    @if($progress && $progress->first() && $progress->first()->is_completed)
                        <div class="text-center">
                            <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                            <h5 class="text-success">¡Sesión Completada!</h5>
                            <p class="mb-2">Puntuación: <strong>{{ $progress->score }}/100</strong></p>
                            <p class="text-muted small">
                                Completada {{ $progress->completed_at->diffForHumans() }}
                            </p>
                        </div>
                    @else
                        <div class="text-center">
                            <i class="fas fa-play-circle fa-3x text-primary mb-3"></i>
                            <h5 class="text-primary">En Progreso</h5>
                            <p class="text-muted">Completa la actividad para continuar</p>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Navegación entre sesiones -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-list me-2"></i>Otras Sesiones
                    </h5>
                </div>
                <div class="card-body">
                    @foreach($course->sessions->sortBy('order') as $otherSession)
                        @php
                            $isCurrentSession = $otherSession->id == $session->id;
                            $isCompleted = $otherSession->progress && $otherSession->progress->first() && $otherSession->progress->first()->is_completed;
                        @endphp
                        <div class="d-flex align-items-center mb-2 {{ $isCurrentSession ? 'bg-light p-2 rounded' : '' }}">
                            <span class="badge bg-{{ $isCurrentSession ? 'primary' : 'secondary' }} me-2">
                                {{ $otherSession->order }}
                            </span>
                            <div class="flex-grow-1">
                                @if($isCurrentSession)
                                    <strong>{{ $otherSession->title }}</strong>
                                    <small class="text-muted d-block">Sesión actual</small>
                                @else
                                    <a href="{{ route('courses.session', [$course->id, $otherSession->id]) }}" 
                                       class="text-decoration-none">
                                        {{ $otherSession->title }}
                                    </a>
                                @endif
                            </div>
                            @if($isCompleted)
                                <i class="fas fa-check-circle text-success"></i>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
let currentScore = 0;
let selectedOption = null;

/**
 * Valida la estructura de una actividad práctica
 * @param {Object} activity - La actividad a validar
 * @return {Object} - Objeto con la validación y mensajes de error
 */
function validateActivity(activity) {
    const result = {
        isValid: false,
        errors: [],
        activity: null
    };
    
    // Verificar que sea un objeto
    if (!activity || typeof activity !== 'object') {
        result.errors.push('La actividad no es un objeto válido');
        return result;
    }
    
    // Verificar tipo de actividad
    if (!activity.type) {
        result.errors.push('La actividad no tiene un tipo definido');
    }
    
    // Verificar pregunta
    if (!activity.question) {
        result.errors.push('La actividad no tiene una pregunta definida');
    }
    
    // Verificar opciones
    if (!activity.options || !Array.isArray(activity.options) || activity.options.length === 0) {
        result.errors.push('La actividad no tiene opciones válidas');
    }
    
    // Verificar respuesta correcta
    if (typeof activity.correct_answer === 'undefined' || 
        activity.correct_answer === null ||
        (activity.options && (activity.correct_answer < 0 || activity.correct_answer >= activity.options.length))) {
        result.errors.push('La actividad no tiene una respuesta correcta válida');
    }
    
    // Si hay errores, no es válida
    if (result.errors.length > 0) {
        console.error('Errores en la actividad:', result.errors);
        return result;
    }
    
    // Si llegamos aquí, la actividad es válida
    result.isValid = true;
    result.activity = activity;
    return result;
}

function playAudioScript() {
    const userId = {{ Auth::id() }};
    const courseId = {{ $course->id }};
    const sessionId = {{ $session->id }};
    const audioUrl = `/storage/courses/${userId}/${courseId}/audio/session_${sessionId}.mp3`;
    
    // Intentar reproducir archivo de audio generado primero
    const audio = new Audio(audioUrl);
    
    audio.onloadeddata = function() {
        console.log('Reproduciendo audio generado');
        audio.play().catch(error => {
            console.log('Error reproduciendo audio generado, usando síntesis de voz:', error);
            playWithSpeechSynthesis();
        });
    };
    
    audio.onerror = function() {
        console.log('Archivo de audio no encontrado, usando síntesis de voz');
        playWithSpeechSynthesis();
    };
    
    // Función fallback para síntesis de voz
    function playWithSpeechSynthesis() {
        const text = @json($session->audio_script ?? '');
        if (!text) {
            Swal.fire({
                icon: 'info',
                title: 'Sin audio',
                text: 'No hay script de audio disponible para esta sesión.'
            });
            return;
        }

        if ('speechSynthesis' in window) {
            const utterance = new SpeechSynthesisUtterance(text);
            utterance.lang = 'es-ES';
            utterance.rate = 0.9;
            utterance.pitch = 1;
            speechSynthesis.speak(utterance);
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Audio no disponible',
                text: 'Tu navegador no soporta síntesis de voz.'
            });
        }
    }
}

function selectOption(index) {
    // Limpiar selección anterior
    document.querySelectorAll('.option-card').forEach(card => {
        card.classList.remove('selected');
    });
    
    // Seleccionar nueva opción
    const selectedCard = document.querySelector(`#option${index}`).closest('.option-card');
    selectedCard.classList.add('selected');
    
    // Marcar radio button
    document.querySelector(`#option${index}`).checked = true;
    selectedOption = index;
}

function submitQuizAnswer() {
    if (selectedOption === null) {
        Swal.fire({
            icon: 'warning',
            title: 'Selecciona una opción',
            text: 'Por favor, selecciona una respuesta antes de enviar.'
        });
        return;
    }

    // Asegurarnos de que la actividad esté correctamente parseada
    let activity;
    try {
        const rawActivity = @json($session->practice_activity);
        activity = typeof rawActivity === 'string' ? JSON.parse(rawActivity) : rawActivity;
    } catch (e) {
        console.error('Error al parsear la actividad:', e);
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Ocurrió un error al procesar la actividad. Por favor, intenta nuevamente.'
        });
        return;
    }
    
    // Validar la estructura de la actividad
    const validationResult = validateActivity(activity);
    if (!validationResult.isValid) {
        console.error('La actividad no tiene el formato esperado:', validationResult.errors);
        Swal.fire({
            icon: 'error',
            title: 'Error en la actividad',
            text: 'La actividad no tiene el formato esperado. ' + validationResult.errors.join('. ')
        });
        return;
    }
    
    // Usar la actividad validada
    activity = validationResult.activity;
    const correctAnswer = activity.correct_answer;
    const isCorrect = selectedOption === correctAnswer;
    const score = isCorrect ? 100 : 0;
    
    // Mostrar resultado inmediato
    displayQuizResults(isCorrect, score, activity);
    
    // Guardar progreso
    saveSessionProgress(score, selectedOption, activity);
}

function displayQuizResults(isCorrect, score, activity) {
    currentScore = score;
    
    // Actualizar puntuación
    document.getElementById('scoreValue').textContent = score;
    const scoreBar = document.getElementById('scoreBar');
    scoreBar.style.width = score + '%';
    scoreBar.className = isCorrect ? 'progress-bar bg-success' : 'progress-bar bg-danger';
    
    // Mostrar feedback
    let feedbackHtml = '';
    
    if (isCorrect) {
        feedbackHtml += `<div class="alert alert-success">
            <h6><i class="fas fa-check-circle me-2"></i>¡Respuesta Correcta!</h6>
            <p class="mb-0">¡Excelente trabajo! Has seleccionado la respuesta correcta.</p>
        </div>`;
    } else {
        // La actividad ya ha sido validada en submitQuizAnswer, pero verificamos por seguridad
        let correctAnswerText = 'No disponible';
        
        if (activity.options && Array.isArray(activity.options) && 
            typeof activity.correct_answer !== 'undefined' && 
            activity.correct_answer >= 0 && 
            activity.correct_answer < activity.options.length) {
            correctAnswerText = activity.options[activity.correct_answer];
        }
        
        feedbackHtml += `<div class="alert alert-danger">
            <h6><i class="fas fa-times-circle me-2"></i>Respuesta Incorrecta</h6>
            <p class="mb-0">La respuesta correcta era: <strong>${correctAnswerText}</strong></p>
        </div>`;
    }
    
    // Mostrar explicación si existe
    if (activity.explanation) {
        feedbackHtml += `<div class="alert alert-info">
            <h6><i class="fas fa-lightbulb me-2"></i>Explicación:</h6>
            <p class="mb-0">${activity.explanation}</p>
        </div>`;
    }
    
    document.getElementById('feedbackContent').innerHTML = feedbackHtml;
    document.getElementById('evaluationResults').style.display = 'block';
    document.getElementById('evaluationResults').scrollIntoView({ behavior: 'smooth' });
}

function saveSessionProgress(score, selectedAnswer, activityData) {
    // Asegurarnos de que activityData sea un objeto válido
    const safeActivity = activityData && typeof activityData === 'object' ? activityData : {};
    
    fetch('{{ route("courses.complete.session", [$course->id, $session->id]) }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        },
        body: JSON.stringify({
            response: `Opción seleccionada: ${selectedAnswer}`,
            // Enviamos solo los campos necesarios para evitar problemas de serialización
            activity: {
                type: safeActivity.type || 'quiz',
                question: safeActivity.question || '',
                options: Array.isArray(safeActivity.options) ? safeActivity.options : [],
                correct_answer: typeof safeActivity.correct_answer !== 'undefined' ? safeActivity.correct_answer : null,
                explanation: safeActivity.explanation || ''
            },
            score: score
        })
    })
    .then(response => response.json())
    .then(data => {
        if (!data.success) {
            console.error('Error saving progress:', data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
    });
}

function completeSession() {
    // Si ya hay una puntuación, redirigir directamente
    if (currentScore > 0 || {{ $progress && $progress->first() && $progress->first()->is_completed ? 'true' : 'false' }}) {
        window.location.href = '{{ route("courses.show", $course->id) }}';
        return;
    }

    // Si no hay actividad, marcar como completada con puntuación básica
    fetch('{{ route("courses.complete.session", [$course->id, $session->id]) }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        },
        body: JSON.stringify({
            response: 'Sesión completada sin actividad práctica',
            activity: null
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('¡Sesión completada exitosamente!');
            window.location.href = '{{ route("courses.show", $course->id) }}';
        } else {
            alert('Error al completar la sesión: ' + (data.message || 'Error desconocido'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error al completar la sesión. Por favor, intenta de nuevo.');
    });
}

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
    statusElement.className = 'text-info';
    
    // Realizar petición para generar recursos
    fetch(`{{ url('/') }}/cursos/${courseId}/sesion/${sessionId}/generar-recursos`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Éxito - actualizar interfaz
            statusElement.innerHTML = `${resourceName.charAt(0).toUpperCase() + resourceName.slice(1)} generada exitosamente`;
            statusElement.className = 'text-success';
            
            // Ocultar botón y mostrar mensaje de éxito
            button.style.display = 'none';
            
            // Mostrar notificación de éxito
            Swal.fire({
                icon: 'success',
                title: `¡${resourceName.charAt(0).toUpperCase() + resourceName.slice(1)} generada!`,
                text: `La ${resourceName} se ha generado exitosamente.`,
                timer: 3000,
                showConfirmButton: false
            }).then(() => {
                // Recargar página para mostrar el recurso generado
                location.reload();
            });
            
        } else {
            // Error - restaurar estado
            button.disabled = false;
            button.innerHTML = `<i class="fas fa-${resourceType === 'image' ? 'magic' : 'microphone'} me-1"></i>Generar ${resourceName.charAt(0).toUpperCase() + resourceName.slice(1)}`;
            statusElement.innerHTML = `Error al generar ${resourceName}`;
            statusElement.className = 'text-danger';
            
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: data.message || `Error al generar ${resourceName}`
            });
        }
    })
    .catch(error => {
        console.error('Error:', error);
        
        // Error de conexión - restaurar estado
        button.disabled = false;
        button.innerHTML = `<i class="fas fa-${resourceType === 'image' ? 'magic' : 'microphone'} me-1"></i>Generar ${resourceName.charAt(0).toUpperCase() + resourceName.slice(1)}`;
        statusElement.innerHTML = `Error de conexión al generar ${resourceName}`;
        statusElement.className = 'text-danger';
        
        Swal.fire({
            icon: 'error',
            title: 'Error de conexión',
            text: `No se pudo conectar con el servidor para generar la ${resourceName}`
        });
    });
}

</script>
@endpush
@endsection
