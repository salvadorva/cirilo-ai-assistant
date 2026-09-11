@extends('layout.app')

@section('title', 'Sentence Builder')

@section('content')
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />
<style>
    .game-container {
        max-width: 1200px;
        margin: 0 auto;
    }
    .game-header {
        background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        color: white;
        padding: 2rem;
        border-radius: 15px;
        margin-bottom: 2rem;
    }
    
    /* Área de construcción de oraciones */
    .sentence-area {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border-radius: 15px;
        padding: 3rem;
        min-height: 150px;
        margin-bottom: 2rem;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-wrap: wrap;
        gap: 10px;
    }
    
    .sentence-area.empty::before {
        content: "Arrastra las palabras aquí para formar la oración";
        color: rgba(255, 255, 255, 0.6);
        font-size: 1.2rem;
        position: absolute;
    }
    
    /* Tarjetas de palabras */
    .word-chip {
        background: white;
        padding: 1rem 1.5rem;
        border-radius: 10px;
        font-size: 1.3rem;
        font-weight: 600;
        cursor: grab;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        transition: all 0.3s;
        user-select: none;
    }
    
    .word-chip:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 15px rgba(0, 0, 0, 0.2);
    }
    
    .word-chip:active {
        cursor: grabbing;
        transform: scale(0.95);
    }
    
    .word-chip.dragging {
        opacity: 0.5;
    }
    
    .word-chip.in-sentence {
        background: linear-gradient(135deg, #50cd89 0%, #43e97b 100%);
        color: white;
    }
    
    /* Área de palabras disponibles */
    .words-pool {
        background: #f8f9fa;
        border-radius: 15px;
        padding: 2rem;
        min-height: 150px;
        display: flex;
        flex-wrap: wrap;
        gap: 15px;
        justify-content: center;
        align-items: center;
    }
    
    /* Área de drop */
    .drop-zone {
        position: relative;
        transition: all 0.3s;
    }
    
    .drop-zone.drag-over {
        background: rgba(255, 255, 255, 0.2);
        border: 3px dashed white;
    }
    
    /* Traducción de la oración */
    .translation-hint {
        background: #e8f4fd;
        border-left: 4px solid #009ef7;
        padding: 1.5rem;
        border-radius: 10px;
        margin-bottom: 2rem;
    }
    
    /* Botones de acción */
    .action-buttons {
        display: flex;
        gap: 1rem;
        justify-content: center;
        margin-top: 2rem;
    }
    
    /* Animaciones */
    @keyframes correct {
        0%, 100% { transform: scale(1); }
        50% { transform: scale(1.1); }
    }
    
    @keyframes incorrect {
        0%, 100% { transform: translateX(0); }
        25% { transform: translateX(-10px); }
        75% { transform: translateX(10px); }
    }
    
    .correct-animation {
        animation: correct 0.5s ease;
    }
    
    .incorrect-animation {
        animation: incorrect 0.5s ease;
    }
    
    /* Modal de resultados */
    .game-over-modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.8);
        z-index: 9999;
        align-items: center;
        justify-content: center;
    }
    
    .game-over-content {
        background: white;
        padding: 3rem;
        border-radius: 20px;
        text-align: center;
        max-width: 500px;
        animation: slideIn 0.5s ease;
    }
    
    @keyframes slideIn {
        from {
            transform: translateY(-100px);
            opacity: 0;
        }
        to {
            transform: translateY(0);
            opacity: 1;
        }
    }
    
    .score-badge {
        font-size: 1.5rem;
        padding: 0.5rem 1rem;
    }
</style>

