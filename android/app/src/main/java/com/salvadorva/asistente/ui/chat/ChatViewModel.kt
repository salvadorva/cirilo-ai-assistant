package com.salvadorva.asistente.ui.chat

import android.app.Application
import android.net.Uri
import androidx.lifecycle.AndroidViewModel
import androidx.lifecycle.viewModelScope
import com.salvadorva.asistente.data.SessionManager
import com.salvadorva.asistente.focus.FocusPlaybackService
import com.salvadorva.asistente.network.ApiClient
import com.salvadorva.asistente.network.models.ChatMessage
import com.salvadorva.asistente.network.models.ChatRequest
import com.salvadorva.asistente.util.DocumentExtractor
import com.salvadorva.asistente.voice.VoiceManager
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext
import okhttp3.MediaType.Companion.toMediaTypeOrNull
import okhttp3.MultipartBody
import okhttp3.RequestBody.Companion.toRequestBody

enum class ChatStatus { Ready, Listening, Thinking, Speaking }

sealed class ChatItem {
    data class Text(val msg: ChatMessage) : ChatItem()
    data class UserWithImage(val text: String, val localUri: Uri) : ChatItem()
    data class UserWithDocument(
        val text: String,
        val filename: String,
        val pageCount: Int,
        val truncated: Boolean,
    ) : ChatItem()
    data class AssistantImage(val url: String, val promptUsed: String?) : ChatItem()
    data class EventCreated(
        val title: String,
        val startDate: String?,
        val allDay: Boolean,
        val label: String = "✓ evento agendado",
        val count: Int = 1,
    ) : ChatItem()
    /** Cirilo pregunta a cuál evento se refiere: se muestran los candidatos (F2-07). */
    data class AgendaCandidates(val candidates: List<Candidate>) : ChatItem()
    data class Candidate(val title: String, val startDate: String?, val allDay: Boolean)
    data class TaskSaved(val title: String, val dueDate: String?, val label: String) : ChatItem()
}

sealed class StagedAttachment {
    data class Image(val uri: Uri) : StagedAttachment()
    data class Document(
        val filename: String,
        val content: String,
        val pageCount: Int,
        val truncated: Boolean,
    ) : StagedAttachment()
}

data class ChatUiState(
    val items: List<ChatItem> = emptyList(),
    val messages: List<ChatMessage> = emptyList(), // historial texto-only para API
    val status: ChatStatus = ChatStatus.Ready,
    val inputText: String = "",
    val conversationId: Int? = null,
    val errorMessage: String? = null,
    val showAttachSheet: Boolean = false,
    val staged: StagedAttachment? = null,
    val attachingDocument: Boolean = false,
    val conversationMode: Boolean = false, // modo manos libres: escucha → responde → vuelve a escuchar
)

class ChatViewModel(app: Application) : AndroidViewModel(app) {

    private val _state = MutableStateFlow(ChatUiState())
    val state: StateFlow<ChatUiState> = _state.asStateFlow()

    private val sessionManager = SessionManager(app.applicationContext)

    // Snapshot reactivo de preferencias de voz (default: TTS nativo Android)
    @Volatile private var useOpenAiVoice: Boolean = false
    @Volatile private var openAiVoice: String = SessionManager.DEFAULT_OPENAI_VOICE

    // Cuántas escuchas seguidas sin resultado llevamos en modo conversación
    // (corta el loop por inactividad para no dejar el mic abierto indefinidamente).
    private var emptyListenStreak = 0

    private val voice: VoiceManager = VoiceManager(app.applicationContext).apply {
        onListeningStart = { _state.value = _state.value.copy(status = ChatStatus.Listening) }
        onListeningEnd = {
            if (_state.value.status == ChatStatus.Listening) {
                _state.value = _state.value.copy(status = ChatStatus.Ready)
            }
        }
        onResult = { transcript ->
            if (_state.value.conversationMode && isMuteCommand(transcript)) {
                // "Cirilo, pará" → pausar el modo conversación sin enviar el comando
                setConversationMode(false)
            } else {
                emptyListenStreak = 0
                sendMessageDirect(transcript, viaVoice = true)
            }
        }
        onError = { msg ->
            if (_state.value.conversationMode && isTransientListenError(msg)) {
                // No escuchó nada / reconocedor ocupado: mantener vivo el loop,
                // pero cortar tras varios intentos vacíos seguidos.
                emptyListenStreak++
                if (emptyListenStreak >= MAX_EMPTY_LISTENS) {
                    setConversationMode(false)
                    _state.value = _state.value.copy(errorMessage = "modo conversación en pausa por inactividad")
                } else {
                    _state.value = _state.value.copy(status = ChatStatus.Ready)
                    continueConversationLoop()
                }
            } else {
                if (_state.value.conversationMode) {
                    _state.value = _state.value.copy(conversationMode = false)
                }
                _state.value = _state.value.copy(status = ChatStatus.Ready, errorMessage = msg)
            }
        }
        onTtsEnd = {
            _state.value = _state.value.copy(status = ChatStatus.Ready)
            if (_state.value.conversationMode) continueConversationLoop()
        }
    }

