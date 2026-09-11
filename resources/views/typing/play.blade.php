@extends('layout.app')

@section('title', $pageTitle ?? 'TypeMaster AI - Practicando')

@section('content')
<div class="container-fluid px-4">
    <!-- Game Header -->
    <div class="row mb-3">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h2 class="mb-0">
                        <i class="fas fa-keyboard me-2"></i>TypeMaster AI
                        @if(isset($lesson))
                            - {{ $lesson->title }}
                        @endif
                    </h2>
                    <small class="text-muted">
                        Modo: <span class="badge bg-primary">{{ ucfirst($mode ?? 'practice') }}</span>
                        Nivel: <span class="badge bg-secondary">{{ ucfirst($level ?? 'beginner') }}</span>
                        @if(isset($lesson))
                            <span class="badge bg-info">Lección {{ $lesson->lesson_number }}</span>
                        @endif
                    </small>
                </div>
                <div>
                    <button class="btn btn-outline-secondary" onclick="window.location.href='{{ isset($lesson) ? route('typing.lessons') : '/typing' }}'">
                        <i class="fas fa-arrow-left me-2"></i>Volver {{ isset($lesson) ? 'a Lecciones' : '' }}
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Stats Bar -->
    <div class="row mb-4">
        <div class="col-lg-3 col-md-6 mb-2">
            <div class="card border-0 bg-light h-100">
                <div class="card-body text-center py-3">
                    <h4 class="mb-0 text-primary" id="wpm-display">0</h4>
                    <small class="text-muted">WPM</small>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-2">
            <div class="card border-0 bg-light h-100">
                <div class="card-body text-center py-3">
                    <h4 class="mb-0 text-success" id="accuracy-display">100%</h4>
                    <small class="text-muted">Precisión</small>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-2">
            <div class="card border-0 bg-light h-100">
                <div class="card-body text-center py-3">
                    <h4 class="mb-0 text-warning" id="errors-display">0</h4>
                    <small class="text-muted">Errores</small>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-2">
            <div class="card border-0 bg-light h-100">
                <div class="card-body text-center py-3">
                    <h4 class="mb-0 text-info" id="timer-display">0:00</h4>
                    <small class="text-muted">Tiempo</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Game Area -->
    <div class="row">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    @if(isset($lesson))
                    <!-- Lesson Instructions -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <div class="alert alert-info border-0">
                                <div class="d-flex align-items-start">
                                    <i class="fas fa-info-circle me-3 mt-1"></i>
                                    <div class="flex-grow-1">
                                        <h6 class="alert-heading mb-2">Instrucciones</h6>
                                        <p class="mb-2">{{ $lesson->instructions }}</p>
                                        
                                        <div class="row mb-3">
                                            <div class="col-md-6">
                                                <strong>🎯 Objetivo WPM:</strong> 
                                                <span class="badge bg-primary">{{ $adjustedTargetWpm ?? $lesson->target_wpm }}</span>
                                                @if(isset($adjustedTargetWpm) && $adjustedTargetWpm != $lesson->target_wpm)
                                                    <small class="text-muted">(adaptado para tu edad)</small>
                                                @endif
                                            </div>
                                            <div class="col-md-6">
                                                <strong>🎯 Precisión:</strong> 
                                                <span class="badge bg-success">{{ $lesson->target_accuracy }}%</span>
                                            </div>
                                        </div>
                                        
                                        @if(count($lesson->focus_keys) > 0)
                                        <div class="mt-3">
                                            <strong>Teclas de enfoque:</strong>
                                            <div class="d-flex flex-wrap gap-1 mt-2">
                                                @foreach($lesson->focus_keys as $key)
                                                <span class="badge bg-primary">{{ strtoupper($key) }}</span>
                                                @endforeach
                                            </div>
                                        </div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    @endif
                    
                    <!-- Text Display Area -->
                    <div class="text-display-area mb-4">
                        <div id="loading-text" class="text-center py-5">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Generando texto...</span>
                            </div>
                            <p class="mt-3 text-muted">Generando texto con IA...</p>
                        </div>
                        
                        <div id="text-display" class="d-none">
                            <div class="text-to-type" id="text-content"></div>
                        </div>
                    </div>

                    <!-- Input Area -->
                    <div class="input-area">
                        <div class="row align-items-center">
                            <div class="col-md-8">
                                <div class="mb-2">
                                    <small class="text-muted">
                                        <i class="fas fa-info-circle me-1"></i>
                                        Puedes usar <kbd>Backspace</kbd> para corregir errores
                                    </small>
                                </div>
                                <textarea 
                                    id="typing-input" 
                                    class="form-control form-control-lg" 
                                    placeholder="Haz clic aquí y comienza a escribir..."
                                    rows="3"
                                    disabled></textarea>
                            </div>
                            <div class="col-md-4 text-center">
                                <div class="d-grid gap-2">
                                    <button id="start-btn" class="btn btn-success btn-lg" onclick="startGame()">
                                        <i class="fas fa-play me-2"></i>Comenzar
                                    </button>
                                    <button id="pause-btn" class="btn btn-warning btn-lg d-none" onclick="pauseGame()">
                                        <i class="fas fa-pause me-2"></i>Pausar
                                    </button>
                                    <button id="reset-btn" class="btn btn-secondary" onclick="resetGame()">
                                        <i class="fas fa-redo me-2"></i>Reiniciar
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Progress Bar -->
    <div class="row mt-3">
        <div class="col-12">
            <div class="card border-0 bg-light">
                <div class="card-body py-3">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <small class="text-muted">Progreso</small>
                        <small class="text-muted" id="progress-text">0%</small>
                    </div>
                    <div class="progress" style="height: 8px;">
                        <div class="progress-bar bg-primary" id="progress-bar" role="progressbar" style="width: 0%"></div>
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
            <div class="modal-header border-0">
                <h5 class="modal-title">
                    <i class="fas fa-trophy me-2 text-warning"></i>¡Sesión Completada!
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row text-center">
                    <div class="col-md-3 mb-3">
                        <div class="p-3 bg-light rounded">
                            <h3 class="text-primary mb-0" id="final-wpm">0</h3>
                            <small class="text-muted">WPM</small>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="p-3 bg-light rounded">
                            <h3 class="text-success mb-0" id="final-accuracy">0%</h3>
                            <small class="text-muted">Precisión</small>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="p-3 bg-light rounded">
                            <h3 class="text-warning mb-0" id="final-errors">0</h3>
                            <small class="text-muted">Errores</small>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="p-3 bg-light rounded">
                            <h3 class="text-info mb-0" id="final-time">0:00</h3>
                            <small class="text-muted">Tiempo</small>
                        </div>
                    </div>
                </div>
                
                <div class="text-center mt-4">
                    <div class="alert alert-success" id="xp-earned-alert" style="display: none;">
                        <h5><i class="fas fa-star me-2"></i>¡Has ganado <span id="xp-earned">0</span> XP!</h5>
                        <p class="mb-0">Total XP: <span id="total-xp">0</span></p>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 justify-content-center">
                <button type="button" class="btn btn-primary" onclick="playAgain()">
                    <i class="fas fa-play me-2"></i>Jugar Otra Vez
                </button>
                <button type="button" class="btn btn-secondary" onclick="goHome()">
                    <i class="fas fa-home me-2"></i>Inicio
                </button>
            </div>
        </div>
    </div>
