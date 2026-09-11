{{-- Scripts para la funcionalidad de la sesión --}}
<script>
// Función para reproducir el audio de la sesión
function playAudioScript(sessionId) {
    const audioPlayer = document.getElementById(`audio-player-${sessionId}`);
    
    if (audioPlayer) {
        // Detener cualquier reproducción anterior
        audioPlayer.pause();
        audioPlayer.currentTime = 0;
        
        // Iniciar reproducción
        audioPlayer.play()
            .then(() => {
                console.log('Audio reproducido correctamente');
            })
            .catch(error => {
                console.error('Error al reproducir audio:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Error de reproducción',
                    text: 'No se pudo reproducir el audio. Por favor, intenta de nuevo.'
                });
            });
    } else {
        console.error('No se encontró el elemento de audio');
    }
}

// Función para enviar respuesta de la actividad práctica
function submitQuizAnswer(sessionId) {
    const selectedOption = document.querySelector('input[name="quizOption"]:checked');
    
    if (!selectedOption) {
        Swal.fire({
            icon: 'warning',
            title: 'Selecciona una opción',
            text: 'Por favor, selecciona una respuesta antes de enviar.'
        });
        return;
    }
    
    const optionIndex = selectedOption.value;
    const feedbackElement = document.getElementById('quiz-feedback');
    const feedbackMessage = document.getElementById('feedback-message');
    const submitButton = document.querySelector('button[onclick^="submitQuizAnswer"]');
    
    // Desactivar botón de envío para evitar múltiples envíos
    submitButton.disabled = true;
    submitButton.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Procesando...';
    
    // Obtener el courseId de la URL actual
    const urlParts = window.location.pathname.split('/');
    const courseId = urlParts[2]; // Asumiendo que la URL es /cursos/{courseId}/sesion/{sessionId}
    
    // Enviar la respuesta al servidor para marcar la sesión como completada
    fetch(`/cursos/${courseId}/sesion/${sessionId}/completar`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        },
        body: JSON.stringify({
            score: 100, // Puntaje por defecto
            feedback: `Opción seleccionada: ${optionIndex}`
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Mostrar mensaje de éxito
            feedbackElement.classList.remove('d-none');
            feedbackMessage.textContent = '¡Respuesta enviada correctamente!';
            
            // Mostrar mensaje de éxito con SweetAlert2
            Swal.fire({
                icon: 'success',
                title: '¡Excelente!',
                text: data.message || '¡Sesión completada exitosamente!',
                confirmButtonText: data.next_session ? 'Continuar a la siguiente sesión' : 'Volver al curso'
            }).then((result) => {
                if (result.isConfirmed) {
                    if (data.next_session) {
                        window.location.href = data.next_session.url;
                    } else {
                        window.location.href = `/cursos/${courseId}`;
                    }
                }
            });
        } else {
            // Restaurar botón en caso de error
            submitButton.disabled = false;
            submitButton.innerHTML = '<i class="fas fa-check-circle me-1"></i>Enviar Respuesta';
            
            // Mostrar mensaje de error
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: data.message || 'Ocurrió un error al procesar tu respuesta. Por favor, intenta de nuevo.'
            });
        }
    })
    .catch(error => {
        console.error('Error:', error);
        
        // Restaurar botón en caso de error
        submitButton.disabled = false;
        submitButton.innerHTML = '<i class="fas fa-check-circle me-1"></i>Enviar Respuesta';
        
        // Mostrar mensaje de error
        Swal.fire({
            icon: 'error',
            title: 'Error de conexión',
            text: 'No se pudo conectar con el servidor. Por favor, verifica tu conexión e intenta de nuevo.'
        });
    });
}

// Función para generar recursos de forma asíncrona
function generateSessionResources(courseId, sessionId, resourceType) {
    const resourceName = resourceType === 'image' ? 'imagen' : 'audio';
    const buttonId = `generate-${resourceType}-btn-${sessionId}`;
    const statusId = `${resourceType}-status-${sessionId}`;
    const button = document.getElementById(buttonId);
    const statusElement = document.getElementById(statusId);
    
    // Mostrar estado de carga
    button.disabled = true;
    button.innerHTML = `<i class="fas fa-spinner fa-spin me-1"></i>Generando ${resourceName}...`;
    statusElement.innerHTML = `Generando ${resourceName}, por favor espera...`;
    statusElement.className = 'text-warning';
    
    // Realizar petición para generar recursos
    fetch(`{{ url('/') }}/cursos/${courseId}/sesion/${sessionId}/generar-recursos`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        },
        body: JSON.stringify({
            resource_type: resourceType
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Éxito - actualizar interfaz
            statusElement.innerHTML = `${resourceName.charAt(0).toUpperCase() + resourceName.slice(1)} generada exitosamente`;
            statusElement.className = 'text-success';
            
            // Ocultar botón y mostrar mensaje de éxito
            button.style.display = 'none';
            
            // Recargar página para mostrar el recurso generado
            Swal.fire({
                icon: 'success',
                title: 'Éxito',
                text: `¡${resourceName.charAt(0).toUpperCase() + resourceName.slice(1)} generada exitosamente!`,
                confirmButtonText: 'Recargar página'
            }).then((result) => {
                if (result.isConfirmed) {
                    location.reload();
                }
            });
        } else {
            // Error - restaurar estado
            button.disabled = false;
            button.innerHTML = `<i class="fas fa-${resourceType === 'image' ? 'magic' : 'microphone'} me-1"></i>Generar ${resourceName.charAt(0).toUpperCase() + resourceName.slice(1)}`;
            statusElement.innerHTML = `Error al generar ${resourceName}`;
            statusElement.className = 'text-danger';
            
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: data.message || `Error al generar ${resourceName}`
            });
        }
    })
    .catch(error => {
        console.error('Error:', error);
        
        // Error de conexión - restaurar estado
        button.disabled = false;
        button.innerHTML = `<i class="fas fa-${resourceType === 'image' ? 'magic' : 'microphone'} me-1"></i>Generar ${resourceName.charAt(0).toUpperCase() + resourceName.slice(1)}`;
        statusElement.innerHTML = `Error de conexión al generar ${resourceName}`;
        statusElement.className = 'text-danger';
        
        Swal.fire({
            icon: 'error',
            title: 'Error de conexión',
            text: `No se pudo conectar con el servidor para generar la ${resourceName}`
        });
    });
}
</script>
