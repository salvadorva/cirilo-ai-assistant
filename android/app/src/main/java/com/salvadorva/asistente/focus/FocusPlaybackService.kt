package com.salvadorva.asistente.focus

import android.app.PendingIntent
import android.app.Service
import android.content.Context
import android.content.Intent
import android.content.pm.ServiceInfo
import android.media.AudioAttributes
import android.media.MediaPlayer
import android.os.Build
import android.os.IBinder
import android.util.Log
import androidx.core.app.NotificationCompat
import androidx.core.content.ContextCompat
import com.salvadorva.asistente.R

/**
 * Reproduce el audio de un mensaje de enfoque sin abrir la app.
 *
 * Se lanza en dos escenarios:
 *  - auto-play al llegar el push (si modo privado está OFF) — permitido desde
 *    background porque el FCM llega con prioridad alta
 *  - acción ▶ de la notificación — permitido por ser interacción del usuario
 *
 * Mientras suena, reemplaza la notificación del mensaje por una "reproduciendo";
 * al terminar, repostea la notificación normal para poder re-escuchar.
 */
class FocusPlaybackService : Service() {

    private var player: MediaPlayer? = null

    override fun onBind(intent: Intent?): IBinder? = null

    override fun onStartCommand(intent: Intent?, flags: Int, startId: Int): Int {
        val title = intent?.getStringExtra(EXTRA_TITLE) ?: "Cirilo"
        val text = intent?.getStringExtra(EXTRA_TEXT).orEmpty()
        val audioUrl = intent?.getStringExtra(EXTRA_AUDIO_URL)

        FocusNotifier.ensureChannel(this)
        val playingNotification = NotificationCompat.Builder(this, FocusNotifier.CHANNEL_ID)
            .setSmallIcon(R.mipmap.ic_launcher)
            .setContentTitle("🔊 $title")
            .setContentText(text)
            .setStyle(NotificationCompat.BigTextStyle().bigText(text))
            .setOngoing(true)
            .setPriority(NotificationCompat.PRIORITY_HIGH)
            .build()

        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.Q) {
            startForeground(
                FocusNotifier.NOTIFICATION_ID,
                playingNotification,
                ServiceInfo.FOREGROUND_SERVICE_TYPE_MEDIA_PLAYBACK
            )
        } else {
            startForeground(FocusNotifier.NOTIFICATION_ID, playingNotification)
        }

        if (audioUrl.isNullOrBlank()) {
            finish(title, text, audioUrl)
            return START_NOT_STICKY
        }

        releasePlayer()
        try {
            player = MediaPlayer().apply {
                setAudioAttributes(
                    AudioAttributes.Builder()
                        .setContentType(AudioAttributes.CONTENT_TYPE_SPEECH)
                        .setUsage(AudioAttributes.USAGE_ASSISTANT)
                        .build()
                )
                setDataSource(audioUrl)
                setOnPreparedListener { it.start() }
                setOnCompletionListener { finish(title, text, audioUrl) }
                setOnErrorListener { _, what, extra ->
                    Log.e(TAG, "MediaPlayer error what=$what extra=$extra")
                    finish(title, text, audioUrl)
                    true
                }
                prepareAsync()
            }
        } catch (e: Exception) {
            Log.e(TAG, "No se pudo reproducir: ${e.message}")
            finish(title, text, audioUrl)
        }

        return START_NOT_STICKY
    }

    private fun finish(title: String, text: String, audioUrl: String?) {
        releasePlayer()
        stopForeground(STOP_FOREGROUND_REMOVE)
        // Reposteo de la notificación normal: permite re-escuchar o abrir la app
        FocusNotifier.post(this, title, text, audioUrl)
        stopSelf()
    }

    private fun releasePlayer() {
        player?.runCatching {
            if (isPlaying) stop()
            release()
        }
        player = null
    }

    override fun onDestroy() {
        releasePlayer()
        super.onDestroy()
    }

    companion object {
        private const val TAG = "FocusPlayback"
        private const val EXTRA_TITLE = "title"
        private const val EXTRA_TEXT = "text"
        private const val EXTRA_AUDIO_URL = "audio_url"

        private fun intent(context: Context, title: String, text: String, audioUrl: String) =
            Intent(context, FocusPlaybackService::class.java).apply {
                putExtra(EXTRA_TITLE, title)
                putExtra(EXTRA_TEXT, text)
                putExtra(EXTRA_AUDIO_URL, audioUrl)
            }

        fun start(context: Context, title: String, text: String, audioUrl: String) {
            ContextCompat.startForegroundService(context, intent(context, title, text, audioUrl))
        }

        fun stop(context: Context) {
            context.stopService(Intent(context, FocusPlaybackService::class.java))
        }

        fun playPendingIntent(context: Context, title: String, text: String, audioUrl: String): PendingIntent {
            val flags = PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE
            val i = intent(context, title, text, audioUrl)
            return if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
                PendingIntent.getForegroundService(context, NOTIFICATION_REQUEST_CODE, i, flags)
            } else {
                PendingIntent.getService(context, NOTIFICATION_REQUEST_CODE, i, flags)
            }
        }

        private const val NOTIFICATION_REQUEST_CODE = 4022
    }
}
