package com.salvadorva.asistente.network.models

/**
 * Slot de mensaje de enfoque tal como lo devuelven los endpoints
 * /api/mobile/focus-slots. La hora es "HH:mm" local (America/Guatemala)
 * y days son días ISO (1=lunes … 7=domingo).
 */
data class FocusSlot(
    val id: Int,
    val title: String,
    val message: String,
    val time: String,
    val days: List<Int> = emptyList(),
    val voice: String = "echo",
    val with_audio: Boolean = true,
    val enabled: Boolean = true,
    val last_sent_at: String? = null,
)

/** Wrapper de la respuesta de index: { "slots": [...] }. */
data class FocusSlotsResponse(
    val slots: List<FocusSlot> = emptyList(),
)

/**
 * Petición de preview: el backend genera el TTS del mensaje sin guardar
 * el slot, para escucharlo desde el editor antes de crear/actualizar.
 */
data class PreviewAudioRequest(
    val message: String,
    val voice: String = "echo",
)

data class PreviewAudioResponse(
    val audio_url: String? = null,
)

/**
 * Payload para crear (POST) o actualizar (PUT) un slot. Todos los campos
 * son opcionales: Gson omite los null, y el backend valida con `sometimes`,
 * así que sirve tanto para el editor completo como para toggles parciales.
 */
data class FocusSlotRequest(
    val title: String? = null,
    val message: String? = null,
    val time: String? = null,
    val days: List<Int>? = null,
    val voice: String? = null,
    val with_audio: Boolean? = null,
    val enabled: Boolean? = null,
)
