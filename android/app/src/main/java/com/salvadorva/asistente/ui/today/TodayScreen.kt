package com.salvadorva.asistente.ui.today

import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Add
import androidx.compose.material.icons.filled.Event
import androidx.compose.material.icons.filled.Refresh
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.font.FontStyle
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.compose.ui.window.Dialog
import androidx.compose.ui.window.DialogProperties
import androidx.lifecycle.viewmodel.compose.viewModel
import com.salvadorva.asistente.network.models.DailySummaryPrefs
import com.salvadorva.asistente.network.models.TaskItem
import java.text.SimpleDateFormat
import java.util.Date
import java.util.Locale
import java.util.TimeZone

/** F6: lo del día primero — compromisos, pendientes y sugerencias sin confirmar. */
@Composable
fun TodayScreen(
    onOpenChat: () -> Unit,
    onOpenEvent: (Int) -> Unit = {},
    vm: TodayViewModel = viewModel(),
) {
    val state by vm.state.collectAsState()
    val today = state.today
    var editing by remember { mutableStateOf<TaskItem?>(null) }
    var pickNewDue by remember { mutableStateOf(false) }

    editing?.let { task ->
        TaskEditDialog(task, onDismiss = { editing = null }) { title, due ->
            task.id?.let { vm.edit(it, title, due) }
            editing = null
        }
    }
    if (pickNewDue) {
        DatePick(initial = state.newTaskDue, onDismiss = { pickNewDue = false }) { vm.onNewTaskDue(it); pickNewDue = false }
    }
    state.allTasks?.let { list ->
        AllTasksDialog(
            tasks = list, filter = state.allTasksFilter, busyId = state.busyTaskId,
            onFilter = vm::openAllTasks, onDismiss = vm::closeAllTasks,
            onComplete = vm::complete, onReopen = vm::reopen, onEdit = { editing = it },
        )
    }

    LazyColumn(
        modifier = Modifier.fillMaxSize(),
        contentPadding = PaddingValues(16.dp),
        verticalArrangement = Arrangement.spacedBy(12.dp),
    ) {
        item {
            Row(verticalAlignment = Alignment.CenterVertically) {
                Column(Modifier.weight(1f)) {
                    Text(today?.date_label?.replaceFirstChar { it.uppercase() } ?: "Hoy", fontWeight = FontWeight.Bold, fontSize = 20.sp)
                    Text(today?.summary ?: if (state.loading) "Cargando…" else "", color = MaterialTheme.colorScheme.onSurfaceVariant, fontSize = 13.sp)
                }
                IconButton(onClick = vm::load, enabled = !state.loading) { Icon(Icons.Default.Refresh, "Actualizar") }
            }
            state.error?.let { Text(it, color = MaterialTheme.colorScheme.error, fontSize = 13.sp) }
            state.message?.let {
                Text(it, color = MaterialTheme.colorScheme.primary, fontSize = 13.sp, modifier = Modifier.clickable { vm.clearMessage() })
            }
        }

        item { SectionTitle("Compromisos de hoy") }
        val events = today?.events.orEmpty()
        if (events.isEmpty()) {
            item { EmptyLine("No tienes compromisos agendados para hoy.") }
        } else {
            items(events) { ev ->
                Card(Modifier.fillMaxWidth().clickable(enabled = ev.id != null) { ev.id?.let(onOpenEvent) }) {
                    Column(Modifier.padding(12.dp)) {
                        Text(ev.title ?: "", fontWeight = FontWeight.SemiBold)
                        Text(
                            (if (ev.all_day == true) "Todo el día" else ev.time ?: "") + (ev.location?.let { " · $it" } ?: ""),
                            color = MaterialTheme.colorScheme.onSurfaceVariant, fontSize = 13.sp,
                        )
                    }
                }
            }
        }

        item {
            Row(verticalAlignment = Alignment.CenterVertically) {
                SectionTitle("Pendientes", Modifier.weight(1f))
                TextButton(onClick = { vm.openAllTasks("open") }) { Text("Ver todos") }
            }
        }
        val tasks = today?.tasks.orEmpty()
        if (tasks.isEmpty()) item { EmptyLine("No tienes pendientes para hoy.") }
        items(tasks) { task ->
            TaskCard(task, today?.date, state.busyTaskId == task.id, onClick = { editing = task }) {
                val id = task.id ?: return@TaskCard
                TextButton(onClick = { vm.complete(id) }) { Text("Hecho") }
                TextButton(onClick = { vm.postponeToTomorrow(id) }) { Text("Mañana") }
                TextButton(onClick = { vm.dismiss(id) }) { Text("Descartar") }
            }
        }
        item {
            Column {
                Row(verticalAlignment = Alignment.CenterVertically) {
                    OutlinedTextField(
                        value = state.newTask,
                        onValueChange = vm::onNewTaskChange,
                        modifier = Modifier.weight(1f),
                        singleLine = true,
                        placeholder = { Text("Nuevo pendiente") },
                    )
                    IconButton(onClick = { pickNewDue = true }) { Icon(Icons.Default.Event, "Fecha límite (opcional)") }
                    IconButton(onClick = vm::addTask, enabled = state.newTask.isNotBlank()) { Icon(Icons.Default.Add, "Agregar pendiente") }
                }
                state.newTaskDue?.let {
                    AssistChip(onClick = { vm.onNewTaskDue(null) }, label = { Text("Fecha límite $it  ✕") })
                }
            }
        }

        val suggestions = today?.suggestions.orEmpty()
        if (suggestions.isNotEmpty()) {
            item {
                SectionTitle("Sugerencias de tus conversaciones")
                Text("No son compromisos hasta que los aceptes.", color = MaterialTheme.colorScheme.onSurfaceVariant, fontSize = 12.sp)
            }
            items(suggestions) { task ->
                TaskCard(task, today?.date, state.busyTaskId == task.id, suggestion = true) {
                    val id = task.id ?: return@TaskCard
                    TextButton(onClick = { vm.accept(id) }) { Text("Aceptar") }
                    TextButton(onClick = { vm.dismiss(id) }) { Text("Descartar") }
                }
            }
        }

        today?.preferences?.let { prefs ->
            item { DailySummaryCard(prefs, state.savingPrefs, vm::savePreferences) }
        }

        item {
            OutlinedButton(onClick = onOpenChat, modifier = Modifier.fillMaxWidth()) { Text("Hablar o escribir a Cirilo") }
        }
    }
}

