@extends('layout.app')

@section('title', $pageTitle ?? 'Modo Supervivencia - TypeMaster AI')

@section('content')
<div class="container-fluid px-4">
    <!-- Header Section -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-0 bg-danger text-white survival-header">
                <div class="card-body p-4">
                    <div class="row align-items-center">
                        <div class="col-lg-8">
                            <h1 class="display-6 fw-bold mb-3 animated-title">
                                <i class="fas fa-skull-crossbones me-3 skull-icon"></i>MODO SUPERVIVENCIA
                            </h1>
                            <p class="lead mb-3">
                                🔥 ¡Sobrevive a oleadas de palabras cada vez más difíciles! 
                                Tienes 3 vidas, enemigos poderosos y jefes épicos te esperan.
                            </p>
                            <div class="d-flex flex-wrap gap-3">
                                <div class="d-flex align-items-center pulse-effect">
                                    <i class="fas fa-heart me-2 text-danger"></i>
                                    <span>3 Vidas</span>
                                </div>
                                <div class="d-flex align-items-center pulse-effect">
                                    <i class="fas fa-fire me-2 text-warning"></i>
                                    <span>Dificultad Progresiva</span>
                                </div>
                                <div class="d-flex align-items-center pulse-effect">
                                    <i class="fas fa-dragon me-2 text-success"></i>
                                    <span>Jefes Épicos</span>
                                </div>
                                <div class="d-flex align-items-center pulse-effect">
                                    <i class="fas fa-star me-2 text-info"></i>
                                    <span>XP: 1.5x</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-4 text-center">
                            <a href="{{ route('typing.modes') }}" class="btn btn-outline-light mb-2">
                                <i class="fas fa-arrow-left me-2"></i>Volver a Modos
                            </a>
                            <div class="best-score-display mt-3">
                                <h5>🏆 Mejor Puntuación</h5>
                                <div class="score-number">{{ App\Models\TypingSession::getBestScoreByMode($user->id, 'survival') }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Game Selection -->
    <div class="row">
        <div class="col-12">
            <div class="card border-0 shadow-lg survival-game-card">
                <div class="card-header bg-dark text-white text-center">
                    <h3 class="mb-0">
                        <i class="fas fa-swords me-2"></i>¿ESTÁS LISTO PARA EL DESAFÍO?
                    </h3>
                </div>
                <div class="card-body text-center p-5">
                    <div class="row">
                        <!-- Dificultad Fácil -->
                        <div class="col-lg-4 mb-4">
                            <div class="difficulty-card easy-mode" data-difficulty="easy">
                                <div class="difficulty-icon">
                                    <i class="fas fa-seedling"></i>
                                </div>
                                <h4 class="difficulty-title">🌱 NOVATO</h4>
                                <p class="difficulty-desc">
                                    Perfecto para empezar. Palabras simples y velocidad moderada.
                                </p>
                                <div class="difficulty-stats">
                                    <div><i class="fas fa-clock"></i> 30 WPM objetivo</div>
                                    <div><i class="fas fa-wave-square"></i> 5 oleadas</div>
                                    <div><i class="fas fa-crown"></i> 1 jefe final</div>
                                </div>
                                <button class="btn btn-success btn-lg w-100 start-survival" data-difficulty="easy">
                                    <i class="fas fa-play me-2"></i>COMENZAR AVENTURA
                                </button>
                            </div>
                        </div>

                        <!-- Dificultad Normal -->
                        <div class="col-lg-4 mb-4">
                            <div class="difficulty-card normal-mode" data-difficulty="normal">
                                <div class="difficulty-icon">
                                    <i class="fas fa-fire"></i>
                                </div>
                                <h4 class="difficulty-title">🔥 GUERRERO</h4>
                                <p class="difficulty-desc">
                                    El desafío real comienza. Palabras complejas y ritmo acelerado.
                                </p>
                                <div class="difficulty-stats">
                                    <div><i class="fas fa-clock"></i> 45 WPM objetivo</div>
                                    <div><i class="fas fa-wave-square"></i> 8 oleadas</div>
                                    <div><i class="fas fa-crown"></i> 2 jefes finales</div>
                                </div>
                                <button class="btn btn-warning btn-lg w-100 start-survival" data-difficulty="normal">
                                    <i class="fas fa-sword me-2"></i>ENTRAR EN BATALLA
                                </button>
                            </div>
                        </div>

                        <!-- Dificultad Difícil -->
                        <div class="col-lg-4 mb-4">
                            <div class="difficulty-card hard-mode" data-difficulty="hard">
                                <div class="difficulty-icon">
                                    <i class="fas fa-skull"></i>
                                </div>
                                <h4 class="difficulty-title">💀 LEYENDA</h4>
                                <p class="difficulty-desc">
                                    Solo para expertos. Textos técnicos y velocidad extrema.
                                </p>
                                <div class="difficulty-stats">
                                    <div><i class="fas fa-clock"></i> 60 WPM objetivo</div>
                                    <div><i class="fas fa-wave-square"></i> 12 oleadas</div>
                                    <div><i class="fas fa-crown"></i> 3 jefes épicos</div>
                                </div>
                                <button class="btn btn-danger btn-lg w-100 start-survival" data-difficulty="hard">
                                    <i class="fas fa-bolt me-2"></i>DESAFÍO ÉPICO
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Instructions -->
                    <div class="instructions-panel mt-4">
                        <h5><i class="fas fa-info-circle me-2"></i>INSTRUCCIONES DE SUPERVIVENCIA</h5>
                        <div class="row text-start">
                            <div class="col-md-6">
                                <ul class="instruction-list">
                                    <li><i class="fas fa-heart text-danger"></i> <strong>Vidas:</strong> Tienes 3 vidas. Cada error grave te quita una vida.</li>
                                    <li><i class="fas fa-target text-warning"></i> <strong>Precisión:</strong> Mantén >90% de precisión para sobrevivir.</li>
                                    <li><i class="fas fa-clock text-info"></i> <strong>Tiempo:</strong> Cada oleada tiene tiempo límite.</li>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <ul class="instruction-list">
                                    <li><i class="fas fa-level-up-alt text-success"></i> <strong>Oleadas:</strong> La dificultad aumenta progresivamente.</li>
                                    <li><i class="fas fa-dragon text-purple"></i> <strong>Jefes:</strong> Textos súper complejos al final de cada nivel.</li>
                                    <li><i class="fas fa-gem text-primary"></i> <strong>Bonus:</strong> Palabras perfectas dan puntos extra.</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Survival Game Modal -->
<div class="modal fade" id="survivalModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-fullscreen">
        <div class="modal-content bg-dark text-white">
            <div class="modal-header border-danger bg-gradient-dark">
                <h5 class="modal-title" id="survivalModalTitle">
                    <i class="fas fa-skull-crossbones me-2"></i>MODO SUPERVIVENCIA
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body survival-arena">
                <!-- Game UI -->
                <div class="survival-ui">
                    <!-- Top Stats Bar -->
                    <div class="stats-bar">
                        <div class="row">
                            <div class="col-md-2">
                                <div class="stat-display lives-display">
                                    <div class="stat-icon"><i class="fas fa-heart"></i></div>
                                    <div class="stat-info">
                                        <div class="stat-label">VIDAS</div>
                                        <div class="stat-value" id="livesCount">3</div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="stat-display wave-display">
                                    <div class="stat-icon"><i class="fas fa-wave-square"></i></div>
                                    <div class="stat-info">
                                        <div class="stat-label">OLEADA</div>
                                        <div class="stat-value" id="currentWave">1</div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="stat-display score-display">
                                    <div class="stat-icon"><i class="fas fa-trophy"></i></div>
                                    <div class="stat-info">
                                        <div class="stat-label">PUNTOS</div>
                                        <div class="stat-value" id="currentScore">0</div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="stat-display wpm-display">
                                    <div class="stat-icon"><i class="fas fa-tachometer-alt"></i></div>
                                    <div class="stat-info">
                                        <div class="stat-label">WPM</div>
                                        <div class="stat-value" id="currentWPM">0</div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="stat-display accuracy-display">
                                    <div class="stat-icon"><i class="fas fa-bullseye"></i></div>
                                    <div class="stat-info">
                                        <div class="stat-label">PRECISIÓN</div>
                                        <div class="stat-value" id="currentAccuracy">100%</div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="stat-display time-display">
                                    <div class="stat-icon"><i class="fas fa-clock"></i></div>
                                    <div class="stat-info">
                                        <div class="stat-label">TIEMPO</div>
                                        <div class="stat-value" id="timeLeft">60</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Game Status -->
                    <div class="game-status" id="gameStatus">
                        <div class="status-message pulse-effect">
                            <h3 id="statusText">¡PREPÁRATE PARA LA BATALLA!</h3>
                            <p id="statusSubtext">La primera oleada está por comenzar...</p>
                        </div>
                    </div>

                    <!-- Enemy Display -->
                    <div class="enemy-display" id="enemyDisplay" style="display: none;">
                        <div class="enemy-container">
                            <div class="enemy-image">
                                <img id="enemyImg" src="" alt="Enemy" class="enemy-sprite">
                            </div>
                            <div class="enemy-info">
                                <h4 id="enemyName">Enemigo</h4>
                                <div class="enemy-health-bar">
                                    <div class="health-fill" id="enemyHealth" style="width: 100%"></div>
                                </div>
                                <p id="enemyDesc">Descripción del enemigo</p>
                            </div>
                        </div>
                    </div>

                    <!-- Typing Area -->
                    <div class="typing-battlefield" id="typingBattlefield" style="display: none;">
                        <div class="text-target">
                            <div id="challengeText" class="challenge-text">
                                Texto de desafío aparecerá aquí...
                            </div>
                        </div>
                        <div class="typing-input-container">
                            <input type="text" id="survivalInput" class="survival-input" 
                                   placeholder="¡Escribe para atacar!" 
                                   autocomplete="off" spellcheck="false">
                        </div>
                        <div class="progress-indicator">
                            <div class="progress-bar" id="challengeProgress" style="width: 0%"></div>
                        </div>
                    </div>

                    <!-- Effects Layer -->
                    <div class="effects-layer" id="effectsLayer"></div>
                </div>
            </div>
            <div class="modal-footer border-danger bg-gradient-dark">
                <button type="button" class="btn btn-secondary" onclick="exitSurvivalGame()">
                    <i class="fas fa-flag-white me-2"></i>Rendirse y Salir
                </button>
                <button type="button" class="btn btn-danger" id="pauseGame">
                    <i class="fas fa-pause me-2"></i>Pausar
                </button>
                <button type="button" class="btn btn-success" id="startBattle" style="display: none;">
                    <i class="fas fa-play me-2"></i>¡COMENZAR BATALLA!
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Game Over Modal -->
<div class="modal fade" id="gameOverModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content bg-dark text-white border-danger">
            <div class="modal-header bg-danger">
                <h5 class="modal-title">
                    <i id="gameOverIcon" class="fas fa-skull me-2"></i>
                    <span id="gameOverTitle">GAME OVER</span>
                </h5>
            </div>
            <div class="modal-body text-center">
                <div id="gameOverContent">
                    <!-- Se llenará dinámicamente -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Salir</button>
                <button type="button" class="btn btn-danger" id="playAgain">
                    <i class="fas fa-redo me-2"></i>Jugar de Nuevo
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Audio Elements -->
<audio id="backgroundMusic" loop>
    <source src="{{ asset('audio/survival-background.mp3') }}" type="audio/mpeg">
</audio>
<audio id="typingSound">
    <source src="{{ asset('audio/keyboard-click.mp3') }}" type="audio/mpeg">
</audio>
<audio id="errorSound">
    <source src="{{ asset('audio/error.mp3') }}" type="audio/mpeg">
</audio>
<audio id="successSound">
    <source src="{{ asset('audio/success.mp3') }}" type="audio/mpeg">
</audio>
<audio id="levelUpSound">
    <source src="{{ asset('audio/level-up.mp3') }}" type="audio/mpeg">
</audio>
<audio id="bossSound">
    <source src="{{ asset('audio/boss-appear.mp3') }}" type="audio/mpeg">
</audio>
<audio id="victorySound">
    <source src="{{ asset('audio/victory.mp3') }}" type="audio/mpeg">
</audio>
<audio id="gameOverSound">
    <source src="{{ asset('audio/game-over.mp3') }}" type="audio/mpeg">
</audio>

<style>
/* Animaciones principales */
@keyframes pulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.05); }
    100% { transform: scale(1); }
}

