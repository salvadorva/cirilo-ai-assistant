package com.salvadorva.asistente.reminders

import android.content.Context
import android.media.MediaPlayer
import com.salvadorva.asistente.network.ApiClient
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import java.io.File

/**
 * Audio de un recordatorio acordado, solo bajo petición (decisión del 30/09/2026): se descarga con
 * la sesión a la caché privada de la app y se reproduce al tocar «Escuchar». Sin autoplay.
 */
object ReminderAudioPlayer {
    private var player: MediaPlayer? = null

    val isPlaying: Boolean get() = runCatching { player?.isPlaying == true }.getOrDefault(false)

    /** Descarga (si hace falta) y reproduce. Devuelve false si el audio no está disponible. */
    suspend fun play(context: Context, reminderId: String, onDone: () -> Unit): Boolean {
        stop()
        val file = withContext(Dispatchers.IO) {
            val dir = File(context.cacheDir, "reminder-audio").apply { mkdirs() }
            val target = File(dir, reminderId.filter { it.isLetterOrDigit() || it == '-' } + ".mp3")
            if (!target.exists() || target.length() == 0L) {
                val res = ApiClient.contextualReminderApi.audio(reminderId)
                val body = res.body()
                if (!res.isSuccessful || body == null) return@withContext null
                body.byteStream().use { input -> target.outputStream().use { input.copyTo(it) } }
            }
            target
        } ?: return false

        player = MediaPlayer().apply {
            setDataSource(file.absolutePath)
            setOnCompletionListener { stop(); onDone() }
            prepare()
            start()
        }
        return true
    }

    fun stop() {
        runCatching { player?.stop() }
        runCatching { player?.release() }
        player = null
    }
}
