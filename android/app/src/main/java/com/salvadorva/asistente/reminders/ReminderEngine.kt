package com.salvadorva.asistente.reminders

import com.google.gson.Gson
import retrofit2.Response
import java.io.IOException

/** Lo que el motor necesita de la bandeja de notificaciones (implementación Android en ReminderNotifier). */
interface ReminderNotifications {
    /** Permiso POST_NOTIFICATIONS concedido, notificaciones de la app y canal propio habilitados. */
    fun canDisplay(): Boolean
    /** `alert = false` reemplaza el aviso sin volver a sonar. */
    fun show(entry: ReminderLedger.Entry, alert: Boolean)
    fun showPendingSync(entry: ReminderLedger.Entry, message: String)
    fun cancel(occurrenceId: String)
}

/** Programa el trabajo en segundo plano (WorkManager en producción). */
fun interface SyncScheduler {
    fun schedule()
}

/**
 * Reglas del contrato Android v1, sin dependencias de Android para poder probarlas en JVM.
 *
 *  - [onPush]: validar, deduplicar y mostrar el aviso genérico. Breve: la red va a [sync].
 *  - [requestAction]: Hecho / Cancelar desde la notificación → cola persistente.
 *  - [sync]: envía acciones y recibos pendientes; nunca da por confirmado lo que el servidor no confirmó.
 *  - [reconcile]: al abrir la app, retira avisos cerrados o caducados.
 */
