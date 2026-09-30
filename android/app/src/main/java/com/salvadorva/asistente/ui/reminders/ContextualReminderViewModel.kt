package com.salvadorva.asistente.ui.reminders

import android.app.Application
import android.content.Context
import android.net.ConnectivityManager
import android.net.NetworkCapabilities
import androidx.lifecycle.AndroidViewModel
import androidx.lifecycle.viewModelScope
import com.salvadorva.asistente.network.ApiClient
import com.salvadorva.asistente.reminders.ReminderAction
import com.salvadorva.asistente.reminders.ReminderDetail
import com.salvadorva.asistente.reminders.ReminderEngine.ActionOutcome
import com.salvadorva.asistente.reminders.Reminders
import com.salvadorva.asistente.reminders.offeredSnoozeMinutes
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch
import java.io.IOException

sealed class ReminderUiState {
    object Loading : ReminderUiState()
    data class Loaded(
        val detail: ReminderDetail,
        val snoozeOptions: List<Int>,
        val snoozeOpen: Boolean,
        val busy: Boolean = false,
        /** Resultado de la última acción; `confirmed` = el servidor la confirmó. */
        val notice: String? = null,
        val confirmed: Boolean = false,
    ) : ReminderUiState()
    data class Error(val message: String) : ReminderUiState()
}

/**
 * Detalle de un recordatorio contextual. El contexto solo se pide aquí, con la
 * app abierta y la sesión válida; nunca viaja en el push ni en la notificación.
 */
class ContextualReminderViewModel(app: Application) : AndroidViewModel(app) {

    private val context = app.applicationContext
    private val engine = Reminders.engine(context)
    private val clock = Reminders.clock(context)

    private val _state = MutableStateFlow<ReminderUiState>(ReminderUiState.Loading)
    val state: StateFlow<ReminderUiState> = _state.asStateFlow()

    private var reminderId: String? = null

    fun open(id: String, openSnooze: Boolean) {
        reminderId = id
        load(openSnooze, notice = null, confirmed = false)
    }

    private fun load(openSnooze: Boolean, notice: String?, confirmed: Boolean) {
        val id = reminderId ?: return
        viewModelScope.launch {
            if (_state.value !is ReminderUiState.Loaded) _state.value = ReminderUiState.Loading
            if (!Reminders.ensureAuth(context)) {
                _state.value = ReminderUiState.Error("Inicia sesión para ver el recordatorio.")
                return@launch
            }
            val response = try {
                ApiClient.contextualReminderApi.detail(id)
            } catch (_: IOException) {
                _state.value = ReminderUiState.Error("Sin conexión: el detalle solo se muestra al consultarlo.")
                return@launch
            }
            val detail = response.body()
            _state.value = when {
                response.isSuccessful && detail != null -> {
                    engine.refreshFromServer(detail)
                    val options = detail.offeredSnoozeMinutes(clock.now())
                    ReminderUiState.Loaded(detail, options, openSnooze && options.isNotEmpty(), notice = notice, confirmed = confirmed)
                }
                response.code() == 401 -> ReminderUiState.Error("La sesión expiró. Vuelve a iniciar sesión.")
                response.code() == 404 -> ReminderUiState.Error("Este recordatorio ya no existe.")
                else -> ReminderUiState.Error("No se pudo cargar el recordatorio (${response.code()}).")
            }
        }
    }

    fun toggleSnooze() {
        val s = _state.value as? ReminderUiState.Loaded ?: return
        _state.value = s.copy(snoozeOpen = !s.snoozeOpen && s.snoozeOptions.isNotEmpty())
    }

    fun complete() = act(ReminderAction.COMPLETE, null)
    fun cancel() = act(ReminderAction.CANCEL, null)
    fun snooze(minutes: Int) = act(ReminderAction.SNOOZE, minutes)

    private fun act(action: ReminderAction, minutes: Int?) {
        val s = _state.value as? ReminderUiState.Loaded ?: return
        if (s.busy) return // doble toque: una sola intención
        if (action == ReminderAction.SNOOZE && (minutes == null || minutes !in s.snoozeOptions)) return
        if (action == ReminderAction.SNOOZE && !isOnline()) {
            _state.value = s.copy(notice = "Posponer requiere conexión.", confirmed = false)
            return
        }
        _state.value = s.copy(busy = true, notice = null)
        viewModelScope.launch {
            val outcome = engine.performInApp(s.detail.id, s.detail.version, action, minutes)
            when (outcome) {
                is ActionOutcome.Confirmed -> load(false, confirmedLabel(action, minutes), confirmed = true)
                is ActionOutcome.Conflict -> load(false, conflictLabel(outcome.code), confirmed = false)
                ActionOutcome.RetryLater -> if (action == ReminderAction.SNOOZE) {
                    load(false, "No se pudo confirmar el aplazamiento. Revisa el estado antes de repetirlo.", false)
                } else {
                    _state.value = s.copy(busy = false, notice = "Sin conexión: pendiente de sincronizar.", confirmed = false)
                }
                ActionOutcome.SessionExpired -> _state.value =
                    s.copy(busy = false, notice = "La sesión expiró. Vuelve a iniciar sesión; la acción queda pendiente.")
                is ActionOutcome.Rejected -> load(false, "El servidor no aceptó la acción (${outcome.code}).", false)
            }
        }
    }

    private fun isOnline(): Boolean {
        val cm = context.getSystemService(Context.CONNECTIVITY_SERVICE) as ConnectivityManager
        val caps = cm.getNetworkCapabilities(cm.activeNetwork) ?: return false
        return caps.hasCapability(NetworkCapabilities.NET_CAPABILITY_INTERNET)
    }

    private fun confirmedLabel(action: ReminderAction, minutes: Int?) = when (action) {
        ReminderAction.COMPLETE -> "Hecho. Confirmado por el servidor."
        ReminderAction.CANCEL -> "Cancelado. Confirmado por el servidor."
        ReminderAction.SNOOZE -> "Pospuesto $minutes min. Confirmado por el servidor."
    }

    private fun conflictLabel(code: String) = when (code) {
        "version_conflict" -> "El recordatorio cambió; este es su estado actual."
        "invalid_state" -> "El recordatorio ya estaba cerrado."
        "reminder_expired" -> "El recordatorio caducó; acuerda uno nuevo con Cirilo."
        "snooze_exceeds_expiry" -> "Esa hora alcanza la caducidad; elige otra opción."
        else -> "El recordatorio cambió ($code)."
    }
}
