package com.salvadorva.asistente.voice

import android.content.Context
import android.content.Intent
import android.media.AudioAttributes
import android.media.AudioFocusRequest
import android.media.AudioManager
import android.media.MediaPlayer
import android.os.Build
import android.os.Bundle
import android.os.Handler
import android.os.Looper
import android.speech.RecognitionListener
import android.speech.RecognizerIntent
import android.speech.SpeechRecognizer
import android.speech.tts.TextToSpeech
import android.speech.tts.UtteranceProgressListener
import android.util.Log
import java.util.Locale
import java.util.UUID

/**
 * Wrapper sobre SpeechRecognizer (STT) y TextToSpeech (TTS) nativos de Android.
 *
 * Uso típico (ChatViewModel):
 *
 *   val voice = VoiceManager(context).apply {
 *       onResult  = { text -> sendMessage(text) }
 *       onError   = { msg -> showError(msg) }
 *       onTtsEnd  = { setState(Ready) }
 *   }
 *   voice.startListening()
 *   voice.speak("respuesta del asistente")
 *
 * Llamar release() cuando el VM se destruye.
 */
class VoiceManager(private val context: Context) {

    private var recognizer: SpeechRecognizer? = null
    private var tts: TextToSpeech? = null
    private var ttsReady = false
    private var remotePlayer: MediaPlayer? = null

    private val audioManager = context.getSystemService(Context.AUDIO_SERVICE) as AudioManager
    private val mainHandler = Handler(Looper.getMainLooper())
    private var listenWatchdog: Runnable? = null

