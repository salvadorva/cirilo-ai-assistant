// 🔥 TYPEMASTER AI - MODO SUPERVIVENCIA 🔥
// El modo de juego más épico y divertido

class SurvivalGame {
    constructor() {
        this.gameActive = false;
        this.difficulty = 'easy';
        this.currentWave = 1;
        this.lives = 3;
        this.score = 0;
        this.wpm = 0;
        this.accuracy = 100;
        this.timeLeft = 60;
        this.currentText = '';
        this.currentPosition = 0;
        this.startTime = null;
        this.correctChars = 0;
        this.totalChars = 0;
        this.gameTimer = null;
        this.waveTimer = null;
        this.currentEnemy = null;
        this.enemyHealth = 100;
        this.soundEnabled = true;
        
        // Configuraciones por dificultad
        this.difficultyConfig = {
            easy: {
                targetWPM: 30,
                waves: 5,
                bosses: 1,
                timePerWave: 60,
                texts: 'easy'
            },
            normal: {
                targetWPM: 45,
                waves: 8,
                bosses: 2,
                timePerWave: 45,
                texts: 'normal'
            },
            hard: {
                targetWPM: 60,
                waves: 12,
                bosses: 3,
                timePerWave: 30,
                texts: 'hard'
            }
        };
        
        // Enemigos y jefes
        this.enemies = {
            easy: [
                { name: '🐢 Tortuga Lenta', health: 50, image: 'turtle.png', desc: 'Un enemigo básico perfecto para practicar' },
                { name: '🐰 Conejo Veloz', health: 75, image: 'rabbit.png', desc: 'Más rápido pero aún manejable' },
                { name: '🦆 Pato Palabrero', health: 60, image: 'duck.png', desc: 'Le gustan las palabras simples' },
                { name: '🐸 Rana Saltarina', health: 70, image: 'frog.png', desc: 'Salta entre las letras' }
            ],
            normal: [
                { name: '🦅 Águila Feroz', health: 100, image: 'eagle.png', desc: 'Vuela alto y rápido' },
                { name: '🐺 Lobo Astuto', health: 120, image: 'wolf.png', desc: 'Caza con precisión letal' },
                { name: '🦊 Zorro Inteligente', health: 110, image: 'fox.png', desc: 'Muy astuto con las palabras' },
                { name: '🐯 Tigre Salvaje', health: 130, image: 'tiger.png', desc: 'Feroz y poderoso' }
            ],
            hard: [
                { name: '🐉 Dragón de Texto', health: 200, image: 'dragon.png', desc: 'El terror de los teclados' },
                { name: '⚡ Rayo Cibernético', health: 180, image: 'cyber.png', desc: 'Velocidad sobrehumana' },
                { name: '👹 Demonio Código', health: 220, image: 'demon.png', desc: 'Maestro de la sintaxis' },
                { name: '🔥 Fénix Digital', health: 250, image: 'phoenix.png', desc: 'Renace más fuerte' }
            ],
            bosses: [
                { name: '👑 Rey de las Palabras', health: 300, image: 'king.png', desc: 'El soberano del vocabulario' },
                { name: '🧙‍♂️ Mago de Códigos', health: 350, image: 'wizard.png', desc: 'Maestro de lenguajes de programación' },
                { name: '🤖 IA Suprema', health: 400, image: 'ai-boss.png', desc: 'La inteligencia artificial definitiva' }
            ]
        };
        
        // Textos por dificultad
        this.gameTexts = {
            easy: [
                'el gato come pescado en casa',
                'mi familia vive cerca del parque',
                'el sol sale todas las mañanas',
                'me gusta leer libros nuevos',
                'la música suena muy bien hoy',
                'vamos a caminar por la playa',
                'el agua del río está fría',
                'mi hermano juega en el jardín',
                'las flores crecen en primavera',
                'el coche azul es muy rápido'
            ],
            normal: [
                'la tecnología avanza rápidamente en nuestra sociedad moderna',
                'el desarrollo de software requiere conocimientos técnicos avanzados',
                'las redes sociales han transformado la comunicación global',
                'la inteligencia artificial está revolucionando múltiples industrias',
                'el cambio climático presenta desafíos complejos para la humanidad',
                'la educación digital ofrece nuevas oportunidades de aprendizaje',
                'los dispositivos móviles conectan personas alrededor del mundo',
                'la programación es una habilidad fundamental en el siglo XXI',
                'la ciberseguridad protege información valiosa de amenazas digitales',
                'el comercio electrónico ha revolucionado la forma de comprar'
            ],
            hard: [
                'const algorithm = (array) => array.filter(item => item.id > 0).map(element => element.transform());',
                'function calculateComplexMatrix(dimensions, iterations) { return matrix.reduce((acc, curr) => acc + curr.value, 0); }',
                'SELECT users.name, COUNT(orders.id) FROM users LEFT JOIN orders ON users.id = orders.user_id GROUP BY users.id;',
                'import { ReactComponent, useState, useEffect } from "react"; const [state, setState] = useState(initialValue);',
                'class DatabaseConnection extends AbstractConnection implements ConnectionInterface { public function execute($query) {} }',
                'docker run -d --name container -p 8080:80 -v /host/path:/container/path nginx:alpine',
                'git checkout -b feature/new-functionality && git add . && git commit -m "feat: implement advanced algorithm"',
                'kubectl apply -f deployment.yaml && kubectl get pods --selector=app=myapp --output=jsonpath={.items[*].metadata.name}',
                'terraform plan -var="instance_type=t3.micro" -var="region=us-west-2" -out=deployment.tfplan',
                'ansible-playbook -i inventory.yml playbook.yml --extra-vars="environment=production database_host=db.example.com"'
            ],
            bosses: [
                'Implementar algoritmos de aprendizaje automático utilizando redes neuronales profundas para procesamiento de lenguaje natural y visión por computadora avanzada',
                'Desarrollar arquitecturas de microservicios distribuidos con patrones de tolerancia a fallos, balanceadores de carga y sistemas de monitoreo en tiempo real',
                'Diseñar bases de datos NoSQL escalables con replicación maestro-esclavo, sharding horizontal y consistencia eventual para aplicaciones de alto rendimiento'
            ]
        };
        
        this.init();
    }
    
