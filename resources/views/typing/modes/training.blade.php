@extends('layout.app')

@section('title', $pageTitle ?? 'Modo Entrenamiento - TypeMaster AI')

@section('content')
<div class="container-fluid px-4">
    <!-- Header Section -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-0 bg-primary text-white">
                <div class="card-body p-4">
                    <div class="row align-items-center">
                        <div class="col-lg-8">
                            <h1 class="display-6 fw-bold mb-3">
                                <i class="fas fa-dumbbell me-3"></i>Modo Entrenamiento
                            </h1>
                            <p class="lead mb-3">
                                Practica por filas del teclado y mejora la técnica de dedos específicos. Enfoque en precisión y memoria muscular.
                            </p>
                            <div class="d-flex flex-wrap gap-3">
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-target me-2"></i>
                                    <span>Enfoque en precisión</span>
                                </div>
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-hand me-2"></i>
                                    <span>Técnica de dedos</span>
                                </div>
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-clock me-2"></i>
                                    <span>Sin límite de tiempo</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-4 text-center">
                            <a href="{{ route('typing.modes') }}" class="btn btn-outline-light mb-2">
                                <i class="fas fa-arrow-left me-2"></i>Volver a Modos
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Training Mode Selection -->
    <div class="row">
        <!-- Fila Central (ASDF JKLÑ) -->
        <div class="col-lg-4 col-md-6 mb-4">
            <div class="card h-100 border-0 shadow-sm training-card" data-training="central-row">
                <div class="card-header bg-primary text-white text-center">
                    <h5 class="mb-0"><i class="fas fa-keyboard me-2"></i>Fila Central</h5>
                </div>
                <div class="card-body">
                    <div class="keyboard-preview mb-3">
                        <div class="keyboard-row">
                            <span class="key highlight">A</span>
                            <span class="key highlight">S</span>
                            <span class="key highlight">D</span>
                            <span class="key highlight">F</span>
                            <span class="key">G</span>
                            <span class="key">H</span>
                            <span class="key highlight">J</span>
                            <span class="key highlight">K</span>
                            <span class="key highlight">L</span>
                            <span class="key highlight">Ñ</span>
                        </div>
                    </div>
                    <p class="text-muted mb-3">
                        Posición base de los dedos. Fundamental para una técnica correcta.
                    </p>
                    <div class="mb-3">
                        <div class="d-flex justify-content-between">
                            <small><i class="fas fa-hand-paper"></i> Meñiques - Índices</small>
                            <small><i class="fas fa-star"></i> Básico</small>
                        </div>
                    </div>
                    <button class="btn btn-primary w-100 start-training" data-mode="central-row">
                        <i class="fas fa-play me-2"></i>Comenzar
                    </button>
                </div>
            </div>
        </div>

        <!-- Fila Superior (QWER UIOP) -->
        <div class="col-lg-4 col-md-6 mb-4">
            <div class="card h-100 border-0 shadow-sm training-card" data-training="top-row">
                <div class="card-header bg-success text-white text-center">
                    <h5 class="mb-0"><i class="fas fa-arrow-up me-2"></i>Fila Superior</h5>
                </div>
                <div class="card-body">
                    <div class="keyboard-preview mb-3">
                        <div class="keyboard-row">
                            <span class="key highlight">Q</span>
                            <span class="key highlight">W</span>
                            <span class="key highlight">E</span>
                            <span class="key highlight">R</span>
                            <span class="key">T</span>
                            <span class="key">Y</span>
                            <span class="key highlight">U</span>
                            <span class="key highlight">I</span>
                            <span class="key highlight">O</span>
                            <span class="key highlight">P</span>
                        </div>
                    </div>
                    <p class="text-muted mb-3">
                        Extensión hacia arriba desde la fila central. Mejora la coordinación.
                    </p>
                    <div class="mb-3">
                        <div class="d-flex justify-content-between">
                            <small><i class="fas fa-arrow-up"></i> Movimiento vertical</small>
                            <small><i class="fas fa-star"></i> Intermedio</small>
                        </div>
                    </div>
                    <button class="btn btn-success w-100 start-training" data-mode="top-row">
                        <i class="fas fa-play me-2"></i>Comenzar
                    </button>
                </div>
            </div>
        </div>

        <!-- Fila Inferior (ZXCV BNM) -->
        <div class="col-lg-4 col-md-6 mb-4">
            <div class="card h-100 border-0 shadow-sm training-card" data-training="bottom-row">
                <div class="card-header bg-warning text-white text-center">
                    <h5 class="mb-0"><i class="fas fa-arrow-down me-2"></i>Fila Inferior</h5>
                </div>
                <div class="card-body">
                    <div class="keyboard-preview mb-3">
                        <div class="keyboard-row">
                            <span class="key highlight">Z</span>
                            <span class="key highlight">X</span>
                            <span class="key highlight">C</span>
                            <span class="key highlight">V</span>
                            <span class="key">B</span>
                            <span class="key">N</span>
                            <span class="key highlight">M</span>
                            <span class="key highlight">,</span>
                            <span class="key highlight">.</span>
                            <span class="key highlight">/</span>
                        </div>
                    </div>
                    <p class="text-muted mb-3">
                        Extensión hacia abajo. Perfecciona la movilidad de los dedos.
                    </p>
                    <div class="mb-3">
                        <div class="d-flex justify-content-between">
                            <small><i class="fas fa-arrow-down"></i> Movimiento hacia abajo</small>
                            <small><i class="fas fa-star"></i> Intermedio</small>
                        </div>
                    </div>
                    <button class="btn btn-warning w-100 start-training" data-mode="bottom-row">
                        <i class="fas fa-play me-2"></i>Comenzar
                    </button>
                </div>
            </div>
        </div>

        <!-- Números y Símbolos -->
        <div class="col-lg-4 col-md-6 mb-4">
            <div class="card h-100 border-0 shadow-sm training-card" data-training="numbers">
                <div class="card-header bg-info text-white text-center">
                    <h5 class="mb-0"><i class="fas fa-hashtag me-2"></i>Números y Símbolos</h5>
                </div>
                <div class="card-body">
                    <div class="keyboard-preview mb-3">
                        <div class="keyboard-row">
                            <span class="key highlight">1</span>
                            <span class="key highlight">2</span>
                            <span class="key highlight">3</span>
                            <span class="key highlight">4</span>
                            <span class="key highlight">5</span>
                            <span class="key highlight">6</span>
                            <span class="key highlight">7</span>
                            <span class="key highlight">8</span>
                            <span class="key highlight">9</span>
                            <span class="key highlight">0</span>
                        </div>
                    </div>
                    <p class="text-muted mb-3">
                        Domina los números y símbolos especiales para escritura completa.
                    </p>
                    <div class="mb-3">
                        <div class="d-flex justify-content-between">
                            <small><i class="fas fa-calculator"></i> Números y símbolos</small>
                            <small><i class="fas fa-star"></i> Avanzado</small>
                        </div>
                    </div>
                    <button class="btn btn-info w-100 start-training" data-mode="numbers">
                        <i class="fas fa-play me-2"></i>Comenzar
                    </button>
                </div>
            </div>
        </div>

        <!-- Palabras Comunes -->
        <div class="col-lg-4 col-md-6 mb-4">
            <div class="card h-100 border-0 shadow-sm training-card" data-training="common-words">
                <div class="card-header bg-secondary text-white text-center">
                    <h5 class="mb-0"><i class="fas fa-book me-2"></i>Palabras Comunes</h5>
                </div>
                <div class="card-body">
                    <div class="word-preview mb-3">
                        <span class="word-badge">que</span>
                        <span class="word-badge">para</span>
                        <span class="word-badge">con</span>
                        <span class="word-badge">una</span>
                        <span class="word-badge">por</span>
                    </div>
                    <p class="text-muted mb-3">
                        Practica las palabras más frecuentes en español para fluidez.
                    </p>
                    <div class="mb-3">
                        <div class="d-flex justify-content-between">
                            <small><i class="fas fa-language"></i> Vocabulario frecuente</small>
                            <small><i class="fas fa-star"></i> Práctico</small>
                        </div>
                    </div>
                    <button class="btn btn-secondary w-100 start-training" data-mode="common-words">
                        <i class="fas fa-play me-2"></i>Comenzar
                    </button>
                </div>
            </div>
        </div>

        <!-- Combinaciones de Dedos -->
        <div class="col-lg-4 col-md-6 mb-4">
            <div class="card h-100 border-0 shadow-sm training-card" data-training="finger-combos">
                <div class="card-header bg-dark text-white text-center">
                    <h5 class="mb-0"><i class="fas fa-hand-rock me-2"></i>Combinaciones</h5>
                </div>
                <div class="card-body">
                    <div class="combo-preview mb-3">
                        <span class="combo-badge">fr</span>
                        <span class="combo-badge">ju</span>
                        <span class="combo-badge">ki</span>
                        <span class="combo-badge">de</span>
                        <span class="combo-badge">lo</span>
                    </div>
                    <p class="text-muted mb-3">
                        Mejora la coordinación entre dedos con combinaciones desafiantes.
                    </p>
                    <div class="mb-3">
                        <div class="d-flex justify-content-between">
                            <small><i class="fas fa-link"></i> Coordinación</small>
                            <small><i class="fas fa-star"></i> Experto</small>
                        </div>
                    </div>
                    <button class="btn btn-dark w-100 start-training" data-mode="finger-combos">
                        <i class="fas fa-play me-2"></i>Comenzar
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Training Game Modal -->
<div class="modal fade" id="trainingModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="trainingModalTitle">Modo Entrenamiento</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <!-- Game Stats -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="stat-card">
                            <div class="stat-value" id="currentWPM">0</div>
                            <div class="stat-label">WPM</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card">
                            <div class="stat-value" id="currentAccuracy">100</div>
                            <div class="stat-label">Precisión %</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card">
                            <div class="stat-value" id="currentProgress">0</div>
                            <div class="stat-label">Progreso %</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card">
                            <div class="stat-value" id="currentScore">0</div>
                            <div class="stat-label">Puntos</div>
                        </div>
                    </div>
                </div>

                <!-- Typing Area -->
                <div class="typing-container">
                    <div class="text-to-type" id="textToType">
                        Cargando texto de entrenamiento...
                    </div>
                    <div class="typing-input-container">
                        <input type="text" id="typingInput" class="typing-input" placeholder="Empieza a escribir aquí..." autocomplete="off" spellcheck="false">
                    </div>
                </div>

                <!-- Keyboard Guide -->
                <div class="keyboard-guide mt-4" id="keyboardGuide">
                    <!-- Se llenará dinámicamente según el modo -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Salir</button>
                <button type="button" class="btn btn-primary" id="restartTraining">Reiniciar</button>
            </div>
        </div>
    </div>
