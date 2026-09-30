package com.salvadorva.asistente.reminders

import com.google.gson.Gson
import com.google.gson.JsonObject
import kotlinx.coroutines.runBlocking
import okhttp3.mockwebserver.MockResponse
import okhttp3.mockwebserver.SocketPolicy
import org.junit.After
import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertTrue
import org.junit.Test
import java.util.concurrent.TimeUnit

/**
 * Reclamo explícito de instalación (contrato v1.1, §8.1):
 * `POST /api/mobile/device-token/claim`.
 */
class InstallationClaimFlowTest {

    private val h = Harness(readTimeoutMs = 300)
    private val registry = InstallationRegistry(h.store, h.api, now = { h.time.now }, newId = { Fixtures.INSTALLATION_ID })
    private var fcmToken: String? = "fcm-token-vigente"
    private var reRegistrations = 0
    private var cleared = 0
    private var requestsWhenCleared = -1

    @After fun tearDown() = h.shutdown()

    private val claimer = ApiInstallationClaimer(registry, { fcmToken }, { true }, { "1.0" })

    private val flow = InstallationClaimFlow(
        registry, claimer,
        clearLocalState = {
            cleared++
            requestsWhenCleared = h.server.requestCount
            h.ledger.clear()
            h.outbox.clear()
        },
        reRegister = { reRegistrations++ },
    )

    private fun conflict() = runBlocking {
        h.enqueue(409, """{"code":"installation_conflict","message":"otra cuenta","request_id":null}""")
        registry.register("fcm-token-vigente", true, "1.0")
        h.server.takeRequest(1, TimeUnit.SECONDS)
        assertTrue(registry.hasConflict)
    }

    private fun claimOk(alreadyOwned: Boolean = false, ready: Boolean = true) = h.enqueue(
        200,
        """{"message":"ok","claimed":${!alreadyOwned},"already_owned":$alreadyOwned,"installation_id":"${Fixtures.INSTALLATION_ID}",""" +
            """"capabilities":["contextual_reminders_v1"],"contextual_reminders_ready":$ready}""",
    )

    private fun error(status: Int, code: String, headers: Map<String, String> = emptyMap()) =
        h.enqueue(status, """{"code":"$code","message":"x","request_id":null}""", headers)

    // ── Solo explícito ──────────────────────────────────────────────

    @Test
    fun `sin conflicto no se ofrece ni se llama`() = runBlocking {
        assertFalse(flow.canOffer())
        assertEquals(ClaimResult.NotOffered, flow.claim(confirmedByUser = true))
        assertEquals(0, h.server.requestCount)
        assertEquals(0, cleared)
    }

    @Test
    fun `sin confirmacion del usuario no se llama ni se borra nada`() = runBlocking {
        conflict()
        assertTrue(flow.canOffer())
        assertEquals(ClaimResult.NotOffered, flow.claim(confirmedByUser = false))
        assertEquals(1, h.server.requestCount) // solo el registro que dio 409
        assertEquals(0, cleared)
    }

    // ── Éxito ───────────────────────────────────────────────────────

    @Test
    fun `envia installation_id y token FCM vigente como prueba de posesion`() = runBlocking {
        conflict()
        claimOk()
        flow.claim(confirmedByUser = true)
        val req = h.server.takeRequest(1, TimeUnit.SECONDS)!!
        assertEquals("POST", req.method)
        assertEquals("/api/mobile/device-token/claim", req.path)
        val body = Gson().fromJson(req.body.readUtf8(), JsonObject::class.java)
        assertEquals(Fixtures.INSTALLATION_ID, body["installation_id"].asString)
        assertEquals("fcm-token-vigente", body["token"].asString)
        assertEquals(CAPABILITY_CONTEXTUAL_REMINDERS_V1, body.getAsJsonArray("capabilities")[0].asString)
        // Mismo cuerpo que el registro (contrato: «cuerpo igual al del registro»).
        assertEquals(Fixtures.json("device-registration-request.json").keySet() + "token", body.keySet())
    }

    @Test
    fun `borra el estado local de la cuenta anterior antes de reclamar`() = runBlocking {
        h.engine().onPush(Fixtures.push())
        h.engine().requestAction(Fixtures.REMINDER_ID, 1, ReminderAction.COMPLETE)
        conflict()
        claimOk()
        flow.claim(confirmedByUser = true)
        assertEquals(1, cleared)
        assertEquals(1, requestsWhenCleared) // se borró antes de la petición de reclamo
        assertTrue(h.ledger.all().isEmpty())
        assertTrue(h.outbox.pendingActions().isEmpty())
        assertTrue(h.outbox.pendingReceipts().isEmpty())
    }