    init {
        viewModelScope.launch {
            sessionManager.useOpenAiVoice.collect { useOpenAiVoice = it }
        }
        viewModelScope.launch {
            sessionManager.openAiVoice.collect { openAiVoice = it }
        }
    }

    fun updateInput(text: String) {
        _state.value = _state.value.copy(inputText = text)
    }

    /**
     * Mensaje de enfoque llegado por push: se muestra como línea de Cirilo
     * y se reproduce (audio remoto OpenAI, o TTS nativo si no hay audio).
     */
    fun playFocusMessage(text: String, audioUrl: String?) {
        if (text.isBlank() && audioUrl.isNullOrBlank()) return
        // Si el service de auto-play sigue sonando, la app toma el control
        FocusPlaybackService.stop(getApplication())
        val msg = ChatMessage(role = "assistant", content = text)
        _state.value = _state.value.copy(
            items = _state.value.items + ChatItem.Text(msg),
            status = ChatStatus.Speaking,
        )
        if (!audioUrl.isNullOrBlank()) {
            voice.playRemoteAudio(audioUrl)
        } else {
            voice.speak(text)
        }
    }

    fun sendMessage() {
        val text = _state.value.inputText.trim()
        val staged = _state.value.staged
        if (_state.value.status != ChatStatus.Ready) return
        if (text.isEmpty() && staged == null) return

        _state.value = _state.value.copy(inputText = "", staged = null)

        when (staged) {
            is StagedAttachment.Image -> sendImageWithQuestion(staged.uri, text)
            is StagedAttachment.Document -> sendDocumentWithQuestion(staged, text)
            // En modo conversación, aunque se escriba, Cirilo responde por voz y
            // el loop sigue escuchando después.
            null -> sendMessageDirect(text, viaVoice = _state.value.conversationMode)
        }
    }

    // ── Modo conversación (manos libres) ────────────────────────────

    fun setConversationMode(enabled: Boolean) {
        if (enabled) {
            emptyListenStreak = 0
            _state.value = _state.value.copy(conversationMode = true, errorMessage = null)
            if (_state.value.status == ChatStatus.Ready) {
                voice.startListening()
            }
        } else {
            _state.value = _state.value.copy(conversationMode = false)
            voice.cancelListening()
            voice.stopSpeaking()
            if (_state.value.status != ChatStatus.Thinking) {
                _state.value = _state.value.copy(status = ChatStatus.Ready)
            }
        }
    }

    /** Reanuda la escucha tras una pausa breve si el modo sigue activo. */
    private fun continueConversationLoop() {
        viewModelScope.launch {
            kotlinx.coroutines.delay(450)
            if (_state.value.conversationMode && _state.value.status == ChatStatus.Ready) {
                voice.startListening()
            }
        }
    }

    private fun isMuteCommand(transcript: String): Boolean {
        val n = normalize(transcript)
        return MUTE_COMMANDS.any { n.contains(it) }
    }

    private fun isTransientListenError(msg: String): Boolean =
        msg in TRANSIENT_LISTEN_ERRORS

    // ── Chat texto plano ────────────────────────────────────────────
    private fun sendMessageDirect(text: String, viaVoice: Boolean) {
        val userMsg = ChatMessage(role = "user", content = text)
        _state.value = _state.value.copy(
            items = _state.value.items + ChatItem.Text(userMsg),
            messages = _state.value.messages + userMsg,
            status = ChatStatus.Thinking,
            errorMessage = null,
        )

        viewModelScope.launch {
            try {
                val historyToSend = _state.value.messages
                    .dropLast(1)
                    .takeLast(6)
                // Solo pedimos audio server-side cuando vino por voz Y el usuario
                // habilitó voz OpenAI. En caso contrario, TTS nativo Android.
                val wantOpenAiAudio = viaVoice && useOpenAiVoice
                val response = ApiClient.chatApi.chat(
                    java.util.UUID.randomUUID().toString(),
                    ChatRequest(
                        prompt = text,
                        conversation_id = _state.value.conversationId,
                        history = historyToSend,
                        generateAudio = wantOpenAiAudio,
                        voice = if (wantOpenAiAudio) openAiVoice else null,
                    )
                )
                if (response.isSuccessful) {
                    val body = response.body()
                    if (body != null) {
                        val assistantMsg = ChatMessage(role = "assistant", content = body.reply)
                        val newItems = buildList<ChatItem> {
                            add(ChatItem.Text(assistantMsg))
                            body.image_generated?.let { gen ->
                                add(ChatItem.AssistantImage(url = gen.url, promptUsed = gen.prompt_used))
                            }
                            addAll(ChatCards.from(body))
                        }
                        _state.value = _state.value.copy(
                            items = _state.value.items + newItems,
                            messages = _state.value.messages + assistantMsg,
                            conversationId = body.conversation_id,
                            status = ChatStatus.Speaking,
                        )
                        if (viaVoice) {
                            val audioUrl = body.audio_url
                            if (wantOpenAiAudio && !audioUrl.isNullOrBlank()) {
                                voice.playRemoteAudio(audioUrl)
                            } else {
                                voice.speak(body.reply)
                            }
                        } else {
                            kotlinx.coroutines.delay(800)
                            _state.value = _state.value.copy(status = ChatStatus.Ready)
                        }
                    } else {
                        setError("Respuesta vacía del asistente")
                    }
                } else {
                    setError("Error ${response.code()}")
                }
            } catch (e: Exception) {
                setError(e.message ?: "Error de conexión")
            }
        }
    }

