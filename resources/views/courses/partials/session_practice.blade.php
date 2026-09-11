{{-- Sección de actividad práctica --}}
<!-- DEBUG: Inicio de sección de práctica -->
@php
    \Illuminate\Support\Facades\Log::info('Cargando sección de práctica', [
        'practiceActivity' => $practiceActivity ?? 'no-definido',
        'practiceActivity_tipo' => isset($practiceActivity) ? gettype($practiceActivity) : 'no-definido',
        'sessionId' => $session->id ?? 'no-definido'
    ]);
@endphp
@if(isset($practiceActivity) && !empty($practiceActivity))
    <!-- DEBUG: practiceActivity disponible -->
    <div class="activity-section">
        <h3 class="mb-4">
            <i class="fas fa-tasks me-2 text-success"></i>Actividad Práctica
        </h3>
        
        @php
            $activity = is_string($practiceActivity) 
                ? json_decode($practiceActivity, true) 
                : $practiceActivity;
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
                    
                    @if(isset($activity['question']))
                        <div class="mb-3">
                            <h5>{{ $activity['question'] }}</h5>
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
                            <button class="btn btn-primary" onclick="submitQuizAnswer({{ $session->id }})">
                                <i class="fas fa-check-circle me-1"></i>Enviar Respuesta
                            </button>
                        </div>
                        
                        <div id="quiz-feedback" class="mt-3 d-none">
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle me-1"></i>
                                <span id="feedback-message"></span>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        @endif
    </div>
@endif