@Composable
private fun SectionTitle(text: String, modifier: Modifier = Modifier) {
    Text(text, fontWeight = FontWeight.Bold, fontSize = 15.sp, modifier = modifier.padding(top = 4.dp))
}

@Composable
private fun EmptyLine(text: String) {
    Text(text, color = MaterialTheme.colorScheme.onSurfaceVariant, fontSize = 13.sp)
}

private fun taskLabel(task: TaskItem, today: String?, suggestion: Boolean): String {
    val due = task.due_date
    return when {
        suggestion -> "Sugerencia"
        task.status == "done" -> "Hecho"
        task.status == "dismissed" -> "Descartado"
        task.status == "postponed" -> "Pospuesto" + (task.postponed_until?.let { " al $it" } ?: "")
        due == null -> "Sin fecha"
        today != null && due < today -> "Vencido ($due)"
        today != null && due == today -> "Vence hoy"
        else -> "Fecha límite $due"
    }
}

@Composable
private fun TaskCard(
    task: TaskItem,
    today: String?,
    busy: Boolean,
    suggestion: Boolean = false,
    onClick: (() -> Unit)? = null,
    actions: @Composable RowScope.() -> Unit,
) {
    Card(Modifier.fillMaxWidth().then(if (onClick != null) Modifier.clickable(onClick = onClick) else Modifier)) {
        Column(Modifier.padding(horizontal = 12.dp, vertical = 8.dp)) {
            Text(task.title ?: "", fontStyle = if (suggestion) FontStyle.Italic else FontStyle.Normal)
            Text(taskLabel(task, today, suggestion), color = MaterialTheme.colorScheme.onSurfaceVariant, fontSize = 12.sp)
            if (busy) LinearProgressIndicator(Modifier.fillMaxWidth().padding(top = 4.dp)) else Row(content = actions)
        }
    }
}

/** Editar título y fecha límite de un pendiente. */
@Composable
private fun TaskEditDialog(task: TaskItem, onDismiss: () -> Unit, onSave: (String, String?) -> Unit) {
    var title by remember { mutableStateOf(task.title.orEmpty()) }
    var due by remember { mutableStateOf(task.due_date) }
    var pick by remember { mutableStateOf(false) }
    if (pick) DatePick(initial = due, onDismiss = { pick = false }) { due = it; pick = false }
    AlertDialog(
        onDismissRequest = onDismiss,
        title = { Text("Editar pendiente") },
        text = {
            Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
                OutlinedTextField(value = title, onValueChange = { title = it }, singleLine = true, label = { Text("Título") })
                OutlinedButton(onClick = { pick = true }) { Text(due?.let { "Fecha límite: $it" } ?: "Agregar fecha límite") }
            }
        },
        confirmButton = { TextButton(onClick = { onSave(title, due) }, enabled = title.isNotBlank()) { Text("Guardar") } },
        dismissButton = { TextButton(onClick = onDismiss) { Text("Cancelar") } },
    )
}

