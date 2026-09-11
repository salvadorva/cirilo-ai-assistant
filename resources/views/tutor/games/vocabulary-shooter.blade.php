@extends('layout.app')

@section('title', 'Vocabulary Shooter - Juegos de Inglés')

@section('content')
<div class="container-fluid px-4 py-4">
    <!-- Header del Juego -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-0 bg-gradient-primary text-white shadow-lg">
                <div class="card-body p-4">
                    <div class="row align-items-center">
                        <div class="col-lg-8">
                            <h1 class="display-5 fw-bold mb-3">
                                <i class="fas fa-crosshairs me-3"></i>Vocabulary Shooter 🎯
                            </h1>
                            <p class="lead mb-3">
                                ¡Palabras en español caen desde el cielo! Escribe la traducción en inglés para dispararlas antes de que lleguen al suelo.
                            </p>
                            <div class="d-flex flex-wrap gap-3">
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-heart me-2"></i>
                                    <span>3 Vidas</span>
                                </div>
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-fire me-2"></i>
                                    <span>Sistema de Combos</span>
                                </div>
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-magic me-2"></i>
                                    <span>Power-ups Épicos</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-4 text-center">
                            <div class="display-1">
                                <i class="fas fa-rocket"></i>
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
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0"><i class="fas fa-cog me-2"></i>Configuración del Juego</h4>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label"><i class="fas fa-signal me-2"></i>Nivel de Dificultad</label>
                            <select class="form-select" id="difficulty-level">
                                <option value="basic">🟢 Básico (Palabras simples)</option>
                                <option value="intermediate" selected>🟡 Intermedio (Vocabulario común)</option>
                                <option value="advanced">🔴 Avanzado (Palabras complejas)</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label"><i class="fas fa-tachometer-alt me-2"></i>Velocidad Inicial</label>
                            <select class="form-select" id="initial-speed">
                                <option value="slow">Lenta</option>
                                <option value="normal" selected>Normal</option>
                                <option value="fast">Rápida</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label"><i class="fas fa-magic me-2"></i>Power-ups</label>
                            <select class="form-select" id="powerups-enabled">
                                <option value="true" selected>Activados</option>
                                <option value="false">Desactivados</option>
                            </select>
                        </div>
                    </div>
                    <div class="row mt-3">
                        <div class="col-12 text-center">
                            <button id="start-game-btn" class="btn btn-primary btn-lg px-5">
                                <i class="fas fa-play me-2"></i>¡Comenzar Juego!
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
                                <span class="fw-bold">Score: <span id="score">0</span></span>
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
                                <i class="fas fa-fire text-warning me-2"></i>
                                <span class="fw-bold">Combo: <span id="combo">0</span>x</span>
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
                <div class="card-body p-0" style="background: linear-gradient(180deg, #1a1a2e 0%, #16213e 100%); min-height: 500px; position: relative; overflow: hidden;">
                    <!-- Contenedor de palabras que caen -->
                    <div id="falling-words-container" style="position: relative; width: 100%; height: 500px;">
                        <!-- Las palabras se generarán aquí dinámicamente -->
                    </div>

                    <!-- Contenedor de power-ups -->
                    <div id="powerups-container" style="position: absolute; top: 10px; right: 10px; z-index: 100;">
                        <!-- Power-ups activos se mostrarán aquí -->
                    </div>

                    <!-- Área de input -->
                    <div style="position: absolute; bottom: 20px; left: 50%; transform: translateX(-50%); width: 80%; max-width: 600px; z-index: 200;">
                        <div class="input-group input-group-lg">
                            <span class="input-group-text bg-primary text-white">
                                <i class="fas fa-keyboard"></i>
                            </span>
                            <input type="text" 
                                   id="word-input" 
                                   class="form-control form-control-lg" 
                                   placeholder="Escribe la traducción en inglés..." 
                                   autocomplete="off"
                                   style="font-size: 1.5rem; text-align: center;">
                        </div>
                        <div id="input-feedback" class="text-center mt-2" style="min-height: 30px;">
                            <!-- Feedback visual aparecerá aquí -->
                        </div>
                    </div>
                </div>

                <!-- Footer con estadísticas -->
                <div class="card-footer bg-dark text-white">
                    <div class="row text-center">
                        <div class="col-3">
                            <small class="text-muted">Correctas</small>
                            <div class="fw-bold fs-5" id="correct-count">0</div>
                        </div>
                        <div class="col-3">
                            <small class="text-muted">Falladas</small>
                            <div class="fw-bold fs-5" id="missed-count">0</div>
                        </div>
                        <div class="col-3">
                            <small class="text-muted">Precisión</small>
                            <div class="fw-bold fs-5" id="accuracy">100%</div>
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
                <h5 class="modal-title"><i class="fas fa-skull-crossbones me-2"></i>Game Over</h5>
            </div>
            <div class="modal-body text-center py-4">
                <h2 class="display-4 mb-3">🎮</h2>
                <h3 class="mb-4">¡Juego Terminado!</h3>
                
                <div class="row mb-4">
                    <div class="col-6">
                        <div class="card bg-light">
                            <div class="card-body">
                                <h5 class="text-muted">Puntuación Final</h5>
                                <h2 class="text-primary" id="final-score">0</h2>
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
                    <p class="text-muted">Palabras correctas: <span id="final-correct">0</span></p>
                </div>

                <div class="d-grid gap-2">
                    <button class="btn btn-primary btn-lg" onclick="restartGame()">
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
.falling-word {
    position: absolute;
    padding: 12px 24px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 12px;
    font-size: 1.5rem;
    font-weight: bold;
    box-shadow: 0 4px 15px rgba(0,0,0,0.3);
    animation: fall linear;
    cursor: default;
    user-select: none;
    z-index: 50;
}

