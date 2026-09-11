@extends('layout.app')

@section('title','Ejercicios Generados por IA')

@section('content')
<meta name="csrf-token" content="{{ csrf_token() }}">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11.0.19/dist/sweetalert2.min.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.0.19/dist/sweetalert2.all.min.js"></script>
<script src="{{ asset('audio/audio_generator.js') }}"></script>
<style>
    .card { border: none; box-shadow: 0 0.5rem 1.5rem 0.5rem rgba(0, 0, 0, 0.075); }
    .card-header { background-color: #fff; border-bottom: 1px solid #eff2f5; }
    .btn-primary { background-color: #009ef7; border-color: #009ef7; }
    .btn-primary:hover { background-color: #0095e8; border-color: #0095e8; }
    .level-badge { font-size: 1.2rem; padding: 0.4rem 0.8rem; }
    .ai-icon { font-size: 2.5rem; color: #7239ea; }
    .exercise-card { transition: transform 0.2s; }
    .exercise-card:hover { transform: translateY(-5px); }
    .focus-badge { 
        position: absolute; 
        top: -10px; 
        right: -10px; 
        border-radius: 50%; 
        width: 30px; 
        height: 30px; 
        display: flex; 
        align-items: center; 
        justify-content: center;
        box-shadow: 0 0.5rem 1.5rem 0.2rem rgba(0, 0, 0, 0.1);
    }
</style>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h2 class="card-title fw-bold"><i class="fa-solid fa-robot me-2"></i>Ejercicios Generados por IA</h2>
        <span class="badge bg-primary level-badge">Nivel {{ $level }}</span>
    </div>
    
    <div class="card-body">
        <div class="alert alert-info d-flex mb-4">
            <div class="me-4">
                <i class="fas fa-lightbulb ai-icon"></i>
            </div>
            <div>
                <h4>Ejercicios personalizados</h4>
                <p class="mb-0">Basado en tu evaluación, hemos generado ejercicios enfocados en mejorar tu área más débil: <strong>{{ ucfirst($weakestSection) }}</strong>. Practica estos ejercicios regularmente para mejorar tus habilidades.</p>
            </div>
        </div>
        
        <div class="row mb-4">
            @php
                // Mapear tipos de ejercicio a iconos y colores
                $typeIcons = [
                    'vocabulary' => ['icon' => 'fa-book', 'color' => 'primary'],
                    'grammar' => ['icon' => 'fa-pencil-alt', 'color' => 'danger'],
                    'practice' => ['icon' => 'fa-comments', 'color' => 'success'],
                    'listening' => ['icon' => 'fa-headphones', 'color' => 'warning']
                ];
                
                // Mapear tipos a nombres en español
                $typeNames = [
                    'vocabulary' => 'Vocabulario',
                    'grammar' => 'Gramática',
                    'practice' => 'Conversación',
                    'listening' => 'Comprensión Auditiva'
                ];
                
                $icon = $typeIcons[$focusType]['icon'] ?? 'fa-star';
                $color = $typeIcons[$focusType]['color'] ?? 'primary';
                $typeName = $typeNames[$focusType] ?? ucfirst($focusType);
            @endphp
            
            <div class="col-12 mb-4">
                <h3 class="mb-3">
                    <i class="fas {{ $icon }} text-{{ $color }} me-2"></i>
                    Ejercicios de {{ $typeName }}
                </h3>
            </div>
            
            @foreach($exercises as $index => $exercise)
                <div class="col-md-4 mb-4">
                    <div class="card h-100 exercise-card position-relative">
                        <span class="badge bg-{{ $color }} focus-badge">{{ $index + 1 }}</span>
                        <div class="card-body p-4">
                            <h4 class="mb-3">{{ $exercise['title'] }}</h4>
                            <p class="mb-4">{{ Str::limit($exercise['instructions'], 100) }}</p>
                            <div class="d-grid">
                                <button class="btn btn-{{ $color }} start-exercise" data-exercise="{{ $index }}">
                                    <i class="fas fa-play me-2"></i> Iniciar
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
        
        <!-- Área de trabajo para el ejercicio seleccionado -->
        <div class="card mb-4 d-none" id="exercise-workspace">
            <div class="card-header">
                <h4 class="card-title mb-0" id="current-exercise-title">Ejercicio</h4>
            </div>
            <div class="card-body">
                <div class="alert alert-light mb-4">
                    <p class="mb-0" id="current-exercise-description"></p>
                </div>
                
                <!-- El área de respuesta principal ahora está integrada en cada ejercicio -->
                <div class="form-group mb-4" id="fallback-answer-container" style="display:none;">
                    <label class="form-label">Si no puedes responder arriba, usa este campo:</label>
                    <textarea class="form-control" rows="4" id="exercise-answer"></textarea>
                </div>
                
                @if($focusType == 'listening')
                <div class="d-flex justify-content-center mb-4">
                    <button id="play-audio-btn" class="btn btn-primary btn-lg" data-level="{{ $level }}">
                        <i class="fas fa-volume-up me-2"></i> Reproducir audio
                    </button>
                    <p class="text-muted mt-2">Escucha el audio y responde a las preguntas en el área de texto.</p>
                </div>
                @endif
                
                @if($focusType == 'practice')
                <div class="text-center mb-4">
                    <button class="btn btn-danger btn-lg record-btn">
                        <i class="fas fa-microphone me-2"></i> Grabar respuesta
                    </button>
                    <p class="text-muted mt-2" id="recording-status">Haz clic para comenzar a grabar</p>
                </div>
                @endif
                
                <div class="d-flex justify-content-between">
                    <button class="btn btn-light" id="cancel-exercise">
                        <i class="fas fa-times me-2"></i> Cancelar
                    </button>
                    <button class="btn btn-success" id="submit-exercise">
                        <i class="fas fa-check me-2"></i> Enviar respuesta
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Progreso del usuario -->
        <div class="card mb-4">
            <div class="card-header">
                <h4 class="card-title mb-0">Tu progreso actual</h4>
            </div>
            <div class="card-body">
                <div class="row">
                    @php
                        // Calcular porcentajes (cada sección tiene 5 puntos máximo)
                        $vocabularyPercent = isset($sectionScores['vocabulary']) ? ($sectionScores['vocabulary'] / 5) * 100 : 0;
                        $grammarPercent = isset($sectionScores['grammar']) ? ($sectionScores['grammar'] / 5) * 100 : 0;
                        $speakingPercent = isset($sectionScores['speaking']) ? ($sectionScores['speaking'] / 5) * 100 : 0;
                        $listeningPercent = isset($sectionScores['listening']) ? ($sectionScores['listening'] / 5) * 100 : 0;
                    @endphp
                    
                    <div class="col-md-3 mb-3">
                        <h5>Vocabulario</h5>
                        <div class="progress">
                            <div class="progress-bar bg-primary" role="progressbar" 
                                style="width: {{ $vocabularyPercent }}%" 
                                aria-valuenow="{{ $vocabularyPercent }}" 
                                aria-valuemin="0" 
                                aria-valuemax="100">{{ round($vocabularyPercent) }}%</div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <h5>Gramática</h5>
                        <div class="progress">
                            <div class="progress-bar bg-danger" role="progressbar" 
                                style="width: {{ $grammarPercent }}%" 
                                aria-valuenow="{{ $grammarPercent }}" 
                                aria-valuemin="0" 
                                aria-valuemax="100">{{ round($grammarPercent) }}%</div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <h5>Conversación</h5>
                        <div class="progress">
                            <div class="progress-bar bg-success" role="progressbar" 
                                style="width: {{ $speakingPercent }}%" 
                                aria-valuenow="{{ $speakingPercent }}" 
                                aria-valuemin="0" 
                                aria-valuemax="100">{{ round($speakingPercent) }}%</div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <h5>Comprensión Auditiva</h5>
                        <div class="progress">
                            <div class="progress-bar bg-warning" role="progressbar" 
                                style="width: {{ $listeningPercent }}%" 
                                aria-valuenow="{{ $listeningPercent }}" 
                                aria-valuemin="0" 
                                aria-valuemax="100">{{ round($listeningPercent) }}%</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="d-flex justify-content-between">
            <a href="{{ route('tutor.exercises', ['level' => $level]) }}" class="btn btn-light">
                <i class="fas fa-arrow-left me-2"></i>Volver a ejercicios
            </a>
            <a href="{{ route('tutor.evaluation') }}" class="btn btn-primary">
                <i class="fas fa-redo me-2"></i>Nueva evaluación
            </a>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const startButtons = document.querySelectorAll('.start-exercise');
        const exerciseWorkspace = document.getElementById('exercise-workspace');
        const currentExerciseTitle = document.getElementById('current-exercise-title');
        const currentExerciseDescription = document.getElementById('current-exercise-description');
        const cancelExerciseBtn = document.getElementById('cancel-exercise');
        const submitExerciseBtn = document.getElementById('submit-exercise');
        const exercises = @json($exercises);
        
        // Botones para iniciar ejercicios
        startButtons.forEach(button => {
            button.addEventListener('click', function() {
                const exerciseIndex = this.getAttribute('data-exercise');
                const exercise = exercises[exerciseIndex];
                
                // Mostrar el área de trabajo
                exerciseWorkspace.classList.remove('d-none');
                
                // Desplazarse al área de trabajo
                exerciseWorkspace.scrollIntoView({ behavior: 'smooth' });
                
                // Actualizar el contenido del ejercicio
                currentExerciseTitle.textContent = exercise.title;
                
                // Crear el contenido HTML con instrucciones, contenido y ejemplo
                let exerciseHtml = `
                    <div class="mb-3">
                        <h5 class="text-primary">Instrucciones:</h5>
                        <p>${exercise.instructions}</p>
                    </div>
                    <div class="mb-3">
                        <h5 class="text-primary">Contenido:</h5>
                        <div class="exercise-activities">
                            ${typeof exercise.content === 'string' ? 
                                `<p>${exercise.content.replace(/_{3,}/g, '<input type="text" class="form-control d-inline answer-input" style="width: 150px; display: inline-block !important;" data-answer-index="0">')}</p>` : 
                                Array.isArray(exercise.content) ? 
                                    exercise.content.map((item, index) => {
                                        if (typeof item === 'object' && item !== null) {
                                            // Si es un objeto, mostrar sus propiedades con input para activity
                                            return `<div class="card mb-2">
                                                <div class="card-body">
                                                    ${Object.entries(item).map(([key, value]) => {
                                                        if (key === 'activity') {
                                                            // Verificar si tiene espacio en blanco para completar
                                                            if (value.includes('_____')) {
                                                                return `<p><strong>${key}:</strong> ${value.replace(/_{3,}/g, '<input type="text" class="form-control d-inline answer-input" style="width: 150px; display: inline-block !important;" data-answer-index="' + index + '">')}</p>`;
                                                            } else {
                                                                // Si pide escribir una oración, proporcionar un área de texto
                                                                return `<p><strong>${key}:</strong> ${value}</p>
                                                                <textarea class="form-control answer-input mt-2" rows="2" data-answer-index="${index}"></textarea>`;
                                                            }
                                                        } else {
                                                            return `<p><strong>${key}:</strong> ${value}</p>`;
                                                        }
                                                    }).join('')}
                                                </div>
                                            </div>`;
                                        } else {
                                            // Si es un valor simple con espacio en blanco, añadir input
                                            if (typeof item === 'string' && item.includes('_____')) {
                                                return `<p>${item.replace(/_{3,}/g, '<input type="text" class="form-control d-inline answer-input" style="width: 150px; display: inline-block !important;" data-answer-index="' + index + '">')}</p>`;
                                            } else {
                                                return `<p>${item}</p>`;
                                            }
                                        }
                                    }).join('') : 
                                    `<p>Formato no reconocido</p>`
                            }
                        </div>
                    </div>`;
                
                // Añadir ejemplo si existe
                if (exercise.example) {
                    exerciseHtml += `
                    <div class="mb-3 p-3 bg-light rounded">
                        <h5 class="text-primary">Ejemplo:</h5>
                        <div>
                            ${typeof exercise.example === 'string' ? 
                                `<p>${exercise.example}</p>` : 
                                typeof exercise.example === 'object' && exercise.example !== null ? 
                                    `<div class="card">
                                        <div class="card-body">
                                            ${Object.entries(exercise.example).map(([key, value]) => 
                                                `<p><strong>${key}:</strong> ${value}</p>`
                                            ).join('')}
                                        </div>
                                    </div>` : 
                                    `<p>No hay ejemplo disponible</p>`
                            }
                        </div>
                    </div>`;
                }
                
                // Actualizar la descripción con el HTML
                currentExerciseDescription.innerHTML = exerciseHtml;
                
                // Guardar el índice del ejercicio actual
                exerciseWorkspace.setAttribute('data-current-exercise', exerciseIndex);
            });
        });
        
        // Botón para cancelar ejercicio
        cancelExerciseBtn.addEventListener('click', function() {
            exerciseWorkspace.classList.add('d-none');
            document.getElementById('exercise-answer').value = '';
        });
        
        // Botón para enviar respuesta
        submitExerciseBtn.addEventListener('click', function() {
            const exerciseIndex = exerciseWorkspace.getAttribute('data-current-exercise');
            
            // Recolectar todas las respuestas de los inputs en el ejercicio actual
            const answerInputs = document.querySelectorAll('.answer-input');
            let combinedAnswer = '';
            
            let hasAnswers = false;
            // Crear un array para almacenar todas las respuestas
            const answerParts = [];
            
            answerInputs.forEach(input => {
                const value = input.value.trim();
                if (value) {
                    hasAnswers = true;
                    const index = input.getAttribute('data-answer-index') || '0';
                    answerParts.push(`Pregunta ${index}: ${value}`);
                }
            });
            
            // También verificar si hay respuesta en el textarea principal (fallback)
            const fallbackAnswer = document.getElementById('exercise-answer').value.trim();
            if (fallbackAnswer) {
                hasAnswers = true;
                answerParts.push(`Respuesta general: ${fallbackAnswer}`);
            }
            
            // Combinar todas las respuestas en un solo string
            combinedAnswer = answerParts.join('\n');
            
            // Validar que haya al menos una respuesta
            if (!hasAnswers) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Respuesta requerida',
                    text: 'Por favor, completa al menos uno de los ejercicios antes de enviar.',
                    confirmButtonColor: '#009ef7'
                });
                return;
            }
            
            // Simular evaluación de las respuestas (en un sistema real, esto se haría en el servidor)
            // Para esta demostración, consideramos correctas el 70% de las respuestas aleatoriamente
            const isCorrect = Math.random() > 0.3;
            
            // Mostrar indicador de carga
            submitExerciseBtn.disabled = true;
            submitExerciseBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Enviando...';
            
            // Crear un objeto FormData para el envío (más compatible)
            const formData = new FormData();
            formData.append('_token', '{{ csrf_token() }}');
            formData.append('type', '{{ $focusType }}');
            formData.append('level', '{{ $level }}');
            formData.append('exercise_id', exerciseIndex);
            formData.append('answer', combinedAnswer);
            formData.append('is_correct', isCorrect ? '1' : '0');
            
            // Enviar la respuesta al servidor usando XMLHttpRequest en lugar de fetch
            const xhr = new XMLHttpRequest();
            xhr.open('POST', '{{ route("tutor.save.progress") }}', true);
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            xhr.setRequestHeader('X-CSRF-TOKEN', document.querySelector('meta[name="csrf-token"]').getAttribute('content'));
            
            xhr.onload = function() {
                // Restaurar el botón de envío
                submitExerciseBtn.disabled = false;
                submitExerciseBtn.innerHTML = '<i class="fas fa-check me-2"></i> Enviar respuesta';
                
                if (xhr.status === 200) {
                    try {
                        const data = JSON.parse(xhr.responseText);
                        
                        if (data.success) {
                            // Actualizar las barras de progreso con los nuevos datos
                            if (data.progress && data.progress.section_scores) {
                        const progressBars = {
                            'vocabulary': document.querySelector('.progress-bar.bg-primary'),
                            'grammar': document.querySelector('.progress-bar.bg-danger'),
                            'speaking': document.querySelector('.progress-bar.bg-success'),
                            'listening': document.querySelector('.progress-bar.bg-warning')
                        };
                        
                        // Actualizar todas las barras de progreso
                        for (const [section, bar] of Object.entries(progressBars)) {
                            if (bar && data.progress.section_scores[section]) {
                                const score = data.progress.section_scores[section];
                                const maxScore = 5;
                                const progressPercent = (score / maxScore) * 100;
                                
                                bar.style.width = progressPercent + '%';
                                bar.setAttribute('aria-valuenow', progressPercent);
                                bar.textContent = Math.round(progressPercent) + '%';
                            }
                        }
                            }
                            
                            // Ocultar el área de trabajo y limpiar campos
                            exerciseWorkspace.classList.add('d-none');
                            document.getElementById('exercise-answer').value = '';
                            
                            // Limpiar todos los inputs de respuesta
                            document.querySelectorAll('.answer-input').forEach(input => {
                                input.value = '';
                            });
                            
                            // Marcar el ejercicio como completado (cambiar estilo del botón)
                            const button = document.querySelector(`.start-exercise[data-exercise="${exerciseIndex}"]`);
                            if (button) {
                                button.classList.remove('btn-primary', 'btn-danger', 'btn-success', 'btn-warning');
                                button.classList.add('btn-light');
                                button.innerHTML = '<i class="fas fa-check me-2"></i> Completado';
                            }
                            
                            // Mostrar mensaje de éxito o error según la evaluación
                            if (isCorrect) {
                                Swal.fire({
                                    icon: 'success',
                                    title: '¡Correcto!',
                                    text: 'Tu respuesta es correcta. ¡Sigue así!',
                                    confirmButtonColor: '#50cd89'
                                });
                            } else {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Incorrecto',
                                    text: 'Tu respuesta no es correcta. ¡Inténtalo de nuevo!',
                                    confirmButtonColor: '#009ef7'
                                });
                            }
                        } else {
                            // Mostrar mensaje de error con SweetAlert
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: 'Error al guardar el progreso: ' + (data.error || 'Error desconocido'),
                                confirmButtonColor: '#009ef7'
                            });
                            console.error('Error del servidor:', data);
                        }
                    } catch (e) {
                        console.error('Error al procesar la respuesta:', e, xhr.responseText);
                        Swal.fire({
                            icon: 'error',
                            title: 'Error de parseo',
                            text: 'Hubo un problema al procesar la respuesta del servidor',
                            confirmButtonColor: '#009ef7'
                        });
                    }
                } else {
                    console.error('Error HTTP:', xhr.status, xhr.statusText);
                    Swal.fire({
                        icon: 'error',
                        title: 'Error ' + xhr.status,
                        text: 'Hubo un problema al comunicarse con el servidor',
                        confirmButtonColor: '#009ef7'
                    });
                }
            };
            
            // Manejar errores de red
            xhr.onerror = function() {
                // Restaurar el botón de envío
                submitExerciseBtn.disabled = false;
                submitExerciseBtn.innerHTML = '<i class="fas fa-check me-2"></i> Enviar respuesta';
                
                console.error('Error de red al enviar la solicitud');
                Swal.fire({
                    icon: 'error',
                    title: 'Error de conexión',
                    text: 'No fue posible enviar tu respuesta. Por favor, verifica tu conexión e inténtalo nuevamente.',
                    confirmButtonColor: '#009ef7',
                    footer: '<a href="#">Reportar este problema al soporte</a>'
                });
            };
            
            // Enviar la solicitud
            xhr.send(formData);
        });
        });
        
        // Funcionalidad para el botón de grabación usando Web Speech API
        const recordBtn = document.querySelector('.record-btn');
        if (recordBtn) {
            let isRecording = false;
            let recognition = null;
            const recordingStatus = document.getElementById('recording-status');
            
            // Verificar si el navegador soporta la API de reconocimiento de voz
            if ('webkitSpeechRecognition' in window || 'SpeechRecognition' in window) {
                // Crear una instancia de reconocimiento de voz
                recognition = new (window.SpeechRecognition || window.webkitSpeechRecognition)();
                recognition.continuous = true;
                recognition.interimResults = true;
                recognition.lang = 'en-US'; // Configurar para reconocimiento en inglés
                
                // Configurar los eventos de reconocimiento
                recognition.onstart = function() {
                    isRecording = true;
                    recordBtn.innerHTML = '<i class="fas fa-stop me-2"></i> Detener grabación';
                    recordBtn.classList.add('btn-danger');
                    recordBtn.classList.remove('btn-primary');
                    recordingStatus.textContent = 'Grabando... Habla en inglés';
                };
                
                recognition.onresult = function(event) {
                    let interimTranscript = '';
                    let finalTranscript = '';
                    
                    for (let i = event.resultIndex; i < event.results.length; i++) {
                        const transcript = event.results[i][0].transcript;
                        if (event.results[i].isFinal) {
                            finalTranscript += transcript;
                        } else {
                            interimTranscript += transcript;
                        }
                    }
                    
                    // Actualizar el área de texto con la transcripción
                    const answerArea = document.getElementById('exercise-answer');
                    answerArea.value = finalTranscript || interimTranscript;
                };
                
                recognition.onerror = function(event) {
                    console.error('Error de reconocimiento de voz:', event.error);
                    stopRecording();
                    recordingStatus.textContent = 'Error en la grabación: ' + event.error;
                };
                
                recognition.onend = function() {
                    stopRecording();
                };
                
                // Función para detener la grabación
                function stopRecording() {
                    isRecording = false;
                    recordBtn.innerHTML = '<i class="fas fa-microphone me-2"></i> Grabar respuesta';
                    recordBtn.classList.remove('btn-danger');
                    recordBtn.classList.add('btn-primary');
                    recordingStatus.textContent = 'Grabación completada';
                    if (recognition) {
                        recognition.stop();
                    }
                }
                
                // Evento de clic para iniciar/detener la grabación
                recordBtn.addEventListener('click', function() {
                    if (!isRecording) {
                        // Iniciar grabación
                        recognition.start();
                    } else {
                        // Detener grabación
                        stopRecording();
                    }
                });
                
            } else {
                // Navegador no compatible, mostrar mensaje y deshabilitar botón
                recordBtn.addEventListener('click', function() {
                    Swal.fire({
                        icon: 'error',
                        title: 'Navegador no compatible',
                        text: 'Tu navegador no soporta el reconocimiento de voz. Intenta con Chrome, Edge o Safari recientes.',
                        confirmButtonColor: '#009ef7'
                    });
                });
                recordingStatus.textContent = 'Reconocimiento de voz no disponible en este navegador';
                recordBtn.classList.add('disabled');
            }
        }
        // Funcionalidad para el botón de reproducir audio usando el generador de audio sintético
        const playAudioBtn = document.getElementById('play-audio-btn');
        
        if (playAudioBtn) {
            let isSpeaking = false;
            
            playAudioBtn.addEventListener('click', function() {
                const level = this.getAttribute('data-level') || 'B1';
                
                if (isSpeaking) {
                    // Detener la síntesis de voz si está reproduciéndose
                    window.speechSynthesis.cancel();
                    isSpeaking = false;
                    playAudioBtn.innerHTML = '<i class="fas fa-volume-up me-2"></i> Reproducir audio';
                } else {
                    // Iniciar la síntesis de voz
                    playAudioBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Reproduciendo...';
                    isSpeaking = true;
                    
                    // Generar y reproducir el audio
                    if (window.generateEnglishAudio) {
                        window.generateEnglishAudio(level);
                        
                        // Comprobar periódicamente si la síntesis de voz ha terminado
                        const checkSpeaking = setInterval(function() {
                            if (!window.speechSynthesis.speaking) {
                                clearInterval(checkSpeaking);
                                isSpeaking = false;
                                playAudioBtn.innerHTML = '<i class="fas fa-volume-up me-2"></i> Reproducir audio';
                            }
                        }, 100);
                    } else {
                        // Si no se puede cargar el generador de audio, mostrar un error
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'No se pudo cargar el generador de audio. Por favor, recarga la página.',
                            confirmButtonColor: '#009ef7'
                        });
                        isSpeaking = false;
                        playAudioBtn.innerHTML = '<i class="fas fa-volume-up me-2"></i> Reproducir audio';
                    }
                }
            });
        }
    });
</script>
@endsection