</div>

<style>
.text-display-area {
    min-height: 200px;
    background: #f8f9fa;
    border-radius: 10px;
    padding: 20px;
    font-family: 'Courier New', monospace;
}

.text-to-type {
    font-size: 1.2rem;
    line-height: 1.8;
    letter-spacing: 0.5px;
}

.char-correct {
    background-color: #d4edda;
    color: #155724;
}

.char-incorrect {
    background-color: #f8d7da;
    color: #721c24;
}

.char-current {
    background-color: #007bff;
    color: white;
    animation: blink 1s infinite;
}

@keyframes blink {
    50% { opacity: 0.5; }
}

#typing-input {
    border-radius: 10px;
    border: 2px solid #e9ecef;
    transition: border-color 0.3s ease;
}

#typing-input:focus {
    border-color: #007bff;
    box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
}

.modal-content {
    border-radius: 15px;
    border: none;
}
</style>

<script>
// Game State Variables
let gameState = {
    isPlaying: false,
    isPaused: false,
    startTime: null,
    currentText: '',
    currentPosition: 0,
    errors: 0,
    totalKeystrokes: 0,
    correctKeystrokes: 0
};

// Lesson data (if applicable)
const lessonData = @if(isset($lesson)) {
    id: {{ $lesson->id }},
    title: "{{ $lesson->title }}",
    content: @json($lesson->content),
    target_wpm: {{ $adjustedTargetWpm ?? $lesson->target_wpm }},
    base_target_wpm: {{ $lesson->target_wpm }},
    target_accuracy: {{ $lesson->target_accuracy }},
    focus_keys: @json($lesson->focus_keys)
} @else null @endif;

