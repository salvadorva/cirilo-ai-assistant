package com.salvadorva.asistente.network.models

/** IE1: respuesta de POST /api/mobile/images/edit (éxito o error con code/message). */
data class ImageEditResponse(
    val id: String? = null,
    val url: String? = null,
    val expires_at: String? = null,
    val conversation_id: Int? = null,
    val code: String? = null,
    val message: String? = null,
    val limit: Int? = null,
)