    init() {
        this.bindEvents();
        this.preloadAudio();
        this.setupAudioContext();
    }
    
    preloadAudio() {
        this.sounds = {
            background: document.getElementById('backgroundMusic'),
            typing: document.getElementById('typingSound'),
            error: document.getElementById('errorSound'),
            success: document.getElementById('successSound'),
            levelUp: document.getElementById('levelUpSound'),
            boss: document.getElementById('bossSound'),
            victory: document.getElementById('victorySound'),
            gameOver: document.getElementById('gameOverSound')
        };
        
        // Configurar volúmenes
        this.sounds.background.volume = 0.3;
        this.sounds.typing.volume = 0.5;
        this.sounds.error.volume = 0.7;
        this.sounds.success.volume = 0.6;
        this.sounds.levelUp.volume = 0.8;
        this.sounds.boss.volume = 0.9;
        this.sounds.victory.volume = 0.8;
        this.sounds.gameOver.volume = 0.7;
    }
    
    setupAudioContext() {
        // Intentar reproducir audio al primer clic del usuario
        document.addEventListener('click', () => {
            if (this.sounds.background.paused && this.soundEnabled) {
                this.sounds.background.play().catch(e => console.log('Audio no disponible'));
            }
        }, { once: true });
    }
    
    bindEvents() {
        // Iniciar juego con dificultad seleccionada
        document.querySelectorAll('.start-survival').forEach(btn => {
            btn.addEventListener('click', (e) => {
                this.difficulty = e.target.getAttribute('data-difficulty');
                this.startGame();
            });
        });
        
        // Entrada de texto
        document.getElementById('survivalInput').addEventListener('input', (e) => {
            if (this.gameActive) {
                this.handleTyping(e.target.value);
            }
        });
        
        // Botones de control
        document.getElementById('startBattle').addEventListener('click', () => {
            this.startWave();
        });
        
        document.getElementById('pauseGame').addEventListener('click', () => {
            this.togglePause();
        });
        
        document.getElementById('playAgain').addEventListener('click', () => {
            this.restartGame();
        });
        
        // Resetear al cerrar modal
        document.getElementById('survivalModal').addEventListener('hidden.bs.modal', () => {
            this.endGame();
        });
        
        document.getElementById('gameOverModal').addEventListener('hidden.bs.modal', () => {
            document.getElementById('survivalModal').classList.remove('show');
        });
    }
    