<div class="game-container">
    <!-- Breadcrumb -->
    <div class="mb-4">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('tutor') }}">Centro de Aprendizaje</a></li>
                <li class="breadcrumb-item"><a href="{{ route('tutor.english') }}">Curso de Inglés</a></li>
                <li class="breadcrumb-item"><a href="{{ route('games.index') }}">Juegos</a></li>
                <li class="breadcrumb-item active">Sentence Builder</li>
            </ol>
        </nav>
    </div>

    <!-- Game Header -->
    <div class="game-header">
        <div class="row align-items-center">
            <div class="col-md-4 text-center">
                <div>
                    <span style="font-size: 2rem;" id="currentQuestion">1</span>
                    <span style="font-size: 1.5rem;">/</span>
                    <span style="font-size: 1.5rem;" id="totalQuestions">10</span>
                </div>
                <small>Pregunta</small>
            </div>
            <div class="col-md-4 text-center">
                <h2 class="mb-0">🧩 Sentence Builder</h2>
                <p class="mb-0">Nivel {{ $level }}</p>
                <button class="btn btn-sm btn-outline-light mt-2" id="musicToggle" onclick="toggleMusic()">
                    <i class="fas fa-volume-up" id="musicIcon"></i>
                </button>
            </div>
            <div class="col-md-4 text-center">
                <div class="mb-2">
                    <span id="lives-display">
                        <i class="fas fa-heart text-danger"></i>
                        <i class="fas fa-heart text-danger"></i>
                        <i class="fas fa-heart text-danger"></i>
                    </span>
                </div>
                <div class="score-badge">
                    <i class="fas fa-star"></i> <span id="score">0</span>
                </div>
                <small>Puntuación</small>
            </div>
        </div>
    </div>

    <!-- Instructions -->
    <div class="alert alert-info mb-4" id="instructions">
        <i class="fas fa-info-circle me-2"></i>
        <strong>¿Cómo jugar?</strong> Arrastra las palabras desde abajo hacia el área azul para construir la oración correcta en inglés. El orden importa!
        <button type="button" class="btn btn-sm btn-primary float-end" onclick="startGame()">
            <i class="fas fa-play"></i> Comenzar
        </button>
    </div>

    <!-- Game Board -->
    <div id="game-board" style="display: none;">
        <!-- Traducción (pista) -->
        <div class="translation-hint">
            <h4 class="mb-2">📝 Traduce al inglés:</h4>
            <p class="h5 mb-0" id="translation-text">-</p>
        </div>

        <!-- Área de construcción de oraciones -->
        <div class="sentence-area drop-zone" id="sentenceArea">
            <!-- Las palabras arrastradas aparecerán aquí -->
        </div>

        <!-- Pool de palabras disponibles -->
        <div class="words-pool" id="wordsPool">
            <!-- Las palabras desordenadas aparecerán aquí -->
        </div>

        <!-- Botones de acción -->
        <div class="action-buttons">
            <button class="btn btn-success btn-lg" onclick="checkSentence()">
                <i class="fas fa-check"></i> Verificar
            </button>
            <button class="btn btn-warning btn-lg" onclick="clearSentence()">
                <i class="fas fa-redo"></i> Limpiar
            </button>
            <button class="btn btn-info btn-lg" onclick="skipQuestion()">
                <i class="fas fa-forward"></i> Saltar
            </button>
        </div>
    </div>

    <!-- Game Over Modal -->
    <div class="game-over-modal" id="gameOverModal">
        <div class="game-over-content">
            <h1 class="mb-4">🎉 ¡Juego Terminado!</h1>
            <div class="row mb-4">
                <div class="col-6">
                    <h3 id="final-score">0</h3>
                    <p class="text-muted">Puntuación</p>
                </div>
                <div class="col-6">
                    <h3 id="final-correct">0/10</h3>
                    <p class="text-muted">Correctas</p>
                </div>
            </div>
            <div class="mb-4">
                <h4 id="xp-earned" class="text-success">+0 XP</h4>
            </div>
            <div class="d-flex gap-2 justify-content-center">
                <button class="btn btn-primary btn-lg" onclick="location.reload()">
                    <i class="fas fa-redo"></i> Jugar de Nuevo
                </button>
                <a href="{{ route('games.index') }}" class="btn btn-secondary btn-lg">
                    <i class="fas fa-home"></i> Menú Principal
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Audio Elements -->
<audio id="backgroundMusic" loop>
    <source src="{{ asset('audio/background_music.mp3') }}" type="audio/mpeg">
</audio>
<audio id="successSound">
    <source src="{{ asset('audio/success.mp3') }}" type="audio/mpeg">
</audio>
<audio id="errorSound">
    <source src="{{ asset('audio/error.mp3') }}" type="audio/mpeg">
</audio>
<audio id="gameStartSound">
    <source src="{{ asset('audio/game_start.mp3') }}" type="audio/mpeg">
</audio>
<audio id="victorySound">
    <source src="{{ asset('audio/victory.mp3') }}" type="audio/mpeg">
</audio>

<script>
// Datos del juego
const sentences = @json($sentences);
let currentSentenceIndex = 0;
let score = 0;
let correctAnswers = 0;
let startTime = null;
let sentenceStartTime = null;
let lives = 3; // Sistema de vidas

