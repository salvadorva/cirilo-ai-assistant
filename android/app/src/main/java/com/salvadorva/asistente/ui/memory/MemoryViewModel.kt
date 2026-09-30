package com.salvadorva.asistente.ui.memory

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.salvadorva.asistente.network.ApiClient
import com.salvadorva.asistente.network.MemoryApi
import com.salvadorva.asistente.network.models.MemoryResponse
import com.salvadorva.asistente.network.models.MemorySettingsRequest
import com.salvadorva.asistente.network.models.MemoryValueRequest
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch
import retrofit2.Response

data class MemoryUiState(
    val loading: Boolean = false,
    val busy: Boolean = false,
    val memory: MemoryResponse? = null,
    val error: String? = null,
)

class MemoryViewModel(private val api: MemoryApi = ApiClient.memoryApi) : ViewModel() {

    private val _state = MutableStateFlow(MemoryUiState())
    val state: StateFlow<MemoryUiState> = _state.asStateFlow()

    init {
        load()
    }

    fun load() {
        _state.value = _state.value.copy(loading = true, error = null)
        viewModelScope.launch {
            try {
                val res = api.list()
                _state.value = if (res.isSuccessful) _state.value.copy(loading = false, memory = res.body())
                else _state.value.copy(loading = false, error = "No se pudo cargar la memoria (${res.code()})")
            } catch (e: Exception) {
                _state.value = _state.value.copy(loading = false, error = "Sin conexión. Intenta de nuevo.")
            }
        }
    }

    fun edit(id: Int, value: String) = run("No se pudo guardar el cambio") { api.update(id, MemoryValueRequest(value.trim())) }
    fun forget(id: Int) = run("No se pudo olvidar el dato") { api.forget(id) }
    fun forgetAll() = run("No se pudo borrar la memoria") { api.forgetAll() }
    fun setExtraction(enabled: Boolean) = run("No se pudo cambiar el aprendizaje") { api.settings(MemorySettingsRequest(enabled)) }

    private fun run(errorText: String, call: suspend () -> Response<*>) {
        _state.value = _state.value.copy(busy = true, error = null)
        viewModelScope.launch {
            try {
                val res = call()
                _state.value = _state.value.copy(busy = false, error = if (res.isSuccessful) null else "$errorText (${res.code()})")
                if (res.isSuccessful) load()
            } catch (e: Exception) {
                _state.value = _state.value.copy(busy = false, error = "Sin conexión. $errorText.")
            }
        }
    }
}
