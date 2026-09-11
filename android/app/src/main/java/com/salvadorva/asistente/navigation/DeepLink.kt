package com.salvadorva.asistente.navigation

import android.content.Intent

/**
 * Deep links que la app maneja al recibir un push FCM.
 *
 *   event_reminder / event_start  →  abrir Agenda con el evento seleccionado
 *   chat                          →  abrir tab de Chat (opcionalmente con conversation_id)
 *   focus_message                 →  abrir Chat y reproducir el mensaje de enfoque
 */
sealed class DeepLink {
    data class AgendaEvent(val eventId: Int) : DeepLink()
    data class ChatOpen(val conversationId: Int?) : DeepLink()
    object Chat : DeepLink()
    data class Focus(val text: String, val audioUrl: String?) : DeepLink()
}

private const val EXTRA_TYPE            = "fcm_type"
private const val EXTRA_EVENT_ID        = "fcm_event_id"
private const val EXTRA_CONVERSATION_ID = "fcm_conversation_id"
private const val EXTRA_FOCUS_TEXT      = "fcm_focus_text"
private const val EXTRA_FOCUS_AUDIO     = "fcm_focus_audio"

fun Intent.putDeepLinkExtras(
    type: String?,
    eventId: String? = null,
    conversationId: String? = null,
    focusText: String? = null,
    focusAudio: String? = null,
) {
    type?.let { putExtra(EXTRA_TYPE, it) }
    eventId?.let { putExtra(EXTRA_EVENT_ID, it) }
    conversationId?.let { putExtra(EXTRA_CONVERSATION_ID, it) }
    focusText?.let { putExtra(EXTRA_FOCUS_TEXT, it) }
    focusAudio?.let { putExtra(EXTRA_FOCUS_AUDIO, it) }
}

fun Intent.consumeDeepLink(): DeepLink? {
    // Las notificaciones que construye la app llevan extras fcm_*. Con la app en
    // background los mensajes notification+data los muestra el SISTEMA, y al tocar
    // entrega las claves crudas del data payload (type, event_id...): leer ambas.
    val type = getStringExtra(EXTRA_TYPE) ?: getStringExtra("type") ?: return null
    val eventId = (getStringExtra(EXTRA_EVENT_ID) ?: getStringExtra("event_id"))?.toIntOrNull()
    val convId = (getStringExtra(EXTRA_CONVERSATION_ID) ?: getStringExtra("conversation_id"))?.toIntOrNull()
    val focusText = getStringExtra(EXTRA_FOCUS_TEXT) ?: getStringExtra("body")
    val focusAudio = (getStringExtra(EXTRA_FOCUS_AUDIO) ?: getStringExtra("audio_url"))
        ?.takeIf { it.isNotBlank() }

    val link = when (type) {
        "event_reminder",
        "event_start"    -> eventId?.let { DeepLink.AgendaEvent(it) }
        "chat"           -> DeepLink.ChatOpen(convId)
        "focus_message"  -> DeepLink.Focus(focusText.orEmpty(), focusAudio)
        else             -> null
    }
    removeExtra(EXTRA_TYPE)
    removeExtra(EXTRA_EVENT_ID)
    removeExtra(EXTRA_CONVERSATION_ID)
    removeExtra(EXTRA_FOCUS_TEXT)
    removeExtra(EXTRA_FOCUS_AUDIO)
    removeExtra("type")
    removeExtra("event_id")
    removeExtra("conversation_id")
    removeExtra("body")
    removeExtra("audio_url")
    return link
}
