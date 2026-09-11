@extends('layout.app')

@section('title', $pageTitle ?? 'Modo Zen - TypeMaster AI')

@section('content')
<div class="container-fluid px-4">
    <!-- Header Section -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-0 bg-gradient-zen text-white">
                <div class="card-body p-4">
                    <div class="row align-items-center">
                        <div class="col-lg-8">
                            <h1 class="display-5 fw-bold mb-3">
                                <i class="fas fa-leaf me-3 zen-icon"></i>Modo Zen
                            </h1>
                            <p class="lead mb-3">
                                Encuentra la paz interior mientras mejoras tu mecanografía. Sin presión, sin prisa, solo tú y las palabras.
                            </p>
                            <div class="d-flex flex-wrap gap-3">
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-heart me-2"></i>
                                    <span>Sin estrés</span>
                                </div>
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-music me-2"></i>
                                    <span>Música relajante</span>
                                </div>
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-infinity me-2"></i>
                                    <span>Tiempo ilimitado</span>
                                </div>
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-quote-right me-2"></i>
                                    <span>Frases inspiradoras</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-4 text-center">
                            <div class="display-1 zen-glow">
                                <i class="fas fa-yin-yang"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Zen Configuration -->
    <div class="row mb-4">
        <div class="col-lg-8 mx-auto">
            <div class="card border-0 shadow-sm zen-card">
                <div class="card-header bg-gradient-peaceful text-white">
                    <h4 class="mb-0"><i class="fas fa-cog me-2"></i>Configuración de tu Sesión Zen</h4>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4">
                            <label class="form-label"><i class="fas fa-music me-2"></i>Ambiente Sonoro</label>
                            <select class="form-select" id="ambient-sound">
                                <option value="nature">Sonidos de la Naturaleza</option>
                                <option value="rain" selected>Lluvia Suave</option>
                                <option value="ocean">Olas del Océano</option>
                                <option value="forest">Bosque Tranquilo</option>
                                <option value="silence">Silencio Total</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label"><i class="fas fa-palette me-2"></i>Tema Visual</label>
                            <select class="form-select" id="visual-theme">
                                <option value="sunset" selected>Atardecer</option>
                                <option value="forest">Bosque Verde</option>
                                <option value="ocean">Océano Azul</option>
                                <option value="mountain">Montaña Serena</option>
                                <option value="minimal">Minimalista</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label"><i class="fas fa-quote-right me-2"></i>Tipo de Contenido</label>
                            <select class="form-select" id="content-type">
                                <option value="inspirational" selected>Frases Inspiradoras</option>
                                <option value="mindfulness">Mindfulness</option>
                                <option value="poetry">Poesía</option>
                                <option value="philosophy">Filosofía</option>
                                <option value="nature">Naturaleza</option>
                            </select>
                        </div>
                    </div>
                    <div class="row mt-3">
                        <div class="col-md-6">
                            <label class="form-label"><i class="fas fa-volume-up me-2"></i>Volumen Ambiente</label>
                            <input type="range" class="form-range" id="ambient-volume" min="0" max="100" value="30">
                            <div class="d-flex justify-content-between">
                                <small class="text-muted">Silencio</small>
                                <small class="text-muted">Máximo</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label"><i class="fas fa-clock me-2"></i>Duración de Sesión</label>
                            <select class="form-select" id="session-duration">
                                <option value="0">Sin límite</option>
                                <option value="5">5 minutos</option>
                                <option value="10" selected>10 minutos</option>
                                <option value="15">15 minutos</option>
                                <option value="20">20 minutos</option>
                                <option value="30">30 minutos</option>
                            </select>
                        </div>
                    </div>
                    <div class="row mt-4">
                        <div class="col-12 text-center">
                            <button id="start-zen-session" class="btn btn-zen btn-lg px-5">
                                <i class="fas fa-leaf me-2"></i>Comenzar Sesión Zen
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Zen Game Area -->
    <div class="row" id="zen-area" style="display: none;">
        <div class="col-12">
            <div class="card border-0 shadow-lg zen-game-card">
                <div class="card-header bg-transparent border-0">
                    <div class="row align-items-center">
                        <div class="col-md-3">
                            <div class="d-flex align-items-center text-white">
                                <i class="fas fa-clock me-2"></i>
                                <span id="session-timer">∞</span>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="d-flex align-items-center text-white">
                                <i class="fas fa-keyboard me-2"></i>
                                <span>WPM: <span id="zen-wpm">0</span></span>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="d-flex align-items-center text-white">
                                <i class="fas fa-check-circle me-2"></i>
                                <span>Precisión: <span id="zen-accuracy">100%</span></span>
                            </div>
                        </div>
                        <div class="col-md-3 text-end">
                            <button id="pause-zen" class="btn btn-outline-light btn-sm me-2">
                                <i class="fas fa-pause"></i>
                            </button>
                            <button id="end-zen" class="btn btn-outline-light btn-sm">
                                <i class="fas fa-stop"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <div class="card-body p-0">
                    <!-- Zen Canvas -->
                    <div id="zen-canvas" class="position-relative">
                        <!-- Animated background particles -->
                        <div id="zen-particles"></div>
                        
                        <!-- Breathing guide -->
                        <div id="breathing-guide" class="breathing-circle">
                            <div class="breathing-text">Respira</div>
                        </div>
                        
                        <!-- Quote display -->
                        <div id="quote-display" class="zen-quote-container">
                            <div id="current-quote" class="zen-quote"></div>
                            <div id="quote-author" class="zen-author"></div>
                        </div>
                        
                        <!-- Progress indicator -->
                        <div id="zen-progress" class="zen-progress">
                            <div class="zen-progress-bar"></div>
                        </div>
                    </div>
                    
                    <!-- Typing Area -->
                    <div class="zen-typing-area p-4">
                        <div class="zen-text-display mb-4">
                            <div id="zen-text" class="zen-text"></div>
                        </div>
                        <div class="zen-input-container">
                            <input type="text" id="zen-input" class="zen-input" 
                                   placeholder="Escribe con calma y presencia..." 
                                   autocomplete="off" 
                                   disabled>
                        </div>
                        <div class="zen-stats mt-3">
                            <div class="row text-center">
                                <div class="col-3">
                                    <div class="zen-stat">
                                        <span class="zen-stat-number" id="words-completed">0</span>
                                        <span class="zen-stat-label">Palabras</span>
                                    </div>
                                </div>
                                <div class="col-3">
                                    <div class="zen-stat">
                                        <span class="zen-stat-number" id="characters-typed">0</span>
                                        <span class="zen-stat-label">Caracteres</span>
                                    </div>
                                </div>
                                <div class="col-3">
                                    <div class="zen-stat">
                                        <span class="zen-stat-number" id="zen-streak">0</span>
                                        <span class="zen-stat-label">Racha</span>
                                    </div>
                                </div>
                                <div class="col-3">
                                    <div class="zen-stat">
                                        <span class="zen-stat-number" id="mindfulness-score">100</span>
                                        <span class="zen-stat-label">Mindfulness</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Zen Tips -->
    <div class="row mt-4" id="zen-tips">
        <div class="col-12">
            <div class="card border-0 shadow-sm zen-tips-card">
                <div class="card-header bg-gradient-peaceful text-white">
                    <h5 class="mb-0"><i class="fas fa-lightbulb me-2"></i>Consejos para tu Práctica Zen</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <div class="zen-tip">
                                <div class="zen-tip-icon">🧘</div>
                                <h6>Respiración Consciente</h6>
                                <p class="text-muted small">Mantén una respiración profunda y constante. Deja que tu respiración guíe tu ritmo de escritura.</p>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="zen-tip">
                                <div class="zen-tip-icon">🎯</div>
                                <h6>Enfoque en el Presente</h6>
                                <p class="text-muted small">Concéntrate únicamente en la palabra actual. No pienses en errores pasados o palabras futuras.</p>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="zen-tip">
                                <div class="zen-tip-icon">🌸</div>
                                <h6>Acepta los Errores</h6>
                                <p class="text-muted small">Los errores son parte del proceso. Obsérvalos sin juicio y continúa con serenidad.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Session Complete Modal -->