    startGame() {
        // Resetear estado
        const config = this.difficultyConfig[this.difficulty];
        this.currentWave = 1;
        this.lives = 3;
        this.score = 0;
        this.wpm = 0;
        this.accuracy = 100;
        this.timeLeft = config.timePerWave;
        this.gameActive = true;
        
        // Mostrar modal
        const modal = new bootstrap.Modal(document.getElementById('survivalModal'));
        modal.show();
        
        // Configurar UI inicial
        this.updateUI();
        this.showGameStatus('¡PREPÁRATE PARA LA BATALLA!', 'La primera oleada está por comenzar...');
        
        // Mostrar botón de inicio
        document.getElementById('startBattle').style.display = 'block';
        document.getElementById('typingBattlefield').style.display = 'none';
        document.getElementById('enemyDisplay').style.display = 'none';
        
        // Reproducir música de fondo
        if (this.soundEnabled) {
            this.sounds.background.play().catch(e => console.log('Audio no disponible'));
        }
        
        this.createParticleEffect('🔥', '#e74c3c');
    }
    
    startWave() {
        const config = this.difficultyConfig[this.difficulty];
        
        // Ocultar botón de inicio
        document.getElementById('startBattle').style.display = 'none';
        
        // Determinar si es jefe
        const isBoss = (this.currentWave % Math.ceil(config.waves / config.bosses)) === 0;
        
        // Seleccionar enemigo
        this.selectEnemy(isBoss);
        
        // Mostrar enemigo
        this.showEnemy();
        
        // Preparar texto de desafío
        this.prepareChallenge(isBoss);
        
        // Mostrar campo de batalla
        document.getElementById('typingBattlefield').style.display = 'block';
        
        // Iniciar cronómetro
        this.startWaveTimer();
        
        // Enfocar input
        setTimeout(() => {
            document.getElementById('survivalInput').focus();
        }, 500);
        
        // Efectos visuales
        this.createParticleEffect('⚔️', '#ffc107');
        
        if (isBoss && this.soundEnabled) {
            this.sounds.boss.play().catch(e => console.log('Audio no disponible'));
        }
    }
    
    selectEnemy(isBoss) {
        const config = this.difficultyConfig[this.difficulty];
        let enemyPool;
        
        if (isBoss) {
            enemyPool = this.enemies.bosses;
        } else {
            enemyPool = this.enemies[this.difficulty];
        }
        
        // Seleccionar enemigo aleatorio
        this.currentEnemy = enemyPool[Math.floor(Math.random() * enemyPool.length)];
        this.enemyHealth = this.currentEnemy.health;
        
        // Aumentar salud según la oleada
        this.enemyHealth = Math.floor(this.enemyHealth * (1 + (this.currentWave - 1) * 0.2));
        this.currentEnemy.maxHealth = this.enemyHealth;
    }
    
    showEnemy() {
        document.getElementById('enemyDisplay').style.display = 'block';
        document.getElementById('enemyName').textContent = this.currentEnemy.name;
        document.getElementById('enemyDesc').textContent = this.currentEnemy.desc;
        
        // Usar placeholder image si no existe la imagen
        const enemyImg = document.getElementById('enemyImg');
        enemyImg.src = `/images/enemies/${this.currentEnemy.image}`;
        enemyImg.onerror = () => {
            enemyImg.src = 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTIwIiBoZWlnaHQ9IjEyMCIgdmlld0JveD0iMCAwIDEyMCAxMjAiIGZpbGw9Im5vbmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+CjxyZWN0IHdpZHRoPSIxMjAiIGhlaWdodD0iMTIwIiBmaWxsPSIjMzQ0OTVlIi8+Cjx0ZXh0IHg9IjYwIiB5PSI2MCIgZm9udC1mYW1pbHk9IkFyaWFsIiBmb250LXNpemU9IjI0IiBmaWxsPSIjZWNmMGYxIiB0ZXh0LWFuY2hvcj0ibWlkZGxlIiBkeT0iLjNlbSI+👹</text+Cjwvc3ZnPg==';
        };
        
        this.updateEnemyHealth();
    }
    
