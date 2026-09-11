package com.salvadorva.asistente.focus

import android.app.Notification
import android.app.NotificationChannel
import android.app.NotificationManager
import android.app.PendingIntent
import android.content.Context
import android.content.Intent
import androidx.core.app.NotificationCompat
import com.salvadorva.asistente.MainActivity
import com.salvadorva.asistente.R
import com.salvadorva.asistente.navigation.putDeepLinkExtras

/**
 * Notificaciones de mensajes de enfoque (rutina de organización personal).
 *
 * La notificación ofrece dos caminos:
 *  - tap en el cuerpo → abre la app (deep link focus_message) y reproduce el audio
 *  - acción ▶ → reproduce el audio sin abrir la app (FocusPlaybackService)
 */
object FocusNotifier {

    const val CHANNEL_ID = "focus_messages"
    const val CHANNEL_NAME = "Mensajes de enfoque"
    const val NOTIFICATION_ID = 4021

    fun ensureChannel(context: Context) {
        val manager = context.getSystemService(Context.NOTIFICATION_SERVICE) as NotificationManager
        manager.createNotificationChannel(
            NotificationChannel(CHANNEL_ID, CHANNEL_NAME, NotificationManager.IMPORTANCE_HIGH)
        )
    }

    fun post(context: Context, title: String, text: String, audioUrl: String?) {
        val manager = context.getSystemService(Context.NOTIFICATION_SERVICE) as NotificationManager
        manager.notify(NOTIFICATION_ID, contentNotification(context, title, text, audioUrl))
    }

    fun contentNotification(context: Context, title: String, text: String, audioUrl: String?): Notification {
        ensureChannel(context)

        val tapIntent = Intent(context, MainActivity::class.java).apply {
            flags = Intent.FLAG_ACTIVITY_CLEAR_TOP or Intent.FLAG_ACTIVITY_SINGLE_TOP
            putDeepLinkExtras(type = "focus_message", focusText = text, focusAudio = audioUrl)
        }
        val tapPending = PendingIntent.getActivity(
            context,
            NOTIFICATION_ID,
            tapIntent,
            PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE
        )

        val builder = NotificationCompat.Builder(context, CHANNEL_ID)
            .setSmallIcon(R.mipmap.ic_launcher)
            .setContentTitle(title)
            .setContentText(text)
            .setStyle(NotificationCompat.BigTextStyle().bigText(text))
            .setAutoCancel(true)
            .setPriority(NotificationCompat.PRIORITY_HIGH)
            .setContentIntent(tapPending)

        if (!audioUrl.isNullOrBlank()) {
            builder.addAction(
                0,
                "▶ Reproducir",
                FocusPlaybackService.playPendingIntent(context, title, text, audioUrl)
            )
        }

        return builder.build()
    }
}