// UI Elements
const typingInput = document.getElementById('typing-input');
const textContent = document.getElementById('text-content');
const startBtn = document.getElementById('start-btn');
const pauseBtn = document.getElementById('pause-btn');
const resetBtn = document.getElementById('reset-btn');

// Load text when page loads
document.addEventListener('DOMContentLoaded', function() {
    generateText();
});

function generateText() {
    // Si estamos en modo lección, usar el contenido de la lección
    if (lessonData) {
        document.getElementById('loading-text').classList.add('d-none');
        document.getElementById('text-display').classList.remove('d-none');
        
        gameState.currentText = lessonData.content;
        displayText(lessonData.content);
        return;
    }
    
    // Si es práctica libre, generar texto
    const level = new URLSearchParams(window.location.search).get('level') || 'beginner';
    const theme = new URLSearchParams(window.location.search).get('theme') || 'general';
    
    fetch('/typing/generate-text', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({
            level: level,
            theme: theme,
            length: 'medium'
        })
    })
    .then(response => response.json())
    .then(data => {
        document.getElementById('loading-text').classList.add('d-none');
        document.getElementById('text-display').classList.remove('d-none');
        
        if (data.success) {
            gameState.currentText = data.text;
            displayText(data.text);
        } else {
            gameState.currentText = data.fallback_text;
            displayText(data.fallback_text);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        document.getElementById('loading-text').innerHTML = '<p class="text-danger">Error al cargar el texto</p>';
    });
}

function displayText(text) {
    textContent.innerHTML = text.split('').map((char, index) => 
        `<span id="char-${index}" class="char${index === 0 ? ' char-current' : ''}">${char}</span>`
    ).join('');
}

function startGame() {
    gameState.isPlaying = true;
    gameState.isPaused = false;
    gameState.startTime = Date.now();
    gameState.currentPosition = 0;
    gameState.errors = 0;
    gameState.totalKeystrokes = 0;
    gameState.correctKeystrokes = 0;
    
    typingInput.disabled = false;
    typingInput.focus();
    typingInput.value = '';
    
    startBtn.classList.add('d-none');
    pauseBtn.classList.remove('d-none');
    
    updateCurrentCharacter();
    startTimer();
}

function pauseGame() {
    gameState.isPaused = !gameState.isPaused;
    
    if (gameState.isPaused) {
        typingInput.disabled = true;
        pauseBtn.innerHTML = '<i class="fas fa-play me-2"></i>Continuar';
        pauseBtn.classList.remove('btn-warning');
        pauseBtn.classList.add('btn-success');
    } else {
        typingInput.disabled = false;
        typingInput.focus();
        pauseBtn.innerHTML = '<i class="fas fa-pause me-2"></i>Pausar';
        pauseBtn.classList.remove('btn-success');
        pauseBtn.classList.add('btn-warning');
    }
}

function resetGame() {
    gameState.isPlaying = false;
    gameState.isPaused = false;
    gameState.currentPosition = 0;
    gameState.errors = 0;
    gameState.totalKeystrokes = 0;
    gameState.correctKeystrokes = 0;
    gameState.startTime = null;
    
    typingInput.disabled = true;
    typingInput.value = '';
    
    startBtn.classList.remove('d-none');
    pauseBtn.classList.add('d-none');
    pauseBtn.innerHTML = '<i class="fas fa-pause me-2"></i>Pausar';
    pauseBtn.classList.remove('btn-success');
    pauseBtn.classList.add('btn-warning');
    
    // Reset character highlighting
    document.querySelectorAll('.char').forEach(char => {
        char.className = 'char';
    });
    
    // Reset first character as current
    const firstChar = document.getElementById('char-0');
    if (firstChar) {
        firstChar.classList.add('char-current');
    }
    
    updateStats();
    updateProgress();
}

