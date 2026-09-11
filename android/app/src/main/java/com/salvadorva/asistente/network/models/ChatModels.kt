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

data class ChatEventCreated(
    val id: Int,
    val title: String,
    val start_date: String,
    val all_day: Boolean = false,
)
