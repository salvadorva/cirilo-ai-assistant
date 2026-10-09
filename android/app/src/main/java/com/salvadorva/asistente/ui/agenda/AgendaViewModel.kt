package com.salvadorva.asistente.ui.agenda

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.salvadorva.asistente.network.ApiClient
import com.salvadorva.asistente.network.models.AgendaEvent
import com.salvadorva.asistente.network.models.AgendaEventRequest
import com.salvadorva.asistente.util.isoToDate
import com.salvadorva.asistente.util.localParts
import com.salvadorva.asistente.util.toIso8601
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch
import java.util.Calendar

/** Borrador del formulario de crear/editar evento. */
data class EventEditor(
    val id: Int? = null,            // null = creando, no-null = editando
    val title: String = "",
    val description: String = "",
    val location: String = "",
    val startMillis: Long = defaultStart(),
    val endMillis: Long = defaultStart() + 3_600_000L,
    val allDay: Boolean = false,
    val reminderMinutes: Int = 30,
    val idempotencyKey: String = java.util.UUID.randomUUID().toString(),
) {
    val isEditing: Boolean get() = id != null
    val isValid: Boolean get() = title.isNotBlank() && endMillis >= startMillis

    companion object {
        /** Próxima hora en punto desde ahora. */
        fun defaultStart(): Long = Calendar.getInstance().apply {
            add(Calendar.HOUR_OF_DAY, 1)
            set(Calendar.MINUTE, 0)
            set(Calendar.SECOND, 0)
            set(Calendar.MILLISECOND, 0)
        }.timeInMillis
    }
}

data class AgendaUiState(
    val loading: Boolean = false,
    val saving: Boolean = false,
    val events: List<AgendaEvent> = emptyList(),
    val reminders: List<com.salvadorva.asistente.reminders.ReminderDetail> = emptyList(), // pendientes (Hermes)
    val error: String? = null,
    val editor: EventEditor? = null,    // sheet de crear/editar
    val detail: AgendaEvent? = null,    // sheet de detalle
    val highlightEventId: Int? = null,  // resaltado por deep link FCM
)

class AgendaViewModel : ViewModel() {

    private val _state = MutableStateFlow(AgendaUiState())
    val state: StateFlow<AgendaUiState> = _state.asStateFlow()

    private val api = ApiClient.agendaApi

    init {
        load()
    }

    fun load() {
        _state.value = _state.value.copy(loading = true, error = null)
        viewModelScope.launch {
            try {
                val res = api.list()
                // Los recordatorios acordados con Hermes no son eventos: se piden aparte y, si
                // fallan, la agenda se muestra igual.
                val reminders = runCatching { ApiClient.contextualReminderApi.list() }.getOrNull()
                    ?.takeIf { it.isSuccessful }?.body()?.data.orEmpty()
                    .filter { it.isPending }
                if (res.isSuccessful) {
                    val events = res.body()?.events.orEmpty()
                        .sortedBy { isoToDate(it.start_date)?.time ?: Long.MAX_VALUE }
                    _state.value = _state.value.copy(loading = false, events = events, reminders = reminders)
                } else {
                    _state.value = _state.value.copy(
                        loading = false,
                        error = "Error ${res.code()} al cargar la agenda",
                    )
                }
            } catch (e: Exception) {
                _state.value = _state.value.copy(
                    loading = false,
                    error = e.message ?: "Error de conexión",
                )
            }
        }
    }

    // ── Editor de eventos ───────────────────────────────────────────

    fun openCreate() {
        _state.value = _state.value.copy(editor = EventEditor(), detail = null)
    }

    fun openEdit(event: AgendaEvent) {
        val start = isoToDate(event.start_date)?.time ?: EventEditor.defaultStart()
        val end = isoToDate(event.end_date)?.time ?: (start + 3_600_000L)
        _state.value = _state.value.copy(
            detail = null,
            editor = EventEditor(
                id = event.id,
                title = event.title,
                description = event.description.orEmpty(),
                location = event.location.orEmpty(),
                startMillis = start,
                endMillis = end,
                allDay = event.all_day,
                reminderMinutes = event.reminder_minutes_before,
            ),
        )
    }

    fun updateEditor(transform: (EventEditor) -> EventEditor) {
        _state.value.editor?.let { current ->
            _state.value = _state.value.copy(editor = transform(current))
        }
    }

    fun closeEditor() {
        _state.value = _state.value.copy(editor = null)
    }

    fun save() {
        val editor = _state.value.editor ?: return
        if (!editor.isValid || _state.value.saving) return

        _state.value = _state.value.copy(saving = true, error = null)
        val body = AgendaEventRequest(
            title = editor.title.trim(),
            description = editor.description.trim().ifBlank { null },
            location = editor.location.trim().ifBlank { null },
            start_date = toIso8601(editor.startMillis),
            end_date = toIso8601(editor.endMillis),
            all_day = editor.allDay,
            reminder_minutes_before = editor.reminderMinutes,
        )

        viewModelScope.launch {
            try {
                val res = if (editor.id == null) {
                    api.create(editor.idempotencyKey, body)
                } else {
                    api.update(editor.id, body)
                }
                if (res.isSuccessful) {
                    _state.value = _state.value.copy(saving = false, editor = null)
                    load()
                } else {
                    _state.value = _state.value.copy(
                        saving = false,
                        error = "Error ${res.code()} al guardar",
                    )
                }
            } catch (e: Exception) {
                _state.value = _state.value.copy(
                    saving = false,
                    error = e.message ?: "Error al guardar",
                )
            }
        }
    }

    // ── Detalle / borrado ───────────────────────────────────────────

    fun openDetail(event: AgendaEvent) {
        _state.value = _state.value.copy(detail = event)
        // El listado no trae el estado de los avisos: se pide el detalle al servidor.
        viewModelScope.launch {
            try {
                val res = api.show(event.id)
                val fresh = res.body()
                if (res.isSuccessful && fresh != null && _state.value.detail?.id == event.id) {
                    _state.value = _state.value.copy(detail = fresh)
                }
            } catch (_: Exception) { /* se muestra lo que ya había */ }
        }
    }

    fun closeDetail() {
        _state.value = _state.value.copy(detail = null)
    }

    fun delete(id: Int) {
        if (_state.value.saving) return
        _state.value = _state.value.copy(saving = true, error = null)
        viewModelScope.launch {
            try {
                val res = api.delete(id)
                if (res.isSuccessful) {
                    _state.value = _state.value.copy(saving = false, detail = null)
                    load()
                } else {
                    _state.value = _state.value.copy(
                        saving = false,
                        error = "Error ${res.code()} al eliminar",
                    )
                }
            } catch (e: Exception) {
                _state.value = _state.value.copy(
                    saving = false,
                    error = e.message ?: "Error al eliminar",
                )
            }
        }
    }

    // ── Deep link desde notificación FCM ────────────────────────────

    fun openFromDeepLink(eventId: Int) {
        _state.value = _state.value.copy(highlightEventId = eventId)
        viewModelScope.launch {
            try {
                val res = api.show(eventId)
                if (res.isSuccessful) {
                    res.body()?.let { _state.value = _state.value.copy(detail = it) }
                }
            } catch (_: Exception) { /* el evento ya se resalta en la lista si está cargado */ }
        }
    }

    fun clearError() {
        _state.value = _state.value.copy(error = null)
    }
}