    prepareChallenge(isBoss) {
        let texts;
        
        if (isBoss) {
            texts = this.gameTexts.bosses;
        } else {
            texts = this.gameTexts[this.difficultyConfig[this.difficulty].texts];
        }
        
        // Seleccionar texto aleatorio
        this.currentText = texts[Math.floor(Math.random() * texts.length)];
        
        // Mostrar texto
        document.getElementById('challengeText').textContent = this.currentText;
        
        // Resetear entrada
        document.getElementById('survivalInput').value = '';
        this.currentPosition = 0;
        this.startTime = null;
        this.correctChars = 0;
        this.totalChars = 0;
        
        this.updateTextDisplay();
    }
    
    startWaveTimer() {
        const config = this.difficultyConfig[this.difficulty];
        this.timeLeft = config.timePerWave;
        
        if (this.waveTimer) clearInterval(this.waveTimer);
        
        this.waveTimer = setInterval(() => {
            this.timeLeft--;
            this.updateUI();
            
            if (this.timeLeft <= 0) {
                this.loseLife('¡Tiempo agotado!');
            }
        }, 1000);
    }
    
    handleTyping(input) {
        if (!this.startTime) {
            this.startTime = Date.now();
        }
        
        // Reproducir sonido de tecla
        if (this.soundEnabled) {
            this.sounds.typing.currentTime = 0;
            this.sounds.typing.play().catch(e => {});
        }
        
        this.totalChars = input.length;
        this.correctChars = 0;
        let errors = 0;
        
        // Verificar caracteres
        for (let i = 0; i < input.length && i < this.currentText.length; i++) {
            if (input[i] === this.currentText[i]) {
                this.correctChars++;
            } else {
                errors++;
            }
        }
        
        this.currentPosition = input.length;
        
        // Calcular estadísticas
        this.calculateStats();
        
        // Actualizar displays
        this.updateUI();
        this.updateTextDisplay();
        
        // Verificar si se completó el texto
        if (input === this.currentText) {
            this.completeChallenge();
        }
        
        // Verificar precisión crítica
        if (this.accuracy < 80 && this.totalChars > 10) {
            this.loseLife('¡Precisión muy baja!');
            return;
        }
        
        // Actualizar salud del enemigo
        const progress = Math.min(100, (this.currentPosition / this.currentText.length) * 100);
        this.enemyHealth = this.currentEnemy.maxHealth * (1 - progress / 100);
        this.updateEnemyHealth();
    }
    
    calculateStats() {
        if (!this.startTime) return;
        
        const elapsed = (Date.now() - this.startTime) / 1000 / 60; // minutos
        this.wpm = elapsed > 0 ? Math.round((this.correctChars / 5) / elapsed) : 0;
        this.accuracy = this.totalChars > 0 ? Math.round((this.correctChars / this.totalChars) * 100) : 100;
    }
    
    updateUI() {
        document.getElementById('livesCount').textContent = this.lives;
        document.getElementById('currentWave').textContent = this.currentWave;
        document.getElementById('currentScore').textContent = this.score.toLocaleString();
        document.getElementById('currentWPM').textContent = this.wpm;
        document.getElementById('currentAccuracy').textContent = this.accuracy + '%';
        document.getElementById('timeLeft').textContent = this.timeLeft;
        
        // Cambiar color de precisión
        const accuracyElement = document.getElementById('currentAccuracy');
        if (this.accuracy >= 95) {
            accuracyElement.style.color = '#27ae60';
        } else if (this.accuracy >= 85) {
            accuracyElement.style.color = '#f39c12';
        } else {
            accuracyElement.style.color = '#e74c3c';
        }
        
        // Efecto de advertencia en tiempo
        const timeElement = document.getElementById('timeLeft');
        if (this.timeLeft <= 10) {
            timeElement.style.color = '#e74c3c';
            timeElement.parentElement.classList.add('pulse-effect');
        } else {
            timeElement.style.color = '#fff';
            timeElement.parentElement.classList.remove('pulse-effect');
        }
    }
    
