package com.salvadorva.asistente.ui.memory

import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Close
import androidx.compose.material.icons.filled.Edit
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.compose.ui.window.Dialog
import androidx.compose.ui.window.DialogProperties
import androidx.lifecycle.viewmodel.compose.viewModel
import com.salvadorva.asistente.network.models.MemoryFact

/** F4-06: lo que Cirilo sabe de ti. Editar lo marca como dicho por ti; olvidar no vuelve a aprenderse solo. */
@Composable
fun MemoryDialog(onDismiss: () -> Unit, vm: MemoryViewModel = viewModel()) {
    val state by vm.state.collectAsState()
    var editing by remember { mutableStateOf<MemoryFact?>(null) }
    var forgetting by remember { mutableStateOf<MemoryFact?>(null) }
    var confirmAll by remember { mutableStateOf(false) }
    val memory = state.memory

    editing?.let { fact ->
        var value by remember(fact.id) { mutableStateOf(fact.value.orEmpty()) }
        AlertDialog(
            onDismissRequest = { editing = null },
            title = { Text(fact.key?.replace('_', ' ') ?: "Dato") },
            text = { OutlinedTextField(value = value, onValueChange = { value = it }, label = { Text("Valor") }) },
            confirmButton = {
                TextButton(enabled = value.isNotBlank(), onClick = { fact.id?.let { vm.edit(it, value) }; editing = null }) { Text("Guardar") }
            },
            dismissButton = { TextButton(onClick = { editing = null }) { Text("Cancelar") } },
        )
    }
    forgetting?.let { fact ->
        AlertDialog(
            onDismissRequest = { forgetting = null },
            title = { Text("¿Olvidar este dato?") },
            text = { Text("«${fact.value}». Cirilo no lo volverá a aprender por su cuenta.") },
            confirmButton = { TextButton(onClick = { fact.id?.let(vm::forget); forgetting = null }) { Text("Olvidar") } },
            dismissButton = { TextButton(onClick = { forgetting = null }) { Text("Cancelar") } },
        )
    }
    if (confirmAll) {
        AlertDialog(
            onDismissRequest = { confirmAll = false },
            title = { Text("¿Borrar toda tu memoria?") },
            text = { Text("Cirilo olvidará todos los datos guardados sobre ti. No se puede deshacer.") },
            confirmButton = { TextButton(onClick = { vm.forgetAll(); confirmAll = false }) { Text("Borrar todo") } },
            dismissButton = { TextButton(onClick = { confirmAll = false }) { Text("Cancelar") } },
        )
    }

    Dialog(onDismissRequest = onDismiss, properties = DialogProperties(usePlatformDefaultWidth = false)) {
        Surface(Modifier.fillMaxSize(), color = MaterialTheme.colorScheme.background) {
            Column(Modifier.padding(16.dp), verticalArrangement = Arrangement.spacedBy(10.dp)) {
                Row(verticalAlignment = Alignment.CenterVertically) {
                    Text("Lo que Cirilo sabe de ti", fontWeight = FontWeight.Bold, fontSize = 18.sp, modifier = Modifier.weight(1f))
                    TextButton(onClick = onDismiss) { Text("Cerrar") }
                }
                Row(verticalAlignment = Alignment.CenterVertically) {
                    Column(Modifier.weight(1f)) {
                        Text("Aprender de mis conversaciones")
                        Text(
                            "Si lo apagas, Cirilo usa lo que ya sabe y lo que le pidas recordar («llámame…»), pero no aprende nada más solo.",
                            color = MaterialTheme.colorScheme.onSurfaceVariant, fontSize = 12.sp,
                        )
                    }
                    Switch(checked = memory?.extraction_enabled != false, enabled = memory != null && !state.busy, onCheckedChange = vm::setExtraction)
                }
                state.error?.let { Text(it, color = MaterialTheme.colorScheme.error, fontSize = 13.sp) }
                if (state.loading && memory == null) LinearProgressIndicator(Modifier.fillMaxWidth())

                val groups = memory?.facts.orEmpty().filterValues { it.isNotEmpty() }
                if (memory != null && groups.isEmpty()) {
                    Text("Aún no hay datos guardados sobre ti.", color = MaterialTheme.colorScheme.onSurfaceVariant)
                }
                LazyColumn(Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(6.dp)) {
                    groups.forEach { (category, facts) ->
                        item {
                            Text(memory?.categories?.get(category) ?: category, fontWeight = FontWeight.SemiBold,
                                color = MaterialTheme.colorScheme.primary, modifier = Modifier.padding(top = 8.dp))
                        }
                        items(facts) { fact ->
                            Card(Modifier.fillMaxWidth()) {
                                Row(Modifier.padding(start = 12.dp), verticalAlignment = Alignment.CenterVertically) {
                                    Column(Modifier.weight(1f).padding(vertical = 8.dp)) {
                                        Text(fact.key?.replace('_', ' ') ?: "", color = MaterialTheme.colorScheme.onSurfaceVariant, fontSize = 12.sp)
                                        Text(fact.value.orEmpty())
                                        if (fact.source_type == "user_explicit") {
                                            Text("indicado por ti", color = MaterialTheme.colorScheme.onSurfaceVariant, fontSize = 11.sp)
                                        }
                                    }
                                    IconButton(onClick = { editing = fact }, enabled = !state.busy) { Icon(Icons.Default.Edit, "Editar") }
                                    IconButton(onClick = { forgetting = fact }, enabled = !state.busy) { Icon(Icons.Default.Close, "Olvidar") }
                                }
                            }
                        }
                    }
                }
                if (groups.isNotEmpty()) {
                    OutlinedButton(onClick = { confirmAll = true }, enabled = !state.busy, modifier = Modifier.fillMaxWidth()) {
                        Text("Borrar toda mi memoria", color = MaterialTheme.colorScheme.error)
                    }
                }
            }
        }
    }
}