function updateCurrentCharacter() {
    const typed = typingInput.value;
    
    document.querySelectorAll('.char').forEach((char, index) => {
        char.classList.remove('char-current', 'char-correct', 'char-incorrect');
        
        if (index < typed.length) {
            // Already typed
            if (typed[index] === gameState.currentText[index]) {
                char.classList.add('char-correct');
            } else {
                char.classList.add('char-incorrect');
            }
        } else if (index === typed.length) {
            // Current character (next to type)
            char.classList.add('char-current');
        }
    });
}

function updateStats() {
    const timeElapsed = gameState.startTime ? (Date.now() - gameState.startTime) / 1000 : 0;
    const minutes = timeElapsed / 60;
    
    // Use correct keystrokes for WPM calculation (more accurate)
    const wpm = minutes > 0 ? Math.round((gameState.correctKeystrokes / 5) / minutes) : 0;
    
    // Calculate accuracy based on current typed text
    const typedLength = typingInput.value.length;
    const accuracy = typedLength > 0 ? Math.round((gameState.correctKeystrokes / typedLength) * 100) : 100;
    
    document.getElementById('wpm-display').textContent = wpm;
    document.getElementById('accuracy-display').textContent = accuracy + '%';
    document.getElementById('errors-display').textContent = gameState.errors;
    document.getElementById('timer-display').textContent = formatTime(timeElapsed);
}

function updateProgress() {
    const typed = typingInput.value;
    const progress = (typed.length / gameState.currentText.length) * 100;
    document.getElementById('progress-bar').style.width = progress + '%';
    document.getElementById('progress-text').textContent = Math.round(progress) + '%';
}

function formatTime(seconds) {
    const mins = Math.floor(seconds / 60);
    const secs = Math.floor(seconds % 60);
    return `${mins}:${secs.toString().padStart(2, '0')}`;
}

function startTimer() {
    const timer = setInterval(() => {
        if (!gameState.isPlaying || gameState.isPaused) {
            clearInterval(timer);
            return;
        }
        
        updateStats();
        
        if (gameState.currentPosition >= gameState.currentText.length) {
            clearInterval(timer);
            endGame();
        }
    }, 1000);
}

function endGame() {
    gameState.isPlaying = false;
    typingInput.disabled = true;
    
    const timeElapsed = (Date.now() - gameState.startTime) / 1000;
    const minutes = timeElapsed / 60;
    const wpm = Math.round((gameState.correctKeystrokes / 5) / minutes);
    const accuracy = Math.round((gameState.correctKeystrokes / gameState.totalKeystrokes) * 100);
    
    // Show results modal
    document.getElementById('final-wpm').textContent = wpm;
    document.getElementById('final-accuracy').textContent = accuracy + '%';
    document.getElementById('final-errors').textContent = gameState.errors;
    document.getElementById('final-time').textContent = formatTime(timeElapsed);
    
    // Save session
    saveSession(wpm, accuracy, gameState.errors, timeElapsed);
    
    const modal = new bootstrap.Modal(document.getElementById('resultsModal'));
    modal.show();
}

function saveSession(wpm, accuracy, errors, timeSeconds) {
    const level = new URLSearchParams(window.location.search).get('level') || 'beginner';
    
    // Determinar URL y datos según el modo
    let url, requestData;
    
    if (lessonData) {
        // Modo lección
        url = '/typing/lesson/save-progress';
        requestData = {
            lesson_id: lessonData.id,
            wpm: wpm,
            accuracy: accuracy,
            time_taken: Math.round(timeSeconds),
            errors_count: errors,
            total_keystrokes: gameState.totalKeystrokes,
            correct_keystrokes: gameState.correctKeystrokes
        };
    } else {
        // Modo práctica libre
        url = '/typing/save-session';
        requestData = {
            wpm: wpm,
            accuracy: accuracy,
            errors: errors,
            time_seconds: Math.round(timeSeconds),
            text_used: gameState.currentText,
            level: level
        };
    }
    
    fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify(requestData)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            document.getElementById('xp-earned').textContent = data.xp_earned;
            document.getElementById('total-xp').textContent = data.total_xp;
            document.getElementById('xp-earned-alert').style.display = 'block';
            
            // Actualizar el badge de XP en el header
            updateXPBadge(data.total_xp, data.new_level);
            
            // Mostrar notificación si subió de nivel
            if (data.level_up) {
                showLevelUpNotification(data.new_level);
            }
        }
    })
    .catch(error => {
        console.error('Error saving session:', error);
    });
}

