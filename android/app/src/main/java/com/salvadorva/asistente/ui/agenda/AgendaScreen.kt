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
import androidx.compose.material.icons.filled.CalendarMonth
import androidx.compose.material.icons.filled.Delete
import androidx.compose.material.icons.filled.Edit
import androidx.compose.material.icons.filled.LocationOn
import androidx.compose.material.icons.filled.NotificationsActive
import androidx.compose.material.icons.filled.Schedule
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontFamily
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.lifecycle.viewmodel.compose.viewModel
import com.salvadorva.asistente.network.models.AgendaEvent
import com.salvadorva.asistente.ui.theme.*
import com.salvadorva.asistente.util.combineDateTime
import com.salvadorva.asistente.util.dayKey
import com.salvadorva.asistente.util.formatMillis
import com.salvadorva.asistente.util.friendlyDayLabel
import com.salvadorva.asistente.util.localDateToUtcMillis
import com.salvadorva.asistente.util.localParts
import com.salvadorva.asistente.util.reminderLabel

private val mono = FontFamily.Monospace

private enum class PickTarget { START, END }

private enum class AgendaMode { EVENTS, FOCUS }

private val REMINDER_OPTIONS = listOf(0, 5, 10, 15, 30, 60, 120, 1440)

@Composable
fun AgendaScreen(
    deepLinkEventId: Int? = null,
    onDeepLinkConsumed: () -> Unit = {},
    viewModel: AgendaViewModel = viewModel(),
    focusViewModel: FocusSlotsViewModel = viewModel(),
) {
    val state by viewModel.state.collectAsState()
    var mode by remember { mutableStateOf(AgendaMode.EVENTS) }

    LaunchedEffect(deepLinkEventId) {
        deepLinkEventId?.let {
            mode = AgendaMode.EVENTS
            viewModel.openFromDeepLink(it)
            onDeepLinkConsumed()
        }
    }

    Box(
        modifier = Modifier
            .fillMaxSize()
            .background(
                Brush.radialGradient(
                    colors = listOf(CF_BgGrad1, CF_BgGrad2, CF_Bg),
                    center = Offset(500f, 0f),
                    radius = 1500f,
                )
            )
    ) {
        Column(modifier = Modifier.fillMaxSize()) {
            HeaderBar(
                mode = mode,
                onModeChange = { mode = it },
                onNew = {
                    if (mode == AgendaMode.EVENTS) viewModel.openCreate()
                    else focusViewModel.openCreate()
                },
            )

            when (mode) {
                AgendaMode.EVENTS -> {
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
                        state.loading && state.events.isEmpty() -> LoadingState()
                        state.events.isEmpty() -> EmptyState(onNew = viewModel::openCreate)
                        else -> EventList(
                            events = state.events,
                            highlightId = state.highlightEventId,
                            onClick = viewModel::openDetail,
                        )
                    }
                }

                AgendaMode.FOCUS -> FocusSlotsContent(viewModel = focusViewModel)
            }
        }
    }

    state.detail?.let { event ->
        DetailSheet(
            event = event,
            onEdit = { viewModel.openEdit(event) },
            onDelete = { viewModel.delete(event.id) },
            onDismiss = viewModel::closeDetail,
            deleting = state.saving,
        )
    }

    state.editor?.let { editor ->
        EditorSheet(
            editor = editor,
            saving = state.saving,
            onChange = viewModel::updateEditor,
            onSave = viewModel::save,
            onDismiss = viewModel::closeEditor,
        )
    }
}

