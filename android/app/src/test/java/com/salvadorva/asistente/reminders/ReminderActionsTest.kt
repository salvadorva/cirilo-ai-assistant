package com.salvadorva.asistente.reminders

import com.google.gson.Gson
import com.google.gson.JsonObject
import kotlinx.coroutines.runBlocking
import okhttp3.mockwebserver.MockResponse
import okhttp3.mockwebserver.RecordedRequest
import okhttp3.mockwebserver.SocketPolicy
import org.junit.After
import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertNotNull
import org.junit.Assert.assertTrue
import org.junit.Test

/**
 * Sección 4 del contrato: Hecho, Posponer 15/30/60 y Cancelar con versión e
 * Idempotency-Key. Lista de la sección 6: tres acciones y selector, doble toque,
 * sin conexión, sesión expirada, aviso ya cancelado o caducado.
 */
class ReminderActionsTest {

    private val h = Harness(readTimeoutMs = 300)
    private val occ = Fixtures.REMINDER_ID

    @After fun tearDown() = h.shutdown()

    private fun showReminder(version: Int = 1) {
        h.engine().onPush(Fixtures.push(version = version))
        // Los recibos no interesan aquí: se dan por enviados.
        h.outbox.pendingReceipts().forEach(h.outbox::markReceiptDone)
    }

    private fun take(): RecordedRequest = h.server.takeRequest(1, java.util.concurrent.TimeUnit.SECONDS)!!
    private fun RecordedRequest.json(): JsonObject = Gson().fromJson(body.readUtf8(), JsonObject::class.java)

    // ── Hecho y Cancelar desde la notificación ──────────────────────

    @Test
    fun `Hecho desde la notificacion - pendiente hasta que el servidor confirma`() = runBlocking {
        showReminder()
        h.engine().requestAction(occ, 1, ReminderAction.COMPLETE)
        assertEquals(listOf(occ to "Hecho · pendiente de sincronizar"), h.notifications.pendingSync)
        assertEquals(ReminderLedger.Status.PENDING_SYNC, h.ledger.entry(occ)!!.status)
        assertTrue(h.notifications.cancelled.isEmpty())

        h.enqueue(200, Fixtures.detailJson(state = "completed", version = 2))
        assertEquals(ReminderEngine.SyncResult.DONE, h.engine().sync())

        val req = take()
        assertEquals("/api/mobile/contextual-reminders/$occ/complete", req.path)
        val body = req.json()
        assertEquals(1, body["expected_version"].asInt)
        assertFalse(body.has("minutes"))
        val key = req.getHeader("Idempotency-Key")!!
        assertTrue(key.length in 16..100)
        assertEquals(listOf(occ), h.notifications.cancelled)
        assertEquals(ReminderLedger.Status.CLOSED, h.ledger.entry(occ)!!.status)
        assertTrue(h.outbox.pendingActions().isEmpty())
    }

    @Test
    fun `Cancelar usa su endpoint con la version del aviso`() = runBlocking {
        showReminder(version = 3)
        h.engine().requestAction(occ, 3, ReminderAction.CANCEL)
        h.enqueue(200, Fixtures.detailJson(state = "cancelled", version = 4))
        h.engine().sync()
        val req = take()
        assertEquals("/api/mobile/contextual-reminders/$occ/cancel", req.path)
        assertEquals(3, req.json()["expected_version"].asInt)
    }

    @Test
    fun `doble toque produce una sola peticion con una sola clave`() = runBlocking {
        showReminder()
        val engine = h.engine()
        engine.requestAction(occ, 1, ReminderAction.COMPLETE)
        engine.requestAction(occ, 1, ReminderAction.COMPLETE)
        assertEquals(1, h.outbox.pendingActions().size)

        h.enqueue(200, Fixtures.detailJson(state = "completed", version = 2))
        engine.sync()
        assertEquals(1, h.server.requestCount)
    }

    @Test
    fun `sin conexion se conserva la intencion y se reenvia con la misma clave`() = runBlocking {
        showReminder()
        h.engine().requestAction(occ, 1, ReminderAction.COMPLETE)

        h.server.enqueue(MockResponse().setSocketPolicy(SocketPolicy.DISCONNECT_AFTER_REQUEST))
        assertEquals(ReminderEngine.SyncResult.RETRY, h.engine().sync())
        assertEquals(1, h.outbox.pendingActions().size)
        assertTrue(h.notifications.cancelled.isEmpty()) // nunca se da por confirmada

        h.enqueue(200, Fixtures.detailJson(state = "completed", version = 2), mapOf("Idempotent-Replayed" to "true"))
        assertEquals(ReminderEngine.SyncResult.DONE, h.engine().sync())
        val first = take()
        val second = take()
        assertEquals(first.getHeader("Idempotency-Key"), second.getHeader("Idempotency-Key"))
        assertEquals(first.path, second.path)
    }

