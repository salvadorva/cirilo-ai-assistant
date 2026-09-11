package com.salvadorva.asistente.ui.settings

import android.app.Application
import androidx.lifecycle.AndroidViewModel
import androidx.lifecycle.viewModelScope
import com.salvadorva.asistente.data.SessionManager
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch

data class SettingsUiState(
    val useOpenAiVoice: Boolean = false,
    val openAiVoice: String = SessionManager.DEFAULT_OPENAI_VOICE,
    val privateMode: Boolean = false,
)

class SettingsViewModel(app: Application) : AndroidViewModel(app) {

    private val sessionManager = SessionManager(app.applicationContext)

    private val _state = MutableStateFlow(SettingsUiState())
    val state: StateFlow<SettingsUiState> = _state.asStateFlow()

    init {
        viewModelScope.launch {
            sessionManager.useOpenAiVoice.collect { enabled ->
                _state.value = _state.value.copy(useOpenAiVoice = enabled)
            }
        }
        viewModelScope.launch {
            sessionManager.openAiVoice.collect { v ->
                _state.value = _state.value.copy(openAiVoice = v)
            }
        }
        viewModelScope.launch {
            sessionManager.privateMode.collect { enabled ->
                _state.value = _state.value.copy(privateMode = enabled)
            }
        }
    }

    fun setUseOpenAiVoice(enabled: Boolean) {
        viewModelScope.launch { sessionManager.setUseOpenAiVoice(enabled) }
    }

    fun setOpenAiVoice(voice: String) {
        viewModelScope.launch { sessionManager.setOpenAiVoice(voice) }
    }

    fun setPrivateMode(enabled: Boolean) {
        viewModelScope.launch { sessionManager.setPrivateMode(enabled) }
    }
}