.falling-word.active {
    background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
    transform: scale(1.1);
    box-shadow: 0 0 20px rgba(245, 87, 108, 0.8);
}

.power-up {
    position: absolute;
    padding: 15px;
    background: linear-gradient(135deg, #ffd700 0%, #ffed4e 100%);
    border-radius: 50%;
    font-size: 2rem;
    box-shadow: 0 4px 20px rgba(255, 215, 0, 0.6);
    animation: fall linear, pulse 0.5s infinite alternate;
    cursor: pointer;
    z-index: 60;
}

.power-up:hover {
    transform: scale(1.2);
    box-shadow: 0 0 30px rgba(255, 215, 0, 1);
}

@keyframes fall {
    from {
        transform: translateY(0) rotate(0deg);
    }
    to {
        transform: translateY(500px) rotate(360deg);
    }
}

@keyframes pulse {
    from {
        transform: scale(1);
    }
    to {
        transform: scale(1.1);
    }
}

.explosion {
    position: absolute;
    font-size: 3rem;
    animation: explode 0.5s ease-out forwards;
    pointer-events: none;
    z-index: 1000;
}

@keyframes explode {
    0% {
        transform: scale(0) rotate(0deg);
        opacity: 1;
    }
    100% {
        transform: scale(2) rotate(180deg);
        opacity: 0;
    }
}

.combo-display {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    font-size: 4rem;
    font-weight: bold;
    color: #ffd700;
    text-shadow: 0 0 20px rgba(255, 215, 0, 0.8);
    animation: comboAnimation 1s ease-out forwards;
    pointer-events: none;
    z-index: 999;
}

@keyframes comboAnimation {
    0% {
        transform: translate(-50%, -50%) scale(0);
        opacity: 0;
    }
    50% {
        transform: translate(-50%, -50%) scale(1.2);
        opacity: 1;
    }
    100% {
        transform: translate(-50%, -100%) scale(1);
        opacity: 0;
    }
}

#input-feedback {
    font-size: 1.2rem;
    font-weight: bold;
    transition: all 0.3s;
}

