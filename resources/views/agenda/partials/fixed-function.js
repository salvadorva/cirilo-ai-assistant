function processVoiceRequest() {
    console.log('Iniciando processVoiceRequest');
    
    // Obtener referencias a elementos clave
    const userInput = document.getElementById('simpleUserVoiceInput');
    const processBtn = document.getElementById('simpleProcessVoiceRequest');
    const responseText = document.getElementById('simpleResponseText');
    const badgeContainer = document.getElementById('simpleBadgeContainer');
    const playButtonContainer = document.getElementById('simplePlayButtonContainer');
    const playButton = document.getElementById('simplePlayAudioButton');
    
    if (!userInput) {
        console.error('Error: No se encontró el campo de entrada simpleUserVoiceInput');
        showToast('Error: No se encontró el campo de entrada', 'error', 5000);
        return;
    }
    
    // Actualizar estado del botón de procesamiento
    if (processBtn) {
        processBtn.disabled = true;
        processBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-2"></i>Procesando...';
    }
    
    // Mostrar mensaje de procesamiento
    if (responseText) {
        responseText.innerHTML = '<div class="alert alert-info" style="color: #0c5460 !important; background-color: #d1ecf1 !important; border-color: #bee5eb !important;">Procesando tu solicitud...</div>';
    }
    
    const input = userInput.value.trim();
    console.log('Texto a procesar:', input);
    
    if (!input) {
        showToast('Por favor, introduce un texto o usa el micrófono.', 'warning', 5000);
        
        // Restablecer botón si existe
        if (processBtn) {
            processBtn.disabled = false;
            processBtn.innerHTML = '<i class="fa-solid fa-paper-plane me-2"></i>Procesar Solicitud';
        }
        
        return;
    }
    
    // Detectar si es una consulta de eventos
    const isEventQuery = input.toLowerCase().includes('eventos de hoy') || 
                         input.toLowerCase().includes('consultar mis eventos') || 
                         input.toLowerCase().includes('agenda de hoy') ||
                         input.toLowerCase().includes('calendario de hoy') ||
                         input.toLowerCase().includes('eventos para hoy') ||
                         input.toLowerCase().includes('qué tengo hoy');
    
    console.log('¿Es consulta de eventos?', isEventQuery);
    
    // Si es una consulta de eventos, manejarla directamente para evitar problemas con OpenAI
    if (isEventQuery) {
        console.log('Manejando consulta de eventos directamente en el frontend');
        
        // Mostrar mensaje de procesamiento
        if (responseText) {
            responseText.innerHTML = '<div class="alert alert-info" style="color: #0c5460 !important; background-color: #d1ecf1 !important; border-color: #bee5eb !important;">Consultando tus eventos de hoy...</div>';
        }
        
        // Mostrar badge con estilo forzado para visibilidad
        if (badgeContainer) {
            badgeContainer.innerHTML = `<span class="badge badge-success badge-lg" style="color: #000000 !important; font-weight: bold !important; background-color: #d4edda !important;">consulta</span>`;
        }
        
        // Fecha de hoy para la consulta
        const today = new Date().toISOString().split('T')[0];
        
        // Intentar obtener eventos directamente mediante fetch
        fetch('/agenda/events?date=' + today, {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => {
            console.log('Respuesta de eventos recibida:', response.status);
            if (!response.ok) {
                throw new Error(`Error HTTP: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            console.log('Eventos recibidos:', data);
            
            // Procesar los eventos
            let message = '';
            if (data.length === 0) {
                message = 'No tienes eventos programados para hoy.';
            } else {
                message = `Tienes ${data.length} evento${data.length > 1 ? 's' : ''} para hoy: `;
                data.forEach((event, index) => {
                    const hora = event.hora ? event.hora : 'Sin hora específica';
                    message += `${index + 1}. ${event.titulo} a las ${hora}. `;
                });
            }
            
            console.log('Mensaje a mostrar:', message);
            
            // Mostrar el mensaje en el modal
            if (responseText) {
                responseText.innerHTML = `<div class="alert alert-success" style="color: #155724 !important; background-color: #d4edda !important; border-color: #c3e6cb !important;">${message}</div>`;
            }
            
            // Intentar reproducir el mensaje con síntesis de voz
            try {
                const utterance = new SpeechSynthesisUtterance(message);
                utterance.lang = 'es-ES';
                speechSynthesis.speak(utterance);
                console.log('Reproduciendo mensaje con síntesis de voz');
                
                // Mostrar botón de reproducción como respaldo
                if (playButtonContainer && playButton) {
                    playButtonContainer.style.display = 'block';
                    playButton.style.color = '#0000ff';
                    playButton.textContent = '▶️ REPRODUCIR AUDIO (Clic aquí si no escuchas)';
                    playButton.onclick = function() {
                        const utterance = new SpeechSynthesisUtterance(message);
                        utterance.lang = 'es-ES';
                        speechSynthesis.speak(utterance);
                        showToast('Reproduciendo audio...', 'info', 3000);
                    };
                    showToast('Haz clic en el botón azul si no escuchas el audio', 'info', 8000);
                }
            } catch (error) {
                console.error('Error al reproducir audio con síntesis de voz:', error);
                showToast('No se pudo reproducir el audio automáticamente', 'warning', 8000);
            }
            
            // Restablecer botón (si existe)
            if (processBtn) {
                processBtn.disabled = false;
                processBtn.innerHTML = '<i class="fa-solid fa-paper-plane me-2"></i>Procesar Solicitud';
            }
        })
        .catch(error => {
            console.error('Error al obtener eventos:', error);
            showToast('Error al obtener eventos: ' + error.message, 'error', 8000);
            
            // Restablecer botón si existe
            if (processBtn) {
                processBtn.disabled = false;
                processBtn.innerHTML = '<i class="fa-solid fa-paper-plane me-2"></i>Procesar Solicitud';
            }
            
            // Mostrar mensaje de error en el modal
            if (responseText) {
                responseText.innerHTML = `<div class="alert alert-danger" style="color: #721c24 !important; background-color: #f8d7da !important; border-color: #f5c6cb !important;">
                    Error al obtener eventos. Intentando redirigir a la página de agenda...
                </div>`;
            }
            
            // Redirigir a la página de eventos como alternativa
            setTimeout(() => {
                window.location.href = '/agenda?date=' + today;
            }, 2000);
        });
        
        return; // Detener el flujo normal para evitar llamar a la API de OpenAI
    }
    
    // Obtener el token CSRF con manejo de errores
    let csrfToken = '';
    try {
        const csrfMeta = document.querySelector('meta[name="csrf-token"]');
        if (csrfMeta) {
            csrfToken = csrfMeta.getAttribute('content');
        } else {
            console.error('No se encontró el token CSRF');
            showToast('Error: No se encontró el token CSRF', 'error', 5000);
            
            // Restablecer botón si existe
            if (processBtn) {
                processBtn.disabled = false;
                processBtn.innerHTML = '<i class="fa-solid fa-paper-plane me-2"></i>Procesar Solicitud';
            }
            return;
        }
    } catch (error) {
        console.error('Error al obtener el token CSRF:', error);
        showToast('Error al procesar la solicitud', 'error', 5000);
        
        // Restablecer botón si existe
        if (processBtn) {
            processBtn.disabled = false;
            processBtn.innerHTML = '<i class="fa-solid fa-paper-plane me-2"></i>Procesar Solicitud';
        }
        return;
    }
    
    // Preparar los datos para la solicitud
    const data = {
        user_input: input,
        step: 'initial',
        conversation_context: [],
        partial_event: {}
    };
    
    // Si estamos en un flujo de conversación (como crear evento), usar el contexto actual
    if (conversationContext && conversationContext.length > 0) {
        const lastContext = conversationContext[conversationContext.length - 1];
        data.step = lastContext.step || 'initial';
        data.partial_event = lastContext.partial_event || {};
        data.conversation_context = conversationContext;
        console.log('Continuando flujo de conversación:', data.step);
        console.log('Evento parcial:', data.partial_event);
    }
    
    console.log('Datos a enviar:', data);
    console.log('URL de la petición:', window.location.origin + '/agenda/process-voice');
    
    // Enviar solicitud al servidor
    fetch('/agenda/process-voice', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken,
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json'
        },
        body: JSON.stringify(data)
    })
    .then(response => {
        console.log('Respuesta recibida del servidor:', response.status, response.statusText);
        if (!response.ok) {
            throw new Error(`Error HTTP: ${response.status} ${response.statusText}`);
        }
        return response.json();
    })
    .then(data => {
        console.log('Datos recibidos del servidor:', data);
        
        // Actualizar el contexto de la conversación si es necesario
        if (data.conversation_context) {
            conversationContext = data.conversation_context;
            console.log('Contexto de conversación actualizado:', conversationContext);
        }
        
        // Manejar la respuesta del servidor
        handleServerResponse(data);
        
        // Restablecer el botón de procesamiento
        if (processBtn) {
            processBtn.disabled = false;
            processBtn.innerHTML = '<i class="fa-solid fa-paper-plane me-2"></i>Procesar Solicitud';
        }
    })
    .catch(error => {
        console.error('Error al procesar la solicitud:', error);
        showToast('Error al procesar la solicitud: ' + error.message, 'error', 8000);
        
        // Mostrar mensaje de error en el modal
        if (responseText) {
            responseText.innerHTML = `<div class="alert alert-danger" style="color: #721c24 !important; background-color: #f8d7da !important; border-color: #f5c6cb !important;">
                Error al procesar la solicitud: ${error.message}
            </div>`;
        }
        
        // Restablecer el botón de procesamiento
        if (processBtn) {
            processBtn.disabled = false;
            processBtn.innerHTML = '<i class="fa-solid fa-paper-plane me-2"></i>Procesar Solicitud';
        }
    });
}
