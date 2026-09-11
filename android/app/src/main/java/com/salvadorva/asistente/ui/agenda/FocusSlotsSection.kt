package com.salvadorva.asistente.ui.agenda

import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.BasicTextField
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Add
import androidx.compose.material.icons.filled.GraphicEq
import androidx.compose.material.icons.filled.Schedule
import androidx.compose.material.icons.filled.TextFields
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontFamily
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.salvadorva.asistente.network.models.FocusSlot
import com.salvadorva.asistente.ui.theme.*

private val mono = FontFamily.Monospace

// Etiquetas de días ISO: índice 0 = lunes (1) … índice 6 = domingo (7)
private val DAY_LABELS = listOf("Lu", "Ma", "Mi", "Ju", "Vi", "Sá", "Do")

/**
 * Contenido de la pestaña Agenda en modo "enfoque": lista de focus_slots
 * con toggle rápido on/off y editor completo (título, mensaje, hora, días,
 * tipo voz/texto).
 */
@Composable
fun FocusSlotsContent(viewModel: FocusSlotsViewModel) {
    val state by viewModel.state.collectAsState()

    Column(modifier = Modifier.fillMaxSize()) {
        state.error?.let { err ->
            Text(
                "// error: $err",
                color = CF_Pink,
                fontFamily = mono,
                fontSize = 11.sp,
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(horizontal = 18.dp, vertical = 4.dp)
                    .clickable { viewModel.clearError() },
            )
        }

        when {
            state.loading && state.slots.isEmpty() -> SlotsLoadingState()
            state.slots.isEmpty() -> SlotsEmptyState(onNew = viewModel::openCreate)
            else -> SlotList(
                slots = state.slots,
                onClick = viewModel::openEdit,
                onToggle = viewModel::setEnabled,
            )
        }
    }

    state.editor?.let { editor ->
        SlotEditorSheet(
            editor = editor,
            saving = state.saving,
            preview = state.preview,
            onChange = viewModel::updateEditor,
            onPreview = viewModel::togglePreview,
            onSave = viewModel::save,
            onDelete = { editor.id?.let(viewModel::delete) },
            onDismiss = viewModel::closeEditor,
        )
    }
}

// ─── Estados vacío / cargando ─────────────────────────────────────
@Composable
private fun SlotsLoadingState() {
    Box(Modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
        Column(horizontalAlignment = Alignment.CenterHorizontally) {
            CircularProgressIndicator(color = CF_Green, strokeWidth = 2.dp)
            Spacer(Modifier.height(12.dp))
            Text("// cargando slots…", color = CF_Dim, fontFamily = mono, fontSize = 12.sp)
        }
    }
}

@Composable
private fun SlotsEmptyState(onNew: () -> Unit) {
    Column(
        modifier = Modifier
            .fillMaxSize()
            .padding(32.dp),
        verticalArrangement = Arrangement.Center,
        horizontalAlignment = Alignment.CenterHorizontally,
    ) {
        Icon(Icons.Default.GraphicEq, null, tint = CF_Dim, modifier = Modifier.size(48.dp))
        Spacer(Modifier.height(14.dp))
        Text("> system: sin slots de enfoque", color = CF_Text, fontFamily = mono, fontSize = 13.sp)
        Spacer(Modifier.height(6.dp))
        Text(
            "// creá tu rutina y Cirilo te acompaña",
            color = CF_Dim.copy(alpha = 0.7f),
            fontFamily = mono,
            fontSize = 11.sp,
        )
        Spacer(Modifier.height(18.dp))
        Row(
            verticalAlignment = Alignment.CenterVertically,
            horizontalArrangement = Arrangement.spacedBy(6.dp),
            modifier = Modifier
                .clip(RoundedCornerShape(10.dp))
                .background(CF_Green.copy(alpha = 0.10f))
                .border(1.dp, CF_Green.copy(alpha = 0.5f), RoundedCornerShape(10.dp))
                .clickable(onClick = onNew)
                .padding(horizontal = 14.dp, vertical = 9.dp),
        ) {
            Icon(Icons.Default.Add, null, tint = CF_Green, modifier = Modifier.size(16.dp))
            Text("crear slot", color = CF_Green, fontFamily = mono, fontSize = 12.sp, fontWeight = FontWeight.SemiBold)
        }
    }
}