#input-feedback.correct {
    color: #00ff00;
    text-shadow: 0 0 10px rgba(0, 255, 0, 0.8);
}

#input-feedback.wrong {
    color: #ff0000;
    text-shadow: 0 0 10px rgba(255, 0, 0, 0.8);
}
</style>

<script>
let gameState = {
    isPlaying: false,
    isPaused: false,
    score: 0,
    lives: 3,
    combo: 0,
    maxCombo: 0,
    correctWords: 0,
    missedWords: 0,
    totalWords: 0,
    difficulty: 'intermediate',
    speed: 'normal',
    powerupsEnabled: true,
    words: [],
    activeWords: [],
    powerups: [],
    activePowerups: [],
    currentTargetWord: null,
    startTime: null,
    gameTimer: null,
    spawnTimer: null
};

// Configuración de velocidades (ajustadas para mejor jugabilidad)
const speedConfig = {
    slow: { wordFallDuration: 12000, spawnInterval: 4000 },
    normal: { wordFallDuration: 9000, spawnInterval: 3000 },
    fast: { wordFallDuration: 6000, spawnInterval: 2000 }
};

// Power-ups disponibles
const powerupTypes = [
    { id: 'slow', icon: '🐌', name: 'Slow Motion', effect: 'Reduce velocidad 50%', duration: 10000 },
    { id: 'freeze', icon: '❄️', name: 'Freeze', effect: 'Congela palabras', duration: 5000 },
    { id: 'double', icon: '✨', name: 'Double Points', effect: 'Puntos x2', duration: 15000 },
    { id: 'shield', icon: '🛡️', name: 'Shield', effect: 'Protege de 1 error', duration: 30000 }
];

document.addEventListener('DOMContentLoaded', function() {
    // Event listeners
    document.getElementById('start-game-btn').addEventListener('click', startGame);
    document.getElementById('word-input').addEventListener('input', handleInput);
    document.getElementById('word-input').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            checkAnswer();
        }
    });

    // Cargar vocabulario
    loadVocabulary();
});

async function loadVocabulary() {
    try {
        const response = await fetch('{{ route("games.vocabulary-shooter.words") }}');
        const data = await response.json();
        gameState.words = data.words;
        console.log(`Vocabulario cargado: ${gameState.words.length} palabras`);
    } catch (error) {
        console.error('Error cargando vocabulario:', error);
        // Vocabulario de respaldo
        gameState.words = [
            { spanish: 'casa', english: 'house', level: 'basic' },
            { spanish: 'perro', english: 'dog', level: 'basic' },
            { spanish: 'gato', english: 'cat', level: 'basic' }
        ];
    }
}

function startGame() {
    // Obtener configuración
    gameState.difficulty = document.getElementById('difficulty-level').value;
    const speedSetting = document.getElementById('initial-speed').value;
    gameState.speed = speedSetting;
    gameState.powerupsEnabled = document.getElementById('powerups-enabled').value === 'true';

    // Reset estado
    gameState.isPlaying = true;
    gameState.isPaused = false;
    gameState.score = 0;
    gameState.lives = 3;
    gameState.combo = 0;
    gameState.maxCombo = 0;
    gameState.correctWords = 0;
    gameState.missedWords = 0;
    gameState.totalWords = 0;
    gameState.activeWords = [];
    gameState.activePowerups = [];
    gameState.currentTargetWord = null;
    gameState.startTime = Date.now();

    // Filtrar palabras por dificultad
    const allWords = [...gameState.words];
    gameState.words = allWords.filter(w => w.level === gameState.difficulty);

    // UI
    document.getElementById('game-config').style.display = 'none';
    document.getElementById('game-area').style.display = 'block';
    document.getElementById('word-input').focus();

    // Limpiar contenedores
    document.getElementById('falling-words-container').innerHTML = '';
    document.getElementById('powerups-container').innerHTML = '';

    // Actualizar display
    updateDisplay();

    // Reproducir sonido de inicio
    playSound('game_start');

    // Iniciar spawning
    spawnWord();
    gameState.spawnTimer = setInterval(() => {
        if (!gameState.isPaused) {
            spawnWord();
            // Spawn power-up ocasionalmente
            if (gameState.powerupsEnabled && Math.random() < 0.15) {
                spawnPowerup();
            }
        }
    }, speedConfig[gameState.speed].spawnInterval);

    // Timer del juego
    gameState.gameTimer = setInterval(updateTimer, 1000);
}

