@extends('layout.app')

@section('title', $pageTitle ?? 'TypeMaster AI - Lección')

@section('content')
<div class="container-fluid px-4">
    <!-- Lesson Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-0 bg-gradient-primary text-white">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <h1 class="display-6 fw-bold mb-2">
                                <span class="badge bg-white text-primary me-3">Lección {{ $lesson->lesson_number }}</span>
                                {{ $lesson->title }}
                            </h1>
                            <p class="lead mb-3">{{ $lesson->description }}</p>
                            <div class="d-flex flex-wrap gap-3">
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-tachometer-alt me-2"></i>
                                    <span>Objetivo: {{ $lesson->target_wpm }} WPM</span>
                                </div>
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-bullseye me-2"></i>
                                    <span>Precisión: {{ $lesson->target_accuracy }}%</span>
                                </div>
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-clock me-2"></i>
                                    <span>~{{ round($lesson->estimated_duration / 60) }} minutos</span>
                                </div>
                            </div>
                        </div>
                        <div class="text-center">
                            <div class="bg-white bg-opacity-20 rounded-3 p-3">
                                <h4 class="mb-0">
                                    @if($userProgress)
                                        @if($userProgress->completed_at)
                                            <i class="fas fa-trophy text-warning"></i> Completada
                                        @else
                                            <i class="fas fa-play-circle"></i> En Progreso
                                        @endif
                                    @else
                                        <i class="fas fa-rocket"></i> Nueva
                                    @endif
                                </h4>
                                @if($userProgress)
                                    <small>Intentos: {{ $userProgress->attempts }}</small>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Instructions and Progress -->
    <div class="row mb-4">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h5 class="card-title">
                        <i class="fas fa-info-circle text-primary me-2"></i>Instrucciones
                    </h5>
                    <p class="card-text">{{ $lesson->instructions }}</p>
                    
                    @if(count($lesson->focus_keys) > 0)
                    <div class="mb-3">
                        <h6 class="mb-2">Teclas de enfoque:</h6>
                        <div class="d-flex flex-wrap gap-2">
                            @foreach($lesson->focus_keys as $key)
                            <span class="badge bg-primary text-white px-3 py-2 fs-6">{{ strtoupper($key) }}</span>
                            @endforeach
                        </div>
                    </div>
                    @endif
                </div>
            </div>
        </div>
        
        <div class="col-lg-4">
            @if($userProgress)
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h5 class="card-title">
                        <i class="fas fa-chart-line text-success me-2"></i>Tu Progreso
                    </h5>
                    <div class="row text-center">
                        <div class="col-6">
                            <div class="border-end">
                                <h4 class="text-primary mb-0">{{ $userProgress->best_wpm ?? 0 }}</h4>
                                <small class="text-muted">Mejor WPM</small>
                            </div>
                        </div>
                        <div class="col-6">
                            <h4 class="text-success mb-0">{{ round($userProgress->best_accuracy ?? 0, 1) }}%</h4>
                            <small class="text-muted">Mejor Precisión</small>
                        </div>
                    </div>
                    
                    @if($userProgress->completed_at)
                    <div class="alert alert-success mt-3 mb-0">
                        <i class="fas fa-check-circle me-2"></i>
                        ¡Lección completada el {{ $userProgress->completed_at->format('d/m/Y') }}!
                    </div>
                    @endif
                </div>
            </div>
            @endif
        </div>
    </div>

    <!-- Typing Practice Area -->
    <div class="row">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <!-- Stats Header -->
                    <div class="row mb-3">
                        <div class="col-lg-3 col-sm-6 mb-2">
                            <div class="text-center p-2 bg-light rounded">
                                <h4 class="mb-0 text-primary" id="wpm-display">0</h4>
                                <small class="text-muted">WPM</small>
                            </div>
                        </div>
                        <div class="col-lg-3 col-sm-6 mb-2">
                            <div class="text-center p-2 bg-light rounded">
                                <h4 class="mb-0 text-success" id="accuracy-display">100%</h4>
                                <small class="text-muted">Precisión</small>
                            </div>
                        </div>
                        <div class="col-lg-3 col-sm-6 mb-2">
                            <div class="text-center p-2 bg-light rounded">
                                <h4 class="mb-0 text-warning" id="time-display">00:00</h4>
                                <small class="text-muted">Tiempo</small>
                            </div>
                        </div>
                        <div class="col-lg-3 col-sm-6 mb-2">
                            <div class="text-center p-2 bg-light rounded">
                                <h4 class="mb-0 text-danger" id="errors-display">0</h4>
                                <small class="text-muted">Errores</small>
                            </div>
                        </div>
                    </div>

                    <!-- Text Display -->
                    <div class="mb-4">
                        <div id="text-container" class="p-4 border rounded bg-light" style="font-family: 'Courier New', monospace; font-size: 1.2rem; line-height: 1.8;">
                            {{ $lesson->content }}
                        </div>
                    </div>

                    <!-- Input Area -->
                    <div class="mb-3">
                        <textarea id="typing-input" 
                                  class="form-control" 
                                  rows="4" 
                                  placeholder="Haz clic aquí y comienza a escribir..."
                                  style="font-family: 'Courier New', monospace; font-size: 1.1rem; resize: none;"></textarea>
                    </div>

                    <!-- Control Buttons -->
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <button id="start-btn" class="btn btn-success btn-lg me-2">
                                <i class="fas fa-play me-2"></i>Comenzar
                            </button>
                            <button id="reset-btn" class="btn btn-outline-secondary">
                                <i class="fas fa-redo me-2"></i>Reiniciar
                            </button>
                        </div>
                        
                        <div>
                            <a href="{{ route('typing.lessons') }}" class="btn btn-outline-primary">
                                <i class="fas fa-arrow-left me-2"></i>Volver a Lecciones
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Results Modal -->
<div class="modal fade" id="resultsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">
                    <i class="fas fa-chart-bar me-2"></i>Resultados de la Lección
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row text-center mb-4">
                    <div class="col-md-3">
                        <div class="p-3">
                            <h3 class="text-primary mb-0" id="final-wpm">0</h3>
                            <small class="text-muted">WPM</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="p-3">
                            <h3 class="text-success mb-0" id="final-accuracy">0%</h3>
                            <small class="text-muted">Precisión</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="p-3">
                            <h3 class="text-warning mb-0" id="final-time">0:00</h3>
                            <small class="text-muted">Tiempo</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="p-3">
                            <h3 class="text-info mb-0" id="final-xp">0</h3>
                            <small class="text-muted">XP Ganado</small>
                        </div>
                    </div>
                </div>
                
                <div id="completion-message" class="alert" style="display: none;"></div>
                
                <div class="text-center">
                    <div class="mb-3">
                        <h6>Objetivos de la lección:</h6>
                        <div class="row">
                            <div class="col-6">
                                <span class="badge bg-primary">{{ $lesson->target_wpm }} WPM</span>
                            </div>
                            <div class="col-6">
                                <span class="badge bg-success">{{ $lesson->target_accuracy }}% Precisión</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cerrar</button>
                <button type="button" class="btn btn-primary" onclick="restartLesson()">Intentar de Nuevo</button>
                <button type="button" class="btn btn-success" onclick="nextLesson()" id="next-lesson-btn" style="display: none;">
                    Siguiente Lección <i class="fas fa-arrow-right ms-2"></i>
                </button>
            </div>
        </div>
    </div>