/** Lista completa con filtros; permite reabrir lo hecho o descartado. */
@Composable
private fun AllTasksDialog(
    tasks: List<TaskItem>,
    filter: String,
    busyId: Int?,
    onFilter: (String) -> Unit,
    onDismiss: () -> Unit,
    onComplete: (Int) -> Unit,
    onReopen: (Int) -> Unit,
    onEdit: (TaskItem) -> Unit,
) {
    Dialog(onDismissRequest = onDismiss, properties = DialogProperties(usePlatformDefaultWidth = false)) {
        Surface(Modifier.fillMaxSize(), color = MaterialTheme.colorScheme.background) {
            Column(Modifier.padding(16.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                Row(verticalAlignment = Alignment.CenterVertically) {
                    Text("Todos los pendientes", fontWeight = FontWeight.Bold, fontSize = 18.sp, modifier = Modifier.weight(1f))
                    TextButton(onClick = onDismiss) { Text("Cerrar") }
                }
                Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                    listOf("open" to "Abiertos", "done" to "Hechos", "all" to "Todos").forEach { (key, label) ->
                        FilterChip(selected = filter == key, onClick = { onFilter(key) }, label = { Text(label) })
                    }
                }
                if (tasks.isEmpty()) EmptyLine("No hay pendientes en esta vista.")
                LazyColumn(verticalArrangement = Arrangement.spacedBy(8.dp)) {
                    items(tasks) { task ->
                        TaskCard(task, null, busyId == task.id, suggestion = task.status == "suggested", onClick = { onEdit(task) }) {
                            val id = task.id ?: return@TaskCard
                            if (task.status == "done" || task.status == "dismissed") {
                                TextButton(onClick = { onReopen(id) }) { Text("Reabrir") }
                            } else if (task.status != "suggested") {
                                TextButton(onClick = { onComplete(id) }) { Text("Hecho") }
                            }
                        }
                    }
                }
            }
        }
    }
}

/** F6-05: resumen diario opcional con hora, canal y días. */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun DailySummaryCard(prefs: DailySummaryPrefs, saving: Boolean, onSave: (DailySummaryPrefs) -> Unit) {
    var draft by remember(prefs) { mutableStateOf(prefs) }
    var pickTime by remember { mutableStateOf(false) }
    if (pickTime) {
        val parts = draft.time.split(":").map { it.toIntOrNull() ?: 0 }
        val timeState = rememberTimePickerState(initialHour = parts.getOrElse(0) { 7 }, initialMinute = parts.getOrElse(1) { 30 }, is24Hour = true)
        AlertDialog(
            onDismissRequest = { pickTime = false },
            confirmButton = {
                TextButton(onClick = {
                    draft = draft.copy(time = String.format(Locale.US, "%02d:%02d", timeState.hour, timeState.minute)); pickTime = false
                }) { Text("Listo") }
            },
            dismissButton = { TextButton(onClick = { pickTime = false }) { Text("Cancelar") } },
            text = { TimePicker(state = timeState) },
        )
    }
    Card(Modifier.fillMaxWidth()) {
        Column(Modifier.padding(12.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
            Row(verticalAlignment = Alignment.CenterVertically) {
                Column(Modifier.weight(1f)) {
                    Text("Resumen diario", fontWeight = FontWeight.SemiBold)
                    Text("Tus compromisos y pendientes cada mañana.", color = MaterialTheme.colorScheme.onSurfaceVariant, fontSize = 12.sp)
                }
                Switch(checked = draft.enabled, onCheckedChange = { draft = draft.copy(enabled = it) })
            }
            if (draft.enabled) {
                OutlinedButton(onClick = { pickTime = true }) { Text("Hora: ${draft.time}") }
                Row(horizontalArrangement = Arrangement.spacedBy(6.dp)) {
                    listOf("internal" to "Cirilo", "telegram" to "Telegram", "email" to "Correo").forEach { (key, label) ->
                        FilterChip(selected = draft.channel == key, onClick = { draft = draft.copy(channel = key) }, label = { Text(label) })
                    }
                }
                Row(horizontalArrangement = Arrangement.spacedBy(6.dp)) {
                    listOf("weekdays" to "Lunes a viernes", "daily" to "Todos los días").forEach { (key, label) ->
                        FilterChip(selected = draft.days == key, onClick = { draft = draft.copy(days = key) }, label = { Text(label) })
                    }
                }
            }
            if (draft != prefs) {
                Button(onClick = { onSave(draft) }, enabled = !saving) { Text(if (saving) "Guardando…" else "Guardar") }
            }
        }
    }
}

/** Selector de fecha que devuelve AAAA-MM-DD (el DatePicker trabaja en UTC). */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun DatePick(initial: String?, onDismiss: () -> Unit, onPick: (String) -> Unit) {
    val utc = remember { SimpleDateFormat("yyyy-MM-dd", Locale.US).apply { timeZone = TimeZone.getTimeZone("UTC") } }
    val state = rememberDatePickerState(initialSelectedDateMillis = initial?.let { runCatching { utc.parse(it)?.time }.getOrNull() })
    DatePickerDialog(
        onDismissRequest = onDismiss,
        confirmButton = {
            TextButton(onClick = { state.selectedDateMillis?.let { onPick(utc.format(Date(it))) } ?: onDismiss() }) { Text("Listo") }
        },
        dismissButton = { TextButton(onClick = onDismiss) { Text("Cancelar") } },
    ) { DatePicker(state = state) }
}
