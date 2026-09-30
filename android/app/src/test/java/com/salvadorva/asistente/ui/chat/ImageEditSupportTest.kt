package com.salvadorva.asistente.ui.chat

import com.google.gson.Gson
import com.salvadorva.asistente.network.models.ImageEditResponse
import com.salvadorva.asistente.util.ImageEditSupport
import org.junit.Assert.assertEquals
import org.junit.Assert.assertTrue
import org.junit.Test

/** IE1: respuestas del backend y mensajes al usuario. */
class ImageEditSupportTest {
    @Test
    fun successResponseParses() {
        val r = Gson().fromJson("""{"id":"9f1b2c3d-0000-4000-8000-000000000001","url":"/api/mobile/images/edits/9f1b2c3d-0000-4000-8000-000000000001","expires_at":"2026-10-07T10:00:00-06:00","conversation_id":12}""",
            ImageEditResponse::class.java)
        assertEquals(12, r.conversation_id)
        assertTrue(r.url!!.startsWith("/api/mobile/images/edits/"))
    }

    @Test
    fun errorsBecomeClearMessagesWithoutProviderDetails() {
        val rejected = Gson().fromJson("""{"code":"content_rejected","message":"No puedo hacer esa edición con esa imagen o instrucción. Prueba con otra.","id":"x"}""", ImageEditResponse::class.java)
        assertEquals("No puedo hacer esa edición con esa imagen o instrucción. Prueba con otra.", ImageEditSupport.errorMessage(422, rejected.code, rejected.message))
        assertTrue(ImageEditSupport.errorMessage(504, "provider_timeout", null).contains("no se reintentó", ignoreCase = true))
        assertEquals("Alcanzaste el límite de imágenes.", ImageEditSupport.errorMessage(429, "image_quota_exceeded", "Alcanzaste el límite de imágenes."))
        assertEquals("No se pudo editar la imagen (500).", ImageEditSupport.errorMessage(500, null, null))
    }
}