<div class="modal fade" id="zenCompleteModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content zen-modal">
            <div class="modal-header bg-gradient-peaceful text-white border-0">
                <h5 class="modal-title">
                    <i class="fas fa-om me-2"></i>Sesión Zen Completada
                </h5>
            </div>
            <div class="modal-body text-center p-4">
                <div class="zen-completion-icon mb-4">
                    <i class="fas fa-check-circle"></i>
                </div>
                <h3 class="mb-4">¡Felicidades por tu práctica mindful!</h3>
                
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="zen-final-stat">
                            <div class="zen-final-number" id="final-words">0</div>
                            <div class="zen-final-label">Palabras Completadas</div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="zen-final-stat">
                            <div class="zen-final-number" id="final-wpm">0</div>
                            <div class="zen-final-label">WPM Promedio</div>
                        </div>
                    </div>
                </div>
                
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="zen-final-stat">
                            <div class="zen-final-number" id="final-accuracy">100%</div>
                            <div class="zen-final-label">Precisión</div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="zen-final-stat">
                            <div class="zen-final-number" id="final-mindfulness">100</div>
                            <div class="zen-final-label">Mindfulness Score</div>
                        </div>
                    </div>
                </div>
                
                <div class="zen-reflection mb-4">
                    <h5>Reflexión de tu Sesión</h5>
                    <div id="zen-reflection-text" class="zen-reflection-content">
                        "En la quietud de la mente se encuentra la sabiduría. En la quietud del corazón se encuentra el amor."
                    </div>
                </div>
                
                <div class="zen-xp-gained alert alert-zen">
                    <i class="fas fa-star me-2"></i>XP Zen Ganado: <span id="zen-earned-xp">0</span>
                </div>
            </div>
            <div class="modal-footer bg-light border-0">
                <button type="button" class="btn btn-zen" onclick="restartZenSession()">
                    <i class="fas fa-redo me-2"></i>Nueva Sesión
                </button>
                <button type="button" class="btn btn-outline-secondary" onclick="goToGameModes()">
                    <i class="fas fa-gamepad me-2"></i>Otros Modos
                </button>
                <button type="button" class="btn btn-outline-secondary" onclick="goToMainMenu()">
                    <i class="fas fa-home me-2"></i>Menú Principal
                </button>
            </div>
        </div>
    </div>