    @Test
    fun `timeout repite la misma peticion con la misma clave`() = runBlocking {
        showReminder()
        h.engine().requestAction(occ, 1, ReminderAction.COMPLETE)
        h.server.enqueue(MockResponse().setSocketPolicy(SocketPolicy.NO_RESPONSE))
        assertEquals(ReminderEngine.SyncResult.RETRY, h.engine().sync())
        h.enqueue(200, Fixtures.detailJson(state = "completed", version = 2))
        h.engine().sync()
        assertEquals(take().getHeader("Idempotency-Key"), take().getHeader("Idempotency-Key"))
    }

    @Test
    fun `sesion expirada - la accion queda en cola y el aviso lo indica`() = runBlocking {
        showReminder()
        h.engine().requestAction(occ, 1, ReminderAction.COMPLETE)
        h.enqueue(401, """{"message":"Unauthenticated."}""")
        assertEquals(ReminderEngine.SyncResult.SESSION_EXPIRED, h.engine().sync())
        assertEquals(1, h.outbox.pendingActions().size)
        assertEquals("Inicia sesión en Cirilo para sincronizar", h.notifications.pendingSync.last().second)

        h.enqueue(200, Fixtures.detailJson(state = "completed", version = 2))
        assertEquals(ReminderEngine.SyncResult.DONE, h.engine().sync())
        assertEquals(take().getHeader("Idempotency-Key"), take().getHeader("Idempotency-Key"))
    }

    // ── 409: refrescar con `current`, nunca forzar otra versión ─────

    @Test
    fun `version_conflict refresca el aviso con la version actual y no reintenta`() = runBlocking {
        showReminder()
        h.engine().requestAction(occ, 1, ReminderAction.COMPLETE)
        h.enqueue(409, Fixtures.conflictJson("version_conflict", version = 2))
        assertEquals(ReminderEngine.SyncResult.DONE, h.engine().sync())
        assertEquals(1, h.server.requestCount)
        assertTrue(h.outbox.pendingActions().isEmpty())
        assertEquals(FakeNotifications.Shown(occ, 2, alert = false), h.notifications.shown.last())
        assertEquals(2, h.ledger.entry(occ)!!.version)
    }

    @Test
    fun `aviso ya cancelado en el servidor se retira`() = runBlocking {
        showReminder()
        h.engine().requestAction(occ, 1, ReminderAction.COMPLETE)
        h.enqueue(409, Fixtures.conflictJson("invalid_state", state = "cancelled", version = 2))
        h.engine().sync()
        assertEquals(listOf(occ), h.notifications.cancelled)
        assertEquals(ReminderLedger.Status.CLOSED, h.ledger.entry(occ)!!.status)
    }

    @Test
    fun `aviso caducado en el servidor se retira`() = runBlocking {
        showReminder()
        h.engine().requestAction(occ, 1, ReminderAction.CANCEL)
        h.enqueue(409, Fixtures.conflictJson("reminder_expired", state = "expired", version = 2))
        h.engine().sync()
        assertEquals(listOf(occ), h.notifications.cancelled)
    }

    @Test
    fun `idempotency_conflict sin current se reintenta mas tarde`() = runBlocking {
        showReminder()
        h.engine().requestAction(occ, 1, ReminderAction.COMPLETE)
        h.enqueue(409, """{"code":"idempotency_conflict","message":"en curso","request_id":null}""")
        assertEquals(ReminderEngine.SyncResult.RETRY, h.engine().sync())
        assertEquals(1, h.outbox.pendingActions().size)
    }

    // ── Posponer: selector 15/30/60 desde el detalle ────────────────

    @Test
    fun `el selector ofrece solo las opciones que expone el detalle`() {
        val gson = Gson()
        val now = Fixtures.SCHEDULED
        fun options(json: String) = gson.fromJson(json, ReminderDetail::class.java).offeredSnoozeMinutes(now)

        assertEquals(listOf(15, 30, 60), options(Fixtures.detailJson()))
        assertEquals(listOf(15, 30), options(Fixtures.detailJson(snooze = listOf(30, 15))))
        assertEquals(listOf(15), options(Fixtures.detailJson(snooze = listOf(15, 45)))) // 45 no es opción de la app
        assertEquals(emptyList<Int>(), options(Fixtures.detailJson(state = "completed")))
        // Detalle viejo en pantalla: a las 15:20 ya no cabe posponer 60 antes de las 16:00.
        assertEquals(listOf(15, 30), gson.fromJson(Fixtures.detailJson(), ReminderDetail::class.java)
            .offeredSnoozeMinutes(Fixtures.EXPIRES - 40 * 60_000))
    }

    @Test
    fun `Posponer envia minutes y el push de la nueva version vuelve a mostrarse`() = runBlocking {
        showReminder()
        h.enqueue(200, Fixtures.detailJson(version = 2, scheduledAt = "2026-09-21T15:01:00Z"))
        val outcome = h.engine().performInApp(occ, 1, ReminderAction.SNOOZE, 30)
        assertTrue(outcome is ReminderEngine.ActionOutcome.Confirmed)

        val req = take()
        assertEquals("/api/mobile/contextual-reminders/$occ/snooze", req.path)
        val body = req.json()
        assertEquals(1, body["expected_version"].asInt)
        assertEquals(30, body["minutes"].asInt)
        assertNotNull(req.getHeader("Idempotency-Key"))
        assertEquals(listOf(occ), h.notifications.cancelled)
        assertEquals(ReminderLedger.Status.WAITING, h.ledger.entry(occ)!!.status)

        // A las 15:01 el backend despacha la versión 2: es un aviso nuevo, no un duplicado.
        h.time.now = ReminderPushParser.parseUtc("2026-09-21T15:01:00Z")!!
        val push = Fixtures.push(version = 2) + ("scheduled_at" to "2026-09-21T15:01:00Z")
        assertTrue(h.engine().onPush(push) is ReminderEngine.PushOutcome.Shown)
        assertEquals(FakeNotifications.Shown(occ, 2, alert = true), h.notifications.shown.last())
    }