// ─── Lista ────────────────────────────────────────────────────────
@Composable
private fun SlotList(
    slots: List<FocusSlot>,
    onClick: (FocusSlot) -> Unit,
    onToggle: (FocusSlot, Boolean) -> Unit,
) {
    LazyColumn(
        modifier = Modifier.fillMaxSize(),
        contentPadding = PaddingValues(start = 16.dp, end = 16.dp, top = 4.dp, bottom = 24.dp),
        verticalArrangement = Arrangement.spacedBy(8.dp),
    ) {
        items(slots, key = { it.id }) { slot ->
            SlotCard(
                slot = slot,
                onClick = { onClick(slot) },
                onToggle = { enabled -> onToggle(slot, enabled) },
            )
        }
    }
}

@Composable
private fun SlotCard(
    slot: FocusSlot,
    onClick: () -> Unit,
    onToggle: (Boolean) -> Unit,
) {
    val accent = if (slot.enabled) CF_Green else CF_Dim

    Row(
        modifier = Modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(12.dp))
            .background(accent.copy(alpha = 0.07f))
            .border(1.dp, accent.copy(alpha = 0.4f), RoundedCornerShape(12.dp))
            .clickable(onClick = onClick)
            .padding(12.dp),
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(12.dp),
    ) {
        Box(
            modifier = Modifier
                .width(4.dp)
                .height(48.dp)
                .clip(RoundedCornerShape(2.dp))
                .background(accent)
        )
        Column(modifier = Modifier.weight(1f)) {
            Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                Text(
                    slot.time,
                    color = if (slot.enabled) CF_Cyan else CF_Dim,
                    fontFamily = mono,
                    fontSize = 15.sp,
                    fontWeight = FontWeight.Bold,
                )
                TypeChip(withAudio = slot.with_audio, voice = slot.voice, enabled = slot.enabled)
            }
            Spacer(Modifier.height(3.dp))
            Text(
                slot.title,
                color = if (slot.enabled) CF_Text else CF_Dim,
                fontFamily = mono,
                fontSize = 13.sp,
                fontWeight = FontWeight.SemiBold,
                maxLines = 1,
            )
            Spacer(Modifier.height(2.dp))
            Text(
                daysLabel(slot.days),
                color = CF_Dim,
                fontFamily = mono,
                fontSize = 10.5.sp,
            )
        }
        Switch(
            checked = slot.enabled,
            onCheckedChange = onToggle,
            colors = SwitchDefaults.colors(
                checkedThumbColor = Color.White,
                checkedTrackColor = CF_Green,
                checkedBorderColor = CF_Green,
                uncheckedThumbColor = CF_Dim,
                uncheckedTrackColor = Color.Transparent,
                uncheckedBorderColor = CF_Dim.copy(alpha = 0.5f),
            ),
        )
    }
}

@Composable
private fun TypeChip(withAudio: Boolean, voice: String, enabled: Boolean) {
    val accent = when {
        !enabled -> CF_Dim
        withAudio -> CF_Purple
        else -> CF_Dim
    }
    Row(
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(3.dp),
        modifier = Modifier
            .clip(RoundedCornerShape(6.dp))
            .background(accent.copy(alpha = 0.12f))
            .padding(horizontal = 6.dp, vertical = 2.dp),
    ) {
        Icon(
            if (withAudio) Icons.Default.GraphicEq else Icons.Default.TextFields,
            null,
            tint = accent,
            modifier = Modifier.size(10.dp),
        )
        Text(
            if (withAudio) "voz·$voice" else "texto",
            color = accent,
            fontFamily = mono,
            fontSize = 9.5.sp,
        )
    }
}

