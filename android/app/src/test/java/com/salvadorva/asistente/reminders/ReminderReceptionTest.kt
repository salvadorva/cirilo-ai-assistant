package com.salvadorva.asistente.reminders

import org.junit.After
import org.junit.Assert.assertEquals
import org.junit.Assert.assertTrue
import org.junit.Test

/**
 * Sección 3 del contrato: recepción, deduplicación persistente y caducidad.
 * Lista de la sección 6: push duplicado o desordenado, versión menor tras una mayor,
 * app cerrada, reinicio, permiso denegado o canal silenciado, reloj incorrecto,
 * aviso caducado.
 */
class ReminderReceptionTest {

    private val h = Harness()
    private val occ = Fixtures.REMINDER_ID

    @After fun tearDown() = h.shutdown()

    @Test
    fun `push valido muestra un aviso generico que suena y encola recibos`() {
        val outcome = h.engine().onPush(Fixtures.push())
        assertTrue(outcome is ReminderEngine.PushOutcome.Shown)
        assertEquals(listOf(FakeNotifications.Shown(occ, 1, alert = true)), h.notifications.shown)
        assertEquals(
            setOf(ReceiptEvent.RECEIVED, ReceiptEvent.DISPLAYED),
            h.outbox.pendingReceipts().map { it.event }.toSet(),
        )
        assertEquals(1, h.scheduled)
    }

    @Test
    fun `push duplicado no vuelve a mostrar ni a sonar`() {
        val engine = h.engine()
        engine.onPush(Fixtures.push())
        assertEquals(ReminderEngine.PushOutcome.Duplicate, engine.onPush(Fixtures.push()))
        assertEquals(1, h.notifications.shown.size)
        assertEquals(2, h.outbox.pendingReceipts().size)
    }

    @Test
    fun `la deduplicacion persiste con la app cerrada o tras reiniciar`() {
        h.engine().onPush(Fixtures.push())
        // Proceso nuevo: motor, ledger y cola nuevos sobre el mismo almacenamiento persistente.
        assertEquals(ReminderEngine.PushOutcome.Duplicate, h.engine().onPush(Fixtures.push()))
        assertEquals(1, h.notifications.alerts)
    }

    @Test
    fun `tras reiniciar se reponen los avisos vigentes sin sonar`() {
        h.engine().onPush(Fixtures.push())
        h.notifications.shown.clear()
        h.engine().restoreAfterReboot()
        assertEquals(listOf(FakeNotifications.Shown(occ, 1, alert = false)), h.notifications.shown)
    }

    @Test
    fun `una version mayor reemplaza el aviso sin volver a sonar`() {
        val engine = h.engine()
        engine.onPush(Fixtures.push(version = 1))
        engine.onPush(Fixtures.push(version = 2))
        assertEquals(
            listOf(FakeNotifications.Shown(occ, 1, true), FakeNotifications.Shown(occ, 2, false)),
            h.notifications.shown,
        )
    }

    @Test
    fun `push desordenado - la version menor que llega despues se descarta`() {
        val engine = h.engine()
        engine.onPush(Fixtures.push(version = 3))
        assertEquals(ReminderEngine.PushOutcome.Stale, engine.onPush(Fixtures.push(version = 2)))
        assertEquals(ReminderEngine.PushOutcome.Stale, engine.onPush(Fixtures.push(version = 1)))
        assertEquals(listOf(FakeNotifications.Shown(occ, 3, true)), h.notifications.shown)
    }

    @Test
    fun `aviso ya caducado no se muestra ni envia recibos`() {
        h.time.now = Fixtures.EXPIRES
        assertEquals(ReminderEngine.PushOutcome.Expired, h.engine().onPush(Fixtures.push()))
        assertTrue(h.notifications.shown.isEmpty())
        assertTrue(h.outbox.pendingReceipts().isEmpty())
        // Y un duplicado posterior tampoco se reevalúa.
        h.time.now = Fixtures.SCHEDULED
        assertEquals(ReminderEngine.PushOutcome.Duplicate, h.engine().onPush(Fixtures.push()))
    }

    @Test
    fun `permiso denegado o canal silenciado - se registra la recepcion pero no displayed`() {
        h.notifications.enabled = false
        val outcome = h.engine().onPush(Fixtures.push())
        assertTrue(outcome is ReminderEngine.PushOutcome.NotDisplayable)
        assertTrue(h.notifications.shown.isEmpty())
        assertEquals(listOf(ReceiptEvent.RECEIVED), h.outbox.pendingReceipts().map { it.event })
    }

    @Test
    fun `reloj del telefono adelantado - la hora del servidor evita descartar un aviso vigente`() {
        // El teléfono cree que ya pasó la caducidad, pero el servidor dice que son las 14:31.
        val serverNow = Fixtures.SCHEDULED + 60_000
        h.time.now = Fixtures.EXPIRES + 3 * 3_600_000
        h.clock.learnServerTime(serverNow)
        assertTrue(h.engine().onPush(Fixtures.push()) is ReminderEngine.PushOutcome.Shown)
    }

    @Test
    fun `reloj del telefono atrasado - la hora del servidor descarta un aviso caducado`() {
        h.time.now = Fixtures.SCHEDULED
        h.clock.learnServerTime(Fixtures.EXPIRES + 60_000)
        assertEquals(ReminderEngine.PushOutcome.Expired, h.engine().onPush(Fixtures.push()))
    }

    @Test
    fun `el desfase se aprende de la cabecera Date del backend`() = kotlinx.coroutines.runBlocking {
        h.engine().onPush(Fixtures.push())
        h.time.now = Fixtures.T0 - 2 * 3_600_000 // teléfono 2 h atrasado
        h.enqueue(200, """{"recorded":true}""", mapOf("Date" to "Mon, 21 Sep 2026 14:31:00 GMT"))
        h.enqueue(200, """{"recorded":true}""")
        h.registry.installationId()
        h.engine().sync()
        assertEquals(Fixtures.SCHEDULED + 60_000, h.clock.now())
    }

    @Test
    fun `el ledger descarta entradas viejas pasada la retencion`() {
        h.engine().onPush(Fixtures.push())
        h.time.now = Fixtures.EXPIRES + ReminderLedger.RETENTION_AFTER_EXPIRY_MS + 1
        h.engine().onPush(Fixtures.push(id = "11111111-2222-3333-4444-555555555555", expiresAt = "2026-10-01T16:00:00Z"))
        assertEquals(null, h.ledger.entry(occ))
    }
}