</div>

<style>
.text-typed {
    background-color: #d4edda;
}
.text-correct {
    background-color: #d4edda;
}
.text-error {
    background-color: #f8d7da;
}
.text-current {
    background-color: #fff3cd;
    animation: blink 1s infinite;
}

@keyframes blink {
    0%, 50% { opacity: 1; }
    51%, 100% { opacity: 0.3; }
}

#typing-input:focus {
    box-shadow: 0 0 0 0.2rem rgba(0,123,255,.25);
}
</style>

<script>
class LessonTypingGame {
    constructor() {
        this.text = @json($lesson->content);
        this.lessonId = {{ $lesson->id }};
        this.targetWpm = {{ $lesson->target_wpm }};
        this.targetAccuracy = {{ $lesson->target_accuracy }};
        
        this.isActive = false;
        this.startTime = null;
        this.currentPosition = 0;
        this.errorsCount = 0;
        this.correctChars = 0;
        
        this.init();
    }
    
    init() {
        this.textContainer = document.getElementById('text-container');
        this.typingInput = document.getElementById('typing-input');
        this.startBtn = document.getElementById('start-btn');
        this.resetBtn = document.getElementById('reset-btn');
        
        this.setupEventListeners();
        this.renderText();
        this.updateStats();
    }
    
    setupEventListeners() {
        this.startBtn.addEventListener('click', () => this.startGame());
        this.resetBtn.addEventListener('click', () => this.resetGame());
        this.typingInput.addEventListener('input', (e) => this.handleInput(e));
        this.typingInput.addEventListener('keydown', (e) => this.handleKeydown(e));
    }
    
    startGame() {
        this.isActive = true;
        this.startTime = Date.now();
        this.typingInput.focus();
        this.typingInput.disabled = false;
        this.startBtn.style.display = 'none';
        this.resetBtn.textContent = 'Terminar';
        
        this.timer = setInterval(() => this.updateStats(), 100);
    }
    
    resetGame() {
        this.isActive = false;
        this.startTime = null;
        this.currentPosition = 0;
        this.errorsCount = 0;
        this.correctChars = 0;
        
        this.typingInput.value = '';
        this.typingInput.disabled = true;
        this.startBtn.style.display = 'inline-block';
        this.resetBtn.textContent = 'Reiniciar';
        
        if (this.timer) {
            clearInterval(this.timer);
        }
        
        this.renderText();
        this.updateStats();
    }
    