// ─── Sheet de crear / editar ──────────────────────────────────────
@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun SlotEditorSheet(
    editor: SlotEditor,
    saving: Boolean,
    preview: PreviewState,
    onChange: ((SlotEditor) -> SlotEditor) -> Unit,
    onPreview: () -> Unit,
    onSave: () -> Unit,
    onDelete: () -> Unit,
    onDismiss: () -> Unit,
) {
    val sheetState = rememberModalBottomSheetState(skipPartiallyExpanded = true)
    var timePickerOpen by remember { mutableStateOf(false) }
    var confirmDelete by remember { mutableStateOf(false) }

    ModalBottomSheet(
        onDismissRequest = onDismiss,
        sheetState = sheetState,
        containerColor = Color(0xFF0A0418),
        dragHandle = { SlotSheetHandle(CF_Green) },
    ) {
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .verticalScroll(rememberScrollState())
                .padding(horizontal = 20.dp)
                .padding(bottom = 28.dp),
            verticalArrangement = Arrangement.spacedBy(14.dp),
        ) {
            Text(
                if (editor.isEditing) "// slot.edit" else "// slot.new",
                color = CF_Dim, fontFamily = mono, fontSize = 11.sp, letterSpacing = 1.5.sp,
            )

            SlotFieldLabel("título")
            SlotTextField(
                value = editor.title,
                placeholder = "ej. Trabajo profundo",
                onValueChange = { v -> onChange { it.copy(title = v) } },
            )

            SlotFieldLabel("mensaje (lo que Cirilo te dirá)")
            SlotTextField(
                value = editor.message,
                placeholder = "ej. Cierra el YouTube, gorila. Toca sesión de enfoque.",
                singleLine = false,
                onValueChange = { v -> onChange { it.copy(message = v) } },
            )

            SlotFieldLabel("hora (Guatemala)")
            Row(
                modifier = Modifier
                    .clip(RoundedCornerShape(10.dp))
                    .background(CF_Cyan.copy(alpha = 0.06f))
                    .border(1.dp, CF_Cyan.copy(alpha = 0.4f), RoundedCornerShape(10.dp))
                    .clickable { timePickerOpen = true }
                    .padding(horizontal = 12.dp, vertical = 11.dp),
                verticalAlignment = Alignment.CenterVertically,
                horizontalArrangement = Arrangement.spacedBy(6.dp),
            ) {
                Icon(Icons.Default.Schedule, null, tint = CF_Cyan, modifier = Modifier.size(14.dp))
                Text(editor.time, color = CF_Text, fontFamily = mono, fontSize = 13.sp)
            }

            SlotFieldLabel("días")
            Row(horizontalArrangement = Arrangement.spacedBy(6.dp)) {
                (1..7).forEach { day ->
                    val selected = day in editor.days
                    Box(
                        modifier = Modifier
                            .weight(1f)
                            .clip(RoundedCornerShape(8.dp))
                            .background(if (selected) CF_Green.copy(alpha = 0.15f) else Color.Transparent)
                            .border(
                                1.dp,
                                if (selected) CF_Green.copy(alpha = 0.6f) else CF_Dim.copy(alpha = 0.35f),
                                RoundedCornerShape(8.dp),
                            )
                            .clickable {
                                onChange {
                                    it.copy(days = if (selected) it.days - day else it.days + day)
                                }
                            }
                            .padding(vertical = 8.dp),
                        contentAlignment = Alignment.Center,
                    ) {
                        Text(
                            DAY_LABELS[day - 1],
                            color = if (selected) CF_Green else CF_Dim,
                            fontFamily = mono,
                            fontSize = 11.sp,
                            fontWeight = if (selected) FontWeight.Bold else FontWeight.Normal,
                        )
                    }
                }
            }

            // Tipo: mensaje de voz o solo texto
            Row(verticalAlignment = Alignment.CenterVertically) {
                Column(modifier = Modifier.weight(1f)) {
                    Text("❯ mensaje de voz", color = CF_Text, fontFamily = mono, fontSize = 13.sp)
                    Text(
                        if (editor.withAudio) "Cirilo lo dice en audio (TTS OpenAI)"
                        else "notificación de solo texto",
                        color = CF_Dim,
                        fontFamily = mono,
                        fontSize = 10.5.sp,
                    )
                }
                Switch(
                    checked = editor.withAudio,
                    onCheckedChange = { c -> onChange { it.copy(withAudio = c) } },
                    colors = SwitchDefaults.colors(
                        checkedThumbColor = Color.White,
                        checkedTrackColor = CF_Purple,
                        checkedBorderColor = CF_Purple,
                        uncheckedThumbColor = CF_Dim,
                        uncheckedTrackColor = Color.Transparent,
                        uncheckedBorderColor = CF_Dim.copy(alpha = 0.5f),
                    ),
                )
            }

            if (editor.withAudio) {
                Row(horizontalArrangement = Arrangement.spacedBy(10.dp)) {
                    VoicePill("echo", editor.voice == "echo", Modifier.weight(1f)) {
                        onChange { it.copy(voice = "echo") }
                    }
                    VoicePill("nova", editor.voice == "nova", Modifier.weight(1f)) {
                        onChange { it.copy(voice = "nova") }
                    }
                }

                // Preview: escuchar cómo suena el mensaje con la voz elegida
                SlotActionPill(
                    label = when (preview) {
                        PreviewState.Loading -> "generando audio…"
                        PreviewState.Playing -> "■ detener"
                        else -> "❯ escuchar mensaje"
                    },
                    accent = if (editor.message.isNotBlank()) CF_Cyan else CF_Dim,
                    modifier = Modifier.fillMaxWidth(),
                    onClick = { if (editor.message.isNotBlank()) onPreview() },
                )
            }

            Spacer(Modifier.height(4.dp))
            SlotActionPill(
                label = when {
                    saving -> "guardando…"
                    editor.isEditing -> "❯ actualizar slot"
                    else -> "❯ crear slot"
                },
                accent = if (editor.isValid) CF_Green else CF_Dim,
                modifier = Modifier.fillMaxWidth(),
                onClick = { if (editor.isValid && !saving) onSave() },
            )

            if (editor.isEditing) {
                SlotActionPill(
                    label = if (confirmDelete) (if (saving) "eliminando…" else "confirmar ✕") else "❯ eliminar slot",
                    accent = CF_Pink,
                    modifier = Modifier.fillMaxWidth(),
                    onClick = { if (confirmDelete) onDelete() else confirmDelete = true },
                )
            }
        }
    }

    if (timePickerOpen) {
        val parts = editor.time.split(":")
        val tpState = rememberTimePickerState(
            initialHour = parts.getOrNull(0)?.toIntOrNull() ?: 8,
            initialMinute = parts.getOrNull(1)?.toIntOrNull() ?: 0,
            is24Hour = true,
        )
        AlertDialog(
            onDismissRequest = { timePickerOpen = false },
            containerColor = Color(0xFF120A26),
            confirmButton = {
                TextButton(onClick = {
                    onChange { it.copy(time = String.format("%02d:%02d", tpState.hour, tpState.minute)) }
                    timePickerOpen = false
                }) { Text("OK", color = CF_Cyan, fontFamily = mono) }
            },
            dismissButton = {
                TextButton(onClick = { timePickerOpen = false }) { Text("cancelar", color = CF_Dim, fontFamily = mono) }
            },
            text = {
                Box(Modifier.fillMaxWidth(), contentAlignment = Alignment.Center) {
                    TimePicker(state = tpState)
                }
            },
        )
    }
}

