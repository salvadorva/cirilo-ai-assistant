package com.salvadorva.asistente.reminders

import com.google.gson.Gson
import com.google.gson.JsonObject
import kotlinx.coroutines.runBlocking
import okhttp3.mockwebserver.MockResponse
import okhttp3.mockwebserver.SocketPolicy
import org.junit.After
import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Test
import java.util.concurrent.TimeUnit

/**
 * Sección 5 (recibos) y sección 1 (registro de instalación). Lista de la sección 6:
 * token renovado con la misma installation_id, app antigua sin la capacidad.
 */
class ReceiptsAndRegistrationTest {

    private val h = Harness(readTimeoutMs = 300)
    private val occ = Fixtures.REMINDER_ID

    @After fun tearDown() = h.shutdown()

    private fun take() = h.server.takeRequest(1, TimeUnit.SECONDS)!!
    private fun body(r: okhttp3.mockwebserver.RecordedRequest) = Gson().fromJson(r.body.readUtf8(), JsonObject::class.java)

    // ── Recibos ─────────────────────────────────────────────────────

    @Test
    fun `recibos received y displayed van solo a receipts y nunca completan`() = runBlocking {
        h.registry.installationId()
        h.engine().onPush(Fixtures.push())
        h.enqueue(200, """{"recorded":true,"event":"received","version":1}""")
        h.enqueue(200, """{"recorded":true,"event":"displayed","version":1}""")
        assertEquals(ReminderEngine.SyncResult.DONE, h.engine().sync())

        val requests = listOf(take(), take())
        requests.forEach { assertEquals("/api/mobile/contextual-reminders/$occ/receipts", it.path) }
        val bodies = requests.map(::body)
        assertEquals(setOf("received", "displayed"), bodies.map { it["event"].asString }.toSet())
        bodies.forEach {
            assertEquals(1, it["version"].asInt)
            assertEquals(Fixtures.INSTALLATION_ID, it["installation_id"].asString)
            assertEquals(Fixtures.json("receipt-request.json").keySet(), it.keySet())
        }
        // El aviso sigue visible: un recibo no es Hecho.
        assertEquals(ReminderLedger.Status.SHOWN, h.ledger.entry(occ)!!.status)
        assertTrue(h.notifications.cancelled.isEmpty())
        assertEquals(2, h.server.requestCount)
    }

    @Test
    fun `los recibos son idempotentes - no se reenvian ni se duplican`() = runBlocking {
        h.registry.installationId()
        val engine = h.engine()
        engine.onPush(Fixtures.push())
        engine.onPush(Fixtures.push()) // duplicado
        h.enqueue(200, """{"recorded":false,"event":"received","version":1}""") // sin despacho: definitivo igual
        h.enqueue(200, """{"recorded":true,"event":"displayed","version":1}""")
        engine.sync()
        engine.sync()
        assertEquals(2, h.server.requestCount)
        assertFalse(h.outbox.enqueueReceipt(occ, 1, ReceiptEvent.RECEIVED))
    }

    @Test
    fun `recibo sin red se reintenta mas tarde`() = runBlocking {
        h.registry.installationId()
        h.engine().onPush(Fixtures.push())
        h.server.enqueue(MockResponse().setSocketPolicy(SocketPolicy.DISCONNECT_AFTER_REQUEST))
        h.server.enqueue(MockResponse().setSocketPolicy(SocketPolicy.DISCONNECT_AFTER_REQUEST))
        assertEquals(ReminderEngine.SyncResult.RETRY, h.engine().sync())
        assertEquals(2, h.outbox.pendingReceipts().size)
    }

    // ── Registro de instalación ─────────────────────────────────────

    private fun ok(ready: Boolean = true) = h.enqueue(
        200,
        """{"message":"ok","installation_id":"${Fixtures.INSTALLATION_ID}","capabilities":["contextual_reminders_v1"],"contextual_reminders_ready":$ready}""",
    )

    @Test
    fun `registro envia installation_id estable, capacidad y app_version`() = runBlocking {
        ok()
        assertEquals(InstallationRegistry.Result.REGISTERED, h.registry.register("fcm-token-A", canDisplay = true, appVersion = "1.0"))
        val req = take()
        assertEquals("POST", req.method)
        assertEquals("/api/mobile/device-token", req.path)
        val json = body(req)
        assertEquals("fcm-token-A", json["token"].asString)
        assertEquals(Fixtures.INSTALLATION_ID, json["installation_id"].asString)
        assertEquals(listOf(CAPABILITY_CONTEXTUAL_REMINDERS_V1), json.getAsJsonArray("capabilities").map { it.asString })
        assertEquals("1.0", json["app_version"].asString)
        assertEquals("android", json["platform"].asString)
        // Mismas claves que la fixture del contrato + token.
        assertEquals(Fixtures.json("device-registration-request.json").keySet() + "token", json.keySet())
        assertTrue(h.registry.isReady)
    }