</div>

<style>
/* Zen Theme Gradients and Colors */
.bg-gradient-zen {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

.bg-gradient-peaceful {
    background: linear-gradient(135deg, #a8caba 0%, #5d4e75 100%);
}

.zen-icon, .zen-glow {
    animation: zenFloat 3s ease-in-out infinite alternate;
}

@keyframes zenFloat {
    from { transform: translateY(0px); }
    to { transform: translateY(-10px); }
}

/* Zen Cards */
.zen-card, .zen-game-card, .zen-tips-card {
    backdrop-filter: blur(10px);
    background: rgba(255, 255, 255, 0.95);
}

.zen-modal {
    backdrop-filter: blur(15px);
    background: rgba(255, 255, 255, 0.98);
}

/* Zen Canvas */
#zen-canvas {
    height: 400px;
    background: linear-gradient(135deg, #ff9a9e 0%, #fecfef 50%, #fecfef 100%);
    overflow: hidden;
    position: relative;
}

#zen-canvas.forest {
    background: linear-gradient(135deg, #134e5e 0%, #71b280 100%);
}

#zen-canvas.ocean {
    background: linear-gradient(135deg, #74b9ff 0%, #0984e3 100%);
}

#zen-canvas.mountain {
    background: linear-gradient(135deg, #e17055 0%, #f39c12 100%);
}

#zen-canvas.minimal {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
}

/* Zen Particles */
#zen-particles {
    position: absolute;
    width: 100%;
    height: 100%;
    overflow: hidden;
}

.zen-particle {
    position: absolute;
    width: 4px;
    height: 4px;
    background: rgba(255, 255, 255, 0.6);
    border-radius: 50%;
    animation: zenParticleFloat 8s infinite linear;
}

@keyframes zenParticleFloat {
    0% { 
        transform: translateY(100vh) rotate(0deg);
        opacity: 0;
    }
    10% { opacity: 1; }
    90% { opacity: 1; }
    100% { 
        transform: translateY(-100px) rotate(360deg);
        opacity: 0;
    }
}

/* Breathing Guide */
.breathing-circle {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    width: 120px;
    height: 120px;
    border: 3px solid rgba(255, 255, 255, 0.8);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    animation: breathingGuide 4s infinite ease-in-out;
}

.breathing-text {
    color: white;
    font-size: 16px;
    font-weight: 300;
    text-align: center;
}

@keyframes breathingGuide {
    0% { transform: translate(-50%, -50%) scale(1); opacity: 0.7; }
    50% { transform: translate(-50%, -50%) scale(1.3); opacity: 1; }
    100% { transform: translate(-50%, -50%) scale(1); opacity: 0.7; }
}

/* Quote Display */
.zen-quote-container {
    position: absolute;
    top: 20px;
    left: 50%;
    transform: translateX(-50%);
    text-align: center;
    color: white;
    max-width: 80%;
}

.zen-quote {
    font-size: 20px;
    font-weight: 300;
    line-height: 1.5;
    margin-bottom: 10px;
    text-shadow: 1px 1px 2px rgba(0,0,0,0.3);
}

.zen-author {
    font-size: 14px;
    opacity: 0.8;
    font-style: italic;
}