class ReminderEngine(
    private val api: ContextualReminderApi,
    private val ledger: ReminderLedger,
    private val outbox: ReminderOutbox,
    private val notifications: ReminderNotifications,
    private val clock: ReminderClock,
    private val scheduler: SyncScheduler,
    private val installationId: () -> String?,
    private val gson: Gson = Gson(),
) {

    // ── Recepción (sección 3) ──────────────────────────────────────

    sealed class PushOutcome {
        object Ignored : PushOutcome()
        data class Shown(val entry: ReminderLedger.Entry, val alerted: Boolean) : PushOutcome()
        /** Aceptado pero sin mostrar: permiso denegado o canal silenciado. */
        data class NotDisplayable(val entry: ReminderLedger.Entry) : PushOutcome()
        object Duplicate : PushOutcome()
        object Stale : PushOutcome()
        object Expired : PushOutcome()
    }

    fun onPush(data: Map<String, String>): PushOutcome {
        val push = (ReminderPushParser.parse(data) as? PushParseResult.Valid)?.push ?: return PushOutcome.Ignored
        return when (val decision = ledger.admit(push, clock.now())) {
            ReminderLedger.Decision.Duplicate -> PushOutcome.Duplicate
            ReminderLedger.Decision.Stale -> PushOutcome.Stale
            ReminderLedger.Decision.Expired -> PushOutcome.Expired
            is ReminderLedger.Decision.Show -> {
                val entry = decision.entry
                outbox.enqueueReceipt(entry.reminderId, entry.version, ReceiptEvent.RECEIVED)
                val outcome = if (notifications.canDisplay()) {
                    notifications.show(entry, alert = !decision.replaces)
                    outbox.enqueueReceipt(entry.reminderId, entry.version, ReceiptEvent.DISPLAYED)
                    PushOutcome.Shown(entry, alerted = !decision.replaces)
                } else {
                    PushOutcome.NotDisplayable(entry)
                }
                scheduler.schedule()
                outcome
            }
        }
    }

    // ── Acciones desde la notificación (sección 4) ─────────────────

    /**
     * Hecho o Cancelar. Se persiste antes de tocar la red y el aviso pasa a
     * «pendiente de sincronizar», nunca a «hecho».
     */
    fun requestAction(reminderId: String, version: Int, action: ReminderAction) {
        require(action != ReminderAction.SNOOZE) { "Posponer exige conexión y se elige en la app" }
        outbox.enqueueAction(reminderId, version, action)
        ledger.findByReminderId(reminderId)?.let { entry ->
            ledger.setStatus(entry.occurrenceId, ReminderLedger.Status.PENDING_SYNC)?.let {
                notifications.showPendingSync(it, pendingLabel(action))
            }
        }
        scheduler.schedule()
    }

    /**
     * Acción desde el detalle abierto en la app. Se persiste la intención con su
     * clave y se intenta en el momento, repitiendo la misma petición si falla la red.
     *  - Hecho/Cancelar sin confirmar quedan en cola («pendiente de sincronizar»).
     *  - Posponer exige conexión: si no se confirma, sale de la cola y el resultado
     *    es incierto; la UI debe volver a consultar el detalle, nunca darlo por hecho.
     */
    suspend fun performInApp(reminderId: String, version: Int, action: ReminderAction, minutes: Int? = null): ActionOutcome {
        val pending = outbox.enqueueAction(reminderId, version, action, minutes)
        var outcome: ActionOutcome = ActionOutcome.RetryLater
        repeat(IN_APP_ATTEMPTS) {
            outcome = executeAction(pending)
            if (outcome != ActionOutcome.RetryLater) return outcome
        }
        if (action == ReminderAction.SNOOZE) {
            outbox.removeAction(pending)
        } else {
            ledger.findByReminderId(reminderId)?.let { entry ->
                ledger.setStatus(entry.occurrenceId, ReminderLedger.Status.PENDING_SYNC)?.let {
                    if (it.isDue(clock.now())) notifications.showPendingSync(it, pendingLabel(action))
                }
            }
            scheduler.schedule()
        }
        return outcome
    }

    // ── Sincronización ──────────────────────────────────────────────

    enum class SyncResult { DONE, RETRY, SESSION_EXPIRED }

    sealed class ActionOutcome {
        /** El servidor confirmó. `detail` = estado que devolvió. */
        data class Confirmed(val detail: ReminderDetail?, val replayed: Boolean) : ActionOutcome()
        /** 409 con `current`: el aviso se refrescó con ese estado. */
        data class Conflict(val code: String, val current: ReminderDetail) : ActionOutcome()
        /** Sin confirmar: se reintenta con la misma clave. */
        object RetryLater : ActionOutcome()
        object SessionExpired : ActionOutcome()
        /** 404/422: no tiene sentido reintentar. */
        data class Rejected(val code: String?) : ActionOutcome()
    }

    suspend fun sync(): SyncResult {
        var retry = false
        for (action in outbox.pendingActions()) {
            when (executeAction(action)) {
                ActionOutcome.SessionExpired -> return SyncResult.SESSION_EXPIRED.also { markSessionExpired() }
                ActionOutcome.RetryLater -> retry = true
                else -> Unit
            }
        }
        for (receipt in outbox.pendingReceipts()) {
            when (sendReceipt(receipt)) {
                ReceiptOutcome.SESSION_EXPIRED -> return SyncResult.SESSION_EXPIRED
                ReceiptOutcome.RETRY -> retry = true
                ReceiptOutcome.DONE -> Unit
            }
        }
        return if (retry) SyncResult.RETRY else SyncResult.DONE
    }

    /** Ejecuta una acción ya persistida en la cola. Solo sale de la cola con respuesta definitiva. */
    suspend fun executeAction(action: ReminderOutbox.PendingAction): ActionOutcome {
        val response = try {
            api.act(action.reminderId, action.action.path, action.idempotencyKey, action.request())
        } catch (_: IOException) {
            return ActionOutcome.RetryLater // timeout o sin red: misma petición, misma clave
        }
        learnServerTime(response)
        val code = response.code()
        return when {
            response.isSuccessful -> {
                outbox.removeAction(action)
                val detail = response.body()
                val entry = detail?.let { ledger.applyServerState(it, clock.now()) }
                    ?: ledger.findByReminderId(action.reminderId)?.let {
                        ledger.setStatus(it.occurrenceId, closedOrWaiting(action.action))
                    }
                entry?.let { notifications.cancel(it.occurrenceId) }
                ActionOutcome.Confirmed(detail, response.headers()["Idempotent-Replayed"] == "true")
            }
            code == 401 -> ActionOutcome.SessionExpired
            code == 409 -> {
                val error = parseError(response)
                val current = error?.current ?: return ActionOutcome.RetryLater // idempotency_conflict: en curso
                outbox.removeAction(action)
                refreshFromServer(current)
                ActionOutcome.Conflict(error.code ?: "conflict", current)
            }
            code == 404 -> {
                outbox.removeAction(action)
                ledger.findByReminderId(action.reminderId)?.let {
                    ledger.setStatus(it.occurrenceId, ReminderLedger.Status.CLOSED)
                    notifications.cancel(it.occurrenceId)
                }
                ActionOutcome.Rejected(parseError(response)?.code ?: "not_found")
            }
            code == 422 -> {
                outbox.removeAction(action)
                restoreVisible(action.reminderId)
                ActionOutcome.Rejected(parseError(response)?.code ?: "validation_failed")
            }
            else -> ActionOutcome.RetryLater // 429, 5xx
        }
    }

    // ── Recibos (sección 5) ─────────────────────────────────────────

    enum class ReceiptOutcome { DONE, RETRY, SESSION_EXPIRED }

    /** Solo diagnóstico: llama únicamente a /receipts, nunca completa la tarea. */
    suspend fun sendReceipt(receipt: ReminderOutbox.PendingReceipt): ReceiptOutcome {
        val installation = installationId() ?: return ReceiptOutcome.RETRY
        val response = try {
            api.receipt(receipt.reminderId, ReceiptRequest(receipt.event.wire, receipt.version, installation))
        } catch (_: IOException) {
            return ReceiptOutcome.RETRY
        }
        learnServerTime(response)
        return when {
            // `recorded: false` también es respuesta definitiva (no hubo despacho a esta instalación).
            response.isSuccessful -> ReceiptOutcome.DONE.also { outbox.markReceiptDone(receipt) }
            response.code() == 401 -> ReceiptOutcome.SESSION_EXPIRED
            response.code() in listOf(404, 409, 422) -> ReceiptOutcome.DONE.also { outbox.markReceiptDone(receipt) }
            else -> ReceiptOutcome.RETRY
        }
    }

    // ── Reconciliación (sección 3.6) ────────────────────────────────

    /**
     * Retira avisos cerrados o caducados. Devuelve false si no se pudo consultar
     * (sin red o sesión inválida); en ese caso solo retira los caducados localmente.
     */
    suspend fun reconcile(): Boolean {
        val now = clock.now()
        ledger.visible(Long.MIN_VALUE).filter { now >= it.expiresAtMillis }.forEach { expireLocally(it) }
        val tracked = ledger.all().filter { it.status != ReminderLedger.Status.CLOSED }
        if (tracked.isEmpty()) return true

        val response = try {
            api.list()
        } catch (_: IOException) {
            return false
        }
        learnServerTime(response)
        val pending = response.body()?.takeIf { response.isSuccessful }?.data ?: return false
        val byId = pending.associateBy { it.occurrence_id ?: it.id }
        for (entry in tracked) {
            val server = byId[entry.occurrenceId] ?: byId[entry.reminderId]
            if (server == null) {
                // Ya no está pendiente: cerrado o caducado en el servidor.
                ledger.setStatus(entry.occurrenceId, ReminderLedger.Status.CLOSED)
                notifications.cancel(entry.occurrenceId)
            } else if (!outbox.hasPendingAction(entry.reminderId)) {
                refreshFromServer(server)
            }
        }
        return true
    }

    /** Retira un aviso que alcanzó su caducidad (también lo hace el sistema con setTimeoutAfter). */
    fun expireLocally(entry: ReminderLedger.Entry) {
        ledger.setStatus(entry.occurrenceId, ReminderLedger.Status.CLOSED)
        notifications.cancel(entry.occurrenceId)
    }

    /** Tras reiniciar el teléfono la bandeja se vacía: reponer los avisos vigentes sin sonar. */
    fun restoreAfterReboot() {
        if (!notifications.canDisplay()) return
        val now = clock.now()
        for (entry in ledger.visible(now)) {
            if (entry.status == ReminderLedger.Status.PENDING_SYNC) {
                val action = outbox.pendingActions().firstOrNull { it.reminderId == entry.reminderId }
                notifications.showPendingSync(entry, pendingLabel(action?.action ?: ReminderAction.COMPLETE))
            } else {
                notifications.show(entry, alert = false)
            }
        }
    }

    /** Refresca el aviso con el estado del servidor (409 `current`, detalle o listado). */
    fun refreshFromServer(detail: ReminderDetail) {
        val entry = ledger.applyServerState(detail, clock.now()) ?: return
        when (entry.status) {
            ReminderLedger.Status.SHOWN -> if (notifications.canDisplay()) notifications.show(entry, alert = false)
            ReminderLedger.Status.PENDING_SYNC -> Unit
            ReminderLedger.Status.WAITING, ReminderLedger.Status.CLOSED -> notifications.cancel(entry.occurrenceId)
        }
    }

    private fun restoreVisible(reminderId: String) {
        val entry = ledger.findByReminderId(reminderId) ?: return
        if (entry.status == ReminderLedger.Status.PENDING_SYNC && !outbox.hasPendingAction(reminderId)) {
            ledger.setStatus(entry.occurrenceId, ReminderLedger.Status.SHOWN)?.let {
                if (it.isDue(clock.now()) && notifications.canDisplay()) notifications.show(it, alert = false)
            }
        }
    }

    private fun markSessionExpired() {
        val waiting = outbox.pendingActions().map { it.reminderId }.toSet()
        ledger.visible(clock.now()).filter { it.reminderId in waiting }.forEach {
            notifications.showPendingSync(it, "Inicia sesión en Cirilo para sincronizar")
        }
    }

    private fun closedOrWaiting(action: ReminderAction) =
        if (action == ReminderAction.SNOOZE) ReminderLedger.Status.WAITING else ReminderLedger.Status.CLOSED

    private fun parseError(response: Response<*>): ReminderApiError? =
        runCatching { gson.fromJson(response.errorBody()?.charStream(), ReminderApiError::class.java) }.getOrNull()

    private fun learnServerTime(response: Response<*>) {
        response.headers().getDate("Date")?.let { clock.learnServerTime(it.time) }
    }

    companion object {
        const val IN_APP_ATTEMPTS = 2

        fun pendingLabel(action: ReminderAction) = when (action) {
            ReminderAction.COMPLETE -> "Hecho · pendiente de sincronizar"
            ReminderAction.CANCEL -> "Cancelar · pendiente de sincronizar"
            ReminderAction.SNOOZE -> "Posponer · pendiente de confirmar"
        }
    }
}
