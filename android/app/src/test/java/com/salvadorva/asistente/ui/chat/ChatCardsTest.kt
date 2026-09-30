package com.salvadorva.asistente.ui.chat

import com.google.gson.Gson
import com.salvadorva.asistente.network.models.ChatResponse
import com.salvadorva.asistente.network.models.TodayResponse
import org.junit.Assert.assertEquals
import org.junit.Assert.assertTrue
import org.junit.Test

/** Respuestas con la forma real del backend (F2-06, F6-06), parseadas con Gson como en la app. */
class ChatCardsTest {
    private val gson = Gson()

    private fun parse(json: String): ChatResponse = gson.fromJson(json, ChatResponse::class.java)

    @Test
    fun createdEventUsesTheAgendaContract() {
        val items = ChatCards.from(parse("""{"reply":"Listo","conversation_id":1,
            "event_created":{"id":5,"title":"Llamar a Ana","start_date":"2026-09-22T10:00:00-06:00","all_day":false},
            "agenda":{"status":"created","count":1,"events":[{"id":5,"title":"Llamar a Ana","start":"2026-09-22T10:00:00-06:00","all_day":false}]}}"""))
        val card = items.single() as ChatItem.EventCreated
        assertEquals("Llamar a Ana", card.title)
        assertEquals("2026-09-22T10:00:00-06:00", card.startDate)
        assertEquals("✓ evento agendado", card.label)
    }

    @Test
    fun clarificationShowsCandidatesAndNoSavedEvent() {
        val items = ChatCards.from(parse("""{"reply":"¿Cuál?","conversation_id":1,"event_created":null,
            "agenda":{"status":"needs_clarification","events":[],"candidates":[
              {"id":1,"title":"Cita con Ana","start":"2026-09-21T09:00:00-06:00","all_day":false},
              {"id":2,"title":"Cita con Luis","start":"2026-09-21T10:00:00-06:00","all_day":false}]}}"""))
        assertEquals(2, (items.single() as ChatItem.AgendaCandidates).candidates.size)
    }

    @Test
    fun oldServerWithoutAgendaStillShowsTheEvent() {
        val items = ChatCards.from(parse("""{"reply":"Listo","conversation_id":1,
            "event_created":{"id":5,"title":"Dentista","start_date":"2026-09-22T10:00:00-06:00","all_day":false}}"""))
        assertEquals("Dentista", (items.single() as ChatItem.EventCreated).title)
    }

    @Test
    fun eventCreatedWithoutStartDateDoesNotCrash() {
        // Forma que el backend nuevo mandaba antes de la corrección (sin start_date).
        val items = ChatCards.from(parse("""{"reply":"Listo","conversation_id":1,"event_created":{"id":5,"series_id":null,"title":"Dentista","count":1}}"""))
        assertEquals(null, (items.single() as ChatItem.EventCreated).startDate)
    }

    @Test
    fun savedTaskAndNothingElse() {
        val items = ChatCards.from(parse("""{"reply":"Guardado","conversation_id":1,"event_created":null,
            "agenda":{"status":"none","events":[]},
            "tasks":{"status":"created","items":[{"id":3,"title":"Revisar la propuesta","status":"open","due_date":null}]}}"""))
        val card = items.single() as ChatItem.TaskSaved
        assertEquals("Revisar la propuesta", card.title)
        assertEquals(null, card.dueDate)
    }

    @Test
    fun plainReplyHasNoCards() {
        assertTrue(ChatCards.from(parse("""{"reply":"Hola","conversation_id":1,"event_created":null,"agenda":{"status":"none"},"tasks":{"status":"none","items":[]}}""")).isEmpty())
    }

    @Test
    fun todayResponseParses() {
        val today = gson.fromJson("""{"date":"2026-09-21","date_label":"lunes 21 de septiembre","summary":"Tienes 1 compromiso y 1 pendiente.",
            "events":[{"id":1,"title":"Dentista","time":"10:00","all_day":false,"location":null,"url":"/agenda?fecha=2026-09-21&evento=1"}],
            "tasks":[{"id":3,"title":"Pagar la luz","status":"open","due_date":"2026-09-19","postponed_until":null,"source":"web","url":"/hoy#tarea-3"}],
            "suggestions":[]}""", TodayResponse::class.java)
        assertEquals("Dentista", today.events!!.single().title)
        assertEquals("2026-09-19", today.tasks!!.single().due_date)
    }
}