    @Test
    fun `Posponer sin confirmar no queda en cola ni se da por hecho`() = runBlocking {
        showReminder()
        repeat(ReminderEngine.IN_APP_ATTEMPTS) {
            h.server.enqueue(MockResponse().setSocketPolicy(SocketPolicy.DISCONNECT_AFTER_REQUEST))
        }
        val outcome = h.engine().performInApp(occ, 1, ReminderAction.SNOOZE, 15)
        assertEquals(ReminderEngine.ActionOutcome.RetryLater, outcome)
        assertTrue(h.outbox.pendingActions().isEmpty())
        assertTrue(h.notifications.cancelled.isEmpty())
        // Los reintentos en línea repitieron la misma clave.
        assertEquals(take().getHeader("Idempotency-Key"), take().getHeader("Idempotency-Key"))
    }

    @Test
    fun `snooze_exceeds_expiry refresca con el estado actual`() = runBlocking {
        showReminder()
        h.enqueue(409, Fixtures.conflictJson("snooze_exceeds_expiry", version = 1))
        val outcome = h.engine().performInApp(occ, 1, ReminderAction.SNOOZE, 60)
        assertEquals("snooze_exceeds_expiry", (outcome as ReminderEngine.ActionOutcome.Conflict).code)
        assertTrue(h.outbox.pendingActions().isEmpty())
    }

    @Test
    fun `Hecho en la app sin conexion queda pendiente de sincronizar`() = runBlocking {
        showReminder()
        repeat(ReminderEngine.IN_APP_ATTEMPTS) {
            h.server.enqueue(MockResponse().setSocketPolicy(SocketPolicy.DISCONNECT_AFTER_REQUEST))
        }
        assertEquals(ReminderEngine.ActionOutcome.RetryLater, h.engine().performInApp(occ, 1, ReminderAction.COMPLETE))
        assertEquals(1, h.outbox.pendingActions().size)
        assertEquals(ReminderLedger.Status.PENDING_SYNC, h.ledger.entry(occ)!!.status)
        assertTrue(h.scheduled >= 2)
    }

    // ── Reconciliación al abrir la app ──────────────────────────────

    @Test
    fun `reconciliar retira avisos que ya no estan pendientes en el servidor`() = runBlocking {
        showReminder()
        h.enqueue(200, """{"data":[],"meta":{"total":0,"per_page":50,"current_page":1,"last_page":1}}""")
        assertTrue(h.engine().reconcile())
        assertEquals("/api/mobile/contextual-reminders?state=pending&per_page=50", take().path)
        assertEquals(listOf(occ), h.notifications.cancelled)
    }

    @Test
    fun `reconciliar con el listado sin contexto de v1_1 oculta un aviso pospuesto`() = runBlocking {
        showReminder()
        // §8.2: el listado solo trae el resumen + title; nada de context, next_action ni actions.
        h.enqueue(200, """{"data":[{"id":"$occ","occurrence_id":"$occ","state":"pending","version":2,""" +
            """"scheduled_at":"2026-09-21T15:10:00Z","expires_at":"2026-09-21T16:00:00Z","timezone":"America/Guatemala",""" +
            """"with_audio":false,"dispatch":{"state":"pending","error_category":null},"title":"Enviar propuesta a Ana"}],""" +
            """"meta":{"total":1,"per_page":50,"current_page":1,"last_page":1}}""")
        assertTrue(h.engine().reconcile())
        val entry = h.ledger.entry(occ)!!
        assertEquals(ReminderLedger.Status.WAITING, entry.status)
        assertEquals(2, entry.version)
        assertEquals(listOf(occ), h.notifications.cancelled)
        // El push de la versión 2 a las 15:10 vuelve a mostrarse.
        h.time.now = ReminderPushParser.parseUtc("2026-09-21T15:10:00Z")!!
        assertTrue(h.engine().onPush(Fixtures.push(version = 2) + ("scheduled_at" to "2026-09-21T15:10:00Z"))
            is ReminderEngine.PushOutcome.Shown)
    }

    @Test
    fun `reconciliar sin red retira igualmente los caducados`() = runBlocking {
        showReminder()
        h.time.now = Fixtures.EXPIRES + 1
        h.server.enqueue(MockResponse().setSocketPolicy(SocketPolicy.DISCONNECT_AFTER_REQUEST))
        h.engine().reconcile()
        assertEquals(listOf(occ), h.notifications.cancelled)
    }
}
