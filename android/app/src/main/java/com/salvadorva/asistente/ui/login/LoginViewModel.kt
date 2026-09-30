package com.salvadorva.asistente.ui.login

import android.os.Build
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.salvadorva.asistente.data.SessionManager
import com.salvadorva.asistente.network.ApiClient
import com.salvadorva.asistente.network.models.LoginRequest
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.launch

sealed class LoginState {
    object Idle : LoginState()
    object Loading : LoginState()
    object Success : LoginState()
    data class Error(val message: String) : LoginState()
}

class LoginViewModel(private val sessionManager: SessionManager) : ViewModel() {

    private val _state = MutableStateFlow<LoginState>(LoginState.Idle)
    val state: StateFlow<LoginState> = _state

    fun login(email: String, password: String) {
        if (email.isBlank() || password.isBlank()) {
            _state.value = LoginState.Error("Completa todos los campos")
            return
        }
        viewModelScope.launch {
            _state.value = LoginState.Loading
            try {
                val deviceName = "${Build.MANUFACTURER} ${Build.MODEL}".take(80)
                val response = ApiClient.authApi.login(
                    LoginRequest(email.trim(), password, deviceName)
                )
                if (response.isSuccessful) {
                    val body = response.body()!!
                    sessionManager.saveSession(
                        token = body.token,
                        name  = body.user.name,
                        email = body.user.email,
                        role  = body.user.role ?: "usuario"
                    )
                    ApiClient.setToken(body.token)
                    // El registro del token FCM (con installation_id) lo agenda AppNavigation.
                    _state.value = LoginState.Success
                } else {
                    _state.value = LoginState.Error("Credenciales incorrectas")
                }
            } catch (e: Exception) {
                _state.value = LoginState.Error("Error de conexión")
            }
        }
    }
}
