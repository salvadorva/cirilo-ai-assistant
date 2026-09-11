@extends('layout.app')

@section('title', $pageTitle ?? 'Modo Arcade - TypeMaster AI')

@section('content')
<div class="container-fluid px-4">
    <!-- Header Section -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-0 bg-gradient-arcade text-white">
                <div class="card-body p-4">
                    <div class="row align-items-center">
                        <div class="col-lg-8">
                            <h1 class="display-5 fw-bold mb-3">
                                <i class="fas fa-rocket me-3"></i>Modo Arcade
                            </h1>
                            <p class="lead mb-3">
                                ¡Palabras que caen estilo Tetris! Escríbelas antes de que toquen el suelo y obtén power-ups increíbles. 
                                <strong class="text-warning">¡Cuidado! Solo puedes perder 5 palabras.</strong>
                            </p>
                            <div class="d-flex flex-wrap gap-3">
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-bolt me-2"></i>
                                    <span>Velocidad Extrema</span>
                                </div>
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-magic me-2"></i>
                                    <span>Power-ups</span>
                                </div>
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-fire me-2"></i>
                                    <span>Combos</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-4 text-center">
                            <div class="display-1 arcade-glow">
                                <i class="fas fa-gamepad"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Game Configuration -->
    <div class="row mb-4">
        <div class="col-lg-8 mx-auto">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-success text-white">
                    <h4 class="mb-0"><i class="fas fa-cog me-2"></i>Configuración del Juego</h4>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4">
                            <label class="form-label"><i class="fas fa-tachometer-alt me-2"></i>Velocidad Inicial</label>
                            <select class="form-select" id="initial-speed">
                                <option value="1">Principiante (Lento)</option>
                                <option value="2" selected>Normal</option>
                                <option value="3">Rápido</option>
                                <option value="4">Extremo</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label"><i class="fas fa-layer-group me-2"></i>Tipo de Palabras</label>
                            <select class="form-select" id="word-type">
                                <option value="common">Palabras Comunes</option>
                                <option value="technical">Técnicas</option>
                                <option value="mixed" selected>Mixtas</option>
                                <option value="numbers">Con Números</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label"><i class="fas fa-magic me-2"></i>Power-ups</label>
                            <select class="form-select" id="powerups-enabled">
                                <option value="true" selected>Activados</option>
                                <option value="false">Desactivados</option>
                            </select>
                        </div>
                    </div>
                    <div class="row mt-3">
                        <div class="col-12 text-center">
                            <button id="start-arcade-game" class="btn btn-success btn-lg">
                                <i class="fas fa-play me-2"></i>¡Comenzar Arcade!
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Game Area -->
    <div class="row" id="game-area" style="display: none;">
        <div class="col-12">
            <div class="card border-0 shadow-lg">
                <div class="card-header bg-dark text-white">
                    <div class="row align-items-center">
                        <div class="col-md-3">
                            <div class="d-flex align-items-center">
                                <i class="fas fa-heart me-2 text-danger"></i>
                                <span>Vidas: <span id="lives-remaining">5</span>/5</span>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="d-flex align-items-center">
                                <i class="fas fa-star me-2"></i>
                                <span>Score: <span id="current-score">0</span></span>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="d-flex align-items-center">
                                <i class="fas fa-layer-group me-2"></i>
                                <span>Nivel: <span id="current-level">1</span></span>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="d-flex align-items-center">
                                <i class="fas fa-fire me-2"></i>
                                <span>Combo: <span id="current-combo">0</span></span>
                            </div>
                        </div>
                        <div class="col-md-3 text-end">
                            <button id="pause-game" class="btn btn-warning btn-sm me-2">
                                <i class="fas fa-pause"></i> Pausa
                            </button>
                            <button id="exit-arcade" class="btn btn-danger btn-sm">
                                <i class="fas fa-times"></i> Salir
                            </button>
                        </div>
                    </div>
                </div>
                <div class="card-body p-0">
                    <!-- Game Canvas -->
                    <div id="game-canvas" class="position-relative bg-dark" style="height: 500px; overflow: hidden;">
                        <!-- Falling words will be rendered here -->
                        <div id="falling-words-container"></div>
                        
                        <!-- Power-ups display -->
                        <div id="powerups-display" class="position-absolute top-0 end-0 p-3">
                            <div id="active-powerups"></div>
                        </div>
                        
                        <!-- Effects overlay -->
                        <div id="effects-overlay" class="position-absolute top-0 start-0 w-100 h-100 pointer-events-none"></div>
                    </div>
                    
                    <!-- Typing Area -->
                    <div class="bg-light p-3 border-top">
                        <div class="row align-items-center">
                            <div class="col-md-8">
                                <div class="input-group input-group-lg">
                                    <span class="input-group-text bg-success text-white">
                                        <i class="fas fa-keyboard"></i>
                                    </span>
                                    <input type="text" id="typing-input" class="form-control" 
                                           placeholder="Escribe las palabras que caen..." 
                                           autocomplete="off" 
                                           disabled>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="d-flex align-items-center justify-content-between">
                                    <div class="text-center">
                                        <small class="text-muted d-block">WPM</small>
                                        <strong id="current-wpm">0</strong>
                                    </div>
                                    <div class="text-center">
                                        <small class="text-muted d-block">Precisión</small>
                                        <strong id="current-accuracy">100%</strong>
                                    </div>
                                    <div class="text-center">
                                        <small class="text-muted d-block">Tiempo</small>
                                        <strong id="game-timer">0:00</strong>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Game Rules Info -->
    <div class="row mt-4" id="game-rules-info">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-warning text-dark">
                    <h5 class="mb-0"><i class="fas fa-exclamation-triangle me-2"></i>Reglas del Juego</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6><i class="fas fa-target text-success me-2"></i>Objetivo</h6>
                            <ul class="list-unstyled mb-3">
                                <li>• Escribe las palabras antes de que lleguen al suelo</li>
                                <li>• Obtén la puntuación más alta posible</li>
                                <li>• Sobrevive el mayor tiempo posible</li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <h6><i class="fas fa-heart text-danger me-2"></i>Sistema de Vidas</h6>
                            <ul class="list-unstyled mb-3">
                                <li>• Tienes <strong>5 vidas</strong> al comenzar</li>
                                <li>• Pierdes 1 vida por cada palabra que toque el suelo</li>
                                <li>• El juego termina al perder todas las vidas</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Power-ups Info -->
    <div class="row mt-4" id="powerups-info">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i class="fas fa-magic me-2"></i>Power-ups Disponibles</h5>
                </div>
                <div class="card-body">
                    <div class="alert alert-warning mb-3">
                        <i class="fas fa-mouse-pointer me-2"></i>
                        <strong>¿Cómo usar?</strong> Los power-ups aparecen con un <strong>fondo dorado brillante</strong>. 
                        <strong>¡Haz clic sobre ellos</strong> para activarlos instantáneamente! No necesitas escribirlos.
                    </div>
                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <div class="text-center">
                                <div class="fs-2 text-warning mb-2">⚡</div>
                                <h6>Speed Boost</h6>
                                <small class="text-muted">Ralentiza las palabras por 10 segundos</small>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="text-center">
                                <div class="fs-2 text-danger mb-2">💥</div>
                                <h6>Bomb</h6>
                                <small class="text-muted">Elimina todas las palabras en pantalla</small>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="text-center">
                                <div class="fs-2 text-success mb-2">⭐</div>
                                <h6>Score Multiplier</h6>
                                <small class="text-muted">Duplica los puntos por 15 segundos</small>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="text-center">
                                <div class="fs-2 text-primary mb-2">🛡️</div>
                                <h6>Shield</h6>
                                <small class="text-muted">Protege de errores por 20 segundos</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Game Over Modal -->