// ─── Header ───────────────────────────────────────────────────────
@Composable
private fun HeaderBar(
    mode: AgendaMode,
    onModeChange: (AgendaMode) -> Unit,
    onNew: () -> Unit,
) {
    val accent = if (mode == AgendaMode.EVENTS) CF_Cyan else CF_Green

    Row(
        modifier = Modifier
            .fillMaxWidth()
            .padding(horizontal = 16.dp, vertical = 14.dp),
        horizontalArrangement = Arrangement.SpaceBetween,
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
            ModePill(
                label = "eventos",
                selected = mode == AgendaMode.EVENTS,
                accent = CF_Cyan,
                onClick = { onModeChange(AgendaMode.EVENTS) },
            )
            ModePill(
                label = "enfoque",
                selected = mode == AgendaMode.FOCUS,
                accent = CF_Green,
                onClick = { onModeChange(AgendaMode.FOCUS) },
            )
        }
        Row(
            verticalAlignment = Alignment.CenterVertically,
            horizontalArrangement = Arrangement.spacedBy(6.dp),
            modifier = Modifier
                .clip(RoundedCornerShape(10.dp))
                .background(accent.copy(alpha = 0.10f))
                .border(1.dp, accent.copy(alpha = 0.5f), RoundedCornerShape(10.dp))
                .clickable(onClick = onNew)
                .padding(horizontal = 12.dp, vertical = 7.dp),
        ) {
            Icon(Icons.Default.Add, null, tint = accent, modifier = Modifier.size(16.dp))
            Text(
                if (mode == AgendaMode.EVENTS) "nuevo.evento" else "nuevo.slot",
                color = accent,
                fontFamily = mono,
                fontSize = 12.sp,
                fontWeight = FontWeight.SemiBold,
            )
        }
    }
}

@Composable
private fun ModePill(
    label: String,
    selected: Boolean,
    accent: Color,
    onClick: () -> Unit,
) {
    Box(
        modifier = Modifier
            .clip(RoundedCornerShape(10.dp))
            .background(if (selected) accent.copy(alpha = 0.12f) else Color.Transparent)
            .border(
                1.dp,
                if (selected) accent.copy(alpha = 0.55f) else CF_Dim.copy(alpha = 0.3f),
                RoundedCornerShape(10.dp),
            )
            .clickable(onClick = onClick)
            .padding(horizontal = 12.dp, vertical = 7.dp),
    ) {
        Text(
            "// $label",
            color = if (selected) accent else CF_Dim,
            fontFamily = mono,
            fontSize = 12.sp,
            fontWeight = if (selected) FontWeight.SemiBold else FontWeight.Normal,
            letterSpacing = 1.sp,
        )
    }
}

// ─── Estados vacío / cargando ─────────────────────────────────────
@Composable
private fun LoadingState() {
    Box(Modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
        Column(horizontalAlignment = Alignment.CenterHorizontally) {
            CircularProgressIndicator(color = CF_Cyan, strokeWidth = 2.dp)
            Spacer(Modifier.height(12.dp))
            Text("// cargando eventos…", color = CF_Dim, fontFamily = mono, fontSize = 12.sp)
        }
    }
}

@Composable
private fun EmptyState(onNew: () -> Unit) {
    Column(
        modifier = Modifier
            .fillMaxSize()
            .padding(32.dp),
        verticalArrangement = Arrangement.Center,
        horizontalAlignment = Alignment.CenterHorizontally,
    ) {
        Icon(Icons.Default.CalendarMonth, null, tint = CF_Dim, modifier = Modifier.size(48.dp))
        Spacer(Modifier.height(14.dp))
        Text("> system: sin eventos próximos", color = CF_Text, fontFamily = mono, fontSize = 13.sp)
        Spacer(Modifier.height(6.dp))
        Text(
            "// agendá algo o pedíselo a Cirilo por voz",
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
                .background(CF_Cyan.copy(alpha = 0.10f))
                .border(1.dp, CF_Cyan.copy(alpha = 0.5f), RoundedCornerShape(10.dp))
                .clickable(onClick = onNew)
                .padding(horizontal = 14.dp, vertical = 9.dp),
        ) {
            Icon(Icons.Default.Add, null, tint = CF_Cyan, modifier = Modifier.size(16.dp))
            Text("crear evento", color = CF_Cyan, fontFamily = mono, fontSize = 12.sp, fontWeight = FontWeight.SemiBold)
        }
    }
}

