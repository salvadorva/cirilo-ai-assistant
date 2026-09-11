<!-- Modal para detalles del evento -->
<div class="modal fade" id="eventDetailsModal" tabindex="-1" aria-labelledby="eventDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="eventDetailsModalLabel">Detalles del Evento</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="eventDetails"></div>
            </div>
            <div class="modal-footer flex-column align-items-stretch gap-2">
                {{-- Acciones sobre el evento individual --}}
                <div class="d-flex gap-2 justify-content-end">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                    <button type="button" id="editEventBtn" class="btn btn-primary">
                        <i class="fa-solid fa-edit me-1"></i>Editar este evento
                    </button>
                    <button type="button" id="deleteEventBtn" class="btn btn-danger">
                        <i class="fa-solid fa-trash me-1"></i>Eliminar
                    </button>
                </div>
                {{-- Acciones sobre la serie (ocultas si no es parte de una serie) --}}
                <div id="seriesActionBtns" class="gap-2 justify-content-end" style="display:none;border-top:1px solid #dee2e6;padding-top:0.5rem;">
                    <span class="text-muted me-auto" style="font-size:0.82rem;align-self:center;">
                        <i class="fa-solid fa-layer-group me-1"></i>Evento recurrente:
                    </span>
                    <button type="button" id="editSeriesBtn" class="btn btn-sm btn-outline-primary">
                        <i class="fa-solid fa-pencil me-1"></i>Editar toda la serie
                    </button>
                    <button type="button" id="deleteSeriesBtn" class="btn btn-sm btn-outline-danger">
                        <i class="fa-solid fa-trash me-1"></i>Eliminar toda la serie
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal para crear evento -->
<div class="modal fade" id="createEventModal" tabindex="-1" aria-labelledby="createEventModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="createEventModalLabel">Crear Evento</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="createEventForm" class="needs-validation" novalidate>
                    <div class="mb-3">
                        <label for="createEventTitle" class="form-label">Título</label>
                        <input type="text" class="form-control" id="createEventTitle" name="title" required>
                        <div class="invalid-feedback">
                            Por favor, ingresa un título para el evento.
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="createEventStartDate" class="form-label">Fecha de inicio</label>
                            <input type="date" class="form-control" id="createEventStartDate" name="start_date" required>
                            <div class="invalid-feedback">
                                Por favor, selecciona una fecha de inicio.
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label for="createEventEndDate" class="form-label">Fecha de fin</label>
                            <input type="date" class="form-control" id="createEventEndDate" name="end_date">
                        </div>
                    </div>
                    
                    <div class="row mb-3" id="createEventTimeFields">
                        <div class="col-md-6">
                            <label for="createEventStartTime" class="form-label">Hora de inicio</label>
                            <input type="time" class="form-control" id="createEventStartTime" name="start_time">
                        </div>
                        <div class="col-md-6">
                            <label for="createEventEndTime" class="form-label">Hora de fin</label>
                            <input type="time" class="form-control" id="createEventEndTime" name="end_time">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="createEventAllDay" name="all_day">
                            <label class="form-check-label" for="createEventAllDay">
                                Todo el día
                            </label>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="createEventDescription" class="form-label">Descripción</label>
                        <textarea class="form-control" id="createEventDescription" name="description" rows="3"></textarea>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="createEventCategory" class="form-label">Categoría</label>
                            <select class="form-select" id="createEventCategory" name="category">
                                <option value="general">General</option>
                                <option value="trabajo">Trabajo</option>
                                <option value="personal">Personal</option>
                                <option value="salud">Salud</option>
                                <option value="educacion">Educación</option>
                                <option value="social">Social</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="createEventLocation" class="form-label">Ubicación</label>
                            <input type="text" class="form-control" id="createEventLocation" name="location">
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="createEventColor" class="form-label">Color</label>
                            <input type="color" class="form-control form-control-color" id="createEventColor" name="color" value="#3788d8">
                        </div>
                        <div class="col-md-6">
                            <label for="createEventReminder" class="form-label">Recordatorio (minutos antes)</label>
                            <input type="number" class="form-control" id="createEventReminder" name="reminder_minutes_before" value="10" min="0">
                        </div>
                    </div>

                    @if(Auth::check() && Auth::user()->hasNextcloud())
                    <div class="mb-1">
                        <label class="form-label mb-1 small text-muted">
                            <i class="fas fa-cloud me-1"></i>¿Dónde guardar?
                        </label>
                        <div class="d-flex gap-2">
                            <input type="radio" class="btn-check" name="sync_target" id="syncPersonal" value="personal" checked>
                            <label class="btn btn-sm btn-outline-secondary" for="syncPersonal">
                                <i class="fas fa-user me-1"></i>Personal
                            </label>
                            <input type="radio" class="btn-check" name="sync_target" id="syncTrabajo" value="trabajo">
                            <label class="btn btn-sm btn-outline-primary" for="syncTrabajo">
                                <i class="fas fa-briefcase me-1"></i>Trabajo → Nextcloud
                            </label>
                        </div>
                    </div>
                    @endif
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" id="saveCreateEventBtn" class="btn btn-primary">
                    <i class="fa-solid fa-save me-2"></i>Guardar
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal para editar evento -->
<div class="modal fade" id="editEventModal" tabindex="-1" aria-labelledby="editEventModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editEventModalLabel">Editar Evento</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="editEventForm" class="needs-validation" novalidate>
                    <!-- El contenido se cargará dinámicamente -->
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" id="saveEditEventBtn" class="btn btn-primary">
                    <i class="fa-solid fa-save me-2"></i>Guardar
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal para editar toda la serie de eventos recurrentes -->
<div class="modal fade" id="editSeriesModal" tabindex="-1" aria-labelledby="editSeriesModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editSeriesModalLabel">
                    <i class="fa-solid fa-layer-group me-2"></i>Editar toda la serie
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info py-2 mb-3" style="font-size:0.85rem;">
                    <i class="fa-solid fa-circle-info me-1"></i>
                    Los cambios se aplicarán a <strong>todos los eventos</strong> de esta serie.
                    Las fechas individuales no cambian; si modificas la hora, se actualizará en todos.
                </div>
                <form id="editSeriesForm" class="needs-validation" novalidate>
                    <div class="mb-3">
                        <label for="editSeriesTitle" class="form-label">Título</label>
                        <input type="text" class="form-control" id="editSeriesTitle" name="title" required>
                    </div>
                    <div class="mb-3">
                        <label for="editSeriesDescription" class="form-label">Descripción</label>
                        <textarea class="form-control" id="editSeriesDescription" name="description" rows="2"></textarea>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="editSeriesCategory" class="form-label">Categoría</label>
                            <select class="form-select" id="editSeriesCategory" name="category">
                                <option value="general">General</option>
                                <option value="trabajo">Trabajo</option>
                                <option value="personal">Personal</option>
                                <option value="salud">Salud</option>
                                <option value="educacion">Educación</option>
                                <option value="social">Social</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="editSeriesLocation" class="form-label">Ubicación</label>
                            <input type="text" class="form-control" id="editSeriesLocation" name="location">
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="editSeriesColor" class="form-label">Color</label>
                            <input type="color" class="form-control form-control-color" id="editSeriesColor" name="color" value="#3788d8">
                        </div>
                        <div class="col-md-6">
                            <label for="editSeriesReminder" class="form-label">Recordatorio</label>
                            <select class="form-select" id="editSeriesReminder" name="reminder_minutes_before">
                                <option value="0">Sin recordatorio</option>
                                <option value="5">5 minutos antes</option>
                                <option value="10">10 minutos antes</option>
                                <option value="15">15 minutos antes</option>
                                <option value="30">30 minutos antes</option>
                                <option value="60">1 hora antes</option>
                                <option value="120">2 horas antes</option>
                                <option value="1440">1 día antes</option>
                            </select>
                        </div>
                    </div>
                    <div class="row mb-1">
                        <div class="col-md-6">
                            <label for="editSeriesStartTime" class="form-label">
                                Nueva hora de inicio <span class="text-muted">(opcional)</span>
                            </label>
                            <input type="time" class="form-control" id="editSeriesStartTime" name="start_time">
                        </div>
                        <div class="col-md-6">
                            <label for="editSeriesEndTime" class="form-label">
                                Nueva hora de fin <span class="text-muted">(opcional)</span>
                            </label>
                            <input type="time" class="form-control" id="editSeriesEndTime" name="end_time">
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" id="saveEditSeriesBtn" class="btn btn-primary">
                    <i class="fa-solid fa-save me-1"></i>Guardar cambios en la serie
                </button>
            </div>
        </div>
    </div>
</div>
