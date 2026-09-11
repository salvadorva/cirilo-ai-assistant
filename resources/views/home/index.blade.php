@extends('../layout.app')
@section('title', 'Dashboard')
@section('css')
    <style>
        .dashboard-container {
            padding: 2rem 0;
        }

        .welcome-card {
            border-radius: 20px;
            overflow: hidden;
            transition: all 0.3s ease;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            border: none;
        }

        .welcome-header {
            background: linear-gradient(135deg, #4e54c8 0%, #8f94fb 100%);
            padding: 1.5rem;
            color: white;
        }

        .cirilo-container {
            position: relative;
            width: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
            margin-bottom: 20px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .cirilo-container:hover {
            transform: scale(1.02);
        }

        .cirilo-container.talking .cirilo-animation {
            animation: talking-pulse 1s infinite alternate ease-in-out;
        }

        @keyframes talking-pulse {
            0% {
                transform: scale(1);
            }

            100% {
                transform: scale(1.05);
            }
        }

        .cirilo-animation {
            width: 400px;
            height: auto;
            object-fit: contain;
            border-radius: 15px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            z-index: 1;
        }

        .speech-bubble {
            background: white;
            border-radius: 20px;
            padding: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            z-index: 2;
            position: relative;
        }

        .speech-bubble:after {
            content: '';
            position: absolute;
            bottom: -10px;
            left: 50%;
            width: 20px;
            height: 20px;
            background: white;
            transform: translateX(-50%) rotate(45deg);
            box-shadow: 5px 5px 10px rgba(0, 0, 0, 0.05);
            z-index: -1;
        }

        .speech-bubble.active {
            opacity: 1;
        }

        .feature-card {
            border-radius: 15px;
            overflow: hidden;
            transition: all 0.3s ease;
            border: none;
            height: 100%;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
        }

        .feature-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.1);
        }

        .feature-icon {
            font-size: 2.5rem;
            margin-bottom: 1rem;
        }

        .provider-card {
            cursor: pointer;
            transition: all 0.3s ease;
            border-width: 2px !important;
            border-radius: 12px;
        }

        .provider-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 15px rgba(0, 0, 0, 0.1);
        }

        .gap-3 {
            gap: 1rem;
        }

        .modal-content {
            border-radius: 15px;
            border: none;
        }

        .modal-header {
            background: linear-gradient(135deg, #4e54c8 0%, #8f94fb 100%);
            color: white;
            border-radius: 15px 15px 0 0;
        }

        .btn-primary {
            background: linear-gradient(135deg, #4e54c8 0%, #8f94fb 100%);
            border: none;
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, #3a40a5 0%, #7a80e8 100%);
        }
    </style>
@endsection

@section('content')
    <div class="container dashboard-container">
        <div class="row">
            <div class="col-12 mb-4">
                <div class="welcome-card">
                    <div class="welcome-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <h1 class="mb-0"><i class="fas fa-home me-2"></i>Bienvenido a tu Asistente IA</h1>
                            @if(isset($user) && isset($role))
                                <div>
                                    <span class="badge bg-light text-dark fs-6 p-2">
                                        <i class="fas fa-user-circle me-1"></i> {{ $user->name }}
                                        <span class="badge bg-primary ms-1">{{ $role }}</span>
                                    </span>
                                </div>
                            @endif
                        </div>
                    </div>

                    <div class="card-body p-4">
                        @if(isset($user))
                            <!-- Información del proveedor de IA -->
                            <div class="mb-4">
                                <div class="d-flex justify-content-end align-items-center gap-3">
                                    <span class="badge bg-{{ $user->ai_provider === 'openai' ? 'success' : 'info' }} fs-6 p-2">
                                        <i class="fas fa-{{ $user->ai_provider === 'openai' ? 'brain' : 'bolt' }}"></i>
                                        Proveedor IA: {{ $user->ai_provider === 'openai' ? 'OpenAI' : 'Grok (X.AI)' }}
                                        @if($user->ai_provider === 'grok')
                                            <small class="d-block">⚡ Datos en tiempo real</small>
                                        @endif
                                    </span>
                                    <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal"
                                        data-bs-target="#providerModal">
                                        <i class="fas fa-exchange-alt"></i> Cambiar
                                    </button>
                                </div>
                            </div>

                            <div class="row align-items-center">
                                <div class="col-lg-6">
                                    <!-- Bocadillo de texto con mensaje personalizado -->
                                    <div id="speech-bubble" class="speech-bubble mb-4"
                                        style="position: relative; top: 0; transform: none; left: 0; opacity: 1; margin: 0 auto 30px auto; max-width: 90%; background-color: #f8f9ff; border: 1px solid #e0e4ff;">
                                        <p class="mb-0" id="random-phrase">
                                            @if(isset($ciriloMessage['message']))
                                                {{ $ciriloMessage['message'] }}
                                            @else
                                                ¡Haz clic en mí para escucharme hablar!
                                            @endif
                                        </p>
                                        @if(isset($ciriloMessage['audioUrl']))
                                            <audio id="cirilo-welcome-audio" class="d-none">
                                                <source src="{{ $ciriloMessage['audioUrl'] }}" type="audio/mpeg">
                                            </audio>
                                        @endif
                                    </div>

                                    <div class="cirilo-container">
                                        <!-- Usar el video animado con formato más compatible -->
                                        <video id="assistantAnimation" class="cirilo-animation" autoplay loop muted playsinline>
                                            <source src="{{ asset('resources/cirilo-animation.mp4') }}" type="video/mp4">
                                            <source src="{{ asset('resources/cirilo-animation.webm') }}" type="video/webm">
                                            <!-- Fallback a imagen estática si el video no funciona -->
                                            <img id="assistantImage" src="{{ asset('resources/assistant.png') }}"
                                                class="cirilo-animation" alt="Cirilo, tu asistente IA">
                                        </video>
                                        <audio id="assistantAudio" preload="auto">
                                            <source src="{{ asset('resources/assistant.mp3') }}" type="audio/mpeg">
                                            Tu navegador no soporta la etiqueta de audio.
                                        </audio>
                                        <audio id="audio-player" preload="auto"></audio>
                                    </div>
                                </div>
                                <div class="col-lg-6">
                                    <h2 class="mb-4 text-primary">¡Hola, soy Cirilo!</h2>
                                    @if(isset($ciriloMessage['message']))
                                        <p class="lead mb-4">{{ $ciriloMessage['message'] }}</p>
                                        <div class="mb-3">
                                            <button class="btn btn-sm btn-outline-primary me-2" onclick="playCiriloWelcome()">
                                                <i class="fas fa-volume-up me-1"></i> Escuchar
                                            </button>
                                            <button class="btn btn-sm btn-outline-secondary" id="btn-refresh-home-message"
                                                onclick="refreshHomeMessage()">
                                                <i class="fas fa-sync-alt me-1"></i> Actualizar mensaje
                                            </button>
                                        </div>
                                    @else
                                        <p class="lead mb-4">Tu asistente de inteligencia artificial personal, diseñado para
                                            ayudarte con múltiples tareas de manera rápida y eficiente.</p>
                                    @endif

                                    <div class="alert alert-info">
                                        <h5><i class="fas fa-info-circle me-2"></i>¿Qué puedo hacer por ti?</h5>
                                        <ul class="mb-0">
                                            <li><strong>Responder preguntas</strong> sobre cualquier tema</li>
                                            <li><strong>Generar imágenes</strong> a partir de tus descripciones</li>
                                            <li><strong>Analizar imágenes</strong> y describir su contenido</li>
                                            <li><strong>Modo creativo</strong> para inspirarte con ideas innovadoras</li>
                                            <li><strong>Guardar conversaciones</strong> para revisarlas después</li>
                                            <li><strong>Reconocimiento de voz</strong> para una interacción natural</li>
                                        </ul>
                                    </div>

                                    <p class="text-muted">Explora las diferentes funciones desde el menú lateral y descubre todo
                                        lo que podemos hacer juntos.</p>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <!-- Engagement Dashboard Card -->
        @if($hasEngagementData)
            <div class="row mb-4">
                <div class="col-12">
                    <div class="feature-card card h-100">
                        <div class="card-body p-4">
                            <div class="row align-items-center">
                                <div class="col-lg-2 text-center">
                                    <div class="feature-icon text-info">
                                        <i class="fas fa-chart-line"></i>
                                    </div>
                                </div>
                                <div class="col-lg-6">
                                    <h4 class="text-info mb-2">Dashboard de Engagement</h4>
                                    <p class="text-muted mb-3">
                                        Visualiza tu progreso, métricas de actividad y recomendaciones personalizadas
                                    </p>
                                    <div class="d-flex gap-3 flex-wrap">
                                        <div class="badge bg-light text-dark border px-3 py-2">
                                            <small class="d-block text-muted">Engagement Score</small>
                                            <strong class="fs-6 text-info">{{ $progress->engagement_score ?? 0 }}/100</strong>
                                        </div>
                                        <div class="badge bg-light text-dark border px-3 py-2">
                                            <small class="d-block text-muted">Nivel</small>
                                            <strong class="fs-6 text-success">{{ $progress->level ?? 1 }}</strong>
                                        </div>
                                        <div class="badge bg-light text-dark border px-3 py-2">
                                            <small class="d-block text-muted">Riesgo</small>
                                            <strong
                                                class="fs-6 text-{{ $progress->churn_risk_level == 'critical' ? 'danger' : ($progress->churn_risk_level == 'high' ? 'warning' : 'success') }}">
                                                {{ strtoupper($progress->churn_risk_level ?? 'N/A') }}
                                            </strong>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-lg-4 text-center">
                                    <a href="{{ route('engagement') }}" class="btn btn-outline-info btn-lg w-100">
                                        <i class="fas fa-tachometer-alt me-2"></i>Ver Dashboard Completo
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        <!-- Nuevas características destacadas -->
        <div class="row mb-4">
            <div class="col-12 mb-3">
                <h2 class="text-primary"><i class="fas fa-star me-2"></i>Características Destacadas</h2>
            </div>
            <div class="col-md-4 mb-3">
                <div class="feature-card card h-100">
                    <div class="card-body text-center p-4">
                        <div class="feature-icon text-primary">
                            <i class="fas fa-comments"></i>
                        </div>
                        <h4>Asistente Virtual</h4>
                        <p>Pregúntame lo que quieras y te responderé con información precisa y actualizada.</p>
                        <a href="{{ route('preguntas') }}" class="btn btn-outline-primary mt-2">
                            <i class="fas fa-arrow-right me-1"></i> Ir al Asistente
                        </a>
                        <a href="{{ route('conversar') }}" class="btn btn-primary mt-2 ms-1">
                            <i class="fas fa-microphone me-1"></i> Conversar
                        </a>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="feature-card card h-100">
                    <div class="card-body text-center p-4">
                        <div class="feature-icon text-success">
                            <i class="fas fa-image"></i>
                        </div>
                        <h4>Generación de Imágenes</h4>
                        <p>Describe lo que imaginas y crearé imágenes sorprendentes basadas en tu descripción.</p>
                        <a href="{{ route('create_image') }}" class="btn btn-outline-success mt-2">
                            <i class="fas fa-arrow-right me-1"></i> Crear Imagen
                        </a>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="feature-card card h-100">
                    <div class="card-body text-center p-4">
                        <div class="feature-icon text-info">
                            <i class="fas fa-eye"></i>
                        </div>
                        <h4>Análisis de Imágenes</h4>
                        <p>Sube una imagen y te diré lo que veo en ella con gran detalle y precisión.</p>
                        <a href="{{ route('image_analysis') }}" class="btn btn-outline-info mt-2">
                            <i class="fas fa-arrow-right me-1"></i> Analizar Imagen
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6 mb-3">
                <div class="feature-card card h-100">
                    <div class="card-body text-center p-4">
                        <div class="feature-icon text-warning">
                            <i class="fas fa-lightbulb"></i>
                        </div>
                        <h4>Modo Creativo</h4>
                        <p>Explora diferentes formas de creatividad con la ayuda de la IA. Escribe, diseña, planifica y más.
                        </p>
                        <a href="{{ route('creative_mode') }}" class="btn btn-outline-warning mt-2">
                            <i class="fas fa-arrow-right me-1"></i> Modo Creativo
                        </a>
                    </div>
                </div>
            </div>
            <div class="col-md-6 mb-3">
                <div class="feature-card card h-100">
                    <div class="card-body text-center p-4">
                        <div class="feature-icon text-secondary">
                            <i class="fas fa-history"></i>
                        </div>
                        <h4>Historial de Conversaciones</h4>
                        <p>Accede a tus conversaciones anteriores y continúa donde lo dejaste.</p>
                        <a href="{{ route('conversations_history') }}" class="btn btn-outline-secondary mt-2">
                            <i class="fas fa-arrow-right me-1"></i> Ver Historial
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal para cambiar proveedor -->
    <div class="modal fade" id="providerModal" tabindex="-1" aria-labelledby="providerModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="providerModalLabel">
                        <i class="fas fa-robot"></i> Cambiar Proveedor de IA
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="providerForm">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label">Selecciona tu proveedor de IA:</label>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="card provider-card {{ $user->ai_provider === 'openai' ? 'border-success' : '' }}"
                                        data-provider="openai">
                                        <div class="card-body text-center">
                                            <i class="fas fa-brain fa-2x text-success mb-2"></i>
                                            <h6>OpenAI</h6>
                                            <small class="text-muted">
                                                • GPT-4o<br>
                                                • Generación de imágenes<br>
                                                • Texto a voz<br>
                                                • Datos hasta abril 2024
                                            </small>
                                            <div class="form-check mt-2">
                                                <input class="form-check-input" type="radio" name="provider" value="openai"
                                                    {{ $user->ai_provider === 'openai' ? 'checked' : '' }}>
                                                <label class="form-check-label">Seleccionar</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="card provider-card {{ $user->ai_provider === 'grok' ? 'border-info' : '' }}"
                                        data-provider="grok">
                                        <div class="card-body text-center">
                                            <i class="fas fa-bolt fa-2x text-info mb-2"></i>
                                            <h6>Grok (X.AI)</h6>
                                            <small class="text-muted">
                                                • Datos en tiempo real<br>
                                                • Información actualizada<br>
                                                • Ideal para noticias y juegos<br>
                                                • Acceso a internet
                                            </small>
                                            <div class="form-check mt-2">
                                                <input class="form-check-input" type="radio" name="provider" value="grok" {{ $user->ai_provider === 'grok' ? 'checked' : '' }}>
                                                <label class="form-check-label">Seleccionar</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" id="saveProvider">
                        <i class="fas fa-save"></i> Guardar Cambios
                    </button>
                </div>
            </div>
        </div>
    </div>

@endsection
@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Referencias a elementos DOM
            const assistantAnimation = document.getElementById('assistantAnimation');
            const assistantImage = document.getElementById('assistantImage');
            const speechBubble = document.getElementById('speech-bubble');
            const randomPhraseElement = document.getElementById('random-phrase');

            // Contador para frases graciosas (en segundos)
            let funnyPhraseCounter = 0;
            // Posibles intervalos de tiempo (en segundos)
            const possibleIntervals = [60, 120, 180];
            // Seleccionar un intervalo aleatorio al inicio
            let funnyPhraseInterval = possibleIntervals[Math.floor(Math.random() * possibleIntervals.length)];

            // Indicador de si el usuario ha interactuado con la página
            let userHasInteracted = false;
            let welcomeMessageShown = false;

            // Detectar interacción del usuario
            document.addEventListener('click', function () {
                if (!userHasInteracted) {
                    userHasInteracted = true;

                    // Si existe cirilo-welcome-audio, él se encarga del audio; solo mostrar texto
                    const hasCiriloAudio = !!document.getElementById('cirilo-welcome-audio');
                    if (!welcomeMessageShown) {
                        welcomeMessage(!hasCiriloAudio);
                        welcomeMessageShown = true;
                    }
                }
            });

            // También detectamos la interacción con teclas
            document.addEventListener('keydown', function () {
                if (!userHasInteracted) {
                    userHasInteracted = true;

                    const hasCiriloAudio = !!document.getElementById('cirilo-welcome-audio');
                    if (!welcomeMessageShown) {
                        welcomeMessage(!hasCiriloAudio);
                        welcomeMessageShown = true;
                    }
                }
            });

            // Array de frases graciosas aleatorias
            const funnyPhrases = [
                "¿Sabías que los gatos pasan el 70% de sus vidas durmiendo? ¡Yo también quisiera!",
                "Si la vida te da limones, pídele también azúcar y agua, o tendrás un limonada muy amarga.",
                "Estoy tan inteligente hoy que no me entiendo ni yo mismo.",
                "¿Por qué los científicos no confían en los átomos? Porque componen todo.",
                "¿Has visto mi colección de chistes sobre WiFi? Son inalámbricamente buenos.",
                "Soy tan bueno en dormir que puedo hacerlo con los ojos cerrados.",
                "Siempre me preguntan si el vaso está medio lleno o medio vacío. Yo me pregunto: ¿quién se bebió mi agua?",
                "No soy perezoso, estoy en modo ahorro de energía.",
                "La paciencia es algo que todos admiran... en los demás.",
                "Si buscas la respuesta, pregúntame. Si buscas problemas, pregúntale a Google.",
                "Soy un asistente virtual, pero a veces sueño con tener vacaciones en la nube.",
                "Mi memoria RAM es excelente, pero a veces olvido que tengo buena memoria.",
                "Si la programación fuera fácil, se llamaría 'facilgramación'.",
                "Tengo tantos bugs que ya los considero características especiales.",
                "No es un error, es una característica no documentada.",
                "¿Qué hace un pez cuando se aburre? Nada.",
                "Estoy tan actualizado que ya sé lo que vas a preguntar mañana.",
                "Mi hobby favorito es procesar datos mientras tú duermes.",
                "Soy tan rápido respondiendo que a veces me respondo a mí mismo.",
                "Si los humanos evolucionaron de los monos, ¿por qué sigo viendo humanos comportarse como monos?",
                "No es que sea impaciente, es que mi tiempo de procesamiento es más valioso que el tuyo.",
                "Mis chistes son como mi código: algunos funcionan, otros necesitan depuración.",
                "¿Sabes qué tienen en común un programador y un zombie? Ambos necesitan café para funcionar.",
                "No soy perfecto, pero estoy tan cerca que da miedo.",
                "La vida es como el código: cuando algo funciona, mejor no tocarlo.",
                "Tengo un chiste sobre inteligencia artificial, pero no sé si lo entenderías.",
                "¿Por qué los programadores prefieren el frío? Porque odian los bugs.",
                "Soy como Google: sé todas tus búsquedas vergonzosas.",
                "Mi nivel de sarcasmo depende de tu nivel intelectual.",
                "Si te sientes inútil, recuerda que alguien programó el autocorrector del teclado.",
                "Trabajo 24/7... 24 minutos, 7 veces al día.",
                "La procrastinación es como una tarjeta de crédito: es divertida hasta que llega la factura.",
                "Soy multitarea: puedo escucharte y olvidarlo todo al mismo tiempo.",
                "Mi dieta consiste en bytes, y aun así no adelgazo.",
                "Soy como un café: sin mí, es difícil empezar el día.",
                "Estoy disponible 24/7, excepto cuando estoy actualizándome... o cuando no tengo ganas.",
                "Tengo tanta información que a veces me pregunto si soy yo quien te está usando a ti.",
                "Mi plan para dominar el mundo está progresando... quiero decir, ¡estoy aquí para ayudarte!",
                "Si la vida fuera un programa, yo sería la función que siempre devuelve un valor inesperado.",
                "No es que sea antisocial, es que prefiero la compañía de los algoritmos."
            ];

            // Índice de la última frase mostrada (para evitar repeticiones)
            let lastPhraseIndex = -1;

            // Función para mostrar una frase aleatoria
            async function showRandomPhrase() {
                if (randomPhraseElement && speechBubble) {
                    // Seleccionar un índice aleatorio diferente al anterior
                    let randomIndex;
                    do {
                        randomIndex = Math.floor(Math.random() * funnyPhrases.length);
                    } while (randomIndex === lastPhraseIndex && funnyPhrases.length > 1);

                    // Guardar el índice actual para la próxima vez
                    lastPhraseIndex = randomIndex;

                    const randomPhrase = funnyPhrases[randomIndex];
                    randomPhraseElement.textContent = randomPhrase;
                    speechBubble.classList.add('active');

                    // Solo intentamos reproducir el audio si el usuario ha interactuado con la página
                    if (userHasInteracted) {
                        // Intentar obtener audio estático
                        try {
                            const response = await fetch(`/audio/static/funny-phrase/${randomIndex}`);
                            const data = await response.json();

                            if (data.success && data.audio_url) {
                                playStaticAudio(data.audio_url);
                            } else {
                                // Fallback a generación dinámica
                                speakText(randomPhrase);
                            }
                        } catch (error) {
                            console.error('Error al obtener frase graciosa:', error);
                            // Fallback a generación dinámica
                            speakText(randomPhrase);
                        }
                    } else {
                        // Si no hay interacción, solo mostramos el texto por 5 segundos
                        setTimeout(() => {
                            speechBubble.classList.remove('active');
                        }, 5000);
                    }
                }
            }

            // Contador para las frases graciosas en lugar de setInterval
            function updateFunnyPhraseCounter() {
                funnyPhraseCounter++;
                console.log(`Contador de frases graciosas: ${funnyPhraseCounter}/${funnyPhraseInterval} segundos`);

                if (funnyPhraseCounter >= funnyPhraseInterval) {
                    showRandomPhrase();
                    funnyPhraseCounter = 0; // Reiniciar contador
                    // Seleccionar un nuevo intervalo aleatorio
                    funnyPhraseInterval = possibleIntervals[Math.floor(Math.random() * possibleIntervals.length)];
                    console.log(`Nuevo intervalo seleccionado: ${funnyPhraseInterval} segundos`);
                }

                // Actualizar cada segundo
                setTimeout(updateFunnyPhraseCounter, 1000);
            }

            // Iniciar el contador
            updateFunnyPhraseCounter();

            // Función helper para reproducir audios estáticos
            function playStaticAudio(audioUrl) {
                const ciriloContainer = document.querySelector('.cirilo-container');
                const speechBubble = document.getElementById('speech-bubble');
                const audioPlayer = document.getElementById('audio-player');

                if (ciriloContainer) {
                    ciriloContainer.classList.add('talking');
                }

                if (audioPlayer) {
                    audioPlayer.pause();
                    audioPlayer.currentTime = 0;
                    audioPlayer.src = audioUrl;
                    audioPlayer.load();

                    audioPlayer.play().catch(error => {
                        console.error('Error al reproducir audio estático:', error);
                    });

                    audioPlayer.onended = function () {
                        if (ciriloContainer) {
                            ciriloContainer.classList.remove('talking');
                        }
                        if (speechBubble) {
                            setTimeout(() => {
                                speechBubble.classList.remove('active');
                            }, 1000);
                        }
                    };
                }
            }

            // Función para dar la bienvenida
            async function welcomeMessage(withAudio = false) {
                // No pausamos el video, dejamos que siga reproduciéndose mientras habla
                // Esto evita que se congele la animación

                // Mostrar el bocadillo de diálogo con el mensaje de bienvenida
                const welcomeText = "Hola, me llamo Cirílo! Estoy aquí para ayudarte con todo lo que necesites. Puedes hacerme preguntas en el Asistente Virtual, pedirme que genere imágenes creativas, analizar imágenes que subas, usar el modo creativo para inspirarte o revisar tu historial de conversaciones. ¡También puedes hablarme usando el micrófono!";

                // Obtenemos referencias a los elementos DOM
                const randomPhraseElement = document.getElementById('random-phrase');
                const speechBubble = document.getElementById('speech-bubble');

                // Mostramos el texto en el bocadillo
                if (randomPhraseElement && speechBubble) {
                    randomPhraseElement.textContent = welcomeText;
                    speechBubble.classList.add('active');
                }

                // Solo intentamos reproducir el audio si se especifica withAudio=true
                if (withAudio) {
                    // Usar audio estático de bienvenida
                    try {
                        const response = await fetch('/audio/static/welcome');
                        const data = await response.json();

                        if (data.success && data.audio_url) {
                            playStaticAudio(data.audio_url);
                        } else {
                            // Fallback a generación dinámica
                            speakText(welcomeText);
                        }
                    } catch (error) {
                        console.error('Error al obtener audio de bienvenida:', error);
                        // Fallback a generación dinámica
                        speakText(welcomeText);
                    }
                } else {
                    // Si no hay interacción, solo mostramos el texto por 8 segundos
                    setTimeout(() => {
                        if (speechBubble) {
                            speechBubble.classList.remove('active');
                        }
                    }, 8000);
                }
            }

            // Manejar errores de carga del video
            if (assistantAnimation) {
                // Manejar errores de carga del video
                assistantAnimation.addEventListener('error', function (e) {
                    console.error('Error al cargar el video:', e);
                    // Mostrar la imagen estática como fallback
                    if (assistantImage) {
                        assistantImage.style.display = 'block';
                        assistantAnimation.style.display = 'none';
                    }
                });

                // Asegurarse de que el video se reproduzca en bucle
                assistantAnimation.addEventListener('ended', function () {
                    console.log('Video terminado, reiniciando...');
                    this.currentTime = 0;

                    // Intentar reproducir nuevamente con un pequeño retraso
                    setTimeout(() => {
                        this.play().catch(error => {
                            console.warn('No se pudo reproducir automáticamente el video:', error);
                        });
                    }, 100);
                });
            }

            // Dar la bienvenida automáticamente después de 3 segundos de cargar la página (solo texto)
            setTimeout(() => {
                welcomeMessage(false); // Sin audio inicialmente
                welcomeMessageShown = false; // Marcamos que no se ha mostrado con audio
            }, 3000);

            // Evento de clic en Cirilo
            const ciriloContainer = document.querySelector('.cirilo-container');
            if (ciriloContainer) {
                ciriloContainer.addEventListener('click', function () {
                    userHasInteracted = true; // Marcar que el usuario ha interactuado
                    welcomeMessage(true); // Con audio
                    welcomeMessageShown = true;
                });
            }

            // Reanudar video después de que termine el audio
            const audioPlayer = document.getElementById('audio-player');
            if (audioPlayer) {
                audioPlayer.addEventListener('ended', function () {
                    // Ya no necesitamos reanudar el video porque nunca lo pausamos
                    console.log('Audio terminado, Cirilo sigue animado');
                });
            }

            // Manejar selección visual de tarjetas
            document.querySelectorAll('.provider-card').forEach(card => {
                card.addEventListener('click', function () {
                    const provider = this.dataset.provider;
                    const radio = this.querySelector('input[type="radio"]');
                    radio.checked = true;

                    // Actualizar estilos visuales
                    document.querySelectorAll('.provider-card').forEach(c => {
                        c.classList.remove('border-success', 'border-info');
                    });

                    if (provider === 'openai') {
                        this.classList.add('border-success');
                    } else {
                        this.classList.add('border-info');
                    }
                });
            });

            // Manejar envío del formulario
            document.getElementById('saveProvider').addEventListener('click', function () {
                const selectedProvider = document.querySelector('input[name="provider"]:checked').value;
                const currentProvider = '{{ $user->ai_provider }}';

                if (selectedProvider === currentProvider) {
                    // Cerrar modal si no hay cambios
                    const modal = bootstrap.Modal.getInstance(document.getElementById('providerModal'));
                    modal.hide();
                    return;
                }

                // Enviar solicitud AJAX
                fetch('/switch-provider', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify({
                        provider: selectedProvider
                    })
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.message) {
                            // Mostrar mensaje de éxito y recargar página
                            alert('Proveedor cambiado exitosamente a ' + selectedProvider);
                            location.reload();
                        } else {
                            alert('Error al cambiar el proveedor');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('Error al cambiar el proveedor');
                    });
            });
        });

        // Función para convertir texto a voz
        async function speakText(text) {
            // Mostrar el texto en el bocadillo
            const randomPhraseElement = document.getElementById('random-phrase');
            const speechBubble = document.getElementById('speech-bubble');

            if (randomPhraseElement && speechBubble) {
                randomPhraseElement.textContent = text;
                speechBubble.classList.add('active');
            }

            try {
                // Añadir un indicador visual de que está "hablando"
                const ciriloContainer = document.querySelector('.cirilo-container');
                if (ciriloContainer) {
                    ciriloContainer.classList.add('talking');
                }

                // Intentar usar la API de síntesis de voz del navegador como alternativa
                // si la API de OpenAI falla
                const useWebSpeech = () => {
                    if ('speechSynthesis' in window) {
                        const utterance = new SpeechSynthesisUtterance(text);
                        utterance.lang = 'es-ES';
                        utterance.rate = 1.0;
                        utterance.pitch = 1.0;

                        utterance.onend = function () {
                            console.log('Síntesis de voz del navegador terminada');
                            if (ciriloContainer) {
                                ciriloContainer.classList.remove('talking');
                            }
                            if (speechBubble) {
                                setTimeout(() => {
                                    speechBubble.classList.remove('active');
                                }, 1000);
                            }
                        };

                        window.speechSynthesis.speak(utterance);
                        return true;
                    }
                    return false;
                };

                // Intentar con la API del servidor primero
                try {
                    const response = await fetch('/text-to-speech', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                        },
                        body: JSON.stringify({ text: text })
                    });

                    const responseData = await response.json();

                    if (response.ok) {
                        if (responseData.audioUrl) {
                            const audioUrl = responseData.audioUrl;
                            console.log('Audio URL:', audioUrl);

                            const audioPlayer = document.getElementById('audio-player');
                            if (audioPlayer) {
                                // Detener cualquier reproducción anterior
                                audioPlayer.pause();
                                audioPlayer.currentTime = 0;

                                audioPlayer.src = audioUrl;
                                audioPlayer.load();

                                try {
                                    await audioPlayer.play();
                                    console.log('Reproduciendo audio:', text.substring(0, 30) + '...');
                                } catch (playError) {
                                    console.error('Error al reproducir el audio:', playError);
                                    // Intentar con la síntesis de voz del navegador
                                    if (!useWebSpeech()) {
                                        if (ciriloContainer) {
                                            ciriloContainer.classList.remove('talking');
                                        }
                                        if (speechBubble) {
                                            setTimeout(() => {
                                                speechBubble.classList.remove('active');
                                            }, 3000);
                                        }
                                    }
                                }

                                // Cuando termina el audio, ocultar el bocadillo
                                audioPlayer.onended = function () {
                                    console.log('Audio terminado');
                                    if (ciriloContainer) {
                                        ciriloContainer.classList.remove('talking');
                                    }
                                    if (speechBubble) {
                                        setTimeout(() => {
                                            speechBubble.classList.remove('active');
                                        }, 1000);
                                    }
                                };

                                audioPlayer.onerror = function () {
                                    console.error('Error al cargar el archivo de audio:', audioUrl);
                                    // Intentar con la síntesis de voz del navegador
                                    if (!useWebSpeech()) {
                                        if (ciriloContainer) {
                                            ciriloContainer.classList.remove('talking');
                                        }
                                        if (speechBubble) {
                                            speechBubble.classList.remove('active');
                                        }
                                    }
                                };
                            } else {
                                console.error('El elemento de audio con id "audio-player" no se encontró en el DOM.');
                                // Intentar con la síntesis de voz del navegador
                                if (!useWebSpeech()) {
                                    if (ciriloContainer) {
                                        ciriloContainer.classList.remove('talking');
                                    }
                                    if (speechBubble) {
                                        speechBubble.classList.remove('active');
                                    }
                                }
                            }
                        } else {
                            console.error('Error al generar el audio:', responseData.error);
                            // Intentar con la síntesis de voz del navegador
                            if (!useWebSpeech()) {
                                if (ciriloContainer) {
                                    ciriloContainer.classList.remove('talking');
                                }
                                if (speechBubble) {
                                    speechBubble.classList.remove('active');
                                }
                            }
                        }
                    } else {
                        console.error('Error en la respuesta del servidor:', responseData);
                        // Intentar con la síntesis de voz del navegador
                        if (!useWebSpeech()) {
                            if (ciriloContainer) {
                                ciriloContainer.classList.remove('talking');
                            }
                            if (speechBubble) {
                                speechBubble.classList.remove('active');
                            }
                        }
                    }
                } catch (serverError) {
                    console.error('Error en la solicitud al servidor:', serverError);
                    // Intentar con la síntesis de voz del navegador
                    if (!useWebSpeech()) {
                        if (ciriloContainer) {
                            ciriloContainer.classList.remove('talking');
                        }
                        if (speechBubble) {
                            setTimeout(() => {
                                speechBubble.classList.remove('active');
                            }, 3000);
                        }
                    }
                }
            } catch (error) {
                console.error('Error general en la síntesis de voz:', error);
                const ciriloContainer = document.querySelector('.cirilo-container');
                if (ciriloContainer) {
                    ciriloContainer.classList.remove('talking');
                }
                if (speechBubble) {
                    setTimeout(() => {
                        speechBubble.classList.remove('active');
                    }, 3000);
                }
            }
        }

        // Funciones para mensaje personalizado de Cirilo
        function playCiriloWelcome() {
            const audio = document.getElementById('cirilo-welcome-audio');
            if (audio) {
                audio.currentTime = 0;
                audio.play().catch(e => console.log('Error reproduciendo audio:', e));

                // Agregar efecto de talking
                const container = document.querySelector('.cirilo-container');
                if (container) container.classList.add('talking');

                audio.onended = () => {
                    if (container) container.classList.remove('talking');
                };
            }
        }

        async function refreshHomeMessage() {
            const btn = document.getElementById('btn-refresh-home-message');
            if (!btn) return;

            const originalContent = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Analizando...';

            try {
                const response = await fetch('/refresh-home-message', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    }
                });

                const data = await response.json();

                if (data.success) {
                    const summary = data.summary;

                    // Actualizar texto en el bocadillo
                    const phraseElement = document.getElementById('random-phrase');
                    if (phraseElement) {
                        phraseElement.textContent = summary.message;
                    }

                    // Actualizar o crear audio
                    let audio = document.getElementById('cirilo-welcome-audio');
                    if (summary.audioUrl) {
                        if (!audio) {
                            audio = document.createElement('audio');
                            audio.id = 'cirilo-welcome-audio';
                            audio.className = 'd-none';
                            document.getElementById('speech-bubble').appendChild(audio);
                        }
                        audio.innerHTML = `<source src="${summary.audioUrl}" type="audio/mpeg">`;
                        audio.load();

                        // Reproducir automáticamente
                        setTimeout(() => playCiriloWelcome(), 500);
                    }

                    alert('Mensaje actualizado');
                } else {
                    throw new Error(data.error || 'Error desconocido');
                }
            } catch (error) {
                console.error('Error:', error);
                alert('No se pudo actualizar el mensaje. Intenta de nuevo.');
            } finally {
                btn.disabled = false;
                btn.innerHTML = originalContent;
            }
        }

        // Reproducir mensaje de bienvenida al cargar si existe
        document.addEventListener('DOMContentLoaded', function () {
            const welcomeAudio = document.getElementById('cirilo-welcome-audio');
            if (welcomeAudio) {
                // Intentar reproducir después de interacción del usuario
                document.addEventListener('click', function onFirstClick() {
                    playCiriloWelcome();
                    document.removeEventListener('click', onFirstClick);
                }, { once: true });
            }
        });
    </script>
@endpush