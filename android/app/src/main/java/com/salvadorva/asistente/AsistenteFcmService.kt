package com.salvadorva.asistente

import android.app.NotificationChannel
import android.app.NotificationManager
import android.app.PendingIntent
import android.content.Context
import android.content.Intent
import androidx.core.app.NotificationCompat
import com.google.firebase.messaging.FirebaseMessagingService
import com.google.firebase.messaging.RemoteMessage
import com.salvadorva.asistente.data.SessionManager
import com.salvadorva.asistente.focus.FocusNotifier
import com.salvadorva.asistente.focus.FocusPlaybackService
import com.salvadorva.asistente.navigation.putDeepLinkExtras
import com.salvadorva.asistente.reminders.ReminderPushParser
import com.salvadorva.asistente.reminders.Reminders
import kotlinx.coroutines.flow.first
import kotlinx.coroutines.runBlocking

class AsistenteFcmService : FirebaseMessagingService() {

    companion object {
        const val CHANNEL_ID = "asistente_notifications"
        const val CHANNEL_NAME = "Notificaciones del asistente"
    }

    override fun onNewToken(token: String) {
        super.onNewToken(token)
        // Misma installation_id, token nuevo. Va a WorkManager: en un proceso
        // arrancado por FCM el bearer se lee de la sesión guardada, y sin red se reintenta.
        Reminders.scheduleRegistration(applicationContext, fcmToken = token, force = true)
    }

    override fun onMessageReceived(message: RemoteMessage) {
        super.onMessageReceived(message)

        // Mensajes de enfoque: llegan data-only (sin bloque notification) para que
        // este service corra también en background y arme su propia notificación
        if (message.data["type"] == "focus_message") {
            handleFocusMessage(message.data)
            return
        }

        // Recordatorio contextual v1: data-only. Validar, deduplicar y mostrar el
        // aviso genérico; recibos y consultas van a WorkManager.
        if (message.data["type"] == ReminderPushParser.TYPE) {
            if (Reminders.hasSession(applicationContext)) {
                Reminders.engine(applicationContext).onPush(message.data)
            }
            return
        }

        val title = message.notification?.title ?: return
        val body = message.notification?.body ?: ""
        val type = message.data["type"]
        val eventId = message.data["event_id"]
        val conversationId = message.data["conversation_id"]
        showNotification(title, body, type, eventId, conversationId)
    }

    private fun handleFocusMessage(data: Map<String, String>) {
        val title = data["title"] ?: "Cirilo"
        val text = data["body"].orEmpty()
        val audioUrl = data["audio_url"]?.takeIf { it.isNotBlank() }

        FocusNotifier.post(this, title, text, audioUrl)

        // Modo privado (Ajustes): si está apagado, el mensaje se reproduce solo al llegar
        val privateMode = runBlocking { SessionManager(applicationContext).privateMode.first() }
        if (!privateMode && audioUrl != null) {
            FocusPlaybackService.start(this, title, text, audioUrl)
        }
    }

    private fun showNotification(
        title: String,
        body: String,
        type: String?,
        eventId: String?,
        conversationId: String?,
    ) {
        val manager = getSystemService(Context.NOTIFICATION_SERVICE) as NotificationManager

        val channel = NotificationChannel(
            CHANNEL_ID, CHANNEL_NAME, NotificationManager.IMPORTANCE_HIGH
        )
        manager.createNotificationChannel(channel)

        val intent = Intent(this, MainActivity::class.java).apply {
            flags = Intent.FLAG_ACTIVITY_CLEAR_TOP or Intent.FLAG_ACTIVITY_SINGLE_TOP
            putDeepLinkExtras(type, eventId, conversationId)
        }
        val pendingIntent = PendingIntent.getActivity(
            this,
            (eventId ?: conversationId ?: type ?: "").hashCode(),
            intent,
            PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE
        )

        val notification = NotificationCompat.Builder(this, CHANNEL_ID)
            .setSmallIcon(R.mipmap.ic_launcher)
            .setContentTitle(title)
            .setContentText(body)
            .setAutoCancel(true)
            .setPriority(NotificationCompat.PRIORITY_HIGH)
            .setContentIntent(pendingIntent)
            .build()

        manager.notify(System.currentTimeMillis().toInt(), notification)
    }
}