@Composable
private fun VoicePill(label: String, selected: Boolean, modifier: Modifier = Modifier, onClick: () -> Unit) {
    Box(
        modifier = modifier
            .clip(RoundedCornerShape(10.dp))
            .background(if (selected) CF_Purple.copy(alpha = 0.15f) else Color.Transparent)
            .border(
                1.dp,
                if (selected) CF_Purple.copy(alpha = 0.6f) else CF_Dim.copy(alpha = 0.35f),
                RoundedCornerShape(10.dp),
            )
            .clickable(onClick = onClick)
            .padding(vertical = 10.dp),
        contentAlignment = Alignment.Center,
    ) {
        Text(
            "$ $label",
            color = if (selected) CF_Purple else CF_Dim,
            fontFamily = mono,
            fontSize = 12.sp,
            fontWeight = if (selected) FontWeight.SemiBold else FontWeight.Normal,
        )
    }
}

// ─── Piezas locales (mismo lenguaje visual que AgendaScreen) ──────
@Composable
private fun SlotFieldLabel(text: String) {
    Text("$ $text", color = CF_Dim, fontFamily = mono, fontSize = 11.sp)
}

@Composable
private fun SlotTextField(
    value: String,
    placeholder: String,
    singleLine: Boolean = true,
    onValueChange: (String) -> Unit,
) {
    Box(
        modifier = Modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(10.dp))
            .background(CF_Purple.copy(alpha = 0.06f))
            .border(1.dp, CF_Purple.copy(alpha = 0.4f), RoundedCornerShape(10.dp))
            .padding(horizontal = 12.dp, vertical = 11.dp),
    ) {
        Row {
            Text("❯ ", color = CF_Purple, fontFamily = mono, fontSize = 14.sp)
            BasicTextField(
                value = value,
                onValueChange = onValueChange,
                singleLine = singleLine,
                maxLines = if (singleLine) 1 else 4,
                textStyle = androidx.compose.ui.text.TextStyle(color = CF_Text, fontFamily = mono, fontSize = 13.sp),
                cursorBrush = Brush.verticalGradient(listOf(CF_Cyan, CF_Purple)),
                decorationBox = { inner ->
                    if (value.isEmpty()) {
                        Text(placeholder, color = CF_Dim, fontFamily = mono, fontSize = 13.sp)
                    }
                    inner()
                },
                modifier = Modifier.weight(1f),
            )
        }
    }
}

