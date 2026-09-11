@extends('layout.app')

@section('title', 'Word Match Rush')

@section('content')
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />
<style>
    .game-container {
        max-width: 1200px;
        margin: 0 auto;
    }
    .game-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 2rem;
        border-radius: 15px;
        margin-bottom: 2rem;
    }
    .word-card {
        border: 3px solid #e0e0e0;
        border-radius: 15px;
        padding: 2rem;
        text-align: center;
        cursor: pointer;
        transition: all 0.3s;
        background: white;
        height: 150px;
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
    }
    .word-card:hover {
        transform: scale(1.05);
        border-color: #667eea;
        box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
    }
    .word-card.selected {
        border-color: #667eea;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        transform: scale(1.05);
    }
    .word-card.matched {
        border-color: #50cd89;
        background: linear-gradient(135deg, #50cd89 0%, #43e97b 100%);
        color: white;
        pointer-events: none;
        animation: matchAnimation 0.5s ease;
    }
    .word-card.wrong {
        border-color: #f1416c;
        background: #ffe0e0;
        animation: shake 0.5s ease;
    }
    .word-emoji {
        font-size: 3rem;
        margin-bottom: 0.5rem;
    }
    .word-text {
        font-size: 1.5rem;
        font-weight: bold;
    }
    .timer {
        font-size: 2rem;
        font-weight: bold;
    }
    .score-badge {
        font-size: 1.5rem;
        padding: 0.5rem 1rem;
    }
    
    @keyframes matchAnimation {
        0%, 100% { transform: scale(1.05); }
        50% { transform: scale(1.2); }
    }
    
    @keyframes shake {
        0%, 100% { transform: translateX(0); }
        25% { transform: translateX(-10px); }
        75% { transform: translateX(10px); }
    }
    
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
</style>

<div class="game-container">
    <!-- Breadcrumb -->
    <div class="mb-4">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('tutor') }}">Centro de Aprendizaje</a></li>
                <li class="breadcrumb-item"><a href="{{ route('tutor.english') }}">Curso de Inglés</a></li>
                <li class="breadcrumb-item"><a href="{{ route('games.index') }}">Juegos</a></li>
                <li class="breadcrumb-item active">Word Match Rush</li>
            </ol>
        </nav>
    </div>

    <!-- Game Header -->
    <div class="game-header">
        <div class="row align-items-center">
            <div class="col-md-4 text-center">
                <div class="timer" id="timer">⏱️ 60</div>
                <small>Tiempo restante</small>
            </div>
            <div class="col-md-4 text-center">
                <h2 class="mb-0">🎯 Word Match Rush</h2>
                <p class="mb-0">Nivel {{ $level }}</p>
                <button class="btn btn-sm btn-outline-light mt-2" id="musicToggle" onclick="toggleMusic()">
                    <i class="fas fa-volume-up" id="musicIcon"></i>
                </button>
            </div>
            <div class="col-md-4 text-center">
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
        <strong>¿Cómo jugar?</strong> Selecciona una palabra en inglés y luego su traducción correcta en español. ¡Tienes 60 segundos!
        <button type="button" class="btn btn-sm btn-primary float-end" onclick="startGame()">
            <i class="fas fa-play"></i> Comenzar
        </button>
    </div>

    <!-- Game Board -->
    <div id="game-board" style="display: none;">
        <div class="row mb-4">
            <div class="col-md-6">
                <h4 class="text-center mb-3">English 🇬🇧</h4>
                <div id="english-words"></div>
            </div>
            <div class="col-md-6">
                <h4 class="text-center mb-3">Español 🇪🇸</h4>
                <div id="spanish-words"></div>
            </div>
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
                    <h3 id="final-accuracy">0%</h3>
                    <p class="text-muted">Precisión</p>
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
<audio id="levelUpSound">
    <source src="{{ asset('audio/level-up.mp3') }}" type="audio/mpeg">
</audio>
<audio id="gameStartSound">
    <source src="{{ asset('audio/game_start.mp3') }}" type="audio/mpeg">
</audio>
<audio id="victorySound">
    <source src="{{ asset('audio/victory.mp3') }}" type="audio/mpeg">
</audio>

<script>
// Game variables
const vocabulary = @json($vocabulary);
let gameActive = false;
let timeLeft = 60;
let score = 0;
let matches = 0;
let attempts = 0;
let correctAnswers = 0;
let selectedCard = null;
let selectedEnglish = null;
let selectedSpanish = null;
let timerInterval = null;
let startTime = null;
let lives = 3; // Sistema de vidas

// Audio elements
const backgroundMusic = document.getElementById('backgroundMusic');
const successSound = document.getElementById('successSound');
const errorSound = document.getElementById('errorSound');
const levelUpSound = document.getElementById('levelUpSound');
const gameStartSound = document.getElementById('gameStartSound');
const victorySound = document.getElementById('victorySound');

// Configurar volúmenes
backgroundMusic.volume = 0.3;
successSound.volume = 0.5;
errorSound.volume = 0.5;
levelUpSound.volume = 0.6;
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

function startGame() {
    document.getElementById('instructions').style.display = 'none';
    document.getElementById('game-board').style.display = 'block';
    gameActive = true;
    startTime = Date.now();
    
    // Reproducir sonidos
    playSound(gameStartSound);
    setTimeout(() => startBackgroundMusic(), 500);
    
    // Shuffle y renderizar palabras
    renderWords();
    
    // Iniciar timer
    startTimer();
}

