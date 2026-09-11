@extends('../layout.app')
@section('title', 'Generador de Imágenes')
@section('css')
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
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
        background: linear-gradient(135deg, #4e73df 0%, #36b9cc 100%);
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
    
    .user-info-card {
        border-radius: 15px;
        overflow: hidden;
        transition: all 0.3s ease;
        box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        margin-bottom: 20px;
    }
    
    .user-info-header {
        background: linear-gradient(135deg, #4e73df 0%, #36b9cc 100%);
        color: white;
        padding: 15px 20px;
    }
    
    .user-info-content {
        padding: 15px;
        background-color: #fff;
    }
    
    .image-card {
        border-radius: 15px;
        overflow: hidden;
        transition: all 0.3s ease;
        box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        margin-bottom: 20px;
    }
    
    .image-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 15px 30px rgba(0,0,0,0.12);
    }
    
    .image-card img {
        width: 100%;
        height: auto;
        object-fit: contain;
    }
    
    .image-card-footer {
        padding: 15px;
        background-color: #f8f9fa;
        border-top: 1px solid #e9ecef;
    }
    
    .prompt-input {
        border-radius: 25px;
        padding: 12px 20px;
        border: 1px solid #ced4da;
        transition: all 0.3s ease;
    }
    
    .prompt-input:focus {
        box-shadow: 0 0 0 0.25rem rgba(78, 115, 223, 0.25);
        border-color: #4e73df;
    }
    
    .btn-record {
        border-radius: 50%;
        width: 45px;
        height: 45px;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.3s ease;
    }
    
    .btn-record:hover {
        transform: scale(1.1);
    }
    
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
</style>
@endsection

@section('content')
<div class="container mt-4">
    <div class="row">
        <div class="col-12 mb-4">
            <h1 class="display-5 fw-bold text-primary"><i class="fa-solid fa-image me-2"></i>Generador de Imágenes</h1>
            <p class="lead">Describe la imagen que deseas crear y nuestro sistema la generará usando DALL-E 3.</p>
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
                            <div class="d-flex align-items-center">
                                <i class="fa-solid fa-robot text-primary me-2"></i>
                                <span>Proveedor: <span class="fw-bold">OpenAI (DALL-E 3)</span></span>
                            </div>
                        </div>
                        
                        <div class="alert alert-info mt-3">
                            <i class="fas fa-wand-magic-sparkles me-2"></i> {{ $user->name }}, puedes pedir imágenes de lo que quieras imaginar: videojuegos, animales, robots, superhéroes…
                        </div>
                    @endif
                </div>
            </div>
            
            <!-- Límite diario de imágenes -->
            @if(isset($role) && $role !== 'admin')
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body">
                    <h6 class="card-title mb-3">
                        <i class="fa-solid fa-chart-pie text-info me-2"></i>
                        Límite Diario
                    </h6>
                    <div class="mb-3">
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Imágenes usadas:</span>
                            <span class="fw-bold">{{ $imagesUsed ?? 0 }} / {{ $dailyLimit ?? 4 }}</span>
                        </div>
                        <div class="progress" style="height: 20px;">
                            @php
                                $percentage = isset($imagesUsed) && isset($dailyLimit) && $dailyLimit > 0 
                                    ? ($imagesUsed / $dailyLimit) * 100 
                                    : 0;
                                $progressColor = $percentage < 50 ? 'bg-success' : ($percentage < 75 ? 'bg-warning' : 'bg-danger');
                            @endphp
                            <div class="progress-bar {{ $progressColor }}" 
                                 role="progressbar" 
                                 style="width: {{ $percentage }}%"
                                 aria-valuenow="{{ $imagesUsed ?? 0 }}" 
                                 aria-valuemin="0" 
                                 aria-valuemax="{{ $dailyLimit ?? 4 }}">
                                {{ round($percentage) }}%
                            </div>
                        </div>
                    </div>
                    <div class="alert alert-info mb-0 py-2 px-3">
                        <small>
                            <i class="fa-solid fa-info-circle me-1"></i>
                            @if(isset($imagesRemaining) && $imagesRemaining > 0)
                                Te quedan <strong>{{ $imagesRemaining }}</strong> {{ $imagesRemaining == 1 ? 'imagen' : 'imágenes' }} hoy
                            @else
                                Has alcanzado el límite diario. Se reinicia mañana.
                            @endif
                        </small>
                    </div>
                </div>
            </div>
            @endif
            
            <div class="d-grid gap-2">
                <button type="button" class="btn btn-outline-danger" id="clear-history-button">
                    <i class="fa-solid fa-trash me-2"></i>Limpiar Historial
                </button>
            </div>
        </div>
        
        <div class="col-lg-9">
            <div class="chat-container">
                <div class="chat-header">
                    <h4 class="mb-0"><i class="fa-solid fa-wand-magic-sparkles me-2"></i>Generador de Imágenes con DALL-E 3</h4>
                </div>
                <div class="chat-content" id="responses-container">
                    <div class="text-center p-5">
                        <i class="fa-solid fa-image fa-4x text-primary mb-3"></i>
                        <h3>Generador de Imágenes con IA</h3>
                        <p class="text-muted">Describe la imagen que deseas crear y la IA la generará para ti.</p>
                    </div>
                </div>
                <div class="chat-input-container">
                    <form id="generate-image-form" method="POST">
                        @csrf
                        <div class="input-group">
                            <input type="text" class="form-control prompt-input" id="prompt" name="prompt" placeholder="Describe la imagen que deseas crear..." required>
                            <button type="button" id="mic-button" class="btn btn-outline-secondary btn-record">
                                <i class="bi bi-mic-fill"></i>
                            </button>
                            <button type="submit" class="btn btn-primary px-4">
                                <i class="fa-solid fa-wand-magic-sparkles me-2"></i>Generar
                            </button>
                        </div>
                        <div class="text-muted small mt-2">
                            <i class="fa-solid fa-info-circle me-1"></i> Nota: OpenAI puede rechazar solicitudes que no cumplan con sus políticas de uso. Si una imagen no puede generarse, se te mostrará un mensaje explicativo.
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/clipboard/dist/clipboard.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const form = document.getElementById('generate-image-form');
        const micButton = document.getElementById('mic-button');
        const promptInput = document.getElementById('prompt');
        const responsesContainer = document.getElementById('responses-container');
        const clearHistoryButton = document.getElementById('clear-history-button');
        let isRecording = false;

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            const promptValue = promptInput.value;
            if (promptValue.trim() !== '') {
                processPrompt(promptValue);
            }
        });

        micButton.addEventListener('click', function () {
            if (!isRecording) {
                startRecognition();
                micButton.classList.add('btn-danger', 'pulse-recording');
                micButton.classList.remove('btn-outline-secondary');
                isRecording = true;
            } else {
                // Si ya está grabando, detener la grabación
                isRecording = false;
                micButton.classList.remove('btn-danger', 'pulse-recording');
                micButton.classList.add('btn-outline-secondary');
            }
        });

        clearHistoryButton.addEventListener('click', function() {
            // Restablecer el contenedor de respuestas a su estado inicial
            responsesContainer.innerHTML = `
                <div class="text-center p-5">
                    <i class="fa-solid fa-image fa-4x text-primary mb-3"></i>
                    <h3>Generador de Imágenes con IA</h3>
                    <p class="text-muted">Describe la imagen que deseas crear y la IA la generará para ti.</p>
                </div>
            `;
        });

        function processPrompt(prompt) {
            console.log('Enviando solicitud con prompt:', prompt);
            
            // Mostrar mensaje del usuario
            const userMessage = document.createElement('div');
            userMessage.classList.add('message', 'user-message');
            userMessage.innerHTML = `
                <div>${prompt}</div>
                <div class="message-time">${getCurrentTime()}</div>
            `;
            responsesContainer.appendChild(userMessage);
            
            // Mostrar indicador de carga
            const loadingIndicator = document.createElement('div');
            loadingIndicator.classList.add('message', 'assistant-message');
            loadingIndicator.innerHTML = `
                <div>Generando imagen, por favor espera...</div>
                <div class="d-flex align-items-center mt-2">
                    <div class="spinner-border spinner-border-sm text-primary me-2" role="status"></div>
                    <span>Esto puede tomar unos segundos</span>
                </div>
            `;
            responsesContainer.appendChild(loadingIndicator);
            
            // Hacer scroll hacia abajo
            responsesContainer.scrollTop = responsesContainer.scrollHeight;

            axios({
                method: 'post',
                url: '/generate-image',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                data: {
                    prompt: prompt
                }
            })
            .then(response => {
                console.log('Respuesta recibida:', response);
                
                // Eliminar el indicador de carga
                responsesContainer.removeChild(loadingIndicator);

                const imageUrl = response.data.image_url;
                console.log('URL de la imagen generada:', imageUrl);

                // Crear tarjeta de imagen
                const imageCard = document.createElement('div');
                imageCard.classList.add('image-card', 'mb-4');
                imageCard.innerHTML = `
                    <img src="${imageUrl}" alt="Imagen generada" class="img-fluid">
                    <div class="image-card-footer d-flex justify-content-between align-items-center">
                        <span class="text-muted small">${getCurrentTime()}</span>
                        <a href="${imageUrl}" download="dalle_image.png" target="_blank" class="btn btn-sm btn-success">
                            <i class="fa-solid fa-download me-1"></i>Descargar
                        </a>
                    </div>
                `;
                responsesContainer.appendChild(imageCard);

                // Hacer scroll hacia abajo
                responsesContainer.scrollTop = responsesContainer.scrollHeight;

                promptInput.value = '';
            })
            .catch(error => {
                console.error('Error durante la solicitud:', error);
                
                // Eliminar el indicador de carga
                responsesContainer.removeChild(loadingIndicator);
                
                // Preparar mensaje de error
                let errorMsg = 'Hubo un problema al generar la imagen.';
                let isLimitError = false;
                
                if (error.response) {
                    // Error 429 = Límite alcanzado
                    if (error.response.status === 429) {
                        isLimitError = true;
                        errorMsg = error.response.data.message || error.response.data.error || 'Has alcanzado el límite diario de generación de imágenes.';
                        
                        // Recargar la página para actualizar el contador
                        setTimeout(() => {
                            location.reload();
                        }, 3000);
                    } else {
                        errorMsg = error.response.data.error || errorMsg;
                    }
                } else if (error.message && error.message.includes('timeout')) {
                    errorMsg = 'La solicitud ha tardado demasiado tiempo. Por favor, intenta con un prompt más simple o inténtalo de nuevo más tarde.';
                }
                
                // Mostrar mensaje de error
                const errorMessage = document.createElement('div');
                errorMessage.classList.add('message', 'assistant-message');
                errorMessage.innerHTML = `
                    <div class="${isLimitError ? 'text-warning' : 'text-danger'}">
                        <i class="fa-solid fa-${isLimitError ? 'hourglass-end' : 'circle-exclamation'} me-2"></i>
                        ${isLimitError ? '<strong>Límite Alcanzado:</strong>' : 'Error:'} ${errorMsg}
                    </div>
                    ${isLimitError ? '<div class="mt-2"><small class="text-muted">La página se recargará en 3 segundos...</small></div>' : ''}
                    <div class="message-time">${getCurrentTime()}</div>
                `;
                responsesContainer.appendChild(errorMessage);
                
                // Hacer scroll hacia abajo
                responsesContainer.scrollTop = responsesContainer.scrollHeight;
            });
        }

        function startRecognition() {
            if ('webkitSpeechRecognition' in window || 'SpeechRecognition' in window) {
                const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
                const recognition = new SpeechRecognition();
                recognition.lang = 'es-ES';
                recognition.start();

                recognition.onresult = function (event) {
                    const transcript = event.results[0][0].transcript;
                    promptInput.value = transcript;
                    
                    // Restaurar el botón del micrófono
                    isRecording = false;
                    micButton.classList.remove('btn-danger', 'pulse-recording');
                    micButton.classList.add('btn-outline-secondary');
                };

                recognition.onspeechend = function () {
                    recognition.stop();
                    
                    // Restaurar el botón del micrófono
                    isRecording = false;
                    micButton.classList.remove('btn-danger', 'pulse-recording');
                    micButton.classList.add('btn-outline-secondary');
                };

                recognition.onerror = function (event) {
                    if (event.error === 'not-allowed') {
                        alert('Permiso para usar el micrófono denegado. Por favor, permita el acceso al micrófono.');
                    }
                    console.error('Error de reconocimiento:', event.error);
                    
                    // Restaurar el botón del micrófono
                    isRecording = false;
                    micButton.classList.remove('btn-danger', 'pulse-recording');
                    micButton.classList.add('btn-outline-secondary');
                };
            } else {
                console.error('Reconocimiento de voz no soportado en este navegador.');
                alert('Reconocimiento de voz no soportado en este navegador. Por favor, use un navegador compatible como Google Chrome.');
            }
        }
        
        function getCurrentTime() {
            const now = new Date();
            const hours = now.getHours().toString().padStart(2, '0');
            const minutes = now.getMinutes().toString().padStart(2, '0');
            return `${hours}:${minutes}`;
        }
    });
</script>
@endpush