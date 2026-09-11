@extends('layout.app')

@section('title', 'Grammar Runner - Juegos de Inglés')

@section('content')
<div class="container-fluid px-4 py-4">
    <!-- Header del Juego -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-0 bg-gradient-success text-white shadow-lg">
                <div class="card-body p-4">
                    <div class="row align-items-center">
                        <div class="col-lg-8">
                            <h1 class="display-5 fw-bold mb-3">
                                <i class="fas fa-running me-3"></i>Grammar Runner 🏃‍♂️
                            </h1>
                            <p class="lead mb-3">
                                ¡Corre y salta eligiendo la gramática correcta! Evita obstáculos respondiendo preguntas de gramática inglesa mientras avanzas.
                            </p>
                            <div class="d-flex flex-wrap gap-3">
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-heart me-2"></i>
                                    <span>3 Vidas</span>
                                </div>
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-graduation-cap me-2"></i>
                                    <span>Gramática Interactiva</span>
                                </div>
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-tachometer-alt me-2"></i>
                                    <span>Velocidad Creciente</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-4 text-center">
                            <div class="display-1">
                                <i class="fas fa-book-reader"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Configuración del Juego -->
    <div class="row mb-4" id="game-config">
        <div class="col-lg-8 mx-auto">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-success text-white">
                    <h4 class="mb-0"><i class="fas fa-cog me-2"></i>Configuración del Juego</h4>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label"><i class="fas fa-signal me-2"></i>Nivel de Dificultad</label>
                            <select class="form-select" id="difficulty-level">
                                <option value="basic">🟢 Básico (Presente simple, articulos)</option>
                                <option value="intermediate" selected>🟡 Intermedio (Todos los tiempos)</option>
                                <option value="advanced">🔴 Avanzado (Estructuras complejas)</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label"><i class="fas fa-tachometer-alt me-2"></i>Velocidad del Juego</label>
                            <select class="form-select" id="game-speed">
                                <option value="slow">Lenta (Principiante)</option>
                                <option value="normal" selected>Normal</option>
                                <option value="fast">Rápida (Experto)</option>
                            </select>
                        </div>
                    </div>
                    <div class="row mt-3">
                        <div class="col-12 text-center">
                            <button id="start-game-btn" class="btn btn-success btn-lg px-5">
                                <i class="fas fa-play me-2"></i>¡Comenzar a Correr!
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Área de Juego -->
    <div class="row" id="game-area" style="display: none;">
        <div class="col-12">
            <div class="card border-0 shadow-lg">
                <!-- Header del Juego -->
                <div class="card-header bg-dark text-white">
                    <div class="row align-items-center">
                        <div class="col-md-3">
                            <div class="d-flex align-items-center">
                                <i class="fas fa-trophy text-warning me-2"></i>
                                <span class="fw-bold">Distancia: <span id="distance">0</span>m</span>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="d-flex align-items-center">
                                <i class="fas fa-heart text-danger me-2"></i>
                                <span id="lives-display">❤️❤️❤️</span>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="d-flex align-items-center">
                                <i class="fas fa-check-circle text-success me-2"></i>
                                <span class="fw-bold">Correctas: <span id="correct-count">0</span></span>
                            </div>
                        </div>
                        <div class="col-md-3 text-end">
                            <button class="btn btn-sm btn-warning" onclick="pauseGame()">
                                <i class="fas fa-pause"></i> Pausa
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Canvas del Juego -->
                <div class="card-body p-0 position-relative" style="background: linear-gradient(180deg, #87CEEB 0%, #90EE90 100%); height: 600px; overflow: hidden;">
                    <!-- Canvas Principal -->
                    <canvas id="game-canvas" width="1200" height="600"></canvas>
                    
                    <!-- Pregunta Flotante -->
                    <div id="question-panel" class="position-absolute" style="display: none; top: 20px; left: 50%; transform: translateX(-50%); z-index: 100; width: 80%; max-width: 700px;">
                        <div class="card border-0 shadow-lg">
                            <div class="card-body p-4 bg-white">
                                <h5 class="mb-3 text-center" id="question-text">Pregunta aparecerá aquí</h5>
                                <div class="d-grid gap-2" id="options-container">
                                    <!-- Las opciones se generarán dinámicamente -->
                                </div>
                                <div class="mt-3 text-center">
                                    <small class="text-muted">⏱️ <span id="question-timer">10</span> segundos restantes</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Footer con estadísticas -->
                <div class="card-footer bg-dark text-white">
                    <div class="row text-center">
                        <div class="col-3">
                            <small class="text-muted">Precisión</small>
                            <div class="fw-bold fs-5" id="accuracy">100%</div>
                        </div>
                        <div class="col-3">
                            <small class="text-muted">Errores</small>
                            <div class="fw-bold fs-5" id="errors-count">0</div>
                        </div>
                        <div class="col-3">
                            <small class="text-muted">Velocidad</small>
                            <div class="fw-bold fs-5" id="game-speed-display">Normal</div>
                        </div>
                        <div class="col-3">
                            <small class="text-muted">Tiempo</small>
                            <div class="fw-bold fs-5" id="timer">0:00</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal de Pausa -->