    private val focusRequest: AudioFocusRequest? = if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
        AudioFocusRequest.Builder(AudioManager.AUDIOFOCUS_GAIN_TRANSIENT)
            .setAudioAttributes(
                AudioAttributes.Builder()
                    .setUsage(AudioAttributes.USAGE_ASSISTANCE_ACCESSIBILITY)
                    .setContentType(AudioAttributes.CONTENT_TYPE_SPEECH)
                    .build()
            )
            .setAcceptsDelayedFocusGain(false)
            .build()
    } else null

    /** Callback con la transcripción final. */
    var onResult: (String) -> Unit = {}

    /** Callback de error de STT (mensaje legible). */
    var onError: (String) -> Unit = {}

    /** Callback cuando inicia a escuchar realmente (después del beep). */
    var onListeningStart: () -> Unit = {}

    /** Callback cuando termina de escuchar (por silencio o error). */
    var onListeningEnd: () -> Unit = {}

    /** Callback cuando TTS termina de hablar. */
    var onTtsEnd: () -> Unit = {}

    init {
        initTts()
    }

    private fun initTts() {
        tts = TextToSpeech(context.applicationContext) { status ->
            if (status == TextToSpeech.SUCCESS) {
                tts?.language = Locale("es", "GT")
                tts?.setSpeechRate(1.0f)
                tts?.setOnUtteranceProgressListener(object : UtteranceProgressListener() {
                    override fun onStart(utteranceId: String?) {}
                    override fun onDone(utteranceId: String?) {
                        abandonAudioFocus()
                        onTtsEnd()
                    }
                    @Deprecated("Deprecated in Java")
                    override fun onError(utteranceId: String?) {
                        abandonAudioFocus()
                        onTtsEnd()
                    }
                })
                ttsReady = true
            } else {
                Log.w(TAG, "TTS init failed status=$status")
            }
        }
    }

    private fun requestAudioFocus() {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            focusRequest?.let { audioManager.requestAudioFocus(it) }
        } else {
            @Suppress("DEPRECATION")
            audioManager.requestAudioFocus(null, AudioManager.STREAM_MUSIC, AudioManager.AUDIOFOCUS_GAIN_TRANSIENT)
        }
    }

    private fun abandonAudioFocus() {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            focusRequest?.let { audioManager.abandonAudioFocusRequest(it) }
        } else {
            @Suppress("DEPRECATION")
            audioManager.abandonAudioFocus(null)
        }
    }

    private fun cancelWatchdog() {
        listenWatchdog?.let { mainHandler.removeCallbacks(it) }
        listenWatchdog = null
    }

    private fun armWatchdog(timeoutMs: Long = 12_000L) {
        cancelWatchdog()
        listenWatchdog = Runnable {
            Log.w(TAG, "Watchdog: sin resultado tras ${timeoutMs}ms, reseteando reconocedor")
            cleanupRecognizer()
            abandonAudioFocus()
            onListeningEnd()
            onError("tiempo de espera agotado, intenta de nuevo")
        }.also { mainHandler.postDelayed(it, timeoutMs) }
    }

    fun startListening() {
        if (recognizer != null) {
            recognizer?.cancel()
            recognizer?.destroy()
            recognizer = null
        }
        cancelWatchdog()

        if (!SpeechRecognizer.isRecognitionAvailable(context)) {
            onError("Reconocimiento de voz no disponible en este dispositivo")
            return
        }

        requestAudioFocus()

        recognizer = SpeechRecognizer.createSpeechRecognizer(context.applicationContext).apply {
            setRecognitionListener(object : RecognitionListener {
                override fun onReadyForSpeech(params: Bundle?) {
                    armWatchdog()
                    onListeningStart()
                }
                override fun onBeginningOfSpeech() { cancelWatchdog() }
                override fun onRmsChanged(rmsdB: Float) {}
                override fun onBufferReceived(buffer: ByteArray?) {}
                override fun onEndOfSpeech() {
                    cancelWatchdog()
                    onListeningEnd()
                }
                override fun onError(error: Int) {
                    cancelWatchdog()
                    abandonAudioFocus()
                    onListeningEnd()
                    val msg = when (error) {
                        SpeechRecognizer.ERROR_NETWORK,
                        SpeechRecognizer.ERROR_NETWORK_TIMEOUT -> "sin conexión a internet"
                        SpeechRecognizer.ERROR_AUDIO -> "error de audio"
                        SpeechRecognizer.ERROR_INSUFFICIENT_PERMISSIONS -> "permiso de micrófono denegado"
                        SpeechRecognizer.ERROR_NO_MATCH,
                        SpeechRecognizer.ERROR_SPEECH_TIMEOUT -> "no escuché nada"
                        SpeechRecognizer.ERROR_RECOGNIZER_BUSY -> "el reconocedor está ocupado"
                        SpeechRecognizer.ERROR_SERVER -> "error del servidor de voz"
                        SpeechRecognizer.ERROR_CLIENT -> "error del cliente de voz"
                        else -> "error de reconocimiento ($error)"
                    }
                    onError(msg)
                    cleanupRecognizer()
                }
                override fun onResults(results: Bundle?) {
                    cancelWatchdog()
                    abandonAudioFocus()
                    onListeningEnd()
                    val matches = results?.getStringArrayList(SpeechRecognizer.RESULTS_RECOGNITION)
                    val text = matches?.firstOrNull()?.trim().orEmpty()
                    if (text.isNotEmpty()) {
                        onResult(text)
                    } else {
                        onError("no escuché nada")
                    }
                    cleanupRecognizer()
                }
                override fun onPartialResults(partialResults: Bundle?) {}
                override fun onEvent(eventType: Int, params: Bundle?) {}
            })
        }

        val intent = Intent(RecognizerIntent.ACTION_RECOGNIZE_SPEECH).apply {
            putExtra(RecognizerIntent.EXTRA_LANGUAGE_MODEL, RecognizerIntent.LANGUAGE_MODEL_FREE_FORM)
            putExtra(RecognizerIntent.EXTRA_LANGUAGE, "es-GT")
            putExtra(RecognizerIntent.EXTRA_LANGUAGE_PREFERENCE, "es-GT")
            putExtra(RecognizerIntent.EXTRA_PREFER_OFFLINE, false)
            putExtra(RecognizerIntent.EXTRA_MAX_RESULTS, 1)
            putExtra(RecognizerIntent.EXTRA_SPEECH_INPUT_COMPLETE_SILENCE_LENGTH_MILLIS, 1500L)
            putExtra(RecognizerIntent.EXTRA_SPEECH_INPUT_POSSIBLY_COMPLETE_SILENCE_LENGTH_MILLIS, 1500L)
        }

        try {
            recognizer?.startListening(intent)
        } catch (e: Exception) {
            cancelWatchdog()
            abandonAudioFocus()
            onError("no se pudo iniciar el reconocedor: ${e.message}")
            cleanupRecognizer()
        }
    }

    fun stopListening() {
        recognizer?.stopListening()
    }

    fun cancelListening() {
        cancelWatchdog()
        recognizer?.cancel()
        cleanupRecognizer()
        abandonAudioFocus()
        onListeningEnd()
    }

    private fun cleanupRecognizer() {
        recognizer?.destroy()
        recognizer = null
    }

    /** Habla un texto. Si TTS aún no está listo, lo ignora. */
    fun speak(text: String) {
        if (!ttsReady || text.isBlank()) {
            onTtsEnd()
            return
        }
        requestAudioFocus()
        val clean = text
            .replace(Regex("\\*+"), "")
            .replace(Regex("#+"), "")
            .replace(Regex("`+"), "")
            .replace(Regex("\\[(.*?)]\\(.*?\\)"), "$1")
            .trim()
        tts?.speak(
            clean,
            TextToSpeech.QUEUE_FLUSH,
            null,
            UUID.randomUUID().toString(),
        )
    }

    /**
     * Reproduce audio MP3 desde una URL remota (OpenAI TTS server-side).
     * Cuando termina, invoca onTtsEnd igual que speak() para que el estado
     * de la app vuelva a Ready.
     */
    fun playRemoteAudio(url: String) {
        if (url.isBlank()) {
            onTtsEnd()
            return
        }
        requestAudioFocus()
        stopRemoteAudio()
        try {
            remotePlayer = MediaPlayer().apply {
                setAudioAttributes(
                    AudioAttributes.Builder()
                        .setContentType(AudioAttributes.CONTENT_TYPE_SPEECH)
                        .setUsage(AudioAttributes.USAGE_ASSISTANT)
                        .build()
                )
                setDataSource(url)
                setOnPreparedListener { it.start() }
                setOnCompletionListener {
                    it.release()
                    remotePlayer = null
                    abandonAudioFocus()
                    onTtsEnd()
                }
                setOnErrorListener { mp, what, extra ->
                    Log.e(TAG, "MediaPlayer error what=$what extra=$extra")
                    mp.release()
                    remotePlayer = null
                    abandonAudioFocus()
                    onTtsEnd()
                    true
                }
                prepareAsync()
            }
        } catch (e: Exception) {
            Log.e(TAG, "playRemoteAudio failed: ${e.message}")
            remotePlayer?.release()
            remotePlayer = null
            abandonAudioFocus()
            onTtsEnd()
        }
    }

    private fun stopRemoteAudio() {
        remotePlayer?.runCatching {
            if (isPlaying) stop()
            release()
        }
        remotePlayer = null
    }

    fun stopSpeaking() {
        tts?.stop()
        stopRemoteAudio()
    }

    fun release() {
        cancelWatchdog()
        recognizer?.destroy()
        recognizer = null
        tts?.stop()
        tts?.shutdown()
        tts = null
        stopRemoteAudio()
        abandonAudioFocus()
    }

    private companion object {
        const val TAG = "VoiceManager"
    }
}