    updateTextDisplay() {
        const textElement = document.getElementById('challengeText');
        const input = document.getElementById('survivalInput').value;
        
        let html = '';
        
        for (let i = 0; i < this.currentText.length; i++) {
            if (i < input.length) {
                if (input[i] === this.currentText[i]) {
                    html += `<span class="char-correct">${this.currentText[i]}</span>`;
                } else {
                    html += `<span class="char-incorrect">${this.currentText[i]}</span>`;
                }
            } else if (i === input.length) {
                html += `<span class="char-current">${this.currentText[i]}</span>`;
            } else {
                html += `<span class="char-pending">${this.currentText[i]}</span>`;
            }
        }
        
        textElement.innerHTML = html;
    }
    
    updateEnemyHealth() {
        const percentage = Math.max(0, (this.enemyHealth / this.currentEnemy.maxHealth) * 100);
        document.getElementById('enemyHealth').style.width = percentage + '%';
        
        // Cambiar color según salud
        const healthBar = document.getElementById('enemyHealth');
        if (percentage > 60) {
            healthBar.style.background = 'linear-gradient(90deg, #e74c3c, #c0392b)';
        } else if (percentage > 30) {
            healthBar.style.background = 'linear-gradient(90deg, #f39c12, #e67e22)';
        } else {
            healthBar.style.background = 'linear-gradient(90deg, #27ae60, #229954)';
        }
    }
    
    completeChallenge() {
        // Parar timer
        if (this.waveTimer) clearInterval(this.waveTimer);
        
        // Calcular puntuación
        const timeBonus = Math.max(0, this.timeLeft * 10);
        const accuracyBonus = this.accuracy >= 95 ? 500 : this.accuracy >= 90 ? 200 : 0;
        const speedBonus = this.wpm > this.difficultyConfig[this.difficulty].targetWPM ? (this.wpm - this.difficultyConfig[this.difficulty].targetWPM) * 20 : 0;
        const waveScore = 1000 + timeBonus + accuracyBonus + speedBonus;
        
        this.score += waveScore;
        
        // Efectos de victoria
        this.createParticleEffect('🎉', '#27ae60');
        if (this.soundEnabled) {
            this.sounds.success.play().catch(e => {});
        }
        
        // Verificar si completó todas las oleadas
        const config = this.difficultyConfig[this.difficulty];
        if (this.currentWave >= config.waves) {
            this.victory();
            return;
        }
        
        // Avanzar a siguiente oleada
        this.currentWave++;
        
        // Mostrar mensaje de oleada completada
        this.showGameStatus(
            `¡OLEADA ${this.currentWave - 1} COMPLETADA!`,
            `Puntos ganados: ${waveScore.toLocaleString()}. Preparándose para oleada ${this.currentWave}...`
        );
        
        // Ocultar elementos de batalla
        document.getElementById('typingBattlefield').style.display = 'none';
        document.getElementById('enemyDisplay').style.display = 'none';
        document.getElementById('startBattle').style.display = 'block';
        
        // Efecto de subida de nivel
        if (this.soundEnabled) {
            this.sounds.levelUp.play().catch(e => {});
        }
        
        this.updateUI();
    }
    
    loseLife(reason) {
        this.lives--;
        
        // Efectos de error
        this.createParticleEffect('💥', '#e74c3c');
        this.createScreenShake();
        
        if (this.soundEnabled) {
            this.sounds.error.play().catch(e => {});
        }
        
        if (this.lives <= 0) {
            this.gameOver();
            return;
        }
        
        // Mostrar mensaje de vida perdida
        this.showGameStatus(
            `💔 VIDA PERDIDA`,
            `${reason} Te quedan ${this.lives} vidas. ¡Continúa luchando!`
        );
        
        // Resetear oleada actual
        document.getElementById('typingBattlefield').style.display = 'none';
        document.getElementById('enemyDisplay').style.display = 'none';
        document.getElementById('startBattle').style.display = 'block';
        
        // Parar timer
        if (this.waveTimer) clearInterval(this.waveTimer);
        
        this.updateUI();
    }
    