// ─── Lista agrupada por día ───────────────────────────────────────
@Composable
private fun EventList(
    events: List<AgendaEvent>,
    highlightId: Int?,
    onClick: (AgendaEvent) -> Unit,
) {
    val grouped = events.groupBy { dayKey(it.start_date) }
    LazyColumn(
        modifier = Modifier.fillMaxSize(),
        contentPadding = PaddingValues(start = 16.dp, end = 16.dp, bottom = 24.dp),
        verticalArrangement = Arrangement.spacedBy(8.dp),
    ) {
        grouped.forEach { (_, dayEvents) ->
            item {
                Text(
                    friendlyDayLabel(dayEvents.first().start_date),
                    color = CF_Cyan,
                    fontFamily = mono,
                    fontSize = 11.sp,
                    fontWeight = FontWeight.Bold,
                    letterSpacing = 1.5.sp,
                    modifier = Modifier.padding(top = 10.dp, bottom = 2.dp),
                )
            }
            items(dayEvents, key = { it.id }) { event ->
                EventCard(
                    event = event,
                    highlighted = event.id == highlightId,
                    onClick = { onClick(event) },
                )
            }
        }
    }
}

@Composable
private fun EventCard(event: AgendaEvent, highlighted: Boolean, onClick: () -> Unit) {
    val accent = parseColor(event.color, CF_Purple)
    val borderColor = if (highlighted) CF_Cyan else accent.copy(alpha = 0.4f)
    val borderWidth = if (highlighted) 1.5.dp else 1.dp

    Row(
        modifier = Modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(12.dp))
            .background(accent.copy(alpha = if (highlighted) 0.14f else 0.07f))
            .border(borderWidth, borderColor, RoundedCornerShape(12.dp))
            .clickable(onClick = onClick)
            .padding(12.dp),
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(12.dp),
    ) {
        Box(
            modifier = Modifier
                .width(4.dp)
                .height(40.dp)
                .clip(RoundedCornerShape(2.dp))
                .background(accent)
        )
        Column(modifier = Modifier.weight(1f)) {
            Text(
                event.title,
                color = CF_Text,
                fontFamily = mono,
                fontSize = 13.sp,
                fontWeight = FontWeight.SemiBold,
                maxLines = 2,
            )
            Spacer(Modifier.height(3.dp))
            Text(
                timeLabel(event),
                color = CF_Dim,
                fontFamily = mono,
                fontSize = 11.sp,
            )
            event.location?.takeIf { it.isNotBlank() }?.let { loc ->
                Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(4.dp)) {
                    Icon(Icons.Default.LocationOn, null, tint = CF_Dim, modifier = Modifier.size(11.dp))
                    Text(loc, color = CF_Dim, fontFamily = mono, fontSize = 10.5.sp, maxLines = 1)
                }
            }
        }
        if (event.reminder_minutes_before > 0) {
            Icon(Icons.Default.NotificationsActive, null, tint = CF_Green.copy(alpha = 0.8f), modifier = Modifier.size(15.dp))
        }
    }
}