function updateXPBadge(totalXP, newLevel) {
    // Actualizar el nivel en el avatar
    const userLevel = document.querySelector('.user-level');
    if (userLevel) {
        userLevel.textContent = `Nv. ${newLevel}`;
    }
    
    // Actualizar el XP en la barra de progreso
    const xpCurrent = document.querySelector('.xp-current');
    if (xpCurrent) {
        xpCurrent.textContent = totalXP;
    }
    
    // Calcular progreso del nivel actual (esto es una aproximación)
    const xpForThisLevel = totalXP % 100; // Simplificado
    const progressBar = document.querySelector('.xp-progress-fill');
    if (progressBar) {
        progressBar.style.width = `${xpForThisLevel}%`;
    }
    
    // Efecto visual de actualización
    const xpContainer = document.querySelector('.xp-progress-container');
    if (xpContainer) {
        xpContainer.style.transform = 'scale(1.05)';
        setTimeout(() => {
            xpContainer.style.transform = 'scale(1)';
        }, 200);
    }
}

function showLevelUpNotification(newLevel) {
    // Crear notificación temporal de subida de nivel
    const notification = document.createElement('div');
    notification.innerHTML = `
        <div class="alert alert-success alert-dismissible fade show position-fixed" 
             style="top: 100px; right: 20px; z-index: 9999; min-width: 300px;">
            <i class="fas fa-star me-2"></i>
            <strong>¡Felicidades!</strong> Has alcanzado el nivel ${newLevel}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
    document.body.appendChild(notification);
    
    // Auto-remove after 5 seconds
    setTimeout(() => {
        const alert = notification.querySelector('.alert');
        if (alert) {
            alert.remove();
        }
    }, 5000);
}

function playAgain() {
    const modal = bootstrap.Modal.getInstance(document.getElementById('resultsModal'));
    modal.hide();
    generateText();
    resetGame();
}

function goHome() {
    window.location.href = '/typing';
}

// Handle typing input
typingInput.addEventListener('input', function(e) {
    if (!gameState.isPlaying || gameState.isPaused) return;
    
    const typed = e.target.value;
    const typedLength = typed.length;
    
    // Update current position based on typed text length
    gameState.currentPosition = typedLength;
    
    // Count total keystrokes (only forward progress)
    if (typedLength > gameState.totalKeystrokes) {
        gameState.totalKeystrokes = typedLength;
    }
    
    // Calculate correct keystrokes and errors
    gameState.correctKeystrokes = 0;
    gameState.errors = 0;
    
    for (let i = 0; i < typedLength; i++) {
        if (i < gameState.currentText.length) {
            if (typed[i] === gameState.currentText[i]) {
                gameState.correctKeystrokes++;
            } else {
                gameState.errors++;
            }
        }
    }
    
    updateCurrentCharacter();
    updateProgress();
    
    // Check if completed
    if (typedLength >= gameState.currentText.length) {
        // Verify all characters are correct
        let allCorrect = true;
        for (let i = 0; i < gameState.currentText.length; i++) {
            if (typed[i] !== gameState.currentText[i]) {
                allCorrect = false;
                break;
            }
        }
        
        if (allCorrect) {
            setTimeout(() => endGame(), 500); // Small delay to show completion
        }
    }
});

// Allow backspace and navigation keys for corrections
typingInput.addEventListener('keydown', function(e) {
    if (!gameState.isPlaying || gameState.isPaused) return;
    
    // Allow most keys, only prevent some problematic ones
    const blockedKeys = ['Tab', 'Escape', 'F1', 'F2', 'F3', 'F4', 'F5', 'F6', 'F7', 'F8', 'F9', 'F10', 'F11', 'F12'];
    
    if (blockedKeys.includes(e.key)) {
        e.preventDefault();
    }
    
    // Prevent typing beyond the text length
    if (e.target.value.length >= gameState.currentText.length && !['Backspace', 'Delete', 'ArrowLeft', 'ArrowRight'].includes(e.key)) {
        if (e.key.length === 1) { // Only block printable characters
            e.preventDefault();
        }
    }
});
</script>
@endsection