    victory() {
        this.gameActive = false;
        
        // Parar todos los timers
        if (this.waveTimer) clearInterval(this.waveTimer);
        
        // Calcular XP final
        const xpGained = this.calculateFinalXP();
        
        // Guardar puntuación
        this.saveScore(xpGained);
        
        // Mostrar modal de victoria
        this.showGameOver(true, xpGained);
        
        // Efectos épicos de victoria
        this.createVictoryEffects();
        
        if (this.soundEnabled) {
            this.sounds.background.pause();
            this.sounds.victory.play().catch(e => {});
        }
    }
    
    gameOver() {
        this.gameActive = false;
        
        // Parar todos los timers
        if (this.waveTimer) clearInterval(this.waveTimer);
        
        // Calcular XP final
        const xpGained = this.calculateFinalXP();
        
        // Guardar puntuación
        this.saveScore(xpGained);
        
        // Mostrar modal de game over
        this.showGameOver(false, xpGained);
        
        if (this.soundEnabled) {
            this.sounds.background.pause();
            this.sounds.gameOver.play().catch(e => {});
        }
    }
    
    calculateFinalXP() {
        const baseXP = 50;
        const scoreMultiplier = Math.floor(this.score / 1000);
        const waveBonus = (this.currentWave - 1) * 20;
        const survivalMultiplier = 1.5; // Modo supervivencia da 1.5x XP
        
        return Math.floor((baseXP + scoreMultiplier + waveBonus) * survivalMultiplier);
    }
    
    showGameOver(victory, xpGained) {
        const modal = new bootstrap.Modal(document.getElementById('gameOverModal'));
        const icon = document.getElementById('gameOverIcon');
        const title = document.getElementById('gameOverTitle');
        const content = document.getElementById('gameOverContent');
        
        if (victory) {
            icon.className = 'fas fa-crown me-2';
            title.textContent = '🏆 ¡VICTORIA ÉPICA!';
            content.innerHTML = `
                <div class="victory-content">
                    <h2 class="text-warning mb-4">¡Has conquistado el Modo Supervivencia!</h2>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="stat-final">
                                <i class="fas fa-trophy"></i>
                                <span>Puntuación Final</span>
                                <strong>${this.score.toLocaleString()}</strong>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="stat-final">
                                <i class="fas fa-wave-square"></i>
                                <span>Oleadas Completadas</span>
                                <strong>${this.currentWave}</strong>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="stat-final">
                                <i class="fas fa-tachometer-alt"></i>
                                <span>WPM Promedio</span>
                                <strong>${this.wpm}</strong>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="stat-final">
                                <i class="fas fa-star"></i>
                                <span>XP Ganado</span>
                                <strong>${xpGained}</strong>
                            </div>
                        </div>
                    </div>
                    <div class="achievement-unlock mt-4">
                        <h5>🏅 ¡Logros Desbloqueados!</h5>
                        <p>Sobreviviente Épico - Completar Modo Supervivencia</p>
                    </div>
                </div>
            `;
        } else {
            icon.className = 'fas fa-skull me-2';
            title.textContent = '💀 GAME OVER';
            content.innerHTML = `
                <div class="gameover-content">
                    <h2 class="text-danger mb-4">¡La batalla ha terminado!</h2>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="stat-final">
                                <i class="fas fa-trophy"></i>
                                <span>Puntuación</span>
                                <strong>${this.score.toLocaleString()}</strong>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="stat-final">
                                <i class="fas fa-wave-square"></i>
                                <span>Oleadas Alcanzadas</span>
                                <strong>${this.currentWave}</strong>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="stat-final">
                                <i class="fas fa-tachometer-alt"></i>
                                <span>WPM Promedio</span>
                                <strong>${this.wpm}</strong>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="stat-final">
                                <i class="fas fa-star"></i>
                                <span>XP Ganado</span>
                                <strong>${xpGained}</strong>
                            </div>
                        </div>
                    </div>
                    <div class="encouragement mt-4">
                        <h5>💪 ¡No te rindas!</h5>
                        <p>Cada batalla te hace más fuerte. ¡Inténtalo de nuevo!</p>
                    </div>
                </div>
            `;
        }
        
        modal.show();
    }
    