<div class="modal fade" id="pauseModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title"><i class="fas fa-pause me-2"></i>Juego Pausado</h5>
            </div>
            <div class="modal-body text-center py-4">
                <p class="lead">El juego está en pausa</p>
                <div class="d-grid gap-3">
                    <button class="btn btn-success btn-lg" onclick="resumeGame()">
                        <i class="fas fa-play me-2"></i>Continuar
                    </button>
                    <button class="btn btn-secondary" onclick="restartGame()">
                        <i class="fas fa-redo me-2"></i>Reiniciar
                    </button>
                    <button class="btn btn-danger" onclick="exitGame()">
                        <i class="fas fa-times me-2"></i>Salir
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal de Game Over -->
<div class="modal fade" id="gameOverModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="fas fa-flag-checkered me-2"></i>Carrera Terminada</h5>
            </div>
            <div class="modal-body text-center py-4">
                <h2 class="display-4 mb-3">🏁</h2>
                <h3 class="mb-4">¡Fin del Juego!</h3>
                
                <div class="row mb-4">
                    <div class="col-6">
                        <div class="card bg-light">
                            <div class="card-body">
                                <h5 class="text-muted">Distancia Recorrida</h5>
                                <h2 class="text-primary" id="final-distance">0m</h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="card bg-light">
                            <div class="card-body">
                                <h5 class="text-muted">Precisión</h5>
                                <h2 class="text-success" id="final-accuracy">0%</h2>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mb-4">
                    <p class="lead mb-2">XP Ganado: <strong class="text-warning" id="xp-earned">0</strong></p>
                    <p class="text-muted">Respuestas correctas: <span id="final-correct">0</span></p>
                </div>

                <div class="d-grid gap-2">
                    <button class="btn btn-success btn-lg" onclick="restartGame()">
                        <i class="fas fa-redo me-2"></i>Jugar de Nuevo
                    </button>
                    <button class="btn btn-secondary" onclick="exitGame()">
                        <i class="fas fa-home me-2"></i>Volver al Menú
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
#game-canvas {
    display: block;
    width: 100%;
    height: 100%;
}

.option-btn {
    font-size: 1.1rem;
    padding: 15px;
    border: 2px solid #dee2e6;
    transition: all 0.3s;
}

.option-btn:hover {
    transform: scale(1.05);
    border-color: #28a745;
    background-color: #e8f5e9;
}

.option-btn.correct {
    background-color: #28a745 !important;
    color: white !important;
    border-color: #28a745 !important;
}

.option-btn.wrong {
    background-color: #dc3545 !important;
    color: white !important;
    border-color: #dc3545 !important;
}

@keyframes shake {
    0%, 100% { transform: translateX(0); }
    25% { transform: translateX(-10px); }
    75% { transform: translateX(10px); }
}

.shake {
    animation: shake 0.5s;
}
</style>

<script>
let gameState = {
    isPlaying: false,
    isPaused: false,
    distance: 0,
    lives: 3,
    correctAnswers: 0,
    totalQuestions: 0,
    errors: 0,
    difficulty: 'intermediate',
    speed: 2,
    startTime: null,
    gameTimer: null,
    questionTimer: null,
    questions: [],
    currentQuestion: null,
    questionTimeLeft: 10,
    
    // Configuración de velocidades
    speedConfig: {
        slow: 2,
        normal: 3,
        fast: 4
    },
    
    // Canvas y Runner
    canvas: null,
    ctx: null,
    runner: {
        x: 100,
        y: 0,
        width: 40,
        height: 60,
        velocityY: 0,
        isJumping: false,
        groundY: 500
    },
    obstacles: [],
    lastObstacleTime: 0,
    obstacleInterval: 2000,
    
    // Animación
    animationId: null
};

