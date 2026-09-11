@extends('layout.app')

@section('title','Ejercicios Personalizados')

@section('content')
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />
<style>
    .card { border: none; box-shadow: 0 0.5rem 1.5rem 0.5rem rgba(0, 0, 0, 0.075); }
    .card-header { background-color: #fff; border-bottom: 1px solid #eff2f5; }
    .btn-primary { background-color: #009ef7; border-color: #009ef7; }
    .btn-primary:hover { background-color: #0095e8; border-color: #0095e8; }
    .btn-success { background-color: #50cd89; border-color: #50cd89; }
    .level-badge { font-size: 1.5rem; padding: 0.5rem 1rem; }
    .exercise-card { transition: transform 0.2s; }
    .exercise-card:hover { transform: translateY(-5px); }
    .category-icon { font-size: 2rem; margin-bottom: 1rem; }
</style>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h2 class="card-title fw-bold"><i class="fa-solid fa-graduation-cap me-2"></i>Ejercicios Personalizados</h2>
        <div class="d-flex align-items-center gap-3">
            <button id="tutorRecommendationsBtn" class="btn btn-success btn-sm">
                <i class="fas fa-robot me-2"></i>Recomendaciones del Tutor
            </button>
            <span class="badge bg-primary level-badge">Nivel {{ $level }}</span>
        </div>
    </div>
    <div class="card-body">
        <p class="text-muted mb-4">Estos ejercicios están diseñados específicamente para tu nivel de inglés. Practica regularmente para mejorar tus habilidades.</p>
        
        <!-- Speaking Feature (Highlighted) - Moved to top -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card border-primary h-100 exercise-card" style="border-width: 2px;">
                    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                        <h3 class="mb-0"><i class="fas fa-microphone me-2"></i>Conversación (Speaking)</h3>
                        <span class="badge bg-white text-primary">Recomendado</span>
                    </div>
                    <div class="card-body p-4">
                        <div class="row">
                            <div class="col-md-4">
                                <!-- Stats & Score Badge -->
                                <div class="mb-3 bg-light py-2 px-3 rounded-3">
                                    <div class="d-flex justify-content-between">
                                        <span class="text-muted">Último ejercicio:</span>
                                        <span class="fw-bold">{{ isset($scores['speaking_last']) ? $scores['speaking_last'] : '0' }} pts</span>
                                    </div>
                                    <div class="d-flex justify-content-between">
                                        <span class="text-muted">Media:</span>
                                        <span class="fw-bold">{{ round($scores['speaking'] ?? 0) }} pts</span>
                                    </div>
                                </div>
                                
                                <a href="{{ route('tutor.practice', ['type' => 'speaking', 'level' => $level]) }}" class="btn btn-primary btn-lg w-100">
                                    <i class="fas fa-microphone me-2"></i> Iniciar conversación
                                </a>
                            </div>
                            <div class="col-md-8">
                                <h4 class="text-primary mb-3">Practica tu pronunciación con IA</h4>
                                <div class="row">
                                    <div class="col-md-6">
                                        <ul class="list-unstyled">
                                            <li class="mb-2"><i class="fas fa-volume-up text-success me-2"></i> Escucha instrucciones de audio</li>
                                            <li class="mb-2"><i class="fas fa-microphone text-info me-2"></i> Practica con tu micrófono</li>
                                        </ul>
                                    </div>
                                    <div class="col-md-6">
                                        <ul class="list-unstyled">
                                            <li class="mb-2"><i class="fas fa-chart-line text-warning me-2"></i> Análisis instantáneo de pronunciación</li>
                                            <li class="mb-2"><i class="fas fa-star text-primary me-2"></i> Puntuación palabra por palabra</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Other exercises in a row -->
        <div class="row">
            <!-- Vocabulary Exercises -->
            <div class="col-md-4 mb-4">
                <div class="card h-100 exercise-card">
                    <div class="card-body text-center p-4">
                        <div class="category-icon text-primary">
                            <i class="fas fa-book"></i>
                        </div>
                        <h3 class="mb-3">Vocabulario</h3>
                        
                        <!-- Stats & Score Badge -->
                        <div class="mb-3 bg-light py-2 px-3 rounded-3">
                            <div class="d-flex justify-content-between">
                                <span class="text-muted">Último ejercicio:</span>
                                <span class="fw-bold">{{ isset($scores['vocabulary_last']) ? $scores['vocabulary_last'] : '0' }} pts</span>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span class="text-muted">Media:</span>
                                <span class="fw-bold">{{ round($scores['vocabulary'] ?? 0) }} pts</span>
                            </div>
                        </div>
                        
                        <ul class="list-group list-group-flush mb-4">
                            @foreach($exercises['vocabulary'] ?? [] as $exercise)
                                <li class="list-group-item border-0 d-flex align-items-center">
                                    <i class="fas fa-check-circle text-success me-2"></i>
                                    {{ $exercise }}
                                </li>
                            @endforeach
                        </ul>
                        <a href="{{ route('tutor.practice', ['type' => 'vocabulary', 'level' => $level]) }}" class="btn btn-primary w-100">
                            <i class="fas fa-play me-2"></i> Iniciar práctica
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Grammar Exercises -->
            <div class="col-md-4 mb-4">
                <div class="card h-100 exercise-card">
                    <div class="card-body text-center p-4">
                        <div class="category-icon text-danger">
                            <i class="fas fa-pencil-alt"></i>
                        </div>
                        <h3 class="mb-3">Gramática</h3>
                        
                        <!-- Stats & Score Badge -->
                        <div class="mb-3 bg-light py-2 px-3 rounded-3">
                            <div class="d-flex justify-content-between">
                                <span class="text-muted">Último ejercicio:</span>
                                <span class="fw-bold">{{ isset($scores['grammar_last']) ? $scores['grammar_last'] : '0' }} pts</span>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span class="text-muted">Media:</span>
                                <span class="fw-bold">{{ round($scores['grammar'] ?? 0) }} pts</span>
                            </div>
                        </div>
                        
                        <ul class="list-group list-group-flush mb-4">
                            @foreach($exercises['grammar'] ?? [] as $exercise)
                                <li class="list-group-item border-0 d-flex align-items-center">
                                    <i class="fas fa-check-circle text-success me-2"></i>
                                    {{ $exercise }}
                                </li>
                            @endforeach
                        </ul>
                        <a href="{{ route('tutor.practice', ['type' => 'grammar', 'level' => $level]) }}" class="btn btn-primary w-100">
                            <i class="fas fa-play me-2"></i> Iniciar práctica
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Listening Exercises -->
            <div class="col-md-4 mb-4">
                <div class="card h-100 exercise-card">
                    <div class="card-body text-center p-4">
                        <div class="category-icon text-warning">
                            <i class="fas fa-headphones"></i>
                        </div>
                        <h3 class="mb-3">Comprensión Auditiva</h3>
                        
                        <!-- Stats & Score Badge -->
                        <div class="mb-3 bg-light py-2 px-3 rounded-3">
                            <div class="d-flex justify-content-between">
                                <span class="text-muted">Último ejercicio:</span>
                                <span class="fw-bold">{{ isset($scores['listening_last']) ? $scores['listening_last'] : '0' }} pts</span>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span class="text-muted">Media:</span>
                                <span class="fw-bold">{{ round($scores['listening'] ?? 0) }} pts</span>
                            </div>
                        </div>
                        
                        <ul class="list-group list-group-flush mb-4">
                            @foreach($exercises['listening'] ?? [] as $exercise)
                                <li class="list-group-item border-0 d-flex align-items-center">
                                    <i class="fas fa-check-circle text-success me-2"></i>
                                    {{ $exercise }}
                                </li>
                            @endforeach
                        </ul>
                        <a href="{{ route('tutor.practice', ['type' => 'listening', 'level' => $level]) }}" class="btn btn-primary w-100">
                            <i class="fas fa-play me-2"></i> Iniciar práctica
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- AI Assistant Section -->
        <div class="card mt-4">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="me-4">
                        <i class="fas fa-robot text-primary" style="font-size: 3rem;"></i>
                    </div>
                    <div>
                        <h4>Asistente de Práctica</h4>
                        <p class="mb-3">Nuestro asistente de IA puede generar ejercicios personalizados adicionales basados en tu nivel y necesidades.</p>
                        <div class="d-flex gap-2">
                            <a href="{{ route('tutor.generate.ai', ['level' => $level]) }}" class="btn btn-success">
                                <i class="fas fa-magic me-2"></i> Generar ejercicio personalizado
                            </a>
                            <a href="{{ route('tutor.exercise.history') }}" class="btn btn-outline-primary">
                                <i class="fas fa-history me-2"></i> Ver historial de ejercicios
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Progress Tracking -->
        <div class="card mt-4">
            <div class="card-header">
                <h4 class="card-title mb-0">Tu progreso</h4>
            </div>
            <div class="card-body">
                <div class="row">
                    @php
                        // Calcular porcentajes basados en las puntuaciones reales del usuario
                        $scores = session('section_scores', [
                            'vocabulary' => 0,
                            'grammar' => 0,
                            'speaking' => 0,
                            'listening' => 0
                        ]);
                        
                        // Calcular porcentajes (las puntuaciones van del 0-100)
                        $vocabularyPercent = isset($scores['vocabulary']) ? min($scores['vocabulary'], 100) : 0;
                        $grammarPercent = isset($scores['grammar']) ? min($scores['grammar'], 100) : 0;
                        $speakingPercent = isset($scores['speaking']) ? min($scores['speaking'], 100) : 0;
                        $listeningPercent = isset($scores['listening']) ? min($scores['listening'], 100) : 0;
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
        
        <!-- Recomendaciones personalizadas -->
        <div class="card mt-4">
            <div class="card-header">
                <h4 class="card-title mb-0">Recomendaciones personalizadas</h4>
            </div>
            <div class="card-body">
                @php
                    // Determinar la sección con menor puntuación para recomendaciones
                    $minScore = min($scores);
                    $weakestSection = array_search($minScore, $scores);
                    
                    $recommendations = [
                        'vocabulary' => [
                            'Utiliza tarjetas de memoria para aprender nuevo vocabulario',
                            'Etiqueta objetos en tu casa con su nombre en inglés',
                            'Lee artículos simples en inglés sobre temas que te interesen'
                        ],
                        'grammar' => [
                            'Practica con ejercicios de completar frases',
                            'Escribe un diario corto diario en inglés',
                            'Identifica estructuras gramaticales en textos simples'
                        ],
                        'speaking' => [
                            'Practica conversaciones básicas frente al espejo',
                            'Graba tu voz leyendo textos en inglés',
                            'Intenta pensar en inglés durante 5 minutos al día'
                        ],
                        'listening' => [
                            'Escucha podcasts cortos en inglés',
                            'Ve videos con subtítulos en inglés',
                            'Practica con canciones sencillas en inglés'
                        ]
                    ];
                @endphp
                
                <div class="alert alert-info">
                    <h5><i class="fas fa-lightbulb me-2"></i>Área de mejora: {{ ucfirst($weakestSection) }}</h5>
                    <p>Basado en tu evaluación, te recomendamos enfocarte en mejorar esta área:</p>
                    <ul>
                        @foreach($recommendations[$weakestSection] as $tip)
                            <li>{{ $tip }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const tutorBtn = document.getElementById('tutorRecommendationsBtn');
    
    if (tutorBtn) {
        tutorBtn.addEventListener('click', function() {
            generateTutorRecommendations();
        });
    }
    
    async function generateTutorRecommendations() {
        // Cambiar el estado del botón
        const originalText = tutorBtn.innerHTML;
        tutorBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Generando recomendaciones...';
        tutorBtn.disabled = true;
        
        try {
            const response = await fetch('{{ route("tutor.recommendations") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({
                    level: '{{ $level }}',
                    scores: @json($scores ?? []),
                    section_scores: @json($section_scores ?? [])
                })
            });
            
            const data = await response.json();
            
            if (data.success && data.audio_url) {
                // Mostrar las recomendaciones y reproducir el audio
                await showRecommendationsWithAudio(data.recommendations, data.audio_url);
            } else {
                throw new Error(data.message || 'Error al generar recomendaciones');
            }
            
        } catch (error) {
            console.error('Error:', error);
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'No se pudieron generar las recomendaciones. Inténtalo de nuevo.',
                confirmButtonColor: '#009ef7'
            });
        } finally {
            // Restaurar el botón
            tutorBtn.innerHTML = originalText;
            tutorBtn.disabled = false;
        }
    }
    
    async function showRecommendationsWithAudio(recommendations, audioUrl) {
        // Mostrar las recomendaciones en un modal
        const result = await Swal.fire({
            title: '<i class="fas fa-robot text-success me-2"></i>Recomendaciones del Tutor',
            html: `
                <div class="text-start">
                    <div class="alert alert-info mb-3">
                        <i class="fas fa-volume-up me-2"></i>
                        <strong>Escucha las recomendaciones personalizadas del tutor</strong>
                    </div>
                    <div class="recommendations-content">
                        ${recommendations}
                    </div>
                    <div class="mt-3">
                        <button id="playRecommendationsBtn" class="btn btn-success btn-sm">
                            <i class="fas fa-play me-2"></i>Reproducir Audio (OpenAI)
                        </button>
                    </div>
                </div>
            `,
            showCloseButton: true,
            showConfirmButton: false,
            width: '600px',
            didOpen: () => {
                // Configurar el botón de reproducción
                const playBtn = document.getElementById('playRecommendationsBtn');
                if (playBtn) {
                    playBtn.addEventListener('click', function() {
                        playRecommendationsAudio(audioUrl, playBtn);
                    });
                }
                
                // Intentar reproducir automáticamente
                setTimeout(() => {
                    playRecommendationsAudio(audioUrl, playBtn);
                }, 500);
            }
        });
    }
    
    function playRecommendationsAudio(audioUrl, button) {
        if (!audioUrl) {
            console.error('No hay URL de audio disponible');
            return;
        }
        
        const originalText = button.innerHTML;
        button.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Reproduciendo...';
        button.disabled = true;
        
        const audio = new Audio(audioUrl);
        
        audio.onloadstart = () => {
            console.log('Cargando audio de recomendaciones...');
        };
        
        audio.oncanplay = () => {
            console.log('Audio listo para reproducir');
        };
        
        audio.onplay = () => {
            console.log('Reproduciendo recomendaciones del tutor');
        };
        
        audio.onended = () => {
            console.log('Audio de recomendaciones terminado');
            button.innerHTML = '<i class="fas fa-check me-2"></i>Audio Completado';
            setTimeout(() => {
                button.innerHTML = originalText;
                button.disabled = false;
            }, 2000);
        };
        
        audio.onerror = (error) => {
            console.error('Error al reproducir audio:', error);
            button.innerHTML = '<i class="fas fa-exclamation-triangle me-2"></i>Error de Audio';
            
            // Fallback a speechSynthesis
            setTimeout(() => {
                button.innerHTML = originalText;
                button.disabled = false;
                useSpeechSynthesisForRecommendations();
            }, 1500);
        };
        
        // Intentar reproducir
        audio.play().catch(error => {
            if (error.name === 'NotAllowedError') {
                console.log('Autoplay bloqueado, mostrando botón manual');
                button.innerHTML = '<i class="fas fa-play me-2"></i>Hacer clic para reproducir';
                button.disabled = false;
                
                // Crear event listener temporal para el clic manual
                const playManually = () => {
                    audio.play().catch(err => {
                        console.error('Error en reproducción manual:', err);
                        useSpeechSynthesisForRecommendations();
                    });
                    button.removeEventListener('click', playManually);
                };
                
                button.addEventListener('click', playManually);
            } else {
                console.error('Error de reproducción:', error);
                audio.onerror(error);
            }
        });
    }
    
    function useSpeechSynthesisForRecommendations() {
        console.log('Usando speechSynthesis como fallback para recomendaciones');
        
        if ('speechSynthesis' in window) {
            const utterance = new SpeechSynthesisUtterance('Las recomendaciones del tutor están disponibles en el texto mostrado.');
            utterance.lang = 'es-ES';
            utterance.rate = 0.9;
            utterance.pitch = 1;
            
            speechSynthesis.speak(utterance);
        }
    }
});
</script>
@endpush
