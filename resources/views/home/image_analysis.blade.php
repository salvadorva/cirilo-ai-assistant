@extends('../layout.app')
@section('title','Análisis de Imágenes')
@section('css')
<style>
    .image-preview {
        max-height: 300px;
        object-fit: contain;
        border-radius: 10px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    }
    
    .analysis-card {
        border-radius: 15px;
        overflow: hidden;
        transition: all 0.3s ease;
        box-shadow: 0 5px 15px rgba(0,0,0,0.08);
    }
    
    .analysis-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 15px 30px rgba(0,0,0,0.12);
    }
    
    .analysis-header {
        background: linear-gradient(135deg, #2193b0 0%, #6dd5ed 100%);
        color: white;
        padding: 20px;
        border-radius: 15px 15px 0 0;
    }
    
    .analysis-content {
        padding: 20px;
        background-color: #fff;
        border-radius: 0 0 15px 15px;
    }
    
    .upload-container {
        border: 2px dashed #ccc;
        border-radius: 15px;
        padding: 30px;
        text-align: center;
        background-color: #f9f9f9;
        transition: all 0.3s ease;
    }
    
    .upload-container:hover {
        border-color: #2193b0;
        background-color: #f0f9fb;
    }
    
    .upload-container.dragover {
        border-color: #2193b0;
        background-color: #e0f3f7;
    }
    
    #audio-player {
        height: 30px;
        margin-top: 10px;
    }
</style>
@endsection

@section('content')
<div class="container mt-4">
    <div class="row">
        <div class="col-12 mb-4">
            <h1 class="display-5 fw-bold text-primary"><i class="fa-solid fa-eye me-2"></i>Análisis de Imágenes</h1>
            <p class="lead">Sube una imagen y deja que la IA la analice y describa lo que ve.</p>
        </div>
    </div>
    
    <div class="row">
        <div class="col-lg-6 mb-4">
            <!-- Sección de subida de imagen -->
            <div class="card mb-4">
                <div class="card-header bg-light">
                    <h4 class="mb-0"><i class="fa-solid fa-upload me-2"></i>Subir Imagen</h4>
                </div>
                <div class="card-body">
                    <form id="image-upload-form">
                        <div id="upload-container" class="upload-container mb-3">
                            <i class="fa-solid fa-cloud-upload-alt fa-3x text-muted mb-3"></i>
                            <p class="mb-2">Arrastra y suelta una imagen aquí o</p>
                            <label for="image-input" class="btn btn-primary">
                                Seleccionar Archivo
                            </label>
                            <input type="file" id="image-input" class="d-none" accept="image/*">
                            <p class="text-muted small mt-2">Formatos soportados: JPG, PNG, GIF (máx. 4MB)</p>
                        </div>
                        
                        <div id="image-preview-container" class="text-center mb-3 d-none">
                            <img id="image-preview" class="image-preview mb-3" src="" alt="Vista previa">
                            <div class="d-flex justify-content-center">
                                <button type="button" id="change-image-btn" class="btn btn-outline-secondary me-2">
                                    <i class="fa-solid fa-exchange-alt me-1"></i>Cambiar
                                </button>
                                <button type="button" id="remove-image-btn" class="btn btn-outline-danger">
                                    <i class="fa-solid fa-trash me-1"></i>Eliminar
                                </button>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="prompt-input" class="form-label">Pregunta sobre la imagen (opcional)</label>
                            <input type="text" class="form-control" id="prompt-input" placeholder="Ej: ¿Qué hay en esta imagen? ¿Qué está pasando?">
                        </div>
                        
                        <button type="submit" id="analyze-btn" class="btn btn-primary w-100" disabled>
                            <i class="fa-solid fa-magnifying-glass me-2"></i>Analizar Imagen
                        </button>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="col-lg-6">
            <!-- Resultado del análisis -->
            <div class="analysis-card mb-4">
                <div class="analysis-header">
                    <h3 class="mb-0"><i class="fa-solid fa-brain me-2"></i>Resultado del Análisis</h3>
                </div>
                <div class="analysis-content">
                    <div id="analysis-placeholder" class="text-center py-5">
                        <i class="fa-solid fa-robot fa-4x text-muted mb-3"></i>
                        <p class="text-muted">Sube una imagen para ver el análisis</p>
                    </div>
                    <div id="analysis-loading" class="text-center py-5 d-none">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Cargando...</span>
                        </div>
                        <p class="mt-3 text-muted">Analizando imagen...</p>
                    </div>
                    <div id="analysis-result" class="d-none">
                        <div id="analysis-content" class="mb-4"></div>
                        <div class="d-flex justify-content-between">
                            <button id="speak-analysis-btn" class="btn btn-outline-primary">
                                <i class="fa-solid fa-volume-up me-2"></i>Escuchar
                            </button>
                            <button id="save-analysis-btn" class="btn btn-success">
                                <i class="fa-solid fa-save me-2"></i>Guardar
                            </button>
                        </div>
                        <audio id="audio-player" controls class="w-100 mt-3 d-none"></audio>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal para guardar análisis -->
