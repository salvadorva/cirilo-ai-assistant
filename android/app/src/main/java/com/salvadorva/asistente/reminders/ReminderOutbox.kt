package com.salvadorva.asistente.reminders

import com.google.gson.Gson
import java.util.UUID

/**
 * Cola persistente de peticiones al backend (secciones 4 y 5 del contrato).
 *
 * Acciones: la `Idempotency-Key` se genera UNA vez por intención del usuario y se
 * persiste con la petición; un timeout repite la misma petición con la misma clave.
 * Un doble toque sobre la misma (id, versión, acción) reutiliza la entrada existente.
 *
 * Recibos: uno por (id, versión, evento); se recuerdan los ya enviados.
 */
class ReminderOutbox(
    private val store: KeyValueStore,
    private val gson: Gson = Gson(),
    private val newKey: () -> String = { UUID.randomUUID().toString() },
) {

    data class PendingAction(
        val reminderId: String,
        val version: Int,
        val action: ReminderAction,
        val minutes: Int?,
        val idempotencyKey: String,
    ) {
        fun request() = ReminderActionRequest(expected_version = version, minutes = minutes)
    }

    data class PendingReceipt(val reminderId: String, val version: Int, val event: ReceiptEvent)

    /** Devuelve la acción en cola; si ya existía para esa intención, la misma (misma clave). */
    @Synchronized
    fun enqueueAction(reminderId: String, version: Int, action: ReminderAction, minutes: Int? = null): PendingAction {
        require(action != ReminderAction.SNOOZE || minutes in SUPPORTED_SNOOZE_MINUTES) { "minutes" }
        val k = actionKey(reminderId, version, action, minutes)
        store.get(k)?.let { json -> runCatching { gson.fromJson(json, PendingAction::class.java) }.getOrNull()?.let { return it } }
        val pending = PendingAction(reminderId, version, action, minutes, newKey())
        store.put(k, gson.toJson(pending))
        return pending
    }

    fun pendingActions(): List<PendingAction> = store.keys(ACTION_PREFIX).mapNotNull { k ->
        store.get(k)?.let { runCatching { gson.fromJson(it, PendingAction::class.java) }.getOrNull() }
    }

    fun hasPendingAction(reminderId: String): Boolean = pendingActions().any { it.reminderId == reminderId }

    @Synchronized
    fun removeAction(action: PendingAction) =
        store.remove(actionKey(action.reminderId, action.version, action.action, action.minutes))

    /** false si ese recibo ya se envió o ya está en cola. */
    @Synchronized
    fun enqueueReceipt(reminderId: String, version: Int, event: ReceiptEvent): Boolean {
        val k = receiptKey(reminderId, version, event)
        if (store.get(k) != null) return false
        store.put(k, gson.toJson(PendingReceipt(reminderId, version, event)) + RECEIPT_QUEUED)
        return true
    }

    fun pendingReceipts(): List<PendingReceipt> = store.keys(RECEIPT_PREFIX).mapNotNull { k ->
        store.get(k)?.takeIf { it.endsWith(RECEIPT_QUEUED) }?.removeSuffix(RECEIPT_QUEUED)
            ?.let { runCatching { gson.fromJson(it, PendingReceipt::class.java) }.getOrNull() }
    }

    /** Enviado (o descartado sin reintento): se recuerda para no volver a mandarlo. */
    @Synchronized
    fun markReceiptDone(receipt: PendingReceipt) =
        store.put(receiptKey(receipt.reminderId, receipt.version, receipt.event), RECEIPT_DONE)

    /** Al cerrar sesión: las peticiones eran de la cuenta anterior. */
    @Synchronized
    fun clear() {
        (store.keys(ACTION_PREFIX) + store.keys(RECEIPT_PREFIX)).forEach(store::remove)
    }

    private fun actionKey(id: String, version: Int, action: ReminderAction, minutes: Int?) =
        "$ACTION_PREFIX$id.$version.${action.path}.${minutes ?: 0}"

    private fun receiptKey(id: String, version: Int, event: ReceiptEvent) =
        "$RECEIPT_PREFIX$id.$version.${event.wire}"

    companion object {
        private const val ACTION_PREFIX = "action."
        private const val RECEIPT_PREFIX = "receipt."
        private const val RECEIPT_QUEUED = "#queued"
        private const val RECEIPT_DONE = "done"
    }
}