document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('start-game-btn').addEventListener('click', startGame);
    loadQuestions();
});

async function loadQuestions() {
    try {
        const response = await fetch('{{ route("games.grammar-runner.questions") }}');
        const data = await response.json();
        gameState.questions = data.questions;
        console.log(`Preguntas cargadas: ${gameState.questions.length}`);
    } catch (error) {
        console.error('Error cargando preguntas:', error);
        gameState.questions = [
            {
                question: "She ___ to school every day.",
                options: ["go", "goes", "going", "gone"],
                correct: 1,
                level: "basic",
                category: "verb_tense"
            }
        ];
    }
}

function startGame() {
    // Configuración
    gameState.difficulty = document.getElementById('difficulty-level').value;
    const speedSetting = document.getElementById('game-speed').value;
    gameState.speed = gameState.speedConfig[speedSetting];
    
    // Reset estado
    gameState.isPlaying = true;
    gameState.isPaused = false;
    gameState.distance = 0;
    gameState.lives = 3;
    gameState.correctAnswers = 0;
    gameState.totalQuestions = 0;
    gameState.errors = 0;
    gameState.startTime = Date.now();
    gameState.obstacles = [];
    gameState.currentQuestion = null;
    
    // Filtrar preguntas por dificultad
    const allQuestions = [...gameState.questions];
    let filteredQuestions = allQuestions.filter(q => q.level === gameState.difficulty);
    
    // Si no hay preguntas del nivel seleccionado, usar todas las disponibles
    if (filteredQuestions.length === 0) {
        console.warn(`No hay preguntas para nivel ${gameState.difficulty}, usando todas las disponibles`);
        filteredQuestions = allQuestions;
    }
    
    gameState.questions = filteredQuestions;
    console.log(`Preguntas filtradas: ${gameState.questions.length} para nivel ${gameState.difficulty}`);
    
    // Validar que hay preguntas antes de iniciar
    if (gameState.questions.length === 0) {
        alert('Error: No hay preguntas disponibles para este juego. Por favor, contacta al administrador.');
        return;
    }
    
    // UI
    document.getElementById('game-config').style.display = 'none';
    document.getElementById('game-area').style.display = 'block';
    document.getElementById('game-speed-display').textContent = speedSetting.charAt(0).toUpperCase() + speedSetting.slice(1);
    
    // Canvas setup
    gameState.canvas = document.getElementById('game-canvas');
    gameState.ctx = gameState.canvas.getContext('2d');
    
    // Reset runner
    gameState.runner.y = gameState.runner.groundY;
    gameState.runner.velocityY = 0;
    gameState.runner.isJumping = false;
    
    updateDisplay();
    playSound('game_start');
    
    // Iniciar game loop
    gameLoop();
    
    // Timer del juego
    gameState.gameTimer = setInterval(updateTimer, 1000);
    
    // Primera pregunta después de 3 segundos
    setTimeout(() => {
        if (gameState.isPlaying && !gameState.isPaused) {
            showQuestion();
        }
    }, 3000);
}

function gameLoop() {
    if (!gameState.isPlaying) return;
    
    if (!gameState.isPaused) {
        // Limpiar canvas
        gameState.ctx.clearRect(0, 0, gameState.canvas.width, gameState.canvas.height);
        
        // Dibujar escenario
        drawBackground();
        drawGround();
        
        // Actualizar y dibujar runner
        updateRunner();
        drawRunner();
        
        // Actualizar y dibujar obstáculos
        updateObstacles();
        drawObstacles();
        
        // Generar nuevos obstáculos
        generateObstacles();
        
        // Incrementar distancia
        gameState.distance += gameState.speed * 0.1;
        document.getElementById('distance').textContent = Math.floor(gameState.distance);
    }
    
    gameState.animationId = requestAnimationFrame(gameLoop);
}

