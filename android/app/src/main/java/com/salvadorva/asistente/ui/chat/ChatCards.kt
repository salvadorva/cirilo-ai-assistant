package com.salvadorva.asistente.ui.chat

import com.salvadorva.asistente.network.models.ChatResponse

/**
 * F2/F6: tarjetas que muestran lo que realmente quedó guardado en este turno (datos del servidor,
 * no del texto del modelo). Función pura para poder probarla en la JVM.
 */
object ChatCards {
    private val AGENDA_LABELS = mapOf(
        "created" to "✓ evento agendado",
        "replayed" to "✓ evento agendado",
        "duplicate" to "ya estaba en tu agenda",
        "updated" to "✓ evento actualizado",
        "cancelled" to "evento cancelado",
    )
    private val TASK_LABELS = mapOf(
        "created" to "✓ pendiente guardado",
        "duplicate" to "ya estaba en tus pendientes",
        "updated" to "✓ pendiente actualizado",
    )

    fun from(body: ChatResponse): List<ChatItem> = buildList {
        val agenda = body.agenda
        val label = agenda?.status?.let { AGENDA_LABELS[it] }
        val event = agenda?.events?.firstOrNull()
        when {
            label != null && event?.title != null ->
                add(ChatItem.EventCreated(event.title, event.start, event.all_day == true, label, agenda.count ?: 1))
            agenda?.status == "needs_clarification" && !agenda.candidates.isNullOrEmpty() ->
                add(ChatItem.AgendaCandidates(agenda.candidates.mapNotNull { c ->
                    c.title?.let { ChatItem.Candidate(it, c.start, c.all_day == true) }
                }))
            // Servidores sin contrato `agenda`: solo event_created.
            agenda == null && body.event_created?.title != null -> body.event_created.let { ev ->
                add(ChatItem.EventCreated(ev.title!!, ev.start_date, ev.all_day == true))
            }
        }

        val tasks = body.tasks
        val taskLabel = tasks?.status?.let { TASK_LABELS[it] }
        if (taskLabel != null) {
            tasks.items.orEmpty().forEach { task ->
                task.title?.let { add(ChatItem.TaskSaved(it, task.due_date, taskLabel)) }
            }
        }
    }
}
