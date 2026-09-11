<!-- Scripts para el calendario -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Obtener elemento del calendario
        const calendarEl = document.getElementById('calendar');
        
        // Verificar si el elemento existe
        if (!calendarEl) {
            console.error('No se encontró el elemento del calendario');
            return;
        }
        
        // Inicializar calendario
        const calendar = new FullCalendar.Calendar(calendarEl, {
            initialView: 'dayGridMonth',
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek'
            },
            locale: 'es',
            timeZone: 'local',
            editable: true,
            selectable: true,
            selectMirror: true,
            dayMaxEvents: true,
            events: '/agenda/events',
            
            // Manejar clic en evento
            eventClick: function(info) {
                // Obtener datos del evento
                const event = info.event;
                const eventId = event.id;
                const eventTitle = event.title;
                const eventStart = event.start;
                const eventEnd = event.end;
                const eventAllDay = event.allDay;
                const eventExtendedProps = event.extendedProps;
                
                // Mostrar modal de detalles del evento
                showEventDetailsModal(eventId, eventTitle, eventStart, eventEnd, eventAllDay, eventExtendedProps);
            },
            
            // Manejar selección de fecha
            select: function(info) {
                // Mostrar modal de creación de evento
                showCreateEventModal(info.startStr, info.endStr, info.allDay);
            }
        });
        
        // Renderizar calendario
        calendar.render();
        
        // Guardar referencia global al calendario
        window.calendar = calendar;

        // Asignar eventos a los botones de la barra de herramientas personalizada
        const btnAddEvent = document.getElementById('btnAddEvent');
        const btnViewDay = document.getElementById('btnViewDay');
        const btnViewWeek = document.getElementById('btnViewWeek');
        const btnViewMonth = document.getElementById('btnViewMonth');

        if (btnAddEvent) {
            btnAddEvent.addEventListener('click', () => {
                showCreateEventModal(new Date().toISOString().split('T')[0], null, true);
            });
        }

        const viewButtons = [btnViewDay, btnViewWeek, btnViewMonth];

        function setActiveButton(activeBtn) {
            viewButtons.forEach(btn => {
                if (btn) {
                    btn.classList.remove('active');
                }
            });
            if (activeBtn) {
                activeBtn.classList.add('active');
            }
        }

        if (btnViewDay) {
            btnViewDay.addEventListener('click', () => {
                calendar.changeView('timeGridDay');
                setActiveButton(btnViewDay);
            });
        }

        if (btnViewWeek) {
            btnViewWeek.addEventListener('click', () => {
                calendar.changeView('timeGridWeek');
                setActiveButton(btnViewWeek);
            });
        }

        if (btnViewMonth) {
            btnViewMonth.addEventListener('click', () => {
                calendar.changeView('dayGridMonth');
                setActiveButton(btnViewMonth);
            });
        }

        // Función para mostrar modal de detalles del evento
        function showEventDetailsModal(eventId, eventTitle, eventStart, eventEnd, eventAllDay, eventExtendedProps) {
            console.log('Mostrando modal para evento ID:', eventId);
            
            // Obtener modal
            const modalElement = document.getElementById('eventDetailsModal');
            if (!modalElement) {
                console.error('No se encontró el modal de detalles');
                return;
            }
            
            const modal = new bootstrap.Modal(modalElement);
            
            // Actualizar título del modal
            document.getElementById('eventDetailsModalLabel').textContent = eventTitle;
            
            // Formatear fechas
            const startDate = new Date(eventStart);
            const endDate = eventEnd ? new Date(eventEnd) : null;
            
            // Formatear fecha de inicio
            const formattedStartDate = startDate.toLocaleDateString('es-ES', {
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });
            
            // Formatear hora de inicio
            const formattedStartTime = eventAllDay ? 'Todo el día' : startDate.toLocaleTimeString('es-ES', {
                hour: '2-digit',
                minute: '2-digit'
            });
            
            // Actualizar detalles del evento
            let eventDetailsHTML = `
                <p><strong>Fecha:</strong> ${formattedStartDate}</p>
                <p><strong>Hora:</strong> ${formattedStartTime}</p>
            `;
            
            // Añadir fecha de fin si existe
            if (endDate) {
                // Formatear fecha de fin
                const formattedEndDate = endDate.toLocaleDateString('es-ES', {
                    weekday: 'long',
                    year: 'numeric',
                    month: 'long',
                    day: 'numeric'
                });
                
                // Formatear hora de fin
                const formattedEndTime = eventAllDay ? 'Todo el día' : endDate.toLocaleTimeString('es-ES', {
                    hour: '2-digit',
                    minute: '2-digit'
                });
                
                // Añadir al HTML
                eventDetailsHTML += `
                    <p><strong>Hasta:</strong> ${formattedEndDate} ${formattedEndTime}</p>
                `;
            }
            
            // Añadir descripción si existe
            if (eventExtendedProps && eventExtendedProps.description) {
                eventDetailsHTML += `
                    <p><strong>Descripción:</strong> ${eventExtendedProps.description}</p>
                `;
            }
            
            // Añadir ubicación si existe
            if (eventExtendedProps && eventExtendedProps.location) {
                eventDetailsHTML += `
                    <p><strong>Ubicación:</strong> ${eventExtendedProps.location}</p>
                `;
            }
            
            // Añadir categoría si existe
            if (eventExtendedProps && eventExtendedProps.category) {
                eventDetailsHTML += `
                    <p><strong>Categoría:</strong> ${eventExtendedProps.category}</p>
                `;
            }

            // Añadir recordatorio si existe
            if (eventExtendedProps && eventExtendedProps.reminder_minutes_before > 0) {
                const mins = eventExtendedProps.reminder_minutes_before;
                let reminderLabel = '';
                if (mins >= 1440) reminderLabel = `${mins / 1440} día(s) antes`;
                else if (mins >= 60) reminderLabel = `${mins / 60} hora(s) antes`;
                else reminderLabel = `${mins} minutos antes`;
                eventDetailsHTML += `
                    <p><strong>Recordatorio:</strong> ${reminderLabel}</p>
                `;
            }

            // Actualizar contenido del modal
            document.getElementById('eventDetails').innerHTML = eventDetailsHTML;
            
            // Configurar botón de eliminar
            const deleteButton = modalElement.querySelector('#deleteEventBtn');
            if (deleteButton) {
                // Remover eventos anteriores
                const newDeleteButton = deleteButton.cloneNode(true);
                deleteButton.replaceWith(newDeleteButton);
                
                // Asignar evento con addEventListener
                newDeleteButton.addEventListener('click', function() {
                    console.log('Botón eliminar presionado para evento ID:', eventId);
                    
                    Swal.fire({
                        title: '¿Eliminar evento?',
                        text: 'Esta acción no se puede deshacer',
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#d33',
                        cancelButtonColor: '#3085d6',
                        confirmButtonText: 'Sí, eliminar',
                        cancelButtonText: 'Cancelar'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            console.log('Confirmación aceptada, eliminando evento ID:', eventId);
                            deleteEvent(eventId);
                            modal.hide();
                        }
                    });
                });
            } else {
                console.error('Botón eliminar no encontrado en el modal');
            }
            
            // Configurar botones de acción
            document.getElementById('editEventBtn').onclick = function() {
                modal.hide();
                showEditEventModal(eventId);
            };

            // ── Botones de serie (solo si el evento pertenece a una serie) ──
            const seriesId = eventExtendedProps && eventExtendedProps.series_id;
            const seriesBtns = document.getElementById('seriesActionBtns');
            if (seriesBtns) {
                if (seriesId) {
                    seriesBtns.style.display = 'flex';

                    const editSeriesBtn = document.getElementById('editSeriesBtn');
                    const newEditSeriesBtn = editSeriesBtn.cloneNode(true);
                    editSeriesBtn.replaceWith(newEditSeriesBtn);
                    newEditSeriesBtn.addEventListener('click', function() {
                        modal.hide();
                        showEditSeriesModal(seriesId, eventTitle, eventExtendedProps);
                    });

                    const deleteSeriesBtn = document.getElementById('deleteSeriesBtn');
                    const newDeleteSeriesBtn = deleteSeriesBtn.cloneNode(true);
                    deleteSeriesBtn.replaceWith(newDeleteSeriesBtn);
                    newDeleteSeriesBtn.addEventListener('click', function() {
                        Swal.fire({
                            title: '¿Eliminar toda la serie?',
                            text: 'Se eliminarán todos los eventos de esta serie. Esta acción no se puede deshacer.',
                            icon: 'warning',
                            showCancelButton: true,
                            confirmButtonColor: '#d33',
                            cancelButtonColor: '#3085d6',
                            confirmButtonText: 'Sí, eliminar serie',
                            cancelButtonText: 'Cancelar'
                        }).then((result) => {
                            if (result.isConfirmed) {
                                modal.hide();
                                deleteEventSeries(seriesId);
                            }
                        });
                    });
                } else {
                    seriesBtns.style.display = 'none';
                }
            }

            // Mostrar modal
            modal.show();
        }
        
        // Función para mostrar modal de creación de evento
        function showCreateEventModal(startStr, endStr, allDay) {
            // Obtener modal
            const modal = new bootstrap.Modal(document.getElementById('createEventModal'));
            
            // Limpiar formulario
            document.getElementById('createEventForm').reset();
            
            // Configurar fechas
            document.getElementById('createEventStartDate').value = startStr.substring(0, 10);
            document.getElementById('createEventEndDate').value = endStr ? endStr.substring(0, 10) : '';
            
            // Configurar horas si no es todo el día
            if (!allDay) {
                const startDate = new Date(startStr);
                const endDate = endStr ? new Date(endStr) : new Date(startDate.getTime() + 3600000); // +1 hora
                
                document.getElementById('createEventStartTime').value = startDate.toTimeString().substring(0, 5);
                document.getElementById('createEventEndTime').value = endDate.toTimeString().substring(0, 5);
            }
            
            // Configurar checkbox de todo el día
            document.getElementById('createEventAllDay').checked = allDay;
            
            // Mostrar/ocultar campos de hora según todo el día
            toggleTimeFields('create', allDay);
            
            // Configurar botón de guardar
            document.getElementById('saveCreateEventBtn').onclick = function() {
                // Validar formulario
                if (document.getElementById('createEventForm').checkValidity()) {
                    // Obtener datos del formulario
                    const formData = new FormData(document.getElementById('createEventForm'));
                    
                    // Convertir a objeto
                    const eventData = {};
                    formData.forEach((value, key) => {
                        eventData[key] = value;
                    });
                    
                    // Crear evento
                    createEventFromForm(eventData);
                    
                    // Cerrar modal
                    modal.hide();
                } else {
                    // Mostrar validación
                    document.getElementById('createEventForm').classList.add('was-validated');
                }
            };
            
            // Mostrar modal
            modal.show();
        }
        
        // Función para mostrar modal de edición de evento
        function showEditEventModal(eventId) {
            // Obtener modal
            const modal = new bootstrap.Modal(document.getElementById('editEventModal'));
            
            // Mostrar indicador de carga
            document.getElementById('editEventForm').innerHTML = `
                <div class="text-center p-5">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Cargando...</span>
                    </div>
                    <p class="mt-2">Cargando detalles del evento...</p>
                </div>
            `;
            
            // Obtener detalles del evento
            fetch(`/agenda/events/${eventId}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`Error del servidor: ${response.status} ${response.statusText}`);
                    }
                    return response.json();
                })
                .then(data => {
                    // Verificar si hay datos
                    if (!data.event) {
                        throw new Error('No se encontraron datos del evento');
                    }
                    
                    // Obtener evento
                    const event = data.event;
                    
                    // Actualizar formulario
                    document.getElementById('editEventForm').innerHTML = `
                        <div class="mb-3">
                            <label for="editEventTitle" class="form-label">Título</label>
                            <input type="text" class="form-control" id="editEventTitle" name="title" value="${event.title || ''}" required>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="editEventStartDate" class="form-label">Fecha de inicio</label>
                                <input type="date" class="form-control" id="editEventStartDate" name="start_date" value="${event.start_date || ''}" required>
                            </div>
                            <div class="col-md-6">
                                <label for="editEventEndDate" class="form-label">Fecha de fin</label>
                                <input type="date" class="form-control" id="editEventEndDate" name="end_date" value="${event.end_date || ''}">
                            </div>
                        </div>
                        
                        <div class="row mb-3" id="editEventTimeFields">
                            <div class="col-md-6">
                                <label for="editEventStartTime" class="form-label">Hora de inicio</label>
                                <input type="time" class="form-control" id="editEventStartTime" name="start_time" value="${event.start_time || ''}">
                            </div>
                            <div class="col-md-6">
                                <label for="editEventEndTime" class="form-label">Hora de fin</label>
                                <input type="time" class="form-control" id="editEventEndTime" name="end_time" value="${event.end_time || ''}">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="editEventAllDay" name="all_day" ${event.all_day ? 'checked' : ''}>
                                <label class="form-check-label" for="editEventAllDay">
                                    Todo el día
                                </label>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="editEventDescription" class="form-label">Descripción</label>
                            <textarea class="form-control" id="editEventDescription" name="description" rows="2">${event.description || ''}</textarea>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="editEventCategory" class="form-label">Categoría</label>
                                <select class="form-select" id="editEventCategory" name="category">
                                    <option value="general" ${event.category === 'general' ? 'selected' : ''}>General</option>
                                    <option value="trabajo" ${event.category === 'trabajo' ? 'selected' : ''}>Trabajo</option>
                                    <option value="personal" ${event.category === 'personal' ? 'selected' : ''}>Personal</option>
                                    <option value="salud" ${event.category === 'salud' ? 'selected' : ''}>Salud</option>
                                    <option value="educacion" ${event.category === 'educacion' ? 'selected' : ''}>Educación</option>
                                    <option value="social" ${event.category === 'social' ? 'selected' : ''}>Social</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="editEventLocation" class="form-label">Ubicación</label>
                                <input type="text" class="form-control" id="editEventLocation" name="location" value="${event.location || ''}">
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="editEventColor" class="form-label">Color</label>
                                <input type="color" class="form-control form-control-color" id="editEventColor" name="color" value="${event.color || '#3788d8'}">
                            </div>
                            <div class="col-md-6">
                                <label for="editEventReminder" class="form-label">Recordatorio</label>
                                <select class="form-select" id="editEventReminder" name="reminder_minutes_before">
                                    <option value="0"   ${(event.reminder_minutes_before ?? 0) == 0   ? 'selected' : ''}>Sin recordatorio</option>
                                    <option value="5"   ${(event.reminder_minutes_before ?? 0) == 5   ? 'selected' : ''}>5 minutos antes</option>
                                    <option value="10"  ${(event.reminder_minutes_before ?? 0) == 10  ? 'selected' : ''}>10 minutos antes</option>
                                    <option value="15"  ${(event.reminder_minutes_before ?? 0) == 15  ? 'selected' : ''}>15 minutos antes</option>
                                    <option value="30"  ${(event.reminder_minutes_before ?? 0) == 30  ? 'selected' : ''}>30 minutos antes</option>
                                    <option value="60"  ${(event.reminder_minutes_before ?? 0) == 60  ? 'selected' : ''}>1 hora antes</option>
                                    <option value="120" ${(event.reminder_minutes_before ?? 0) == 120 ? 'selected' : ''}>2 horas antes</option>
                                    <option value="1440"${(event.reminder_minutes_before ?? 0) == 1440? 'selected' : ''}>1 día antes</option>
                                </select>
                            </div>
                        </div>

                        <input type="hidden" name="id" value="${event.id}">
                    `;
                    
                    // Configurar evento para checkbox de todo el día
                    document.getElementById('editEventAllDay').addEventListener('change', function() {
                        toggleTimeFields('edit', this.checked);
                    });
                    
                    // Mostrar/ocultar campos de hora según todo el día
                    toggleTimeFields('edit', event.all_day);
                })
                .catch(error => {
                    console.error('Error al obtener detalles del evento:', error);
                    
                    // Mostrar mensaje de error
                    document.getElementById('editEventForm').innerHTML = `
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            Error al cargar los detalles del evento: ${error.message}
                        </div>
                    `;
                });
            
            // Configurar botón de guardar
            document.getElementById('saveEditEventBtn').onclick = function() {
                // Validar formulario
                if (document.getElementById('editEventForm').checkValidity()) {
                    // Obtener datos del formulario
                    const formData = new FormData(document.getElementById('editEventForm'));
                    
                    // Convertir a objeto
                    const eventData = {};
                    formData.forEach((value, key) => {
                        eventData[key] = value;
                    });
                    
                    // Actualizar evento
                    updateEvent(eventData);
                    
                    // Cerrar modal
                    modal.hide();
                } else {
                    // Mostrar validación
                    document.getElementById('editEventForm').classList.add('was-validated');
                }
            };
            
            // Mostrar modal
            modal.show();
        }
        
        // Función para mostrar/ocultar campos de hora
        function toggleTimeFields(prefix, allDay) {
            const timeFields = document.getElementById(`${prefix}EventTimeFields`);
            
            if (allDay) {
                timeFields.classList.add('d-none');
            } else {
                timeFields.classList.remove('d-none');
            }
        }
        
        // Configurar eventos para checkboxes de todo el día
        document.getElementById('createEventAllDay').addEventListener('change', function() {
            toggleTimeFields('create', this.checked);
        });
        
        // Función para crear evento desde formulario
        function createEventFromForm(eventData) {
            const csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

            // Leer si el usuario eligió sincronizar con Nextcloud
            const syncTarget = document.querySelector('input[name="sync_target"]:checked')?.value ?? 'personal';

            fetch('/agenda/events', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
                body: JSON.stringify(eventData)
            })
            .then(response => {
                if (!response.ok) throw new Error(`Error del servidor: ${response.status}`);
                return response.json();
            })
            .then(async data => {
                if (data.success) {
                    showToast('Evento creado correctamente', 'success');
                    calendar.refetchEvents();

                    // Sincronizar con Nextcloud si el usuario eligió "trabajo"
                    if (syncTarget === 'trabajo' && data.event) {
                        const url = data.event.series_id
                            ? `/agenda/series/${data.event.series_id}/sync-nextcloud`
                            : `/agenda/events/${data.event.id}/sync-nextcloud`;
                        try {
                            const res  = await fetch(url, {
                                method: 'POST',
                                headers: { 'X-CSRF-TOKEN': csrf, 'Content-Type': 'application/json' }
                            });
                            const sync = await res.json();
                            showToast(
                                sync.success || sync.nextcloud_synced
                                    ? 'Sincronizado con Nextcloud ☁'
                                    : 'Guardado. Error al sincronizar con Nextcloud',
                                sync.success || sync.nextcloud_synced ? 'success' : 'warning'
                            );
                        } catch (e) {
                            showToast('Guardado. No se pudo conectar con Nextcloud', 'warning');
                        }
                    }
                } else {
                    showToast('Error: ' + (data.message || 'No se pudo crear el evento'), 'error');
                }
            })
            .catch(error => {
                console.error('Error al crear evento:', error);
                showToast('Error al crear el evento: ' + error.message, 'error');
            });
        }
        
        // Función para actualizar evento
        function updateEvent(eventData) {
            // Enviar solicitud a la API
            fetch(`/agenda/events/${eventData.id}`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify(eventData)
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`Error del servidor: ${response.status} ${response.statusText}`);
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    // Mostrar mensaje de éxito
                    showToast('Evento actualizado correctamente', 'success');
                    
                    // Actualizar calendario
                    calendar.refetchEvents();
                } else {
                    // Mostrar mensaje de error
                    showToast('Error: ' + (data.message || 'No se pudo actualizar el evento'), 'error');
                }
            })
            .catch(error => {
                console.error('Error al actualizar evento:', error);
                
                // Mostrar mensaje de error
                showToast('Error al actualizar el evento: ' + error.message, 'error');
            });
        }
        
        // Función para eliminar evento
        function deleteEvent(eventId) {
            console.log('Iniciando eliminación del evento ID:', eventId);
            
            // Verificar que tenemos un ID válido
            if (!eventId) {
                console.error('Error: ID de evento no válido');
                showToast('Error: ID de evento no válido', 'error');
                return;
            }
            
            // Obtener el token CSRF
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            if (!csrfToken) {
                console.error('Error: No se encontró el token CSRF');
                showToast('Error: No se encontró el token CSRF', 'error');
                return;
            }
            
            console.log('Enviando solicitud DELETE a /agenda/events/' + eventId);
            
            // Enviar solicitud a la API
            fetch(`/agenda/events/${eventId}`, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json',
                    'Content-Type': 'application/json'
                }
            })
            .then(response => {
                console.log('Respuesta recibida:', response.status, response.statusText);
                
                if (!response.ok) {
                    throw new Error(`Error del servidor: ${response.status} ${response.statusText}`);
                }
                return response.json();
            })
            .then(data => {
                console.log('Datos de respuesta:', data);
                
                if (data.success) {
                    // Actualizar calendario
                    calendar.refetchEvents();
                    
                    // Mostrar mensaje de éxito
                    showToast('Evento eliminado correctamente', 'success');
                } else {
                    // Mostrar mensaje de error
                    showToast('Error: ' + (data.message || 'No se pudo eliminar el evento'), 'error');
                }
            })
            .catch(error => {
                console.error('Error al eliminar evento:', error);
                
                // Mostrar mensaje de error
                showToast('Error al eliminar el evento: ' + error.message, 'error');
            });
        }
        
        // ── Editar serie completa ──────────────────────────────────────────
        function showEditSeriesModal(seriesId, eventTitle, props) {
            const modal = new bootstrap.Modal(document.getElementById('editSeriesModal'));
            const form  = document.getElementById('editSeriesForm');

            // Pre-poblar con los datos del evento clickeado
            document.getElementById('editSeriesTitle').value       = eventTitle || '';
            document.getElementById('editSeriesDescription').value = props.description || '';
            document.getElementById('editSeriesCategory').value    = props.category || 'general';
            document.getElementById('editSeriesLocation').value    = props.location || '';
            document.getElementById('editSeriesColor').value       = props.color || '#3788d8';
            document.getElementById('editSeriesReminder').value    = props.reminder_minutes_before ?? 0;
            document.getElementById('editSeriesStartTime').value   = '';
            document.getElementById('editSeriesEndTime').value     = '';

            document.getElementById('saveEditSeriesBtn').onclick = function() {
                if (!form.checkValidity()) {
                    form.classList.add('was-validated');
                    return;
                }
                const data = {
                    title:                   document.getElementById('editSeriesTitle').value,
                    description:             document.getElementById('editSeriesDescription').value,
                    category:                document.getElementById('editSeriesCategory').value,
                    location:                document.getElementById('editSeriesLocation').value,
                    color:                   document.getElementById('editSeriesColor').value,
                    reminder_minutes_before: document.getElementById('editSeriesReminder').value,
                    start_time:              document.getElementById('editSeriesStartTime').value || null,
                    end_time:                document.getElementById('editSeriesEndTime').value || null,
                };
                modal.hide();
                updateEventSeries(seriesId, data);
            };

            modal.show();
        }

        function updateEventSeries(seriesId, data) {
            fetch(`/agenda/series/${seriesId}`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify(data)
            })
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    showToast(res.message, 'success');
                    calendar.refetchEvents();
                } else {
                    showToast('Error: ' + (res.message || 'No se pudo actualizar la serie'), 'error');
                }
            })
            .catch(e => showToast('Error al actualizar la serie: ' + e.message, 'error'));
        }

        function deleteEventSeries(seriesId) {
            fetch(`/agenda/series/${seriesId}`, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'Accept': 'application/json'
                }
            })
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    showToast(res.message, 'success');
                    calendar.refetchEvents();
                } else {
                    showToast('Error: ' + (res.message || 'No se pudo eliminar la serie'), 'error');
                }
            })
            .catch(e => showToast('Error al eliminar la serie: ' + e.message, 'error'));
        }

        // Función para mostrar notificaciones toast
        function showToast(message, type = 'info') {
            // Crear elemento toast
            const toast = document.createElement('div');
            toast.className = `alert alert-${type === 'success' ? 'success' : 'danger'} alert-dismissible fade show position-fixed`;
            toast.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
            
            toast.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            `;
            
            // Agregar al body
            document.body.appendChild(toast);
            
            // Auto-remover después de 5 segundos
            setTimeout(() => {
                if (toast.parentNode) {
                    toast.parentNode.removeChild(toast);
                }
            }, 5000);
        }
    });
</script>
