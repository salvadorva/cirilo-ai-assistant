package com.salvadorva.asistente.network.models

/**
 * Evento de la agenda tal como lo devuelven los endpoints /api/mobile/agenda.
 * Las fechas son ISO 8601 con offset (ej. "2026-06-20T15:00:00-06:00").
 */
data class AgendaEvent(
    val id: Int,
    val title: String,
    val description: String? = null,
    val location: String? = null,
    val category: String? = null,
    val color: String? = null,
    val start_date: String? = null,
    val end_date: String? = null,
    val all_day: Boolean = false,
    val reminder_minutes_before: Int = 0,
    val status: String? = null,
    val notified: Boolean = false,
    val recurrence_type: String? = null,
    val recurrence_end_date: String? = null,
    // UUID en el backend: con Int, Gson fallaba al leer cualquier serie.
    val series_id: String? = null,
    val nextcloud_synced: Boolean = false,
    /** F3: estado de cada aviso del horario vigente (solo en GET /agenda/events/{id}). */
    val notifications: List<EventNotice>? = null,
)

/** Aviso por canal. «Aceptado por el proveedor» nunca significa recibido ni leído. */
data class EventNotice(
    val kind: String? = null,      // reminder | start
    val channel: String? = null,   // internal | email | telegram | fcm
    val status: String? = null,
    val label: String? = null,
    val attempts: Int? = null,
    val accepted_at: String? = null,
)

/** Wrapper de la respuesta de index/upcoming: { "events": [...] }. */
data class AgendaEventsResponse(
    val events: List<AgendaEvent> = emptyList(),
)

/** Payload para crear (POST) o actualizar (PUT) un evento. */
data class AgendaEventRequest(
    val title: String,
    val description: String? = null,
    val location: String? = null,
    val start_date: String,
    val end_date: String? = null,
    val all_day: Boolean = false,
    val reminder_minutes_before: Int? = null,
)

/** Respuesta de DELETE: { "ok": true }. */
data class AgendaDeleteResponse(
    val ok: Boolean = false,
)
