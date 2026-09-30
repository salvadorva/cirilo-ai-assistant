package com.salvadorva.asistente.ui.reminders

import kotlinx.coroutines.launch
import com.salvadorva.asistente.reminders.ReminderAudioPlayer
import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
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
import androidx.compose.ui.window.Dialog
import androidx.compose.ui.window.DialogProperties
import androidx.lifecycle.viewmodel.compose.viewModel
import com.salvadorva.asistente.reminders.ReminderDetail
import com.salvadorva.asistente.reminders.ReminderPushParser
import com.salvadorva.asistente.ui.theme.*
import java.text.SimpleDateFormat
import java.util.Locale
import java.util.TimeZone

private val mono = FontFamily.Monospace

/** Detalle de un recordatorio contextual en lenguaje Cyber Console. */
@Composable
fun ContextualReminderDialog(
    reminderId: String,
    openSnooze: Boolean,
    onDismiss: () -> Unit,
    viewModel: ContextualReminderViewModel = viewModel(key = "reminder-$reminderId"),
) {
    LaunchedEffect(reminderId, openSnooze) { viewModel.open(reminderId, openSnooze) }
    val state by viewModel.state.collectAsState()

    Dialog(onDismissRequest = onDismiss, properties = DialogProperties(usePlatformDefaultWidth = false)) {
        Column(
            modifier = Modifier
                .padding(16.dp)
                .fillMaxWidth()
                .clip(RoundedCornerShape(18.dp))
                .background(Brush.radialGradient(listOf(CF_BgGrad1, CF_Bg), radius = 1400f))
                .border(1.dp, CF_Border, RoundedCornerShape(18.dp))
                .verticalScroll(rememberScrollState())
                .padding(18.dp),
            verticalArrangement = Arrangement.spacedBy(12.dp),
        ) {
            Text("> cirilo: recordatorio acordado", color = CF_Purple, fontFamily = mono, fontSize = 12.sp)

            when (val s = state) {
                ReminderUiState.Loading -> Box(Modifier.fillMaxWidth().padding(24.dp), Alignment.Center) {
                    CircularProgressIndicator(color = CF_Cyan, strokeWidth = 2.dp)
                }
                is ReminderUiState.Error -> {
                    Text(s.message, color = CF_Text, fontSize = 14.sp)
                    CloseRow(onDismiss)
                }
                is ReminderUiState.Loaded -> LoadedContent(s, viewModel, onDismiss)
            }
        }
    }
}

@Composable
private fun LoadedContent(s: ReminderUiState.Loaded, vm: ContextualReminderViewModel, onDismiss: () -> Unit) {
    val d = s.detail
    Text(d.title.orEmpty(), color = CF_Text, fontSize = 20.sp, fontWeight = FontWeight.Bold)
    StatusLine(d)

    d.context?.takeIf { it.isNotBlank() }?.let {
        Label("// contexto")
        Text(it, color = CF_Text, fontSize = 14.sp)
    }
    d.next_action?.takeIf { it.isNotBlank() }?.let {
        Label("// siguiente acción")
        Text("> $it", color = CF_Cyan, fontFamily = mono, fontSize = 14.sp)
    }

    if (d.isPending && d.audio_ready == true) ListenButton(d.id)

    s.notice?.let {
        Text(
            it,
            color = if (s.confirmed) CF_Green else CF_Pink,
            fontFamily = mono,
            fontSize = 12.sp,
        )
    }

    val actions = d.actions
    if (d.isPending && actions != null) {
        Row(horizontalArrangement = Arrangement.spacedBy(8.dp), modifier = Modifier.fillMaxWidth()) {
            if (actions.complete) ActionButton("Hecho", CF_Green, !s.busy, Modifier.weight(1f), vm::complete)
            if (s.snoozeOptions.isNotEmpty()) ActionButton("Posponer", CF_Purple, !s.busy, Modifier.weight(1f), vm::toggleSnooze)
            if (actions.cancel) ActionButton("Cancelar", CF_Pink, !s.busy, Modifier.weight(1f), vm::cancel)
        }
        if (s.snoozeOpen) {
            Label("// posponer")
            Row(horizontalArrangement = Arrangement.spacedBy(8.dp), modifier = Modifier.fillMaxWidth()) {
                s.snoozeOptions.forEach { m ->
                    ActionButton("$m min", CF_Cyan, !s.busy, Modifier.weight(1f)) { vm.snooze(m) }
                }
            }
        }
        if (s.busy) Text("> enviando…", color = CF_Dim, fontFamily = mono, fontSize = 12.sp)
    }

    CloseRow(onDismiss)
}

/** Audio bajo petición: nunca se reproduce solo; se detiene al cerrar el diálogo. */
@Composable
private fun ListenButton(reminderId: String) {
    val context = androidx.compose.ui.platform.LocalContext.current
    val scope = androidx.compose.runtime.rememberCoroutineScope()
    var playing by androidx.compose.runtime.remember { androidx.compose.runtime.mutableStateOf(false) }
    var failed by androidx.compose.runtime.remember { androidx.compose.runtime.mutableStateOf(false) }
    androidx.compose.runtime.DisposableEffect(reminderId) { onDispose { ReminderAudioPlayer.stop() } }
    ActionButton(if (playing) "■ Detener" else "▶ Escuchar", CF_Cyan, true, Modifier.fillMaxWidth()) {
        if (playing) {
            ReminderAudioPlayer.stop(); playing = false
        } else {
            scope.launch {
                failed = false
                playing = runCatching { ReminderAudioPlayer.play(context, reminderId) { playing = false } }.getOrDefault(false)
                failed = !playing
            }
        }
    }
    if (failed) Text("> el audio no está disponible; el texto sigue siendo el recordatorio", color = CF_Dim, fontFamily = mono, fontSize = 11.sp)
}

@Composable
private fun StatusLine(d: ReminderDetail) {
    val state = when (d.state) {
        "pending" -> "PENDIENTE"
        "completed" -> "HECHO"
        "cancelled" -> "CANCELADO"
        "expired" -> "CADUCADO"
        else -> d.state.uppercase()
    }
    val window = "${localTime(d.scheduled_at, d.timezone)} → ${localTime(d.expires_at, d.timezone)}"
    Text("[$state] $window · v${d.version}", color = CF_Dim, fontFamily = mono, fontSize = 11.sp)
}

@Composable
private fun Label(text: String) = Text(text, color = CF_Dim, fontFamily = mono, fontSize = 11.sp)

@Composable
private fun ActionButton(label: String, color: Color, enabled: Boolean, modifier: Modifier, onClick: () -> Unit) {
    OutlinedButton(
        onClick = onClick,
        enabled = enabled,
        modifier = modifier,
        border = BorderStroke(1.dp, if (enabled) color else CF_Dim),
        colors = ButtonDefaults.outlinedButtonColors(contentColor = color),
        contentPadding = PaddingValues(horizontal = 6.dp, vertical = 8.dp),
    ) {
        Text(label, fontFamily = mono, fontSize = 13.sp, maxLines = 1)
    }
}

@Composable
private fun CloseRow(onDismiss: () -> Unit) {
    Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.End) {
        TextButton(onClick = onDismiss) { Text("cerrar", color = CF_Dim, fontFamily = mono) }
    }
}

private fun localTime(utc: String?, timezone: String?): String {
    val millis = ReminderPushParser.parseUtc(utc) ?: return "—"
    val format = SimpleDateFormat("EEE HH:mm", Locale.forLanguageTag("es-GT"))
    format.timeZone = TimeZone.getTimeZone(timezone ?: "America/Guatemala")
    return format.format(millis)
}
