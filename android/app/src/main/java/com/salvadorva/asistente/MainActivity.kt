package com.salvadorva.asistente

import android.Manifest
import android.content.Intent
import android.os.Build
import android.os.Bundle
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.activity.enableEdgeToEdge
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.lifecycle.lifecycleScope
import com.salvadorva.asistente.data.SessionManager
import com.salvadorva.asistente.navigation.AppNavigation
import com.salvadorva.asistente.navigation.DeepLink
import com.salvadorva.asistente.navigation.consumeDeepLink
import com.salvadorva.asistente.reminders.Reminders
import com.salvadorva.asistente.ui.theme.AsistenteTheme
import com.tom_roush.pdfbox.android.PDFBoxResourceLoader
import kotlinx.coroutines.launch

class MainActivity : ComponentActivity() {

    private val requestNotificationPermission =
        registerForActivityResult(ActivityResultContracts.RequestPermission()) {}

    private var pendingDeepLink by mutableStateOf<DeepLink?>(null)

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        enableEdgeToEdge()

        PDFBoxResourceLoader.init(applicationContext)

        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU) {
            requestNotificationPermission.launch(Manifest.permission.POST_NOTIFICATIONS)
        }

        pendingDeepLink = intent?.consumeDeepLink()

        val sessionManager = SessionManager(applicationContext)
        setContent {
            AsistenteTheme {
                AppNavigation(
                    sessionManager  = sessionManager,
                    pendingDeepLink = pendingDeepLink,
                    onDeepLinkHandled = { pendingDeepLink = null }
                )
            }
        }
    }

    override fun onResume() {
        super.onResume()
        // Al abrir la app: retirar avisos cerrados o caducados y reanudar la cola.
        lifecycleScope.launch {
            if (Reminders.ensureAuth(applicationContext)) {
                runCatching { Reminders.engine(applicationContext).reconcile() }
                Reminders.scheduleSync(applicationContext)
            }
        }
    }

    override fun onNewIntent(intent: Intent) {
        super.onNewIntent(intent)
        setIntent(intent)
        intent.consumeDeepLink()?.let { pendingDeepLink = it }
    }
}
