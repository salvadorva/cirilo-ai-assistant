package com.salvadorva.asistente.network.models

/** F6: GET /api/mobile/today. Campos opcionales porque Gson no respeta la nulabilidad de Kotlin. */
data class TodayResponse(
    val date: String? = null,
    val date_label: String? = null,
    val summary: String? = null,
    val events: List<TodayEvent>? = null,
    val tasks: List<TaskItem>? = null,
    val suggestions: List<TaskItem>? = null,
)

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

/** action: complete | postpone | dismiss | reopen | accept (postpone con until = AAAA-MM-DD). */
data class TaskUpdateRequest(val action: String, val until: String? = null)

data class TaskResponse(val status: String? = null, val task: TaskItem? = null)