    // ── Documento + pregunta → /chat ────────────────────────────────
    private fun sendDocumentWithQuestion(doc: StagedAttachment.Document, text: String) {
        val question = if (text.isBlank()) {
            "Léelo y dime de qué trata."
        } else text

        val displayItem = ChatItem.UserWithDocument(
            text = question,
            filename = doc.filename,
            pageCount = doc.pageCount,
            truncated = doc.truncated,
        )

        // El prompt real al modelo incluye el contenido del documento
        val truncatedNote = if (doc.truncated) " (contenido truncado)" else ""
        val fullPrompt = buildString {
            append("Adjunté el documento \"${doc.filename}\" (${doc.pageCount} página")
            append(if (doc.pageCount == 1) "" else "s")
            append("$truncatedNote). Contenido:\n\n---\n")
            append(doc.content)
            append("\n---\n\n")
            append(question)
        }

        val userMsgApi = ChatMessage(role = "user", content = fullPrompt)
        _state.value = _state.value.copy(
            items = _state.value.items + displayItem,
            messages = _state.value.messages + userMsgApi,
            status = ChatStatus.Thinking,
            errorMessage = null,
        )

        viewModelScope.launch {
            try {
                val historyToSend = _state.value.messages
                    .dropLast(1)
                    .takeLast(6)
                val response = ApiClient.chatApi.chat(
                    java.util.UUID.randomUUID().toString(),
                    ChatRequest(
                        prompt = fullPrompt,
                        conversation_id = _state.value.conversationId,
                        history = historyToSend,
                        generateAudio = false,
                    )
                )
                if (response.isSuccessful) {
                    val body = response.body()!!
                    val assistantMsg = ChatMessage(role = "assistant", content = body.reply)
                    val newItems = buildList<ChatItem> {
                        add(ChatItem.Text(assistantMsg))
                        body.image_generated?.let { gen ->
                            add(ChatItem.AssistantImage(url = gen.url, promptUsed = gen.prompt_used))
                        }
                        addAll(ChatCards.from(body))
                    }
                    _state.value = _state.value.copy(
                        items = _state.value.items + newItems,
                        messages = _state.value.messages + assistantMsg,
                        conversationId = body.conversation_id,
                        status = ChatStatus.Ready,
                    )
                } else {
                    setError("Error analizando documento: ${response.code()}")
                }
            } catch (e: Exception) {
                setError(e.message ?: "Error procesando documento")
            }
        }
    }