@Composable
private fun SlotActionPill(label: String, accent: Color, modifier: Modifier = Modifier, onClick: () -> Unit) {
    Box(
        modifier = modifier
            .clip(RoundedCornerShape(12.dp))
            .background(accent.copy(alpha = 0.10f))
            .border(1.dp, accent.copy(alpha = 0.5f), RoundedCornerShape(12.dp))
            .clickable(onClick = onClick)
            .padding(vertical = 12.dp),
        contentAlignment = Alignment.Center,
    ) {
        Text(label, color = accent, fontFamily = mono, fontSize = 13.sp, fontWeight = FontWeight.SemiBold)
    }
}

@Composable
private fun SlotSheetHandle(accent: Color) {
    Box(
        modifier = Modifier
            .padding(vertical = 10.dp)
            .size(width = 36.dp, height = 4.dp)
            .clip(RoundedCornerShape(2.dp))
            .background(accent.copy(alpha = 0.5f))
    )
}

// ─── Helpers ──────────────────────────────────────────────────────
private fun daysLabel(days: List<Int>): String {
    val sorted = days.distinct().sorted()
    return when (sorted) {
        listOf(1, 2, 3, 4, 5) -> "lun–vie"
        listOf(1, 2, 3, 4, 5, 6) -> "lun–sáb"
        listOf(1, 2, 3, 4, 5, 6, 7) -> "todos los días"
        listOf(6, 7) -> "sáb–dom"
        else -> sorted.joinToString("·") { DAY_LABELS[it - 1] }
    }
}