function spawnWord() {
    if (gameState.words.length === 0) return;

    const word = gameState.words[Math.floor(Math.random() * gameState.words.length)];
    const wordId = Date.now() + Math.random();

    const wordElement = document.createElement('div');
    wordElement.className = 'falling-word';
    wordElement.id = `word-${wordId}`;
    wordElement.textContent = word.spanish;
    wordElement.style.left = `${Math.random() * 80 + 10}%`;
    wordElement.style.animationDuration = `${speedConfig[gameState.speed].wordFallDuration}ms`;

    document.getElementById('falling-words-container').appendChild(wordElement);

    const wordData = {
        id: wordId,
        element: wordElement,
        spanish: word.spanish,
        english: word.english,
        spawnTime: Date.now()
    };

    gameState.activeWords.push(wordData);
    gameState.totalWords++;

    // Cuando la palabra llega al suelo
    setTimeout(() => {
        if (gameState.activeWords.find(w => w.id === wordId)) {
            missWord(wordId);
        }
    }, speedConfig[gameState.speed].wordFallDuration);
}

function spawnPowerup() {
    const powerup = powerupTypes[Math.floor(Math.random() * powerupTypes.length)];
    const powerupId = Date.now() + Math.random();

    const powerupElement = document.createElement('div');
    powerupElement.className = 'power-up';
    powerupElement.id = `powerup-${powerupId}`;
    powerupElement.textContent = powerup.icon;
    powerupElement.title = `${powerup.name}: ${powerup.effect}`;
    powerupElement.style.left = `${Math.random() * 80 + 10}%`;
    powerupElement.style.animationDuration = `${speedConfig[gameState.speed].wordFallDuration * 1.5}ms`;

    powerupElement.addEventListener('click', () => activatePowerup(powerup, powerupId));

    document.getElementById('falling-words-container').appendChild(powerupElement);

    // Auto-remove si no se recoge
    setTimeout(() => {
        if (document.getElementById(`powerup-${powerupId}`)) {
            powerupElement.remove();
        }
    }, speedConfig[gameState.speed].wordFallDuration * 1.5);
}

function activatePowerup(powerup, powerupId) {
    const element = document.getElementById(`powerup-${powerupId}`);
    if (!element) return;

    element.remove();
    playSound('success');

    // Aplicar efecto
    gameState.activePowerups.push({
        type: powerup.id,
        endTime: Date.now() + powerup.duration
    });

    // Mostrar notificación
    showNotification(`${powerup.icon} ${powerup.name} activado!`, 'success');

    // Aplicar efectos visuales según el power-up
    // (implementación simplificada)
}

function handleInput(e) {
    const input = e.target.value.toLowerCase().trim();
    if (!input) {
        // Limpiar palabra activa si el input está vacío
        if (gameState.currentTargetWord) {
            gameState.currentTargetWord.element.classList.remove('active');
            gameState.currentTargetWord = null;
        }
        return;
    }

    // Buscar palabra que coincida exactamente
    const exactMatch = gameState.activeWords.find(w => 
        w.english.toLowerCase() === input
    );

    if (exactMatch) {
        // Auto-submit cuando la palabra está completa
        hitWord(exactMatch);
        e.target.value = '';
        showFeedback('¡Correcto! +' + calculatePoints(), 'correct');
        gameState.currentTargetWord = null;
        return;
    }

    // Buscar palabra que coincida parcialmente
    const matchingWord = gameState.activeWords.find(w => 
        w.english.toLowerCase().startsWith(input)
    );

    if (matchingWord) {
        // Remover clase active de la palabra anterior si existe
        if (gameState.currentTargetWord && gameState.currentTargetWord.id !== matchingWord.id) {
            gameState.currentTargetWord.element.classList.remove('active');
        }
        matchingWord.element.classList.add('active');
        gameState.currentTargetWord = matchingWord;
    } else if (gameState.currentTargetWord) {
        gameState.currentTargetWord.element.classList.remove('active');
        gameState.currentTargetWord = null;
    }
}