@keyframes glow {
    0% { box-shadow: 0 0 5px #ff0000; }
    50% { box-shadow: 0 0 20px #ff0000, 0 0 30px #ff0000; }
    100% { box-shadow: 0 0 5px #ff0000; }
}

@keyframes shake {
    0%, 100% { transform: translateX(0); }
    25% { transform: translateX(-5px); }
    75% { transform: translateX(5px); }
}

@keyframes float {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-10px); }
}

.pulse-effect {
    animation: pulse 2s infinite;
}

.glow-effect {
    animation: glow 2s infinite alternate;
}

.shake-effect {
    animation: shake 0.5s;
}

.float-effect {
    animation: float 3s infinite ease-in-out;
}

/* Header styling */
.survival-header {
    background: linear-gradient(45deg, #dc3545, #7d1320);
    position: relative;
    overflow: hidden;
}

.survival-header::before {
    content: '';
    position: absolute;
    top: -50%;
    left: -50%;
    width: 200%;
    height: 200%;
    background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="50" cy="50" r="2" fill="rgba(255,255,255,0.1)"/></svg>') repeat;
    animation: float 20s linear infinite;
}

.animated-title {
    position: relative;
    z-index: 2;
}

.skull-icon {
    animation: pulse 1.5s infinite;
}

/* Difficulty cards */
.difficulty-card {
    background: linear-gradient(145deg, #2c3e50, #34495e);
    border-radius: 15px;
    padding: 30px 20px;
    margin: 10px;
    cursor: pointer;
    transition: all 0.3s ease;
    border: 3px solid transparent;
    position: relative;
    overflow: hidden;
}

.difficulty-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
    transition: left 0.5s;
}

.difficulty-card:hover::before {
    left: 100%;
}

.easy-mode:hover {
    border-color: #28a745;
    transform: translateY(-10px);
    box-shadow: 0 15px 30px rgba(40, 167, 69, 0.3);
}

.normal-mode:hover {
    border-color: #ffc107;
    transform: translateY(-10px);
    box-shadow: 0 15px 30px rgba(255, 193, 7, 0.3);
}

.hard-mode:hover {
    border-color: #dc3545;
    transform: translateY(-10px);
    box-shadow: 0 15px 30px rgba(220, 53, 69, 0.3);
}

.difficulty-icon {
    font-size: 4rem;
    margin-bottom: 20px;
    color: #fff;
}

.difficulty-title {
    color: #fff;
    margin-bottom: 15px;
    font-weight: bold;
}

.difficulty-desc {
    color: #bdc3c7;
    margin-bottom: 20px;
    font-size: 0.9rem;
}

.difficulty-stats {
    margin-bottom: 25px;
}

.difficulty-stats div {
    margin-bottom: 8px;
    color: #ecf0f1;
    font-size: 0.85rem;
}

/* Best score display */
.best-score-display {
    background: rgba(0,0,0,0.3);
    padding: 15px;
    border-radius: 10px;
    border: 2px solid rgba(255,255,255,0.2);
}

.score-number {
    font-size: 2rem;
    font-weight: bold;
    color: #ffc107;
}

/* Instructions */
.instructions-panel {
    background: linear-gradient(145deg, #1a1a1a, #2d2d2d);
    border-radius: 15px;
    padding: 25px;
    border: 2px solid #495057;
}

.instruction-list {
    list-style: none;
    padding: 0;
}

.instruction-list li {
    margin-bottom: 10px;
    padding: 8px 0;
    color: #ecf0f1;
}

/* Survival Arena */
.survival-arena {
    background: 
        radial-gradient(circle at 20% 50%, rgba(120, 20, 20, 0.3) 0%, transparent 50%),
        radial-gradient(circle at 80% 50%, rgba(20, 20, 120, 0.3) 0%, transparent 50%),
        linear-gradient(145deg, #0d1117, #1a1a1a);
    min-height: 100vh;
    position: relative;
    overflow: hidden;
}

/* Stats bar */
.stats-bar {
    background: linear-gradient(145deg, #2c3e50, #34495e);
    padding: 15px 20px;
    border-radius: 10px;
    margin-bottom: 20px;
    border: 2px solid #495057;
}

.stat-display {
    display: flex;
    align-items: center;
    background: rgba(0,0,0,0.3);
    padding: 12px;
    border-radius: 8px;
    border: 1px solid rgba(255,255,255,0.1);
    transition: all 0.3s ease;
}

.stat-display:hover {
    transform: scale(1.05);
    border-color: rgba(255,255,255,0.3);
}

.stat-icon {
    font-size: 1.5rem;
    margin-right: 12px;
    min-width: 30px;
}

.stat-label {
    font-size: 0.7rem;
    color: #bdc3c7;
    margin-bottom: 2px;
}

.stat-value {
    font-size: 1.2rem;
    font-weight: bold;
    color: #fff;
}

.lives-display .stat-icon { color: #e74c3c; }
.wave-display .stat-icon { color: #3498db; }
.score-display .stat-icon { color: #f39c12; }
.wpm-display .stat-icon { color: #e67e22; }
.accuracy-display .stat-icon { color: #27ae60; }
.time-display .stat-icon { color: #e74c3c; }

/* Game status */
.game-status {
    text-align: center;
    padding: 40px 20px;
    background: rgba(0,0,0,0.5);
    border-radius: 15px;
    margin-bottom: 20px;
    border: 2px solid #495057;
}

.status-message h3 {
    color: #e74c3c;
    font-size: 2.5rem;
    margin-bottom: 15px;
    text-shadow: 0 0 10px rgba(231, 76, 60, 0.5);
}

.status-message p {
    color: #bdc3c7;
    font-size: 1.2rem;
}

/* Enemy display */
.enemy-display {
    text-align: center;
    padding: 30px;
    background: rgba(220, 53, 69, 0.1);
    border-radius: 15px;
    border: 2px solid #dc3545;
    margin-bottom: 20px;
}

.enemy-container {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 30px;
}

.enemy-image {
    flex-shrink: 0;
}

.enemy-sprite {
    width: 120px;
    height: 120px;
    border-radius: 50%;
    border: 3px solid #dc3545;
    animation: float 3s infinite ease-in-out;
}

.enemy-info {
    flex-grow: 1;
    text-align: left;
}

.enemy-info h4 {
    color: #e74c3c;
    font-size: 1.8rem;
    margin-bottom: 10px;
}

.enemy-health-bar {
    width: 100%;
    height: 20px;
    background: #2c3e50;
    border-radius: 10px;
    overflow: hidden;
    margin-bottom: 15px;
    border: 2px solid #dc3545;
}

.health-fill {
    height: 100%;
    background: linear-gradient(90deg, #e74c3c, #c0392b);
    transition: width 0.3s ease;
    border-radius: 8px;
}

/* Typing battlefield */
.typing-battlefield {
    background: rgba(0,0,0,0.7);
    border-radius: 15px;
    padding: 30px;
    border: 2px solid #495057;
}

.text-target {
    background: #1a1a1a;
    border-radius: 10px;
    padding: 25px;
    margin-bottom: 20px;
    border: 2px solid #495057;
    min-height: 150px;
}

.challenge-text {
    font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
    font-size: 1.3rem;
    line-height: 1.8;
    color: #ecf0f1;
    text-align: left;
}

.survival-input {
    width: 100%;
    background: #2c3e50;
    border: 3px solid #495057;
    border-radius: 10px;
    padding: 15px 20px;
    font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
    font-size: 1.3rem;
    color: #ecf0f1;
    outline: none;
    transition: all 0.3s ease;
}

.survival-input:focus {
    border-color: #e74c3c;
    box-shadow: 0 0 15px rgba(231, 76, 60, 0.3);
}

.progress-indicator {
    margin-top: 15px;
    height: 8px;
    background: #2c3e50;
    border-radius: 4px;
    overflow: hidden;
}

.progress-bar {
    height: 100%;
    background: linear-gradient(90deg, #27ae60, #2ecc71);
    transition: width 0.3s ease;
    border-radius: 4px;
}

/* Effects layer */
.effects-layer {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    pointer-events: none;
    z-index: 9999;
}

/* Responsive */
@media (max-width: 768px) {
    .enemy-container {
        flex-direction: column;
        text-align: center;
    }
    
    .enemy-info {
        text-align: center;
    }
    
    .stats-bar .row > div {
        margin-bottom: 10px;
    }
    
    .challenge-text {
        font-size: 1.1rem;
    }
    
    .survival-input {
        font-size: 1.1rem;
    }
}

/* Text highlighting for typing */
.char-correct {
    background-color: rgba(46, 204, 113, 0.3);
    color: #2ecc71;
}

.char-incorrect {
    background-color: rgba(231, 76, 60, 0.3);
    color: #e74c3c;
}

.char-current {
    background-color: #3498db;
    color: white;
    animation: pulse 1s infinite;
}

.char-pending {
    color: #bdc3c7;
}

/* Error warning styles */
.error-warning {
    margin-top: 10px;
    padding: 8px 12px;
    border-radius: 5px;
    font-weight: bold;
    text-align: center;
    transition: all 0.3s ease;
}

.error-warning-normal {
    background-color: rgba(255, 193, 7, 0.2);
    color: #ffc107;
    border: 1px solid #ffc107;
}

.error-warning-high {
    background-color: rgba(255, 152, 0, 0.3);
    color: #ff9800;
    border: 1px solid #ff9800;
    animation: pulse-warning 1s infinite;
}

.error-critical {
    background-color: rgba(244, 67, 54, 0.3) !important;
    color: #f44336 !important;
    border: 2px solid #f44336 !important;
    animation: pulse-critical 0.5s infinite;
}

/* Input error states */
.survival-input.error-warning-shake {
    border-color: #ff9800 !important;
}

.survival-input.error-critical {
    border-color: #f44336 !important;
    box-shadow: 0 0 10px rgba(244, 67, 54, 0.5) !important;
}

/* Shake animation */
.shake-effect {
    animation: shake 0.3s ease-in-out;
}

@keyframes shake {
    0%, 100% { transform: translateX(0); }
    25% { transform: translateX(-5px); }
    75% { transform: translateX(5px); }
}

@keyframes pulse-warning {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.7; }
}

@keyframes pulse-critical {
    0%, 100% { opacity: 1; transform: scale(1); }
    50% { opacity: 0.8; transform: scale(1.02); }
}

/* Life lost effect */
.life-lost-effect {
    position: fixed;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    background: rgba(0, 0, 0, 0.9);
    color: white;
    padding: 30px;
    border-radius: 15px;
    text-align: center;
    z-index: 1000;
    border: 3px solid #f44336;
    animation: lifeLost 3s ease-out forwards;
}

.life-lost-icon {
    font-size: 3rem;
    margin-bottom: 10px;
    animation: heartbreak 0.5s ease-out;
}

.life-lost-message {
    font-size: 1.2rem;
    font-weight: bold;
    margin-bottom: 5px;
    color: #f44336;
}

.life-lost-submessage {
    font-size: 1rem;
    color: #ff9800;
}

@keyframes lifeLost {
    0% { 
        opacity: 0;
        transform: translate(-50%, -50%) scale(0.5);
    }
    20% { 
        opacity: 1;
        transform: translate(-50%, -50%) scale(1.1);
    }
    80% { 
        opacity: 1;
        transform: translate(-50%, -50%) scale(1);
    }
    100% { 
        opacity: 0;
        transform: translate(-50%, -50%) scale(0.8);
    }
}

@keyframes heartbreak {
    0% { transform: scale(1); }
    50% { transform: scale(1.3) rotate(-10deg); }
    100% { transform: scale(1) rotate(0deg); }
}
</style>

<script>
// Variables del juego
let gameState = {
    isActive: false,
    isPaused: false,
    currentWave: 1,
    lives: 3,
    score: 0,
    wpm: 0,
    accuracy: 100,
    timeElapsed: 0,
    currentEnemy: null,
    currentText: '',
    userInput: '',
    previousInput: '',
    startTime: null,
    correctChars: 0,
    totalChars: 0,
    difficulty: 'normal',
    maxWaves: 8,
    difficultyMultiplier: 1.0,
    consecutiveErrors: 0,
    maxConsecutiveErrors: 5
};

// Configuración de enemigos por oleada
const enemies = {
    1: { name: 'Slime Principiante', health: 100, text: 'el gato subió al tejado para ver las estrellas', image: 'slime.png' },
    2: { name: 'Goblin Escritor', health: 150, text: 'la programación es una forma de arte digital que transforma ideas en realidad', image: 'goblin.png' },
    3: { name: 'Esqueleto Guerrero', health: 180, text: 'en las montañas más altas donde el viento sopla con fuerza indomable', image: 'skeleton.png' },
    4: { name: 'Orc Destructor', health: 220, text: 'la inteligencia artificial revoluciona la manera en que procesamos información compleja', image: 'orc.png' },
    5: { name: 'Mago Oscuro', health: 250, text: 'mediante algoritmos sofisticados podemos resolver problemas que anteriormente parecían imposibles', image: 'wizard.png' },
    6: { name: 'Dragón Sintáctico', health: 300, text: 'en el vasto universo de la programación donde cada línea de código representa una decisión importante', image: 'dragon.png' },
    7: { name: 'Demonio del Código', health: 350, text: 'los desarrolladores experimentados entienden que la optimización prematura puede ser la raíz de todos los males', image: 'demon.png' },
    8: { name: 'Rey Tirano Final', health: 500, text: 'la implementación de sistemas distribuidos requiere un profundo entendimiento de los principios fundamentales de la computación', image: 'boss-tyrant.png' }
};

// Sonidos del juego
const sounds = {
    start: new Audio('{{ asset("audio/game_start.mp3") }}'),
    hit: new Audio('{{ asset("audio/hit.mp3") }}'),
    miss: new Audio('{{ asset("audio/miss.mp3") }}'),
    victory: new Audio('{{ asset("audio/victory.mp3") }}'),
    defeat: new Audio('{{ asset("audio/defeat.mp3") }}'),
    levelUp: new Audio('{{ asset("audio/level_up.mp3") }}'),
    background: new Audio('{{ asset("audio/survival-background.mp3") }}')
};

// Configurar volumen de sonidos
Object.values(sounds).forEach(sound => {
    sound.volume = 0.5;
    sound.addEventListener('error', (e) => {
        console.log('Audio file not found:', e.target.src);
    });
    sound.addEventListener('loadeddata', () => {
        console.log('Audio loaded successfully:', sound.src);
    });
});

// Función de test para verificar sonidos (solo para debug)
function testSounds() {
    console.log('Probando sonidos...');
    Object.keys(sounds).forEach(key => {
        console.log(`Testing sound: ${key}`);
        setTimeout(() => playSound(key), 1000 * Object.keys(sounds).indexOf(key));
    });
}

// Descomentar la siguiente línea para probar sonidos al cargar la página
// setTimeout(testSounds, 2000);

document.addEventListener('DOMContentLoaded', function() {
    // Event listeners para botones de dificultad
    document.querySelectorAll('.start-survival').forEach(button => {
        button.addEventListener('click', function() {
            const difficulty = this.getAttribute('data-difficulty');
            startSurvivalGame(difficulty);
        });
    });

    // Inicializar el juego cuando se abre el modal
    const modal = document.getElementById('survivalModal');
    modal.addEventListener('shown.bs.modal', function() {
        startCountdown();
    });

    // Event listeners del juego
    document.getElementById('survivalInput').addEventListener('input', handleTyping);
    document.getElementById('pauseGame').addEventListener('click', togglePause);
    
    // Reset game when modal closes
    modal.addEventListener('hidden.bs.modal', function() {
        resetGame();
    });
});

function startSurvivalGame(difficulty) {
    // Configurar dificultad
    gameState.difficulty = difficulty;
    
    // Configurar enemigos según dificultad
    configureDifficulty(difficulty);
    
    // Mostrar modal del juego
    const modal = new bootstrap.Modal(document.getElementById('survivalModal'));
    modal.show();
}

function configureDifficulty(difficulty) {
    switch(difficulty) {
        case 'easy':
            gameState.lives = 5; // Más vidas para principiantes
            gameState.maxWaves = 5; // Slime, Goblin, Skeleton, Orc, Wizard
            gameState.difficultyMultiplier = 0.8;
            gameState.maxConsecutiveErrors = 7; // Más errores permitidos
            break;
        case 'normal':
            gameState.lives = 3;
            gameState.maxWaves = 6; // Todos menos Dragon, Demon
            gameState.difficultyMultiplier = 1.0;
            gameState.maxConsecutiveErrors = 5; // Errores estándar
            break;
        case 'hard':
            gameState.lives = 2; // Menos vidas para expertos
            gameState.maxWaves = 8; // Todos los enemigos incluyendo jefe final
            gameState.difficultyMultiplier = 1.5;
            gameState.maxConsecutiveErrors = 3; // Muy pocos errores permitidos
            break;
        default:
            gameState.lives = 3;
            gameState.maxWaves = 6;
            gameState.difficultyMultiplier = 1.0;
            gameState.maxConsecutiveErrors = 5;
    }
    
    // Actualizar textos según dificultad
    updateEnemiesForDifficulty(difficulty);
}

function updateEnemiesForDifficulty(difficulty) {
    const easyTexts = [
        'el gato sube al techo',
        'mi casa es muy grande',
        'me gusta leer libros',
        'el sol brilla hoy',
        'voy al parque mañana',
        'la comida está rica',
        'tengo un perro negro',
        'escribir es divertido'
    ];
    
    const normalTexts = [
        'la programación es una forma de arte digital que transforma ideas en realidad',
        'en las montañas más altas donde el viento sopla con fuerza indomable',
        'la inteligencia artificial revoluciona la manera en que procesamos información',
        'mediante algoritmos sofisticados podemos resolver problemas complejos',
        'el desarrollo web moderno requiere conocimientos multidisciplinarios',
        'los patrones de diseño facilitan la creación de código mantenible',
        'la optimización de bases de datos mejora el rendimiento significativamente',
        'la arquitectura de software determina la escalabilidad del sistema'
    ];
    
    const hardTexts = [
        'mediante algoritmos sofisticados podemos resolver problemas que anteriormente parecían imposibles de abordar',
        'la implementación de sistemas distribuidos requiere consideraciones especiales sobre consistencia eventual',
        'los patrones de diseño orientados a objetos facilitan la creación de código mantenible y escalable',
        'la optimización de consultas en bases de datos relacionales implica análisis detallado de índices',
        'las arquitecturas de microservicios proporcionan flexibilidad pero introducen complejidad adicional',
        'el paradigma de programación funcional enfatiza la inmutabilidad y las funciones puras como fundamentos',
        'la computación cuántica representa un cambio paradigmático en el procesamiento de información compleja',
        'los algoritmos de aprendizaje automático requieren grandes volúmenes de datos para entrenamiento efectivo'
    ];
    
    let selectedTexts;
    switch(difficulty) {
        case 'easy':
            selectedTexts = easyTexts;
            break;
        case 'normal':
            selectedTexts = normalTexts;
            break;
        case 'hard':
            selectedTexts = hardTexts;
            break;
        default:
            selectedTexts = normalTexts;
    }
    
    // Actualizar enemigos con nuevos textos
    Object.keys(enemies).forEach((key, index) => {
        if (selectedTexts[index]) {
            enemies[key].text = selectedTexts[index];
        }
    });
}

function startCountdown() {
    const gameStatus = document.getElementById('gameStatus');
    const statusText = document.getElementById('statusText');
    const statusSubtext = document.getElementById('statusSubtext');
    
    if (!gameStatus || !statusText || !statusSubtext) {
        console.error('Elements not found for countdown');
        return;
    }
    
    let count = 3;
    
    const countdownInterval = setInterval(() => {
        if (count > 0) {
            statusText.textContent = `¡COMENZANDO EN ${count}!`;
            statusSubtext.textContent = `Prepárate para enfrentar la oleada ${gameState.currentWave}...`;
            count--;
        } else {
            clearInterval(countdownInterval);
            statusText.textContent = '¡BATALLA!';
            statusSubtext.textContent = '¡Escribe para atacar!';
            
            setTimeout(() => {
                startGame();
            }, 1000);
        }
    }, 1000);
}

function startGame() {
    gameState.isActive = true;
    gameState.startTime = Date.now();
    
    // Ocultar área de status
    document.getElementById('gameStatus').style.display = 'none';
    
    // Mostrar display del enemigo y campo de batalla
    document.getElementById('enemyDisplay').style.display = 'block';
    document.getElementById('typingBattlefield').style.display = 'block';
    
    // Cargar primer enemigo
    loadEnemy(gameState.currentWave);
    
    // Iniciar timer
    startTimer();
    
    // Enfocar input
    document.getElementById('survivalInput').focus();
    
    // Sonido de inicio y música de fondo
    playSound('start');
    playBackgroundMusic();
    
    console.log('Juego iniciado - Oleada:', gameState.currentWave);
}

function loadEnemy(wave) {
    const enemy = enemies[wave] || enemies[5]; // Usar último enemigo si superamos las oleadas definidas
    gameState.currentEnemy = { ...enemy, currentHealth: enemy.health };
    gameState.currentText = enemy.text;
    gameState.userInput = '';
    
    // Actualizar UI del enemigo
    updateEnemyDisplay();
    updateTextDisplay();
    
    console.log('Enemigo cargado:', enemy.name);
}

function updateEnemyDisplay() {
    const enemy = gameState.currentEnemy;
    document.getElementById('enemyName').textContent = enemy.name;
    document.getElementById('enemyImg').src = `{{ asset('images/enemies/') }}/${enemy.image}`;
    document.getElementById('enemyImg').alt = enemy.name;
    
    // Actualizar barra de vida
    const healthPercentage = (enemy.currentHealth / enemy.health) * 100;
    document.getElementById('enemyHealth').style.width = healthPercentage + '%';
}

function updateTextDisplay() {
    const textElement = document.getElementById('challengeText');
    const input = gameState.userInput;
    const text = gameState.currentText;
    
    let html = '';
    
    for (let i = 0; i < text.length; i++) {
        if (i < input.length) {
            if (input[i] === text[i]) {
                html += `<span class="char-correct">${text[i]}</span>`;
            } else {
                html += `<span class="char-incorrect">${text[i]}</span>`;
            }
        } else if (i === input.length) {
            html += `<span class="char-current">${text[i]}</span>`;
        } else {
            html += `<span class="char-pending">${text[i]}</span>`;
        }
    }
    
    textElement.innerHTML = html;
    
    // Actualizar progreso
    const progress = (input.length / text.length) * 100;
    document.getElementById('challengeProgress').style.width = progress + '%';
}

function handleTyping(event) {
    if (!gameState.isActive || gameState.isPaused) return;
    
    gameState.previousInput = gameState.userInput;
    gameState.userInput = event.target.value;
    gameState.totalChars = gameState.userInput.length;
    
    // Verificar si el texto completo es correcto hasta donde va
    let isTypingCorrectly = true;
    let correctSoFar = true;
    
    for (let i = 0; i < gameState.userInput.length && i < gameState.currentText.length; i++) {
        if (gameState.userInput[i] !== gameState.currentText[i]) {
            correctSoFar = false;
            break;
        }
    }
    
    // Detectar si se escribió un caracter incorrecto
    const currentChar = gameState.userInput[gameState.userInput.length - 1];
    const expectedChar = gameState.currentText[gameState.userInput.length - 1];
    
    // Manejar errores consecutivos
    if (gameState.userInput.length > gameState.previousInput.length) {
        if (currentChar !== expectedChar) {
            // Error detectado
            gameState.consecutiveErrors++;
            playSound('miss');
            showErrorWarning();
            console.log('Error detectado:', currentChar, 'vs', expectedChar, '- Errores consecutivos:', gameState.consecutiveErrors);
            
            // Verificar si pierde una vida por errores consecutivos
            if (gameState.consecutiveErrors >= gameState.maxConsecutiveErrors) {
                loseLifeFromErrors();
            }
        } else {
            // Escribió correctamente, resetear contador de errores
            gameState.consecutiveErrors = 0;
            hideErrorWarning();
            playSound('hit');
            console.log('Hit detectado - Errores consecutivos reseteados');
        }
    }
    
    // Calcular caracteres correctos
    gameState.correctChars = 0;
    for (let i = 0; i < gameState.userInput.length && i < gameState.currentText.length; i++) {
        if (gameState.userInput[i] === gameState.currentText[i]) {
            gameState.correctChars++;
        }
    }
    
    // Actualizar stats
    updateStats();
    updateTextDisplay();
    
    // Verificar si se completó el texto
    if (gameState.userInput === gameState.currentText) {
        gameState.consecutiveErrors = 0; // Reset errores al completar
        defeatEnemy();
        return;
    }
    
    // Verificar errores (reducir vida del enemigo por cada caracter correcto)
    const currentCorrect = gameState.correctChars;
    const damage = Math.floor((currentCorrect / gameState.currentText.length) * 100);
    const newHealth = gameState.currentEnemy.health - damage;
    
    if (newHealth !== gameState.currentEnemy.currentHealth) {
        gameState.currentEnemy.currentHealth = Math.max(0, newHealth);
        updateEnemyDisplay();
        
        if (gameState.correctChars > 0) {
            createHitEffect();
        }
    }
}

function defeatEnemy() {
    playSound('victory');
    createVictoryEffect();
    
    // Calcular puntuación con multiplicador de dificultad
    const timeBonus = Math.max(0, 60 - gameState.timeElapsed);
    const wpmBonus = Math.floor(gameState.wpm * 10);
    const accuracyBonus = Math.floor(gameState.accuracy * 5);
    const waveBonus = gameState.currentWave * 100;
    
    const baseScore = 500 + timeBonus + wpmBonus + accuracyBonus + waveBonus;
    const enemyScore = Math.floor(baseScore * gameState.difficultyMultiplier);
    gameState.score += enemyScore;
    
    // Limpiar input
    document.getElementById('survivalInput').value = '';
    gameState.userInput = '';
    
    // Siguiente oleada
    gameState.currentWave++;
    
    if (gameState.currentWave <= gameState.maxWaves) {
        setTimeout(() => {
            loadEnemy(gameState.currentWave);
            playSound('levelUp');
        }, 2000);
    } else {
        // Victoria total
        setTimeout(() => {
            gameWin();
        }, 2000);
    }
    
    updateStats();
}

function takeDamage() {
    gameState.lives--;
    playSound('miss');
    createDamageEffect();
    
    if (gameState.lives <= 0) {
        gameOver();
    }
    
    updateStats();
}

function loseLifeFromErrors() {
    gameState.lives--;
    gameState.consecutiveErrors = 0; // Reset contador después de perder vida
    
    playSound('game_over');
    createLifeLostEffect('¡Demasiados errores consecutivos!');
    
    if (gameState.lives <= 0) {
        gameOver();
    } else {
        // Regenerar texto después de perder vida
        gameState.userInput = '';
        document.getElementById('survivalInput').value = '';
        updateTextDisplay();
    }
    
    updateStats();
}

function showErrorWarning() {
    const input = document.getElementById('survivalInput');
    const warningElement = document.getElementById('errorWarning') || createErrorWarningElement();
    
    // Actualizar contador de errores
    warningElement.textContent = `Errores consecutivos: ${gameState.consecutiveErrors}/${gameState.maxConsecutiveErrors}`;
    
    // Cambiar color según proximidad al límite
    if (gameState.consecutiveErrors >= gameState.maxConsecutiveErrors - 1) {
        warningElement.className = 'error-warning error-critical';
        input.classList.add('error-critical');
    } else if (gameState.consecutiveErrors >= gameState.maxConsecutiveErrors - 2) {
        warningElement.className = 'error-warning error-warning-high';
        input.classList.add('error-warning-shake');
    } else {
        warningElement.className = 'error-warning error-warning-normal';
    }
    
    warningElement.style.display = 'block';
    
    // Efecto de shake en el input
    input.classList.add('shake-effect');
    setTimeout(() => {
        input.classList.remove('shake-effect');
    }, 300);
}

function hideErrorWarning() {
    const warningElement = document.getElementById('errorWarning');
    const input = document.getElementById('survivalInput');
    
    if (warningElement) {
        warningElement.style.display = 'none';
    }
    
    // Limpiar clases de error del input
    input.classList.remove('error-critical', 'error-warning-shake', 'shake-effect');
}

function createErrorWarningElement() {
    const warningElement = document.createElement('div');
    warningElement.id = 'errorWarning';
    warningElement.className = 'error-warning';
    warningElement.style.display = 'none';
    
    // Insertar después del input
    const input = document.getElementById('survivalInput');
    input.parentNode.insertBefore(warningElement, input.nextSibling);
    
    return warningElement;
}

function createLifeLostEffect(message) {
    const container = document.getElementById('typingBattlefield');
    const effect = document.createElement('div');
    effect.className = 'life-lost-effect';
    effect.innerHTML = `
        <div class="life-lost-icon">💔</div>
        <div class="life-lost-message">${message}</div>
        <div class="life-lost-submessage">-1 Vida</div>
    `;
    
    container.appendChild(effect);
    
    setTimeout(() => {
        if (effect.parentNode) {
            effect.parentNode.removeChild(effect);
        }
    }, 3000);
}

function updateStats() {
    // Calcular WPM
    const timeElapsed = gameState.startTime ? (Date.now() - gameState.startTime) / 1000 / 60 : 0;
    gameState.wpm = timeElapsed > 0 ? Math.round((gameState.correctChars / 5) / timeElapsed) : 0;
    
    // Calcular precisión
    gameState.accuracy = gameState.totalChars > 0 ? Math.round((gameState.correctChars / gameState.totalChars) * 100) : 100;
    
    // Actualizar UI
    document.getElementById('livesCount').textContent = gameState.lives;
    document.getElementById('currentWave').textContent = gameState.currentWave;
    document.getElementById('currentScore').textContent = gameState.score.toLocaleString();
    document.getElementById('currentWPM').textContent = gameState.wpm;
    document.getElementById('currentAccuracy').textContent = gameState.accuracy + '%';
    document.getElementById('timeLeft').textContent = Math.floor(gameState.timeElapsed);
}

function startTimer() {
    const timerInterval = setInterval(() => {
        if (!gameState.isActive || gameState.isPaused) return;
        
        gameState.timeElapsed++;
        updateStats();
        
        if (!gameState.isActive) {
            clearInterval(timerInterval);
        }
    }, 1000);
}

function togglePause() {
    gameState.isPaused = !gameState.isPaused;
    const pauseBtn = document.getElementById('pauseGame');
    
    if (gameState.isPaused) {
        pauseBtn.innerHTML = '<i class="fas fa-play me-2"></i>Continuar';
        pauseBtn.className = 'btn btn-success';
    } else {
        pauseBtn.innerHTML = '<i class="fas fa-pause me-2"></i>Pausar';
        pauseBtn.className = 'btn btn-danger';
        document.getElementById('survivalInput').focus();
    }
}

function gameOver() {
    gameState.isActive = false;
    stopBackgroundMusic();
    playSound('defeat');
    
    // Guardar puntuación
    saveSurvivalScore();
    
    // Mostrar modal de game over
    showGameOverModal(false);
}

function gameWin() {
    gameState.isActive = false;
    stopBackgroundMusic();
    playSound('victory');
    
    // Bonus por completar todas las oleadas según dificultad
    const difficultyBonus = {
        'easy': 1000,
        'normal': 2000,
        'hard': 5000
    };
    gameState.score += difficultyBonus[gameState.difficulty] || 2000;
    
    // Guardar puntuación
    saveSurvivalScore();
    
    // Mostrar modal de victoria
    showGameOverModal(true);
}

function showGameOverModal(victory) {
    const modal = new bootstrap.Modal(document.getElementById('gameOverModal'));
    const icon = document.getElementById('gameOverIcon');
    const title = document.getElementById('gameOverTitle');
    const content = document.getElementById('gameOverContent');
    
    if (victory) {
        icon.className = 'fas fa-crown me-2';
        title.textContent = '¡VICTORIA ÉPICA!';
        content.innerHTML = `
            <h3 class="text-warning mb-4">¡Has derrotado a todos los enemigos!</h3>
            <div class="row">
                <div class="col-md-6">
                    <h5>Puntuación Final: ${gameState.score.toLocaleString()}</h5>
                    <p>Oleadas Completadas: ${gameState.currentWave - 1}</p>
                    <p>WPM Promedio: ${gameState.wpm}</p>
                </div>
                <div class="col-md-6">
                    <p>Precisión: ${gameState.accuracy}%</p>
                    <p>Tiempo Total: ${Math.floor(gameState.timeElapsed)}s</p>
                    <p>Vidas Restantes: ${gameState.lives}</p>
                </div>
            </div>
            <div class="mt-4">
                <button class="btn btn-success me-2" onclick="restartSurvivalGame()">
                    <i class="fas fa-redo me-2"></i>Jugar de Nuevo
                </button>
                <button class="btn btn-primary me-2" onclick="goToGameModes()">
                    <i class="fas fa-gamepad me-2"></i>Otros Modos
                </button>
                <button class="btn btn-secondary" onclick="goToMainMenu()">
                    <i class="fas fa-home me-2"></i>Menú Principal
                </button>
            </div>
        `;
    } else {
        icon.className = 'fas fa-skull me-2';
        title.textContent = 'GAME OVER';
        content.innerHTML = `
            <h3 class="text-danger mb-4">¡Has sido derrotado!</h3>
            <div class="row">
                <div class="col-md-6">
                    <h5>Puntuación: ${gameState.score.toLocaleString()}</h5>
                    <p>Oleadas Alcanzadas: ${gameState.currentWave}</p>
                    <p>WPM Promedio: ${gameState.wpm}</p>
                </div>
                <div class="col-md-6">
                    <p>Precisión: ${gameState.accuracy}%</p>
                    <p>Tiempo Sobrevivido: ${Math.floor(gameState.timeElapsed)}s</p>
                </div>
            </div>
            <div class="mt-4">
                <button class="btn btn-danger me-2" onclick="restartSurvivalGame()">
                    <i class="fas fa-redo me-2"></i>Intentar de Nuevo
                </button>
                <button class="btn btn-primary me-2" onclick="goToGameModes()">
                    <i class="fas fa-gamepad me-2"></i>Otros Modos
                </button>
                <button class="btn btn-secondary" onclick="goToMainMenu()">
                    <i class="fas fa-home me-2"></i>Menú Principal
                </button>
            </div>
        `;
    }
    
    modal.show();
}

// Funciones de navegación
function restartSurvivalGame() {
    // Cerrar modal de game over
    const gameOverModal = bootstrap.Modal.getInstance(document.getElementById('gameOverModal'));
    if (gameOverModal) {
        gameOverModal.hide();
    }
    
    // Cerrar modal principal
    const survivalModal = bootstrap.Modal.getInstance(document.getElementById('survivalModal'));
    if (survivalModal) {
        survivalModal.hide();
    }
    
    // Reiniciar después de cerrar modales
    setTimeout(() => {
        location.reload();
    }, 300);
}

function goToGameModes() {
    // Cerrar todos los modales
    const gameOverModal = bootstrap.Modal.getInstance(document.getElementById('gameOverModal'));
    if (gameOverModal) {
        gameOverModal.hide();
    }
    
    const survivalModal = bootstrap.Modal.getInstance(document.getElementById('survivalModal'));
    if (survivalModal) {
        survivalModal.hide();
    }
    
    // Ir a página de modos de juego
    setTimeout(() => {
        window.location.href = '{{ route("typing.modes") }}';
    }, 300);
}

function goToMainMenu() {
    // Cerrar todos los modales
    const gameOverModal = bootstrap.Modal.getInstance(document.getElementById('gameOverModal'));
    if (gameOverModal) {
        gameOverModal.hide();
    }
    
    const survivalModal = bootstrap.Modal.getInstance(document.getElementById('survivalModal'));
    if (survivalModal) {
        survivalModal.hide();
    }
    
    // Ir al menú principal
    setTimeout(() => {
        window.location.href = '{{ route("typing.index") }}';
    }, 300);
}

function exitSurvivalGame() {
    // Detener música y resetear juego
    stopBackgroundMusic();
    resetGame();
    
    // Cerrar modal principal
    const survivalModal = bootstrap.Modal.getInstance(document.getElementById('survivalModal'));
    if (survivalModal) {
        survivalModal.hide();
    }
    
    // Ir a página de modos de juego
    setTimeout(() => {
        window.location.href = '{{ route("typing.modes") }}';
    }, 300);
}

function resetGame() {
    gameState = {
        isActive: false,
        isPaused: false,
        currentWave: 1,
        lives: 3,
        score: 0,
        wpm: 0,
        accuracy: 100,
        timeElapsed: 0,
        currentEnemy: null,
        currentText: '',
        userInput: '',
        previousInput: '',
        startTime: null,
        correctChars: 0,
        totalChars: 0,
        difficulty: 'normal',
        maxWaves: 8,
        difficultyMultiplier: 1.0
    };
    
    // Detener música de fondo
    stopBackgroundMusic();
    
    // Reset UI
    document.getElementById('gameStatus').style.display = 'block';
    document.getElementById('enemyDisplay').style.display = 'none';
    document.getElementById('typingBattlefield').style.display = 'none';
    document.getElementById('survivalInput').value = '';
    
    // Reset status text
    document.getElementById('statusText').textContent = '¡PREPÁRATE PARA LA BATALLA!';
    document.getElementById('statusSubtext').textContent = 'La primera oleada está por comenzar...';
    
    updateStats();
}

function saveSurvivalScore() {
    const data = {
        mode: 'survival',
        score: gameState.score,
        wpm: gameState.wpm,
        accuracy: gameState.accuracy,
        time_played: gameState.timeElapsed,
        extra_data: {
            difficulty: gameState.difficulty,
            waves_completed: gameState.currentWave - 1,
            max_waves: gameState.maxWaves,
            lives_remaining: gameState.lives,
            final_wave: gameState.currentWave,
            difficulty_multiplier: gameState.difficultyMultiplier
        }
    };
    
    fetch('{{ route("typing.mode.save.score") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            console.log('Puntuación guardada:', data);
            // Actualizar XP en la interfaz
            updateNavbarXP(data.total_xp, data.level, data.current_xp, data.required_xp);
        }
    })
    .catch(error => {
        console.error('Error al guardar puntuación:', error);
    });
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

// Funciones de efectos visuales
function createHitEffect() {
    // Efecto de golpe - se podría expandir con partículas
    const effect = document.createElement('div');
    effect.style.cssText = `
        position: fixed;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        color: #e74c3c;
        font-size: 2rem;
        font-weight: bold;
        pointer-events: none;
        z-index: 10000;
        animation: damageEffect 1s ease-out forwards;
    `;
    effect.textContent = '💥 HIT!';
    
    document.body.appendChild(effect);
    
    setTimeout(() => {
        document.body.removeChild(effect);
    }, 1000);
}

function createVictoryEffect() {
    const effect = document.createElement('div');
    effect.style.cssText = `
        position: fixed;
        top: 30%;
        left: 50%;
        transform: translate(-50%, -50%);
        color: #27ae60;
        font-size: 3rem;
        font-weight: bold;
        pointer-events: none;
        z-index: 10000;
        animation: victoryEffect 2s ease-out forwards;
    `;
    effect.textContent = '🏆 ¡ENEMIGO DERROTADO!';
    
    document.body.appendChild(effect);
    
    setTimeout(() => {
        document.body.removeChild(effect);
    }, 2000);
}

function createDamageEffect() {
    const effect = document.createElement('div');
    effect.style.cssText = `
        position: fixed;
        top: 40%;
        left: 50%;
        transform: translate(-50%, -50%);
        color: #e74c3c;
        font-size: 2.5rem;
        font-weight: bold;
        pointer-events: none;
        z-index: 10000;
        animation: damageEffect 1.5s ease-out forwards;
    `;
    effect.textContent = '💀 ¡VIDA PERDIDA!';
    
    document.body.appendChild(effect);
    
    setTimeout(() => {
        document.body.removeChild(effect);
    }, 1500);
}

function playSound(soundName) {
    try {
        console.log('Intentando reproducir sonido:', soundName);
        const sound = sounds[soundName];
        if (sound) {
            console.log('Sonido encontrado:', sound.src);
            sound.currentTime = 0;
            sound.play().catch(e => console.log('Could not play sound:', soundName, e));
        } else {
            console.log('Sonido no encontrado en objeto sounds:', soundName);
            console.log('Sonidos disponibles:', Object.keys(sounds));
        }
    } catch (e) {
        console.log('Sound error:', e);
    }
}

function playBackgroundMusic() {
    try {
        const bgMusic = sounds.background;
        if (bgMusic) {
            bgMusic.loop = true;
            bgMusic.volume = 0.3; // Música de fondo más suave
            bgMusic.play().catch(e => console.log('Could not play background music'));
        }
    } catch (e) {
        console.log('Background music error:', e);
    }
}

function stopBackgroundMusic() {
    try {
        const bgMusic = sounds.background;
        if (bgMusic) {
            bgMusic.pause();
            bgMusic.currentTime = 0;
        }
    } catch (e) {
        console.log('Stop background music error:', e);
    }
}

// CSS para animaciones de efectos
const style = document.createElement('style');
style.textContent = `
    @keyframes damageEffect {
        0% { 
            opacity: 1; 
            transform: translate(-50%, -50%) scale(0.5); 
        }
        50% { 
            opacity: 1; 
            transform: translate(-50%, -50%) scale(1.2); 
        }
        100% { 
            opacity: 0; 
            transform: translate(-50%, -50%) scale(1) translateY(-50px); 
        }
    }
    
    @keyframes victoryEffect {
        0% { 
            opacity: 1; 
            transform: translate(-50%, -50%) scale(0.3); 
        }
        50% { 
            opacity: 1; 
            transform: translate(-50%, -50%) scale(1.1); 
        }
        100% { 
            opacity: 0; 
            transform: translate(-50%, -50%) scale(1) translateY(-100px); 
        }
    }
`;
document.head.appendChild(style);
</script>
@endsection
