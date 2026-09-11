@extends('layout.app')

@section('title', 'Práctica de ' . ucfirst($type) . ' - Nivel ' . $level)

@section('content')
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                        <h2 class="card-title mb-0">
                            @if($type == 'vocabulary')
                                <i class="fas fa-book text-primary me-2"></i>Práctica de Vocabulario
                            @elseif($type == 'grammar')
                                <i class="fas fa-pencil-alt text-danger me-2"></i>Práctica de Gramática
                            @elseif($type == 'speaking')
                                <i class="fas fa-microphone text-success me-2"></i>Práctica de Conversación
                            @elseif($type == 'listening')
                                <i class="fas fa-headphones text-warning me-2"></i>Práctica de Comprensión Auditiva
                            @else
                                <i class="fas fa-graduation-cap text-info me-2"></i>Práctica General
                            @endif
                        </h2>
                        <span class="badge bg-primary">Nivel {{ $level }}</span>
                    </div>
                    <a href="{{ route('tutor.exercises', ['level' => $level]) }}" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Volver a ejercicios
                    </a>
                </div>
                
                <div class="card-body">
                    <!-- Mensaje motivacional -->
                    @if(isset($motivationalMessage))
                    <div class="alert alert-info d-flex align-items-center mb-4">
                        <i class="fas fa-lightbulb me-3 fs-4"></i>
                        <div>
                            <strong>¡Mensaje de tu tutor!</strong><br>
                            {{ $motivationalMessage }}
                        </div>
                        <button id="play-motivation" class="btn btn-sm btn-outline-info ms-auto">
                            <i class="fas fa-volume-up"></i>
                        </button>
                    </div>
                    @endif
                    
                    <!-- Análisis de IA y recomendación personalizada -->
                    @if(isset($aiRecommendation) && $aiRecommendation && isset($aiAnalysis))
                    <div class="alert alert-success mb-4">
                        <div class="d-flex align-items-start">
                            <i class="fas fa-robot me-3 fs-3"></i>
                            <div>
                                <h5 class="alert-heading">Recomendación Personalizada</h5>
                                <p>Hemos analizado tu progreso y hemos identificado lo siguiente:</p>
                                <ul>
                                    <li><strong>Área más débil:</strong> {{ $aiAnalysis['weakest_area_name'] ?? ucfirst($type) }}</li>
                                    <li><strong>Área más fuerte:</strong> {{ $aiAnalysis['strength_area_name'] ?? 'No determinada' }}</li>
                                </ul>
                                <p>Por eso te hemos generado este ejercicio de <strong>{{ ucfirst($type) }}</strong> para ayudarte a mejorar en esta área específica.</p>
                                <hr>
                                <p class="mb-0">Completa este ejercicio para mejorar tus habilidades y aumentar tu puntuación.</p>
                            </div>
                        </div>
                    </div>
                    @endif
                    
                    <!-- Progreso -->
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <h5>Tu progreso en {{ ucfirst($type) }}</h5>
                            <div class="progress mb-2" style="height: 25px;">
                                <div class="progress-bar" role="progressbar" style="width: {{ $progress }}%" 
                                     aria-valuenow="{{ $progress }}" aria-valuemin="0" aria-valuemax="100">
                                    {{ $progress }}%
                                </div>
                            </div>
                            <p class="text-muted mb-0" id="progress-text">{{ $score }}/{{ $maxScore }} puntos ({{ $progress }}%)</p>
                        </div>
                    </div>
                    
                    <!-- Ejercicio generado por IA -->
                    @if(isset($aiExercise) && $aiExercise)
                    <div class="row">
                        <div class="col-12">
                            <div class="card border-primary">
                                <div class="card-header bg-light">
                                    <h4 class="mb-0">
                                        <i class="fas fa-robot text-primary me-2"></i>
                                        {{ $aiExercise['title'] ?? 'Ejercicio Personalizado' }}
                                    </h4>
                                </div>
                                <div class="card-body">
                                    <!-- Instrucciones -->
                                    <div class="alert alert-info">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <h6><i class="fas fa-info-circle me-2"></i>Instrucciones:</h6>
                                                @if(is_array($aiExercise['instructions']))
                                                    @foreach($aiExercise['instructions'] as $instruction)
                                                        <p class="mb-1">{!! nl2br(e($instruction)) !!}</p>
                                                    @endforeach
                                                @else
                                                    {!! nl2br(e($aiExercise['instructions'])) !!}
                                                @endif
                                            </div>
                                            <button id="listenInstructionsBtn" class="btn btn-sm btn-success">
                                                <i class="fas fa-volume-up me-1"></i>Escuchar Instrucciones
                                            </button>
                                        </div>
                                    </div>
                                    
                                    <!-- Audio para speaking/listening -->
                                    @if(in_array($type, ['speaking', 'listening']) && isset($audioUrl) && $audioUrl)
                                    <div class="mb-4">
                                        <h6><i class="fas fa-volume-up me-2"></i>Escucha el audio:</h6>
                                        <audio controls class="w-100">
                                            <source src="{{ $audioUrl }}" type="audio/mpeg">
                                            Tu navegador no soporta el elemento de audio.
                                        </audio>
                                        <button id="play-audio" class="btn btn-success mt-2">
                                            <i class="fas fa-play me-2"></i>Reproducir
                                        </button>
                                    </div>
                                    @endif
                                    
                                    <!-- Contenido del ejercicio -->
                                    <div class="mb-4">
                                        <h6>Ejercicio:</h6>
                                        <div class="border p-3 rounded bg-light">
                                            @if(isset($aiExercise['content']))
                                                @if(is_array($aiExercise['content']))
                                                    @foreach($aiExercise['content'] as $item)
                                                        <p>{!! nl2br(e($item)) !!}</p>
                                                    @endforeach
                                                @else
                                                    {!! nl2br(e($aiExercise['content'])) !!}
                                                @endif
                                            @endif
                                        </div>
                                    </div>
                                    
                                    <!-- Ejemplo -->
                                    @if(isset($aiExercise['example']) && $aiExercise['example'])
                                    <div class="mb-4">
                                        <h6><i class="fas fa-lightbulb me-2"></i>Ejemplo:</h6>
                                        <div class="border p-3 rounded bg-warning bg-opacity-10">
                                            @if(is_array($aiExercise['example']))
                                                @foreach($aiExercise['example'] as $item)
                                                    <p>{{ $item }}</p>
                                                @endforeach
                                            @else
                                                {{ $aiExercise['example'] }}
                                            @endif
                                        </div>
                                    </div>
                                    @endif
                                    
                                    <!-- Área de respuesta para speaking -->
                                    @if($type == 'speaking')
                                    <div class="speaking-practice">
                                        <!-- Botón para reproducir instrucciones -->
                                        <div class="mb-3 text-center">
                                            <button id="play-instructions-btn" class="btn btn-info btn-lg">
                                                <i class="fas fa-volume-up me-2"></i>Escuchar Instrucciones del Tutor
                                            </button>
                                        </div>
                                        
                                        <h6><i class="fas fa-microphone me-2"></i>Tu respuesta:</h6>
                                        
                                        <!-- Controles de grabación -->
                                        <div class="d-flex align-items-center mb-3">
                                            <button id="record-btn" class="btn btn-danger me-3">
                                                <i class="fas fa-microphone"></i> Grabar
                                            </button>
                                            <div id="recording-status" class="text-muted">Presiona grabar para comenzar</div>
                                        </div>
                                        
                                        <!-- Información de compatibilidad -->
                                        <div class="alert alert-warning" id="browser-warning" style="display: none;">
                                            <i class="fas fa-exclamation-triangle me-2"></i>
                                            <strong>Nota:</strong> Para una mejor experiencia de grabación, recomendamos usar Chrome o Edge. 
                                            Firefox puede tener limitaciones con el reconocimiento de voz.
                                        </div>
                                        
                                        <!-- Barra de progreso de grabación -->
                                        <div class="progress mb-3" style="height: 10px; display: none;" id="recording-progress">
                                            <div class="progress-bar bg-danger progress-bar-striped progress-bar-animated" 
                                                 role="progressbar" style="width: 0%" id="recording-bar"></div>
                                        </div>
                                        
                                        <!-- Transcripción -->
                                        <div class="mb-3">
                                            <label for="transcription" class="form-label">Transcripción de tu respuesta:</label>
                                            <textarea id="transcription" class="form-control" rows="3" 
                                                      placeholder="Aquí aparecerá la transcripción de tu respuesta..." readonly></textarea>
                                        </div>
                                        
                                        <!-- Análisis palabra por palabra -->
                                        <div id="word-analysis" class="d-none">
                                            <h6><i class="fas fa-chart-line me-2"></i>Análisis de tu pronunciación:</h6>
                                            <div id="word-analysis-content" class="border p-3 rounded"></div>
                                        </div>
                                        
                                        <!-- Botón de evaluación -->
                                        <button id="evaluate-speaking" class="btn btn-primary" disabled>
                                            <i class="fas fa-check me-2"></i>Evaluar mi respuesta
                                        </button>
                                    </div>
                                    @else
                                    <!-- Área de respuesta para otros tipos -->
                                    <div class="card border-success mt-4">
                                        <div class="card-header bg-light">
                                            <h6 class="mb-0">
                                                <i class="fas fa-pencil-alt text-success me-2"></i>
                                                Tu Respuesta
                                            </h6>
                                        </div>
                                        <div class="card-body">
                                            <div class="mb-3">
                                                <label for="user-answer" class="form-label">Escribe tu respuesta en inglés:</label>
                                                <textarea id="user-answer" class="form-control" rows="4" 
                                                    placeholder="Escribe tu respuesta aquí..."></textarea>
                                                <div class="form-text">
                                                    <i class="fas fa-lightbulb text-warning me-1"></i>
                                                    Tómate tu tiempo para pensar y escribir una respuesta completa.
                                                </div>
                                            </div>
                                            
                                            <div class="d-flex gap-2">
                                                <button id="submit-answer" class="btn btn-success">
                                                    <i class="fas fa-paper-plane me-2"></i>Evaluar mi respuesta
                                                </button>
                                                <button id="clear-answer" class="btn btn-outline-secondary">
                                                    <i class="fas fa-eraser me-2"></i>Limpiar
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    @endif
                                    
                                    <!-- Resultados de evaluación -->
                                    <div id="evaluation-results" class="mt-4 d-none">
                                        <div class="card border-primary">
                                            <div class="card-header bg-primary text-white">
                                                <h6 class="mb-0">
                                                    <i class="fas fa-star me-2"></i>Resultados de tu evaluación
                                                </h6>
                                            </div>
                                            <div class="card-body">
                                                <div id="evaluation-content"></div>
                                                
                                                <!-- Botón de audio para feedback -->
                                                <div id="feedback-audio-section" class="mt-3 d-none">
                                                    <button id="play-feedback-btn" class="btn btn-success btn-sm">
                                                        <i class="fas fa-volume-up me-2"></i>Escuchar Comentarios
                                                    </button>
                                                </div>
                                                
                                                <!-- Botón para finalizar -->
                                                <div class="mt-4 text-center">
                                                    <button id="finish-exercise-btn" class="btn btn-primary btn-lg">
                                                        <i class="fas fa-check-circle me-2"></i>Finalizar Ejercicio
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    @else
                    <!-- Fallback si no hay ejercicio de IA -->
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        No se pudo generar un ejercicio personalizado en este momento. Por favor, intenta de nuevo más tarde.
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<meta name="csrf-token" content="{{ csrf_token() }}">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11.0.19/dist/sweetalert2.min.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.0.19/dist/sweetalert2.all.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    console.log('=== SPEAKING PRACTICE DEBUG ===');
    console.log('DOM loaded, initializing speaking practice...');
    
    // Funcionalidad para el botón de grabación usando Web Speech API
    const recordBtn = document.getElementById('record-btn');
    const recordingStatus = document.getElementById('recording-status');
    const recordingProgress = document.getElementById('recording-progress');
    const recordingBar = document.getElementById('recording-bar');
    const transcriptionArea = document.getElementById('transcription');
    const evaluateSpeakingBtn = document.getElementById('evaluate-speaking');
    const wordAnalysis = document.getElementById('word-analysis');
    const wordAnalysisContent = document.getElementById('word-analysis-content');
    const evaluationResults = document.getElementById('evaluation-results');
    const evaluationContent = document.getElementById('evaluation-content');
    
    // Debug: verificar elementos críticos
    console.log('=== ELEMENT CHECK ===');
    console.log('Record button:', recordBtn);
    console.log('Recording status:', recordingStatus);
    console.log('Transcription area:', transcriptionArea);
    console.log('Evaluate speaking button:', evaluateSpeakingBtn);
    console.log('Play instructions button:', document.getElementById('play-instructions-btn'));
    
    // Datos del ejercicio
    const aiExercise = @json($aiExercise ?? null);
    const exerciseType = '{{ $type }}';
    
    console.log('=== DATA CHECK ===');
    console.log('AI Exercise:', aiExercise);
    console.log('Exercise Type:', exerciseType);
    console.log('Is speaking?', exerciseType === 'speaking');
    
    // Funcionalidad para reproducir audio motivacional
    const playMotivationBtn = document.getElementById('play-motivation');
    if (playMotivationBtn) {
        playMotivationBtn.addEventListener('click', function() {
            const message = `{{ $motivationalMessage ?? '' }}`;
            if ('speechSynthesis' in window && message) {
                const utterance = new SpeechSynthesisUtterance(message);
                utterance.lang = 'es-ES';
                utterance.rate = 0.8;
                speechSynthesis.speak(utterance);
            }
        });
    }
    
    // Funcionalidad para reproducir audio del ejercicio
    const playAudioBtn = document.getElementById('play-audio');
    if (playAudioBtn) {
        playAudioBtn.addEventListener('click', function() {
            const audio = document.querySelector('audio');
            if (audio) {
                audio.play();
            }
        });
    }
    
    // Funcionalidad para reproducir instrucciones del tutor en speaking
    const playInstructionsBtn = document.getElementById('play-instructions-btn');
    
    if (playInstructionsBtn && exerciseType === 'speaking') {
        playInstructionsBtn.addEventListener('click', function() {
            // Crear mensaje de instrucciones del tutor
            let instructionsText = 'Welcome to your speaking practice. Please read the exercise carefully and then record your response in English.';
            
            // Obtener las instrucciones del ejercicio si están disponibles
            if (aiExercise && aiExercise.instructions) {
                if (typeof aiExercise.instructions === 'string') {
                    instructionsText = aiExercise.instructions;
                } else if (Array.isArray(aiExercise.instructions)) {
                    instructionsText = aiExercise.instructions.join(' ');
                }
            }
            
            // Cambiar estado del botón
            playInstructionsBtn.disabled = true;
            playInstructionsBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Generando audio...';

            // Función para mostrar botón de reproducción cuando autoplay está bloqueado
            function showPlayButton(audio) {
                resetInstructionsButton();
                playInstructionsBtn.innerHTML = '<i class="fas fa-play me-2"></i>Reproduciendo...';
                playInstructionsBtn.style.backgroundColor = '#28a745';
                playInstructionsBtn.style.borderColor = '#28a745';
                
                // Crear un nuevo event listener temporal para reproducir el audio
                const playAudioHandler = function() {
                    playInstructionsBtn.innerHTML = '<i class="fas fa-volume-up me-2"></i>Reproduciendo...';
                    playInstructionsBtn.style.backgroundColor = '';
                    playInstructionsBtn.style.borderColor = '';
                    
                    audio.play()
                        .then(() => {
                            console.log('Audio de OpenAI reproduciéndose tras interacción del usuario');
                        })
                        .catch(error => {
                            console.error('Error al reproducir audio tras interacción:', error);
                            useSpeechSynthesisFallback();
                        });
                    
                    // Remover este event listener temporal
                    playInstructionsBtn.removeEventListener('click', playAudioHandler);
                };
                
                // Agregar el event listener temporal
                playInstructionsBtn.addEventListener('click', playAudioHandler);
                
                // Mostrar mensaje informativo
                Swal.fire({
                    icon: 'info',
                    title: 'Audio listo',
                    text: 'El audio se ha generado con OpenAI. Haz clic en el botón verde para reproducirlo.',
                    confirmButtonColor: '#28a745',
                    timer: 3000,
                    timerProgressBar: true
                });
            }

            fetch('{{ route("tutor.generate.instructions.audio") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({
                    text: instructionsText
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success && data.audio_url) {
                    // Reproducir audio de OpenAI
                    playInstructionsBtn.innerHTML = '<i class="fas fa-play me-2"></i>Reproduciendo...';
                    
                    const audio = new Audio(data.audio_url);
                    
                    // Configurar eventos del audio
                    audio.onplay = function() {
                        console.log('Audio de instrucciones iniciado (OpenAI)');
                    };
                    audio.onended = function() {
                        resetInstructionsButton();
                        console.log('Audio de instrucciones finalizado (OpenAI)');
                    };
                    audio.onerror = function(e) {
                        console.error('Error al reproducir audio de OpenAI:', e);
                        useSpeechSynthesisFallback();
                    };
                    
                    // Intentar reproducir con manejo de autoplay
                    const playPromise = audio.play();
                    
                    if (playPromise !== undefined) {
                        playPromise
                            .then(() => {
                                console.log('Audio de OpenAI reproduciéndose correctamente');
                            })
                            .catch(error => {
                                console.error('Error de autoplay bloqueado:', error);
                                
                                // Si es un error de autoplay, crear un botón para que el usuario haga clic
                                if (error.name === 'NotAllowedError' || error.message.includes('play method is not allowed')) {
                                    showPlayButton(audio);
                                } else {
                                    console.error('Error diferente al reproducir audio de OpenAI:', error);
                                    useSpeechSynthesisFallback();
                                }
                            });
                    } else {
                        // Navegador muy antiguo, usar fallback
                        useSpeechSynthesisFallback();
                    }
                } else {
                    console.warn('No se pudo generar audio con OpenAI, usando fallback');
                    useSpeechSynthesisFallback();
                }
            })
            .catch(error => {
                console.error('Error al conectar con API de audio:', error);
                useSpeechSynthesisFallback();
            });
            
            // Función fallback usando speechSynthesis
            function useSpeechSynthesisFallback() {
                console.log('Usando speechSynthesis como fallback');
                attemptSpeechSynthesis();
            }
            
            // Función para intentar síntesis de voz con fallback
            function attemptSpeechSynthesis() {
                if (!('speechSynthesis' in window)) {
                    showSpeechNotAvailable();
                    return;
                }
                
                try {
                    // Verificar si hay voces disponibles
                    const voices = speechSynthesis.getVoices();
                    console.log('Available voices:', voices.length);
                    
                    // Si no hay voces, esperar a que se carguen
                    if (voices.length === 0) {
                        speechSynthesis.addEventListener('voiceschanged', function() {
                            console.log('Voices loaded, retrying synthesis');
                            performSynthesis();
                        }, { once: true });
                        
                        // Timeout si las voces no se cargan
                        setTimeout(() => {
                            if (speechSynthesis.getVoices().length === 0) {
                                console.warn('No voices available after timeout');
                                showSpeechNotAvailable();
                            }
                        }, 3000);
                    } else {
                        performSynthesis();
                    }
                    
                } catch (error) {
                    console.error('Error in speech synthesis setup:', error);
                    showSpeechNotAvailable();
                }
            }
            
            function performSynthesis() {
                try {
                    speechSynthesis.cancel();
                    
                    setTimeout(() => {
                        const utterance = new SpeechSynthesisUtterance(instructionsText);
                        // Determinar idioma según el tipo de ejercicio
                        const exerciseType = '{{ $type }}';
                        if (exerciseType === 'speaking' || exerciseType === 'listening') {
                            // Para ejercicios de speaking y listening, las instrucciones suelen estar en inglés
                            utterance.lang = 'en-US';
                        } else {
                            // Para vocabulario y gramática, usar español
                            utterance.lang = 'es-ES';
                        }
                        utterance.rate = 0.8;
                        utterance.pitch = 1.0;
                        utterance.volume = 1.0;
                        
                        // Buscar una voz en inglés
                        const voices = speechSynthesis.getVoices();
                        const englishVoice = voices.find(voice => 
                            voice.lang.startsWith('en') && voice.name.includes('Google')
                        ) || voices.find(voice => voice.lang.startsWith('en'));
                        
                        if (englishVoice) {
                            utterance.voice = englishVoice;
                        }
                        
                        utterance.onstart = function() {
                            console.log('Speech synthesis started');
                            playInstructionsBtn.innerHTML = '<i class="fas fa-volume-up me-2"></i>Reproduciendo...';
                        };
                        
                        utterance.onend = function() {
                            console.log('Speech synthesis ended');
                            resetInstructionsButton();
                        };
                        
                        utterance.onerror = function(event) {
                            console.error('Speech synthesis error details:', {
                                error: event.error,
                                type: event.type,
                                target: event.target,
                                message: event.message || 'Unknown error',
                                voicesAvailable: speechSynthesis.getVoices().length
                            });
                            resetInstructionsButton();
                            
                            // No mostrar alerta para errores menores como 'interrupted'
                            if (event.error !== 'interrupted') {
                                showSpeechNotAvailable();
                            }
                        };
                        
                        // Verificar que speechSynthesis esté listo antes de hablar
                        if (speechSynthesis.speaking) {
                            speechSynthesis.cancel();
                            setTimeout(() => speechSynthesis.speak(utterance), 100);
                        } else {
                            speechSynthesis.speak(utterance);
                        }
                        
                    }, 200);
                    
                } catch (error) {
                    console.error('Error creating speech synthesis:', error);
                    resetInstructionsButton();
                    showSpeechNotAvailable();
                }
            }
            
            function resetInstructionsButton() {
                playInstructionsBtn.innerHTML = '<i class="fas fa-volume-up me-2"></i>Escuchar Instrucciones del Tutor';
                playInstructionsBtn.disabled = false;
            }
            
            function showSpeechNotAvailable() {
                resetInstructionsButton();
                Swal.fire({
                    icon: 'info',
                    title: 'Audio no disponible',
                    text: 'No se pudo reproducir el audio de las instrucciones. Puedes leer las instrucciones escritas arriba.',
                    confirmButtonColor: '#009ef7',
                    timer: 3000,
                    timerProgressBar: true
                });
            }
        });
        
        // Comentado: Auto-reproducir removido para evitar errores de autoplay
        // Los navegadores modernos requieren interacción del usuario antes de reproducir audio
        /*
        setTimeout(() => {
            if (playInstructionsBtn && 'speechSynthesis' in window) {
                // Verificar si las voces están disponibles antes de auto-reproducir
                const voices = speechSynthesis.getVoices();
                if (voices.length > 0) {
                    playInstructionsBtn.click();
                } else {
                    // Esperar a que se carguen las voces
                    speechSynthesis.addEventListener('voiceschanged', function() {
                        playInstructionsBtn.click();
                    }, { once: true });
                }
            }
        }, 2000);
        */
    } else {
        console.log('Instructions button not found or not speaking exercise');
    }
    
    // Detectar Firefox y mostrar advertencia
    const isFirefox = navigator.userAgent.toLowerCase().indexOf('firefox') > -1;
    if (isFirefox && exerciseType === 'speaking') {
        const browserWarning = document.getElementById('browser-warning');
        if (browserWarning) {
            browserWarning.style.display = 'block';
        }
    }
    
    if (recordBtn && exerciseType === 'speaking') {
        let isRecording = false;
        let recognition = null;
        
        // Verificar si el navegador soporta la API de reconocimiento de voz
        if ('webkitSpeechRecognition' in window || 'SpeechRecognition' in window) {
            // Crear una instancia de reconocimiento de voz
            recognition = new (window.SpeechRecognition || window.webkitSpeechRecognition)();
            recognition.continuous = true; // Permitir grabación continua para pausas naturales
            recognition.interimResults = true;
            recognition.lang = 'en-US'; // Configurar para reconocimiento en inglés
            recognition.maxAlternatives = 1;
            
            // Configuración específica para Firefox
            if (isFirefox) {
                recognition.continuous = true; // Cambiar a true para permitir pausas
                recognition.interimResults = false; // Firefox tiene problemas con interim results
            }
            
            // Variables para manejar el timeout de silencio
            let silenceTimer = null;
            let finalTranscript = '';
            let isManualStop = false;
            
            // Configurar los eventos de reconocimiento
            recognition.onstart = function() {
                isRecording = true;
                isManualStop = false;
                finalTranscript = '';
                recordBtn.classList.remove('btn-danger');
                recordBtn.classList.add('btn-warning');
                recordBtn.innerHTML = '<i class="fas fa-stop"></i> Detener';
                recordingStatus.textContent = 'Grabando... Habla en inglés (pausas permitidas)';
                recordingProgress.style.display = 'block';
                
                // Simular progreso de grabación
                let progress = 0;
                const progressInterval = setInterval(() => {
                    progress += 1;
                    recordingBar.style.width = progress + '%';
                    if (progress >= 100) {
                        progress = 0; // Reiniciar el progreso para grabación continua
                    }
                    if (!isRecording) {
                        clearInterval(progressInterval);
                    }
                }, 200);
            };
            
            recognition.onresult = function(event) {
                let interimTranscript = '';
                
                // Limpiar el timer de silencio ya que hay actividad de voz
                if (silenceTimer) {
                    clearTimeout(silenceTimer);
                    silenceTimer = null;
                }
                
                for (let i = event.resultIndex; i < event.results.length; i++) {
                    const transcript = event.results[i][0].transcript;
                    if (event.results[i].isFinal) {
                        finalTranscript += transcript + ' '; // Agregar espacio entre frases
                        console.log('Texto final agregado:', transcript);
                    } else {
                        interimTranscript += transcript;
                    }
                }
                
                // Actualizar el área de texto con la transcripción acumulada
                transcriptionArea.value = finalTranscript + interimTranscript;
                
                // Configurar timer de silencio para auto-detener después de 3 segundos sin voz
                silenceTimer = setTimeout(() => {
                    if (isRecording && !isManualStop) {
                        console.log('Deteniendo grabación por silencio prolongado');
                        stopRecording();
                    }
                }, 3000); // 3 segundos de silencio antes de auto-detener
            };
            
            recognition.onerror = function(event) {
                console.error('Recognition error details:', {
                    error: event.error,
                    type: event.type,
                    target: event.target,
                    message: event.message || 'Unknown error'
                });
                stopRecording();
                
                let errorMessage = 'Hubo un problema con el reconocimiento de voz.';
                let errorTitle = 'Error de grabación';
                
                // Mensajes específicos según el tipo de error
                switch(event.error) {
                    case 'not-allowed':
                        errorMessage = 'Necesitas permitir el acceso al micrófono para usar esta función. Por favor, recarga la página y permite el acceso.';
                        errorTitle = 'Acceso al micrófono denegado';
                        break;
                    case 'no-speech':
                        errorMessage = 'No se detectó ningún audio. Asegúrate de hablar claramente y que tu micrófono esté funcionando.';
                        errorTitle = 'No se detectó voz';
                        break;
                    case 'audio-capture':
                        errorMessage = 'No se pudo acceder al micrófono. Verifica que esté conectado y funcionando correctamente.';
                        errorTitle = 'Error de micrófono';
                        break;
                    case 'network':
                        errorMessage = 'Error de conexión. Verifica tu conexión a internet e inténtalo de nuevo.';
                        errorTitle = 'Error de conexión';
                        break;
                    case 'aborted':
                        errorMessage = 'La grabación fue interrumpida. Puedes intentar de nuevo.';
                        errorTitle = 'Grabación interrumpida';
                        break;
                    case 'service-not-allowed':
                        errorMessage = 'El servicio de reconocimiento de voz no está disponible. Intenta recargar la página.';
                        errorTitle = 'Servicio no disponible';
                        break;
                    default:
                        if (isFirefox) {
                            errorMessage = 'Firefox puede tener limitaciones con el reconocimiento de voz. Te recomendamos usar Chrome o Edge para una mejor experiencia, o escribir tu respuesta manualmente.';
                            errorTitle = 'Limitación del navegador';
                        } else {
                            errorMessage = `Error desconocido: ${event.error || 'Sin detalles'}. Intenta recargar la página o usar otro navegador.`;
                        }
                }
                
                recordingStatus.textContent = 'Error: ' + errorMessage;
                
                // No mostrar alerta para errores menores como 'aborted'
                if (event.error !== 'aborted') {
                    Swal.fire({
                        icon: 'error',
                        title: errorTitle,
                        text: errorMessage,
                        confirmButtonColor: '#009ef7',
                        footer: isFirefox ? '<small>Recomendamos usar Chrome o Edge para una mejor experiencia</small>' : ''
                    });
                }
            };
            
            recognition.onend = function() {
                // Limpiar timer de silencio
                if (silenceTimer) {
                    clearTimeout(silenceTimer);
                    silenceTimer = null;
                }
                
                // Si no fue una parada manual y aún estamos grabando, reiniciar
                if (!isManualStop && isRecording) {
                    console.log('Reiniciando reconocimiento automáticamente');
                    setTimeout(() => {
                        if (isRecording && !isManualStop) {
                            try {
                                recognition.start();
                            } catch (error) {
                                console.error('Error al reiniciar reconocimiento:', error);
                                stopRecording();
                            }
                        }
                    }, 100);
                } else {
                    stopRecording();
                }
            };
            
            // Función para detener la grabación
            function stopRecording() {
                isRecording = false;
                isManualStop = true;
                
                // Limpiar timer de silencio
                if (silenceTimer) {
                    clearTimeout(silenceTimer);
                    silenceTimer = null;
                }
                
                recordBtn.classList.remove('btn-warning');
                recordBtn.classList.add('btn-danger');
                recordBtn.innerHTML = '<i class="fas fa-microphone"></i> Grabar';
                recordingStatus.textContent = 'Grabación completada';
                recordingProgress.style.display = 'none';
                
                if (recognition) {
                    recognition.stop();
                }
                
                // Habilitar botón de evaluación si hay transcripción
                if (transcriptionArea.value.trim()) {
                    evaluateSpeakingBtn.disabled = false;
                }
            }
            
            // Evento de clic para iniciar/detener la grabación
            recordBtn.addEventListener('click', function() {
                if (!isRecording) {
                    // Limpiar transcripción anterior
                    transcriptionArea.value = '';
                    evaluateSpeakingBtn.disabled = true;
                    wordAnalysis.classList.add('d-none');
                    evaluationResults.classList.add('d-none');
                    
                    // Iniciar grabación
                    recognition.start();
                } else {
                    // Detener grabación
                    stopRecording();
                }
            });
            
            // Event listener para evaluar respuesta de speaking
            if (evaluateSpeakingBtn) {
                evaluateSpeakingBtn.addEventListener('click', function() {
                    const userResponse = transcriptionArea.value.trim();
                    const expectedResponse = aiExercise?.content || '';
                    
                    if (!userResponse) {
                        Swal.fire({
                            icon: 'warning',
                            title: 'Respuesta vacía',
                            text: 'Por favor, graba tu respuesta antes de evaluar.',
                            confirmButtonColor: '#009ef7'
                        });
                        return;
                    }
                    
                    // Mostrar loading
                    evaluateSpeakingBtn.disabled = true;
                    evaluateSpeakingBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Evaluando...';
                    
                    // Enviar a la IA para evaluación
                    fetch('{{ route("tutor.evaluate.speaking") }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                        },
                        body: JSON.stringify({
                            answer: userResponse,
                            exercise_content: expectedResponse,
                            level: '{{ $level }}'
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        evaluateSpeakingBtn.disabled = false;
                        evaluateSpeakingBtn.innerHTML = '<i class="fas fa-check me-2"></i>Evaluar mi respuesta';
                        
                        if (data.success && data.evaluation) {
                            // Pasar también el audio_url si está disponible
                            displayEvaluationResults(data.evaluation, data.audio_url);
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error de evaluación',
                                text: data.error || 'No se pudo evaluar tu respuesta. Inténtalo de nuevo.',
                                confirmButtonColor: '#009ef7'
                            });
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        evaluateSpeakingBtn.disabled = false;
                        evaluateSpeakingBtn.innerHTML = '<i class="fas fa-check me-2"></i>Evaluar mi respuesta';
                        
                        Swal.fire({
                            icon: 'error',
                            title: 'Error de conexión',
                            text: 'Hubo un problema al conectar con el servidor. Inténtalo de nuevo.',
                            confirmButtonColor: '#009ef7'
                        });
                    });
                });
            }
            
            // Función para mostrar los resultados de evaluación
            function displayEvaluationResults(evaluation, audioUrl = null) {
                // Mostrar puntuaciones
                let scoresHtml = `
                    <div class="row mb-3">
                        <div class="col-md-3">
                            <div class="text-center">
                                <div class="fs-2 fw-bold text-primary">${evaluation.score || 0}</div>
                                <div class="text-muted">Puntuación General</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-center">
                                <div class="fs-4 fw-bold text-success">${evaluation.pronunciation_score || 0}</div>
                                <div class="text-muted">Pronunciación</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-center">
                                <div class="fs-4 fw-bold text-info">${evaluation.grammar_score || 0}</div>
                                <div class="text-muted">Gramática</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-center">
                                <div class="fs-4 fw-bold text-warning">${evaluation.vocabulary_score || 0}</div>
                                <div class="text-muted">Vocabulario</div>
                            </div>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <div class="mb-3">
                        <h6><i class="fas fa-comment-dots text-primary me-2"></i>Comentarios del tutor:</h6>
                        <p class="mb-0">${evaluation.feedback}</p>
                    </div>
                    
                    ${evaluation.strengths ? `
                    <div class="mb-3">
                        <h6><i class="fas fa-thumbs-up text-success me-2"></i>Fortalezas:</h6>
                        <p class="mb-0 text-success">${evaluation.strengths}</p>
                    </div>
                    ` : ''}
                    
                    ${evaluation.improvements ? `
                    <div class="mb-3">
                        <h6><i class="fas fa-arrow-up text-warning me-2"></i>Áreas de mejora:</h6>
                        <p class="mb-0 text-warning">${evaluation.improvements}</p>
                    </div>
                    ` : ''}
                    
                    ${evaluation.suggestions ? `
                    <div class="mb-3">
                        <h6><i class="fas fa-lightbulb text-info me-2"></i>Sugerencias:</h6>
                        <p class="mb-0 text-info">${evaluation.suggestions}</p>
                    </div>
                    ` : ''}
                `;
                
                evaluationContent.innerHTML = scoresHtml;
                evaluationResults.classList.remove('d-none');
                
                // Configurar audio si está disponible
                if (audioUrl) {
                    setupFeedbackAudio(audioUrl);
                }
                
                // Scroll hacia los resultados
                evaluationResults.scrollIntoView({ behavior: 'smooth' });
            }
            
            // Función para obtener clase de color de la barra de progreso
            function getProgressBarClass(score) {
                if (score >= 80) return 'bg-success';
                if (score >= 60) return 'bg-warning';
                return 'bg-danger';
            }
            
            // Función para configurar audio de feedback
            function setupFeedbackAudio(audioUrl) {
                const audioSection = document.getElementById('feedback-audio-section');
                const playBtn = document.getElementById('play-feedback-btn');
                
                audioSection.classList.remove('d-none');
                
                playBtn.addEventListener('click', function() {
                    playFeedbackAudio(audioUrl, playBtn);
                });
                
                // Intentar reproducir automáticamente
                setTimeout(() => {
                    playFeedbackAudio(audioUrl, playBtn);
                }, 1000);
            }
            
            // Función para reproducir audio de feedback
            function playFeedbackAudio(audioUrl, button) {
                const originalText = button.innerHTML;
                button.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Reproduciendo...';
                button.disabled = true;
                
                const audio = new Audio(audioUrl);
                
                audio.onplay = () => {
                    console.log('Reproduciendo feedback de evaluación');
                };
                
                audio.onended = () => {
                    button.innerHTML = '<i class="fas fa-check me-2"></i>Audio Completado';
                    setTimeout(() => {
                        button.innerHTML = originalText;
                        button.disabled = false;
                    }, 2000);
                };
                
                audio.onerror = () => {
                    button.innerHTML = '<i class="fas fa-exclamation-triangle me-2"></i>Error de Audio';
                    setTimeout(() => {
                        button.innerHTML = originalText;
                        button.disabled = false;
                        // Fallback a speechSynthesis
                        useSpeechSynthesisForFeedback();
                    }, 1500);
                };
                
                audio.play().catch(error => {
                    if (error.name === 'NotAllowedError') {
                        button.innerHTML = '<i class="fas fa-play me-2"></i>Hacer clic para reproducir';
                        button.disabled = false;
                    } else {
                        audio.onerror();
                    }
                });
            }
            
            // Función para reproducir instrucciones
            function playInstructions() {
                const instructionsText = getInstructionsText();
                const button = document.getElementById('listenInstructionsBtn');
                
                if (!instructionsText) {
                    console.error('No se encontraron instrucciones para reproducir');
                    return;
                }
                
                const originalText = button.innerHTML;
                button.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Generando audio...';
                button.disabled = true;
                
                // Intentar generar audio con OpenAI primero
                fetch('{{ route("tutor.generate.instructions.audio") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({
                        text: instructionsText
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.audio_url) {
                        button.innerHTML = '<i class="fas fa-volume-up me-1"></i>Reproduciendo...';
                        
                        const audio = new Audio(data.audio_url);
                        audio.onended = () => {
                            button.innerHTML = originalText;
                            button.disabled = false;
                        };
                        audio.onerror = () => {
                            button.innerHTML = originalText;
                            button.disabled = false;
                            useSpeechSynthesisForInstructions(instructionsText);
                        };
                        audio.play().catch(() => {
                            useSpeechSynthesisForInstructions(instructionsText);
                        });
                    } else {
                        throw new Error('No se pudo generar audio');
                    }
                })
                .catch(error => {
                    console.error('Error al generar audio:', error);
                    button.innerHTML = originalText;
                    button.disabled = false;
                    useSpeechSynthesisForInstructions(instructionsText);
                });
            }
            
            // Función para obtener texto de instrucciones
            function getInstructionsText() {
                const instructionsDiv = document.querySelector('.alert-info div div');
                return instructionsDiv ? instructionsDiv.textContent.trim() : '';
            }
            
            // Función para usar speechSynthesis como fallback para instrucciones
            function useSpeechSynthesisForInstructions(text) {
                if ('speechSynthesis' in window) {
                    const utterance = new SpeechSynthesisUtterance(text);
                    // Determinar idioma según el tipo de ejercicio
                    const exerciseType = '{{ $type }}';
                    if (exerciseType === 'speaking' || exerciseType === 'listening') {
                        // Para ejercicios de speaking y listening, las instrucciones suelen estar en inglés
                        utterance.lang = 'en-US';
                    } else {
                        // Para vocabulario y gramática, usar español
                        utterance.lang = 'es-ES';
                    }
                    utterance.rate = 0.8;
                    speechSynthesis.speak(utterance);
                }
            }
            
            // Función para usar speechSynthesis como fallback para feedback
            function useSpeechSynthesisForFeedback() {
                if ('speechSynthesis' in window) {
                    const utterance = new SpeechSynthesisUtterance('Los comentarios están disponibles en el texto mostrado.');
                    utterance.lang = 'es-ES';
                    utterance.rate = 0.8;
                    speechSynthesis.speak(utterance);
                }
            }
            
            // Función para mostrar botón de reproducción manual del feedback
            function showFeedbackPlayButton(audio) {
                const feedbackAlert = document.querySelector('.alert-info');
                if (feedbackAlert) {
                    const playButton = document.createElement('button');
                    playButton.className = 'btn btn-sm btn-success mt-2';
                    playButton.innerHTML = '<i class="fas fa-play me-1"></i>Reproducir Comentarios (OpenAI)';
                    playButton.onclick = function() {
                        audio.play();
                        playButton.remove();
                    };
                    feedbackAlert.appendChild(playButton);
                }
            }
        } else {
            // Navegador no compatible
            Swal.fire({
                icon: 'warning',
                title: 'Navegador no compatible',
                text: 'Tu navegador no soporta reconocimiento de voz. Usa Chrome, Edge o Firefox.',
                confirmButtonColor: '#009ef7'
            });
        }
    } else {
        console.log('Record button not found or not speaking exercise');
        
        // Fallback: agregar event listener básico si el botón existe
        if (recordBtn) {
            recordBtn.addEventListener('click', function() {
                alert('Funcionalidad de grabación en desarrollo. Por favor, escribe tu respuesta manualmente.');
            });
        }
    }
    
    // Funcionalidad para otros tipos de ejercicios
    const submitAnswerBtn = document.getElementById('submit-answer');
    if (submitAnswerBtn) {
        submitAnswerBtn.addEventListener('click', function() {
            evaluateExercise();
        });
    }
    
    // Botón para limpiar respuesta
    const clearAnswerBtn = document.getElementById('clear-answer');
    if (clearAnswerBtn) {
        clearAnswerBtn.addEventListener('click', function() {
            document.getElementById('user-answer').value = '';
        });
    }
    
    // Botón para finalizar ejercicio
    const finishExerciseBtn = document.getElementById('finish-exercise-btn');
    if (finishExerciseBtn) {
        finishExerciseBtn.addEventListener('click', function() {
            window.location.href = '{{ route("tutor.exercises", ["level" => $level]) }}';
        });
    }
    
    // Botón para escuchar instrucciones
    const listenInstructionsBtn = document.getElementById('listenInstructionsBtn');
    if (listenInstructionsBtn) {
        listenInstructionsBtn.addEventListener('click', function() {
            playInstructions();
        });
    }
    
    // Función para evaluar ejercicios con IA
    async function evaluateExercise() {
        const userAnswer = document.getElementById('user-answer').value.trim();
        const exerciseType = '{{ $type }}';
        
        if (!userAnswer) {
            Swal.fire({
                icon: 'warning',
                title: 'Respuesta vacía',
                text: 'Por favor, escribe tu respuesta antes de enviar.',
                confirmButtonColor: '#009ef7'
            });
            return;
        }
        
        // Cambiar estado del botón
        const originalText = submitAnswerBtn.innerHTML;
        submitAnswerBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Evaluando...';
        submitAnswerBtn.disabled = true;
        
        try {
            // Obtener contenido del ejercicio
            const exerciseContent = getExerciseContent();
            
            // Determinar la ruta según el tipo
            let evaluationRoute = '';
            switch(exerciseType) {
                case 'vocabulary':
                    evaluationRoute = '{{ route("tutor.evaluate.vocabulary") }}';
                    break;
                case 'grammar':
                    evaluationRoute = '{{ route("tutor.evaluate.grammar") }}';
                    break;
                case 'listening':
                    evaluationRoute = '{{ route("tutor.evaluate.listening") }}';
                    break;
                default:
                    throw new Error('Tipo de ejercicio no soportado');
            }
            
            console.log('Enviando datos:', {
                answer: userAnswer,
                exercise_content: exerciseContent,
                level: '{{ $level }}'
            });
            
            const response = await fetch(evaluationRoute, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({
                    answer: userAnswer,
                    exercise_content: exerciseContent,
                    level: '{{ $level }}'
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                displayEvaluationResults(data.evaluation, data.audio_url);
            } else {
                throw new Error(data.message || 'Error al evaluar la respuesta');
            }
            
        } catch (error) {
            console.error('Error:', error);
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'No se pudo evaluar tu respuesta. Inténtalo de nuevo.',
                confirmButtonColor: '#009ef7'
            });
        } finally {
            // Restaurar botón
            submitAnswerBtn.innerHTML = originalText;
            submitAnswerBtn.disabled = false;
        }
    }
    
    // Función para obtener el contenido del ejercicio
    function getExerciseContent() {
        const exerciseDiv = document.querySelector('.border.p-3.rounded.bg-light');
        return exerciseDiv ? exerciseDiv.textContent.trim() : 'Ejercicio no disponible';
    }
    
    // Función para mostrar resultados de evaluación
    function displayEvaluationResults(evaluation, audioUrl) {
        const resultsDiv = document.getElementById('evaluation-results');
        const contentDiv = document.getElementById('evaluation-content');
        
        // Crear HTML para los resultados
        const resultsHTML = `
            <div class="row">
                <div class="col-md-6">
                    <div class="text-center mb-3">
                        <div class="display-4 fw-bold text-primary">${evaluation.score}/100</div>
                        <div class="text-muted">Puntuación obtenida</div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="progress mb-2" style="height: 20px;">
                        <div class="progress-bar ${getProgressBarClass(evaluation.score)}" 
                             style="width: ${evaluation.score}%">${evaluation.score}%</div>
                    </div>
                    <div class="text-center text-muted">Progreso</div>
                </div>
            </div>
            
            <hr>
            
            <div class="mb-3">
                <h6><i class="fas fa-comment-dots text-primary me-2"></i>Comentarios del tutor:</h6>
                <p class="mb-0">${evaluation.feedback}</p>
            </div>
            
            ${evaluation.strengths ? `
            <div class="mb-3">
                <h6><i class="fas fa-thumbs-up text-success me-2"></i>Fortalezas:</h6>
                <p class="mb-0 text-success">${evaluation.strengths}</p>
            </div>
            ` : ''}
            
            ${evaluation.improvements ? `
            <div class="mb-3">
                <h6><i class="fas fa-arrow-up text-warning me-2"></i>Áreas de mejora:</h6>
                <p class="mb-0 text-warning">${evaluation.improvements}</p>
            </div>
            ` : ''}
            
            ${evaluation.suggestions ? `
            <div class="mb-3">
                <h6><i class="fas fa-lightbulb text-info me-2"></i>Sugerencias:</h6>
                <p class="mb-0 text-info">${evaluation.suggestions}</p>
            </div>
            ` : ''}
        `;
        
        contentDiv.innerHTML = resultsHTML;
        resultsDiv.classList.remove('d-none');
        
        // Configurar audio si está disponible
        if (audioUrl) {
            setupFeedbackAudio(audioUrl);
        }
        
        // Scroll hacia los resultados
        resultsDiv.scrollIntoView({ behavior: 'smooth' });
    }
    
    // Función para obtener clase de color de la barra de progreso
    function getProgressBarClass(score) {
        if (score >= 80) return 'bg-success';
        if (score >= 60) return 'bg-warning';
        return 'bg-danger';
    }
    
    // Función para configurar audio de feedback
    function setupFeedbackAudio(audioUrl) {
        const audioSection = document.getElementById('feedback-audio-section');
        const playBtn = document.getElementById('play-feedback-btn');
        
        audioSection.classList.remove('d-none');
        
        playBtn.addEventListener('click', function() {
            playFeedbackAudio(audioUrl, playBtn);
        });
        
        // Intentar reproducir automáticamente
        setTimeout(() => {
            playFeedbackAudio(audioUrl, playBtn);
        }, 1000);
    }
    
    // Función para reproducir audio de feedback
    function playFeedbackAudio(audioUrl, button) {
        const originalText = button.innerHTML;
        button.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Reproduciendo...';
        button.disabled = true;
        
        const audio = new Audio(audioUrl);
        
        audio.onplay = () => {
            console.log('Reproduciendo feedback de evaluación');
        };
        
        audio.onended = () => {
            button.innerHTML = '<i class="fas fa-check me-2"></i>Audio Completado';
            setTimeout(() => {
                button.innerHTML = originalText;
                button.disabled = false;
            }, 2000);
        };
        
        audio.onerror = () => {
            button.innerHTML = '<i class="fas fa-exclamation-triangle me-2"></i>Error de Audio';
            setTimeout(() => {
                button.innerHTML = originalText;
                button.disabled = false;
                // Fallback a speechSynthesis
                useSpeechSynthesisForFeedback();
            }, 1500);
        };
        
        audio.play().catch(error => {
            if (error.name === 'NotAllowedError') {
                button.innerHTML = '<i class="fas fa-play me-2"></i>Hacer clic para reproducir';
                button.disabled = false;
            } else {
                audio.onerror();
            }
        });
    }
    
    // Función para reproducir instrucciones
    function playInstructions() {
        const instructionsText = getInstructionsText();
        const button = document.getElementById('listenInstructionsBtn');
        
        if (!instructionsText) {
            console.error('No se encontraron instrucciones para reproducir');
            return;
        }
        
        const originalText = button.innerHTML;
        button.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Generando audio...';
        button.disabled = true;
        
        // Intentar generar audio con OpenAI primero
        fetch('{{ route("tutor.generate.instructions.audio") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({
                text: instructionsText
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success && data.audio_url) {
                button.innerHTML = '<i class="fas fa-volume-up me-1"></i>Reproduciendo...';
                
                const audio = new Audio(data.audio_url);
                audio.onended = () => {
                    button.innerHTML = originalText;
                    button.disabled = false;
                };
                audio.onerror = () => {
                    button.innerHTML = originalText;
                    button.disabled = false;
                    useSpeechSynthesisForInstructions(instructionsText);
                };
                audio.play().catch(() => {
                    useSpeechSynthesisForInstructions(instructionsText);
                });
            } else {
                throw new Error('No se pudo generar audio');
            }
        })
        .catch(error => {
            console.error('Error al generar audio:', error);
            button.innerHTML = originalText;
            button.disabled = false;
            useSpeechSynthesisForInstructions(instructionsText);
        });
    }
    
    // Función para obtener texto de instrucciones
    function getInstructionsText() {
        const instructionsDiv = document.querySelector('.alert-info div div');
        return instructionsDiv ? instructionsDiv.textContent.trim() : '';
    }
    
    // Función para usar speechSynthesis como fallback para instrucciones
    function useSpeechSynthesisForInstructions(text) {
        if ('speechSynthesis' in window) {
            const utterance = new SpeechSynthesisUtterance(text);
            // Determinar idioma según el tipo de ejercicio
            const exerciseType = '{{ $type }}';
            if (exerciseType === 'speaking' || exerciseType === 'listening') {
                // Para ejercicios de speaking y listening, las instrucciones suelen estar en inglés
                utterance.lang = 'en-US';
            } else {
                // Para vocabulario y gramática, usar español
                utterance.lang = 'es-ES';
            }
            utterance.rate = 0.8;
            speechSynthesis.speak(utterance);
        }
    }
    
    // Función para usar speechSynthesis como fallback para feedback
    function useSpeechSynthesisForFeedback() {
        if ('speechSynthesis' in window) {
            const utterance = new SpeechSynthesisUtterance('Los comentarios están disponibles en el texto mostrado.');
            utterance.lang = 'es-ES';
            utterance.rate = 0.8;
            speechSynthesis.speak(utterance);
        }
    }
    
    // Función para mostrar botón de reproducción manual del feedback
    function showFeedbackPlayButton(audio) {
        const feedbackAlert = document.querySelector('.alert-info');
        if (feedbackAlert) {
            const playButton = document.createElement('button');
            playButton.className = 'btn btn-sm btn-success mt-2';
            playButton.innerHTML = '<i class="fas fa-play me-1"></i>Reproducir Comentarios (OpenAI)';
            playButton.onclick = function() {
                audio.play();
                playButton.remove();
            };
            feedbackAlert.appendChild(playButton);
        }
    }
});
</script>
@endpush
