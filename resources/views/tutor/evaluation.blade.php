@extends('layout.app')

@section('title','Evaluación Inicial')

@section('content')
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />
<style>
    .step { display:none; }
    .step.active { display:block; }
    .wizard-nav { position: relative; display: flex; margin-bottom: 2rem; }
    .wizard-nav-item { flex: 1; text-align: center; padding: 1rem 0; position: relative; z-index: 1; }
    .wizard-nav-item.active { font-weight: bold; }
    .wizard-nav-item.completed { color: #50cd89; }
    .wizard-nav-item.active .wizard-icon { background-color: #009ef7; color: white; }
    .wizard-nav-item.completed .wizard-icon { background-color: #50cd89; color: white; }
    .wizard-icon { width: 45px; height: 45px; border-radius: 50%; background-color: #f1faff; color: #009ef7; 
                 display: flex; align-items: center; justify-content: center; margin: 0 auto 0.5rem; font-size: 1.2rem; }
    .wizard-progress { position: absolute; height: 2px; background-color: #e4e6ef; top: 22px; left: 0; right: 0; z-index: 0; }
    .wizard-progress-bar { height: 100%; background-color: #50cd89; width: 0; transition: width 0.3s; }
    .card { border: none; box-shadow: 0 0.5rem 1.5rem 0.5rem rgba(0, 0, 0, 0.075); }
    .card-header { background-color: #fff; border-bottom: 1px solid #eff2f5; }
    .btn-primary { background-color: #009ef7; border-color: #009ef7; }
    .btn-primary:hover { background-color: #0095e8; border-color: #0095e8; }
    .btn-secondary { background-color: #f1faff; border-color: #f1faff; color: #009ef7; }
    .btn-secondary:hover { background-color: #e4f3ff; border-color: #e4f3ff; color: #009ef7; }
    .btn-success { background-color: #50cd89; border-color: #50cd89; }
    .form-control:focus { border-color: #009ef7; box-shadow: 0 0 0 0.25rem rgba(0, 158, 247, 0.25); }
    .audio-btn.played { background-color: #50cd89; border-color: #50cd89; color: white; }
    .speaking-record-btn { background-color: #f1416c; border-color: #f1416c; color: white; }
    .speaking-record-btn.recording { animation: pulse 1.5s infinite; }
    @keyframes pulse { 0% { opacity: 1; } 50% { opacity: 0.5; } 100% { opacity: 1; } }
</style>

<div class="card">
    <div class="card-header">
        <h2 class="card-title fw-bold"><i class="fa-solid fa-spell-check me-2"></i>Evaluación Inicial</h2>
    </div>
    <div class="card-body">
        <p class="text-muted mb-4">A continuación responderás algunas preguntas básicas para que podamos determinar tu nivel de inglés y personalizar tu experiencia de aprendizaje.</p>
        
        <!-- Wizard Navigation -->
        <div class="wizard-nav">
            <div class="wizard-progress">
                <div class="wizard-progress-bar" id="progress-bar"></div>
            </div>
            <div class="wizard-nav-item active" data-step="1">
                <div class="wizard-icon">
                    <i class="fas fa-headphones"></i>
                </div>
                <div>Listening</div>
            </div>
            <div class="wizard-nav-item" data-step="2">
                <div class="wizard-icon">
                    <i class="fas fa-book"></i>
                </div>
                <div>Vocabulary</div>
            </div>
            <div class="wizard-nav-item" data-step="3">
                <div class="wizard-icon">
                    <i class="fas fa-pencil-alt"></i>
                </div>
                <div>Grammar</div>
            </div>
            <div class="wizard-nav-item" data-step="4">
                <div class="wizard-icon">
                    <i class="fas fa-microphone"></i>
                </div>
                <div>Speaking</div>
            </div>
        </div>
        
        <form action="{{ route('tutor.evaluation.submit') }}" method="POST">
    @csrf
    <!-- Paso 1: Comprensión Auditiva -->
<div class="step active">
    <div class="card shadow-sm border mb-4">
        <div class="card-header bg-light">
            <h4 class="card-title mb-0"><i class="fas fa-headphones me-2 text-primary"></i>Comprensión Auditiva</h4>
        </div>
        <div class="card-body">
            <p class="text-muted mb-4">Escucha cada frase y escribe exactamente lo que oyes en inglés.</p>
            
            @if(isset($quiz['listening']))
                @foreach($quiz['listening'] as $item)
                    <div class="mb-4 p-3 border rounded bg-light-primary bg-opacity-10">
                        <div class="d-flex align-items-center mb-2">
                            <button type="button" class="btn btn-sm btn-primary me-3 audio-btn" 
                                data-text="{{ $item['audio'] }}">
                                <i class="fa fa-volume-up me-1"></i> Reproducir audio
                            </button>
                            <span class="badge bg-primary">Pregunta {{ $item['id'] }}</span>
                        </div>
                        <div class="mt-3">
                            <input type="text" name="l_{{ $item['id'] }}" class="form-control" 
                                placeholder="Escribe lo que escuchas...">
                        </div>
                    </div>
                @endforeach
            @endif
        </div>
    </div>
</div>

<!-- Paso 2: Vocabulario -->
<div class="step">
    <div class="card shadow-sm border mb-4">
        <div class="card-header bg-light">
            <h4 class="card-title mb-0"><i class="fas fa-book me-2 text-primary"></i>Vocabulario</h4>
        </div>
        <div class="card-body">
            <p class="text-muted mb-4">Selecciona la respuesta correcta para cada pregunta de vocabulario.</p>
            
            @if(isset($quiz['vocabulary']))
                @foreach($quiz['vocabulary'] as $item)
                    <div class="mb-4 p-4 border rounded bg-light-primary bg-opacity-10">
                        <h5 class="mb-3">{{ $item['question'] }}</h5>
                        
                        @if(isset($item['image']))
                            <div class="text-center mb-4">
                                <img src="{{ asset('images/'.$item['image']) }}" alt="{{ $item['question'] }}" 
                                    class="img-thumbnail" style="max-height:180px">
                            </div>
                        @endif
                        
                        <div class="row row-cols-1 row-cols-md-2 g-3">
                            @foreach($item['options'] as $index => $option)
                                <div class="col">
                                    <div class="form-check custom-option">
                                        <input class="form-check-input" type="radio" 
                                            name="v_{{ $item['id'] }}" value="{{ $option }}" 
                                            id="v_{{ $item['id'] }}_{{ $index }}">
                                        <label class="form-check-label px-3 py-2 border rounded d-block" 
                                               for="v_{{ $item['id'] }}_{{ $index }}">
                                            {{ $option }}
                                        </label>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            @endif
        </div>
    </div>
</div>

<!-- Paso 3: Gramática -->
<div class="step">
    <div class="card shadow-sm border mb-4">
        <div class="card-header bg-light">
            <h4 class="card-title mb-0"><i class="fas fa-pencil-alt me-2 text-primary"></i>Gramática</h4>
        </div>
        <div class="card-body">
            <p class="text-muted mb-4">Selecciona la opción gramaticalmente correcta para cada pregunta.</p>
            
            @if(isset($quiz['grammar']))
                @foreach($quiz['grammar'] as $item)
                    <div class="mb-4 p-4 border rounded bg-light-primary bg-opacity-10">
                        <h5 class="mb-3">{{ $item['question'] }}</h5>
                        
                        <div class="mt-3">
                            @foreach($item['options'] as $index => $option)
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="radio" 
                                        name="g_{{ $item['id'] }}" value="{{ $option }}" 
                                        id="g_{{ $item['id'] }}_{{ $index }}">
                                    <label class="form-check-label px-2 py-1" 
                                           for="g_{{ $item['id'] }}_{{ $index }}">
                                        {{ $option }}
                                    </label>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            @endif
        </div>
    </div>
</div>

<!-- Paso 4: Speaking -->
<div class="step">
    <div class="card shadow-sm border mb-4">
        <div class="card-header bg-light">
            <h4 class="card-title mb-0"><i class="fas fa-microphone me-2 text-primary"></i>Speaking</h4>
        </div>
        <div class="card-body">
            <p class="text-muted mb-4">Responde las siguientes preguntas oralmente. Puedes grabar tu respuesta o escribirla.</p>
            
            @if(isset($quiz['speaking']))
                @foreach($quiz['speaking'] as $item)
                    <div class="mb-4 p-4 border rounded bg-light-primary bg-opacity-10">
                        <h5 class="mb-3">{{ $item['prompt'] }}</h5>
                        <p class="text-muted small">Criterio: {{ $item['criteria'] }}</p>
                        
                        <div class="d-flex align-items-center mb-3 gap-2">
                            <button type="button" class="btn btn-primary speaking-record-btn" 
                                data-question="{{ $item['id'] }}">
                                <i class="fas fa-microphone me-1"></i> <span class="btn-text">Iniciar reconocimiento de voz</span>
                            </button>
                            <span class="text-muted recording-status" id="status_s_{{ $item['id'] }}"></span>
                        </div>
                        
                        <div class="mt-3">
                            <textarea name="s_{{ $item['id'] }}" class="form-control" rows="3" readonly
                                placeholder="Tu respuesta aparecerá aquí cuando hables..."></textarea>
                            <div class="form-text text-muted">Habla claramente en inglés. El texto reconocido aparecerá automáticamente.</div>
                        </div>
                    </div>
                @endforeach
            @endif
        </div>
    </div>
</div>

<!-- Botones de navegación -->
<div class="d-flex justify-content-between mt-4 mb-5">
    <button type="button" class="btn btn-secondary px-4 py-2" id="prev-btn" style="display:none;">
        <i class="fas fa-arrow-left me-2"></i> Anterior
    </button>
    <button type="button" class="btn btn-primary px-4 py-2" id="next-btn">
        Siguiente <i class="fas fa-arrow-right ms-2"></i>
    </button>
    <button type="submit" class="btn btn-success px-4 py-2" id="submit-btn" style="display:none;">
        <i class="fas fa-check me-2"></i> Finalizar evaluación
    </button>
</div>

</form>
</div>
</div>

<script>
// Configuración del wizard
let currentStep = 1;
const totalSteps = 4;

// Función para actualizar la barra de progreso
function updateProgress() {
    const progressBar = document.getElementById('progress-bar');
    const percentage = ((currentStep - 1) / (totalSteps - 1)) * 100;
    progressBar.style.width = `${percentage}%`;
    
    // Actualizar estado de los iconos de navegación
    document.querySelectorAll('.wizard-nav-item').forEach((item, index) => {
        const step = index + 1;
        item.classList.remove('active', 'completed');
        if (step === currentStep) {
            item.classList.add('active');
        } else if (step < currentStep) {
            item.classList.add('completed');
        }
    });
    
    // Mostrar/ocultar botones
    document.getElementById('prev-btn').style.display = currentStep > 1 ? 'block' : 'none';
    document.getElementById('next-btn').style.display = currentStep < totalSteps ? 'block' : 'none';
    document.getElementById('submit-btn').style.display = currentStep === totalSteps ? 'block' : 'none';
}

// Navegación entre pasos
document.getElementById('next-btn').addEventListener('click', () => {
    if (currentStep < totalSteps) {
        document.querySelectorAll('.step').forEach((step, index) => {
            step.classList.remove('active');
            if (index === currentStep) {
                step.classList.add('active');
            }
        });
        currentStep++;
        updateProgress();
    }
});

document.getElementById('prev-btn').addEventListener('click', () => {
    if (currentStep > 1) {
        currentStep--;
        document.querySelectorAll('.step').forEach((step, index) => {
            step.classList.remove('active');
            if (index === currentStep - 1) {
                step.classList.add('active');
            }
        });
        updateProgress();
    }
});

// Funcionalidad de audio para listening
document.querySelectorAll('.audio-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        const text = btn.dataset.text;
        btn.disabled = true;
        btn.innerHTML = '<i class="fa fa-spinner fa-spin me-1"></i> Cargando...';
        
        fetch('/text-to-speech', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify({text: text})
        })
        .then(r => r.json())
        .then(data => {
            if (data.audioUrl) {
                const audio = new Audio(data.audioUrl);
                btn.innerHTML = '<i class="fa fa-play me-1"></i> Reproduciendo...';
                btn.classList.add('played');
                
                audio.play();
                audio.onended = () => {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fa fa-volume-up me-1"></i> Reproducir audio';
                    setTimeout(() => {
                        btn.classList.remove('played');
                    }, 1000);
                };
            }
        })
        .catch(() => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fa fa-volume-up me-1"></i> Reproducir audio';
        });
    });
});

// Funcionalidad para reconocimiento de voz (speaking)
let recognition = null;

// Comprobar si el navegador soporta reconocimiento de voz
const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
const hasRecognition = (typeof SpeechRecognition !== 'undefined');

// Función para detener el reconocimiento de voz si está activo
function stopRecognition() {
    if (recognition) {
        try {
            recognition.stop();
        } catch (e) {
            console.error('Error al detener el reconocimiento:', e);
        }
        recognition = null;
    }
}

document.querySelectorAll('.speaking-record-btn').forEach(btn => {
    const questionId = btn.dataset.question;
    const statusElement = document.getElementById(`status_s_${questionId}`);
    const textareaElement = document.querySelector(`textarea[name="s_${questionId}"]`);
    let isRecognizing = false;
    
    btn.addEventListener('click', () => {
        if (isRecognizing) {
            // Detener el reconocimiento
            stopRecognition();
            btn.classList.remove('recording');
            btn.querySelector('.btn-text').textContent = 'Iniciar reconocimiento de voz';
            statusElement.textContent = 'Reconocimiento detenido';
            isRecognizing = false;
            return;
        }
        
        // Verificar si el navegador soporta reconocimiento de voz
        if (!hasRecognition) {
            statusElement.textContent = 'Tu navegador no soporta reconocimiento de voz. Intenta con Chrome o Edge.';
            return;
        }
        
        // Solicitar permiso para usar el micrófono
        navigator.mediaDevices.getUserMedia({ audio: true })
            .then(stream => {
                // Detener el stream inmediatamente, solo lo usamos para solicitar permiso
                stream.getTracks().forEach(track => track.stop());
                
                // Asegurarnos de detener cualquier reconocimiento previo
                stopRecognition();
                
                // Crear una nueva instancia de reconocimiento
                recognition = new SpeechRecognition();
                recognition.lang = 'en-US';
                recognition.continuous = true;
                recognition.interimResults = true;
                
                // Variable para almacenar el texto acumulado
                let currentTranscript = '';
                
                recognition.onresult = (event) => {
                    let interimTranscript = '';
                    let finalTranscript = '';
                    
                    // Procesar los resultados del reconocimiento
                    for (let i = event.resultIndex; i < event.results.length; i++) {
                        const transcript = event.results[i][0].transcript;
                        if (event.results[i].isFinal) {
                            finalTranscript += transcript + ' ';
                        } else {
                            interimTranscript += transcript;
                        }
                    }
                    
                    // Actualizar el texto acumulado
                    if (finalTranscript) {
                        currentTranscript += finalTranscript;
                        // Actualizar el textarea con todo el texto reconocido hasta ahora
                        textareaElement.value = currentTranscript.trim();
                    }
                    
                    // Mostrar resultados provisionales
                    if (interimTranscript) {
                        statusElement.textContent = 'Reconociendo: ' + interimTranscript;
                    }
                };
                
                recognition.onend = () => {
                    // Si el reconocimiento termina pero seguimos en modo de reconocimiento, reiniciarlo
                    if (isRecognizing) {
                        try {
                            recognition.start();
                        } catch (e) {
                            console.error('Error al reiniciar el reconocimiento:', e);
                            isRecognizing = false;
                            btn.classList.remove('recording');
                            btn.querySelector('.btn-text').textContent = 'Iniciar reconocimiento de voz';
                            statusElement.textContent = 'Reconocimiento detenido por error';
                        }
                    }
                };
                
                recognition.onerror = (event) => {
                    console.error('Error en reconocimiento de voz:', event.error);
                    statusElement.textContent = 'Error: ' + event.error;
                    isRecognizing = false;
                    btn.classList.remove('recording');
                    btn.querySelector('.btn-text').textContent = 'Iniciar reconocimiento de voz';
                };
                
                // Iniciar el reconocimiento
                try {
                    recognition.start();
                    isRecognizing = true;
                    btn.classList.add('recording');
                    btn.querySelector('.btn-text').textContent = 'Detener reconocimiento';
                    statusElement.textContent = 'Escuchando... Habla ahora';
                } catch (e) {
                    console.error('Error al iniciar el reconocimiento:', e);
                    statusElement.textContent = 'Error al iniciar el reconocimiento';
                }
            })
            .catch(error => {
                console.error('Error al acceder al micrófono:', error);
                statusElement.textContent = 'Error al acceder al micrófono. Verifica los permisos.';
            });
    });
});

// Inicializar la navegación
updateProgress();
</script>
</form>
@endsection