function checkAnswer() {
    const input = document.getElementById('word-input').value.toLowerCase().trim();
    if (!input) return;

    if (gameState.currentTargetWord && gameState.currentTargetWord.english.toLowerCase() === input) {
        // ¡Correcto!
        hitWord(gameState.currentTargetWord);
        document.getElementById('word-input').value = '';
        showFeedback('¡Correcto! +' + calculatePoints(), 'correct');
    } else {
        // Incorrecto
        showFeedback('❌ Intenta de nuevo', 'wrong');
        setTimeout(() => {
            document.getElementById('input-feedback').textContent = '';
            document.getElementById('input-feedback').className = '';
        }, 1000);
    }
}

function hitWord(wordData) {
    // Eliminar palabra
    wordData.element.remove();
    gameState.activeWords = gameState.activeWords.filter(w => w.id !== wordData.id);

    // Incrementar combo
    gameState.combo++;
    if (gameState.combo > gameState.maxCombo) {
        gameState.maxCombo = gameState.combo;
    }

    // Añadir puntos
    const points = calculatePoints();
    gameState.score += points;
    gameState.correctWords++;

    // Efectos visuales
    createExplosion(wordData.element.offsetLeft, wordData.element.offsetTop);
    playSound('success');

    // Mostrar combo si es alto
    if (gameState.combo >= 5) {
        showCombo(gameState.combo);
    }

    updateDisplay();
}

function missWord(wordId) {
    const word = gameState.activeWords.find(w => w.id === wordId);
    if (!word) return;

    word.element.remove();
    gameState.activeWords = gameState.activeWords.filter(w => w.id !== wordId);

    // Verificar si tiene shield activo
    const shieldIndex = gameState.activePowerups.findIndex(p => p.type === 'shield');
    if (shieldIndex !== -1) {
        gameState.activePowerups.splice(shieldIndex, 1);
        showNotification('🛡️ Shield absorbió el daño!', 'info');
        return;
    }

    // Perder vida
    gameState.lives--;
    gameState.missedWords++;
    gameState.combo = 0;

    playSound('error');
    updateDisplay();

    // Game over si no hay vidas
    if (gameState.lives <= 0) {
        gameOver();
    }
}

function calculatePoints() {
    let basePoints = 10;
    
    // Bonus por combo
    const comboBonus = gameState.combo * 2;
    
    // Bonus por double points power-up
    const hasDouble = gameState.activePowerups.some(p => p.type === 'double' && p.endTime > Date.now());
    const multiplier = hasDouble ? 2 : 1;

    return (basePoints + comboBonus) * multiplier;
}

function updateDisplay() {
    document.getElementById('score').textContent = gameState.score;
    document.getElementById('combo').textContent = gameState.combo;
    document.getElementById('correct-count').textContent = gameState.correctWords;
    document.getElementById('missed-count').textContent = gameState.missedWords;
    
    // Vidas
    const hearts = '❤️'.repeat(gameState.lives) + '🖤'.repeat(3 - gameState.lives);
    document.getElementById('lives-display').textContent = hearts;

    // Precisión
    const total = gameState.correctWords + gameState.missedWords;
    const accuracy = total > 0 ? Math.round((gameState.correctWords / total) * 100) : 100;
    document.getElementById('accuracy').textContent = accuracy + '%';
}

