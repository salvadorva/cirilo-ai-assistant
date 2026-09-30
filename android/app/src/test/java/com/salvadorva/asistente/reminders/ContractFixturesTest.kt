package com.salvadorva.asistente.reminders

import com.google.gson.Gson
import com.google.gson.JsonObject
import org.junit.Assert.assertEquals
import org.junit.Assert.assertTrue
import org.junit.Test

/**
 * Las fixtures congeladas del backend encajan con los modelos de la app. Si el
 * backend cambia una clave, esta prueba falla: es una nueva versión del contrato.
 */
class ContractFixturesTest {

    private val gson = Gson()

    @Test
    fun `el push v1 trae exactamente las claves que interpreta la app`() {
        assertEquals(
            setOf("type", "schema_version", "reminder_id", "occurrence_id", "version", "scheduled_at",
                "expires_at", "title", "body", "audio_ready"),
            Fixtures.json("push-contextual-reminder-v1.json").keySet(),
        )
    }

    @Test
    fun `el detalle se deserializa completo`() {
        val d = gson.fromJson(Fixtures.withIds("reminder-detail-response.json"), ReminderDetail::class.java)
        assertEquals("pending", d.state)
        assertEquals(1, d.version)
        assertEquals("Enviar propuesta a Ana", d.title)
        assertEquals("Abrir el borrador y enviarlo por correo.", d.next_action)
        assertEquals(listOf(15, 30, 60), d.actions!!.snooze_minutes)
        assertTrue(d.actions!!.complete && d.actions!!.cancel)
        assertEquals(false, d.audio_ready)
        assertEquals(null, d.delivery_receipt!!.displayed_at)
        // Todas las claves de la fixture tienen campo en el modelo.
        val modelFields = ReminderDetail::class.java.declaredFields.map { it.name }.toSet()
        assertTrue(modelFields.containsAll(Fixtures.json("reminder-detail-response.json").keySet()))
    }

    @Test
    fun `el 409 trae code y current`() {
        val e = gson.fromJson(Fixtures.withIds("conflict-response.json"), ReminderApiError::class.java)
        assertEquals("version_conflict", e.code)
        assertEquals(2, e.current!!.version)
        assertEquals("pending", e.current!!.state)
    }

    @Test
    fun `las peticiones de la app serializan las claves del contrato`() {
        val receipt = gson.toJsonTree(ReceiptRequest("displayed", 1, Fixtures.INSTALLATION_ID)).asJsonObject
        assertEquals(Fixtures.json("receipt-request.json"), receipt)

        val registration = gson.toJsonTree(
            DeviceRegistrationRequest("tok", installation_id = Fixtures.INSTALLATION_ID,
                capabilities = listOf(CAPABILITY_CONTEXTUAL_REMINDERS_V1), app_version = "1.4.0")
        ).asJsonObject
        assertEquals(Fixtures.json("device-registration-request.json").keySet() + "token", registration.keySet())

        val complete = gson.toJsonTree(ReminderActionRequest(expected_version = 2)).asJsonObject
        assertEquals(gson.fromJson("""{"expected_version":2}""", JsonObject::class.java), complete)
        val snooze = gson.toJsonTree(ReminderActionRequest(expected_version = 2, minutes = 30)).asJsonObject
        assertEquals(gson.fromJson("""{"expected_version":2,"minutes":30}""", JsonObject::class.java), snooze)
    }
}