function drawBackground() {
    const ctx = gameState.ctx;
    // Cielo
    const gradient = ctx.createLinearGradient(0, 0, 0, 400);
    gradient.addColorStop(0, '#87CEEB');
    gradient.addColorStop(1, '#E0F6FF');
    ctx.fillStyle = gradient;
    ctx.fillRect(0, 0, gameState.canvas.width, 400);
    
    // Sol
    ctx.fillStyle = '#FFD700';
    ctx.beginPath();
    ctx.arc(1000, 100, 50, 0, Math.PI * 2);
    ctx.fill();
}

function drawGround() {
    const ctx = gameState.ctx;
    ctx.fillStyle = '#228B22';
    ctx.fillRect(0, 540, gameState.canvas.width, 60);
    
    // Línea de césped
    ctx.fillStyle = '#32CD32';
    for (let i = 0; i < gameState.canvas.width; i += 20) {
        ctx.fillRect(i, 540, 10, 5);
    }
}

function updateRunner() {
    const runner = gameState.runner;
    
    // Aplicar gravedad
    runner.velocityY += 0.8;
    runner.y += runner.velocityY;
    
    // Colisión con el suelo
    if (runner.y >= runner.groundY) {
        runner.y = runner.groundY;
        runner.velocityY = 0;
        runner.isJumping = false;
    }
}

function drawRunner() {
    const ctx = gameState.ctx;
    const runner = gameState.runner;
    
    // Cuerpo (rectángulo)
    ctx.fillStyle = '#FF6347';
    ctx.fillRect(runner.x, runner.y, runner.width, runner.height);
    
    // Cabeza
    ctx.fillStyle = '#FFD700';
    ctx.beginPath();
    ctx.arc(runner.x + runner.width/2, runner.y - 10, 15, 0, Math.PI * 2);
    ctx.fill();
    
    // Piernas (animación simple)
    ctx.fillStyle = '#4169E1';
    const legOffset = Math.sin(Date.now() / 100) * 5;
    ctx.fillRect(runner.x + 5, runner.y + runner.height, 12, 20 + legOffset);
    ctx.fillRect(runner.x + 23, runner.y + runner.height, 12, 20 - legOffset);
}

function jump() {
    const runner = gameState.runner;
    if (!runner.isJumping && runner.y === runner.groundY) {
        runner.velocityY = -15;
        runner.isJumping = true;
        playSound('success');
    }
}

function generateObstacles() {
    const now = Date.now();
    if (now - gameState.lastObstacleTime > gameState.obstacleInterval) {
        gameState.obstacles.push({
            x: gameState.canvas.width,
            y: 500,
            width: 40,
            height: 40,
            type: Math.random() > 0.5 ? 'box' : 'spike'
        });
        gameState.lastObstacleTime = now;
        
        // Aumentar dificultad gradualmente
        if (gameState.obstacleInterval > 1200) {
            gameState.obstacleInterval -= 50;
        }
    }
}

function updateObstacles() {
    const runner = gameState.runner;
    
    for (let i = gameState.obstacles.length - 1; i >= 0; i--) {
        const obstacle = gameState.obstacles[i];
        obstacle.x -= gameState.speed * 2;
        
        // Verificar colisión
        if (checkCollision(runner, obstacle)) {
            if (!gameState.currentQuestion) {
                loseLife();
                gameState.obstacles.splice(i, 1);
            }
        }
        
        // Eliminar obstáculos fuera de pantalla
        if (obstacle.x + obstacle.width < 0) {
            gameState.obstacles.splice(i, 1);
        }
    }
}

function drawObstacles() {
    const ctx = gameState.ctx;
    
    gameState.obstacles.forEach(obstacle => {
        if (obstacle.type === 'box') {
            ctx.fillStyle = '#8B4513';
            ctx.fillRect(obstacle.x, obstacle.y, obstacle.width, obstacle.height);
            ctx.strokeStyle = '#654321';
            ctx.lineWidth = 2;
            ctx.strokeRect(obstacle.x, obstacle.y, obstacle.width, obstacle.height);
        } else {
            // Spike
            ctx.fillStyle = '#DC143C';
            ctx.beginPath();
            ctx.moveTo(obstacle.x + obstacle.width/2, obstacle.y);
            ctx.lineTo(obstacle.x, obstacle.y + obstacle.height);
            ctx.lineTo(obstacle.x + obstacle.width, obstacle.y + obstacle.height);
            ctx.closePath();
            ctx.fill();
        }
    });
}