    @Test
    fun `tras el exito desaparece el conflicto y se vuelve a registrar`() = runBlocking {
        conflict()
        claimOk(ready = true)
        assertEquals(ClaimResult.Claimed(alreadyOwned = false), flow.claim(confirmedByUser = true))
        assertFalse(registry.hasConflict)
        assertTrue(registry.isReady)
        assertEquals(1, reRegistrations)
        assertFalse(flow.canOffer())
    }

    @Test
    fun `already_owned es exito idempotente`() = runBlocking {
        conflict()
        claimOk(alreadyOwned = true)
        assertEquals(ClaimResult.Claimed(alreadyOwned = true), flow.claim(confirmedByUser = true))
        assertFalse(registry.hasConflict)
        assertEquals(1, reRegistrations)
    }

    // ── Errores ─────────────────────────────────────────────────────

    @Test
    fun `403 possession_not_proven conserva el conflicto y no re-registra`() = runBlocking {
        conflict()
        error(403, "possession_not_proven")
        assertEquals(ClaimResult.PossessionNotProven, flow.claim(confirmedByUser = true))
        assertTrue(registry.hasConflict)
        assertEquals(0, reRegistrations)
    }

    @Test
    fun `404 installation_not_found pasa al registro normal`() = runBlocking {
        conflict()
        error(404, "installation_not_found")
        assertEquals(ClaimResult.NotFound, flow.claim(confirmedByUser = true))
        assertFalse(registry.hasConflict)
        assertEquals(1, reRegistrations)
    }

    @Test
    fun `429 respeta Retry-After sin volver a llamar ni borrar hasta que venza`() = runBlocking {
        conflict()
        error(429, "rate_limited", mapOf("Retry-After" to "120"))
        assertEquals(ClaimResult.RateLimited(120), flow.claim(confirmedByUser = true))
        assertEquals(1, cleared)

        h.time.now += 60_000
        assertEquals(ClaimResult.RateLimited(60), flow.claim(confirmedByUser = true))
        assertEquals(2, h.server.requestCount) // registro + un solo reclamo
        assertEquals(1, cleared)
        assertTrue(registry.hasConflict)

        h.time.now += 61_000
        claimOk()
        assertEquals(ClaimResult.Claimed(alreadyOwned = false), flow.claim(confirmedByUser = true))
        assertEquals(3, h.server.requestCount)
    }

    @Test
    fun `429 sin Retry-After usa una espera conservadora`() = runBlocking {
        conflict()
        error(429, "rate_limited")
        assertEquals(ClaimResult.RateLimited(3600), flow.claim(confirmedByUser = true))
    }

    @Test
    fun `401 sesion expirada`() = runBlocking {
        conflict()
        h.enqueue(401, """{"message":"Unauthenticated."}""")
        assertEquals(ClaimResult.SessionExpired, flow.claim(confirmedByUser = true))
        assertTrue(registry.hasConflict)
    }

    @Test
    fun `422 no se reintenta`() = runBlocking {
        conflict()
        h.enqueue(422, """{"message":"The token field is required.","errors":{"token":["required"]}}""")
        assertEquals(ClaimResult.Failed("validation_failed", retryable = false), flow.claim(confirmedByUser = true))
    }

    @Test
    fun `sin red o sin token FCM falla de forma reintentable y conserva el conflicto`() = runBlocking {
        conflict()
        h.server.enqueue(MockResponse().setSocketPolicy(SocketPolicy.DISCONNECT_AFTER_REQUEST))
        assertEquals(ClaimResult.Failed(null, retryable = true), flow.claim(confirmedByUser = true))
        // OkHttp puede reintentar la conexión caída: inocuo, el reclamo es idempotente (already_owned).
        val before = h.server.requestCount
        fcmToken = null
        assertEquals(ClaimResult.Failed(null, retryable = true), flow.claim(confirmedByUser = true))
        assertEquals(before, h.server.requestCount) // sin token no hay petición
        assertTrue(registry.hasConflict)
        assertEquals(0, reRegistrations)
    }
}
