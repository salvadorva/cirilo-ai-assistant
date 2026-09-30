@extends('../layout.app')
@section('title','Historial de Conversaciones')
@section('css')
<style>
    .history-card {
        border-radius: 15px;
        overflow: hidden;
        transition: all 0.3s ease;
        box-shadow: 0 5px 15px rgba(0,0,0,0.08);
    }
    
    .history-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 15px 30px rgba(0,0,0,0.12);
    }
    
    .history-header {
        background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
        color: white;
        padding: 20px;
        border-radius: 15px 15px 0 0;
    }
    
    .history-content {
        padding: 20px;
        background-color: #fff;
        border-radius: 0 0 15px 15px;
    }
    
    .conversation-item {
        border-radius: 10px;
        transition: all 0.3s ease;
        cursor: pointer;
    }
    
    .conversation-item:hover {
        transform: translateY(-3px);
        box-shadow: 0 10px 20px rgba(0,0,0,0.1);
    }
    
    .conversation-type {
        font-size: 0.8rem;
        padding: 4px 8px;
        border-radius: 20px;
        display: inline-block;
    }
    
    .type-chat {
        background-color: #e3f2fd;
        color: #1565c0;
    }
    
    .type-creative {
        background-color: #f3e5f5;
        color: #7b1fa2;
    }
    
    .type-image {
        background-color: #e8f5e9;
        color: #2e7d32;
    }
    
    .message-bubble {
        border-radius: 18px;
        padding: 12px 16px;
        margin-bottom: 10px;
        max-width: 80%;
    }
    
    .message-user {
        background-color: #e9ecef;
        margin-right: auto;
    }
    
    .message-assistant {
        background-color: #d1e7dd;
        margin-left: auto;
    }
    
    .message-time {
        font-size: 0.75rem;
        color: #6c757d;
        margin-top: 4px;
    }
    
    .conversation-detail {
        max-height: 600px;
        overflow-y: auto;
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
            <h1 class="display-5 fw-bold text-primary"><i class="fa-solid fa-history me-2"></i>Historial de Conversaciones</h1>
            <p class="lead">Revisa y continúa tus conversaciones anteriores con Cirilo.</p>
        </div>
    </div>
    
    <div class="row">
        <div class="col-lg-4 mb-4">
            <!-- Lista de conversaciones -->
            <div class="card mb-4">
                <div class="card-header bg-light">
                    <div class="d-flex justify-content-between align-items-center">
                        <h4 class="mb-0"><i class="fa-solid fa-comments me-2"></i>Conversaciones</h4>
                        <div class="dropdown">
                            <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" id="filterDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fa-solid fa-filter me-1"></i>Filtrar
                            </button>
                            <ul class="dropdown-menu" aria-labelledby="filterDropdown">
                                <li><a class="dropdown-item active" href="#" data-filter="all">Todas</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="#" data-filter="chat">Chat General</a></li>
                                <li><a class="dropdown-item" href="#" data-filter="creative">Modo Creativo</a></li>
                                <li><a class="dropdown-item" href="#" data-filter="image">Análisis de Imágenes</a></li>
                            </ul>
                        </div>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="input-group p-3">
                        <input type="text" class="form-control" id="search-conversations" placeholder="Buscar conversaciones...">
                        <button class="btn btn-outline-secondary" type="button" id="search-btn">
                            <i class="fa-solid fa-search"></i>
                        </button>
                    </div>
                    
                    <div id="conversations-list" class="list-group list-group-flush">
                        <div class="text-center py-4 text-muted" id="no-conversations">
                            <i class="fa-solid fa-comments fa-3x mb-3"></i>
                            <p>No hay conversaciones guardadas</p>
                        </div>
                        
                        <!-- Las conversaciones se cargarán dinámicamente aquí -->
                    </div>
                </div>
            </div>
            
            <div class="d-grid">
                <button class="btn btn-danger" id="clear-history-btn">
                    <i class="fa-solid fa-trash me-2"></i>Limpiar Historial
                </button>
            </div>
        </div>
        
        <div class="col-lg-8">
            <!-- Detalle de la conversación -->
            <div class="history-card mb-4">
                <div class="history-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h3 class="mb-0"><i class="fa-solid fa-comment-dots me-2"></i><span id="conversation-title">Selecciona una conversación</span></h3>
                        <div id="conversation-actions" class="d-none">
                            <button class="btn btn-sm btn-light" id="continue-conversation-btn">
                                <i class="fa-solid fa-reply me-1"></i>Continuar
                            </button>
                        </div>
                    </div>
                </div>
                <div class="history-content">
                    <div id="conversation-placeholder" class="text-center py-5">
                        <i class="fa-solid fa-comments fa-4x text-muted mb-3"></i>
                        <p class="text-muted">Selecciona una conversación para ver su contenido</p>
                    </div>
                    
                    <div id="conversation-detail" class="conversation-detail d-none">
                        <!-- El contenido de la conversación se cargará dinámicamente aquí -->
                    </div>
                    
                    <div id="audio-controls" class="mt-3 d-none">
                        <div class="d-flex align-items-center">
                            <button id="speak-message-btn" class="btn btn-outline-primary me-2">
                                <i class="fa-solid fa-volume-up me-2"></i>Escuchar respuesta
                            </button>
                            <div class="flex-grow-1">
                                <audio id="audio-player" controls class="w-100"></audio>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal de confirmación para limpiar historial -->
<div class="modal fade" id="clearHistoryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirmar eliminación</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>¿Estás seguro de que deseas eliminar todo el historial de conversaciones? Esta acción no se puede deshacer.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-danger" id="confirm-clear-btn">Eliminar todo</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Elementos DOM
    const conversationsList = document.getElementById('conversations-list');
    const noConversations = document.getElementById('no-conversations');
    const searchInput = document.getElementById('search-conversations');
    const filterDropdown = document.getElementById('filterDropdown');
    const conversationTitle = document.getElementById('conversation-title');
    const conversationDetail = document.getElementById('conversation-detail');
    const conversationPlaceholder = document.getElementById('conversation-placeholder');
    const conversationActions = document.getElementById('conversation-actions');
    const continueConversationBtn = document.getElementById('continue-conversation-btn');
    const clearHistoryBtn = document.getElementById('clear-history-btn');
    const confirmClearBtn = document.getElementById('confirm-clear-btn');
    const speakMessageBtn = document.getElementById('speak-message-btn');
    const audioPlayer = document.getElementById('audio-player');
    const audioControls = document.getElementById('audio-controls');
    
    // Variables globales
    let conversations = [];
    let currentConversationId = null;
    let lastAssistantMessage = '';
    let cachedAudioUrl = null; // URL del audio ya generado para la conversación actual
    
    // Modal de confirmación para limpiar historial
    const clearHistoryModal = new bootstrap.Modal(document.getElementById('clearHistoryModal'));
    
    // Cargar todas las conversaciones al inicio
    loadConversations();
    
    // Filtrar conversaciones
    document.querySelectorAll('.dropdown-item').forEach(item => {
        item.addEventListener('click', function(e) {
            e.preventDefault();
            
            // Actualizar estado activo
            document.querySelectorAll('.dropdown-item').forEach(i => i.classList.remove('active'));
            this.classList.add('active');
            
            const filter = this.getAttribute('data-filter');
            filterConversations(filter);
        });
    });
    
    // Buscar conversaciones
    searchInput.addEventListener('input', function() {
        const searchTerm = this.value.toLowerCase().trim();
        searchConversations(searchTerm);
    });
    
    // Limpiar historial
    clearHistoryBtn.addEventListener('click', function() {
        clearHistoryModal.show();
    });
    
    confirmClearBtn.addEventListener('click', async function() {
        try {
            const response = await fetch('/conversations/clear', {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                }
            });
            
            const data = await response.json();
            
            if (data.success) {
                // Limpiar audios cacheados en localStorage
                Object.keys(localStorage).filter(k => k.startsWith('audio_conv_')).forEach(k => localStorage.removeItem(k));
                conversations = [];
                renderConversationsList();
                
                // Resetear la vista de detalle
                conversationTitle.textContent = 'Selecciona una conversación';
                conversationDetail.innerHTML = '';
                conversationDetail.classList.add('d-none');
                conversationPlaceholder.classList.remove('d-none');
                conversationActions.classList.add('d-none');
                audioControls.classList.add('d-none');
                
                // Cerrar modal
                clearHistoryModal.hide();
                
                // Mostrar mensaje de éxito
                Swal.fire({
                    icon: 'success',
                    title: '¡Eliminado!',
                    text: 'El historial de conversaciones ha sido eliminado.',
                    showConfirmButton: false,
                    timer: 2000
                });
            } else {
                throw new Error(data.message || 'Error al eliminar el historial');
            }
        } catch (error) {
            console.error('Error:', error);
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: error.message || 'Ocurrió un error al eliminar el historial.'
            });
        }
    });
    
    // Continuar conversación
    continueConversationBtn.addEventListener('click', function() {
        if (currentConversationId) {
            window.location.href = `/conversations/${currentConversationId}/continue`;
        }
    });
    
    // Función para cargar conversaciones
    async function loadConversations() {
        try {
            const response = await fetch('/conversations', {
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                }
            });

            if (!response.ok) {
                throw new Error('Error al cargar las conversaciones');
            }

            const data = await response.json();
            conversations = data.conversations || [];

            renderConversationsList();

            // Auto-abrir conversación si viene ?open=id en la URL
            const openId = new URLSearchParams(window.location.search).get('open');
            if (openId) {
                loadConversationDetail(parseInt(openId));
            }
        } catch (error) {
            console.error('Error:', error);
            conversationsList.innerHTML = `
                <div class="alert alert-danger m-3" role="alert">
                    <i class="fa-solid fa-exclamation-triangle me-2"></i>
                    Error al cargar las conversaciones
                </div>
            `;
        }
    }
    
    // Renderizar lista de conversaciones
    function renderConversationsList() {
        if (conversations.length === 0) {
            conversationsList.innerHTML = '';
            noConversations.classList.remove('d-none');
            return;
        }
        
        noConversations.classList.add('d-none');
        conversationsList.innerHTML = '';
        
        conversations.forEach(conversation => {
            const date = new Date(conversation.created_at);
            const formattedDate = date.toLocaleDateString() + ' ' + date.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
            
            let typeClass = 'type-chat';
            let typeIcon = 'fa-comments';
            
            if (conversation.type === 'creative') {
                typeClass = 'type-creative';
                typeIcon = 'fa-lightbulb';
            } else if (conversation.type === 'image') {
                typeClass = 'type-image';
                typeIcon = 'fa-image';
            }
            
            const conversationItem = document.createElement('div');
            conversationItem.className = 'list-group-item list-group-item-action conversation-item p-3';
            conversationItem.setAttribute('data-id', conversation.id);
            conversationItem.setAttribute('data-type', conversation.type);
            
            conversationItem.innerHTML = `
                <div class="d-flex justify-content-between align-items-center">
                    <h6 class="mb-1">${window.CiriloContent.escapeHtml(conversation.title)}</h6>
                    <span class="conversation-type ${typeClass}">
                        <i class="fa-solid ${typeIcon} me-1"></i>${getTypeLabel(conversation.type)}
                    </span>
                </div>
                <p class="mb-1 text-truncate small text-muted">${window.CiriloContent.escapeHtml(getConversationPreview(conversation))}</p>
                <small class="text-muted">${formattedDate}</small>
            `;
            
            conversationItem.addEventListener('click', () => loadConversationDetail(conversation.id));
            conversationsList.appendChild(conversationItem);
        });
    }
    
    // Obtener etiqueta del tipo de conversación
    function getTypeLabel(type) {
        switch (type) {
            case 'chat': return 'Chat';
            case 'creative': return 'Creativo';
            case 'image': return 'Imagen';
            default: return 'Chat';
        }
    }
    
    // Obtener vista previa de la conversación
    function getConversationPreview(conversation) {
        try {
            if (!conversation.content) return 'Sin contenido';
            
            const content = JSON.parse(conversation.content);
            
            if (conversation.type === 'chat') {
                if (content.messages && content.messages.length > 0) {
                    return content.messages[0].content.substring(0, 50) + '...';
                }
            } else if (conversation.type === 'creative') {
                return content.prompt || 'Modo creativo';
            } else if (conversation.type === 'image') {
                return 'Análisis de imagen';
            }
            
            return 'Conversación';
        } catch (e) {
            return 'Contenido no disponible';
        }
    }
    
    // Cargar detalle de conversación
    async function loadConversationDetail(id) {
        try {
            currentConversationId = id;
            // Recuperar audio cacheado de localStorage si existe
            cachedAudioUrl = localStorage.getItem('audio_conv_' + id) || null;
            if (cachedAudioUrl) {
                speakMessageBtn.innerHTML = '<i class="fa-solid fa-play me-2"></i>Reproducir';
            } else {
                speakMessageBtn.innerHTML = '<i class="fa-solid fa-volume-up me-2"></i>Escuchar respuesta';
            }

            // Marcar como seleccionada en la lista
            document.querySelectorAll('.conversation-item').forEach(item => {
                item.classList.remove('active');
                if (item.getAttribute('data-id') == id) {
                    item.classList.add('active');
                }
            });
            
            // Mostrar cargando
            conversationDetail.classList.add('d-none');
            conversationPlaceholder.classList.remove('d-none');
            conversationPlaceholder.innerHTML = `
                <div class="text-center py-5">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Cargando...</span>
                    </div>
                    <p class="mt-3 text-muted">Cargando conversación...</p>
                </div>
            `;
            
            const response = await fetch(`/conversations/${id}`, {
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                }
            });
            
            if (!response.ok) {
                throw new Error('Error al cargar la conversación');
            }
            
            const data = await response.json();
            const conversation = data.conversation;
            
            if (!conversation) {
                throw new Error('Conversación no encontrada');
            }
            
            // Actualizar título
            conversationTitle.textContent = conversation.title;
            
            // Mostrar acciones
            conversationActions.classList.remove('d-none');
            
            // Renderizar contenido según el tipo
            let detailHtml = '';
            lastAssistantMessage = '';
            
            try {
                const content = JSON.parse(conversation.content);
                
                if (conversation.type === 'chat') {
                    if (content.messages && content.messages.length > 0) {
                        detailHtml = renderChatMessages(content.messages);
                    }
                } else if (conversation.type === 'creative') {
                    detailHtml = renderCreativeContent(content);
                } else if (conversation.type === 'image') {
                    detailHtml = renderImageAnalysis(content, conversation.image_path);
                }
            } catch (e) {
                console.error('Error parsing content:', e);
                detailHtml = '<div class="alert alert-warning">No se pudo cargar el contenido de esta conversación.</div>';
            }
            
            // Actualizar contenido
            conversationDetail.innerHTML = detailHtml;
            conversationPlaceholder.classList.add('d-none');
            conversationDetail.classList.remove('d-none');
            
            // Mostrar controles de audio si hay mensaje del asistente
            if (lastAssistantMessage) {
                audioControls.classList.remove('d-none');
            } else {
                audioControls.classList.add('d-none');
            }
            
            // Hacer scroll al final
            conversationDetail.scrollTop = conversationDetail.scrollHeight;
        } catch (error) {
            console.error('Error:', error);
            conversationPlaceholder.innerHTML = `
                <div class="alert alert-danger m-3" role="alert">
                    <i class="fa-solid fa-exclamation-triangle me-2"></i>
                    ${window.CiriloContent.escapeHtml(error.message || 'Error al cargar la conversación')}
                </div>
            `;
        }
    }
    
    // Formatear markdown de forma segura
    function renderMarkdown(text) {
        return window.CiriloContent.renderMarkdown(text);
    }

    // Renderizar mensajes de chat
    function renderChatMessages(messages) {
        let html = '';

        messages.forEach(message => {
            const isUser = message.role === 'user';
            const bubbleClass = isUser ? 'message-user' : 'message-assistant';
            const formattedContent = isUser
                ? window.CiriloContent.plainText(message.content)
                : renderMarkdown(message.content);

            html += `
                <div class="d-flex flex-column ${isUser ? '' : 'align-items-end'}">
                    <div class="message-bubble ${bubbleClass}">
                        ${formattedContent}
                    </div>
                    <div class="message-time ${isUser ? '' : 'text-end'}">
                        ${isUser ? 'Tú' : 'Cirilo'} · ${formatMessageTime(message.timestamp)}
                    </div>
                </div>
            `;

            // Guardar el último mensaje del asistente para la función de voz
            if (!isUser) {
                lastAssistantMessage = message.content;
            }
        });

        return html;
    }
    
    // Renderizar contenido creativo
    function renderCreativeContent(content) {
        lastAssistantMessage = content.assistant;
        return `
            <div class="mb-4">
                <h5>Prompt</h5>
                <div class="p-3 bg-light rounded">
                    ${window.CiriloContent.plainText(content.prompt)}
                </div>
            </div>
            <div>
                <h5>Respuesta</h5>
                <div class="p-3 bg-light rounded">
                    ${renderMarkdown(content.assistant)}
                </div>
            </div>
        `;
    }

    // Renderizar análisis de imagen
    function renderImageAnalysis(content, imagePath) {
        lastAssistantMessage = content.assistant;
        const imageHtml = imagePath
            ? `<div class="text-center mb-4">
                   <img src="/storage/${window.CiriloContent.escapeHtml(imagePath)}" class="img-fluid rounded" style="max-height: 300px;" alt="Imagen analizada">
               </div>`
            : '';
        return `
            ${imageHtml}
            <div>
                <h5>Análisis</h5>
                <div class="p-3 bg-light rounded">
                    ${renderMarkdown(content.assistant)}
                </div>
            </div>
        `;
    }
    
    // Formatear tiempo del mensaje
    function formatMessageTime(timestamp) {
        if (!timestamp) return '';
        
        const date = new Date(timestamp);
        return date.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
    }
    
    // Filtrar conversaciones por tipo
    function filterConversations(filter) {
        const items = document.querySelectorAll('.conversation-item');
        
        items.forEach(item => {
            const type = item.getAttribute('data-type');
            if (filter === 'all' || type === filter) {
                item.style.display = 'block';
            } else {
                item.style.display = 'none';
            }
        });
        
        // Actualizar mensaje de no conversaciones
        const visibleItems = document.querySelectorAll('.conversation-item[style="display: block;"]');
        if (visibleItems.length === 0 && conversations.length > 0) {
            noConversations.classList.remove('d-none');
            noConversations.innerHTML = `
                <i class="fa-solid fa-filter fa-3x mb-3"></i>
                <p>No hay conversaciones de este tipo</p>
            `;
        } else if (conversations.length === 0) {
            noConversations.classList.remove('d-none');
            noConversations.innerHTML = `
                <i class="fa-solid fa-comments fa-3x mb-3"></i>
                <p>No hay conversaciones guardadas</p>
            `;
        } else {
            noConversations.classList.add('d-none');
        }
    }
    
    // Buscar conversaciones
    function searchConversations(term) {
        const items = document.querySelectorAll('.conversation-item');
        
        items.forEach(item => {
            const title = item.querySelector('h6').textContent.toLowerCase();
            const preview = item.querySelector('p').textContent.toLowerCase();
            
            if (title.includes(term) || preview.includes(term)) {
                item.style.display = 'block';
            } else {
                item.style.display = 'none';
            }
        });
        
        // Actualizar mensaje de no conversaciones
        const visibleItems = document.querySelectorAll('.conversation-item[style="display: block;"]');
        if (visibleItems.length === 0 && conversations.length > 0) {
            noConversations.classList.remove('d-none');
            noConversations.innerHTML = `
                <i class="fa-solid fa-search fa-3x mb-3"></i>
                <p>No se encontraron resultados para "${term}"</p>
            `;
        } else {
            noConversations.classList.add('d-none');
        }
    }
    
    // Limpiar markdown del texto antes de enviarlo a TTS
    function cleanTextForTTS(text) {
        if (!text) return '';
        let clean = text.replace(/\[([^\]]+)\]\([^)]+\)/g, '$1'); // links
        clean = clean.replace(/https?:\/\/\S+/g, '');              // URLs sueltas
        clean = clean.replace(/#{1,6}\s+/gm, '');                  // encabezados
        clean = clean.replace(/\*{1,3}([^*]+)\*{1,3}/g, '$1');    // negrita/itálica
        clean = clean.replace(/`[^`]+`/g, '');                     // código inline
        clean = clean.replace(/```[\s\S]*?```/g, '');              // bloques de código
        clean = clean.trim().replace(/\s{2,}/g, ' ');
        if (clean.length > 3000) { clean = clean.substring(0, 3000) + '...'; }
        return clean;
    }

    // Forzar HTTPS si la página se sirve sobre HTTPS
    function forceHttps(url) {
        if (window.location.protocol === 'https:') {
            return url.replace(/^http:\/\//i, 'https://');
        }
        return url;
    }

    // Reproducir una URL de audio esperando que esté listo
    function playAudioUrl(url) {
        audioPlayer.src = forceHttps(url);
        audioPlayer.load();
        audioPlayer.addEventListener('canplay', function handler() {
            audioPlayer.removeEventListener('canplay', handler);
            audioPlayer.play().catch(function(e) {
                console.error('Error al reproducir el audio:', e.message);
            });
        });
    }

    // Función para convertir texto a voz
    async function speakText(text) {
        if (!text) return;

        // Si ya existe audio generado, solo reproducirlo
        if (cachedAudioUrl) {
            playAudioUrl(cachedAudioUrl);
            return;
        }

        const cleanText = cleanTextForTTS(text);
        if (!cleanText) return;

        try {
            speakMessageBtn.disabled = true;
            speakMessageBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Generando audio...';

            const response = await fetch('/text-to-speech', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({ text: cleanText })
            });

            const responseData = await response.json();

            if (response.ok && responseData.audioUrl) {
                cachedAudioUrl = responseData.audioUrl;
                localStorage.setItem('audio_conv_' + currentConversationId, cachedAudioUrl);
                speakMessageBtn.innerHTML = '<i class="fa-solid fa-play me-2"></i>Reproducir';
                playAudioUrl(cachedAudioUrl);
            } else {
                throw new Error(responseData.message || 'Error al generar el audio');
            }
        } catch (error) {
            console.error('Error:', error);
            alert('Error al generar el audio. Por favor, intenta de nuevo.');
            speakMessageBtn.innerHTML = '<i class="fa-solid fa-volume-up me-2"></i>Escuchar respuesta';
        } finally {
            speakMessageBtn.disabled = false;
        }
    }

    // Botón para escuchar mensaje
    speakMessageBtn.addEventListener('click', function() {
        speakText(lastAssistantMessage);
    });
});
</script>
@endpush