function checkCollision(runner, obstacle) {
    return runner.x < obstacle.x + obstacle.width &&
           runner.x + runner.width > obstacle.x &&
           runner.y + runner.height > obstacle.y &&
           runner.y < obstacle.y + obstacle.height;
}

function showQuestion() {
    if (gameState.questions.length === 0) return;
    
    gameState.isPaused = true;
    gameState.totalQuestions++;
    gameState.questionTimeLeft = 10;
    
    // Seleccionar pregunta aleatoria
    const randomIndex = Math.floor(Math.random() * gameState.questions.length);
    gameState.currentQuestion = gameState.questions[randomIndex];
    
    // Mostrar panel
    document.getElementById('question-text').textContent = gameState.currentQuestion.question;
    
    const optionsContainer = document.getElementById('options-container');
    optionsContainer.innerHTML = '';
    
    gameState.currentQuestion.options.forEach((option, index) => {
        const btn = document.createElement('button');
        btn.className = 'btn btn-outline-primary option-btn';
        btn.textContent = option;
        btn.onclick = () => selectAnswer(index);
        optionsContainer.appendChild(btn);
    });
    
    document.getElementById('question-panel').style.display = 'block';
    
    // Timer de pregunta
    updateQuestionTimer();
    gameState.questionTimer = setInterval(() => {
        gameState.questionTimeLeft--;
        updateQuestionTimer();
        
        if (gameState.questionTimeLeft <= 0) {
            clearInterval(gameState.questionTimer);
            selectAnswer(-1); // Tiempo agotado
        }
    }, 1000);
}

function updateQuestionTimer() {
    document.getElementById('question-timer').textContent = gameState.questionTimeLeft;
}

function selectAnswer(selectedIndex) {
    clearInterval(gameState.questionTimer);
    
    const buttons = document.querySelectorAll('.option-btn');
    const correctIndex = gameState.currentQuestion.correct;
    
    if (selectedIndex === correctIndex) {
        // Correcto
        buttons[selectedIndex].classList.add('correct');
        gameState.correctAnswers++;
        playSound('success');
        
        // Saltar para evitar obstáculo
        setTimeout(() => {
            jump();
        }, 300);
    } else {
        // Incorrecto
        if (selectedIndex >= 0) {
            buttons[selectedIndex].classList.add('wrong');
        }
        buttons[correctIndex].classList.add('correct');
        gameState.errors++;
        loseLife();
        playSound('error');
    }
    
    updateDisplay();
    
    // Ocultar pregunta después de 1.5 segundos
    setTimeout(() => {
        document.getElementById('question-panel').style.display = 'none';
        gameState.currentQuestion = null;
        gameState.isPaused = false;
        
        // Próxima pregunta en 5-8 segundos
        const nextQuestionDelay = 5000 + Math.random() * 3000;
        setTimeout(() => {
            if (gameState.isPlaying && !gameState.isPaused) {
                showQuestion();
            }
        }, nextQuestionDelay);
    }, 1500);
}

function loseLife() {
    gameState.lives--;
    updateDisplay();
    
    if (gameState.lives <= 0) {
        gameOver();
    }
}

function updateDisplay() {
    // Vidas
    const hearts = '❤️'.repeat(gameState.lives) + '🖤'.repeat(3 - gameState.lives);
    document.getElementById('lives-display').textContent = hearts;
    
    // Correctas y errores
    document.getElementById('correct-count').textContent = gameState.correctAnswers;
    document.getElementById('errors-count').textContent = gameState.errors;
    
    // Precisión
    const accuracy = gameState.totalQuestions > 0 
        ? Math.round((gameState.correctAnswers / gameState.totalQuestions) * 100) 
        : 100;
    document.getElementById('accuracy').textContent = accuracy + '%';
}

function updateTimer() {
    if (gameState.isPaused) return;
    
    const elapsed = Math.floor((Date.now() - gameState.startTime) / 1000);
    const minutes = Math.floor(elapsed / 60);
    const seconds = elapsed % 60;
    document.getElementById('timer').textContent = `${minutes}:${seconds.toString().padStart(2, '0')}`;
}

function pauseGame() {
    gameState.isPaused = true;
    const modal = new bootstrap.Modal(document.getElementById('pauseModal'));
    modal.show();
}

function resumeGame() {
    gameState.isPaused = false;
    bootstrap.Modal.getInstance(document.getElementById('pauseModal')).hide();
}