</div>

<style>
.training-card {
    transition: transform 0.3s ease, box-shadow 0.3s ease;
    cursor: pointer;
}

.training-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.15);
}

.keyboard-preview {
    text-align: center;
}

.keyboard-row {
    display: flex;
    justify-content: center;
    gap: 2px;
    margin-bottom: 5px;
}

.key {
    display: inline-block;
    padding: 8px 10px;
    background: #f8f9fa;
    border: 2px solid #dee2e6;
    border-radius: 4px;
    font-weight: bold;
    font-size: 12px;
    min-width: 20px;
    text-align: center;
}

.key.highlight {
    background: #ffc107;
    border-color: #f0ad4e;
    color: #333;
}

.word-preview, .combo-preview {
    text-align: center;
    margin: 15px 0;
}

.word-badge, .combo-badge {
    display: inline-block;
    padding: 5px 10px;
    background: #e9ecef;
    border-radius: 15px;
    margin: 2px 4px;
    font-family: monospace;
    font-weight: bold;
    border: 1px solid #ced4da;
}

.stat-card {
    text-align: center;
    padding: 15px;
    background: #f8f9fa;
    border-radius: 8px;
    border: 1px solid #dee2e6;
}

.stat-value {
    font-size: 2rem;
    font-weight: bold;
    color: #495057;
}