<div class="modal fade" id="gameOverModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">
                    <i class="fas fa-flag-checkered me-2"></i>¡Juego Terminado!
                </h5>
            </div>
            <div class="modal-body text-center">
                <div class="row">
                    <div class="col-md-6">
                        <h3 class="text-primary">Score Final</h3>
                        <div class="display-4 text-success" id="final-score">0</div>
                    </div>
                    <div class="col-md-6">
                        <h3 class="text-primary">Nivel Alcanzado</h3>
                        <div class="display-4 text-warning" id="final-level">1</div>
                    </div>
                </div>
                <div class="row mt-4">
                    <div class="col-md-3 text-center">
                        <h5>Palabras Correctas</h5>
                        <div class="fs-4 text-success" id="words-correct">0</div>
                    </div>
                    <div class="col-md-3 text-center">
                        <h5>Palabras Perdidas</h5>
                        <div class="fs-4 text-danger" id="words-missed">0</div>
                    </div>
                    <div class="col-md-3 text-center">
                        <h5>WPM Promedio</h5>
                        <div class="fs-4 text-info" id="avg-wpm">0</div>
                    </div>
                    <div class="col-md-3 text-center">
                        <h5>Precisión</h5>
                        <div class="fs-4 text-warning" id="final-accuracy">0%</div>
                    </div>
                </div>
                <div class="mt-4">
                    <div id="new-record" class="alert alert-success" style="display: none;">
                        <i class="fas fa-trophy me-2"></i>¡Nuevo récord personal!
                    </div>
                    <div id="xp-earned" class="alert alert-info">
                        <i class="fas fa-star me-2"></i>XP Ganado: <span id="earned-xp">0</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-success" onclick="restartArcadeGame()">
                    <i class="fas fa-redo me-2"></i>Jugar de Nuevo
                </button>
                <button type="button" class="btn btn-primary" onclick="goToGameModes()">
                    <i class="fas fa-gamepad me-2"></i>Otros Modos
                </button>
                <button type="button" class="btn btn-secondary" onclick="goToMainMenu()">
                    <i class="fas fa-home me-2"></i>Menú Principal
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Pause Modal -->
<div class="modal fade" id="pauseModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title">
                    <i class="fas fa-pause me-2"></i>Juego Pausado
                </h5>
            </div>
            <div class="modal-body text-center">
                <p class="mb-4">El juego está pausado. ¿Qué deseas hacer?</p>
                <div class="d-grid gap-2">
                    <button class="btn btn-success" onclick="resumeArcadeGame()">
                        <i class="fas fa-play me-2"></i>Continuar
                    </button>
                    <button class="btn btn-warning" onclick="restartArcadeGame()">
                        <i class="fas fa-redo me-2"></i>Reiniciar
                    </button>
                    <button class="btn btn-danger" onclick="exitArcadeGame()">
                        <i class="fas fa-times me-2"></i>Salir
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.bg-gradient-arcade {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

.arcade-glow {
    animation: arcadeGlow 2s ease-in-out infinite alternate;
}

@keyframes arcadeGlow {
    from {
        text-shadow: 0 0 20px #00ff00, 0 0 30px #00ff00, 0 0 40px #00ff00;
    }
    to {
        text-shadow: 0 0 10px #00ff00, 0 0 20px #00ff00, 0 0 30px #00ff00;
    }
}

.falling-word {
    position: absolute;
    padding: 8px 16px;
    background: linear-gradient(135deg, #ff6b6b, #feca57);
    color: white;
    border-radius: 20px;
    font-weight: bold;
    font-size: 18px;
    text-shadow: 1px 1px 2px rgba(0,0,0,0.5);
    transition: all 0.1s ease;
    cursor: pointer;
    z-index: 10;
    box-shadow: 0 4px 15px rgba(255, 107, 107, 0.3);
}

.falling-word.powerup {
    background: linear-gradient(135deg, #ffd700, #ffed4e);
    color: #333;
    animation: powerupGlow 1s ease-in-out infinite alternate;
    border: 3px solid #ff9500;
    transform: scale(1.1);
    text-align: center;
    padding: 10px 20px;
    min-width: 80px;
}

.falling-word.powerup:hover {
    transform: scale(1.2);
    border-color: #00ff00;
    box-shadow: 0 8px 30px rgba(255, 215, 0, 1);
}

.falling-word.active {
    background: linear-gradient(135deg, #00d2ff, #3a7bd5);
    transform: scale(1.1);
    box-shadow: 0 6px 20px rgba(0, 210, 255, 0.5);
}

@keyframes powerupGlow {
    from { box-shadow: 0 4px 15px rgba(255, 215, 0, 0.5); }
    to { box-shadow: 0 6px 25px rgba(255, 215, 0, 0.8); }
}

.explosion-effect {
    position: absolute;
    width: 60px;
    height: 60px;
    background: radial-gradient(circle, #ff6b6b, transparent);
    border-radius: 50%;
    animation: explode 0.5s ease-out forwards;
}

@keyframes explode {
    0% { transform: scale(0); opacity: 1; }
    100% { transform: scale(3); opacity: 0; }
}

.score-popup {
    position: absolute;
    font-weight: bold;
    font-size: 24px;
    color: #00ff00;
    text-shadow: 2px 2px 4px rgba(0,0,0,0.8);
    animation: scoreFloat 1s ease-out forwards;
    pointer-events: none;
    z-index: 100;
}

@keyframes scoreFloat {
    0% { transform: translateY(0) scale(1); opacity: 1; }
    100% { transform: translateY(-50px) scale(1.2); opacity: 0; }
}

.power-up-active {
    border: 3px solid #ffd700;
    box-shadow: 0 0 20px rgba(255, 215, 0, 0.5);
}

.combo-indicator {
    position: fixed;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    font-size: 48px;
    font-weight: bold;
    color: #ff6b6b;
    text-shadow: 3px 3px 6px rgba(0,0,0,0.8);
    animation: comboShow 1s ease-out forwards;
    pointer-events: none;
    z-index: 1000;
}

@keyframes comboShow {
    0% { transform: translate(-50%, -50%) scale(0); opacity: 0; }
    50% { transform: translate(-50%, -50%) scale(1.2); opacity: 1; }
    100% { transform: translate(-50%, -50%) scale(1); opacity: 0; }
}

@keyframes powerupNotification {
    0% { 
        transform: translate(-50%, -50%) scale(0) rotate(-10deg); 
        opacity: 0; 
    }
    20% { 
        transform: translate(-50%, -50%) scale(1.2) rotate(5deg); 
        opacity: 1; 
    }
    80% { 
        transform: translate(-50%, -50%) scale(1) rotate(0deg); 
        opacity: 1; 
    }
    100% { 
        transform: translate(-50%, -50%) scale(0.8) rotate(0deg); 
        opacity: 0; 
    }
}

@keyframes lifeLostFlash {
    0% { opacity: 0; }
    50% { opacity: 1; }
    100% { opacity: 0; }
}

@keyframes lifeLostText {
    0% { transform: translate(-50%, -50%) scale(0); opacity: 0; }
    20% { transform: translate(-50%, -50%) scale(1.2); opacity: 1; }
    80% { transform: translate(-50%, -50%) scale(1); opacity: 1; }
    100% { transform: translate(-50%, -50%) scale(0.8); opacity: 0; }
}

#game-canvas {
    background: linear-gradient(180deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
    position: relative;
}

.typing-input-glow {
    box-shadow: 0 0 20px rgba(0, 255, 0, 0.5);
    border-color: #00ff00 !important;
}

.is-invalid {
    border-color: #dc3545 !important;
    box-shadow: 0 0 20px rgba(220, 53, 69, 0.5) !important;
    animation: inputError 0.3s ease-out;
}

@keyframes inputError {
    0% { transform: translateX(0); }
    25% { transform: translateX(-5px); }
    75% { transform: translateX(5px); }
    100% { transform: translateX(0); }
}

.game-stats {
    background: rgba(0, 0, 0, 0.7);
    border-radius: 10px;
    padding: 10px;
    color: white;
}
</style>

<script>
class ArcadeGame {
    constructor() {
        this.isRunning = false;
        this.isPaused = false;
        this.score = 0;
        this.level = 1;
        this.combo = 0;
        this.wordsCorrect = 0;
        this.wordsTotal = 0;
        this.wordsMissed = 0;
        this.maxMissedWords = 5; // Game Over después de 5 palabras perdidas
        this.wpm = 0;
        this.startTime = null;
        this.gameTimer = null;
        this.wordSpawnTimer = null;
        this.fallingWords = [];
        this.activePowerUps = {};
        this.currentTarget = null;
        this.hasEnded = false;
        
        // Game settings
        this.settings = {
            initialSpeed: 2,
            wordType: 'mixed',
            powerupsEnabled: true,
            fallSpeed: 1,
            spawnRate: 2000
        };
        
        // Word lists
        this.wordLists = {
            common: ['casa', 'perro', 'gato', 'agua', 'fuego', 'tierra', 'cielo', 'sol', 'luna', 'estrella', 'amor', 'vida', 'tiempo', 'mundo', 'persona'],
            technical: ['algoritmo', 'javascript', 'python', 'base', 'datos', 'servidor', 'cliente', 'codigo', 'funcion', 'variable', 'array', 'objeto', 'metodo', 'clase', 'interfaz'],
            mixed: ['rapido', 'desarrollo', 'tecnologia', 'internet', 'computadora', 'teclado', 'pantalla', 'mouse', 'ventana', 'archivo', 'carpeta', 'documento', 'imagen', 'video', 'audio'],
            numbers: ['123', '456', '789', '2024', '100%', '50.5', '3.14', '2x2', '10+5', '8-3', '9*7', '15/3', '100$', '25€', '99.9']
        };
        
        this.powerUpTypes = ['speed', 'bomb', 'multiplier', 'shield'];
        
        this.bindEvents();
    }
    
    bindEvents() {
        document.getElementById('start-arcade-game').addEventListener('click', () => this.startGame());
        document.getElementById('pause-game').addEventListener('click', () => this.pauseGame());
        document.getElementById('exit-arcade').addEventListener('click', () => this.exitGame());
        document.getElementById('typing-input').addEventListener('input', (e) => this.handleTyping(e));
        document.getElementById('typing-input').addEventListener('keydown', (e) => {
            if (e.key === 'Escape') this.pauseGame();
        });
    }
    
    startGame() {
        // Stop any running timers first
        if (this.wordSpawnTimer) {
            clearTimeout(this.wordSpawnTimer);
            this.wordSpawnTimer = null;
        }
        if (this.gameTimer) {
            clearTimeout(this.gameTimer);
            this.gameTimer = null;
        }
        
        // Clear any existing falling words from DOM
        const container = document.getElementById('falling-words-container');
        if (container) {
            container.innerHTML = '';
        }
        
        // Clear effects overlay
        const effectsOverlay = document.getElementById('effects-overlay');
        if (effectsOverlay) {
            effectsOverlay.innerHTML = '';
        }
        
        // Clear active power-ups display
        const activePowerupsDisplay = document.getElementById('active-powerups');
        if (activePowerupsDisplay) {
            activePowerupsDisplay.innerHTML = '';
        }
        
        // Get settings
        this.settings.initialSpeed = parseInt(document.getElementById('initial-speed').value);
        this.settings.wordType = document.getElementById('word-type').value;
        this.settings.powerupsEnabled = document.getElementById('powerups-enabled').value === 'true';
        this.settings.fallSpeed = 1; // Reset to default speed
        
        // Reset game state
        this.score = 0;
        this.level = 1;
        this.combo = 0;
        this.wordsCorrect = 0;
        this.wordsTotal = 0;
        this.wordsMissed = 0;
        this.wpm = 0;
        this.fallingWords = [];
        this.activePowerUps = {};
        this.currentTarget = null;
        this.startTime = Date.now();
    this.hasEnded = false;
        
        // Clear input field
        const typingInput = document.getElementById('typing-input');
        typingInput.value = '';
        typingInput.classList.remove('is-invalid');
        
        // Hide config, show game
        document.getElementById('powerups-info').style.display = 'none';
        document.getElementById('game-rules-info').style.display = 'none';
        document.getElementById('game-area').style.display = 'block';
        document.getElementById('typing-input').disabled = false;
        
        // Update UI before starting
        this.updateUI();
        
        // Start game loops
        this.isRunning = true;
        this.isPaused = false;
        
        // Small delay to ensure UI is ready
        setTimeout(() => {
            this.spawnWords();
            this.gameLoop();
            this.updateTimer();
            document.getElementById('typing-input').focus();
        }, 100);
        
        // Play start sound
        this.playSound('game_start');
    }
    
    pauseGame() {
        if (!this.isRunning) return;
        
        this.isPaused = true;
        clearTimeout(this.wordSpawnTimer);
        
        const pauseModal = new bootstrap.Modal(document.getElementById('pauseModal'));
        pauseModal.show();
    }
    
    resumeGame() {
        this.isPaused = false;
        this.spawnWords();
        this.gameLoop();
        
        const pauseModal = bootstrap.Modal.getInstance(document.getElementById('pauseModal'));
        pauseModal.hide();
        
        document.getElementById('typing-input').focus();
    }
    
    exitGame() {
        this.endGame();
    }
    
    endGame() {
        if (this.hasEnded) {
            return;
        }
        this.hasEnded = true;
        
        this.isRunning = false;
        this.isPaused = false;
        
        // Clear all timers
        if (this.wordSpawnTimer) {
            clearTimeout(this.wordSpawnTimer);
            this.wordSpawnTimer = null;
        }
        if (this.gameTimer) {
            clearTimeout(this.gameTimer);
            this.gameTimer = null;
        }
        
        // Clear DOM elements
        const container = document.getElementById('falling-words-container');
        if (container) {
            container.innerHTML = '';
        }
        
        const effectsOverlay = document.getElementById('effects-overlay');
        if (effectsOverlay) {
            effectsOverlay.innerHTML = '';
        }
        
        const activePowerupsDisplay = document.getElementById('active-powerups');
        if (activePowerupsDisplay) {
            activePowerupsDisplay.innerHTML = '';
        }
        
        // Calculate final stats
        const gameTime = (Date.now() - this.startTime) / 1000 / 60; // in minutes
        const avgWpm = gameTime > 0 ? Math.round(this.wordsCorrect / gameTime * 5) : 0;
        const accuracy = this.wordsTotal > 0 ? Math.round((this.wordsCorrect / this.wordsTotal) * 100) : 100;
        
        // Show results
        document.getElementById('final-score').textContent = this.score.toLocaleString();
        document.getElementById('final-level').textContent = this.level;
        document.getElementById('words-correct').textContent = this.wordsCorrect;
        document.getElementById('words-missed').textContent = this.wordsMissed;
        document.getElementById('avg-wpm').textContent = avgWpm;
        document.getElementById('final-accuracy').textContent = accuracy + '%';
        
        // Calculate XP (base XP * 1.2 multiplier for arcade mode)
        const baseXP = Math.round(this.score / 10);
        const earnedXP = Math.round(baseXP * 1.2);
        document.getElementById('earned-xp').textContent = earnedXP;
        
        // Save session
        this.saveGameSession(avgWpm, accuracy, earnedXP);
        
        // Show modal
        const gameOverModal = new bootstrap.Modal(document.getElementById('gameOverModal'));
        gameOverModal.show();
        
        // Reset UI
        document.getElementById('game-area').style.display = 'none';
        document.getElementById('powerups-info').style.display = 'block';
        document.getElementById('game-rules-info').style.display = 'block';
        document.getElementById('typing-input').disabled = true;
        document.getElementById('typing-input').value = '';
        
        // Clear falling words array
        this.fallingWords = [];
        this.currentTarget = null;
    }
    
    spawnWords() {
        if (!this.isRunning || this.isPaused) return;
        
        // Spawn regular word
        this.spawnWord(false);
        
        // Chance to spawn power-up
        if (this.settings.powerupsEnabled && Math.random() < 0.15) {
            setTimeout(() => this.spawnWord(true), 500);
        }
        
        // Schedule next spawn
        const spawnDelay = Math.max(800, 2000 - (this.level * 100));
        this.wordSpawnTimer = setTimeout(() => this.spawnWords(), spawnDelay);
    }
    
    spawnWord(isPowerUp = false) {
        const container = document.getElementById('falling-words-container');
        const word = document.createElement('div');
        word.className = 'falling-word' + (isPowerUp ? ' powerup' : '');
        
        let text;
        let powerUpType;
        if (isPowerUp) {
            powerUpType = this.powerUpTypes[Math.floor(Math.random() * this.powerUpTypes.length)];
            const powerUpIcons = {
                'speed': '⚡',
                'bomb': '💥',
                'multiplier': '⭐',
                'shield': '🛡️'
            };
            const powerUpNames = {
                'speed': 'SPEED',
                'bomb': 'BOMB',
                'multiplier': 'x2',
                'shield': 'SHIELD'
            };
            text = powerUpIcons[powerUpType];
            word.dataset.powerup = powerUpType;
            word.innerHTML = `
                <div style="font-size: 24px; margin-bottom: 2px;">${text}</div>
                <div style="font-size: 10px; font-weight: bold; text-transform: uppercase;">${powerUpNames[powerUpType]}</div>
            `;
            word.style.cursor = 'pointer';
            word.title = 'Click para activar';
            
            // Add click event for power-ups
            word.addEventListener('click', (e) => {
                e.stopPropagation();
                const wordIndex = this.fallingWords.findIndex(w => w.id === word.dataset.id);
                if (wordIndex !== -1) {
                    this.completeWord(this.fallingWords[wordIndex]);
                }
            });
        } else {
            const wordList = this.wordLists[this.settings.wordType];
            text = wordList[Math.floor(Math.random() * wordList.length)];
            word.textContent = text;
        }
        
        word.dataset.text = text;
        word.dataset.id = Date.now() + Math.random();
        
        // Random horizontal position
        const maxX = container.offsetWidth - 120;
        const x = Math.random() * maxX;
        
        word.style.left = x + 'px';
        word.style.top = '0px';
        
        container.appendChild(word);
        this.fallingWords.push({
            element: word,
            x: x,
            y: 0,
            text: text,
            isPowerUp: isPowerUp,
            powerUpType: powerUpType,
            id: word.dataset.id
        });
    }
    
    gameLoop() {
        if (!this.isRunning || this.isPaused) return;
        
        // Move falling words
        this.fallingWords.forEach((wordObj, index) => {
            wordObj.y += this.settings.fallSpeed * (1 + this.level * 0.2);
            wordObj.element.style.top = wordObj.y + 'px';
            
            // Check if word reached bottom
            if (wordObj.y > 450) {
                if (!wordObj.isPowerUp) {
                    this.wordsMissed++;
                    this.combo = 0; // Reset combo
                    console.log(`Vida perdida! Vidas restantes: ${this.maxMissedWords - this.wordsMissed}`); // Debug
                    this.updateCombo();
                    this.playSound('miss');
                    this.showLifeLostEffect();
                    
                    // Check for game over
                    if (this.wordsMissed >= this.maxMissedWords) {
                        console.log('Game Over!'); // Debug
                        this.playSound('defeat');
                        setTimeout(() => this.endGame(), 500);
                        return;
                    }
                }
                this.removeWord(index);
            }
        });
        
        // Continue game loop
        requestAnimationFrame(() => this.gameLoop());
    }
    
    handleTyping(e) {
        const input = e.target.value.toLowerCase().trim();
        if (!input) {
            this.clearTarget();
            return;
        }
        
        // Find matching word
        let bestMatch = null;
        let bestDistance = Infinity;
        let hasPartialMatch = false;
        
        this.fallingWords.forEach(wordObj => {
            if (!wordObj.isPowerUp && wordObj.text.toLowerCase().startsWith(input)) {
                hasPartialMatch = true;
                const distance = wordObj.y;
                if (distance < bestDistance) {
                    bestMatch = wordObj;
                    bestDistance = distance;
                }
            }
        });
        
        if (bestMatch) {
            this.setTarget(bestMatch);
            
            // Check if word is complete
            if (input === bestMatch.text.toLowerCase()) {
                this.completeWord(bestMatch);
                e.target.value = '';
            }
        } else {
            this.clearTarget();
            // Si no hay ninguna coincidencia parcial y el input tiene más de 2 caracteres, es un error
            if (!hasPartialMatch && input.length >= 2) {
                this.playSound('error'); // Reproducir sonido de error
                // Agregar efecto visual al input
                const inputElement = e.target;
                inputElement.classList.add('is-invalid');
                setTimeout(() => {
                    inputElement.classList.remove('is-invalid');
                }, 300);
            }
        }
    }
    
    setTarget(wordObj) {
        if (this.currentTarget) {
            this.currentTarget.element.classList.remove('active');
        }
        this.currentTarget = wordObj;
        wordObj.element.classList.add('active');
    }
    
    clearTarget() {
        if (this.currentTarget) {
            this.currentTarget.element.classList.remove('active');
            this.currentTarget = null;
        }
    }
    
    completeWord(wordObj) {
        const index = this.fallingWords.indexOf(wordObj);
        if (index === -1) return;
        
        if (wordObj.isPowerUp) {
            this.activatePowerUp(wordObj.element.dataset.powerup);
        } else {
            // Calculate score
            const timeBonus = Math.max(1, 10 - Math.floor(wordObj.y / 50));
            const lengthBonus = wordObj.text.length;
            const comboMultiplier = Math.max(1, this.combo + 1);
            const levelMultiplier = 1 + (this.level - 1) * 0.1;
            
            let points = (10 + timeBonus + lengthBonus) * comboMultiplier * levelMultiplier;
            
            // Apply power-up multipliers
            if (this.activePowerUps.multiplier) {
                points *= 2;
            }
            
            this.score += Math.round(points);
            this.wordsCorrect++;
            this.combo++;
            
            // Show score popup
            this.showScorePopup(wordObj.element, Math.round(points));
            
            // Check level up
            if (this.wordsCorrect % 10 === 0) {
                this.levelUp();
            }
        }
        
        this.wordsTotal++;
        this.removeWord(index);
        this.clearTarget();
        this.updateUI();
        this.updateCombo();
        
        // Play sound based on what was completed
        if (wordObj.isPowerUp) {
            this.playSound('level_up'); // Power-up sound
        } else {
            this.playSound('hit'); // Regular word completion sound
        }
        
        // Add explosion effect
        this.addExplosionEffect(wordObj.element);
    }
    
    removeWord(index) {
        const wordObj = this.fallingWords[index];
        if (wordObj && wordObj.element.parentNode) {
            wordObj.element.parentNode.removeChild(wordObj.element);
        }
        this.fallingWords.splice(index, 1);
    }
    
    activatePowerUp(type) {
        const powerUpMessages = {
            'speed': '⚡ VELOCIDAD REDUCIDA!',
            'bomb': '💥 BOMBA ACTIVADA!',
            'multiplier': '⭐ PUNTOS x2!',
            'shield': '🛡️ ESCUDO ACTIVADO!'
        };
        
        switch (type) {
            case 'speed':
                this.activePowerUps.speed = Date.now() + 10000; // 10 seconds
                this.settings.fallSpeed = 0.5;
                break;
            case 'bomb':
                this.clearAllWords();
                break;
            case 'multiplier':
                this.activePowerUps.multiplier = Date.now() + 15000; // 15 seconds
                break;
            case 'shield':
                this.activePowerUps.shield = Date.now() + 20000; // 20 seconds
                break;
        }
        
        // Show power-up activation message
        this.showPowerUpMessage(powerUpMessages[type]);
        this.updatePowerUpsDisplay();
        // No llamar playSound aquí porque ya se llama en completeWord
    }
    
    showPowerUpMessage(message) {
        const notification = document.createElement('div');
        notification.className = 'powerup-notification';
        notification.textContent = message;
        notification.style.cssText = `
            position: fixed;
            top: 20%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 36px;
            font-weight: bold;
            color: #ffd700;
            text-shadow: 3px 3px 6px rgba(0,0,0,0.8), 0 0 20px rgba(255,215,0,0.5);
            z-index: 10000;
            pointer-events: none;
            animation: powerupNotification 2s ease-out forwards;
            background: rgba(0,0,0,0.7);
            padding: 20px 40px;
            border-radius: 15px;
            border: 3px solid #ffd700;
        `;
        
        document.body.appendChild(notification);
        
        setTimeout(() => {
            if (notification.parentNode) {
                notification.parentNode.removeChild(notification);
            }
        }, 2000);
    }
    
    clearAllWords() {
        this.fallingWords.forEach(wordObj => {
            if (!wordObj.isPowerUp) {
                this.addExplosionEffect(wordObj.element);
                this.score += 5; // Bonus points for each cleared word
            }
        });
        
        // Clear the container
        document.getElementById('falling-words-container').innerHTML = '';
        this.fallingWords = [];
        this.updateUI();
    }
    
    updatePowerUpsDisplay() {
        const display = document.getElementById('active-powerups');
        display.innerHTML = '';
        
        const now = Date.now();
        Object.keys(this.activePowerUps).forEach(type => {
            if (this.activePowerUps[type] > now) {
                const remaining = Math.ceil((this.activePowerUps[type] - now) / 1000);
                const badge = document.createElement('div');
                badge.className = 'badge bg-warning text-dark me-2 mb-2';
                badge.innerHTML = `${this.getPowerUpIcon(type)} ${remaining}s`;
                display.appendChild(badge);
            } else {
                delete this.activePowerUps[type];
                if (type === 'speed') {
                    this.settings.fallSpeed = 1;
                }
            }
        });
    }
    
    getPowerUpIcon(type) {
        const icons = {
            speed: '⚡',
            bomb: '💥',
            multiplier: '⭐',
            shield: '🛡️'
        };
        return icons[type] || '?';
    }
    
    levelUp() {
        this.level++;
        this.playSound('level_up');
        
        // Show level up indicator
        const indicator = document.createElement('div');
        indicator.className = 'combo-indicator';
        indicator.textContent = `¡NIVEL ${this.level}!`;
        document.body.appendChild(indicator);
        
        setTimeout(() => {
            if (indicator.parentNode) {
                indicator.parentNode.removeChild(indicator);
            }
        }, 1000);
    }
    
    updateCombo() {
        if (this.combo >= 5) {
            const indicator = document.createElement('div');
            indicator.className = 'combo-indicator';
            indicator.textContent = `¡COMBO x${this.combo}!`;
            document.body.appendChild(indicator);
            
            setTimeout(() => {
                if (indicator.parentNode) {
                    indicator.parentNode.removeChild(indicator);
                }
            }, 1000);
        }
    }
    
    showScorePopup(element, points) {
        const popup = document.createElement('div');
        popup.className = 'score-popup';
        popup.textContent = `+${points}`;
        
        const rect = element.getBoundingClientRect();
        popup.style.left = (rect.left + rect.width / 2) + 'px';
        popup.style.top = rect.top + 'px';
        popup.style.position = 'fixed';
        
        document.body.appendChild(popup);
        
        setTimeout(() => {
            if (popup.parentNode) {
                popup.parentNode.removeChild(popup);
            }
        }, 1000);
    }
    
    addExplosionEffect(element) {
        const explosion = document.createElement('div');
        explosion.className = 'explosion-effect';
        
        const rect = element.getBoundingClientRect();
        const container = document.getElementById('effects-overlay');
        
        explosion.style.left = (rect.left - container.getBoundingClientRect().left + rect.width / 2 - 30) + 'px';
        explosion.style.top = (rect.top - container.getBoundingClientRect().top + rect.height / 2 - 30) + 'px';
        
        container.appendChild(explosion);
        
        setTimeout(() => {
            if (explosion.parentNode) {
                explosion.parentNode.removeChild(explosion);
            }
        }, 500);
    }
    
    updateUI() {
        document.getElementById('current-score').textContent = this.score.toLocaleString();
        document.getElementById('current-level').textContent = this.level;
        document.getElementById('current-combo').textContent = this.combo;
        document.getElementById('lives-remaining').textContent = Math.max(0, this.maxMissedWords - this.wordsMissed);
        
        // Update lives color based on remaining lives
        const livesElement = document.getElementById('lives-remaining');
        const remainingLives = this.maxMissedWords - this.wordsMissed;
        if (remainingLives <= 1) {
            livesElement.style.color = '#dc3545'; // red
        } else if (remainingLives <= 2) {
            livesElement.style.color = '#fd7e14'; // orange
        } else {
            livesElement.style.color = '#198754'; // green
        }
        
        // Calculate WPM
        const gameTime = (Date.now() - this.startTime) / 1000 / 60;
        this.wpm = gameTime > 0 ? Math.round(this.wordsCorrect / gameTime * 5) : 0;
        document.getElementById('current-wpm').textContent = this.wpm;
        
        // Calculate accuracy
        const accuracy = this.wordsTotal > 0 ? Math.round((this.wordsCorrect / this.wordsTotal) * 100) : 100;
        document.getElementById('current-accuracy').textContent = accuracy + '%';
        
        // Update power-ups
        this.updatePowerUpsDisplay();
    }
    
    updateTimer() {
        if (!this.isRunning) return;
        
        const elapsed = Math.floor((Date.now() - this.startTime) / 1000);
        const minutes = Math.floor(elapsed / 60);
        const seconds = elapsed % 60;
        document.getElementById('game-timer').textContent = `${minutes}:${seconds.toString().padStart(2, '0')}`;
        
        if (!this.isPaused) {
            setTimeout(() => this.updateTimer(), 1000);
        }
    }
    
    showLifeLostEffect() {
        // Crear efecto de pantalla roja
        const overlay = document.createElement('div');
        overlay.className = 'life-lost-overlay';
        overlay.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background: rgba(220, 53, 69, 0.3);
            z-index: 9999;
            pointer-events: none;
            animation: lifeLostFlash 0.5s ease-out;
        `;
        
        document.body.appendChild(overlay);
        
        // Mostrar texto de vida perdida
        const lostText = document.createElement('div');
        lostText.className = 'life-lost-text';
        lostText.textContent = '¡VIDA PERDIDA!';
        lostText.style.cssText = `
            position: fixed;
            top: 30%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 48px;
            font-weight: bold;
            color: #dc3545;
            text-shadow: 3px 3px 6px rgba(0,0,0,0.8);
            z-index: 10000;
            pointer-events: none;
            animation: lifeLostText 1s ease-out forwards;
        `;
        
        document.body.appendChild(lostText);
        
        setTimeout(() => {
            if (overlay.parentNode) overlay.parentNode.removeChild(overlay);
            if (lostText.parentNode) lostText.parentNode.removeChild(lostText);
        }, 1000);
    }
    
    saveGameSession(wpm, accuracy, earnedXP) {
        // Enviar a endpoint correcto para modos y con el payload esperado
        fetch('{{ route("typing.mode.save.score") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                // Usar token Blade para evitar falta de meta en layout
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({
                // Backend espera estos campos en saveModeScore
                mode: 'arcade',
                score: this.score,
                wpm: wpm,
                accuracy: accuracy,
                time_played: Math.floor((Date.now() - this.startTime) / 1000),
                extra_data: {
                    level: this.level,
                    words_correct: this.wordsCorrect,
                    words_total: this.wordsTotal,
                    combo_max: this.combo,
                    earned_xp_client: earnedXP
                }
            })
        })
        .then(response => response.json())
        .then(data => {
            console.debug('Arcade saveModeScore response:', data);
            if (data.success) {
                // Update XP display in header
                this.updateNavbarXP(data.total_xp, data.level, data.current_xp, data.required_xp);
                
                // Mostrar el XP real calculado por el backend en el modal
                if (typeof data.xp_earned === 'number') {
                    const xpEl = document.getElementById('earned-xp');
                    if (xpEl) {
                        xpEl.textContent = data.xp_earned;
                    }
                }

                // Check for new record
                if (data.new_record) {
                    document.getElementById('new-record').style.display = 'block';
                }
            }
        })
        .catch(error => console.error('Error saving session (arcade):', error));
    }
    
    updateNavbarXP(totalXP, level, currentXP, requiredXP) {
        // Actualizar texto de XP total
        const xpTexts = document.querySelectorAll('.user-xp-text');
        xpTexts.forEach(el => {
            el.textContent = `${totalXP.toLocaleString()} XP`;
        });
        
        // Actualizar nivel
        const levelElements = document.querySelectorAll('.user-level');
        levelElements.forEach(el => {
            el.textContent = `Nivel ${level}`;
        });
        
        // Actualizar barra de progreso (usa currentXP del nivel actual, no total)
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
    
    playSound(soundName) {
        try {
            console.log(`Playing sound: ${soundName}`); // Debug log
            const audio = new Audio(`/audio/${soundName}.mp3`);
            audio.volume = 0.5; // Aumentar volumen un poco
            audio.play().catch(e => {
                console.log('Audio play failed:', e);
                // Fallback: intentar con un archivo alternativo si es miss
                if (soundName === 'miss') {
                    const fallbackAudio = new Audio('/audio/error.mp3');
                    fallbackAudio.volume = 0.5;
                    fallbackAudio.play().catch(err => console.log('Fallback audio also failed:', err));
                }
            });
        } catch (e) {
            console.log('Audio not available:', e);
        }
    }
}

// Initialize game
let arcadeGame;

document.addEventListener('DOMContentLoaded', function() {
    arcadeGame = new ArcadeGame();
});

// Navigation functions
function restartArcadeGame() {
    const gameOverModal = bootstrap.Modal.getInstance(document.getElementById('gameOverModal'));
    if (gameOverModal) gameOverModal.hide();
    
    const pauseModal = bootstrap.Modal.getInstance(document.getElementById('pauseModal'));
    if (pauseModal) pauseModal.hide();
    
    // Remove any lingering modal backdrops
    document.querySelectorAll('.modal-backdrop').forEach(backdrop => backdrop.remove());
    document.body.classList.remove('modal-open');
    document.body.style.overflow = '';
    document.body.style.paddingRight = '';
    
    setTimeout(() => {
        arcadeGame.startGame();
    }, 300);
}

function resumeArcadeGame() {
    arcadeGame.resumeGame();
}

function exitArcadeGame() {
    const pauseModal = bootstrap.Modal.getInstance(document.getElementById('pauseModal'));
    if (pauseModal) pauseModal.hide();
    
    // Remove any lingering modal backdrops
    document.querySelectorAll('.modal-backdrop').forEach(backdrop => backdrop.remove());
    document.body.classList.remove('modal-open');
    document.body.style.overflow = '';
    document.body.style.paddingRight = '';
    
    setTimeout(() => {
        arcadeGame.endGame();
    }, 300);
}

function goToGameModes() {
    window.location.href = '{{ route("typing.modes") }}';
}

function goToMainMenu() {
    window.location.href = '{{ route("typing.index") }}';
}
</script>
@endsection