<div class="modal fade" id="saveAnalysisModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Guardar Análisis</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label for="analysis-title-input" class="form-label">Título</label>
                    <input type="text" class="form-control" id="analysis-title-input" placeholder="Ingresa un título para este análisis">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="confirm-save-btn">Guardar</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Elementos DOM
    const uploadContainer = document.getElementById('upload-container');
    const imageInput = document.getElementById('image-input');
    const imagePreviewContainer = document.getElementById('image-preview-container');
    const imagePreview = document.getElementById('image-preview');
    const changeImageBtn = document.getElementById('change-image-btn');
    const removeImageBtn = document.getElementById('remove-image-btn');
    const promptInput = document.getElementById('prompt-input');
    const analyzeBtn = document.getElementById('analyze-btn');
    const analysisPlaceholder = document.getElementById('analysis-placeholder');
    const analysisLoading = document.getElementById('analysis-loading');
    const analysisResult = document.getElementById('analysis-result');
    const analysisContent = document.getElementById('analysis-content');
    const speakAnalysisBtn = document.getElementById('speak-analysis-btn');
    const saveAnalysisBtn = document.getElementById('save-analysis-btn');
    const analysisTitleInput = document.getElementById('analysis-title-input');
    const confirmSaveBtn = document.getElementById('confirm-save-btn');
    const audioPlayer = document.getElementById('audio-player');
    const imageUploadForm = document.getElementById('image-upload-form');
    
    // Variables globales
    let selectedFile = null;
    let analysisText = '';
    let imagePath = '';
    
    // Modal de guardar
    const saveAnalysisModal = new bootstrap.Modal(document.getElementById('saveAnalysisModal'));
    
    // Funciones para drag & drop
    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
        uploadContainer.addEventListener(eventName, preventDefaults, false);
    });
    
    function preventDefaults(e) {
        e.preventDefault();
        e.stopPropagation();
    }
    
    ['dragenter', 'dragover'].forEach(eventName => {
        uploadContainer.addEventListener(eventName, highlight, false);
    });
    
    ['dragleave', 'drop'].forEach(eventName => {
        uploadContainer.addEventListener(eventName, unhighlight, false);
    });
    
    function highlight() {
        uploadContainer.classList.add('dragover');
    }
    
    function unhighlight() {
        uploadContainer.classList.remove('dragover');
    }
    
    // Manejar el drop de archivos
    uploadContainer.addEventListener('drop', handleDrop, false);
    
    function handleDrop(e) {
        const dt = e.dataTransfer;
        const files = dt.files;
        
        if (files.length > 0) {
            handleFiles(files[0]);
        }
    }
    
    // Manejar la selección de archivos
    imageInput.addEventListener('change', function() {
        if (this.files.length > 0) {
            handleFiles(this.files[0]);
        }
    });
    
    // Procesar el archivo seleccionado
    function handleFiles(file) {
        if (!file.type.match('image.*')) {
            alert('Por favor, selecciona una imagen válida.');
            return;
        }
        
        if (file.size > 4 * 1024 * 1024) { // 4MB
            alert('La imagen es demasiado grande. El tamaño máximo es 4MB.');
            return;
        }
        
        selectedFile = file;
        
        // Mostrar vista previa
        const reader = new FileReader();
        reader.onload = function(e) {
            imagePreview.src = e.target.result;
            imagePreviewContainer.classList.remove('d-none');
            uploadContainer.classList.add('d-none');
            analyzeBtn.disabled = false;
        };
        reader.readAsDataURL(file);
    }
    
    // Cambiar imagen
    changeImageBtn.addEventListener('click', function() {
        imageInput.click();
    });
    
    // Eliminar imagen
    removeImageBtn.addEventListener('click', function() {
        selectedFile = null;
        imagePreview.src = '';
        imagePreviewContainer.classList.add('d-none');
        uploadContainer.classList.remove('d-none');
        analyzeBtn.disabled = true;
    });
    
    // Analizar imagen
    imageUploadForm.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        if (!selectedFile) {
            alert('Por favor, selecciona una imagen primero.');
            return;
        }
        
        // Mostrar cargando
        analysisPlaceholder.classList.add('d-none');
        analysisResult.classList.add('d-none');
        analysisLoading.classList.remove('d-none');
        
        // Crear FormData
        const formData = new FormData();
        formData.append('image', selectedFile);
        
        const prompt = promptInput.value.trim();
        if (prompt) {
            formData.append('prompt', prompt);
        }
        
        try {
            console.log('Enviando imagen para análisis...');
            // Enviar imagen para análisis
            const response = await fetch('/analyze-image', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: formData
            });
            
            console.log('Respuesta recibida:', response.status, response.statusText);
            
            // Verificar si la respuesta es JSON válido
            let data;
            const contentType = response.headers.get('content-type');
            if (contentType && contentType.includes('application/json')) {
                data = await response.json();
                console.log('Datos recibidos:', data);
            } else {
                const text = await response.text();
                console.error('Respuesta no JSON:', text);
                throw new Error('La respuesta del servidor no es JSON válido');
            }
            
            if (data.success) {
                console.log('Análisis exitoso, procesando resultado...');
                // Guardar resultado
                analysisText = data.content;
                imagePath = data.image_path;
                
                // Mostrar resultado - asegurar que el texto se muestra correctamente
                if (analysisText) {
                    analysisContent.innerHTML = analysisText.replace(/\n/g, '<br>');
                    
                    // Cambiar visibilidad
                    analysisLoading.classList.add('d-none');
                    analysisResult.classList.remove('d-none');
                    
                    // Convertir a voz automáticamente
                    console.log('Iniciando conversión a voz...');
                    speakText(analysisText);
                } else {
                    console.error('El contenido del análisis está vacío');
                    throw new Error('El contenido del análisis está vacío');
                }
            } else {
                console.error('Error en respuesta:', data);
                throw new Error(data.message || 'Error al analizar la imagen');
            }
        } catch (error) {
            console.error('Error en análisis de imagen:', error);
            
            // Determinar mensaje de error más específico
            let errorMessage = error.message || 'Ocurrió un error al analizar la imagen. Por favor, intenta de nuevo.';
            let errorDetails = '';
            
            // Agregar detalles adicionales según el tipo de error
            if (error.name === 'TypeError' && errorMessage.includes('JSON')) {
                errorDetails = 'La respuesta del servidor no tiene el formato esperado. Contacte al administrador.';
            } else if (error.name === 'SyntaxError') {
                errorDetails = 'Error en el formato de respuesta del servidor.';
            } else if (error.name === 'NetworkError' || error.message.includes('network')) {
                errorDetails = 'Compruebe su conexión a internet e intente nuevamente.';
            }
            
            // Mostrar mensaje de error
            analysisLoading.classList.add('d-none');
            analysisResult.classList.remove('d-none');
            analysisContent.innerHTML = `
                <div class="alert alert-danger" role="alert">
                    <i class="fa-solid fa-exclamation-triangle me-2"></i>
                    <strong>Error:</strong> ${errorMessage}
                    ${errorDetails ? `<p class="mt-2 mb-0"><small>${errorDetails}</small></p>` : ''}
                </div>
            `;
            
            // Registrar detalles adicionales en consola para debugging
            console.log('Estado de elementos DOM:');
            console.log('- analysisResult visible:', !analysisResult.classList.contains('d-none'));
            console.log('- analysisLoading visible:', !analysisLoading.classList.contains('d-none'));
            console.log('- analysisContent actualizado:', !!analysisContent.innerHTML);
        }
    });
    
    // Función para convertir texto a voz
    async function speakText(text) {
        if (!text) return;
        
        try {
            // Cambiar botón a estado de carga
            speakAnalysisBtn.disabled = true;
            speakAnalysisBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Generando audio...';
            
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
                audioPlayer.src = responseData.audioUrl;
                audioPlayer.classList.remove('d-none');
                audioPlayer.load();
                
                try {
                    // Intentar reproducir automáticamente
                    console.log('Intentando reproducción automática...');
                    await audioPlayer.play();
                    console.log('Reproducción automática exitosa');
                } catch (playError) {
                    console.error('Error de reproducción:', playError);
                    
                    // Verificar si es un error de autoplay bloqueado
                    if (playError.name === 'NotAllowedError') {
                        console.log('Autoplay bloqueado por el navegador, mostrando botón manual');
                        showPlayButton(responseData.audioUrl);
                    } else {
                        // Otros errores de reproducción
                        throw playError;
                    }
                }
            } else {
                throw new Error('Error al generar el audio');
            }
        } catch (error) {
            console.error('Error en generación de audio:', error);
            alert('Error al generar el audio: ' + error.message);
        } finally {
            // Restaurar botón solo si no se mostró el botón de reproducción manual
            if (!document.getElementById('manual-play-button')) {
                speakAnalysisBtn.disabled = false;
                speakAnalysisBtn.innerHTML = '<i class="fa-solid fa-volume-up me-2"></i>Escuchar';
            }
        }
    }
    
    // Función para mostrar botón de reproducción manual cuando autoplay está bloqueado
    function showPlayButton(audioUrl) {
        // Crear un contenedor para el mensaje y botón
        const playButtonContainer = document.createElement('div');
        playButtonContainer.className = 'mt-3 alert alert-info';
        playButtonContainer.innerHTML = `
            <p><i class="fa-solid fa-info-circle me-2"></i>El audio está listo pero el navegador bloqueó la reproducción automática.</p>
            <button id="manual-play-button" class="btn btn-success">
                <i class="fa-solid fa-play me-2"></i>Reproducir Audio (OpenAI)
            </button>
        `;
        
        // Insertar después del reproductor de audio
        audioPlayer.parentNode.insertBefore(playButtonContainer, audioPlayer.nextSibling);
        
        // Agregar event listener al botón
        const manualPlayButton = document.getElementById('manual-play-button');
        manualPlayButton.addEventListener('click', async function() {
            try {
                await audioPlayer.play();
                // Remover el contenedor después de reproducir exitosamente
                playButtonContainer.remove();
                // Restaurar el botón original
                speakAnalysisBtn.disabled = false;
                speakAnalysisBtn.innerHTML = '<i class="fa-solid fa-volume-up me-2"></i>Escuchar';
            } catch (error) {
                console.error('Error en reproducción manual:', error);
                alert('Error al reproducir el audio: ' + error.message);
            }
        });
        
        // Restaurar el botón original
        speakAnalysisBtn.disabled = false;
        speakAnalysisBtn.innerHTML = '<i class="fa-solid fa-volume-up me-2"></i>Escuchar';
    }
    
    // Botón para escuchar análisis
    speakAnalysisBtn.addEventListener('click', function() {
        speakText(analysisText);
    });
    
    // Guardar análisis
    saveAnalysisBtn.addEventListener('click', function() {
        if (!analysisText || !imagePath) return;
        
        // Sugerir un título basado en el contenido
        const suggestedTitle = 'Análisis de imagen - ' + new Date().toLocaleDateString();
        analysisTitleInput.value = suggestedTitle;
        saveAnalysisModal.show();
    });
    
    // Confirmar guardado
    confirmSaveBtn.addEventListener('click', async function() {
        const title = analysisTitleInput.value.trim();
        if (!title) {
            alert('Por favor, ingresa un título para guardar el análisis.');
            return;
        }
        
        try {
            const content = JSON.stringify({
                assistant: analysisText
            });
            
            const response = await fetch('/save-image-analysis', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({
                    title: title,
                    content: content,
                    image_path: imagePath
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                saveAnalysisModal.hide();
                
                // Mostrar mensaje de éxito
                Swal.fire({
                    icon: 'success',
                    title: '¡Guardado!',
                    text: 'El análisis se ha guardado correctamente.',
                    showConfirmButton: false,
                    timer: 2000
                });
            } else {
                throw new Error(data.message || 'Error al guardar el análisis');
            }
        } catch (error) {
            console.error('Error:', error);
            alert('Error al guardar el análisis. Por favor, intenta de nuevo.');
        }
    });
});
</script>
@endpush 