.stat-label {
    font-size: 0.875rem;
    color: #6c757d;
    margin-top: 5px;
}

.typing-container {
    background: #ffffff;
    border: 2px solid #dee2e6;
    border-radius: 8px;
    padding: 30px;
    margin: 20px 0;
}

.text-to-type {
    font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
    font-size: 1.2rem;
    line-height: 1.8;
    margin-bottom: 20px;
    padding: 20px;
    background: #f8f9fa;
    border-radius: 6px;
    min-height: 120px;
}

.typing-input {
    width: 100%;
    font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
    font-size: 1.2rem;
    padding: 15px;
    border: 2px solid #ced4da;
    border-radius: 6px;
    outline: none;
    transition: border-color 0.3s ease;
}

.typing-input:focus {
    border-color: #007bff;
    box-shadow: 0 0 0 0.2rem rgba(0,123,255,.25);
}

.keyboard-guide {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 20px;
    border: 1px solid #dee2e6;
}

.finger-guide-description {
    font-size: 0.95rem;
    color: #495057;
}

.finger-legend {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.finger-legend-item {
    display: flex;
    gap: 10px;
    align-items: flex-start;
    background: #ffffff;
    border: 1px solid #e9ecef;
    border-radius: 10px;
    padding: 10px 12px;
    box-shadow: 0 2px 6px rgba(0,0,0,0.04);
}

.finger-dot {
    width: 16px;
    height: 16px;
    border-radius: 50%;
    flex-shrink: 0;
    margin-top: 4px;
    box-shadow: 0 0 0 2px rgba(255,255,255,0.6);
}

.finger-keyboard {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.finger-key-row {
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    gap: 8px;
}

.finger-key {
    min-width: 48px;
    padding: 10px 14px;
    text-align: center;
    font-weight: 600;
    border-radius: 10px;
    border: 1px solid #ced4da;
    background: #ffffff;
    color: #212529;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}

.finger-key[class*='finger-'] {
    color: #ffffff;
    border: none;
}

.finger-left-pinky {
    background: linear-gradient(135deg, #ff6b6b, #ff8787);
}

.finger-left-ring {
    background: linear-gradient(135deg, #ff922b, #ffb347);
}

.finger-left-middle {
    background: linear-gradient(135deg, #feca57, #ffd970);
}

.finger-left-index {
    background: linear-gradient(135deg, #1dd1a1, #48dbfb);
}

.finger-right-index {
    background: linear-gradient(135deg, #54a0ff, #2e86de);
}

.finger-right-middle {
    background: linear-gradient(135deg, #5f27cd, #7d5fff);
}

.finger-right-ring {
    background: linear-gradient(135deg, #ff9ff3, #f368e0);
}

.finger-right-pinky {
    background: linear-gradient(135deg, #ee5253, #ff6b6b);
}

.finger-thumb {
    background: linear-gradient(135deg, #8395a7, #c8d6e5);
    color: #212529;
}

.finger-tip {
    font-size: 0.9rem;
    color: #0c5460;
}

.modal-xl {
    max-width: 1200px;
}
</style>

<script>
let currentTrainingMode = '';
let currentText = '';
let currentPosition = 0;
let startTime = null;
let correctChars = 0;
let totalChars = 0;
let errors = 0;
let isGameActive = false;

// Textos de entrenamiento por modo
const trainingTexts = {
    'central-row': [
        'asdf jklñ asdf jklñ asdf jklñ',
        'ask flask sad flask ask sad',
        'flask ask dash flask ask dash',
        'sad ask flask dash sad ask',
        'añs df kl sa df añs kl sa'
    ],
    'top-row': [
        'qwer uiop qwer uiop qwer uiop',
        'que por que por que por',
        'quepo quero pouer quepo quero',
        'power quote power quote power',
        'qui quo que qui quo que'
    ],
    'bottom-row': [
        'zxcv bnm, zxcv bnm, zxcv bnm,',
        'zoom cox zoom cox zoom cox',
        'vox zcom vox zcom vox zcom',
        'cox zoom nvm cox zoom nvm',
        'zv xc nb mv zv xc nb mv'
    ],
    'numbers': [
        '1234 5678 90 1234 5678 90',
        '12 34 56 78 90 12 34 56',
        '147 258 369 147 258 369',
        '159 260 371 480 159 260',
        '1357 2468 90 1357 2468'
    ],
    'common-words': [
        'que para con una por',
        'que para con una por de la',
        'el en se que para con una',
        'es que para con una por de',
        'que para con una por de la en'
    ],
    'finger-combos': [
        'fr ju ki de lo fr ju ki',
        'gr hy ju fr ki lo de gr',
        'sw ju ki de fr lo sw ju',
        'fr de ju ki lo gr hy sw',
        'qw er ty ui op as df gh'
    ]
};

const fingerGuides = {
    'central-row': {
        description: 'Esta es tu fila base. Coloca los dedos índices sobre las teclas con relieve (F y J) y mantén el resto de dedos alineados sin presionar con fuerza.',
        instructions: [
            { finger: 'Meñique izquierdo', className: 'finger-left-pinky', keys: ['A'], description: 'También se encarga de la tecla Shift izquierda.' },
            { finger: 'Anular izquierdo', className: 'finger-left-ring', keys: ['S'] },
            { finger: 'Medio izquierdo', className: 'finger-left-middle', keys: ['D'] },
            { finger: 'Índice izquierdo', className: 'finger-left-index', keys: ['F', 'G'] },
            { finger: 'Índice derecho', className: 'finger-right-index', keys: ['H', 'J'] },
            { finger: 'Medio derecho', className: 'finger-right-middle', keys: ['K'] },
            { finger: 'Anular derecho', className: 'finger-right-ring', keys: ['L'] },
            { finger: 'Meñique derecho', className: 'finger-right-pinky', keys: ['Ñ'], description: 'También cubre Enter y Shift derecho.' }
        ],
        keyboard: [
            [
                { label: 'A', finger: 'finger-left-pinky' },
                { label: 'S', finger: 'finger-left-ring' },
                { label: 'D', finger: 'finger-left-middle' },
                { label: 'F', finger: 'finger-left-index' },
                { label: 'G', finger: 'finger-left-index' },
                { label: 'H', finger: 'finger-right-index' },
                { label: 'J', finger: 'finger-right-index' },
                { label: 'K', finger: 'finger-right-middle' },
                { label: 'L', finger: 'finger-right-ring' },
                { label: 'Ñ', finger: 'finger-right-pinky' }
            ]
        ],
        tip: 'Relaja las muñecas y vuelve siempre a F y J tras cada palabra para reforzar la memoria muscular.'
    },
    'top-row': {
        description: 'Extiende suavemente los dedos hacia la fila superior manteniendo los nudillos relajados. Evita levantar toda la mano.',
        instructions: [
            { finger: 'Meñique izquierdo', className: 'finger-left-pinky', keys: ['Q'], description: 'También cubre 1 y Tab.' },
            { finger: 'Anular izquierdo', className: 'finger-left-ring', keys: ['W', '2'] },
            { finger: 'Medio izquierdo', className: 'finger-left-middle', keys: ['E', '3'] },
            { finger: 'Índice izquierdo', className: 'finger-left-index', keys: ['R', 'T', '4', '5'] },
            { finger: 'Índice derecho', className: 'finger-right-index', keys: ['Y', 'U', '6', '7'] },
            { finger: 'Medio derecho', className: 'finger-right-middle', keys: ['I', '8'] },
            { finger: 'Anular derecho', className: 'finger-right-ring', keys: ['O', '9'] },
            { finger: 'Meñique derecho', className: 'finger-right-pinky', keys: ['P', '0'], description: 'Incluye la tecla ñ o Enter según tu teclado.' }
        ],
        keyboard: [
            [
                { label: 'Q', finger: 'finger-left-pinky' },
                { label: 'W', finger: 'finger-left-ring' },
                { label: 'E', finger: 'finger-left-middle' },
                { label: 'R', finger: 'finger-left-index' },
                { label: 'T', finger: 'finger-left-index' },
                { label: 'Y', finger: 'finger-right-index' },
                { label: 'U', finger: 'finger-right-index' },
                { label: 'I', finger: 'finger-right-middle' },
                { label: 'O', finger: 'finger-right-ring' },
                { label: 'P', finger: 'finger-right-pinky' }
            ]
        ],
        tip: 'Procura que el movimiento surja desde los nudillos: sube solo el dedo necesario sin perder la postura base.'
    },
    'bottom-row': {
        description: 'Desciende desde la fila base manteniendo contacto ligero con las teclas. La muñeca debe permanecer estable.',
        instructions: [
            { finger: 'Meñique izquierdo', className: 'finger-left-pinky', keys: ['Z'], description: 'También alcanza Shift.' },
            { finger: 'Anular izquierdo', className: 'finger-left-ring', keys: ['X'] },
            { finger: 'Medio izquierdo', className: 'finger-left-middle', keys: ['C'] },
            { finger: 'Índice izquierdo', className: 'finger-left-index', keys: ['V', 'B'] },
            { finger: 'Índice derecho', className: 'finger-right-index', keys: ['N'] },
            { finger: 'Índice derecho', className: 'finger-right-index', keys: ['M'], description: 'Comparte letra con el índice derecho.' },
            { finger: 'Medio derecho', className: 'finger-right-middle', keys: [','] },
            { finger: 'Anular derecho', className: 'finger-right-ring', keys: ['.'] },
            { finger: 'Meñique derecho', className: 'finger-right-pinky', keys: ['/'], description: 'También atiende a Shift derecho.' },
            { finger: 'Pulgares', className: 'finger-thumb', keys: ['Barra espaciadora'] }
        ],
        keyboard: [
            [
                { label: 'Z', finger: 'finger-left-pinky' },
                { label: 'X', finger: 'finger-left-ring' },
                { label: 'C', finger: 'finger-left-middle' },
                { label: 'V', finger: 'finger-left-index' },
                { label: 'B', finger: 'finger-left-index' },
                { label: 'N', finger: 'finger-right-index' },
                { label: 'M', finger: 'finger-right-index' },
                { label: ',', finger: 'finger-right-middle' },
                { label: '.', finger: 'finger-right-ring' },
                { label: '/', finger: 'finger-right-pinky' }
            ]
        ],
        tip: 'Los pulgares descansan sobre la barra espaciadora; sólo uno presiona la barra por vez.'
    },
    'numbers': {
        description: 'Los números utilizan la misma lógica por dedos. Desplaza los índices hacia la fila superior sin levantar la palma.',
        instructions: [
            { finger: 'Meñique izquierdo', className: 'finger-left-pinky', keys: ['1', '¡'] },
            { finger: 'Anular izquierdo', className: 'finger-left-ring', keys: ['2', '"'] },
            { finger: 'Medio izquierdo', className: 'finger-left-middle', keys: ['3', '·'] },
            { finger: 'Índice izquierdo', className: 'finger-left-index', keys: ['4', '$', '5', '%'] },
            { finger: 'Índice derecho', className: 'finger-right-index', keys: ['6', '&', '7', '/'] },
            { finger: 'Medio derecho', className: 'finger-right-middle', keys: ['8', '('] },
            { finger: 'Anular derecho', className: 'finger-right-ring', keys: ['9', ')'] },
            { finger: 'Meñique derecho', className: 'finger-right-pinky', keys: ['0', '='], description: 'También alcanza guiones y signos combinados.' }
        ],
        keyboard: [
            [
                { label: '1', finger: 'finger-left-pinky' },
                { label: '2', finger: 'finger-left-ring' },
                { label: '3', finger: 'finger-left-middle' },
                { label: '4', finger: 'finger-left-index' },
                { label: '5', finger: 'finger-left-index' },
                { label: '6', finger: 'finger-right-index' },
                { label: '7', finger: 'finger-right-index' },
                { label: '8', finger: 'finger-right-middle' },
                { label: '9', finger: 'finger-right-ring' },
                { label: '0', finger: 'finger-right-pinky' }
            ]
        ],
        tip: 'Practica lentamente hasta que la distancia a la fila superior se sienta natural; mira la pantalla, no el teclado.'
    },
    'common-words': {
        description: 'Trabaja las palabras frecuentes del español reforzando los dedos índice y medio para combinar filas superior y base.',
        instructions: [
            { finger: 'Índice izquierdo', className: 'finger-left-index', keys: ['F', 'R', 'T', 'G'], description: 'Coordina con las vocales cercanas (E, D) para formar "que", "para".' },
            { finger: 'Índice derecho', className: 'finger-right-index', keys: ['H', 'J', 'N'], description: 'Ayuda a componer "con", "una", "por".' },
            { finger: 'Medios', className: 'finger-left-middle', keys: ['E'], description: 'Los dedos medios aportan las vocales centrales (E/I).' },
            { finger: 'Medios', className: 'finger-right-middle', keys: ['I'] },
            { finger: 'Anulares', className: 'finger-left-ring', keys: ['S'], description: 'Facilitan terminaciones como "-as", "-es".' },
            { finger: 'Anulares', className: 'finger-right-ring', keys: ['L'] },
            { finger: 'Meñiques', className: 'finger-left-pinky', keys: ['Q'], description: 'Activan conectores como "que"; recuerda usar Shift para mayúsculas.' },
            { finger: 'Meñiques', className: 'finger-right-pinky', keys: ['Ñ', 'P', 'Ó'] }
        ],
        keyboard: [
            [
                { label: 'Q', finger: 'finger-left-pinky' },
                { label: 'W', finger: 'finger-left-ring' },
                { label: 'E', finger: 'finger-left-middle' },
                { label: 'R', finger: 'finger-left-index' },
                { label: 'T', finger: 'finger-left-index' },
                { label: 'Y', finger: 'finger-right-index' },
                { label: 'U', finger: 'finger-right-index' },
                { label: 'I', finger: 'finger-right-middle' },
                { label: 'O', finger: 'finger-right-ring' },
                { label: 'P', finger: 'finger-right-pinky' }
            ],
            [
                { label: 'A', finger: 'finger-left-pinky' },
                { label: 'S', finger: 'finger-left-ring' },
                { label: 'D', finger: 'finger-left-middle' },
                { label: 'F', finger: 'finger-left-index' },
                { label: 'G', finger: 'finger-left-index' },
                { label: 'H', finger: 'finger-right-index' },
                { label: 'J', finger: 'finger-right-index' },
                { label: 'K', finger: 'finger-right-middle' },
                { label: 'L', finger: 'finger-right-ring' },
                { label: 'Ñ', finger: 'finger-right-pinky' }
            ]
        ],
        tip: 'Integra las palabras completas sin mirar el teclado; confía en la memoria muscular de la fila base.'
    },
    'finger-combos': {
        description: 'Practica combinaciones rápidas entre dedos vecinos. Mantén los movimientos cortos para ganar precisión.',
        instructions: [
            { finger: 'Índice izquierdo', className: 'finger-left-index', keys: ['F', 'R', 'D', 'E'], description: 'Alterna entre fila base y superior sin levantar el codo.' },
            { finger: 'Índice derecho', className: 'finger-right-index', keys: ['J', 'U', 'N'], description: 'Refuerza saltos cortos hacia la fila superior.' },
            { finger: 'Medio derecho', className: 'finger-right-middle', keys: ['K', 'I'] },
            { finger: 'Anular derecho', className: 'finger-right-ring', keys: ['L', 'O'] },
            { finger: 'Pulgares', className: 'finger-thumb', keys: ['Espacio'], description: 'Sincroniza la barra espaciadora con el cambio de palabra.' }
        ],
        keyboard: [
            [
                { label: 'F', finger: 'finger-left-index' },
                { label: 'R', finger: 'finger-left-index' },
                { label: 'D', finger: 'finger-left-middle' },
                { label: 'E', finger: 'finger-left-middle' },
                { label: 'J', finger: 'finger-right-index' },
                { label: 'U', finger: 'finger-right-index' },
                { label: 'K', finger: 'finger-right-middle' },
                { label: 'I', finger: 'finger-right-middle' },
                { label: 'L', finger: 'finger-right-ring' },
                { label: 'O', finger: 'finger-right-ring' }
            ]
        ],
        tip: 'Concéntrate en mantener un ritmo constante. Golpea cada tecla con el dedo asignado y vuelve a la fila base.'
    }
};

document.addEventListener('DOMContentLoaded', function() {
    // Hacer que las tarjetas sean clickeables
    document.querySelectorAll('.training-card').forEach(card => {
        card.addEventListener('click', function(e) {
            if (e.target.tagName !== 'BUTTON') {
                const button = this.querySelector('.start-training');
                if (button) {
                    button.click();
                }
            }
        });
    });

    // Iniciar entrenamientos
    document.querySelectorAll('.start-training').forEach(button => {
        button.addEventListener('click', function(e) {
            e.stopPropagation();
            const mode = this.getAttribute('data-mode');
            startTraining(mode);
        });
    });

    // Reiniciar entrenamiento
    document.getElementById('restartTraining').addEventListener('click', function() {
        if (currentTrainingMode) {
            startTraining(currentTrainingMode);
        }
    });

    // Manejo de entrada de texto
    document.getElementById('typingInput').addEventListener('input', function(e) {
        if (!isGameActive) return;
        handleTyping(e.target.value);
    });

    // Resetear al cerrar modal
    document.getElementById('trainingModal').addEventListener('hidden.bs.modal', function() {
        resetGame();
    });
});

function startTraining(mode) {
    currentTrainingMode = mode;
    const modal = new bootstrap.Modal(document.getElementById('trainingModal'));
    
    // Configurar título del modal
    const modeNames = {
        'central-row': 'Fila Central (ASDF JKLÑ)',
        'top-row': 'Fila Superior (QWER UIOP)', 
        'bottom-row': 'Fila Inferior (ZXCV BNM)',
        'numbers': 'Números y Símbolos',
        'common-words': 'Palabras Comunes',
        'finger-combos': 'Combinaciones de Dedos'
    };
    
    document.getElementById('trainingModalTitle').textContent = modeNames[mode];
    
    // Obtener texto aleatorio para el modo
    const texts = trainingTexts[mode];
    currentText = texts[Math.floor(Math.random() * texts.length)];
    
    // Configurar el juego
    resetGame();
    document.getElementById('textToType').textContent = currentText;
    
    modal.show();
    
    // Enfocar input después de mostrar modal
    setTimeout(() => {
        document.getElementById('typingInput').focus();
    }, 500);
}

function resetGame() {
    currentPosition = 0;
    startTime = null;
    correctChars = 0;
    totalChars = 0;
    errors = 0;
    isGameActive = true;
    
    document.getElementById('typingInput').value = '';
    updateStats();
    updateTextDisplay();
}

function handleTyping(input) {
    if (!startTime) {
        startTime = Date.now();
    }
    
    totalChars = input.length;
    correctChars = 0;
    errors = 0;
    
    // Calcular precisión
    for (let i = 0; i < input.length && i < currentText.length; i++) {
        if (input[i] === currentText[i]) {
            correctChars++;
        } else {
            errors++;
        }
    }
    
    currentPosition = input.length;
    
    updateStats();
    updateTextDisplay();
    
    // Verificar si se completó el texto
    if (input === currentText) {
        completeTraining();
    }
}

function updateStats() {
    const elapsed = startTime ? (Date.now() - startTime) / 1000 / 60 : 0; // minutos
    const wpm = elapsed > 0 ? Math.round((correctChars / 5) / elapsed) : 0;
    const accuracy = totalChars > 0 ? Math.round((correctChars / totalChars) * 100) : 100;
    const progress = Math.round((currentPosition / currentText.length) * 100);
    const score = Math.round(wpm * (accuracy / 100) * 10);
    
    document.getElementById('currentWPM').textContent = wpm;
    document.getElementById('currentAccuracy').textContent = accuracy;
    document.getElementById('currentProgress').textContent = progress;
    document.getElementById('currentScore').textContent = score;
}

function updateTextDisplay() {
    const textElement = document.getElementById('textToType');
    const input = document.getElementById('typingInput').value;
    
    let html = '';
    
    for (let i = 0; i < currentText.length; i++) {
        if (i < input.length) {
            if (input[i] === currentText[i]) {
                html += `<span style="background-color: #d4edda; color: #155724;">${currentText[i]}</span>`;
            } else {
                html += `<span style="background-color: #f8d7da; color: #721c24;">${currentText[i]}</span>`;
            }
        } else if (i === input.length) {
            html += `<span style="background-color: #007bff; color: white;">${currentText[i]}</span>`;
        } else {
            html += currentText[i];
        }
    }
    
    textElement.innerHTML = html;
}

function completeTraining() {
    isGameActive = false;
    
    const elapsed = (Date.now() - startTime) / 1000;
    const wpm = Math.round((correctChars / 5) / (elapsed / 60));
    const accuracy = Math.round((correctChars / totalChars) * 100);
    const score = Math.round(wpm * (accuracy / 100) * 10);
    
    // Guardar puntuación
    saveTrainingScore(currentTrainingMode, score, wpm, accuracy, Math.round(elapsed));
    
    // Mostrar mensaje de felicitación
    setTimeout(() => {
        Swal.fire({
            title: '¡Entrenamiento completado! 🏆',
            html: `
                <div class="text-start">
                    <p class="mb-1"><strong>WPM:</strong> ${wpm}</p>
                    <p class="mb-1"><strong>Precisión:</strong> ${accuracy}%</p>
                    <p class="mb-0"><strong>Puntuación:</strong> ${score}</p>
                </div>
            `,
            icon: 'success',
            confirmButtonText: 'Continuar',
            confirmButtonColor: '#0d6efd',
            allowOutsideClick: true
        });
    }, 500);
}

function saveTrainingScore(mode, score, wpm, accuracy, timeSeconds) {
    fetch('{{ route("typing.mode.save.score") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        },
        body: JSON.stringify({
            mode: 'training',
            score: score,
            wpm: wpm,
            accuracy: accuracy,
            time_played: timeSeconds,
            extra_data: {
                training_type: mode,
                text_used: currentText
            }
        })
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
</script>
@endsection
