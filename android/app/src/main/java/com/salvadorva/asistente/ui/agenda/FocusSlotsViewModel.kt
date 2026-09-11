package com.salvadorva.asistente.ui.agenda

import android.media.AudioAttributes
import android.media.MediaPlayer
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.salvadorva.asistente.network.ApiClient
import com.salvadorva.asistente.network.models.FocusSlot
import com.salvadorva.asistente.network.models.FocusSlotRequest
import com.salvadorva.asistente.network.models.PreviewAudioRequest
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch

/** Borrador del formulario de crear/editar slot de enfoque. */
data class SlotEditor(
    val id: Int? = null,            // null = creando, no-null = editando
    val title: String = "",
    val message: String = "",
    val time: String = "08:00",     // "HH:mm" 24h, hora de Guatemala
    val days: Set<Int> = setOf(1, 2, 3, 4, 5),  // ISO: 1=lunes … 7=domingo
    val withAudio: Boolean = true,
    val voice: String = "echo",
) {
    val isEditing: Boolean get() = id != null
    val isValid: Boolean get() = title.isNotBlank() && message.isNotBlank() && days.isNotEmpty()
}

/** Estado del preview de voz en el editor. */
enum class PreviewState { Idle, Loading, Playing }

data class FocusSlotsUiState(
    val loading: Boolean = false,
    val saving: Boolean = false,
    val slots: List<FocusSlot> = emptyList(),
    val error: String? = null,
    val editor: SlotEditor? = null,
    val preview: PreviewState = PreviewState.Idle,
)

class FocusSlotsViewModel : ViewModel() {

    private val _state = MutableStateFlow(FocusSlotsUiState())
    val state: StateFlow<FocusSlotsUiState> = _state.asStateFlow()

    private val api = ApiClient.focusSlotApi

    init {
        load()
    }

    fun load() {
        _state.value = _state.value.copy(loading = true, error = null)
        viewModelScope.launch {
            try {
                val res = api.list()
                if (res.isSuccessful) {
                    val slots = res.body()?.slots.orEmpty().sortedBy { it.time }
                    _state.value = _state.value.copy(loading = false, slots = slots)
                } else {
                    _state.value = _state.value.copy(
                        loading = false,
                        error = "Error ${res.code()} al cargar los slots",
                    )
                }
            } catch (e: Exception) {
                _state.value = _state.value.copy(
                    loading = false,
                    error = e.message ?: "Error de conexión",
                )
            }
        }
    }

    // ── Editor ──────────────────────────────────────────────────────

    fun openCreate() {
        _state.value = _state.value.copy(editor = SlotEditor())
    }

    fun openEdit(slot: FocusSlot) {
        _state.value = _state.value.copy(
            editor = SlotEditor(
                id = slot.id,
                title = slot.title,
                message = slot.message,
                time = slot.time,
                days = slot.days.toSet(),
                withAudio = slot.with_audio,
                voice = slot.voice,
            ),
        )
    }

    fun updateEditor(transform: (SlotEditor) -> SlotEditor) {
        _state.value.editor?.let { current ->
            _state.value = _state.value.copy(editor = transform(current))
        }
    }

    fun closeEditor() {
        stopPreview()
        _state.value = _state.value.copy(editor = null)
    }

    fun save() {
        val editor = _state.value.editor ?: return
        if (!editor.isValid || _state.value.saving) return
        stopPreview()

        _state.value = _state.value.copy(saving = true, error = null)
        val body = FocusSlotRequest(
            title = editor.title.trim(),
            message = editor.message.trim(),
            time = editor.time,
            days = editor.days.sorted(),
            voice = editor.voice,
            with_audio = editor.withAudio,
        )

        viewModelScope.launch {
            try {
                val res = if (editor.id == null) {
                    api.create(body)
                } else {
                    api.update(editor.id, body)
                }
                if (res.isSuccessful) {
                    _state.value = _state.value.copy(saving = false, editor = null)
                    load()
                } else {
                    _state.value = _state.value.copy(
                        saving = false,
                        error = "Error ${res.code()} al guardar",
                    )
                }
            } catch (e: Exception) {
                _state.value = _state.value.copy(
                    saving = false,
                    error = e.message ?: "Error al guardar",
                )
            }
        }
    }

