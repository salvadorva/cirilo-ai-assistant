@extends('layout.app')

@section('title', 'Listening Challenge 🎧')

@section('content')
<style>
    .listening-game-container {
        max-width: 900px;
        margin: 0 auto;
        padding: 20px;
    }
    
    .game-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border-radius: 15px;
        padding: 25px;
        color: white;
        margin-bottom: 30px;
        box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
    }
    
    .game-stats {
        display: flex;
        justify-content: space-around;
        flex-wrap: wrap;
        gap: 20px;
        margin-top: 20px;
    }
    
    .stat-item {
        text-align: center;
        background: rgba(255, 255, 255, 0.2);
        padding: 15px 25px;
        border-radius: 10px;
        backdrop-filter: blur(10px);
    }
    
    .stat-label {
        font-size: 0.9rem;
        opacity: 0.9;
        margin-bottom: 5px;
    }
    
    .stat-value {
        font-size: 1.8rem;
        font-weight: bold;
    }
    
    .hearts {
        color: #ff6b6b;
        font-size: 1.5rem;
    }
    
    .audio-player-card {
        background: white;
        border-radius: 15px;
        padding: 30px;
        box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        margin-bottom: 25px;
        display: none;
    }
    
    .audio-player-card.active {
        display: block;
        animation: slideIn 0.3s ease-out;
    }
    
    @keyframes slideIn {
        from {
            opacity: 0;
            transform: translateY(-20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    .audio-status {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        padding: 20px;
        border-radius: 12px;
        font-size: 1.1rem;
        text-align: center;
        margin: 20px 0;
        font-weight: 600;
        color: white;
        display: none;
        box-shadow: 0 3px 15px rgba(102, 126, 234, 0.3);
    }
    
    .audio-status.visible {
        display: block;
        animation: fadeIn 0.5s ease-in;
    }
    
    .audio-status i {
        font-size: 1.3rem;
        margin-right: 10px;
    }
    
    @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }
    
    .play-audio-btn {
        width: 100%;
        padding: 20px;
        font-size: 1.3rem;
        border-radius: 12px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border: none;
        color: white;
        font-weight: bold;
        transition: all 0.3s ease;
        box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
    }
    
    .play-audio-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(102, 126, 234, 0.6);
    }
    
    .play-audio-btn:disabled {
        background: #95a5a6;
        cursor: not-allowed;
        transform: none;
    }
    
    .question-section {
        margin-top: 30px;
    }
    
    .question-text {
        font-size: 1.4rem;
        font-weight: 600;
        color: #2c3e50;
        margin-bottom: 25px;
        padding: 20px;
        background: #f8f9fa;
        border-radius: 10px;
        border-left: 5px solid #667eea;
    }
    
    .options-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 15px;
        margin-top: 20px;
    }
    
    .option-btn {
        padding: 20px;
        border: 3px solid #e0e0e0;
        border-radius: 12px;
        background: white;
        cursor: pointer;
        transition: all 0.3s ease;
        font-size: 1.1rem;
        font-weight: 500;
        text-align: center;
        box-shadow: 0 3px 10px rgba(0,0,0,0.05);
    }
    
    .option-btn:hover {
        border-color: #667eea;
        background: #f0f3ff;
        transform: translateY(-3px);
        box-shadow: 0 5px 15px rgba(102, 126, 234, 0.2);
    }
    
    .option-btn.selected {
        border-color: #667eea;
        background: #667eea;
        color: white;
    }
    
    .option-btn.correct {
        border-color: #2ecc71;
        background: #2ecc71;
        color: white;
        animation: pulse 0.5s ease;
    }
    
    .option-btn.incorrect {
        border-color: #e74c3c;
        background: #e74c3c;
        color: white;
        animation: shake 0.5s ease;
    }
    
    @keyframes pulse {
        0%, 100% { transform: scale(1); }
        50% { transform: scale(1.05); }
    }
    
    @keyframes shake {
        0%, 100% { transform: translateX(0); }
        25% { transform: translateX(-10px); }
        75% { transform: translateX(10px); }
    }
    
    .next-question-btn {
        margin-top: 30px;
        padding: 15px 40px;
        font-size: 1.2rem;
        border-radius: 10px;
        background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
        border: none;
        color: white;
        font-weight: bold;
        display: none;
    }
    
    .next-question-btn.visible {
        display: inline-block;
        animation: slideUp 0.3s ease-out;
    }
    
    @keyframes slideUp {
        from {
            opacity: 0;
            transform: translateY(20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    .progress-bar-custom {
        height: 8px;
        background: #e0e0e0;
        border-radius: 10px;
        overflow: hidden;
        margin-top: 20px;
    }
    
    .progress-fill {
        height: 100%;
        background: linear-gradient(90deg, #11998e 0%, #38ef7d 100%);
        transition: width 0.5s ease;
        border-radius: 10px;
    }
    
    .game-over-card {
        background: white;
        border-radius: 15px;
        padding: 40px;
        text-align: center;
        box-shadow: 0 10px 40px rgba(0,0,0,0.15);
        display: none;
    }
    
    .game-over-card.visible {
        display: block;
        animation: zoomIn 0.5s ease-out;
    }
    
    @keyframes zoomIn {
        from {
            opacity: 0;
            transform: scale(0.8);
        }
        to {
            opacity: 1;
            transform: scale(1);
        }
    }
    
    .final-score {
        font-size: 4rem;
        font-weight: bold;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        margin: 20px 0;
    }
    
    .action-buttons {
        display: flex;
        gap: 15px;
        justify-content: center;
        flex-wrap: wrap;
        margin-top: 30px;
    }
    
    .btn-action {
        padding: 15px 35px;
        border-radius: 10px;
        font-size: 1.1rem;
        font-weight: 600;
        border: none;
        cursor: pointer;
        transition: all 0.3s ease;
        text-decoration: none;
        display: inline-block;
    }
    
    .btn-restart {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
    }
    
    .btn-menu {
        background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        color: white;
    }
    
    .btn-action:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 20px rgba(0,0,0,0.2);
    }
    
    .loading-spinner {
        text-align: center;
        padding: 50px;
    }
    
    .spinner {
        border: 5px solid #f3f3f3;
        border-top: 5px solid #667eea;
        border-radius: 50%;
        width: 60px;
        height: 60px;
        animation: spin 1s linear infinite;
        margin: 0 auto;
    }
    
    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
    
    .start-screen {
        background: white;
        border-radius: 15px;
        padding: 50px;
        text-align: center;
        box-shadow: 0 10px 40px rgba(0,0,0,0.15);
    }
    
    .start-icon {
        font-size: 5rem;
        margin-bottom: 20px;
    }
    
    .start-btn {
        padding: 20px 60px;
        font-size: 1.5rem;
        border-radius: 15px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border: none;
        color: white;
        font-weight: bold;
        margin-top: 30px;
        cursor: pointer;
        transition: all 0.3s ease;
        box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
    }
    
    .start-btn:hover {
        transform: translateY(-3px);
        box-shadow: 0 12px 35px rgba(102, 126, 234, 0.6);
    }
    
    .feedback-message {
        padding: 15px;
        border-radius: 10px;
        margin-top: 15px;
        font-size: 1.1rem;
        font-weight: 600;
        display: none;
    }
    
    .feedback-message.visible {
        display: block;
        animation: fadeIn 0.3s ease;
    }
    
    .feedback-message.correct {
        background: #d4edda;
        color: #155724;
        border: 2px solid #c3e6cb;
    }
    
    .feedback-message.incorrect {
        background: #f8d7da;
        color: #721c24;
        border: 2px solid #f5c6cb;
    }
</style>

<div class="listening-game-container">
    <!-- Game Header -->
    <div class="game-header">
        <h1 class="text-center mb-0">
            <i class="fas fa-headphones me-2"></i>
            Listening Challenge
        </h1>
        <div class="game-stats">
            <div class="stat-item">
                <div class="stat-label">Nivel</div>
                <div class="stat-value" id="levelDisplay">{{ $level }}</div>
            </div>
            <div class="stat-item">
                <div class="stat-label">Puntuación</div>
                <div class="stat-value" id="scoreDisplay">0</div>
            </div>
            <div class="stat-item">
                <div class="stat-label">Vidas</div>
                <div class="stat-value hearts" id="livesDisplay">❤️❤️❤️</div>
            </div>
            <div class="stat-item">
                <div class="stat-label">Precisión</div>
                <div class="stat-value" id="accuracyDisplay">100%</div>
            </div>
        </div>
        <div class="progress-bar-custom">
            <div class="progress-fill" id="progressBar" style="width: 0%"></div>
        </div>
    </div>

    <!-- Start Screen -->
    <div class="start-screen" id="startScreen">
        <div class="start-icon">🎧</div>
        <h2>¡Bienvenido al Listening Challenge!</h2>
        <p class="lead mt-3">Pon a prueba tu comprensión auditiva. Escucha con atención y responde sin ver el texto.</p>
        <div class="mt-4">
            <p><strong>📝 Instrucciones:</strong></p>
            <ul class="list-unstyled">
                <li>🎧 <strong>Escucha el audio</strong> - NO se mostrará el texto escrito</li>
                <li>❤️ Tienes 3 vidas</li>
                <li>🔄 Puedes reproducir el audio hasta 3 veces</li>
                <li>🔊 Audio generado con OpenAI (voz Echo profesional)</li>
                <li>⏱️ No hay límite de tiempo - concéntrate en escuchar</li>
                <li>🎯 100 puntos por respuesta correcta</li>
                <li>💪 Completa todos los ejercicios para ganar XP</li>
            </ul>
            <div class="alert alert-warning mt-3">
                <i class="fas fa-info-circle me-2"></i>
                <strong>Tip:</strong> Escucha con atención. El texto NO se mostrará, ¡solo tu oído cuenta!
            </div>
        </div>
        <button class="start-btn" onclick="startGame()">
            <i class="fas fa-play me-2"></i>
            ¡Comenzar Juego!
        </button>
    </div>

    <!-- Loading -->
    <div class="loading-spinner" id="loadingSpinner" style="display: none;">
        <div class="spinner"></div>
        <p class="mt-3">Cargando ejercicios...</p>
    </div>

    <!-- Game Area -->
    <div class="audio-player-card" id="gameArea">
        <!-- Audio Controls -->
        <div class="text-center mb-4">
            <button class="play-audio-btn" id="playAudioBtn" onclick="playAudio()">
                <i class="fas fa-volume-up me-2"></i>
                Reproducir Audio <span id="playsLeft">(3 reproducciones disponibles)</span>
            </button>
        </div>

        <!-- Audio Status (indica que se reprodujo) -->
        <div class="audio-status" id="audioStatus">
            <i class="fas fa-check-circle"></i>
            Audio reproducido - Ahora responde la pregunta basándote en lo que escuchaste
        </div>

        <!-- Question Section -->
        <div class="question-section" id="questionSection" style="display: none;">
            <div class="question-text" id="questionText"></div>
            
            <div class="options-grid" id="optionsGrid"></div>
            
            <div class="feedback-message" id="feedbackMessage"></div>
            
            <div class="text-center">
                <button class="next-question-btn" id="nextQuestionBtn" onclick="nextQuestion()">
                    Siguiente Pregunta <i class="fas fa-arrow-right ms-2"></i>
                </button>
            </div>
        </div>
    </div>

    <!-- Game Over Screen -->
    <div class="game-over-card" id="gameOverScreen">
        <div id="gameOverIcon" style="font-size: 5rem;">🏆</div>
        <h2 id="gameOverTitle">¡Juego Terminado!</h2>
        <div class="final-score" id="finalScore">0</div>
        <p class="lead" id="gameOverMessage"></p>
        
        <div class="mt-4">
            <div class="row text-center">
                <div class="col-md-4">
                    <h4>Correctas</h4>
                    <p class="h2 text-success" id="correctAnswers">0</p>
                </div>
                <div class="col-md-4">
                    <h4>Incorrectas</h4>
                    <p class="h2 text-danger" id="wrongAnswers">0</p>
                </div>
                <div class="col-md-4">
                    <h4>Precisión</h4>
                    <p class="h2 text-info" id="finalAccuracy">0%</p>
                </div>
            </div>
        </div>
        
        <div class="action-buttons">
            <button class="btn-action btn-restart" onclick="restartGame()">
                <i class="fas fa-redo me-2"></i>
                Jugar de Nuevo
            </button>
            <a href="{{ route('games.index') }}" class="btn-action btn-menu">
                <i class="fas fa-home me-2"></i>
                Menú de Juegos
            </a>
        </div>
    </div>
</div>

<script>
    let exercises = [];
    let currentExerciseIndex = 0;
    let score = 0;
    let lives = 3;
    let correctAnswers = 0;
    let wrongAnswers = 0;
    let playsRemaining = 3;
    let gameStartTime = null;
    let currentAudio = null;
    let audioCache = {}; // Cache para evitar regenerar audios

    function startGame() {
        document.getElementById('startScreen').style.display = 'none';
        document.getElementById('loadingSpinner').style.display = 'block';
        
        gameStartTime = Date.now();
        
        // Cargar ejercicios
        fetch('{{ route("games.listening-challenge.exercises") }}')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    exercises = data.exercises;
                    document.getElementById('loadingSpinner').style.display = 'none';
                    document.getElementById('gameArea').classList.add('active');
                    loadExercise();
                }
            })
            .catch(error => {
                console.error('Error loading exercises:', error);
                alert('Error al cargar ejercicios. Por favor, recarga la página.');
            });
    }

    function playFromCache(audioUrl) {
        const exercise = exercises[currentExerciseIndex];
        const btn = document.getElementById('playAudioBtn');
        
        // Crear y reproducir el audio
        currentAudio = new Audio(audioUrl);
        
        currentAudio.onended = function() {
            playsRemaining--;
            btn.disabled = playsRemaining <= 0;
            btn.innerHTML = playsRemaining > 0 
                ? `<i class="fas fa-volume-up me-2"></i>Reproducir de Nuevo (${playsRemaining} restantes)`
                : '<i class="fas fa-volume-mute me-2"></i>Sin reproducciones';
            
            // NO mostrar el texto del audio - el usuario debe escuchar
            // Solo mostrar la pregunta
            showQuestion();
        };
        
        currentAudio.onerror = function() {
            console.error('Error al reproducir el audio');
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-volume-up me-2"></i>Reproducir Audio <span id="playsLeft">(Error - Intenta de nuevo)</span>';
        };
        
        currentAudio.play();
    }

    function loadExercise() {
        if (currentExerciseIndex >= exercises.length || lives <= 0) {
            endGame();
            return;
        }

        const exercise = exercises[currentExerciseIndex];
        playsRemaining = 3;
        
        // Reset UI
        document.getElementById('audioStatus').classList.remove('visible');
        document.getElementById('questionSection').style.display = 'none';
        const btn = document.getElementById('playAudioBtn');
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-volume-up me-2"></i>Reproducir Audio <span id="playsLeft">(3 reproducciones disponibles)</span>';
        document.getElementById('feedbackMessage').classList.remove('visible');
        document.getElementById('nextQuestionBtn').classList.remove('visible');
        
        // Update progress - usar currentExerciseIndex para mostrar progreso actual
        const progress = (currentExerciseIndex / exercises.length) * 100;
        document.getElementById('progressBar').style.width = progress + '%';
    }

    function playAudio() {
        if (playsRemaining <= 0) return;
        
        const exercise = exercises[currentExerciseIndex];
        const btn = document.getElementById('playAudioBtn');
        
        btn.disabled = true;
        
        // Cancelar audio previo si existe
        if (currentAudio) {
            currentAudio.pause();
            currentAudio = null;
        }
        
        // Verificar si el audio ya está en cache
        if (audioCache[currentExerciseIndex]) {
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Reproduciendo...';
            playFromCache(audioCache[currentExerciseIndex]);
            return;
        }
        
        // Generar audio con OpenAI
        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Generando audio con OpenAI...';
        fetch('/text-to-speech', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({
                text: exercise.text
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.audioUrl) {
                btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Reproduciendo...';
                
                // Guardar en cache
                audioCache[currentExerciseIndex] = data.audioUrl;
                
                // Reproducir audio
                playFromCache(data.audioUrl);
            } else if (data.error) {
                console.error('Error al generar audio:', data.error);
                alert('Error al generar el audio. Por favor, intenta de nuevo.');
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-volume-up me-2"></i>Reproducir Audio <span id="playsLeft">(' + playsRemaining + ' reproducciones disponibles)</span>';
            }
        })
        .catch(error => {
            console.error('Error en la solicitud de audio:', error);
            alert('Error de conexión. Por favor, intenta de nuevo.');
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-volume-up me-2"></i>Reproducir Audio <span id="playsLeft">(' + playsRemaining + ' reproducciones disponibles)</span>';
        });
    }

    function showQuestion() {
        const exercise = exercises[currentExerciseIndex];
        
        // Mostrar indicador de que el audio fue reproducido
        document.getElementById('audioStatus').classList.add('visible');
        
        document.getElementById('questionSection').style.display = 'block';
        document.getElementById('questionText').textContent = exercise.question;
        
        // Crear opciones
        const optionsGrid = document.getElementById('optionsGrid');
        optionsGrid.innerHTML = '';
        
        exercise.options.forEach((option, index) => {
            const btn = document.createElement('button');
            btn.className = 'option-btn';
            btn.textContent = option;
            btn.onclick = () => selectOption(option, btn);
            optionsGrid.appendChild(btn);
        });
    }

    function selectOption(selectedAnswer, btn) {
        const exercise = exercises[currentExerciseIndex];
        const allOptions = document.querySelectorAll('.option-btn');
        
        // Deshabilitar todas las opciones
        allOptions.forEach(opt => {
            opt.style.pointerEvents = 'none';
        });
        
        const isCorrect = selectedAnswer === exercise.correct;
        
        if (isCorrect) {
            btn.classList.add('correct');
            score += 100;
            correctAnswers++;
            showFeedback('¡Correcto! 🎉', 'correct');
        } else {
            btn.classList.add('incorrect');
            lives--;
            wrongAnswers++;
            showFeedback(`Incorrecto. La respuesta correcta es: ${exercise.correct}`, 'incorrect');
            
            // Mostrar respuesta correcta
            allOptions.forEach(opt => {
                if (opt.textContent === exercise.correct) {
                    opt.classList.add('correct');
                }
            });
        }
        
        updateStats();
        document.getElementById('nextQuestionBtn').classList.add('visible');
        
        if (lives <= 0) {
            setTimeout(endGame, 2000);
        }
    }

    function showFeedback(message, type) {
        const feedback = document.getElementById('feedbackMessage');
        feedback.textContent = message;
        feedback.className = `feedback-message visible ${type}`;
    }

    function nextQuestion() {
        currentExerciseIndex++;
        loadExercise();
    }

    function updateStats() {
        document.getElementById('scoreDisplay').textContent = score;
        document.getElementById('livesDisplay').textContent = '❤️'.repeat(lives) + '💔'.repeat(3 - lives);
        
        const totalAnswered = correctAnswers + wrongAnswers;
        const accuracy = totalAnswered > 0 ? Math.round((correctAnswers / totalAnswered) * 100) : 100;
        document.getElementById('accuracyDisplay').textContent = accuracy + '%';
        
        // Actualizar barra de progreso
        const progress = ((currentExerciseIndex + 1) / exercises.length) * 100;
        document.getElementById('progressBar').style.width = progress + '%';
    }

    function endGame() {
        // Cancelar cualquier audio
        if (currentAudio) {
            currentAudio.pause();
            currentAudio = null;
        }
        
        document.getElementById('gameArea').style.display = 'none';
        document.getElementById('gameOverScreen').classList.add('visible');
        
        const totalAnswered = correctAnswers + wrongAnswers;
        const accuracy = totalAnswered > 0 ? Math.round((correctAnswers / totalAnswered) * 100) : 0;
        
        document.getElementById('finalScore').textContent = score;
        document.getElementById('correctAnswers').textContent = correctAnswers;
        document.getElementById('wrongAnswers').textContent = wrongAnswers;
        document.getElementById('finalAccuracy').textContent = accuracy + '%';
        
        if (accuracy >= 90) {
            document.getElementById('gameOverIcon').textContent = '🏆';
            document.getElementById('gameOverTitle').textContent = '¡Excelente!';
            document.getElementById('gameOverMessage').textContent = '¡Tu comprensión auditiva es excepcional!';
        } else if (accuracy >= 70) {
            document.getElementById('gameOverIcon').textContent = '🌟';
            document.getElementById('gameOverTitle').textContent = '¡Muy Bien!';
            document.getElementById('gameOverMessage').textContent = '¡Buen trabajo! Sigue practicando.';
        } else {
            document.getElementById('gameOverIcon').textContent = '💪';
            document.getElementById('gameOverTitle').textContent = '¡Sigue Intentando!';
            document.getElementById('gameOverMessage').textContent = 'La práctica hace al maestro.';
        }
        
        // Guardar resultado
        saveGameResult(accuracy);
    }

    function saveGameResult(accuracy) {
        const timeSpent = Math.floor((Date.now() - gameStartTime) / 1000);
        
        fetch('{{ route("games.save-result") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({
                game_type: 'listening_challenge',
                score: score,
                accuracy: accuracy,
                time_spent: timeSpent,
                level: {{ $level }},
                details: {
                    correct_answers: correctAnswers,
                    wrong_answers: wrongAnswers,
                    total_questions: currentExerciseIndex
                }
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success && data.xp_earned) {
                // Actualizar XP en el navbar
                if (typeof updateNavbarXP === 'function') {
                    updateNavbarXP(data.user_progress);
                }
            }
        })
        .catch(error => console.error('Error saving result:', error));
    }

    function restartGame() {
        // Reset variables
        currentExerciseIndex = 0;
        score = 0;
        lives = 3;
        correctAnswers = 0;
        wrongAnswers = 0;
        playsRemaining = 3;
        
        // Reset UI
        document.getElementById('gameOverScreen').classList.remove('visible');
        document.getElementById('startScreen').style.display = 'block';
        
        updateStats();
    }
</script>
@endsection
