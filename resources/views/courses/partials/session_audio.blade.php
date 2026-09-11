{{-- Sección de audio de la sesión --}}
<!-- DEBUG: Inicio de sección de audio -->
@php
    \Illuminate\Support\Facades\Log::info('Verificando condiciones de audio', [
        'audioScript_isset' => isset($audioScript) ? 'si' : 'no',
        'audioScript_empty' => isset($audioScript) ? (empty($audioScript) ? 'si' : 'no') : 'N/A',
        'audioScript_value' => $audioScript ?? 'no-definido',
        'hasAudio' => $hasAudio ?? 'no-definido'
    ]);
@endphp

{{-- Siempre mostrar la sección de audio, independientemente del script --}}
    @php
        $audioPath = "courses/{$userId}/{$course->id}/audio/session_{$session->id}.mp3";
        // Log para debug
        \Illuminate\Support\Facades\Log::info('Cargando sección de audio', [
            'audioScript' => $audioScript,
            'audioPath' => $audioPath,
            'hasAudio' => $hasAudio ?? false,
            'userId' => $userId ?? 'no-definido',
            'courseId' => $course->id ?? 'no-definido',
            'sessionId' => $session->id ?? 'no-definido'
        ]);
    @endphp
    <!-- DEBUG: audioScript disponible: {{ strlen($audioScript) }} caracteres -->
    
    <div class="audio-player card mb-4">
        <div class="card-body">
            <h5 class="card-title">
                <i class="fas fa-volume-up me-2 text-primary"></i>Audio de la Sesión
            </h5>
            <div class="alert alert-light border mb-3">
                <p class="mb-0">{{ $audioScript }}</p>
            </div>
            
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    @if($hasAudio)
                        <audio id="audio-player-{{ $session->id }}" class="d-none">
                            <source src="{{ Storage::disk('public')->url($audioPath) }}" type="audio/mpeg">
                            Tu navegador no soporta la reproducción de audio.
                        </audio>
                        
                        <button class="btn btn-outline-primary btn-sm me-2" 
                                onclick="playAudioScript('{{ $session->id }}')">
                            <i class="fas fa-play me-1"></i>Reproducir Audio
                        </button>
                        
                        <small class="text-muted">
                            <i class="fas fa-check-circle text-success me-1"></i>
                            <span id="audio-status-{{ $session->id }}">
                                Audio generado correctamente
                            </span>
                        </small>
                    @else
                        <small class="text-muted">
                            <i class="fas fa-clock text-warning me-1"></i>
                            <span id="audio-status-{{ $session->id }}">
                                Audio pendiente de generar
                            </span>
                        </small>
                    @endif
                </div>
                
                @if(!$hasAudio)
                    <button type="button" class="btn btn-sm btn-outline-success" 
                            onclick="generateSessionResources({{ $course->id }}, {{ $session->id }}, 'audio')" 
                            id="generate-audio-btn-{{ $session->id }}">
                        <i class="fas fa-microphone me-1"></i>Generar Audio
                    </button>
                @endif
            </div>
        </div>
    </div>