    @Test
    fun `token renovado conserva la misma installation_id`() = runBlocking {
        val generated = mutableListOf<String>()
        val registry = InstallationRegistry(h.store, h.api) { java.util.UUID.randomUUID().toString().also { generated += it } }
        ok(); ok()
        registry.register("fcm-token-A", true, "1.0")
        assertTrue(registry.needsRegistration("fcm-token-B", true, "1.0"))
        registry.register("fcm-token-B", true, "1.0")
        // Otra instancia (proceso nuevo) lee la misma.
        val again = InstallationRegistry(h.store, h.api) { error("no debe generar otra") }
        val ids = listOf(body(take()), body(take())).map { it["installation_id"].asString }
        assertEquals(1, generated.size)
        assertEquals(listOf(generated[0], generated[0]), ids)
        assertEquals(generated[0], again.installationId())
    }

    @Test
    fun `sin cambios no se vuelve a registrar - al actualizar la app si`() = runBlocking {
        ok()
        h.registry.register("fcm-token-A", true, "1.0")
        assertFalse(h.registry.needsRegistration("fcm-token-A", true, "1.0"))
        assertTrue(h.registry.needsRegistration("fcm-token-A", true, "1.1"))
    }

    @Test
    fun `409 installation_conflict se recuerda y apaga las funciones contextuales`() = runBlocking {
        ok()
        h.registry.register("fcm-token-A", true, "1.0")
        h.enqueue(409, """{"code":"installation_conflict","message":"La instalación pertenece a otra cuenta","request_id":null}""")
        assertEquals(InstallationRegistry.Result.CONFLICT, h.registry.register("fcm-token-A", true, "1.0"))
        assertTrue(h.registry.hasConflict)
        assertFalse(h.registry.isReady)
        // Se vuelve a intentar en el siguiente arranque o inicio de sesión, no en bucle.
        assertTrue(h.registry.needsRegistration("fcm-token-A", true, "1.0"))
        ok()
        h.registry.register("fcm-token-A", true, "1.0")
        assertFalse(h.registry.hasConflict)
    }

    @Test
    fun `sin permiso o canal silenciado se omite la capacidad - app degradada`() = runBlocking {
        ok(ready = false)
        h.registry.register("fcm-token-A", canDisplay = false, appVersion = "1.0")
        val json = body(take())
        assertFalse(json.has("capabilities"))
        assertTrue(json.has("installation_id"))
        assertFalse(h.registry.isReady)
        // Al recuperar el permiso cambia la huella y se vuelve a anunciar.
        assertTrue(h.registry.needsRegistration("fcm-token-A", canDisplay = true, appVersion = "1.0"))
    }

    @Test
    fun `fingerprint igual pero ready false exige un nuevo registro`() = runBlocking {
        // Backend viejo: 200 sin contextual_reminders_ready → se guardó la huella con ready=false.
        h.enqueue(200, """{"message":"ok"}""")
        h.registry.register("fcm-token-A", canDisplay = true, appVersion = "1.0")
        assertFalse(h.registry.isReady)
        // Tras desplegar el backend nuevo, el mismo token/app/permiso debe volver a registrarse.
        assertTrue(h.registry.needsRegistration("fcm-token-A", canDisplay = true, appVersion = "1.0"))
    }

    @Test
    fun `contextual_reminders_ready false deja la app sin funciones contextuales`() = runBlocking {
        ok(ready = false)
        h.registry.register("fcm-token-A", true, "1.0")
        assertFalse(h.registry.isReady)
    }

    @Test
    fun `cerrar sesion libera la instalacion con DELETE`() = runBlocking {
        ok()
        h.registry.register("fcm-token-A", true, "1.0")
        take()
        h.enqueue(200, """{"message":"ok"}""")
        assertTrue(h.registry.unregister("fcm-token-A"))
        val req = take()
        assertEquals("DELETE", req.method)
        assertEquals("/api/mobile/device-token", req.path)
        assertEquals("fcm-token-A", body(req)["token"].asString)
        assertFalse(h.registry.isReady)
        assertTrue(h.registry.needsRegistration("fcm-token-A", true, "1.0"))
    }

    @Test
    fun `app antigua sin la capacidad - un push contextual sin receptor no rompe nada`() {
        // Una instalación sin capabilities no recibe el tipo nuevo (lo garantiza el backend);
        // si aun así llegara uno de esquema desconocido, se ignora sin fallar.
        val outcome = h.engine().onPush(Fixtures.push() + ("schema_version" to "9"))
        assertEquals(ReminderEngine.PushOutcome.Ignored, outcome)
        assertTrue(h.notifications.shown.isEmpty())
        assertNull(h.ledger.entry(occ))
    }
}