// ─── Sheet de detalle ─────────────────────────────────────────────
@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun DetailSheet(
    event: AgendaEvent,
    onEdit: () -> Unit,
    onDelete: () -> Unit,
    onDismiss: () -> Unit,
    deleting: Boolean,
) {
    val sheetState = rememberModalBottomSheetState(skipPartiallyExpanded = true)
    var confirmDelete by remember { mutableStateOf(false) }
    val accent = parseColor(event.color, CF_Purple)

    ModalBottomSheet(
        onDismissRequest = onDismiss,
        sheetState = sheetState,
        containerColor = Color(0xFF0A0418),
        dragHandle = { SheetHandle(accent) },
    ) {
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .padding(horizontal = 20.dp)
                .padding(bottom = 28.dp),
            verticalArrangement = Arrangement.spacedBy(12.dp),
        ) {
            Text("// event.detail", color = CF_Dim, fontFamily = mono, fontSize = 11.sp, letterSpacing = 1.5.sp)
            Text(event.title, color = CF_Text, fontFamily = mono, fontSize = 17.sp, fontWeight = FontWeight.Bold)

            DetailRow(Icons.Default.Schedule, timeLabel(event), accent)
            event.location?.takeIf { it.isNotBlank() }?.let { DetailRow(Icons.Default.LocationOn, it, accent) }
            if (event.reminder_minutes_before > 0) {
                DetailRow(Icons.Default.NotificationsActive, reminderLabel(event.reminder_minutes_before), accent)
            }
            event.description?.takeIf { it.isNotBlank() }?.let { desc ->
                Text(desc, color = CF_Dim, fontFamily = mono, fontSize = 12.sp, lineHeight = 18.sp)
            }

            Spacer(Modifier.height(4.dp))
            Row(horizontalArrangement = Arrangement.spacedBy(10.dp)) {
                ActionPill("❯ editar", CF_Cyan, Modifier.weight(1f), onClick = onEdit)
                ActionPill(
                    if (confirmDelete) (if (deleting) "eliminando…" else "confirmar ✕") else "❯ eliminar",
                    CF_Pink,
                    Modifier.weight(1f),
                    onClick = { if (confirmDelete) onDelete() else confirmDelete = true },
                )
            }
        }
    }
}

@Composable
private fun DetailRow(icon: androidx.compose.ui.graphics.vector.ImageVector, text: String, accent: Color) {
    Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(8.dp)) {
        Icon(icon, null, tint = accent, modifier = Modifier.size(16.dp))
        Text(text, color = CF_Text, fontFamily = mono, fontSize = 12.5.sp)
    }
}

