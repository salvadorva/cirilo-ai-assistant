package com.salvadorva.asistente.network.models

/** F6: GET /api/mobile/today. Campos opcionales porque Gson no respeta la nulabilidad de Kotlin. */
data class TodayResponse(
    val date: String? = null,
    val date_label: String? = null,
    val summary: String? = null,
    val events: List<TodayEvent>? = null,
    val tasks: List<TaskItem>? = null,
    val suggestions: List<TaskItem>? = null,
    val preferences: DailySummaryPrefs? = null,
)

/** F6-05: resumen diario opcional. channel = internal | telegram | email; days = weekdays | daily. */
data class DailySummaryPrefs(
    val enabled: Boolean = false,
    val time: String = "07:30",
    val channel: String = "internal",
    val days: String = "weekdays",
)

data class TaskListResponse(val data: List<TaskItem>? = null)

data class TodayEvent(
    val id: Int? = null,
    val title: String? = null,
    val time: String? = null,
    val all_day: Boolean? = null,
    val location: String? = null,
)

/** Pendiente: status = suggested | open | postponed | done | dismissed. */
data class TaskItem(
    val id: Int? = null,
    val title: String? = null,
    val status: String? = null,
    val due_date: String? = null,
    val postponed_until: String? = null,
    val source: String? = null,
)

data class TaskCreateRequest(val title: String, val due_date: String? = null)

/**
 * action: complete | postpone | dismiss | reopen | accept (postpone con until = AAAA-MM-DD).
 * Sin acción, cambia título o fecha. Gson omite los campos nulos.
 */
data class TaskUpdateRequest(
    val action: String? = null,
    val until: String? = null,
    val title: String? = null,
    val due_date: String? = null,
)

data class TaskResponse(val status: String? = null, val task: TaskItem? = null)