    // ── Toggle rápido on/off desde la tarjeta ───────────────────────

    fun setEnabled(slot: FocusSlot, enabled: Boolean) {
        // Optimista: se refleja de inmediato y se revierte si el PUT falla
        _state.value = _state.value.copy(
            slots = _state.value.slots.map {
                if (it.id == slot.id) it.copy(enabled = enabled) else it
            },
        )
        viewModelScope.launch {
            try {
                val res = api.update(slot.id, FocusSlotRequest(enabled = enabled))
                if (!res.isSuccessful) revertEnabled(slot)
            } catch (_: Exception) {
                revertEnabled(slot)
            }
        }
    }

    private fun revertEnabled(slot: FocusSlot) {
        _state.value = _state.value.copy(
            slots = _state.value.slots.map {
                if (it.id == slot.id) it.copy(enabled = slot.enabled) else it
            },
            error = "No se pudo cambiar el estado del slot",
        )
    }

    // ── Borrado ─────────────────────────────────────────────────────

    fun delete(id: Int) {
        if (_state.value.saving) return
        _state.value = _state.value.copy(saving = true, error = null)
        viewModelScope.launch {
            try {
                val res = api.delete(id)
                if (res.isSuccessful) {
                    _state.value = _state.value.copy(saving = false, editor = null)
                    load()
                } else {
                    _state.value = _state.value.copy(
                        saving = false,
                        error = "Error ${res.code()} al eliminar",
                    )
                }
            } catch (e: Exception) {
                _state.value = _state.value.copy(
                    saving = false,
                    error = e.message ?: "Error al eliminar",
                )
            }
        }
    }

    fun clearError() {
        _state.value = _state.value.copy(error = null)
    }

    // ── Preview de voz en el editor ──────────────────────────────────

    private var previewPlayer: MediaPlayer? = null

    /** Genera el TTS del mensaje del editor y lo reproduce; si ya suena, lo para. */
    fun togglePreview() {
        val editor = _state.value.editor ?: return
        when (_state.value.preview) {
            PreviewState.Loading -> return
            PreviewState.Playing -> stopPreview()
            PreviewState.Idle -> {
                if (editor.message.isBlank()) return
                _state.value = _state.value.copy(preview = PreviewState.Loading, error = null)
                viewModelScope.launch {
                    try {
                        val res = api.previewAudio(
                            PreviewAudioRequest(editor.message.trim(), editor.voice)
                        )
                        val url = res.body()?.audio_url
                        if (res.isSuccessful && !url.isNullOrBlank()) {
                            playPreview(url)
                        } else {
                            _state.value = _state.value.copy(
                                preview = PreviewState.Idle,
                                error = "Error ${res.code()} al generar el audio",
                            )
                        }
                    } catch (e: Exception) {
                        _state.value = _state.value.copy(
                            preview = PreviewState.Idle,
                            error = e.message ?: "Error al generar el audio",
                        )
                    }
                }
            }
        }
    }

    private fun playPreview(url: String) {
        releasePreviewPlayer()
        try {
            previewPlayer = MediaPlayer().apply {
                setAudioAttributes(
                    AudioAttributes.Builder()
                        .setContentType(AudioAttributes.CONTENT_TYPE_SPEECH)
                        .setUsage(AudioAttributes.USAGE_ASSISTANT)
                        .build()
                )
                setDataSource(url)
                setOnPreparedListener { it.start() }
                setOnCompletionListener { stopPreview() }
                setOnErrorListener { _, _, _ ->
                    stopPreview()
                    true
                }
                prepareAsync()
            }
            _state.value = _state.value.copy(preview = PreviewState.Playing)
        } catch (_: Exception) {
            stopPreview()
        }
    }

    private fun stopPreview() {
        releasePreviewPlayer()
        if (_state.value.preview != PreviewState.Idle) {
            _state.value = _state.value.copy(preview = PreviewState.Idle)
        }
    }

    private fun releasePreviewPlayer() {
        previewPlayer?.runCatching {
            if (isPlaying) stop()
            release()
        }
        previewPlayer = null
    }

    override fun onCleared() {
        releasePreviewPlayer()
        super.onCleared()
    }
}
