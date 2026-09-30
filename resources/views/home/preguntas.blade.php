@extends('../layout.app')
@section('title','Asistente Virtual')
@section('css')
<!-- SweetAlert2 CSS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
<style>
    .chat-container {
        border-radius: 15px;
        overflow: hidden;
        transition: all 0.3s ease;
        box-shadow: 0 5px 15px rgba(0,0,0,0.08);
    }
    
    .chat-container:hover {
        transform: translateY(-5px);
        box-shadow: 0 15px 30px rgba(0,0,0,0.12);
    }
    
    .chat-header {
        background: linear-gradient(135deg, #4e54c8 0%, #8f94fb 100%);
        color: white;
        padding: 20px;
        border-radius: 15px 15px 0 0;
    }
    
    .chat-content {
        padding: 20px;
        background-color: #fff;
        border-radius: 0 0 15px 15px;
        height: 60vh;
        overflow-y: auto;
    }
    
    .chat-input-container {
        background-color: #f8f9fa;
        border-top: 1px solid #e9ecef;
        padding: 15px;
        border-radius: 0 0 15px 15px;
    }
    
    .message {
        margin-bottom: 15px;
        max-width: 85%;
        position: relative;
    }
    
    .user-message {
        background-color: #e3f2fd;
        color: #0d47a1;
        border-radius: 18px 18px 0 18px;
        padding: 12px 15px;
        margin-left: auto;
        box-shadow: 0 2px 5px rgba(0,0,0,0.05);
    }
    
    .assistant-message {
        background-color: #f5f5f5;
        color: #333;
        border-radius: 18px 18px 18px 0;
        padding: 12px 15px;
        margin-right: auto;
        box-shadow: 0 2px 5px rgba(0,0,0,0.05);
    }
    
    .message-time {
        font-size: 0.7rem;
        color: #6c757d;
        margin-top: 5px;
        text-align: right;
    }
    
    .typing-indicator {
        display: inline-block;
        padding: 12px 15px;
        background-color: #f5f5f5;
        border-radius: 18px 18px 18px 0;
        margin-bottom: 15px;
    }
    
    .typing-indicator span {
        height: 8px;
        width: 8px;
        background-color: #6c757d;
        display: inline-block;
        border-radius: 50%;
        animation: typing 1.5s infinite ease-in-out;
        margin: 0 1px;
    }
    
    .typing-indicator span:nth-child(2) {
        animation-delay: 0.2s;
    }
    
    .typing-indicator span:nth-child(3) {
        animation-delay: 0.4s;
    }
    
    @keyframes typing {
        0% { transform: translateY(0); }
        50% { transform: translateY(-5px); }
        100% { transform: translateY(0); }
    }
    
    /* Estilo para el botón pulsante durante la grabación */
    .pulse-recording {
        animation: pulse 1.5s infinite;
    }
    
    @keyframes pulse {
        0% {
            box-shadow: 0 0 0 0 rgba(220, 53, 69, 0.7);
        }
        70% {
            box-shadow: 0 0 0 10px rgba(220, 53, 69, 0);
        }
        100% {
            box-shadow: 0 0 0 0 rgba(220, 53, 69, 0);
        }
    }
    
    /* Ocultar el reproductor de audio pero mantenerlo funcional */
    #audio-player {
        height: 30px;
        margin-top: 10px;
    }
    
    .code-block {
        background-color: #282c34;
        color: #abb2bf;
        border-radius: 8px;
        padding: 15px;
        margin: 10px 0;
        font-family: 'Courier New', Courier, monospace;
        position: relative;
        overflow-x: auto;
    }
    
    .copy-btn {
        position: absolute;
        top: 5px;
        right: 5px;
        background-color: rgba(255, 255, 255, 0.1);
        color: #fff;
        border: none;
        border-radius: 4px;
        padding: 2px 8px;
        font-size: 12px;
        cursor: pointer;
        transition: all 0.2s;
    }
    
    .copy-btn:hover {
        background-color: rgba(255, 255, 255, 0.2);
    }

    .history-item:hover {
        background-color: #f8f9fa;
        border-radius: 4px;
    }
    .history-item:last-child {
        border-bottom: none !important;
    }
    
    .user-info-card {
        border-radius: 15px;
        overflow: hidden;
        transition: all 0.3s ease;
        box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        margin-bottom: 20px;
    }
    
    .user-info-header {
        background: linear-gradient(135deg, #4e54c8 0%, #8f94fb 100%);
        color: white;
        padding: 15px 20px;
    }
    
    .user-info-content {
        padding: 15px;
        background-color: #fff;
    }
</style>
@endsection

@section('content')  
<div class="container mt-4">
    <div class="row">
        <div class="col-12 mb-4">
            <h1 class="display-5 fw-bold text-primary"><i class="fa-solid fa-robot me-2"></i>Asistente Virtual</h1>
            <p class="lead">Pregúntame lo que quieras y te responderé con la información más actualizada posible.</p>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-3 mb-4">
            <!-- Información del usuario -->
            <div class="user-info-card">
                <div class="user-info-header">
                    <h4 class="mb-0"><i class="fa-solid fa-user-circle me-2"></i>Tu Perfil</h4>
                </div>
                <div class="user-info-content">
                    @if(isset($user))
                        <h5 class="mb-3">{{ $user->name }}</h5>
                        <div class="d-flex flex-column gap-2">
                                @if(isset($role))
                                <div class="d-flex align-items-center">
                                    <i class="fa-solid fa-id-badge text-primary me-2"></i>
                                    <span>Rol: <span class="fw-bold">{{ $role }}</span></span>
                                </div>
                                @endif
                                @if(isset($user))
                                <div class="d-flex align-items-center">
                                    <i class="fa-solid fa-{{ $user->ai_provider === 'openai' ? 'brain' : 'bolt' }} text-{{ $user->ai_provider === 'openai' ? 'success' : 'info' }} me-2"></i>
                                    <span>Proveedor: <span class="fw-bold">{{ $user->ai_provider === 'openai' ? 'OpenAI' : 'Grok' }}</span></span>
                                </div>
                                @endif
                            <div class="d-flex align-items-center">
                                <i class="fa-solid fa-calendar text-secondary me-2"></i>
                                <span>{{ $currentDate ?? now()->format('d/m/Y') }}</span>
                            </div>
                        </div>
                        <hr>
                        <button type="button" class="btn btn-outline-danger w-100" id="clear-history-button">
                            <i class="fa-solid fa-trash me-2"></i>Limpiar Historial
                        </button>
                    @else
                        <p class="text-muted">No has iniciado sesión</p>
                    @endif
                </div>
            </div>
            
            <!-- Historial de conversaciones -->
            <div class="card mb-4">
                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fa-solid fa-clock-rotate-left me-2"></i>Historial</h5>
                    <a href="{{ route('conversations_history') }}" class="btn btn-sm btn-outline-secondary" title="Ver todos">
                        <i class="fa-solid fa-list"></i>
                    </a>
                </div>
                <div class="card-body p-2" id="history-list">
                    <div class="text-center text-muted small py-2" id="history-loading">
                        <i class="fa-solid fa-spinner fa-spin me-1"></i>Cargando...
                    </div>
                </div>
            </div>

            <!-- Sugerencias rápidas -->
            <div class="card mb-4">
                <div class="card-header bg-light">
                    <h5 class="mb-0"><i class="fa-solid fa-lightbulb me-2"></i>Sugerencias</h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <button class="btn btn-outline-primary btn-sm suggestion-btn">¿Cuáles son las últimas noticias?</button>
                        <button class="btn btn-outline-primary btn-sm suggestion-btn">Explica la inteligencia artificial</button>
                        <button class="btn btn-outline-primary btn-sm suggestion-btn">Dame ideas para un proyecto</button>
                        <button class="btn btn-outline-primary btn-sm suggestion-btn">Escribe un poema corto</button>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-9">
            <!-- Contenedor del chat -->
            <div class="chat-container mb-4">
                <div class="chat-header d-flex justify-content-between align-items-center">
                    <h3 class="mb-0"><i class="fa-solid fa-comments me-2"></i>Conversación</h3>
                    <div class="d-flex gap-2">
                        <button class="btn btn-sm btn-light fw-semibold" id="new-conversation-btn" title="Iniciar nueva conversación">
                            <i class="fa-solid fa-plus me-1 text-success"></i>Nueva conversación
                        </button>
                        <button class="btn btn-sm btn-light" id="toggle-voice-btn" title="Activar/Desactivar voz">
                            <i class="fa-solid fa-volume-up"></i>
                        </button>
                    </div>
                </div>
                <div class="chat-content" id="responses-container">
                    @if(empty($user->prompt))
                        <div class="text-center py-5">
                            <i class="fa-solid fa-robot fa-4x text-muted mb-3"></i>
                            <p class="text-muted">¡Hola! Estoy aquí para ayudarte. Escribe tu pregunta abajo.</p>
                        </div>
                    @endif
                </div>
                <div class="chat-input-container">
                    <form id="generate-text-form" method="POST" action="/generate-text">
                        @csrf
                        <div class="input-group">
                            <input type="text" class="form-control" id="prompt" name="prompt" placeholder="Escribe tu pregunta aquí...">
                            <button type="button" id="mic-button" class="btn btn-outline-secondary" title="Reconocimiento de voz">
                                <i class="fa-solid fa-microphone"></i>
                            </button>
                            <button type="submit" class="btn btn-primary">
                                <i class="fa-solid fa-paper-plane me-1"></i>Enviar
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            <audio id="audio-player" controls class="d-none"></audio>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/clipboard/dist/clipboard.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
    // Configurar Axios para incluir el token CSRF en todas las peticiones
    axios.defaults.headers.common['X-CSRF-TOKEN'] = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    
    const form = document.getElementById('generate-text-form');
    const clearHistoryButton = document.getElementById('clear-history-button');
    const micButton = document.getElementById('mic-button');
    const promptInput = document.getElementById('prompt');
    const responsesContainer = document.getElementById('responses-container');
    const audioPlayer = document.getElementById('audio-player');
    const toggleVoiceBtn = document.getElementById('toggle-voice-btn');
    const suggestionBtns = document.querySelectorAll('.suggestion-btn');
        
    // Estado de la voz (F5-01): misma preferencia en /preguntas y /conversar
    let voiceEnabled = (function () {
        try { return localStorage.getItem('cirilo_voice_enabled') !== '0'; } catch (e) { return true; }
    })();
    let pendingController = null; // F5-05: respuesta en curso que se puede detener
    
// Inicializar el array historial vacío o con el prompt personalizado si existe
let historial = [];
let currentConversationId = null; // ID de la conversación activa en esta sesión
@if(isset($user) && !empty($user->prompt))
    historial.push({ pregunta: @json($user->prompt), respuesta: "" });
@endif

 function renderVoiceButton() {
        toggleVoiceBtn.innerHTML = voiceEnabled ? '<i class="fa-solid fa-volume-up"></i>' : '<i class="fa-solid fa-volume-mute"></i>';
    }
    renderVoiceButton();

        // Manejar el envío del formulario
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        // F5-05: mientras responde, el botón de enviar detiene la respuesta y el audio.
        if (pendingController) {
            pendingController.abort();
            if (audioPlayer) audioPlayer.pause();
            return;
        }
       
            const prompt = promptInput.value.trim();
            if (!prompt) return;
            
              let historialString = JSON.stringify(historial);
            processPrompt(prompt, historialString);
    });
    
        // Variable para controlar el estado del reconocimiento
    let isRecording = false;
    let currentRecognition = null;
    
        // Manejar el botón de micrófono
    micButton.addEventListener('click', function() {
        if (isRecording && currentRecognition) {
            // Si está grabando, detener
            stopRecognition();
        } else {
            // Si no está grabando, iniciar
            startRecognition();
        }
    });

        // Manejar el botón de activar/desactivar voz
        toggleVoiceBtn.addEventListener('click', function() {
            voiceEnabled = !voiceEnabled;
            try { localStorage.setItem('cirilo_voice_enabled', voiceEnabled ? '1' : '0'); } catch (e) {}
            renderVoiceButton();
            if (!voiceEnabled && audioPlayer) audioPlayer.pause();
            
            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: 'info',
                title: voiceEnabled ? 'Voz activada' : 'Voz desactivada',
                showConfirmButton: false,
                timer: 1500
            });
        });
        
        // Manejar los botones de sugerencias
        suggestionBtns.forEach(btn => {
            btn.addEventListener('click', function() {
                promptInput.value = this.textContent;
                promptInput.focus();
            });
        });
        
        function getCurrentTime() {
            const now = new Date();
            return now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        }

    function processPrompt(prompt, historialString) {
            // Limpiar la caja de texto después de enviar la pregunta
            promptInput.value = '';
            
            // Crear y mostrar el mensaje del usuario
            const userMessageContainer = document.createElement('div');
            userMessageContainer.classList.add('d-flex', 'justify-content-end', 'mb-3');
            
            const userMessage = document.createElement('div');
            userMessage.classList.add('message', 'user-message');
            userMessage.textContent = prompt;
            
            const userTime = document.createElement('div');
            userTime.classList.add('message-time');
            userTime.textContent = getCurrentTime();
            
            userMessageContainer.appendChild(userMessage);
            responsesContainer.appendChild(userMessageContainer);
            userMessage.appendChild(userTime);
            
            // Mostrar indicador de escritura
            const typingContainer = document.createElement('div');
            typingContainer.classList.add('typing-indicator');
            typingContainer.innerHTML = '<span></span><span></span><span></span>';
            responsesContainer.appendChild(typingContainer);
            
            // Scroll al final del contenedor
            responsesContainer.scrollTop = responsesContainer.scrollHeight;
            
        // Agregar la pregunta al historial
        historial.push({ pregunta: prompt });
            
            // Preparar el historial para enviar al backend
            const conversationHistory = historial.map((item, index) => {
                if (item.pregunta) {
                    return { role: 'user', content: item.pregunta };
                } else if (item.respuesta) {
                    return { role: 'assistant', content: item.respuesta };
                }
                return null;
            }).filter(msg => msg !== null);
            
            // F5: texto primero (streaming) y voz aparte. generateAudio: false porque el audio se
            // pide después y solo si la voz está activa; así el texto nunca espera al audio.
            const controller = new AbortController();
            startPending(controller);
            let preview = null;
            let savedAgenda = null;
            window.CiriloChat.send({
                prompt: prompt,
                history: conversationHistory,
                conversation_id: currentConversationId,
                generateAudio: false
            }, {
                onDelta: text => { preview = showPreview(preview, typingContainer, text); },
                onAgenda: agenda => { savedAgenda = agenda; }
            }, { signal: controller.signal })
            .then(data => ({ data }))
            .then(response => {
                    stopPending();
                    if (preview) preview.remove();
                    // Eliminar el indicador de escritura
                    responsesContainer.removeChild(typingContainer);
                
                // Extraer la respuesta de texto según la estructura de la respuesta
                let respuesta = '';
                if (response.data.choices && response.data.choices[0] && response.data.choices[0].message && response.data.choices[0].message.content) {
                    respuesta = response.data.choices[0].message.content;
                }

                // Validar que la respuesta no esté vacía
                if (!respuesta || respuesta.trim() === '') {
                    console.error('La respuesta está vacía');
                    return;
                }

                // Añadir la respuesta al historial
                historial.push({ respuesta: respuesta });

                // Guardar automáticamente la conversación si hay al menos una pregunta y respuesta
                if (historial.length >= 2) {
                    autoSaveConversation();
                }

                    // Formatear la respuesta (código, enlaces, etc.)
                    const formattedResponse = formatResponse(respuesta);
                    
                    // Crear y mostrar el mensaje del asistente
                    const assistantMessageContainer = document.createElement('div');
                    assistantMessageContainer.classList.add('d-flex', 'justify-content-start', 'mb-3');

                    const assistantMessage = document.createElement('div');
                    assistantMessage.classList.add('message', 'assistant-message');
                    assistantMessage.innerHTML = formattedResponse;
                    
                    const assistantTime = document.createElement('div');
                    assistantTime.classList.add('message-time');
                    assistantTime.textContent = getCurrentTime();
                    
                    assistantMessageContainer.appendChild(assistantMessage);
                    responsesContainer.appendChild(assistantMessageContainer);
                    assistantMessage.appendChild(assistantTime);
                    window.CiriloFeedback.mount(assistantMessage, response.data.interaction_id);
                    addListenButton(assistantMessage, respuesta);
                    renderAgendaCard(response.data.agenda, assistantMessage);
                    renderTaskCard(response.data.tasks, assistantMessage);
                    
                    // Scroll al final del contenedor
                    responsesContainer.scrollTop = responsesContainer.scrollHeight;
                
                    // Inicializar botones de copiar código
                    initCopyButtons();
                
                    // Reproducir audio si está habilitado
                    if (voiceEnabled) {
                        if (response.data.audioUrl) {
                            playAudio(response.data.audioUrl);
                        } else {
                            // Limpiar markdown, URLs y citas antes de enviar a TTS
                            const cleanText = respuesta
                                .replace(/\[([^\]]+)\]\([^)]+\)/g, '$1') // [texto](url) → texto
                                .replace(/https?:\/\/\S+/g, '')           // URLs sueltas
                                .replace(/#{1,6}\s+/g, '')                // headers ###
                                .replace(/\*{1,3}([^*]+)\*{1,3}/g, '$1') // **bold**, *italic*
                                .replace(/`[^`]+`/g, '')                  // inline code
                                .replace(/\s{2,}/g, ' ')
                                .trim()
                                .substring(0, 3000);                      // máx 3000 chars para TTS
                            if (cleanText) speakText(cleanText);
                        }
                    }

                    // Actualizar contador de tokens si está disponible
                    if (response.data.token_usage) {
                        updateTokenUsage(response.data.token_usage);
                    }
                    
                    // Mostrar advertencia si existe
                    if (response.data.warning) {
                        Swal.fire({
                            icon: 'warning',
                            title: 'Límite de memoria cercano',
                            text: response.data.warning.message,
                            footer: '<button class="btn btn-sm btn-primary" onclick="document.getElementById(\'clear-history-button\').click()">Iniciar nueva conversación</button>',
                            showConfirmButton: true
                        });
                    }
            })
            .catch(error => {
                    stopPending();
                    if (preview) preview.remove();
                    // Eliminar el indicador de escritura
                    if (typingContainer.parentNode) responsesContainer.removeChild(typingContainer);
                    if (error && error.aborted) {
                        showStoppedNotice(error.agenda || savedAgenda);
                        return;
                    }
                    
                console.error('Error:', error);
                    
                    // Crear y mostrar mensaje de error
                    const errorContainer = document.createElement('div');
                    errorContainer.classList.add('d-flex', 'justify-content-start', 'mb-3');
                    
                    const errorMessage = document.createElement('div');
                    errorMessage.classList.add('message', 'assistant-message', 'text-danger');
                    errorMessage.innerHTML = '<i class="fa-solid fa-exclamation-triangle me-2"></i>Lo siento, ha ocurrido un error al procesar tu solicitud. Por favor, intenta de nuevo más tarde.';
                    
                    errorContainer.appendChild(errorMessage);
                    responsesContainer.appendChild(errorContainer);
                    
                    // Scroll al final del contenedor
                    responsesContainer.scrollTop = responsesContainer.scrollHeight;
                });
        }
        
        function updateTokenUsage(usage) {
            // Buscar o crear el contenedor de uso de tokens
            let tokenContainer = document.getElementById('token-usage-container');
            if (!tokenContainer) {
                tokenContainer = document.createElement('div');
                tokenContainer.id = 'token-usage-container';
                tokenContainer.className = 'mt-3 p-2 bg-light rounded small text-muted';
                document.querySelector('.user-info-content').appendChild(tokenContainer);
            }
            
            const percentage = usage.percentage;
            const colorClass = percentage > 80 ? 'bg-danger' : (percentage > 50 ? 'bg-warning' : 'bg-success');
            
            tokenContainer.innerHTML = `
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <span>Memoria de conversación:</span>
                    <span class="fw-bold">${percentage}%</span>
                </div>
                <div class="progress" style="height: 6px;">
                    <div class="progress-bar ${colorClass}" role="progressbar" style="width: ${percentage}%" aria-valuenow="${percentage}" aria-valuemin="0" aria-valuemax="100"></div>
                </div>
                <div class="text-end mt-1" style="font-size: 0.75rem;">
                    ${usage.total.toLocaleString()} / ${usage.limit.toLocaleString()} tokens
                </div>
            `;
        }
        
        function formatResponse(text) {
            return window.CiriloContent.renderMarkdown(text);
        }
        
        function initCopyButtons() {
            document.querySelectorAll('.copy-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const codeBlock = this.nextElementSibling;
                    const code = codeBlock.textContent;
                    
                    navigator.clipboard.writeText(code).then(() => {
                        const originalText = this.textContent;
                        this.textContent = '¡Copiado!';
                        this.style.backgroundColor = '#4caf50';
                        
                        setTimeout(() => {
                            this.textContent = originalText;
                            this.style.backgroundColor = '';
                        }, 2000);
            });
                });
            });
        }
        
    async function speakText(text) {
        try {
            // Mostrar indicador de carga de audio
            const audioToast = Swal.mixin({
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timerProgressBar: true,
                didOpen: (toast) => {
                    toast.addEventListener('mouseenter', Swal.stopTimer)
                    toast.addEventListener('mouseleave', Swal.resumeTimer)
                }
            });
            
            audioToast.fire({
                icon: 'info',
                title: 'Generando audio...'
            });
            
            const response = await fetch('/text-to-speech', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({ text: text })
            });
    
            const responseData = await response.json();
    
            if (response.ok && responseData.audioUrl) {
                // Cerrar toast anterior
                Swal.close();
                playAudio(responseData.audioUrl);
            } else {
                console.error('Error al generar el audio:', responseData.error);
                audioToast.fire({
                    icon: 'warning',
                    title: 'No se pudo generar el audio',
                    timer: 3000
                });
            }
        } catch (error) {
            console.error('Error en la solicitud de texto a voz:', error);
            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: 'error',
                title: 'Error de conexión al generar audio',
                showConfirmButton: false,
                timer: 3000
            });
        }
    }
    
    function playAudio(audioUrl) {
        if (!audioPlayer) {
            console.error('Elemento de audio no encontrado en el DOM.');
            return;
        }
        // Forzar HTTPS para evitar Mixed Content
        if (window.location.protocol === 'https:') {
            audioUrl = audioUrl.replace(/^http:\/\//i, 'https://');
        }
        audioPlayer.src = audioUrl;
        audioPlayer.load();
        audioPlayer.addEventListener('canplay', function handler() {
            audioPlayer.removeEventListener('canplay', handler);
            audioPlayer.play().catch(function(e) {
                console.error('Error al reproducir el audio:', audioUrl, e.message);
            });
        });
    }
    
        function startRecognition() {
            // Prevenir múltiples inicios
            if (isRecording) {
                console.log('Ya hay un reconocimiento en curso');
                return;
            }
            
            if ('webkitSpeechRecognition' in window || 'SpeechRecognition' in window) {
                const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
                const recognition = new SpeechRecognition();
                currentRecognition = recognition;
                
                // Configuración mejorada
                recognition.lang = 'es-ES';
                recognition.continuous = true;
                recognition.interimResults = true;
                recognition.maxAlternatives = 1;
                let silenceTimer = null;
                let accumulatedTranscript = '';
                
                // Verificar si estamos en HTTPS
                if (window.location.protocol !== 'https:' && window.location.hostname !== 'localhost') {
                    console.warn('El reconocimiento de voz funciona mejor en HTTPS. Protocolo actual:', window.location.protocol);
                }
                
                // Cambiar el botón para indicar que está grabando
                micButton.innerHTML = '<i class="fa-solid fa-stop"></i>';
                micButton.classList.remove('btn-outline-secondary');
                micButton.classList.add('btn-danger', 'pulse-recording');
                micButton.title = 'Detener grabación';
                
                try {
                    recognition.start();
                    isRecording = true;
                    console.log('Reconocimiento de voz iniciado');
                } catch (startError) {
                    console.error('Error al iniciar el reconocimiento de voz:', startError);
                    isRecording = false;
                    currentRecognition = null;
                    resetMicButton();
                    showRecognitionError('Error al iniciar el reconocimiento de voz. Intenta recargar la página.');
                    return;
                }
    
                recognition.onresult = function(event) {
                    try {
                        // Acumular todos los segmentos finalizados
                        let finalTranscript = '';
                        for (let i = 0; i < event.results.length; i++) {
                            if (event.results[i].isFinal) {
                                finalTranscript += event.results[i][0].transcript + ' ';
                            }
                        }
                        if (finalTranscript.trim()) {
                            accumulatedTranscript = finalTranscript.trim();
                            promptInput.value = accumulatedTranscript;
                        }

                        // Reiniciar el timer de silencio cada vez que llega audio
                        clearTimeout(silenceTimer);
                        silenceTimer = setTimeout(function() {
                            try { recognition.stop(); } catch (e) {}
                        }, 1500); // 1.5 segundos de silencio para finalizar

                    } catch (resultError) {
                        console.error('Error al procesar resultado de voz:', resultError);
                    }
                };

                recognition.onspeechend = function() {
                    // No detenemos aquí — dejamos que el silenceTimer lo haga
                    console.log('Pausa detectada, esperando silenceTimer...');
                };
    
                recognition.onend = function() {
                    console.log('Reconocimiento de voz finalizado (end event)');
                    clearTimeout(silenceTimer);
                    isRecording = false;
                    currentRecognition = null;
                    resetMicButton();

                    const transcript = accumulatedTranscript.trim();
                    accumulatedTranscript = '';

                    if (!transcript) return;

                    promptInput.value = transcript;
                    Swal.fire({
                        title: '¿Enviar este mensaje?',
                        html: `<div class="text-start p-3 border rounded bg-light"><strong>Mensaje:</strong> "${transcript}"</div>`,
                        icon: 'question',
                        showCancelButton: true,
                        confirmButtonColor: '#3085d6',
                        cancelButtonColor: '#6c757d',
                        confirmButtonText: 'Enviar',
                        cancelButtonText: 'Editar',
                        focusCancel: true
                    }).then((result) => {
                        if (result.isConfirmed) {
                            processPrompt(transcript, JSON.stringify(historial));
                        }
                    });
                };
    
                // Detectar si estamos en Edge o no Chrome para mostrar mensaje especial
                const isEdge = navigator.userAgent.indexOf("Edg") !== -1;
                const isChrome = navigator.userAgent.indexOf("Chrome") !== -1 && !isEdge;
                
                // Si no estamos en Chrome, mostrar mensaje una sola vez y detener
                if (!isChrome) {
                    resetMicButton();
                    // Usar un flag en sessionStorage para mostrar el mensaje solo una vez por sesión
                    if (!sessionStorage.getItem('voiceRecognitionWarningShown')) {
                        sessionStorage.setItem('voiceRecognitionWarningShown', 'true');
                        Swal.fire({
                            icon: 'warning',
                            title: 'Navegador no óptimo',
                            html: '<p>Has intentado usar el reconocimiento de voz en un navegador que no es Chrome.</p><p><strong>Esta función solo funciona correctamente en Google Chrome.</strong></p><p>Por favor, utiliza Chrome para disfrutar de todas las funcionalidades de la aplicación.</p>',
                            confirmButtonText: 'Entendido'
                        });
                    }
                    return; // Detener el reconocimiento inmediatamente
                }
                
                recognition.onerror = function(event) {
                    console.error('Error de reconocimiento de voz:', event.error, event);
                    isRecording = false;
                    currentRecognition = null;
                    resetMicButton();
                    
                    // Mostrar mensaje de error según el tipo
                    let errorMessage = 'Ocurrió un error con el reconocimiento de voz.';
                    let showAlert = true;
                    
                    switch(event.error) {
                        case 'not-allowed':
                            errorMessage = 'Permiso para usar el micrófono denegado. Por favor, permite el acceso al micrófono en la configuración del navegador.';
                            break;
                        case 'network':
                            errorMessage = 'Error de red con el reconocimiento de voz. Verifica tu conexión a internet.';
                            break;
                        case 'no-speech':
                            errorMessage = 'No se detectó voz. Por favor, habla claramente al micrófono cuando el icono esté activo.';
                            break;
                        case 'aborted':
                            errorMessage = 'Reconocimiento de voz cancelado.';
                            showAlert = false; // No mostrar alerta para cancelaciones
                            break;
                        case 'audio-capture':
                            errorMessage = 'No se pudo capturar audio. Verifica que tu micrófono esté conectado y funcionando correctamente.';
                            break;
                        case 'service-not-allowed':
                            errorMessage = 'El servicio de reconocimiento de voz no está permitido.';
                            break;
                        default:
                            errorMessage = `Error de reconocimiento de voz: ${event.error}. Intenta recargar la página.`;
                    }
                    
                    if (showAlert) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error de reconocimiento de voz',
                            text: errorMessage
                        });
                    } else {
                        console.log(errorMessage);
                    }
                };
            } else {
                Swal.fire({
                    icon: 'warning',
                    title: 'Función no soportada',
                    html: '<p>El reconocimiento de voz no está soportado en este navegador.</p><p><strong>Esta función solo funciona correctamente en Google Chrome.</strong></p><p>Por favor, utiliza Chrome para disfrutar de todas las funcionalidades de la aplicación.</p>'
                });
            }
        }
        
        function stopRecognition() {
            if (currentRecognition && isRecording) {
                try {
                    currentRecognition.stop();
                    console.log('Reconocimiento detenido manualmente');
                } catch (stopError) {
                    console.error('Error al detener manualmente:', stopError);
                }
                isRecording = false;
                currentRecognition = null;
                resetMicButton();
            }
        }
        
        // Función auxiliar para resetear el botón
        function resetMicButton() {
            micButton.innerHTML = '<i class="fa-solid fa-microphone"></i>';
            micButton.classList.remove('btn-danger', 'pulse-recording');
            micButton.classList.add('btn-outline-secondary');
            micButton.title = 'Reconocimiento de voz';
        }
        
        // Función auxiliar para mostrar errores
        function showRecognitionError(message) {
            Swal.fire({
                icon: 'error',
                title: 'Error de reconocimiento de voz',
                text: message
            });
        }
            
        function limpiaHistorial() {
            Swal.fire({
                title: '¿Estás seguro?',
                text: 'Se eliminará todo el historial de conversaciones guardadas',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Sí, limpiar',
                cancelButtonText: 'Cancelar'
            }).then(async (result) => {
                if (result.isConfirmed) {
                    // Limpiar sesión actual en memoria
                    historial = [];
                    currentConversationId = null;
                    @if(isset($user) && !empty($user->prompt))
                        historial.push({ pregunta: @json($user->prompt), respuesta: "" });
                    @endif

                    responsesContainer.innerHTML = `
                        <div class="text-center py-5">
                            <i class="fa-solid fa-robot fa-4x text-muted mb-3"></i>
                            <p class="text-muted">¡Hola! Estoy aquí para ayudarte. Escribe tu pregunta abajo.</p>
                        </div>
                    `;
                    promptInput.value = '';

                    const tokenContainer = document.getElementById('token-usage-container');
                    if (tokenContainer) tokenContainer.remove();

                    // Borrar conversaciones guardadas en DB
                    try {
                        await fetch('/conversations/clear', {
                            method: 'DELETE',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                            }
                        });
                    } catch (e) {
                        console.error('Error al limpiar conversaciones en servidor:', e);
                    }

                    // Refrescar sidebar de historial
                    loadConversationHistory();

                    Swal.fire({
                        icon: 'success',
                        title: 'Historial limpiado',
                        showConfirmButton: false,
                        timer: 1500
                    });
                }
            });
        }
    
        clearHistoryButton.addEventListener('click', limpiaHistorial);

        document.getElementById('new-conversation-btn').addEventListener('click', limpiaHistorial);
        
        // Cargar historial de conversaciones en el panel lateral
        function loadConversationHistory() {
            axios.get('/conversations')
                .then(response => {
                    const container = document.getElementById('history-list');
                    const conversations = response.data.conversations || [];

                    if (conversations.length === 0) {
                        container.innerHTML = '<p class="text-muted small text-center py-2 mb-0">Sin conversaciones guardadas.</p>';
                        return;
                    }

                    const recent = conversations.slice(0, 6);
                    container.innerHTML = recent.map(conv => {
                        const date = new Date(conv.updated_at).toLocaleDateString('es', { day: '2-digit', month: 'short' });
                        const title = window.CiriloContent.escapeHtml(conv.title || 'Conversación sin título');
                        return `<div class="d-flex align-items-center border-bottom py-1 px-1 history-item-row" style="overflow:hidden">
                                    <a href="/historial?open=${conv.id}"
                                       class="d-flex justify-content-between align-items-center text-decoration-none text-dark flex-grow-1 me-1 py-1"
                                       style="min-width:0"
                                       title="${title}">
                                        <span class="text-truncate me-2 small">${title}</span>
                                        <span class="text-muted" style="font-size:0.7rem;white-space:nowrap">${date}</span>
                                    </a>
                                    <button class="btn btn-link btn-sm p-0 text-danger delete-conv-btn"
                                            data-id="${conv.id}" data-title="${title}"
                                            title="Eliminar conversación" style="line-height:1;font-size:0.75rem;">
                                        <i class="fa-solid fa-xmark"></i>
                                    </button>
                                </div>`;
                    }).join('');

                    // Listeners para botones de eliminar individual
                    container.querySelectorAll('.delete-conv-btn').forEach(btn => {
                        btn.addEventListener('click', function(e) {
                            e.preventDefault();
                            e.stopPropagation();
                            const id = this.dataset.id;
                            const title = this.dataset.title;
                            Swal.fire({
                                title: '¿Eliminar conversación?',
                                text: `"${title}"`,
                                icon: 'warning',
                                showCancelButton: true,
                                confirmButtonColor: '#d33',
                                cancelButtonColor: '#6c757d',
                                confirmButtonText: 'Sí, eliminar',
                                cancelButtonText: 'Cancelar'
                            }).then(async result => {
                                if (result.isConfirmed) {
                                    try {
                                        const res = await fetch(`/conversaciones/${id}`, {
                                            method: 'DELETE',
                                            headers: {
                                                'Accept': 'application/json',
                                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                                            }
                                        });
                                        loadConversationHistory();
                                    } catch (err) {
                                        console.error('Error al eliminar conversación:', err);
                                    }
                                }
                            });
                        });
                    });
                })
                .catch(() => {
                    document.getElementById('history-list').innerHTML =
                        '<p class="text-muted small text-center py-2 mb-0">No se pudo cargar.</p>';
                });
        }

        loadConversationHistory();

        // F5-03: vista previa del texto mientras llega (texto plano; al terminar se muestra con formato).
        function showPreview(preview, typingContainer, text) {
            if (!preview) {
                const container = document.createElement('div');
                container.className = 'd-flex justify-content-start mb-3';
                const bubble = document.createElement('div');
                bubble.className = 'message assistant-message';
                bubble.style.whiteSpace = 'pre-wrap';
                container.appendChild(bubble);
                responsesContainer.insertBefore(container, typingContainer);
                typingContainer.style.display = 'none';
                preview = container;
            }
            preview.firstChild.textContent = text;
            responsesContainer.scrollTop = responsesContainer.scrollHeight;
            return preview;
        }

        function startPending(controller) {
            pendingController = controller;
            const button = form.querySelector('button[type="submit"]');
            button.dataset.label = button.innerHTML;
            button.innerHTML = '<i class="fa-solid fa-stop"></i>';
            button.title = 'Detener respuesta';
        }

        function stopPending() {
            pendingController = null;
            const button = form.querySelector('button[type="submit"]');
            if (button.dataset.label) button.innerHTML = button.dataset.label;
            button.title = '';
        }

        // F5-05: detener no deshace lo que ya se guardó; se avisa con la tarjeta del evento.
        function showStoppedNotice(agenda) {
            const container = document.createElement('div');
            container.className = 'd-flex justify-content-start mb-3';
            const bubble = document.createElement('div');
            bubble.className = 'message assistant-message text-muted';
            const saved = agenda && ['created', 'replayed', 'updated', 'cancelled'].includes(agenda.status);
            bubble.textContent = saved
                ? 'Detuviste la respuesta, pero la acción de agenda ya se había guardado:'
                : 'Respuesta detenida.';
            container.appendChild(bubble);
            responsesContainer.appendChild(container);
            if (saved) renderAgendaCard(agenda, bubble);
            responsesContainer.scrollTop = responsesContainer.scrollHeight;
        }

        // F5-02: escuchar una respuesta bajo demanda, aunque la voz automática esté apagada.
        function addListenButton(container, text) {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'btn btn-sm btn-link p-0 me-2';
            button.innerHTML = '<i class="fa-solid fa-volume-up"></i> Escuchar';
            button.addEventListener('click', () => speakText(cleanForSpeech(text)));
            container.appendChild(button);
        }

        function cleanForSpeech(text) {
            return text
                .replace(/\[([^\]]+)\]\([^)]+\)/g, '$1')
                .replace(/https?:\/\/\S+/g, '')
                .replace(/#{1,6}\s+/g, '')
                .replace(/\*{1,3}([^*]+)\*{1,3}/g, '$1')
                .replace(/`[^`]+`/g, '')
                .replace(/\s{2,}/g, ' ')
                .trim()
                .substring(0, 3000);
        }

        // F6-06: tarjeta del pendiente guardado o cambiado, con enlace para completarlo o editarlo en /hoy.
        function renderTaskCard(tasks, container) {
            const labels = { created: 'Pendiente guardado', duplicate: 'Ya estaba en tus pendientes', updated: 'Pendiente actualizado' };
            if (!tasks || !labels[tasks.status] || !(tasks.items || []).length) return;
            const card = document.createElement('div');
            card.className = 'card border-0 bg-light mt-2';
            const body = document.createElement('div');
            body.className = 'card-body py-2 px-3 small';
            const heading = document.createElement('div');
            heading.className = 'fw-semibold mb-1';
            heading.textContent = labels[tasks.status];
            body.appendChild(heading);
            tasks.items.forEach(task => {
                const row = document.createElement('div');
                row.className = 'd-flex justify-content-between align-items-center gap-2';
                const text = document.createElement('span');
                const states = { open: '', done: ' · hecho', postponed: ' · pospuesto', dismissed: ' · descartado' };
                text.textContent = task.title + (task.due_date ? ' · fecha límite ' + task.due_date : ' · sin fecha') + (states[task.status] || '');
                const link = document.createElement('a');
                link.href = '/hoy#tarea-' + encodeURIComponent(task.id);
                link.className = 'text-nowrap';
                link.textContent = 'Ver en Hoy';
                row.append(text, link);
                body.appendChild(row);
            });
            card.appendChild(body);
            container.appendChild(card);
        }

        // F2-07: tarjeta con lo que realmente quedó en la agenda (datos del servidor, no del texto del modelo).
        function renderAgendaCard(agenda, container) {
            if (!agenda) return;
            const labels = { created: 'Agendado', replayed: 'Agendado', duplicate: 'Ya estaba en tu agenda', updated: 'Actualizado', cancelled: 'Cancelado' };
            const events = labels[agenda.status] ? (agenda.events || []) : (agenda.status === 'needs_clarification' ? (agenda.candidates || []) : []);
            if (!events.length) return;

            const card = document.createElement('div');
            card.className = 'card border-0 bg-light mt-2';
            const body = document.createElement('div');
            body.className = 'card-body py-2 px-3 small';
            const heading = document.createElement('div');
            heading.className = 'fw-semibold mb-1';
            heading.textContent = labels[agenda.status] || '¿Cuál de estos?';
            body.appendChild(heading);

            events.forEach(ev => {
                const start = new Date(ev.start);
                const row = document.createElement('div');
                row.className = 'd-flex justify-content-between align-items-center gap-2';
                const text = document.createElement('span');
                const when = start.toLocaleDateString('es-GT', { weekday: 'long', day: 'numeric', month: 'long' })
                    + (ev.all_day ? ' · todo el día' : ' · ' + start.toLocaleTimeString('es-GT', { hour: '2-digit', minute: '2-digit' }));
                text.textContent = ev.title + ' — ' + when + (ev.location ? ' · ' + ev.location : '')
                    + (agenda.count > 1 && labels[agenda.status] ? ' (' + agenda.count + ' eventos en la serie)' : '');
                const link = document.createElement('a');
                const day = ev.start.substring(0, 10);
                link.href = '/agenda?fecha=' + encodeURIComponent(day) + '&evento=' + encodeURIComponent(ev.id);
                link.className = 'text-nowrap';
                link.textContent = agenda.status === 'cancelled' ? 'Ver' : 'Ver o editar';
                row.append(text, link);
                body.appendChild(row);
            });
            card.appendChild(body);
            container.appendChild(card);
        }

        // Función para guardar la conversación automáticamente
        function autoSaveConversation() {
            // Solo guardar si hay al menos una pregunta y una respuesta
            if (historial.length < 2) return;

            // Preparar los mensajes para guardar
            const messages = [];
            let title = '';

            for (let i = 0; i < historial.length; i++) {
                if (historial[i].pregunta) {
                    // Usar la primera pregunta como título
                    if (!title) {
                        title = historial[i].pregunta.substring(0, 50) + (historial[i].pregunta.length > 50 ? '...' : '');
                    }
                    messages.push({
                        role: 'user',
                        content: historial[i].pregunta,
                        timestamp: new Date().toISOString()
                    });
                } else if (historial[i].respuesta) {
                    messages.push({
                        role: 'assistant',
                        content: historial[i].respuesta,
                        timestamp: new Date().toISOString()
                    });
                }
            }

            const content = JSON.stringify({ messages: messages });

            if (currentConversationId) {
                // Actualizar conversación existente en lugar de crear una nueva
                axios.put('/conversaciones/' + currentConversationId, { content: content })
                    .catch(error => {
                        console.error('Error al actualizar la conversación:', error);
                        queueConversationSync({ conversationId: currentConversationId, content });
                    });
            } else {
                // Primera vez: crear la conversación y guardar su ID
                axios.post('/conversaciones', {
                    title: title,
                    type: 'chat',
                    content: content
                })
                .then(response => {
                    if (response.data.conversation_id) {
                        currentConversationId = response.data.conversation_id;
                    }
                    loadConversationHistory();
                })
                .catch(error => {
                    console.error('Error al guardar la conversación:', error);
                    queueConversationSync({ title, content });
                });
            }
        }

        // ── Background Sync: encolar conversación fallida para reintentar al volver la conexión ──
        async function queueConversationSync(data) {
            if (!('serviceWorker' in navigator) || !('SyncManager' in window)) return;
            try {
                const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
                const db = await openSwDB();
                const tx = db.transaction('pending_conversations', 'readwrite');
                tx.objectStore('pending_conversations').add({ ...data, csrf, timestamp: Date.now() });
                const sw = await navigator.serviceWorker.ready;
                await sw.sync.register('save-conversation');
                console.log('Conversación encolada para Background Sync');
            } catch (e) {
                console.warn('Background Sync no disponible:', e);
            }
        }

        function openSwDB() {
            return new Promise((resolve, reject) => {
                const req = indexedDB.open('asistente-sw', 1);
                req.onupgradeneeded = e => {
                    const db = e.target.result;
                    if (!db.objectStoreNames.contains('pending_conversations')) {
                        db.createObjectStore('pending_conversations', { keyPath: 'id', autoIncrement: true });
                    }
                };
                req.onsuccess = e => resolve(e.target.result);
                req.onerror = e => reject(e.target.error);
            });
        }
    });
    </script>
    @endpush
