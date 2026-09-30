package com.salvadorva.asistente.ui.chat

import com.google.gson.Gson
import com.salvadorva.asistente.network.models.AgendaEvent
import com.salvadorva.asistente.network.models.AgendaEventsResponse
import com.salvadorva.asistente.network.models.MemoryResponse
import com.salvadorva.asistente.network.models.TaskUpdateRequest
import com.salvadorva.asistente.network.models.TodayResponse
import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Test

/** Formas reales de las respuestas del backend (1.2.0). */
class ModelsParsingTest {
    private val gson = Gson()

    @Test
    fun seriesIdIsAUuidString() {
        val list = gson.fromJson("""{"events":[{"id":7,"title":"Gimnasio","start_date":"2026-10-05T18:00:00-06:00","series_id":"0f8c2b1e-7a4d-4a5e-9b61-3c2d1e0f9a8b"}]}""",
            AgendaEventsResponse::class.java)
        assertEquals("0f8c2b1e-7a4d-4a5e-9b61-3c2d1e0f9a8b", list.events.single().series_id)
    }

    @Test
    fun eventDetailCarriesNoticeStatus() {
        val event = gson.fromJson("""{"id":7,"title":"Cita","notifications":[{"kind":"reminder","channel":"telegram","status":"accepted","label":"Aceptado por el proveedor","attempts":1,"accepted_at":"2026-09-30T08:00:00-06:00"}]}""",
            AgendaEvent::class.java)
        assertEquals("Aceptado por el proveedor", event.notifications!!.single().label)
    }

    @Test
    fun todayIncludesDailySummaryPreferences() {
        val today = gson.fromJson("""{"date":"2026-09-30","events":[],"tasks":[],"suggestions":[],"preferences":{"enabled":true,"time":"08:00","channel":"telegram","days":"daily"}}""",
            TodayResponse::class.java)
        assertEquals("telegram", today.preferences!!.channel)
    }

    @Test
    fun memoryGroupsFactsByCategory() {
        val memory = gson.fromJson("""{"categories":{"personal_info":"Información Personal"},"facts":{"personal_info":[{"id":1,"key":"preferred_name","value":"Salva","confidence":1.0,"source_type":"user_explicit"}]},"total":1,"extraction_enabled":false}""",
            MemoryResponse::class.java)
        assertEquals("Salva", memory.facts!!["personal_info"]!!.single().value)
        assertFalse(memory.extraction_enabled!!)
    }

    @Test
    fun editingATaskOmitsNullFields() {
        val json = gson.toJson(TaskUpdateRequest(title = "Revisar la propuesta", due_date = "2026-10-02"))
        assertEquals("""{"title":"Revisar la propuesta","due_date":"2026-10-02"}""", json)
    }
}