function restartGame() {
    // Limpiar
    clearInterval(gameState.gameTimer);
    clearInterval(gameState.questionTimer);
    if (gameState.animationId) {
        cancelAnimationFrame(gameState.animationId);
    }
    
    // Ocultar modales
    const pauseModal = bootstrap.Modal.getInstance(document.getElementById('pauseModal'));
    const gameOverModal = bootstrap.Modal.getInstance(document.getElementById('gameOverModal'));
    if (pauseModal) pauseModal.hide();
    if (gameOverModal) gameOverModal.hide();
    
    document.querySelectorAll('.modal-backdrop').forEach(b => b.remove());
    document.body.classList.remove('modal-open');
    document.body.style.overflow = '';
    
    setTimeout(() => startGame(), 300);
}

function exitGame() {
    window.location.href = '{{ route("games.index") }}';
}

function gameOver() {
    gameState.isPlaying = false;
    clearInterval(gameState.gameTimer);
    clearInterval(gameState.questionTimer);
    if (gameState.animationId) {
        cancelAnimationFrame(gameState.animationId);
    }
    
    const timePlayedSeconds = Math.floor((Date.now() - gameState.startTime) / 1000);
    const accuracy = gameState.totalQuestions > 0 
        ? Math.round((gameState.correctAnswers / gameState.totalQuestions) * 100) 
        : 0;
    
    // Guardar resultado
    saveGameResult(timePlayedSeconds, accuracy);
    
    // Mostrar modal
    document.getElementById('final-distance').textContent = Math.floor(gameState.distance) + 'm';
    document.getElementById('final-accuracy').textContent = accuracy + '%';
    document.getElementById('final-correct').textContent = gameState.correctAnswers;
    
    playSound('victory');
    
    setTimeout(() => {
        const modal = new bootstrap.Modal(document.getElementById('gameOverModal'));
        modal.show();
    }, 500);
}

function saveGameResult(timePlayedSeconds, accuracy) {
    const data = {
        game_type: 'grammar_runner',
        score: Math.floor(gameState.distance),
        correct_answers: gameState.correctAnswers,
        total_questions: gameState.totalQuestions,
        time_spent: timePlayedSeconds,
        accuracy: accuracy,
        level: gameState.difficulty
    };
    
    fetch('{{ route("games.save-result") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            document.getElementById('xp-earned').textContent = `+${result.xp_earned} XP`;
            updateNavbarXP(result.total_xp, result.level, result.current_xp, result.required_xp);
        }
    })
    .catch(error => console.error('Error:', error));
}

function updateNavbarXP(totalXP, level, currentXP, requiredXP) {
    const xpTexts = document.querySelectorAll('.user-xp-text');
    xpTexts.forEach(el => {
        el.textContent = `${totalXP.toLocaleString()} XP`;
    });
    
    const levelElements = document.querySelectorAll('.user-level');
    levelElements.forEach(el => {
        el.textContent = `Nivel ${level}`;
    });
    
    const progressText = document.querySelector('.xp-progress-text');
    if (progressText) {
        progressText.textContent = `${currentXP}/${requiredXP} XP`;
    }
    
    const progressFill = document.querySelector('.xp-progress-fill');
    if (progressFill && requiredXP > 0) {
        const percent = Math.min(100, (currentXP / requiredXP) * 100);
        progressFill.style.width = `${percent}%`;
    }
}

function playSound(soundName) {
    try {
        const audio = new Audio(`/audio/${soundName}.mp3`);
        audio.volume = 0.5;
        audio.play().catch(e => console.log('Audio play failed:', e));
    } catch (e) {
        console.log('Audio not available:', e);
    }
}

// Controles de teclado
document.addEventListener('keydown', function(e) {
    if (!gameState.isPlaying || gameState.isPaused) return;
    
    if (e.code === 'Space' || e.code === 'ArrowUp') {
        e.preventDefault();
        if (!gameState.currentQuestion) {
            jump();
        }
    }
    
    // Atajos para responder preguntas (1, 2, 3, 4)
    if (gameState.currentQuestion && e.key >= '1' && e.key <= '4') {
        const index = parseInt(e.key) - 1;
        if (index < gameState.currentQuestion.options.length) {
            selectAnswer(index);
        }
    }
});
</script>
@endsection
