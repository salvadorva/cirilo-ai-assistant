package com.salvadorva.asistente.reminders

/**
 * Modelos del contrato Android v1 de recordatorios contextuales (RC3).
 * Fuente: docs/CONTRATO-ANDROID-RECORDATORIOS.md (raíz del repo) y sus fixtures.
 * Los nombres snake_case son los del JSON; no renombrar sin versionar el contrato.
 */

const val CAPABILITY_CONTEXTUAL_REMINDERS_V1 = "contextual_reminders_v1"

/** Opciones de posponer que la app sabe ofrecer; el detalle decide cuáles aplican. */
val SUPPORTED_SNOOZE_MINUTES = listOf(15, 30, 60)

// ── Registro de instalación ────────────────────────────────────────

data class DeviceRegistrationRequest(
    val token: String,
    val platform: String = "android",
    val installation_id: String,
    /** null = omitir el campo: el backend lo trata como «sin capacidades» (app degradada). */
    val capabilities: List<String>?,
    val app_version: String?,
)

data class DeviceRegistrationResponse(
    val message: String?,
    val installation_id: String?,
    val capabilities: List<String>?,
    val contextual_reminders_ready: Boolean?,
)

/** `200` del reclamo: `claimed` = transferida ahora; `already_owned` = ya era de esta cuenta (idempotente). */
data class DeviceClaimResponse(
    val message: String?,
    val claimed: Boolean?,
    val already_owned: Boolean?,
    val installation_id: String?,
    val capabilities: List<String>?,
    val contextual_reminders_ready: Boolean?,
)

data class DeviceTokenRemoveRequest(val token: String)

// ── Recordatorios ──────────────────────────────────────────────────

data class ReminderDispatch(val state: String?, val error_category: String?)

data class ReminderActionsAvailable(
    val complete: Boolean = false,
    val cancel: Boolean = false,
    val snooze_minutes: List<Int>? = null,
)

data class DeliveryReceipt(val received_at: String?, val displayed_at: String?)

/**
 * Detalle (GET /{id} y elementos del listado) y también el `current` de un 409,
 * que solo trae el resumen: los campos de contexto quedan en null.
 */
data class ReminderDetail(
    val id: String,
    val occurrence_id: String?,
    val state: String,
    val version: Int,
    val scheduled_at: String?,
    val expires_at: String?,
    val timezone: String?,
    val with_audio: Boolean? = null,
    val dispatch: ReminderDispatch? = null,
    val title: String? = null,
    val context: String? = null,
    val next_action: String? = null,
    val confirmed_at: String? = null,
    val completed_at: String? = null,
    val cancelled_at: String? = null,
    val expired_at: String? = null,
    val audio_ready: Boolean? = null,
    val actions: ReminderActionsAvailable? = null,
    val delivery_receipt: DeliveryReceipt? = null,
    val server_time: String? = null,
) {
    val isPending: Boolean get() = state == STATE_PENDING

    companion object {
        const val STATE_PENDING = "pending"
    }
}

data class ReminderListMeta(val total: Int?, val per_page: Int?, val current_page: Int?, val last_page: Int?)

data class ReminderListResponse(
    val data: List<ReminderDetail> = emptyList(),
    val meta: ReminderListMeta? = null,
    val server_time: String? = null,
)

/** Body de Hecho / Cancelar (`minutes` null) y Posponer. Gson omite los null. */
data class ReminderActionRequest(val expected_version: Int, val minutes: Int? = null)

data class ReceiptRequest(val event: String, val version: Int, val installation_id: String)

data class ReceiptResponse(val recorded: Boolean?, val event: String?, val version: Int?)

/** Error estable de la API: {code, message, request_id, current?}. */
data class ReminderApiError(
    val code: String?,
    val message: String?,
    val request_id: String? = null,
    val current: ReminderDetail? = null,
)

/**
 * Opciones de posponer que la UI puede ofrecer: solo las que expone el detalle
 * (el servidor ya excluye las que alcanzan la caducidad) y que la app conoce.
 * Con el reloj corregido se vuelve a filtrar por si el detalle quedó viejo en pantalla.
 */
fun ReminderDetail.offeredSnoozeMinutes(now: Long): List<Int> {
    if (!isPending) return emptyList()
    val expires = ReminderPushParser.parseUtc(expires_at) ?: return emptyList()
    return actions?.snooze_minutes.orEmpty()
        .filter { it in SUPPORTED_SNOOZE_MINUTES && now + it * 60_000L < expires }
        .distinct()
        .sorted()
}

enum class ReminderAction(val path: String) { COMPLETE("complete"), CANCEL("cancel"), SNOOZE("snooze") }

enum class ReceiptEvent(val wire: String) { RECEIVED("received"), DISPLAYED("displayed") }