    saveScore(xpGained) {
        fetch('/typing/mode/save-score', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({
                mode: 'survival',
                score: this.score,
                wpm: this.wpm,
                accuracy: this.accuracy,
                time_played: Math.round((Date.now() - this.startTime) / 1000),
                extra_data: {
                    difficulty: this.difficulty,
                    waves_completed: this.currentWave,
                    lives_remaining: this.lives,
                    enemies_defeated: this.currentWave - 1
                }
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                console.log('Puntuación guardada:', data);
            }
        })
        .catch(error => {
            console.error('Error al guardar puntuación:', error);
        });
    }
    
    // Efectos visuales
    createParticleEffect(emoji, color) {
        const effectsLayer = document.getElementById('effectsLayer');
        
        for (let i = 0; i < 20; i++) {
            const particle = document.createElement('div');
            particle.textContent = emoji;
            particle.style.position = 'fixed';
            particle.style.left = Math.random() * window.innerWidth + 'px';
            particle.style.top = Math.random() * window.innerHeight + 'px';
            particle.style.fontSize = (Math.random() * 20 + 20) + 'px';
            particle.style.color = color;
            particle.style.pointerEvents = 'none';
            particle.style.zIndex = '9999';
            particle.style.transition = 'all 2s ease-out';
            
            effectsLayer.appendChild(particle);
            
            // Animar partícula
            setTimeout(() => {
                particle.style.transform = `translateY(-${Math.random() * 200 + 100}px) rotate(${Math.random() * 360}deg)`;
                particle.style.opacity = '0';
            }, 50);
            
            // Remover partícula
            setTimeout(() => {
                particle.remove();
            }, 2000);
        }
    }
    
    createScreenShake() {
        const modal = document.querySelector('#survivalModal .modal-content');
        modal.classList.add('shake-effect');
        setTimeout(() => {
            modal.classList.remove('shake-effect');
        }, 500);
    }
    
    createVictoryEffects() {
        // Múltiples efectos de partículas
        setTimeout(() => this.createParticleEffect('🎉', '#ffc107'), 0);
        setTimeout(() => this.createParticleEffect('🏆', '#e67e22'), 200);
        setTimeout(() => this.createParticleEffect('⭐', '#f1c40f'), 400);
        setTimeout(() => this.createParticleEffect('💎', '#3498db'), 600);
    }
    
    showGameStatus(title, subtitle) {
        document.getElementById('statusText').textContent = title;
        document.getElementById('statusSubtext').textContent = subtitle;
        document.getElementById('gameStatus').style.display = 'block';
    }
    
    togglePause() {
        // Implementar pausa si es necesario
        console.log('Pause functionality');
    }
    
    restartGame() {
        document.getElementById('gameOverModal').querySelector('.btn-close').click();
        setTimeout(() => {
            this.startGame();
        }, 500);
    }
    
    endGame() {
        this.gameActive = false;
        if (this.waveTimer) clearInterval(this.waveTimer);
        if (this.sounds.background) this.sounds.background.pause();
    }
}

// Inicializar juego cuando la página esté lista
document.addEventListener('DOMContentLoaded', function() {
    // Verificar si estamos en la página de supervivencia
    if (window.location.pathname.includes('/mode/survival')) {
        window.survivalGame = new SurvivalGame();
    }
});

// Estilos adicionales para efectos finales
const additionalStyles = `
<style>
.stat-final {
    background: rgba(255,255,255,0.1);
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 15px;
    text-align: center;
}

.stat-final i {
    display: block;
    font-size: 2rem;
    margin-bottom: 10px;
    color: #f39c12;
}

.stat-final span {
    display: block;
    color: #bdc3c7;
    margin-bottom: 5px;
}

.stat-final strong {
    display: block;
    font-size: 1.5rem;
    color: #ecf0f1;
}

.victory-content {
    text-align: center;
}

.achievement-unlock {
    background: linear-gradient(145deg, #f39c12, #e67e22);
    padding: 20px;
    border-radius: 10px;
    color: white;
}

.encouragement {
    background: rgba(52, 152, 219, 0.2);
    padding: 20px;
    border-radius: 10px;
    border: 2px solid #3498db;
}
</style>
`;

document.head.insertAdjacentHTML('beforeend', additionalStyles);