/* Zen Progress */
.zen-progress {
    position: absolute;
    bottom: 0;
    left: 0;
    width: 100%;
    height: 4px;
    background: rgba(255, 255, 255, 0.3);
}

.zen-progress-bar {
    height: 100%;
    background: rgba(255, 255, 255, 0.8);
    width: 0%;
    transition: width 0.3s ease;
}

/* Zen Typing Area */
.zen-typing-area {
    background: rgba(248, 249, 250, 0.95);
    backdrop-filter: blur(5px);
}

.zen-text-display {
    min-height: 60px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.zen-text {
    font-size: 24px;
    line-height: 1.8;
    text-align: center;
    color: #495057;
    font-weight: 300;
}

.zen-text .char {
    position: relative;
    padding: 2px;
    border-radius: 3px;
    transition: all 0.2s ease;
}

.zen-text .char.correct {
    background-color: rgba(40, 167, 69, 0.2);
    color: #28a745;
}

.zen-text .char.incorrect {
    background-color: rgba(220, 53, 69, 0.2);
    color: #dc3545;
}

.zen-text .char.current {
    background-color: rgba(0, 123, 255, 0.3);
    animation: zenCursor 1s infinite;
}

@keyframes zenCursor {
    0%, 50% { opacity: 1; }
    51%, 100% { opacity: 0.3; }
}

/* Zen Input */
.zen-input-container {
    display: flex;
    justify-content: center;
}

.zen-input {
    width: 100%;
    max-width: 600px;
    padding: 15px 20px;
    font-size: 18px;
    border: 2px solid #e9ecef;
    border-radius: 25px;
    background: rgba(255, 255, 255, 0.9);
    text-align: center;
    transition: all 0.3s ease;
    outline: none;
}

.zen-input:focus {
    border-color: #a8caba;
    box-shadow: 0 0 20px rgba(168, 202, 186, 0.3);
    background: rgba(255, 255, 255, 1);
}

.zen-input.zen-error {
    border-color: #dc3545;
    animation: zenShake 0.3s ease-out;
}

@keyframes zenShake {
    0% { transform: translateX(0); }
    25% { transform: translateX(-3px); }
    75% { transform: translateX(3px); }
    100% { transform: translateX(0); }
}

/* Zen Stats */
.zen-stat {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 10px;
}

.zen-stat-number {
    font-size: 24px;
    font-weight: 500;
    color: #5d4e75;
}

.zen-stat-label {
    font-size: 12px;
    color: #6c757d;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

/* Zen Tips */
.zen-tip {
    text-align: center;
    padding: 20px;
    border-radius: 10px;
    background: rgba(168, 202, 186, 0.1);
    height: 100%;
}

.zen-tip-icon {
    font-size: 2rem;
    margin-bottom: 15px;
}

/* Zen Modal */
.zen-completion-icon {
    font-size: 4rem;
    color: #28a745;
}

.zen-final-stat {
    margin-bottom: 20px;
}

.zen-final-number {
    font-size: 2rem;
    font-weight: 500;
    color: #5d4e75;
}

.zen-final-label {
    font-size: 0.9rem;
    color: #6c757d;
    text-transform: uppercase;
}

.zen-reflection-content {
    font-style: italic;
    font-size: 1.1rem;
    color: #495057;
    line-height: 1.6;
    padding: 20px;
    background: rgba(168, 202, 186, 0.1);
    border-radius: 10px;
    border-left: 4px solid #a8caba;
}

/* Zen Buttons */
.btn-zen {
    background: linear-gradient(135deg, #a8caba 0%, #5d4e75 100%);
    border: none;
    color: white;
    padding: 12px 30px;
    border-radius: 25px;
    font-weight: 500;
    transition: all 0.3s ease;
}

.btn-zen:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(168, 202, 186, 0.4);
    color: white;
}

.alert-zen {
    background: rgba(168, 202, 186, 0.1);
    border: 1px solid #a8caba;
    color: #5d4e75;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .zen-quote {
        font-size: 16px;
    }
    
    .zen-text {
        font-size: 18px;
    }
    
    .zen-input {
        font-size: 16px;
        padding: 12px 15px;
    }
    
    .breathing-circle {
        width: 80px;
        height: 80px;
    }
    
    .breathing-text {
        font-size: 12px;
    }
}
</style>

<script>
class ZenMode {
    constructor() {
        this.isRunning = false;
        this.isPaused = false;
        this.startTime = null;
        this.sessionDuration = 0; // 0 = unlimited
        this.currentTextIndex = 0;
        this.wordsCompleted = 0;
        this.charactersTyped = 0;
        this.accuracy = 100;
        this.wpm = 0;
        this.streak = 0;
        this.mindfulnessScore = 100;
        this.errors = 0;
        this.totalChars = 0;
        
        // Settings
        this.settings = {
            ambientSound: 'rain',
            visualTheme: 'sunset',
            contentType: 'inspirational',
            ambientVolume: 30,
            sessionDuration: 10
        };
        
        // Content collections
        this.contentCollections = {
            inspirational: [
                { text: "La vida no se trata de encontrarse a uno mismo, sino de crearse a uno mismo.", author: "George Bernard Shaw" },
                { text: "El presente es el único momento en el que podemos ser felices.", author: "Thich Nhat Hanh" },
                { text: "La paz viene desde adentro. No la busques afuera.", author: "Buda" },
                { text: "Cada momento es un nuevo comienzo.", author: "T.S. Eliot" },
                { text: "La sabiduría comienza en el silencio.", author: "Pitágoras" },
                { text: "Respira profundo y deja que la calma llene tu ser.", author: "Anónimo" },
                { text: "En la quietud encontramos nuestras respuestas más profundas.", author: "Lao Tzu" },
                { text: "Cada letra que escribes es un paso hacia la maestría.", author: "TypeMaster AI" }
            ],
            mindfulness: [
                { text: "Observa tus pensamientos sin juzgarlos, como nubes que pasan por el cielo.", author: "Jon Kabat-Zinn" },
                { text: "La atención plena es la práctica de estar completamente presente en este momento.", author: "Andy Puddicombe" },
                { text: "Cuando escribes con conciencia, cada tecla es una meditación.", author: "Anónimo" },
                { text: "El mindfulness no es evitar pensamientos, sino observarlos con gentileza.", author: "Tara Brach" },
                { text: "En cada respiración encuentra una oportunidad de comenzar de nuevo.", author: "Jack Kornfield" }
            ],
            poetry: [
                { text: "Dos caminos se bifurcaban en un bosque amarillo, y yo tomé el menos transitado.", author: "Robert Frost" },
                { text: "El viento susurra secretos a las hojas que bailan en la brisa.", author: "Anónimo" },
                { text: "Las palabras son ventanas al alma, cada una revela un fragmento de verdad.", author: "Poeta Desconocido" },
                { text: "En el jardín de las letras, cada palabra es una flor que florece.", author: "TypeMaster AI" }
            ],
            philosophy: [
                { text: "Solo sé que no sé nada, y esto me hace más sabio que aquellos que creen saberlo todo.", author: "Sócrates" },
                { text: "La vida debe ser comprendida hacia atrás, pero debe ser vivida hacia adelante.", author: "Søren Kierkegaard" },
                { text: "El hombre es la medida de todas las cosas.", author: "Protágoras" },
                { text: "Cogito ergo sum: pienso, luego existo.", author: "René Descartes" }
            ],
            nature: [
                { text: "Los árboles susurran antiguos secretos al viento que los acaricia.", author: "Naturaleza" },
                { text: "El océano guarda en sus profundidades la sabiduría de milenios.", author: "Anónimo" },
                { text: "Cada amanecer trae consigo la promesa de un nuevo día lleno de posibilidades.", author: "Madre Tierra" },
                { text: "Las montañas permanecen inmóviles, enseñándonos la virtud de la paciencia.", author: "Sabiduría Ancestral" }
            ]
        };
        
        this.currentContent = [];
        this.ambientAudio = null;
        this.particles = [];
        
        this.bindEvents();
        this.initializeParticles();
    }
    
    bindEvents() {
        document.getElementById('start-zen-session').addEventListener('click', () => this.startSession());
        document.getElementById('pause-zen').addEventListener('click', () => this.pauseSession());
        document.getElementById('end-zen').addEventListener('click', () => this.endSession());
        document.getElementById('zen-input').addEventListener('input', (e) => this.handleTyping(e));
        document.getElementById('zen-input').addEventListener('keydown', (e) => {
            if (e.key === 'Escape') this.pauseSession();
        });
        
        // Settings listeners
        document.getElementById('visual-theme').addEventListener('change', (e) => this.changeTheme(e.target.value));
        document.getElementById('ambient-volume').addEventListener('input', (e) => this.updateVolume(e.target.value));
        document.getElementById('ambient-sound').addEventListener('change', (e) => this.changeAmbientSound(e.target.value));
    }
    
    startSession() {
        // Get settings
        this.settings.ambientSound = document.getElementById('ambient-sound').value;
        this.settings.visualTheme = document.getElementById('visual-theme').value;
        this.settings.contentType = document.getElementById('content-type').value;
        this.settings.ambientVolume = document.getElementById('ambient-volume').value;
        this.settings.sessionDuration = parseInt(document.getElementById('session-duration').value);
        
        // Reset session state
        this.resetSession();
        
        // Load content
        this.loadContent();
        
        // Setup environment
        this.setupAmbientSound();
        this.changeTheme(this.settings.visualTheme);
        
        // Show zen area
        document.getElementById('zen-tips').style.display = 'none';
        document.getElementById('zen-area').style.display = 'block';
        document.getElementById('zen-input').disabled = false;
        document.getElementById('zen-input').focus();
        
        // Start session
        this.isRunning = true;
        this.isPaused = false;
        this.startTime = Date.now();
        
        this.displayCurrentText();
        this.startTimer();
        this.animateParticles();
        
        // Play start sound
        this.playSound('game_start');
        
        this.updateUI();
    }
    
    resetSession() {
        this.wordsCompleted = 0;
        this.charactersTyped = 0;
        this.accuracy = 100;
        this.wpm = 0;
        this.streak = 0;
        this.mindfulnessScore = 100;
        this.errors = 0;
        this.totalChars = 0;
        this.currentTextIndex = 0;
    }
    
    loadContent() {
        this.currentContent = this.contentCollections[this.settings.contentType] || this.contentCollections.inspirational;
        // Shuffle content
        this.currentContent = this.currentContent.sort(() => Math.random() - 0.5);
    }
    
    setupAmbientSound() {
        if (this.settings.ambientSound === 'silence') return;
        
        console.log(`Playing ambient sound: ${this.settings.ambientSound}`);
        
        // Stop any existing ambient audio
        if (this.ambientAudio) {
            this.ambientAudio.pause();
        }
        
        // Load and play the actual audio file
        this.ambientAudio = new Audio(`/audio/ambient/${this.settings.ambientSound}.mp3`);
        this.ambientAudio.volume = this.settings.ambientVolume / 100;
        this.ambientAudio.loop = true;
        this.ambientAudio.play().catch(error => {
            console.warn('Could not autoplay ambient audio:', error);
        });
    }
    
    changeTheme(theme) {
        const canvas = document.getElementById('zen-canvas');
        canvas.className = theme;
        
        // Update particle colors based on theme
        this.updateParticleColors(theme);
    }
    
    updateVolume(volume) {
        if (this.ambientAudio) {
            this.ambientAudio.volume = volume / 100;
        }
    }
    
    changeAmbientSound(soundType) {
        this.settings.ambientSound = soundType;
        this.setupAmbientSound();
    }
    
    displayCurrentText() {
        if (this.currentTextIndex >= this.currentContent.length) {
            this.currentTextIndex = 0; // Loop content
        }
        
        const content = this.currentContent[this.currentTextIndex];
        const textElement = document.getElementById('zen-text');
        const quoteElement = document.getElementById('current-quote');
        const authorElement = document.getElementById('quote-author');
        
        // Display inspirational quote
        quoteElement.textContent = `"${content.text}"`;
        authorElement.textContent = `— ${content.author}`;
        
        // Display typing text
        const words = content.text.split(' ');
        const selectedWords = words.slice(0, Math.min(8, words.length)); // Limit to 8 words
        const displayText = selectedWords.join(' ');
        
        textElement.innerHTML = displayText.split('').map((char, index) => 
            `<span class="char" data-index="${index}">${char}</span>`
        ).join('');
        
        this.currentText = displayText;
        this.userInput = '';
        this.updateTextDisplay();
    }
    
    handleTyping(e) {
        if (!this.isRunning || this.isPaused) return;
        
        this.userInput = e.target.value;
        this.updateTextDisplay();
        
        // Check if text is complete
        if (this.userInput === this.currentText) {
            this.completeText();
            e.target.value = '';
        } else if (this.userInput.length >= this.currentText.length) {
            // User typed too much, restart
            this.handleError();
            e.target.value = '';
        }
    }
    
    updateTextDisplay() {
        const chars = document.querySelectorAll('#zen-text .char');
        
        chars.forEach((char, index) => {
            char.classList.remove('correct', 'incorrect', 'current');
            
            if (index < this.userInput.length) {
                if (this.userInput[index] === this.currentText[index]) {
                    char.classList.add('correct');
                } else {
                    char.classList.add('incorrect');
                }
            } else if (index === this.userInput.length) {
                char.classList.add('current');
            }
        });
    }
    
    completeText() {
        this.wordsCompleted++;
        this.charactersTyped += this.currentText.length;
        this.streak++;
        
        // Calculate accuracy
        const correctChars = this.userInput.split('').filter((char, index) => 
            char === this.currentText[index]
        ).length;
        
        this.totalChars += this.currentText.length;
        this.accuracy = Math.round((this.totalChars - this.errors) / this.totalChars * 100);
        
        // Update mindfulness score based on consistency
        if (this.streak >= 5) {
            this.mindfulnessScore = Math.min(100, this.mindfulnessScore + 2);
        }
        
        // Play success sound
        this.playSound('hit');
        
        // Show completion effect
        this.showCompletionEffect();
        
        // Move to next text
        this.currentTextIndex++;
        setTimeout(() => this.displayCurrentText(), 1000);
        
        this.updateUI();
    }
    
    handleError() {
        this.errors++;
        this.streak = 0;
        this.mindfulnessScore = Math.max(0, this.mindfulnessScore - 5);
        
        const input = document.getElementById('zen-input');
        input.classList.add('zen-error');
        setTimeout(() => input.classList.remove('zen-error'), 300);
        
        this.playSound('miss');
        this.userInput = '';
        this.updateTextDisplay();
    }
    
    showCompletionEffect() {
        const canvas = document.getElementById('zen-canvas');
        const effect = document.createElement('div');
        effect.className = 'zen-completion-ripple';
        effect.style.cssText = `
            position: absolute;
            top: 50%;
            left: 50%;
            width: 100px;
            height: 100px;
            border: 3px solid rgba(255, 255, 255, 0.8);
            border-radius: 50%;
            transform: translate(-50%, -50%);
            animation: zenRipple 0.6s ease-out forwards;
            pointer-events: none;
        `;
        
        canvas.appendChild(effect);
        
        setTimeout(() => {
            if (effect.parentNode) {
                effect.parentNode.removeChild(effect);
            }
        }, 600);
    }
    
    pauseSession() {
        if (!this.isRunning) return;
        
        this.isPaused = !this.isPaused;
        const button = document.getElementById('pause-zen');
        
        if (this.isPaused) {
            button.innerHTML = '<i class="fas fa-play"></i>';
            if (this.ambientAudio) this.ambientAudio.pause();
        } else {
            button.innerHTML = '<i class="fas fa-pause"></i>';
            if (this.ambientAudio) this.ambientAudio.play();
            document.getElementById('zen-input').focus();
        }
    }
    
    endSession() {
        this.isRunning = false;
        this.isPaused = false;
        
        if (this.ambientAudio) {
            this.ambientAudio.pause();
        }
        
        this.showCompletionModal();
    }
    
    showCompletionModal() {
        // Calculate final stats
        const sessionTime = this.startTime ? (Date.now() - this.startTime) / 1000 / 60 : 0; // in minutes
        const finalWpm = sessionTime > 0 ? Math.round(this.wordsCompleted / sessionTime * 5) : 0;
        
        // Show results
        document.getElementById('final-words').textContent = this.wordsCompleted;
        document.getElementById('final-wpm').textContent = finalWpm;
        document.getElementById('final-accuracy').textContent = this.accuracy + '%';
        document.getElementById('final-mindfulness').textContent = this.mindfulnessScore;
        
        // Calculate XP (base XP * 1.1 multiplier for zen mode + mindfulness bonus)
        const baseXP = Math.round(this.wordsCompleted * 5);
        const mindfulnessBonus = Math.round(this.mindfulnessScore / 10);
        const earnedXP = Math.round((baseXP + mindfulnessBonus) * 1.1);
        document.getElementById('zen-earned-xp').textContent = earnedXP;
        
        // Set reflection text based on performance
        const reflections = [
            "La práctica constante es la madre de la maestría.",
            "En cada respiración consciente encuentras un momento de paz.",
            "Tu presencia en este momento es tu mayor logro.",
            "La paciencia y la persistencia son las llaves de la sabiduría.",
            "Cada palabra escrita con conciencia es una meditación."
        ];
        
        const reflection = reflections[Math.floor(Math.random() * reflections.length)];
        document.getElementById('zen-reflection-text').textContent = `"${reflection}"`;
        
        // Save session
        this.saveGameSession(finalWpm, this.accuracy, earnedXP);
        
        // Show modal
        const modal = new bootstrap.Modal(document.getElementById('zenCompleteModal'));
        modal.show();
        
        // Reset UI
        document.getElementById('zen-area').style.display = 'none';
        document.getElementById('zen-tips').style.display = 'block';
        document.getElementById('zen-input').disabled = true;
        document.getElementById('zen-input').value = '';
    }
    
    startTimer() {
        if (this.settings.sessionDuration === 0) {
            document.getElementById('session-timer').textContent = '∞';
            return;
        }
        
        const updateTimer = () => {
            if (!this.isRunning) return;
            
            const elapsed = (Date.now() - this.startTime) / 1000;
            const remaining = (this.settings.sessionDuration * 60) - elapsed;
            
            if (remaining <= 0) {
                this.endSession();
                return;
            }
            
            const minutes = Math.floor(remaining / 60);
            const seconds = Math.floor(remaining % 60);
            document.getElementById('session-timer').textContent = 
                `${minutes}:${seconds.toString().padStart(2, '0')}`;
            
            // Update progress bar
            const progress = (elapsed / (this.settings.sessionDuration * 60)) * 100;
            document.querySelector('.zen-progress-bar').style.width = progress + '%';
            
            if (!this.isPaused) {
                setTimeout(updateTimer, 1000);
            }
        };
        
        updateTimer();
    }
    
    updateUI() {
        document.getElementById('zen-wpm').textContent = this.wpm;
        document.getElementById('zen-accuracy').textContent = this.accuracy + '%';
        document.getElementById('words-completed').textContent = this.wordsCompleted;
        document.getElementById('characters-typed').textContent = this.charactersTyped;
        document.getElementById('zen-streak').textContent = this.streak;
        document.getElementById('mindfulness-score').textContent = this.mindfulnessScore;
        
        // Calculate WPM
        if (this.startTime) {
            const timeElapsed = (Date.now() - this.startTime) / 1000 / 60;
            this.wpm = timeElapsed > 0 ? Math.round(this.wordsCompleted / timeElapsed * 5) : 0;
            document.getElementById('zen-wpm').textContent = this.wpm;
        }
    }
    
    initializeParticles() {
        const container = document.getElementById('zen-particles');
        
        for (let i = 0; i < 20; i++) {
            const particle = document.createElement('div');
            particle.className = 'zen-particle';
            particle.style.left = Math.random() * 100 + '%';
            particle.style.animationDelay = Math.random() * 8 + 's';
            particle.style.animationDuration = (8 + Math.random() * 4) + 's';
            container.appendChild(particle);
        }
    }
    
    animateParticles() {
        // Particles are already animated via CSS
        // This method could be used for more complex particle physics if needed
    }
    
    updateParticleColors(theme) {
        const particles = document.querySelectorAll('.zen-particle');
        let color = 'rgba(255, 255, 255, 0.6)';
        
        switch(theme) {
            case 'forest':
                color = 'rgba(113, 178, 128, 0.6)';
                break;
            case 'ocean':
                color = 'rgba(116, 185, 255, 0.6)';
                break;
            case 'mountain':
                color = 'rgba(225, 112, 85, 0.6)';
                break;
            case 'minimal':
                color = 'rgba(108, 117, 125, 0.6)';
                break;
        }
        
        particles.forEach(particle => {
            particle.style.background = color;
        });
    }
    
    saveGameSession(wpm, accuracy, earnedXP) {
        fetch('{{ route("typing.save.session") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({
                game_mode: 'zen',
                mode_score: this.mindfulnessScore,
                mode_data: {
                    words_completed: this.wordsCompleted,
                    characters_typed: this.charactersTyped,
                    mindfulness_score: this.mindfulnessScore,
                    streak_max: this.streak,
                    content_type: this.settings.contentType
                },
                wpm: wpm,
                accuracy: accuracy,
                duration: Math.floor((Date.now() - this.startTime) / 1000),
                earned_xp: earnedXP
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Update XP display in header
                updateNavbarXP(data.total_xp, data.level, data.current_xp, data.required_xp);
            }
        })
        .catch(error => console.error('Error saving zen session:', error));
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
            const audio = new Audio(`/audio/${soundName}.mp3`);
            audio.volume = 0.3;
            audio.play().catch(e => console.log('Audio play failed:', e));
        } catch (e) {
            console.log('Audio not available:', e);
        }
    }
}

// Add CSS animation for zen ripple effect
const style = document.createElement('style');
style.textContent = `
    @keyframes zenRipple {
        0% { 
            transform: translate(-50%, -50%) scale(0);
            opacity: 1;
        }
        100% { 
            transform: translate(-50%, -50%) scale(3);
            opacity: 0;
        }
    }
`;
document.head.appendChild(style);

// Initialize zen mode
let zenMode;

document.addEventListener('DOMContentLoaded', function() {
    zenMode = new ZenMode();
});

// Navigation functions
function restartZenSession() {
    const modal = bootstrap.Modal.getInstance(document.getElementById('zenCompleteModal'));
    if (modal) modal.hide();
    
    setTimeout(() => {
        zenMode.startSession();
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