// Audio elements
const backgroundMusic = document.getElementById('backgroundMusic');
const successSound = document.getElementById('successSound');
const errorSound = document.getElementById('errorSound');
const gameStartSound = document.getElementById('gameStartSound');
const victorySound = document.getElementById('victorySound');

// Configurar volúmenes
backgroundMusic.volume = 0.3;
successSound.volume = 0.5;
errorSound.volume = 0.5;
gameStartSound.volume = 0.5;
victorySound.volume = 0.7;

// Funciones de audio
function playSound(sound) {
    sound.currentTime = 0;
    sound.play().catch(e => console.log('Error playing sound:', e));
}

function startBackgroundMusic() {
    backgroundMusic.play().catch(e => console.log('Error playing background music:', e));
}

function stopBackgroundMusic() {
    backgroundMusic.pause();
    backgroundMusic.currentTime = 0;
}

let musicEnabled = true;

function toggleMusic() {
    musicEnabled = !musicEnabled;
    const icon = document.getElementById('musicIcon');
    
    if (musicEnabled) {
        backgroundMusic.play().catch(e => console.log('Error playing music:', e));
        icon.className = 'fas fa-volume-up';
    } else {
        backgroundMusic.pause();
        icon.className = 'fas fa-volume-mute';
    }
}

function startGame() {
    document.getElementById('instructions').style.display = 'none';
    document.getElementById('game-board').style.display = 'block';
    startTime = Date.now();
    
    // Reproducir sonidos
    playSound(gameStartSound);
    setTimeout(() => startBackgroundMusic(), 500);
    
    // Cargar primera pregunta
    loadSentence();
}

function loadSentence() {
    if (currentSentenceIndex >= sentences.length) {
        endGame();
        return;
    }
    
    sentenceStartTime = Date.now();
    const sentence = sentences[currentSentenceIndex];
    
    // Actualizar UI
    document.getElementById('currentQuestion').textContent = currentSentenceIndex + 1;
    document.getElementById('totalQuestions').textContent = sentences.length;
    document.getElementById('translation-text').textContent = sentence.translation;
    
    // Limpiar áreas
    document.getElementById('sentenceArea').innerHTML = '';
    document.getElementById('wordsPool').innerHTML = '';
    
    // Desordenar palabras y crear chips
    const shuffledWords = [...sentence.words].sort(() => Math.random() - 0.5);
    const pool = document.getElementById('wordsPool');
    
    shuffledWords.forEach((word, index) => {
        const chip = createWordChip(word, index);
        pool.appendChild(chip);
    });
    
    // Marcar área como vacía
    document.getElementById('sentenceArea').classList.add('empty');
}

function createWordChip(word, index) {
    const chip = document.createElement('div');
    chip.className = 'word-chip';
    chip.textContent = word;
    chip.draggable = true;
    chip.dataset.word = word;
    chip.dataset.originalIndex = index;
    
    // Eventos de arrastre
    chip.addEventListener('dragstart', handleDragStart);
    chip.addEventListener('dragend', handleDragEnd);
    
    return chip;
}

function handleDragStart(e) {
    e.target.classList.add('dragging');
    e.dataTransfer.effectAllowed = 'move';
    e.dataTransfer.setData('text/html', e.target.outerHTML);
    e.dataTransfer.setData('word', e.target.dataset.word);
}

function handleDragEnd(e) {
    e.target.classList.remove('dragging');
}

// Configurar áreas de drop
const sentenceArea = document.getElementById('sentenceArea');
const wordsPool = document.getElementById('wordsPool');

[sentenceArea, wordsPool].forEach(zone => {
    zone.addEventListener('dragover', handleDragOver);
    zone.addEventListener('drop', handleDrop);
    zone.addEventListener('dragleave', handleDragLeave);
});

function handleDragOver(e) {
    if (e.preventDefault) {
        e.preventDefault();
    }
    e.dataTransfer.dropEffect = 'move';
    e.currentTarget.classList.add('drag-over');
    return false;
}

function handleDragLeave(e) {
    e.currentTarget.classList.remove('drag-over');
}

