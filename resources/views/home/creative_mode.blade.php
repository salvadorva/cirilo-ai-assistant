@extends('../layout.app')
@section('title','Modo Creativo')
@section('css')
<style>
    .creative-card {
        border-radius: 15px;
        overflow: hidden;
        transition: all 0.3s ease;
        box-shadow: 0 5px 15px rgba(0,0,0,0.08);
    }
    
    .creative-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 15px 30px rgba(0,0,0,0.12);
    }
    
    .creative-header {
        background: linear-gradient(135deg, #6a11cb 0%, #2575fc 100%);
        color: white;
        padding: 20px;
        border-radius: 15px 15px 0 0;
    }
    
    .creative-content {
        padding: 20px;
        background-color: #fff;
        border-radius: 0 0 15px 15px;
    }
    
    .prompt-card {
        cursor: pointer;
        transition: all 0.3s ease;
        border-radius: 10px;
    }
    
    .prompt-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 10px 20px rgba(0,0,0,0.1);
    }
    
    .prompt-category {
        font-size: 0.8rem;
        padding: 4px 8px;
        border-radius: 20px;
        display: inline-block;
        margin-bottom: 8px;
    }
    
    .category-writing {
        background-color: #e3f2fd;
        color: #1565c0;
    }
    
    .category-visual {
        background-color: #f3e5f5;
        color: #7b1fa2;
    }
    
    .category-business {
        background-color: #e8f5e9;
        color: #2e7d32;
    }
    
    .category-academic {
        background-color: #fff3e0;
        color: #e65100;
    }
    
    .category-technology {
        background-color: #e8eaf6;
        color: #3f51b5;
    }
    
    .category-science {
        background-color: #e0f7fa;
        color: #006064;
    }
    
    .category-art {
        background-color: #fce4ec;
        color: #c2185b;
    }
    
    .category-music {
        background-color: #f1f8e9;
        color: #558b2f;
    }
    
    .category-health {
        background-color: #e1f5fe;
        color: #0288d1;
    }
    
    .category-travel {
        background-color: #f9fbe7;
        color: #827717;
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
            <h1 class="display-5 fw-bold text-primary"><i class="fa-solid fa-lightbulb me-2"></i>Modo Creativo</h1>
            <p class="lead">Explora diferentes formas de creatividad con la ayuda de la IA. Escribe, diseña, planifica y más.</p>
        </div>
    </div>
    
    <div class="row">
        <div class="col-lg-4 mb-4">
            <!-- Sección de categorías y prompts -->
            <div class="card mb-4">
                <div class="card-header bg-light">
                    <h4 class="mb-0"><i class="fa-solid fa-list me-2"></i>Prompts Sugeridos</h4>
                </div>
                <div class="card-body">
                    <div class="input-group mb-3">
                        <input type="text" class="form-control" id="prompt-search" placeholder="Buscar prompts...">
                        <button class="btn btn-outline-secondary" type="button" id="search-btn">
                            <i class="fa-solid fa-search"></i>
                        </button>
                    </div>
                    
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h5 class="mb-0">Categorías</h5>
                            <a href="#" id="show-all-categories" class="text-decoration-none small">Ver todas</a>
                        </div>
                        <div class="d-flex flex-wrap gap-2" id="category-filters">
                            <button class="btn btn-sm btn-outline-primary active" data-category="all">Todas</button>
                            <button class="btn btn-sm btn-outline-primary" data-category="writing">Escritura</button>
                            <button class="btn btn-sm btn-outline-primary" data-category="visual">Visual</button>
                            <button class="btn btn-sm btn-outline-primary" data-category="business">Negocios</button>
                            <button class="btn btn-sm btn-outline-primary" data-category="academic">Académico</button>
                            <button class="btn btn-sm btn-outline-primary d-none category-extra" data-category="technology">Tecnología</button>
                            <button class="btn btn-sm btn-outline-primary d-none category-extra" data-category="science">Ciencia</button>
                            <button class="btn btn-sm btn-outline-primary d-none category-extra" data-category="art">Arte</button>
                            <button class="btn btn-sm btn-outline-primary d-none category-extra" data-category="music">Música</button>
                            <button class="btn btn-sm btn-outline-primary d-none category-extra" data-category="health">Salud</button>
                            <button class="btn btn-sm btn-outline-primary d-none category-extra" data-category="travel">Viajes</button>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <div id="prompts-container">
                        <!-- Prompt Cards -->
                        <div class="card prompt-card mb-3" data-category="writing">
                            <div class="card-body">
                                <span class="prompt-category category-writing">Escritura</span>
                                <h5 class="card-title">Historia corta</h5>
                                <p class="card-text small text-muted">Escribe una historia corta sobre un viaje inesperado en el tiempo.</p>
                            </div>
                        </div>
                        
                        <div class="card prompt-card mb-3" data-category="visual">
                            <div class="card-body">
                                <span class="prompt-category category-visual">Visual</span>
                                <h5 class="card-title">Descripción de escena</h5>
                                <p class="card-text small text-muted">Describe una escena detallada de un mercado futurista en una ciudad flotante.</p>
                            </div>
                        </div>
                        
                        <div class="card prompt-card mb-3" data-category="business">
                            <div class="card-body">
                                <span class="prompt-category category-business">Negocios</span>
                                <h5 class="card-title">Plan de negocio</h5>
                                <p class="card-text small text-muted">Crea un esquema para un plan de negocio de una cafetería temática.</p>
                            </div>
                        </div>
                        
                        <div class="card prompt-card mb-3" data-category="academic">
                            <div class="card-body">
                                <span class="prompt-category category-academic">Académico</span>
                                <h5 class="card-title">Explicación científica</h5>
                                <p class="card-text small text-muted">Explica el concepto de entropía de una manera sencilla de entender.</p>
                            </div>
                        </div>
                        
                        <!-- Nuevas categorías -->
                        <div class="card prompt-card mb-3" data-category="technology">
                            <div class="card-body">
                                <span class="prompt-category category-technology">Tecnología</span>
                                <h5 class="card-title">Innovación tecnológica</h5>
                                <p class="card-text small text-muted">Describe una tecnología emergente y su posible impacto en la sociedad.</p>
                            </div>
                        </div>
                        
                        <div class="card prompt-card mb-3" data-category="science">
                            <div class="card-body">
                                <span class="prompt-category category-science">Ciencia</span>
                                <h5 class="card-title">Divulgación científica</h5>
                                <p class="card-text small text-muted">Explica un descubrimiento científico reciente y por qué es importante.</p>
                            </div>
                        </div>
                        
                        <div class="card prompt-card mb-3" data-category="art">
                            <div class="card-body">
                                <span class="prompt-category category-art">Arte</span>
                                <h5 class="card-title">Análisis artístico</h5>
                                <p class="card-text small text-muted">Describe un movimiento artístico y sus características principales.</p>
                            </div>
                        </div>
                        
                        <div class="card prompt-card mb-3" data-category="music">
                            <div class="card-body">
                                <span class="prompt-category category-music">Música</span>
                                <h5 class="card-title">Composición musical</h5>
                                <p class="card-text small text-muted">Describe los elementos que hacen que una canción sea memorable.</p>
                            </div>
                        </div>
                        
                        <div class="card prompt-card mb-3" data-category="health">
                            <div class="card-body">
                                <span class="prompt-category category-health">Salud</span>
                                <h5 class="card-title">Bienestar</h5>
                                <p class="card-text small text-muted">Sugiere hábitos saludables que pueden mejorar la calidad de vida.</p>
                            </div>
                        </div>
                        
                        <div class="card prompt-card mb-3" data-category="travel">
                            <div class="card-body">
                                <span class="prompt-category category-travel">Viajes</span>
                                <h5 class="card-title">Destino de viaje</h5>
                                <p class="card-text small text-muted">Describe un destino poco conocido que merezca ser visitado.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-8">
            <!-- Sección de entrada y respuesta -->
            <div class="creative-card mb-4">
                <div class="creative-header">
                    <h3 class="mb-0"><i class="fa-solid fa-pencil-alt me-2"></i>Tu Creación</h3>
                </div>
                <div class="creative-content">
                    <form id="creative-form">
                        <div class="mb-3">
                            <label for="creative-input" class="form-label">¿Qué quieres crear hoy?</label>
                            <textarea class="form-control" id="creative-input" rows="5" placeholder="Escribe tu prompt creativo aquí..."></textarea>
                        </div>
                        
                        <div class="d-flex justify-content-between mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="detailed-mode">
                                <label class="form-check-label" for="detailed-mode">Modo detallado</label>
                            </div>
                            <div>
                                <select class="form-select form-select-sm" id="creativity-level">
                                    <option value="balanced">Equilibrado</option>
                                    <option value="creative">Creativo</option>
                                    <option value="precise">Preciso</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="d-grid">
                            <button type="submit" id="create-btn" class="btn btn-primary">
                                <i class="fa-solid fa-magic me-2"></i>Crear
                            </button>
                        </div>
                    </form>
                    
                    <div id="result-container" class="mt-4 d-none">
                        <hr>
                        <h4>Resultado</h4>
                        <div id="loading-indicator" class="text-center py-5 d-none">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Cargando...</span>
                            </div>
                            <p class="mt-3 text-muted">Generando contenido creativo...</p>
                        </div>
                        <div id="result-content" class="mb-3"></div>
                        <div class="d-flex justify-content-between">
                            <button id="speak-result-btn" class="btn btn-outline-primary">
                                <i class="fa-solid fa-volume-up me-2"></i>Escuchar
                            </button>
                            <div>
                                <button id="copy-result-btn" class="btn btn-outline-secondary me-2">
                                    <i class="fa-solid fa-copy me-2"></i>Copiar
                                </button>
                                <button id="save-result-btn" class="btn btn-success">
                                    <i class="fa-solid fa-save me-2"></i>Guardar
                                </button>
                            </div>
                        </div>
                        <audio id="audio-player" controls class="w-100 mt-3 d-none"></audio>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal para guardar creación -->
<div class="modal fade" id="saveCreationModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Guardar Creación</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label for="creation-title-input" class="form-label">Título</label>
                    <input type="text" class="form-control" id="creation-title-input" placeholder="Ingresa un título para esta creación">
                </div>
                <div class="mb-3">
                    <label for="creation-category-input" class="form-label">Categoría</label>
                    <select class="form-select" id="creation-category-input">
                        <option value="writing">Escritura</option>
                        <option value="visual">Visual</option>
                        <option value="business">Negocios</option>
                        <option value="academic">Académico</option>
                        <option value="other">Otro</option>
                    </select>
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
    const promptSearch = document.getElementById('prompt-search');
    const categoryFilters = document.getElementById('category-filters');
    const promptsContainer = document.getElementById('prompts-container');
    const promptCards = document.querySelectorAll('.prompt-card');
    const creativeForm = document.getElementById('creative-form');
    const creativeInput = document.getElementById('creative-input');
    const detailedMode = document.getElementById('detailed-mode');
    const creativityLevel = document.getElementById('creativity-level');
    const createBtn = document.getElementById('create-btn');
    const resultContainer = document.getElementById('result-container');
    const loadingIndicator = document.getElementById('loading-indicator');
    const resultContent = document.getElementById('result-content');
    const speakResultBtn = document.getElementById('speak-result-btn');
    const copyResultBtn = document.getElementById('copy-result-btn');
    const saveResultBtn = document.getElementById('save-result-btn');
    const audioPlayer = document.getElementById('audio-player');
    const creationTitleInput = document.getElementById('creation-title-input');
    const creationCategoryInput = document.getElementById('creation-category-input');
    const confirmSaveBtn = document.getElementById('confirm-save-btn');
    
    // Variables globales
    let currentResult = '';
    
    // Modal de guardar
    const saveCreationModal = new bootstrap.Modal(document.getElementById('saveCreationModal'));
    
    // Filtrar prompts por categoría
    categoryFilters.addEventListener('click', function(e) {
        if (e.target.tagName === 'BUTTON') {
            // Quitar clase activa de todos los botones
            document.querySelectorAll('#category-filters button').forEach(btn => {
                btn.classList.remove('active');
            });
            
            // Añadir clase activa al botón clickeado
            e.target.classList.add('active');
            
            const category = e.target.getAttribute('data-category');
            
            // Filtrar tarjetas
            promptCards.forEach(card => {
                if (category === 'all' || card.getAttribute('data-category') === category) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });
        }
    });
    
    // Buscar prompts
    promptSearch.addEventListener('input', function() {
        const searchTerm = this.value.toLowerCase().trim();
        
        promptCards.forEach(card => {
            const title = card.querySelector('.card-title').textContent.toLowerCase();
            const description = card.querySelector('.card-text').textContent.toLowerCase();
            
            if (title.includes(searchTerm) || description.includes(searchTerm)) {
                card.style.display = 'block';
            } else {
                card.style.display = 'none';
            }
        });
    });
    
    // Seleccionar prompt
    promptsContainer.addEventListener('click', function(e) {
        const promptCard = e.target.closest('.prompt-card');
        if (promptCard) {
            const promptText = promptCard.querySelector('.card-text').textContent;
            creativeInput.value = promptText;
            creativeInput.focus();
        }
    });
    
    // Enviar formulario creativo
    creativeForm.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const prompt = creativeInput.value.trim();
        if (!prompt) {
            alert('Por favor, ingresa un prompt creativo.');
            return;
        }
        
        // Mostrar cargando
        resultContainer.classList.remove('d-none');
        loadingIndicator.classList.remove('d-none');
        resultContent.innerHTML = '';
        
        // Preparar datos
        const requestData = {
            prompt: prompt,
            detailed: detailedMode.checked,
            creativity: creativityLevel.value
        };
        
        try {
            // Enviar solicitud
            const response = await fetch('/creative-mode/generate', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify(requestData)
            });
            
            const data = await response.json();
            
            if (data.success) {
                // Guardar resultado
                currentResult = data.content;
                
                // Mostrar resultado
                resultContent.innerHTML = window.CiriloContent.renderMarkdown(data.content);
                
                // Cambiar visibilidad
                loadingIndicator.classList.add('d-none');
                
                // Convertir a voz automáticamente
                speakText(currentResult);
            } else {
                throw new Error(data.message || 'Error al generar contenido creativo');
            }
        } catch (error) {
            console.error('Error:', error);
            loadingIndicator.classList.add('d-none');
            resultContent.innerHTML = `
                <div class="alert alert-danger" role="alert">
                    <i class="fa-solid fa-exclamation-triangle me-2"></i>
                    ${window.CiriloContent.escapeHtml(error.message || 'Ocurrió un error al generar contenido. Por favor, intenta de nuevo.')}
                </div>
            `;
        }
    });
    
    // Función para convertir texto a voz
    async function speakText(text) {
        if (!text) return;
        
        // Limitar el texto a 2000 caracteres para evitar timeouts
        const maxLength = 2000;
        if (text.length > maxLength) {
            console.warn(`Texto truncado de ${text.length} a ${maxLength} caracteres`);
            // Buscar un punto para cortar el texto de manera natural
            const cutPoint = text.lastIndexOf('.', maxLength);
            text = cutPoint > 0 ? text.substring(0, cutPoint + 1) : text.substring(0, maxLength);
        }
        
        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), 120000); // 2 minutos de timeout
        
        try {
            // Cambiar botón a estado de carga
            speakResultBtn.disabled = true;
            speakResultBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Generando audio...';
            
            const response = await fetch('/text-to-speech', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ 
                    text: text,
                    _token: document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                }),
                signal: controller.signal
            });
            
            clearTimeout(timeoutId);
            
            // Verificar si la respuesta es exitosa
            if (!response.ok) {
                let errorMessage = `Error ${response.status}: ${response.statusText}`;
                try {
                    const errorData = await response.json();
                    errorMessage = errorData.error || errorMessage;
                } catch (e) {
                    console.error('Error al procesar respuesta de error:', e);
                }
                throw new Error(errorMessage);
            }
            
            const responseData = await response.json();
            
            if (responseData.audioUrl) {
                // Crear un nuevo objeto de audio para cada reproducción
                const audio = new Audio(responseData.audioUrl);
                audio.play().catch(error => {
                    console.error('Error al reproducir audio:', error);
                    throw new Error('No se pudo reproducir el audio: ' + error.message);
                });
                
                // Actualizar el reproductor de audio en la interfaz
                audioPlayer.src = responseData.audioUrl;
                audioPlayer.classList.remove('d-none');
                audioPlayer.load();
            } else {
                throw new Error('No se recibió una URL de audio válida');
            }
        } catch (error) {
            console.error('Error en text-to-speech:', error);
            
            // Usar SweetAlert2 para mostrar el error
            Swal.fire({
                icon: 'error',
                title: 'Error de audio',
                text: 'No se pudo generar el audio. ' + (error.message || 'Por favor, intenta de nuevo.'),
                confirmButtonColor: '#3085d6'
            });
        } finally {
            // Restaurar botón
            speakResultBtn.disabled = false;
            speakResultBtn.innerHTML = '<i class="fa-solid fa-volume-up me-2"></i>Escuchar';
        }
    }
    
    // Botón para escuchar resultado
    speakResultBtn.addEventListener('click', function() {
        speakText(currentResult);
    });
    
    // Copiar resultado
    copyResultBtn.addEventListener('click', function() {
        if (!currentResult) return;
        
        navigator.clipboard.writeText(currentResult).then(() => {
            // Cambiar temporalmente el botón para indicar éxito
            const originalText = this.innerHTML;
            this.innerHTML = '<i class="fa-solid fa-check me-2"></i>Copiado';
            
            setTimeout(() => {
                this.innerHTML = originalText;
            }, 2000);
        }).catch(err => {
            console.error('Error al copiar: ', err);
            alert('Error al copiar el texto. Por favor, inténtalo manualmente.');
        });
    });
    
    // Guardar resultado
    saveResultBtn.addEventListener('click', function() {
        if (!currentResult) return;
        
        // Sugerir un título basado en el contenido
        const suggestedTitle = creativeInput.value.trim().substring(0, 30) + '...';
        creationTitleInput.value = suggestedTitle;
        saveCreationModal.show();
    });
    
    // Confirmar guardado
    confirmSaveBtn.addEventListener('click', async function() {
        const title = creationTitleInput.value.trim();
        if (!title) {
            Swal.fire({
                icon: 'warning',
                title: 'Título requerido',
                text: 'Por favor, ingresa un título para guardar la creación.',
                confirmButtonColor: '#3085d6'
            });
            return;
        }
        
        try {
            const content = JSON.stringify({
                prompt: creativeInput.value.trim(),
                assistant: currentResult,
                settings: {
                    detailed: detailedMode.checked,
                    creativity: creativityLevel.value
                }
            });
            
            // Mostrar indicador de carga
            Swal.fire({
                title: 'Guardando...',
                text: 'Espera un momento mientras guardamos tu creación',
                allowOutsideClick: false,
                allowEscapeKey: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
            const response = await fetch('/creative-mode/save', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({
                    title: title,
                    category: creationCategoryInput.value,
                    content: content
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                saveCreationModal.hide();
                
                // Mostrar mensaje de éxito
                Swal.fire({
                    icon: 'success',
                    title: '¡Guardado!',
                    text: 'Tu creación se ha guardado correctamente.',
                    showConfirmButton: false,
                    timer: 2000
                });
            } else {
                throw new Error(data.message || 'Error al guardar la creación');
            }
        } catch (error) {
            console.error('Error:', error);
            
            // Mostrar mensaje de error con SweetAlert2
            Swal.fire({
                icon: 'error',
                title: 'Error al guardar',
                text: error.message || 'Ocurrió un error al guardar la creación. Por favor, intenta de nuevo.',
                confirmButtonColor: '#d33'
            });
        }
    });
    
    // Mostrar todas las categorías
    document.getElementById('show-all-categories').addEventListener('click', function(e) {
        e.preventDefault();
        
        const extraCategories = document.querySelectorAll('.category-extra');
        extraCategories.forEach(category => {
            category.classList.toggle('d-none');
        });
        
        // Cambiar el texto del enlace
        if (this.textContent === 'Ver todas') {
            this.textContent = 'Ver menos';
        } else {
            this.textContent = 'Ver todas';
        }
    });
});
</script>
@endpush
