package com.salvadorva.asistente.ui.settings

import android.app.Application
import androidx.lifecycle.AndroidViewModel
import androidx.lifecycle.viewModelScope
import com.salvadorva.asistente.data.SessionManager
import com.salvadorva.asistente.reminders.ClaimResult
import com.salvadorva.asistente.reminders.REGISTRATION_REQUESTED_MESSAGE
import com.salvadorva.asistente.reminders.Reminders
import com.salvadorva.asistente.reminders.RemindersAction
import com.salvadorva.asistente.reminders.RemindersStatus
import com.salvadorva.asistente.reminders.registrationFinishedMessage
import com.salvadorva.asistente.reminders.remindersStatusOf
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch

data class SettingsUiState(
    val useOpenAiVoice: Boolean = false,
    val openAiVoice: String = SessionManager.DEFAULT_OPENAI_VOICE,
    val privateMode: Boolean = false,
    /** Estado del teléfono como destino de recordatorios contextuales. */
    val remindersStatus: String = "",
    /** Única acción de la tarjeta (reclamar, reintentar registro o ninguna). */
    val remindersAction: RemindersAction = RemindersAction.NONE,
    val registrationInProgress: Boolean = false,
    /** Feedback de «Reintentar registro»; nunca afirma «listo» sin la respuesta guardada. */
    val registrationMessage: String? = null,
    val claimInProgress: Boolean = false,
    /** Resultado del último reclamo; nunca afirma éxito sin confirmación del servidor. */
    val claimMessage: String? = null,
)

class SettingsViewModel(app: Application) : AndroidViewModel(app) {

    private val sessionManager = SessionManager(app.applicationContext)

    private val _state = MutableStateFlow(SettingsUiState())
    val state: StateFlow<SettingsUiState> = _state.asStateFlow()

    init {
        refreshRemindersStatus()
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

    private fun currentRemindersStatus(): RemindersStatus {
        val context = getApplication<Application>().applicationContext
        val registry = Reminders.registry(context)
        val view = remindersStatusOf(
            hasConflict = registry.hasConflict,
            canDisplay = Reminders.notifier(context).canDisplay(),
            isReady = registry.isReady,
            canOfferClaim = Reminders.claimFlow(context).canOffer(),
        )
        _state.value = _state.value.copy(remindersStatus = view.status.text, remindersAction = view.action)
        return view.status
    }

    fun refreshRemindersStatus() {
        currentRemindersStatus()
    }

    /**
     * «Reintentar registro»: fuerza un nuevo POST /device-token en segundo plano y
     * avisa cuando el trabajo termina con el estado que quedó guardado.
     */
    fun retryRegistration() {
        val context = getApplication<Application>().applicationContext
        if (_state.value.registrationInProgress) return // doble toque: un solo trabajo
        val workId = Reminders.scheduleRegistration(context, force = true)
        _state.value = _state.value.copy(registrationInProgress = true, registrationMessage = REGISTRATION_REQUESTED_MESSAGE)
        viewModelScope.launch {
            Reminders.registrationFinished(context, workId).collect {
                val status = currentRemindersStatus()
                _state.value = _state.value.copy(
                    registrationInProgress = false,
                    registrationMessage = registrationFinishedMessage(status),
                )
            }
        }
    }

    /**
     * Reclamo de la instalación (contrato v1.1, §8.1). Solo lo llama el botón de
     * confirmación del diálogo: es la acción consciente que exige el contrato.
     */
    fun confirmClaimInstallation() {
        val context = getApplication<Application>().applicationContext
        if (_state.value.claimInProgress) return // doble toque: un solo intento
        _state.value = _state.value.copy(claimInProgress = true, claimMessage = null)
        viewModelScope.launch {
            val message = if (!Reminders.ensureAuth(context)) {
                "Inicia sesión para usar este teléfono con tu cuenta."
            } else when (val result = Reminders.claimFlow(context).claim(confirmedByUser = true)) {
                is ClaimResult.Claimed ->
                    if (result.alreadyOwned) "Este teléfono ya estaba vinculado a tu cuenta. Registrándolo…"
                    else "Teléfono vinculado a tu cuenta. Registrándolo…"
                ClaimResult.NotFound -> "La instalación ya no existía; se registra de nuevo."
                ClaimResult.PossessionNotProven ->
                    "No se pudo comprobar que este teléfono es el vinculado (su token cambió). Hace falta liberarlo desde soporte."
                is ClaimResult.RateLimited -> "Demasiados intentos. Vuelve a probar en ${minutes(result.retryAfterSeconds)}."
                ClaimResult.SessionExpired -> "La sesión expiró. Vuelve a iniciar sesión."
                ClaimResult.NotOffered -> "No hay conflicto que resolver en este teléfono."
                is ClaimResult.Failed ->
                    if (result.retryable) "No se pudo completar ahora; inténtalo más tarde."
                    else "El servidor rechazó la solicitud${result.code?.let { " ($it)" }.orEmpty()}."
            }
            _state.value = _state.value.copy(claimInProgress = false, claimMessage = message)
            refreshRemindersStatus()
        }
    }

    private fun minutes(seconds: Long): String {
        val m = (seconds + 59) / 60
        return if (m <= 1) "1 minuto" else "$m minutos"
    }

    fun setPrivateMode(enabled: Boolean) {
        viewModelScope.launch { sessionManager.setPrivateMode(enabled) }
    }
}