function renderWords() {
    // Shuffle vocabulario
    const shuffled = [...vocabulary].sort(() => Math.random() - 0.5);
    const englishWords = shuffled.slice(0, 10);
    const spanishWords = [...englishWords].sort(() => Math.random() - 0.5);
    
    // Renderizar palabras en inglés
    const englishContainer = document.getElementById('english-words');
    englishContainer.innerHTML = '';
    englishWords.forEach((word, index) => {
        const card = createWordCard(word.word, word.image, 'english', index);
        englishContainer.appendChild(card);
    });
    
    // Renderizar palabras en español
    const spanishContainer = document.getElementById('spanish-words');
    spanishContainer.innerHTML = '';
    spanishWords.forEach((word, index) => {
        const card = createWordCard(word.translation, word.image, 'spanish', index);
        spanishContainer.appendChild(card);
    });
}

function createWordCard(text, emoji, type, index) {
    const div = document.createElement('div');
    div.className = 'word-card mb-3';
    div.dataset.word = text;
    div.dataset.type = type;
    div.dataset.originalIndex = vocabulary.findIndex(v => 
        type === 'english' ? v.word === text : v.translation === text
    );
    
    // Solo mostrar emoji en español, no en inglés
    if (type === 'spanish') {
        div.innerHTML = `
            <div class="word-emoji">${emoji}</div>
            <div class="word-text">${text}</div>
        `;
    } else {
        div.innerHTML = `
            <div class="word-text" style="font-size: 1.8rem; margin-top: 1rem;">${text}</div>
        `;
    }
    
    div.onclick = () => selectWord(div, type);
    
    return div;
}

function selectWord(card, type) {
    if (!gameActive || card.classList.contains('matched')) return;
    
    if (type === 'english') {
        // Deseleccionar anterior
        if (selectedEnglish) {
            selectedEnglish.classList.remove('selected');
        }
        selectedEnglish = card;
        card.classList.add('selected');
    } else {
        // Deseleccionar anterior
        if (selectedSpanish) {
            selectedSpanish.classList.remove('selected');
        }
        selectedSpanish = card;
        card.classList.add('selected');
    }
    
    // Si ambos están seleccionados, verificar match
    if (selectedEnglish && selectedSpanish) {
        checkMatch();
    }
}

function checkMatch() {
    attempts++;
    const englishIndex = parseInt(selectedEnglish.dataset.originalIndex);
    const spanishIndex = parseInt(selectedSpanish.dataset.originalIndex);
    
    if (englishIndex === spanishIndex) {
        // ¡Match correcto!
        playSound(successSound);
        
        selectedEnglish.classList.remove('selected');
        selectedEnglish.classList.add('matched');
        selectedSpanish.classList.remove('selected');
        selectedSpanish.classList.add('matched');
        
        score += 100;
        correctAnswers++;
        matches++;
        
        document.getElementById('score').textContent = score;
        
        // Verificar si completó todos
        if (matches >= 10) {
            endGame();
        }
    } else {
        // Match incorrecto
        playSound(errorSound);
        
        selectedEnglish.classList.add('wrong');
        selectedSpanish.classList.add('wrong');
        
        score = Math.max(0, score - 20);
        document.getElementById('score').textContent = score;
        
        // Perder una vida
        lives--;
        updateLivesDisplay();
        
        setTimeout(() => {
            selectedEnglish.classList.remove('wrong', 'selected');
            selectedSpanish.classList.remove('wrong', 'selected');
            
            // Si no quedan vidas, terminar el juego
            if (lives <= 0) {
                setTimeout(() => endGame(), 500);
            }
        }, 500);
    }
    
    selectedEnglish = null;
    selectedSpanish = null;
}

function startTimer() {
    timerInterval = setInterval(() => {
        timeLeft--;
        document.getElementById('timer').textContent = `⏱️ ${timeLeft}`;
        
        if (timeLeft <= 0) {
            endGame();
        }
    }, 1000);
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
        livesDisplay.classList.add('shake');
        setTimeout(() => {
            livesDisplay.classList.remove('shake');
        }, 500);
    }
}

function endGame() {
    gameActive = false;
    clearInterval(timerInterval);
    
    // Detener música de fondo
    stopBackgroundMusic();
    
    // Si terminó por falta de vidas o tiempo, mostrar sonido diferente
    if (lives <= 0 || timeLeft <= 0) {
        playSound(errorSound);
    } else if (matches >= 10) {
        playSound(victorySound);
    }
    
    const timeSpent = Math.floor((Date.now() - startTime) / 1000);
    const accuracy = attempts > 0 ? (correctAnswers / attempts) * 100 : 0;
    
    // Mostrar modal
    document.getElementById('final-score').textContent = score;
    document.getElementById('final-accuracy').textContent = accuracy.toFixed(1) + '%';
    document.getElementById('gameOverModal').style.display = 'flex';
    
    // Enviar resultados al servidor
    saveGameResult({
        game_type: 'word-match',
        score: score,
        accuracy: accuracy,
        time_spent: timeSpent,
        correct_answers: correctAnswers,
        total_questions: 10,
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

// Control de música
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
</script>

@endsection
