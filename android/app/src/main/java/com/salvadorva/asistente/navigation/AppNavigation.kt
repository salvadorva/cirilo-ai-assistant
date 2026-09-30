package com.salvadorva.asistente.navigation

import androidx.compose.runtime.*
import androidx.compose.ui.platform.LocalContext
import androidx.navigation.compose.NavHost
import androidx.navigation.compose.composable
import androidx.navigation.compose.rememberNavController
import com.salvadorva.asistente.data.SessionManager
import com.salvadorva.asistente.network.ApiClient
import com.salvadorva.asistente.reminders.Reminders
import com.salvadorva.asistente.ui.login.LoginScreen
import com.salvadorva.asistente.ui.login.LoginViewModel
import com.salvadorva.asistente.ui.main.MainScreen
import kotlinx.coroutines.flow.first
import kotlinx.coroutines.launch

object Routes {
    const val LOGIN = "login"
    const val MAIN  = "main"
}

@Composable
fun AppNavigation(
    sessionManager: SessionManager,
    pendingDeepLink: DeepLink? = null,
    onDeepLinkHandled: () -> Unit = {}
) {
    val navController = rememberNavController()
    val scope = rememberCoroutineScope()
    val context = LocalContext.current.applicationContext
    var startDestination by remember { mutableStateOf<String?>(null) }

    LaunchedEffect(Unit) {
        val savedToken = sessionManager.token.first()
        if (savedToken != null) {
            ApiClient.setToken(savedToken)
            // Cubre la actualización de la app y cambios de permiso: solo llama si algo cambió.
            Reminders.scheduleRegistration(context)
            startDestination = Routes.MAIN
        } else {
            startDestination = Routes.LOGIN
        }
    }

    if (startDestination == null) return

    NavHost(navController = navController, startDestination = startDestination!!) {

        composable(Routes.LOGIN) {
            val viewModel = remember { LoginViewModel(sessionManager) }
            LoginScreen(
                viewModel = viewModel,
                onLoginSuccess = {
                    Reminders.scheduleRegistration(context, force = true)
                    Reminders.scheduleSync(context)
                    navController.navigate(Routes.MAIN) {
                        popUpTo(Routes.LOGIN) { inclusive = true }
                    }
                }
            )
        }

        composable(Routes.MAIN) {
            val userName by sessionManager.userName.collectAsState(initial = "Usuario")
            val userRole by sessionManager.userRole.collectAsState(initial = "")

            MainScreen(
                userName = userName ?: "Usuario",
                userRole = userRole ?: "",
                pendingDeepLink = pendingDeepLink,
                onDeepLinkHandled = onDeepLinkHandled,
                onLogout = {
                    scope.launch {
                        // Antes de invalidar el bearer: liberar la instalación (DELETE device-token).
                        try { Reminders.onLogout(context) } catch (_: Exception) {}
                        try { ApiClient.authApi.logout() } catch (_: Exception) {}
                        ApiClient.setToken(null)
                        sessionManager.clearSession()
                        navController.navigate(Routes.LOGIN) {
                            popUpTo(Routes.MAIN) { inclusive = true }
                        }
                    }
                }
            )
        }
    }
}