function handleDrop(e) {
    if (e.stopPropagation) {
        e.stopPropagation();
    }
    e.preventDefault();
    
    e.currentTarget.classList.remove('drag-over');
    
    const word = e.dataTransfer.getData('word');
    const draggingElement = document.querySelector('.dragging');
    
    if (draggingElement && e.currentTarget !== draggingElement.parentNode) {
        // Mover el elemento
        draggingElement.remove();
        const newChip = createWordChip(word, 0);
        
        if (e.currentTarget.id === 'sentenceArea') {
            newChip.classList.add('in-sentence');
            document.getElementById('sentenceArea').classList.remove('empty');
        }
        
        e.currentTarget.appendChild(newChip);
    }
    
    return false;
}

function checkSentence() {
    const sentenceArea = document.getElementById('sentenceArea');
    const chips = sentenceArea.querySelectorAll('.word-chip');
    
    if (chips.length === 0) {
        alert('Primero debes construir una oración');
        return;
    }
    
    // Construir oración del usuario
    const userSentence = Array.from(chips).map(chip => chip.textContent).join(' ');
    const correctSentence = sentences[currentSentenceIndex].correct;
    
    const timeSpent = (Date.now() - sentenceStartTime) / 1000;
    
    if (userSentence === correctSentence) {
        // ¡Correcto!
        playSound(successSound);
        sentenceArea.classList.add('correct-animation');
        
        // Calcular puntos (más rápido = más puntos)
        const timeBonus = Math.max(0, 100 - Math.floor(timeSpent * 5));
        score += 100 + timeBonus;
        correctAnswers++;
        
        document.getElementById('score').textContent = score;
        
        setTimeout(() => {
            sentenceArea.classList.remove('correct-animation');
            nextSentence();
        }, 1000);
    } else {
        // Incorrecto
        playSound(errorSound);
        sentenceArea.classList.add('incorrect-animation');
        
        score = Math.max(0, score - 30);
        document.getElementById('score').textContent = score;
        
        // Perder una vida
        lives--;
        updateLivesDisplay();
        
        setTimeout(() => {
            sentenceArea.classList.remove('incorrect-animation');
            
            // Si no quedan vidas, terminar el juego
            if (lives <= 0) {
                setTimeout(() => endGame(), 500);
            }
        }, 500);
    }
}

function clearSentence() {
    const sentenceArea = document.getElementById('sentenceArea');
    const chips = sentenceArea.querySelectorAll('.word-chip');
    const pool = document.getElementById('wordsPool');
    
    chips.forEach(chip => {
        chip.classList.remove('in-sentence');
        pool.appendChild(chip);
    });
    
    sentenceArea.classList.add('empty');
}

function skipQuestion() {
    score = Math.max(0, score - 50);
    document.getElementById('score').textContent = score;
    nextSentence();
}

function updateLivesDisplay() {
    const livesDisplay = document.getElementById('lives-display');
    let heartsHTML = '';
    
    for (let i = 0; i < 3; i++) {
        if (i < lives) {
            heartsHTML += '<i class="fas fa-heart text-danger"></i> ';
        } else {
            heartsHTML += '<i class="far fa-heart text-muted"></i> ';
        }
    }
    
    livesDisplay.innerHTML = heartsHTML;
    
    // Animación cuando pierde una vida
    if (lives < 3) {
        livesDisplay.classList.add('incorrect-animation');
        setTimeout(() => {
            livesDisplay.classList.remove('incorrect-animation');
        }, 500);
    }
}

function nextSentence() {
    currentSentenceIndex++;
    loadSentence();
}

function endGame() {
    stopBackgroundMusic();
    
    // Si terminó por falta de vidas, mostrar mensaje diferente
    const gameOver = lives <= 0;
    if (gameOver) {
        playSound(errorSound);
    } else {
        playSound(victorySound);
    }
    
    const totalTime = Math.floor((Date.now() - startTime) / 1000);
    const totalAttempted = currentSentenceIndex;
    const accuracy = totalAttempted > 0 ? (correctAnswers / totalAttempted) * 100 : 0;
    
    // Mostrar modal
    document.getElementById('final-score').textContent = score;
    document.getElementById('final-correct').textContent = `${correctAnswers}/${totalAttempted}`;
    document.getElementById('gameOverModal').style.display = 'flex';
    
    // Guardar resultados
    saveGameResult({
        game_type: 'sentence-builder',
        score: score,
        accuracy: accuracy,
        time_spent: totalTime,
        correct_answers: correctAnswers,
        total_questions: totalAttempted,
        level: {{ $level }}
    });
}

function saveGameResult(data) {
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
