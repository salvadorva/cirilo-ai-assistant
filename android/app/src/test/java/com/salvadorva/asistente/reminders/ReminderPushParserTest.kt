package com.salvadorva.asistente.reminders

import org.junit.Assert.assertEquals
import org.junit.Assert.assertTrue
import org.junit.Test

/** Sección 2 del contrato: payload data-only v1 congelado. */
class ReminderPushParserTest {

    @Test
    fun `la fixture congelada se interpreta completa`() {
        val result = ReminderPushParser.parse(Fixtures.push())
        assertTrue(result is PushParseResult.Valid)
        val push = (result as PushParseResult.Valid).push
        assertEquals(Fixtures.REMINDER_ID, push.reminderId)
        assertEquals(Fixtures.REMINDER_ID, push.occurrenceId)
        assertEquals(1, push.version)
        assertEquals(Fixtures.SCHEDULED, push.scheduledAtMillis)
        assertEquals(Fixtures.EXPIRES, push.expiresAtMillis)
        assertEquals("Cirilo", push.title)
        assertEquals("Tienes un recordatorio acordado", push.body)
    }

    @Test
    fun `una version de esquema desconocida se ignora sin fallar`() {
        val result = ReminderPushParser.parse(Fixtures.push() + ("schema_version" to "2"))
        assertEquals(PushParseResult.UnknownSchema("2"), result)
    }

    @Test
    fun `otros tipos no son de este receptor`() {
        assertEquals(PushParseResult.NotContextual, ReminderPushParser.parse(mapOf("type" to "focus_message")))
        assertEquals(PushParseResult.NotContextual, ReminderPushParser.parse(emptyMap()))
    }

    @Test
    fun `campos invalidos se rechazan`() {
        val base = Fixtures.push()
        listOf(
            base + ("version" to "0"),
            base + ("version" to "uno"),
            base - "occurrence_id",
            base + ("reminder_id" to "<uuid>"),
            base + ("expires_at" to "2026-09-21T16:00:00-06:00"),
            base + ("expires_at" to "2026-09-21T14:30:00Z"), // no posterior a scheduled_at
            base - "scheduled_at",
        ).forEach { data ->
            assertTrue(data.toString(), ReminderPushParser.parse(data) is PushParseResult.Invalid)
        }
    }

    @Test
    fun `audio_ready se ignora y el texto vacio cae al generico`() {
        val push = (ReminderPushParser.parse(Fixtures.push() + mapOf("audio_ready" to "true", "title" to "", "body" to "")) as PushParseResult.Valid).push
        assertEquals("Cirilo", push.title)
        assertEquals("Tienes un recordatorio acordado", push.body)
    }
}