@Composable
private fun ActionPill(label: String, accent: Color, modifier: Modifier = Modifier, onClick: () -> Unit) {
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

// ─── Sheet de crear / editar ──────────────────────────────────────
@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun EditorSheet(
    editor: EventEditor,
    saving: Boolean,
    onChange: ((EventEditor) -> EventEditor) -> Unit,
    onSave: () -> Unit,
    onDismiss: () -> Unit,
) {
    val sheetState = rememberModalBottomSheetState(skipPartiallyExpanded = true)
    var datePicker by remember { mutableStateOf<PickTarget?>(null) }
    var timePicker by remember { mutableStateOf<PickTarget?>(null) }
    var reminderOpen by remember { mutableStateOf(false) }

    ModalBottomSheet(
        onDismissRequest = onDismiss,
        sheetState = sheetState,
        containerColor = Color(0xFF0A0418),
        dragHandle = { SheetHandle(CF_Cyan) },
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
                if (editor.isEditing) "// event.edit" else "// event.new",
                color = CF_Dim, fontFamily = mono, fontSize = 11.sp, letterSpacing = 1.5.sp,
            )

            FieldLabel("título")
            ConsoleTextField(
                value = editor.title,
                placeholder = "ej. Reunión con BI",
                onValueChange = { v -> onChange { it.copy(title = v) } },
            )

            FieldLabel("descripción")
            ConsoleTextField(
                value = editor.description,
                placeholder = "opcional",
                singleLine = false,
                onValueChange = { v -> onChange { it.copy(description = v) } },
            )

            FieldLabel("ubicación")
            ConsoleTextField(
                value = editor.location,
                placeholder = "opcional",
                onValueChange = { v -> onChange { it.copy(location = v) } },
            )

            // Todo el día
            Row(verticalAlignment = Alignment.CenterVertically) {
                Text("❯ todo el día", color = CF_Text, fontFamily = mono, fontSize = 13.sp, modifier = Modifier.weight(1f))
                Switch(
                    checked = editor.allDay,
                    onCheckedChange = { c -> onChange { it.copy(allDay = c) } },
                    colors = SwitchDefaults.colors(
                        checkedThumbColor = Color.White,
                        checkedTrackColor = CF_Cyan,
                        checkedBorderColor = CF_Cyan,
                        uncheckedThumbColor = CF_Dim,
                        uncheckedTrackColor = Color.Transparent,
                        uncheckedBorderColor = CF_Dim.copy(alpha = 0.5f),
                    ),
                )
            }

            FieldLabel("inicio")
            DateTimeRow(
                millis = editor.startMillis,
                showTime = !editor.allDay,
                onDate = { datePicker = PickTarget.START },
                onTime = { timePicker = PickTarget.START },
            )

            FieldLabel("fin")
            DateTimeRow(
                millis = editor.endMillis,
                showTime = !editor.allDay,
                onDate = { datePicker = PickTarget.END },
                onTime = { timePicker = PickTarget.END },
            )

            // Recordatorio
            FieldLabel("recordatorio")
            Box {
                ConsoleSelector(
                    text = reminderLabel(editor.reminderMinutes),
                    onClick = { reminderOpen = true },
                )
                DropdownMenu(
                    expanded = reminderOpen,
                    onDismissRequest = { reminderOpen = false },
                    modifier = Modifier.background(Color(0xFF120A26)),
                ) {
                    REMINDER_OPTIONS.forEach { opt ->
                        DropdownMenuItem(
                            text = { Text(reminderLabel(opt), color = CF_Text, fontFamily = mono, fontSize = 12.sp) },
                            onClick = {
                                onChange { it.copy(reminderMinutes = opt) }
                                reminderOpen = false
                            },
                        )
                    }
                }
            }

            Spacer(Modifier.height(4.dp))
            ActionPill(
                label = when {
                    saving -> "guardando…"
                    editor.isEditing -> "❯ actualizar evento"
                    else -> "❯ crear evento"
                },
                accent = if (editor.isValid) CF_Cyan else CF_Dim,
                modifier = Modifier.fillMaxWidth(),
                onClick = { if (editor.isValid && !saving) onSave() },
            )
        }
    }

    // ── Diálogos de fecha/hora ──
    datePicker?.let { target ->
        val current = if (target == PickTarget.START) editor.startMillis else editor.endMillis
        val dpState = rememberDatePickerState(
            initialSelectedDateMillis = localDateToUtcMillis(current)
        )
        DatePickerDialog(
            onDismissRequest = { datePicker = null },
            confirmButton = {
                TextButton(onClick = {
                    dpState.selectedDateMillis?.let { picked ->
                        val parts = localParts(current)
                        val combined = combineDateTime(picked, parts[3], parts[4])
                        applyDateTime(target, combined, onChange)
                    }
                    datePicker = null
                }) { Text("OK", color = CF_Cyan, fontFamily = mono) }
            },
            dismissButton = {
                TextButton(onClick = { datePicker = null }) { Text("cancelar", color = CF_Dim, fontFamily = mono) }
            },
        ) {
            DatePicker(state = dpState)
        }
    }

    timePicker?.let { target ->
        val current = if (target == PickTarget.START) editor.startMillis else editor.endMillis
        val parts = localParts(current)
        val tpState = rememberTimePickerState(initialHour = parts[3], initialMinute = parts[4], is24Hour = false)
        AlertDialog(
            onDismissRequest = { timePicker = null },
            containerColor = Color(0xFF120A26),
            confirmButton = {
                TextButton(onClick = {
                    val dateMillis = localDateToUtcMillis(current)
                    val combined = combineDateTime(dateMillis, tpState.hour, tpState.minute)
                    applyDateTime(target, combined, onChange)
                    timePicker = null
                }) { Text("OK", color = CF_Cyan, fontFamily = mono) }
            },
            dismissButton = {
                TextButton(onClick = { timePicker = null }) { Text("cancelar", color = CF_Dim, fontFamily = mono) }
            },
            text = {
                Box(Modifier.fillMaxWidth(), contentAlignment = Alignment.Center) {
                    TimePicker(state = tpState)
                }
            },
        )
    }
}