    handleInput(e) {
        if (!this.isActive) return;
        
        const inputText = e.target.value;
        const inputLength = inputText.length;
        
        // Check if user has completed the text
        if (inputLength >= this.text.length) {
            this.finishGame();
            return;
        }
        
        this.currentPosition = inputLength;
        this.renderText();
        this.updateStats();
    }
    
    handleKeydown(e) {
        if (!this.isActive) return;
        
        // Allow backspace and arrow keys
        if (['Backspace', 'ArrowLeft', 'ArrowRight', 'Delete'].includes(e.key)) {
            return;
        }
    }
    
    renderText() {
        const inputText = this.typingInput.value;
        let html = '';
        
        for (let i = 0; i < this.text.length; i++) {
            const char = this.text[i];
            
            if (i < inputText.length) {
                if (inputText[i] === char) {
                    html += `<span class="text-correct">${char === ' ' ? '&nbsp;' : char}</span>`;
                } else {
                    html += `<span class="text-error">${char === ' ' ? '&nbsp;' : char}</span>`;
                }
            } else if (i === inputText.length) {
                html += `<span class="text-current">${char === ' ' ? '&nbsp;' : char}</span>`;
            } else {
                html += char === ' ' ? '&nbsp;' : char;
            }
        }
        
        this.textContainer.innerHTML = html;
    }
    
    updateStats() {
        const currentTime = this.isActive && this.startTime ? (Date.now() - this.startTime) / 1000 : 0;
        const inputText = this.typingInput.value;
        
        // Calculate WPM
        const wordsTyped = inputText.length / 5;
        const timeInMinutes = currentTime / 60;
        const wpm = timeInMinutes > 0 ? Math.round(wordsTyped / timeInMinutes) : 0;
        
        // Calculate accuracy
        let correctChars = 0;
        let totalChars = inputText.length;
        
        for (let i = 0; i < inputText.length && i < this.text.length; i++) {
            if (inputText[i] === this.text[i]) {
                correctChars++;
            }
        }
        
        const accuracy = totalChars > 0 ? Math.round((correctChars / totalChars) * 100) : 100;
        const errors = totalChars - correctChars;
        
        // Update displays
        document.getElementById('wpm-display').textContent = wpm;
        document.getElementById('accuracy-display').textContent = accuracy + '%';
        document.getElementById('time-display').textContent = this.formatTime(currentTime);
        document.getElementById('errors-display').textContent = errors;
        
        // Store current stats
        this.currentWpm = wpm;
        this.currentAccuracy = accuracy;
        this.currentTime = currentTime;
        this.errorsCount = errors;
    }
    
    formatTime(seconds) {
        const mins = Math.floor(seconds / 60);
        const secs = Math.floor(seconds % 60);
        return `${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
    }
    
    finishGame() {
        this.isActive = false;
        clearInterval(this.timer);
        
        this.typingInput.disabled = true;
        this.saveProgress();
    }
    
    async saveProgress() {
        try {
            const response = await fetch('{{ route("typing.lesson.save.progress") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify({
                    lesson_id: this.lessonId,
                    wpm: this.currentWpm,
                    accuracy: this.currentAccuracy,
                    time_taken: Math.round(this.currentTime),
                    errors_count: this.errorsCount
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.showResults(data);
            } else {
                console.error('Error saving progress:', data.error);
            }
        } catch (error) {
            console.error('Error saving progress:', error);
        }
    }
    
    showResults(data) {
        document.getElementById('final-wpm').textContent = this.currentWpm;
        document.getElementById('final-accuracy').textContent = this.currentAccuracy + '%';
        document.getElementById('final-time').textContent = this.formatTime(this.currentTime);
        document.getElementById('final-xp').textContent = data.xp_gained || 0;
        
        const messageDiv = document.getElementById('completion-message');
        
        if (data.completed) {
            messageDiv.className = 'alert alert-success';
            messageDiv.innerHTML = '<i class="fas fa-trophy me-2"></i>¡Felicitaciones! Has completado esta lección.';
            messageDiv.style.display = 'block';
            
            // Show next lesson button if available
            const nextBtn = document.getElementById('next-lesson-btn');
            nextBtn.style.display = 'inline-block';
        } else {
            messageDiv.className = 'alert alert-warning';
            messageDiv.innerHTML = '<i class="fas fa-info-circle me-2"></i>Sigue practicando para alcanzar los objetivos de la lección.';
            messageDiv.style.display = 'block';
        }
        
        const modal = new bootstrap.Modal(document.getElementById('resultsModal'));
        modal.show();
    }
}

function restartLesson() {
    const modal = bootstrap.Modal.getInstance(document.getElementById('resultsModal'));
    modal.hide();
    game.resetGame();
}

function nextLesson() {
    window.location.href = '{{ route("typing.lessons") }}';
}

// Initialize game when page loads
let game;
document.addEventListener('DOMContentLoaded', function() {
    game = new LessonTypingGame();
});
</script>

@endsection
