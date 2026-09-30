package com.salvadorva.asistente.reminders

import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertTrue
import org.junit.Test

/** Estado y acción de la tarjeta de Ajustes (bug RC3: «no habilitado» sin ninguna acción). */
class RemindersStatusTest {

    @Test
    fun `permitidas, sin conflicto y no lista - registro pendiente con accion Reintentar registro`() {
        val view = remindersStatusOf(hasConflict = false, canDisplay = true, isReady = false, canOfferClaim = false)
        assertEquals(RemindersStatus.PENDING_SERVER, view.status)
        assertEquals(RemindersAction.RETRY_REGISTRATION, view.action)
    }

    @Test
    fun `listo no ofrece accion`() {
        val view = remindersStatusOf(hasConflict = false, canDisplay = true, isReady = true, canOfferClaim = false)
        assertEquals(RemindersStatusView(RemindersStatus.READY, RemindersAction.NONE), view)
    }

    @Test
    fun `permiso o canal bloqueado no ofrece registrar aunque no este listo`() {
        val view = remindersStatusOf(hasConflict = false, canDisplay = false, isReady = false, canOfferClaim = false)
        assertEquals(RemindersStatusView(RemindersStatus.BLOCKED, RemindersAction.NONE), view)
    }

    @Test
    fun `conflicto tiene prioridad y ofrece reclamar solo si el reclamo esta disponible`() {
        assertEquals(
            RemindersStatusView(RemindersStatus.CONFLICT, RemindersAction.CLAIM),
            remindersStatusOf(hasConflict = true, canDisplay = false, isReady = false, canOfferClaim = true),
        )
        assertEquals(
            RemindersStatusView(RemindersStatus.CONFLICT, RemindersAction.NONE),
            remindersStatusOf(hasConflict = true, canDisplay = true, isReady = false, canOfferClaim = false),
        )
    }

    @Test
    fun `reintentar registro solo aparece con notificaciones permitidas, sin conflicto y no lista`() {
        for (conflict in listOf(true, false)) for (display in listOf(true, false))
            for (ready in listOf(true, false)) for (claim in listOf(true, false)) {
                val action = remindersStatusOf(conflict, display, ready, claim).action
                assertEquals(
                    "conflict=$conflict display=$display ready=$ready claim=$claim",
                    display && !conflict && !ready,
                    action == RemindersAction.RETRY_REGISTRATION,
                )
            }
    }

    @Test
    fun `los cuatro estados tienen textos distintos y el antiguo ya no aparece`() {
        val texts = RemindersStatus.entries.map { it.text }
        assertEquals(4, texts.toSet().size)
        assertTrue(RemindersStatus.BLOCKED.text.contains("permiso"))
        assertTrue(RemindersStatus.PENDING_SERVER.text.contains("registro pendiente"))
        assertTrue(RemindersStatus.READY.text.startsWith("listo"))
        assertTrue(RemindersStatus.CONFLICT.text.startsWith("conflicto"))
        texts.forEach { assertFalse(it.contains("no habilitado para este teléfono")) }
    }

    @Test
    fun `el feedback nunca da por listo un registro solo solicitado`() {
        assertTrue(REGISTRATION_REQUESTED_MESSAGE.contains("pendiente"))
        assertFalse(REGISTRATION_REQUESTED_MESSAGE.contains("listo"))
        assertTrue(registrationFinishedMessage(RemindersStatus.READY).contains("listo"))
        assertFalse(registrationFinishedMessage(RemindersStatus.PENDING_SERVER).contains("listo"))
    }
}
