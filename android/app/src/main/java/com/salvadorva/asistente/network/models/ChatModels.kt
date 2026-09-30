package com.salvadorva.asistente.network.models

data class ChatRequest(
    val prompt: String,
    val conversation_id: Int? = null,
    val history: List<ChatMessage> = emptyList(),
    val generateAudio: Boolean = false,
    val voice: String? = null,
)

data class ChatResponse(
    val reply: String,
    val conversation_id: Int,
    val provider_used: String?,
    val audio_url: String?,
    val event_created: ChatEventCreated?,
    val image_generated: ChatImageGenerated? = null,
    val image_url: String? = null,
    /** F2-06: qué pasó realmente con la agenda en este turno (created, needs_input, needs_clarification…). */
    val agenda: ChatAgenda? = null,
    /** F6-06: pendientes guardados o cambiados en este turno. */
    val tasks: ChatTasks? = null,
)

data class ChatAgenda(
    val status: String? = null,
    val events: List<ChatAgendaEvent>? = null,
    val candidates: List<ChatAgendaEvent>? = null,
    val missing: List<String>? = null,
    val count: Int? = null,
)

data class ChatAgendaEvent(
    val id: Int? = null,
    val title: String? = null,
    val start: String? = null,
    val all_day: Boolean? = null,
    val status: String? = null,
)

data class ChatTasks(
    val status: String? = null,
    val items: List<TaskItem>? = null,
)

data class ChatImageGenerated(
    val url: String,
    val prompt_used: String?,
)

data class ChatMessage(
    val role: String,        // "user" | "assistant"
    val content: String,
    val created_at: String? = null,
)

// Gson no respeta la nulabilidad de Kotlin: todo lo que el servidor podría omitir es opcional.
data class ChatEventCreated(
    val id: Int? = null,
    val title: String? = null,
    val start_date: String? = null,
    val all_day: Boolean? = null,
)