function updateTimer() {
    if (gameState.isPaused) return;
    
    const elapsed = Math.floor((Date.now() - gameState.startTime) / 1000);
    const minutes = Math.floor(elapsed / 60);
    const seconds = elapsed % 60;
    document.getElementById('timer').textContent = `${minutes}:${seconds.toString().padStart(2, '0')}`;
}

function createExplosion(x, y) {
    const explosion = document.createElement('div');
    explosion.className = 'explosion';
    explosion.textContent = '💥';
    explosion.style.left = x + 'px';
    explosion.style.top = y + 'px';
    document.getElementById('falling-words-container').appendChild(explosion);

    setTimeout(() => explosion.remove(), 500);
}

function showCombo(combo) {
    const comboDisplay = document.createElement('div');
    comboDisplay.className = 'combo-display';
    comboDisplay.textContent = `${combo}x COMBO!`;
    document.getElementById('falling-words-container').appendChild(comboDisplay);

    setTimeout(() => comboDisplay.remove(), 1000);
}

function showFeedback(message, type) {
    const feedback = document.getElementById('input-feedback');
    feedback.textContent = message;
    feedback.className = type;

    setTimeout(() => {
        feedback.textContent = '';
        feedback.className = '';
    }, 1000);
}

function showNotification(message, type) {
    // Implementación simple con alert temporal
    // TODO: Integrar con sistema de notificaciones existente
    console.log(`[${type}] ${message}`);
}

function pauseGame() {
    gameState.isPaused = true;
    const modal = new bootstrap.Modal(document.getElementById('pauseModal'));
    modal.show();
}

function resumeGame() {
    gameState.isPaused = false;
    bootstrap.Modal.getInstance(document.getElementById('pauseModal')).hide();
    document.getElementById('word-input').focus();
}

function restartGame() {
    // Limpiar timers
    clearInterval(gameState.spawnTimer);
    clearInterval(gameState.gameTimer);

    // Ocultar modales
    const pauseModal = bootstrap.Modal.getInstance(document.getElementById('pauseModal'));
    const gameOverModal = bootstrap.Modal.getInstance(document.getElementById('gameOverModal'));
    if (pauseModal) pauseModal.hide();
    if (gameOverModal) gameOverModal.hide();

    // Limpiar backdrops
    document.querySelectorAll('.modal-backdrop').forEach(b => b.remove());
    document.body.classList.remove('modal-open');
    document.body.style.overflow = '';

    // Reiniciar
    setTimeout(() => startGame(), 300);
}

function exitGame() {
    window.location.href = '{{ route("games.index") }}';
}

function gameOver() {
    gameState.isPlaying = false;

    // Detener timers
    clearInterval(gameState.spawnTimer);
    clearInterval(gameState.gameTimer);

    // Calcular tiempo jugado
    const timePlayedSeconds = Math.floor((Date.now() - gameState.startTime) / 1000);
    const accuracy = gameState.totalWords > 0 ? Math.round((gameState.correctWords / gameState.totalWords) * 100) : 0;

    // Guardar resultado
    saveGameResult(timePlayedSeconds, accuracy);

    // Mostrar modal
    document.getElementById('final-score').textContent = gameState.score;
    document.getElementById('final-accuracy').textContent = accuracy + '%';
    document.getElementById('final-correct').textContent = gameState.correctWords;

    playSound('victory');

    setTimeout(() => {
        const modal = new bootstrap.Modal(document.getElementById('gameOverModal'));
        modal.show();
    }, 500);
}

function saveGameResult(timePlayedSeconds, accuracy) {
    const data = {
        game_type: 'vocabulary_shooter',
        score: gameState.score,
        correct_answers: gameState.correctWords,
        total_questions: gameState.totalWords,
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
            
            // Actualizar barra de XP en el navbar
            updateNavbarXP(result.total_xp, result.level, result.current_xp, result.required_xp);
        }
    })
    .catch(error => console.error('Error:', error));
}

function updateNavbarXP(totalXP, level, currentXP, requiredXP) {
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
    
    // Actualizar barra de progreso
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
</script>
@endsection
