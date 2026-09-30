package com.salvadorva.asistente.ui.today

import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Add
import androidx.compose.material.icons.filled.Refresh
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.font.FontStyle
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.lifecycle.viewmodel.compose.viewModel
import com.salvadorva.asistente.network.models.TaskItem

/** F6: lo del día primero — compromisos, pendientes y sugerencias sin confirmar. */
@Composable
fun TodayScreen(onOpenChat: () -> Unit, vm: TodayViewModel = viewModel()) {
    val state by vm.state.collectAsState()
    val today = state.today

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
        }

        item { SectionTitle("Compromisos de hoy") }
        val events = today?.events.orEmpty()
        if (events.isEmpty()) {
            item { EmptyLine("No tienes compromisos agendados para hoy.") }
        } else {
            items(events) { ev ->
                Card(Modifier.fillMaxWidth()) {
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

        item { SectionTitle("Pendientes") }
        val tasks = today?.tasks.orEmpty()
        if (tasks.isEmpty()) item { EmptyLine("No tienes pendientes para hoy.") }
        items(tasks) { task ->
            TaskCard(task, today?.date, state.busyTaskId == task.id) {
                val id = task.id ?: return@TaskCard
                TextButton(onClick = { vm.complete(id) }) { Text("Hecho") }
                TextButton(onClick = { vm.postponeToTomorrow(id) }) { Text("Mañana") }
                TextButton(onClick = { vm.dismiss(id) }) { Text("Descartar") }
            }
        }
        item {
            Row(verticalAlignment = Alignment.CenterVertically) {
                OutlinedTextField(
                    value = state.newTask,
                    onValueChange = vm::onNewTaskChange,
                    modifier = Modifier.weight(1f),
                    singleLine = true,
                    placeholder = { Text("Nuevo pendiente") },
                )
                IconButton(onClick = vm::addTask, enabled = state.newTask.isNotBlank()) { Icon(Icons.Default.Add, "Agregar pendiente") }
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

        item {
            OutlinedButton(onClick = onOpenChat, modifier = Modifier.fillMaxWidth()) { Text("Hablar o escribir a Cirilo") }
        }
    }
}

@Composable
private fun SectionTitle(text: String) {
    Text(text, fontWeight = FontWeight.Bold, fontSize = 15.sp, modifier = Modifier.padding(top = 4.dp))
}

@Composable
private fun EmptyLine(text: String) {
    Text(text, color = MaterialTheme.colorScheme.onSurfaceVariant, fontSize = 13.sp)
}

@Composable
private fun TaskCard(task: TaskItem, today: String?, busy: Boolean, suggestion: Boolean = false, actions: @Composable RowScope.() -> Unit) {
    Card(Modifier.fillMaxWidth()) {
        Column(Modifier.padding(horizontal = 12.dp, vertical = 8.dp)) {
            Text(task.title ?: "", fontStyle = if (suggestion) FontStyle.Italic else FontStyle.Normal)
            val due = task.due_date
            Text(
                when {
                    suggestion -> "Sugerencia"
                    due == null -> "Sin fecha"
                    today != null && due < today -> "Vencido ($due)"
                    else -> "Vence hoy"
                },
                color = MaterialTheme.colorScheme.onSurfaceVariant, fontSize = 12.sp,
            )
            if (busy) LinearProgressIndicator(Modifier.fillMaxWidth().padding(top = 4.dp)) else Row(content = actions)
        }
    }
}