    // ── Imagen + pregunta → /chat/image (Vision + persistencia) ───
    private fun sendImageWithQuestion(uri: Uri, text: String) {
        val context = getApplication<Application>()
        val question = text.ifBlank { "¿qué hay en esta imagen?" }
        val displayItem = ChatItem.UserWithImage(text = question, localUri = uri)

        // Marker en historial local para coincidir con lo que el backend persiste,
        // permitiendo que preguntas de seguimiento tengan contexto vía texto.
        val userMsgForHistory = ChatMessage(
            role = "user",
            content = "[Imagen adjunta] $question",
        )

        _state.value = _state.value.copy(
            items = _state.value.items + displayItem,
            messages = _state.value.messages + userMsgForHistory,
            status = ChatStatus.Thinking,
            errorMessage = null,
        )

        viewModelScope.launch {
            try {
                val (bytes, mimeType) = withContext(Dispatchers.IO) {
                    val stream = context.contentResolver.openInputStream(uri)
                        ?: throw Exception("No se pudo abrir la imagen")
                    val data = stream.use { it.readBytes() }
                    val mt = context.contentResolver.getType(uri) ?: "image/jpeg"
                    data to mt
                }
                val ext = mimeType.substringAfter("/").takeIf { it.isNotBlank() } ?: "jpg"
                val imagePart = MultipartBody.Part.createFormData(
                    "image",
                    "photo.$ext",
                    bytes.toRequestBody(mimeType.toMediaTypeOrNull()),
                )
                val promptPart = question.toRequestBody("text/plain".toMediaTypeOrNull())
                val convIdPart = _state.value.conversationId
                    ?.toString()
                    ?.toRequestBody("text/plain".toMediaTypeOrNull())

                val response = ApiClient.chatApi.chatWithImage(imagePart, promptPart, convIdPart)
                if (response.isSuccessful) {
                    val body = response.body()!!
                    val assistantMsg = ChatMessage(role = "assistant", content = body.reply)
                    _state.value = _state.value.copy(
                        items = _state.value.items + ChatItem.Text(assistantMsg),
                        messages = _state.value.messages + assistantMsg,
                        conversationId = body.conversation_id,
                        status = ChatStatus.Ready,
                    )
                } else {
                    setError("Error analizando imagen: ${response.code()}")
                }
            } catch (e: Exception) {
                setError(e.message ?: "Error procesando imagen")
            }
        }
    }

    // ── Attach: sheet, imagen, documento ───────────────────────────
    fun showAttachSheet() {
        if (_state.value.status != ChatStatus.Ready) return
        _state.value = _state.value.copy(showAttachSheet = true)
    }

    fun dismissAttachSheet() {
        _state.value = _state.value.copy(showAttachSheet = false)
    }

    fun stageImage(uri: Uri) {
        _state.value = _state.value.copy(
            staged = StagedAttachment.Image(uri),
            showAttachSheet = false,
            errorMessage = null,
        )
    }

    fun stageDocument(uri: Uri) {
        _state.value = _state.value.copy(
            showAttachSheet = false,
            attachingDocument = true,
            errorMessage = null,
        )
        val context = getApplication<Application>()
        viewModelScope.launch {
            val result = withContext(Dispatchers.IO) {
                DocumentExtractor.extract(context, uri)
            }
            when (result) {
                is DocumentExtractor.Result.Success -> {
                    _state.value = _state.value.copy(
                        staged = StagedAttachment.Document(
                            filename = result.filename,
                            content = result.content,
                            pageCount = result.pageCount,
                            truncated = result.truncated,
                        ),
                        attachingDocument = false,
                    )
                }
                is DocumentExtractor.Result.Error -> {
                    _state.value = _state.value.copy(
                        attachingDocument = false,
                        errorMessage = result.message,
                    )
                }
            }
        }
    }

    fun clearStaged() {
        _state.value = _state.value.copy(staged = null)
    }

    fun startListening() {
        if (_state.value.status == ChatStatus.Speaking) {
            voice.stopSpeaking()
            _state.value = _state.value.copy(status = ChatStatus.Ready)
        }
        if (_state.value.status == ChatStatus.Listening) {
            voice.cancelListening()
            _state.value = _state.value.copy(status = ChatStatus.Ready)
            return
        }
        if (_state.value.status != ChatStatus.Ready) return
        _state.value = _state.value.copy(errorMessage = null)
        voice.startListening()
    }

    fun clearError() {
        _state.value = _state.value.copy(errorMessage = null)
    }

    private fun setError(msg: String) {
        _state.value = _state.value.copy(
            status = ChatStatus.Ready,
            errorMessage = msg,
        )
    }

    override fun onCleared() {
        voice.release()
        super.onCleared()
    }

    private companion object {
        const val MAX_EMPTY_LISTENS = 3

        // Comandos de voz para pausar el modo conversación (sin tildes ni signos,
        // ver normalize()). Mismos que el modo conversación del asistente web.
        val MUTE_COMMANDS = listOf(
            "cirilo para", "cirilo pausa", "cirilo mute", "cirilo silenciar",
            "cirilo silencio", "cirilo detente", "cirilo alto", "cirilo stop",
            "cirilo basta", "cirilo callate",
        )

        // Errores de STT recuperables: en modo conversación reintentamos.
        val TRANSIENT_LISTEN_ERRORS = setOf(
            "no escuché nada",
            "el reconocedor está ocupado",
            "tiempo de espera agotado, intenta de nuevo",
        )

        /** Minúsculas, sin tildes ni signos, espacios colapsados. */
        fun normalize(s: String): String =
            java.text.Normalizer.normalize(s.lowercase(), java.text.Normalizer.Form.NFD)
                .replace(Regex("\\p{Mn}+"), "")
                .replace(Regex("[^a-z0-9 ]"), " ")
                .replace(Regex("\\s+"), " ")
                .trim()
    }
}
