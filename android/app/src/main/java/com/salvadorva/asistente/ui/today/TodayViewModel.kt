package com.salvadorva.asistente.ui.today

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.salvadorva.asistente.network.ApiClient
import com.salvadorva.asistente.network.TodayApi
import com.salvadorva.asistente.network.models.TaskCreateRequest
import com.salvadorva.asistente.network.models.TaskUpdateRequest
import com.salvadorva.asistente.network.models.TodayResponse
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch
import java.text.SimpleDateFormat
import java.util.Calendar
import java.util.Locale

data class TodayUiState(
    val loading: Boolean = false,
    val busyTaskId: Int? = null,
    val today: TodayResponse? = null,
    val error: String? = null,
    val newTask: String = "",
)

/** F6: compromisos y pendientes del día; cada acción recarga desde el servidor (fuente de verdad). */
class TodayViewModel(private val api: TodayApi = ApiClient.todayApi) : ViewModel() {

    private val _state = MutableStateFlow(TodayUiState())
    val state: StateFlow<TodayUiState> = _state.asStateFlow()

    init {
        load()
    }

    fun load() {
        _state.value = _state.value.copy(loading = true, error = null)
        viewModelScope.launch {
            try {
                val res = api.today()
                _state.value = if (res.isSuccessful) {
                    _state.value.copy(loading = false, today = res.body())
                } else {
                    _state.value.copy(loading = false, error = "No se pudo cargar tu día (${res.code()})")
                }
            } catch (e: Exception) {
                _state.value = _state.value.copy(loading = false, error = "Sin conexión. Intenta de nuevo.")
            }
        }
    }

    fun complete(id: Int) = update(id, TaskUpdateRequest("complete"))
    fun postponeToTomorrow(id: Int) = update(id, TaskUpdateRequest("postpone", tomorrow()))
    fun dismiss(id: Int) = update(id, TaskUpdateRequest("dismiss"))
    fun accept(id: Int) = update(id, TaskUpdateRequest("accept"))

    fun onNewTaskChange(text: String) {
        _state.value = _state.value.copy(newTask = text)
    }

    fun addTask() {
        val title = _state.value.newTask.trim()
        if (title.isEmpty()) return
        viewModelScope.launch {
            try {
                val res = api.createTask(TaskCreateRequest(title))
                if (res.isSuccessful) {
                    _state.value = _state.value.copy(newTask = "")
                    load()
                } else {
                    _state.value = _state.value.copy(error = "No se pudo guardar el pendiente (${res.code()})")
                }
            } catch (e: Exception) {
                _state.value = _state.value.copy(error = "Sin conexión. El pendiente no se guardó.")
            }
        }
    }

    // java.time requiere API 26 y la app admite desde la 24.
    private fun tomorrow(): String = SimpleDateFormat("yyyy-MM-dd", Locale.US)
        .format(Calendar.getInstance().apply { add(Calendar.DAY_OF_MONTH, 1) }.time)

    private fun update(id: Int, body: TaskUpdateRequest) {
        _state.value = _state.value.copy(busyTaskId = id, error = null)
        viewModelScope.launch {
            try {
                val res = api.updateTask(id, body)
                _state.value = _state.value.copy(busyTaskId = null)
                if (res.isSuccessful) load() else _state.value = _state.value.copy(error = "No se pudo actualizar (${res.code()})")
            } catch (e: Exception) {
                _state.value = _state.value.copy(busyTaskId = null, error = "Sin conexión. No se cambió el pendiente.")
            }
        }
    }
}
