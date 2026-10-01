package com.salvadorva.asistente.network.models

/** IE1: respuesta de POST /api/mobile/images/edit (éxito o error con code/message). */
data class ImageEditResponse(
    val id: String? = null,
    val url: String? = null,
    val expires_at: String? = null,
    val conversation_id: Int? = null,
    val round: Int? = null,
    /** Ajustes que quedan en esta cadena; 0 = era la última imagen. */
    val remaining: Int? = null,
    /** Texto fijo con el que Cirilo entrega la imagen («¿Quedó como querías…?»). */
    val assistant_message: String? = null,
    val code: String? = null,
    val message: String? = null,
    val limit: Int? = null,
)

/** Cuerpo de POST /api/mobile/images/edits/{id}/refine. */
data class ImageRefineRequest(val instruction: String)
