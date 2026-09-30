package com.salvadorva.asistente.reminders

import com.google.gson.Gson
import com.google.gson.JsonObject
import okhttp3.OkHttpClient
import okhttp3.mockwebserver.MockResponse
import okhttp3.mockwebserver.MockWebServer
import retrofit2.Retrofit
import retrofit2.converter.gson.GsonConverterFactory
import java.util.concurrent.TimeUnit

/** Utilidades compartidas: fixtures del contrato, reloj controlable y dobles de prueba. */
object Fixtures {
    const val REMINDER_ID = "7d1e2c9a-3b4f-4a5e-9c61-2f8d1e0a9b7c"
    const val INSTALLATION_ID = "0f8c2b1e-7a4d-4a5e-9b61-3c2d1e0f9a8b"

    /** Reloj de referencia de las fixtures: 2026-09-21T14:00:00Z. */
    val T0: Long = ReminderPushParser.parseUtc("2026-09-21T14:00:00Z")!!
    val SCHEDULED: Long = ReminderPushParser.parseUtc("2026-09-21T14:30:00Z")!!
    val EXPIRES: Long = ReminderPushParser.parseUtc("2026-09-21T16:00:00Z")!!

    fun raw(name: String): String =
        requireNotNull(javaClass.classLoader!!.getResource("contextual-reminders/$name")) { name }.readText()

    fun json(name: String): JsonObject = Gson().fromJson(raw(name), JsonObject::class.java)

    /** Fixture con los `<uuid>` sustituidos por un ID real. */
    fun withIds(name: String, id: String = REMINDER_ID): String = raw(name).replace("<uuid>", id)

    fun push(version: Int = 1, id: String = REMINDER_ID, expiresAt: String = "2026-09-21T16:00:00Z"): Map<String, String> {
        @Suppress("UNCHECKED_CAST")
        val base = Gson().fromJson(withIds("push-contextual-reminder-v1.json", id), Map::class.java) as Map<String, String>
        return base + mapOf("version" to version.toString(), "expires_at" to expiresAt)
    }

    /** Detalle/resumen del servidor con estado y versión dados. */
    fun detailJson(
        state: String = "pending",
        version: Int = 1,
        scheduledAt: String = "2026-09-21T14:30:00Z",
        snooze: List<Int> = listOf(15, 30, 60),
        id: String = REMINDER_ID,
    ): String {
        val o = Gson().fromJson(withIds("reminder-detail-response.json", id), JsonObject::class.java)
        o.addProperty("state", state)
        o.addProperty("version", version)
        o.addProperty("scheduled_at", scheduledAt)
        val actions = o.getAsJsonObject("actions")
        actions.addProperty("complete", state == "pending")
        actions.addProperty("cancel", state == "pending")
        actions.add("snooze_minutes", Gson().toJsonTree(if (state == "pending") snooze else emptyList()))
        return o.toString()
    }

    fun conflictJson(code: String, state: String = "pending", version: Int = 2, scheduledAt: String = "2026-09-21T14:30:00Z"): String {
        val o = Gson().fromJson(withIds("conflict-response.json"), JsonObject::class.java)
        o.addProperty("code", code)
        val current = o.getAsJsonObject("current")
        current.addProperty("state", state)
        current.addProperty("version", version)
        current.addProperty("scheduled_at", scheduledAt)
        return o.toString()
    }
}

class MutableTime(var now: Long = Fixtures.SCHEDULED + 60_000)

class FakeNotifications(var enabled: Boolean = true) : ReminderNotifications {
    data class Shown(val occurrenceId: String, val version: Int, val alert: Boolean)

    val shown = mutableListOf<Shown>()
    val pendingSync = mutableListOf<Pair<String, String>>()
    val cancelled = mutableListOf<String>()

    override fun canDisplay() = enabled
    override fun show(entry: ReminderLedger.Entry, alert: Boolean) {
        shown += Shown(entry.occurrenceId, entry.version, alert)
    }
    override fun showPendingSync(entry: ReminderLedger.Entry, message: String) {
        pendingSync += entry.occurrenceId to message
    }
    override fun cancel(occurrenceId: String) {
        cancelled += occurrenceId
    }

    /** Cuántas veces sonó (alert = true). */
    val alerts get() = shown.count { it.alert }
}

/** Arma el motor contra un MockWebServer con almacenamiento persistente simulado. */
class Harness(
    val server: MockWebServer = MockWebServer().apply { start() },
    val store: KeyValueStore = InMemoryStore(),
    val time: MutableTime = MutableTime(),
    val notifications: FakeNotifications = FakeNotifications(),
    readTimeoutMs: Long = 2_000,
) {
    var scheduled = 0

    val api: ContextualReminderApi = Retrofit.Builder()
        .baseUrl(server.url("/"))
        .client(OkHttpClient.Builder().readTimeout(readTimeoutMs, TimeUnit.MILLISECONDS).build())
        .addConverterFactory(GsonConverterFactory.create())
        .build()
        .create(ContextualReminderApi::class.java)

    val clock = ReminderClock(store) { time.now }
    val ledger = ReminderLedger(store)
    private var keyCounter = 0
    val outbox = ReminderOutbox(store) { "test-idempotency-key-${++keyCounter}-0000" }
    val registry = InstallationRegistry(store, api) { Fixtures.INSTALLATION_ID }

    /** Un motor nuevo sobre el mismo almacenamiento = app cerrada y vuelta a abrir, o teléfono reiniciado. */
    fun engine() = ReminderEngine(api, ReminderLedger(store), ReminderOutbox(store) { "test-idempotency-key-${++keyCounter}-0000" },
        notifications, clock, { scheduled++ }, { registry.existingInstallationId() })

    fun enqueue(code: Int, body: String = "{}", headers: Map<String, String> = emptyMap()) {
        server.enqueue(MockResponse().setResponseCode(code).setBody(body).apply { headers.forEach { (k, v) -> addHeader(k, v) } })
    }

    fun shutdown() = server.shutdown()
}