/** Aplica una nueva fecha/hora al campo, manteniendo fin >= inicio. */
private fun applyDateTime(
    target: PickTarget,
    millis: Long,
    onChange: ((EventEditor) -> EventEditor) -> Unit,
) {
    onChange { e ->
        if (target == PickTarget.START) {
            val newEnd = if (e.endMillis < millis) millis + 3_600_000L else e.endMillis
            e.copy(startMillis = millis, endMillis = newEnd)
        } else {
            e.copy(endMillis = if (millis < e.startMillis) e.startMillis else millis)
        }
    }
}

// ─── Piezas reutilizables del editor ──────────────────────────────
@Composable
private fun FieldLabel(text: String) {
    Text("$ $text", color = CF_Dim, fontFamily = mono, fontSize = 11.sp)
}

@Composable
private fun ConsoleTextField(
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
private fun ConsoleSelector(text: String, onClick: () -> Unit) {
    Row(
        modifier = Modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(10.dp))
            .background(CF_Cyan.copy(alpha = 0.06f))
            .border(1.dp, CF_Cyan.copy(alpha = 0.4f), RoundedCornerShape(10.dp))
            .clickable(onClick = onClick)
            .padding(horizontal = 12.dp, vertical = 12.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Text(text, color = CF_Text, fontFamily = mono, fontSize = 13.sp, modifier = Modifier.weight(1f))
        Icon(Icons.Default.Edit, null, tint = CF_Cyan.copy(alpha = 0.7f), modifier = Modifier.size(14.dp))
    }
}

@Composable
private fun DateTimeRow(millis: Long, showTime: Boolean, onDate: () -> Unit, onTime: () -> Unit) {
    Row(horizontalArrangement = Arrangement.spacedBy(10.dp)) {
        ConsoleChip(
            text = formatMillis(millis, "EEE d MMM yyyy").replaceFirstChar { it.uppercase() },
            icon = Icons.Default.CalendarMonth,
            modifier = Modifier.weight(if (showTime) 1.6f else 1f),
            onClick = onDate,
        )
        if (showTime) {
            ConsoleChip(
                text = formatMillis(millis, "HH:mm"),
                icon = Icons.Default.Schedule,
                modifier = Modifier.weight(1f),
                onClick = onTime,
            )
        }
    }
}

@Composable
private fun ConsoleChip(
    text: String,
    icon: androidx.compose.ui.graphics.vector.ImageVector,
    modifier: Modifier = Modifier,
    onClick: () -> Unit,
) {
    Row(
        modifier = modifier
            .clip(RoundedCornerShape(10.dp))
            .background(CF_Cyan.copy(alpha = 0.06f))
            .border(1.dp, CF_Cyan.copy(alpha = 0.4f), RoundedCornerShape(10.dp))
            .clickable(onClick = onClick)
            .padding(horizontal = 10.dp, vertical = 11.dp),
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(6.dp),
    ) {
        Icon(icon, null, tint = CF_Cyan, modifier = Modifier.size(14.dp))
        Text(text, color = CF_Text, fontFamily = mono, fontSize = 12.sp, maxLines = 1)
    }
}

@Composable
private fun SheetHandle(accent: Color) {
    Box(
        modifier = Modifier
            .padding(vertical = 10.dp)
            .size(width = 36.dp, height = 4.dp)
            .clip(RoundedCornerShape(2.dp))
            .background(accent.copy(alpha = 0.5f))
    )
}

// ─── Helpers de presentación ──────────────────────────────────────
private fun timeLabel(event: AgendaEvent): String {
    if (event.all_day) return "Todo el día"
    val start = event.start_date?.let { formatMillisFromIso(it, "HH:mm") } ?: return ""
    val end = event.end_date?.let { formatMillisFromIso(it, "HH:mm") }
    return if (end != null) "$start – $end" else start
}

private fun formatMillisFromIso(iso: String, pattern: String): String? =
    com.salvadorva.asistente.util.isoToDate(iso)?.let {
        formatMillis(it.time, pattern)
    }

private fun parseColor(hex: String?, fallback: Color): Color {
    if (hex.isNullOrBlank()) return fallback
    return try {
        Color(android.graphics.Color.parseColor(hex))
    } catch (_: Exception) {
        fallback
    }
}
