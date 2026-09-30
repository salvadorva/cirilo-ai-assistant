package com.salvadorva.asistente.ui.chat

import android.Manifest
import android.content.ContentValues
import android.content.pm.PackageManager
import android.graphics.Bitmap
import android.graphics.drawable.BitmapDrawable
import android.provider.MediaStore
import android.widget.Toast
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.PickVisualMediaRequest
import androidx.activity.result.contract.ActivityResultContracts
import coil.ImageLoader
import coil.request.ImageRequest
import coil.request.SuccessResult
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext
import androidx.compose.animation.core.*
import androidx.compose.foundation.Canvas
import androidx.compose.foundation.Image
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.BasicTextField
import androidx.compose.foundation.text.KeyboardActions
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.KeyboardArrowLeft
import androidx.compose.material.icons.filled.AttachFile
import androidx.compose.material.icons.filled.AutoFixHigh
import androidx.compose.material.icons.filled.Close
import androidx.compose.material.icons.filled.Description
import androidx.compose.material.icons.filled.Checklist
import androidx.compose.material.icons.filled.EventAvailable
import androidx.compose.material.icons.filled.Image as IconImage
import androidx.compose.material.icons.filled.Mic
import androidx.compose.material.icons.filled.RecordVoiceOver
import androidx.compose.material.icons.filled.Send
import androidx.compose.material.icons.filled.VolumeUp
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.draw.rotate
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.geometry.Size
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.graphicsLayer
import androidx.compose.ui.graphics.drawscope.translate
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.platform.LocalView
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.text.SpanStyle
import androidx.compose.ui.text.buildAnnotatedString
import androidx.compose.ui.text.font.FontFamily
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.ImeAction
import androidx.compose.ui.text.withStyle
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.core.content.ContextCompat
import androidx.lifecycle.viewmodel.compose.viewModel
import coil.compose.AsyncImage
import com.salvadorva.asistente.R
import com.salvadorva.asistente.navigation.DeepLink
import com.salvadorva.asistente.network.models.ChatMessage
import com.salvadorva.asistente.ui.theme.*
import kotlinx.coroutines.delay
import kotlin.math.abs
import kotlin.math.sin

private val mono = FontFamily.Monospace
private val sans = FontFamily.SansSerif

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun ChatScreen(
    focusMessage: DeepLink.Focus? = null,
    onFocusConsumed: () -> Unit = {},
    viewModel: ChatViewModel = viewModel(),
) {
    val state by viewModel.state.collectAsState()
    val context = LocalContext.current

    // Mensaje de enfoque llegado por deep link (tap en la notificación push)
    LaunchedEffect(focusMessage) {
        focusMessage?.let {
            viewModel.playFocusMessage(it.text, it.audioUrl)
            onFocusConsumed()
        }
    }

    // En modo conversación mantenemos la pantalla encendida: si se apaga por
    // inactividad, el SpeechRecognizer deja de funcionar y se corta el loop.
    val view = LocalView.current
    DisposableEffect(state.conversationMode) {
        view.keepScreenOn = state.conversationMode
        onDispose { view.keepScreenOn = false }
    }

    val micPermissionLauncher = rememberLauncherForActivityResult(
        contract = ActivityResultContracts.RequestPermission(),
    ) { granted ->
        if (granted) viewModel.startListening()
    }

    val onMicTap: () -> Unit = {
        val granted = ContextCompat.checkSelfPermission(
            context, Manifest.permission.RECORD_AUDIO
        ) == PackageManager.PERMISSION_GRANTED
        if (granted) {
            viewModel.startListening()
        } else {
            micPermissionLauncher.launch(Manifest.permission.RECORD_AUDIO)
        }
    }

    val convPermissionLauncher = rememberLauncherForActivityResult(
        contract = ActivityResultContracts.RequestPermission(),
    ) { granted ->
        if (granted) viewModel.setConversationMode(true)
    }

    val onConversationToggle: () -> Unit = {
        if (state.conversationMode) {
            viewModel.setConversationMode(false)
        } else {
            val granted = ContextCompat.checkSelfPermission(
                context, Manifest.permission.RECORD_AUDIO
            ) == PackageManager.PERMISSION_GRANTED
            if (granted) {
                viewModel.setConversationMode(true)
            } else {
                convPermissionLauncher.launch(Manifest.permission.RECORD_AUDIO)
            }
        }
    }

    val imagePicker = rememberLauncherForActivityResult(
        contract = ActivityResultContracts.PickVisualMedia(),
    ) { uri ->
        uri?.let { viewModel.stageImage(it) }
    }

    val editImagePicker = rememberLauncherForActivityResult(
        contract = ActivityResultContracts.PickVisualMedia(),
    ) { uri ->
        uri?.let { viewModel.stageImageForEdit(it) }
    }

    val documentPicker = rememberLauncherForActivityResult(
        contract = ActivityResultContracts.OpenDocument(),
    ) { uri ->
        uri?.let { viewModel.stageDocument(it) }
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
        GridFloor()

        Column(modifier = Modifier.fillMaxSize()) {
            TopBar()

            Spacer(Modifier.height(4.dp))

            CiriloAvatar(status = state.status)

            StatusLabel(status = state.status)

            Spacer(Modifier.height(14.dp))

            TerminalLog(
                items = state.items,
                status = state.status,
                modifier = Modifier
                    .weight(1f)
                    .padding(horizontal = 14.dp),
            )

            state.errorMessage?.let { err ->
                Text(
                    text = "// error: $err",
                    color = CF_Pink,
                    fontFamily = mono,
                    fontSize = 11.sp,
                    modifier = Modifier
                        .fillMaxWidth()
                        .padding(horizontal = 18.dp, vertical = 4.dp),
                )
            }

            // Chip de attachment staged sobre el bottom bar
            state.staged?.let { staged ->
                StagedChip(staged = staged, onClear = viewModel::clearStaged)
            }
            if (state.attachingDocument) {
                Text(
                    text = "// extracting document text…",
                    color = CF_Cyan,
                    fontFamily = mono,
                    fontSize = 11.sp,
                    modifier = Modifier
                        .fillMaxWidth()
                        .padding(horizontal = 18.dp, vertical = 4.dp),
                )
            }

            if (state.conversationMode) {
                ConversationBanner()
            }

            BottomActionBar(
                inputText = state.inputText,
                onInputChange = viewModel::updateInput,
                onSend = viewModel::sendMessage,
                onMicTap = onMicTap,
                onAttachTap = viewModel::showAttachSheet,
                onConversationToggle = onConversationToggle,
                status = state.status,
                hasStaged = state.staged != null,
                conversationMode = state.conversationMode,
            )
        }
    }

    if (state.showAttachSheet) {
        AttachSheet(
            onDismiss = viewModel::dismissAttachSheet,
            onPickImage = {
                viewModel.dismissAttachSheet()
                imagePicker.launch(
                    PickVisualMediaRequest(ActivityResultContracts.PickVisualMedia.ImageOnly)
                )
            },
            onEditImage = {
                viewModel.dismissAttachSheet()
                editImagePicker.launch(
                    PickVisualMediaRequest(ActivityResultContracts.PickVisualMedia.ImageOnly)
                )
            },
            onPickDocument = {
                viewModel.dismissAttachSheet()
                documentPicker.launch(
                    arrayOf("text/plain", "text/markdown", "application/pdf", "text/*")
                )
            },
        )
    }
}

// ─── Top bar terminal ────────────────────────────────────────────
@Composable
private fun TopBar() {
    Row(
        modifier = Modifier
            .fillMaxWidth()
            .padding(horizontal = 14.dp, vertical = 12.dp),
        horizontalArrangement = Arrangement.SpaceBetween,
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Row(verticalAlignment = Alignment.CenterVertically) {
            Icon(
                Icons.AutoMirrored.Filled.KeyboardArrowLeft,
                contentDescription = null,
                tint = CF_Cyan,
                modifier = Modifier.size(18.dp),
            )
            Text(
                "cd ..",
                color = CF_Cyan,
                fontFamily = mono,
                fontSize = 11.sp,
            )
        }
        Text(
            buildAnnotatedString {
                withStyle(SpanStyle(
                    brush = Brush.linearGradient(listOf(CF_Cyan, CF_Purple)),
                    fontWeight = FontWeight.Bold,
                )) {
                    append("◆ CIRILO.OS")
                }
            },
            fontFamily = mono,
            fontSize = 11.sp,
            letterSpacing = 3.sp,
        )
        Text(
            "● online",
            color = CF_Green.copy(alpha = 0.85f),
            fontFamily = mono,
            fontSize = 11.sp,
        )
    }
}

// ─── Grid floor en perspectiva ───────────────────────────────────
@Composable
private fun GridFloor() {
    Canvas(modifier = Modifier.fillMaxSize()) {
        val gridSize = 38.dp.toPx()
        val height = size.height
        val width = size.width
        val floorHeight = 260.dp.toPx()
        val floorTop = height - floorHeight

        translate(top = floorTop) {
            var y = 0f
            while (y < floorHeight) {
                val alpha = (y / floorHeight).coerceIn(0f, 1f) * 0.5f
                drawLine(
                    color = CF_GridCyan.copy(alpha = alpha),
                    start = Offset(-width * 0.2f, y),
                    end = Offset(width * 1.2f, y),
                    strokeWidth = 1.dp.toPx(),
                )
                y += gridSize
            }
            val centerX = width / 2f
            var i = -10
            while (i <= 10) {
                val baseX = centerX + i * gridSize
                drawLine(
                    color = CF_GridPurple.copy(alpha = 0.4f),
                    start = Offset(centerX + i * gridSize * 0.2f, 0f),
                    end = Offset(baseX, floorHeight),
                    strokeWidth = 1.dp.toPx(),
                )
                i++
            }
        }
    }
}

// ─── Avatar con conic ring + halo + HUD ticks ────────────────────
@Composable
private fun CiriloAvatar(status: ChatStatus) {
    val infinite = rememberInfiniteTransition(label = "ring")
    val rotation by infinite.animateFloat(
        initialValue = 0f,
        targetValue = 360f,
        animationSpec = infiniteRepeatable(
            animation = tween(
                durationMillis = if (status == ChatStatus.Thinking) 2500 else 4000,
                easing = LinearEasing,
            ),
            repeatMode = RepeatMode.Restart,
        ),
        label = "rot",
    )

    val statusColor = when (status) {
        ChatStatus.Ready -> CF_Dim
        ChatStatus.Listening -> CF_Green
        ChatStatus.Thinking -> CF_Pink
        ChatStatus.Speaking -> CF_Cyan
    }

    Box(
        contentAlignment = Alignment.Center,
        modifier = Modifier
            .fillMaxWidth()
            .padding(top = 4.dp)
    ) {
        Box(
            modifier = Modifier.size(168.dp),
            contentAlignment = Alignment.Center,
        ) {
            Box(
                modifier = Modifier
                    .fillMaxSize()
                    .rotate(rotation)
                    .clip(CircleShape)
                    .background(
                        Brush.sweepGradient(
                            colors = listOf(
                                Color.Transparent,
                                CF_Cyan,
                                Color.Transparent,
                                CF_Purple,
                                Color.Transparent,
                            )
                        )
                    )
            )
            Box(
                modifier = Modifier
                    .size(152.dp)
                    .clip(CircleShape)
                    .border(1.dp, CF_Cyan.copy(alpha = 0.5f), CircleShape)
                    .background(CF_Bg)
            )
            Image(
                painter = painterResource(id = R.drawable.cirilo_face),
                contentDescription = "Cirilo",
                contentScale = ContentScale.Crop,
                modifier = Modifier
                    .size(136.dp)
                    .clip(CircleShape)
                    .border(2.dp, CF_Cyan, CircleShape),
            )
            HudTicks(color = statusColor)
        }
    }
}

@Composable
private fun HudTicks(color: Color) {
    Canvas(modifier = Modifier.size(192.dp)) {
        val center = Offset(size.width / 2f, size.height / 2f)
        val radius = 88.dp.toPx()
        val tickHeight = 14.dp.toPx()
        val tickWidth = 4.dp.toPx()

        for (deg in listOf(0, 90, 180, 270)) {
            val rad = Math.toRadians(deg.toDouble())
            val cx = center.x + (kotlin.math.sin(rad) * radius).toFloat()
            val cy = center.y - (kotlin.math.cos(rad) * radius).toFloat()
            drawRect(
                color = color,
                topLeft = Offset(cx - tickWidth / 2f, cy - tickHeight / 2f),
                size = Size(tickWidth, tickHeight),
            )
        }
    }
}

// ─── Status label "CIRILO_" + STATE ──────────────────────────────
@Composable
private fun StatusLabel(status: ChatStatus) {
    val statusText = when (status) {
        ChatStatus.Ready -> "STDBY"
        ChatStatus.Listening -> "LISTENING"
        ChatStatus.Thinking -> "PROCESSING"
        ChatStatus.Speaking -> "SPEAKING"
    }
    val statusColor = when (status) {
        ChatStatus.Ready -> CF_Dim
        ChatStatus.Listening -> CF_Green
        ChatStatus.Thinking -> CF_Pink
        ChatStatus.Speaking -> CF_Cyan
    }

    Column(
        modifier = Modifier
            .fillMaxWidth()
            .padding(top = 14.dp),
        horizontalAlignment = Alignment.CenterHorizontally,
    ) {
        Text(
            buildAnnotatedString {
                withStyle(SpanStyle(
                    brush = Brush.linearGradient(listOf(Color.White, Color(0xFFC4B5FD))),
                    fontWeight = FontWeight.Bold,
                )) {
                    append("CIRILO_")
                }
            },
            fontFamily = sans,
            fontSize = 22.sp,
            letterSpacing = (-0.4).sp,
        )
        Spacer(Modifier.height(2.dp))
        Row {
            Text("▸ STATE: ",
                color = CF_Cyan,
                fontFamily = mono,
                fontSize = 10.5.sp,
                letterSpacing = 2.5.sp,
            )
            Text(statusText,
                color = statusColor,
                fontFamily = mono,
                fontSize = 10.5.sp,
                letterSpacing = 2.5.sp,
                fontWeight = FontWeight.Bold,
            )
            Text(" ◂",
                color = CF_Cyan,
                fontFamily = mono,
                fontSize = 10.5.sp,
                letterSpacing = 2.5.sp,
            )
        }
    }
}

// ─── Terminal log ────────────────────────────────────────────────
@Composable
private fun TerminalLog(
    items: List<ChatItem>,
    status: ChatStatus,
    modifier: Modifier = Modifier,
) {
    Column(
        modifier = modifier
            .clip(RoundedCornerShape(10.dp))
            .background(CF_Panel)
            .border(1.dp, CF_Border, RoundedCornerShape(10.dp))
    ) {
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .padding(horizontal = 10.dp, vertical = 6.dp),
            horizontalArrangement = Arrangement.SpaceBetween,
            verticalAlignment = Alignment.CenterVertically,
        ) {
            Text(
                "// live_transcript.log",
                color = CF_Dim,
                fontFamily = mono,
                fontSize = 10.sp,
            )
            Text(
                when (status) {
                    ChatStatus.Listening -> "● REC"
                    ChatStatus.Thinking -> "◇ proc"
                    ChatStatus.Speaking -> "▶ play"
                    ChatStatus.Ready -> "○ idle"
                },
                color = when (status) {
                    ChatStatus.Listening -> CF_Pink
                    ChatStatus.Thinking -> CF_Purple
                    ChatStatus.Speaking -> CF_Cyan
                    ChatStatus.Ready -> CF_Dim
                },
                fontFamily = mono,
                fontSize = 10.sp,
            )
        }
        HorizontalDivider(color = CF_Border, thickness = 1.dp)

        val scrollState = rememberScrollState()
        LaunchedEffect(items.size) {
            scrollState.animateScrollTo(scrollState.maxValue)
        }

        Column(
            modifier = Modifier
                .weight(1f)
                .verticalScroll(scrollState)
                .padding(horizontal = 12.dp, vertical = 10.dp),
            verticalArrangement = Arrangement.spacedBy(8.dp),
        ) {
            if (items.isEmpty()) {
                Text(
                    "> system: ready_",
                    color = CF_Dim,
                    fontFamily = mono,
                    fontSize = 12.sp,
                )
                Text(
                    "// escribe abajo o tocá 📎 para adjuntar imagen o documento",
                    color = CF_Dim.copy(alpha = 0.6f),
                    fontFamily = mono,
                    fontSize = 11.sp,
                )
            }
            items.forEach { item ->
                when (item) {
                    is ChatItem.Text -> TerminalMessage(item.msg)
                    is ChatItem.UserWithImage -> UserImageItem(item)
                    is ChatItem.UserWithDocument -> UserDocumentItem(item)
                    is ChatItem.AssistantImage -> AssistantImageItem(item)
                    is ChatItem.UserImageEdit -> UserImageEditItem(item)
                    is ChatItem.EventCreated -> EventCreatedItem(item)
                    is ChatItem.AgendaCandidates -> AgendaCandidatesItem(item)
                    is ChatItem.TaskSaved -> TaskSavedItem(item)
                }
            }
            if (status == ChatStatus.Listening) ListeningLine()
            if (status == ChatStatus.Thinking) ThinkingLine()
        }

        HorizontalDivider(color = CF_Border, thickness = 1.dp)
        Waveform(active = status != ChatStatus.Ready, modifier = Modifier
            .fillMaxWidth()
            .padding(horizontal = 12.dp, vertical = 8.dp)
            .height(28.dp))
    }
}

@Composable
private fun TerminalMessage(msg: ChatMessage) {
    val prefix = if (msg.role == "user") "> user:" else "> cirilo:"
    val prefixColor = if (msg.role == "user") CF_Cyan else CF_Purple
    val bodyColor = if (msg.role == "user") CF_GreenSoft else CF_Text

    Text(
        buildAnnotatedString {
            withStyle(SpanStyle(color = prefixColor, fontWeight = FontWeight.Bold)) {
                append("$prefix ")
            }
            withStyle(SpanStyle(color = bodyColor)) {
                append(msg.content)
            }
        },
        fontFamily = mono,
        fontSize = 12.sp,
        lineHeight = 18.sp,
    )
}

@Composable
private fun UserImageItem(item: ChatItem.UserWithImage) {
    Column(verticalArrangement = Arrangement.spacedBy(6.dp)) {
        Text(
            buildAnnotatedString {
                withStyle(SpanStyle(color = CF_Cyan, fontWeight = FontWeight.Bold)) {
                    append("> user: ")
                }
                withStyle(SpanStyle(color = CF_GreenSoft)) {
                    append(item.text)
                }
            },
            fontFamily = mono,
            fontSize = 12.sp,
            lineHeight = 18.sp,
        )
        AsyncImage(
            model = item.localUri,
            contentDescription = null,
            contentScale = ContentScale.Crop,
            modifier = Modifier
                .size(120.dp)
                .clip(RoundedCornerShape(8.dp))
                .border(1.dp, CF_Cyan.copy(alpha = 0.5f), RoundedCornerShape(8.dp)),
        )
    }
}

@Composable
private fun UserImageEditItem(item: ChatItem.UserImageEdit) {
    Column(verticalArrangement = Arrangement.spacedBy(6.dp)) {
        Text(
            buildAnnotatedString {
                withStyle(SpanStyle(color = CF_Cyan, fontWeight = FontWeight.Bold)) { append("> user: ") }
                withStyle(SpanStyle(color = CF_Text)) { append("editar imagen: ${item.instruction}") }
            },
            fontFamily = mono,
            fontSize = 12.sp,
        )
        AsyncImage(
            model = item.localUri,
            contentDescription = null,
            contentScale = ContentScale.Crop,
            modifier = Modifier
                .size(96.dp)
                .clip(RoundedCornerShape(8.dp))
                .border(1.dp, CF_Cyan.copy(alpha = 0.5f), RoundedCornerShape(8.dp)),
        )
    }
}

@Composable
private fun AssistantImageItem(item: ChatItem.AssistantImage) {
    val context = LocalContext.current
    val scope = rememberCoroutineScope()
    var saving by remember { mutableStateOf(false) }

    Column(verticalArrangement = Arrangement.spacedBy(6.dp)) {
        Text(
            buildAnnotatedString {
                withStyle(SpanStyle(color = CF_Purple, fontWeight = FontWeight.Bold)) {
                    append("> cirilo: ")
                }
                withStyle(SpanStyle(color = CF_Dim)) {
                    append(if (item.edited) "imagen editada ↓ (disponible 7 días)" else "imagen generada ↓")
                }
            },
            fontFamily = mono,
            fontSize = 12.sp,
        )
        AsyncImage(
            model = item.url,
            contentDescription = null,
            contentScale = ContentScale.FillWidth,
            modifier = Modifier
                .fillMaxWidth()
                .heightIn(max = 320.dp)
                .clip(RoundedCornerShape(8.dp))
                .border(1.dp, CF_Purple.copy(alpha = 0.5f), RoundedCornerShape(8.dp)),
        )
        item.promptUsed?.takeIf { it.isNotBlank() }?.let { p ->
            val short = if (p.length > 70) p.take(70) + "…" else p
            Text(
                "$ gpt-image-1: \"$short\"",
                color = CF_Dim,
                fontFamily = mono,
                fontSize = 10.sp,
            )
        }
        // Botón de descarga Cyber Console
        Text(
            if (saving) "// guardando..." else "❯ save.image",
            color = if (saving) CF_Dim else CF_Cyan,
            fontFamily = mono,
            fontSize = 11.sp,
            modifier = Modifier
                .clickable(enabled = !saving) {
                    scope.launch {
                        saving = true
                        val ok = saveImageToGallery(context, item.url)
                        saving = false
                        Toast.makeText(
                            context,
                            if (ok) "Imagen guardada en Galería → Pictures/Cirilo" else "Error al guardar la imagen",
                            Toast.LENGTH_SHORT
                        ).show()
                    }
                }
                .padding(vertical = 2.dp),
        )
    }
}

private suspend fun saveImageToGallery(context: android.content.Context, url: String): Boolean =
    withContext(Dispatchers.IO) {
        try {
            val loader = ImageLoader(context)
            val request = ImageRequest.Builder(context).data(url).allowHardware(false).build()
            val bitmap = ((loader.execute(request) as? SuccessResult)?.drawable as? BitmapDrawable)?.bitmap
                ?: return@withContext false

            val filename = "cirilo_${System.currentTimeMillis()}.png"
            val cv = ContentValues().apply {
                put(MediaStore.Images.Media.DISPLAY_NAME, filename)
                put(MediaStore.Images.Media.MIME_TYPE, "image/png")
                put(MediaStore.Images.Media.RELATIVE_PATH, "Pictures/Cirilo")
            }
            val uri = context.contentResolver.insert(MediaStore.Images.Media.EXTERNAL_CONTENT_URI, cv)
                ?: return@withContext false
            context.contentResolver.openOutputStream(uri)?.use { out ->
                bitmap.compress(Bitmap.CompressFormat.PNG, 100, out)
            }
            true
        } catch (e: Exception) {
            false
        }
    }

@Composable
private fun UserDocumentItem(item: ChatItem.UserWithDocument) {
    Column(verticalArrangement = Arrangement.spacedBy(6.dp)) {
        Text(
            buildAnnotatedString {
                withStyle(SpanStyle(color = CF_Cyan, fontWeight = FontWeight.Bold)) {
                    append("> user: ")
                }
                withStyle(SpanStyle(color = CF_GreenSoft)) {
                    append(item.text)
                }
            },
            fontFamily = mono,
            fontSize = 12.sp,
            lineHeight = 18.sp,
        )
        Row(
            verticalAlignment = Alignment.CenterVertically,
            horizontalArrangement = Arrangement.spacedBy(8.dp),
            modifier = Modifier
                .clip(RoundedCornerShape(8.dp))
                .background(CF_Cyan.copy(alpha = 0.08f))
                .border(1.dp, CF_Cyan.copy(alpha = 0.4f), RoundedCornerShape(8.dp))
                .padding(horizontal = 10.dp, vertical = 6.dp),
        ) {
            Icon(
                Icons.Default.Description,
                contentDescription = null,
                tint = CF_Cyan,
                modifier = Modifier.size(16.dp),
            )
            val pagesLabel = if (item.pageCount == 1) "1 pág" else "${item.pageCount} págs"
            val truncLabel = if (item.truncated) " · truncado" else ""
            Text(
                "${item.filename} · $pagesLabel$truncLabel",
                color = CF_Cyan,
                fontFamily = mono,
                fontSize = 11.sp,
            )
        }
    }
}

@Composable
private fun EventCreatedItem(item: ChatItem.EventCreated) {
    val whenLabel = eventWhen(item.startDate, item.allDay) + if (item.count > 1) " · ${item.count} eventos en la serie" else ""
    Row(
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(8.dp),
        modifier = Modifier
            .clip(RoundedCornerShape(8.dp))
            .background(CF_Green.copy(alpha = 0.08f))
            .border(1.dp, CF_Green.copy(alpha = 0.45f), RoundedCornerShape(8.dp))
            .padding(horizontal = 10.dp, vertical = 8.dp),
    ) {
        Icon(Icons.Default.EventAvailable, null, tint = CF_Green, modifier = Modifier.size(16.dp))
        Column {
            Text(item.label, color = CF_Green, fontFamily = mono, fontSize = 11.sp, fontWeight = FontWeight.Bold)
            Text(item.title, color = CF_Text, fontFamily = mono, fontSize = 12.sp)
            Text(whenLabel, color = CF_Dim, fontFamily = mono, fontSize = 10.5.sp)
        }
    }
}

private fun eventWhen(startDate: String?, allDay: Boolean): String = when {
    startDate.isNullOrBlank() -> ""
    allDay -> com.salvadorva.asistente.util.formatLocalDate(startDate) + " · todo el día"
    else -> com.salvadorva.asistente.util.formatLocalDateTime(startDate)
}

/** Cirilo pregunta a cuál evento se refiere; nada se cambió todavía. */
@Composable
private fun AgendaCandidatesItem(item: ChatItem.AgendaCandidates) {
    Column(
        verticalArrangement = Arrangement.spacedBy(2.dp),
        modifier = Modifier
            .clip(RoundedCornerShape(8.dp))
            .border(1.dp, CF_Cyan.copy(alpha = 0.45f), RoundedCornerShape(8.dp))
            .padding(horizontal = 10.dp, vertical = 8.dp),
    ) {
        Text("¿cuál de estos?", color = CF_Cyan, fontFamily = mono, fontSize = 11.sp, fontWeight = FontWeight.Bold)
        item.candidates.forEach { c ->
            Text("• ${c.title}  ${eventWhen(c.startDate, c.allDay)}", color = CF_Text, fontFamily = mono, fontSize = 11.5.sp)
        }
    }
}

@Composable
private fun TaskSavedItem(item: ChatItem.TaskSaved) {
    Row(
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(8.dp),
        modifier = Modifier
            .clip(RoundedCornerShape(8.dp))
            .background(CF_Green.copy(alpha = 0.08f))
            .border(1.dp, CF_Green.copy(alpha = 0.45f), RoundedCornerShape(8.dp))
            .padding(horizontal = 10.dp, vertical = 8.dp),
    ) {
        Icon(Icons.Default.Checklist, null, tint = CF_Green, modifier = Modifier.size(16.dp))
        Column {
            Text(item.label, color = CF_Green, fontFamily = mono, fontSize = 11.sp, fontWeight = FontWeight.Bold)
            Text(item.title, color = CF_Text, fontFamily = mono, fontSize = 12.sp)
            Text(item.dueDate?.let { "fecha límite $it" } ?: "sin fecha · en la pestaña Hoy", color = CF_Dim, fontFamily = mono, fontSize = 10.5.sp)
        }
    }
}

@Composable
private fun ListeningLine() {
    Text(
        buildAnnotatedString {
            withStyle(SpanStyle(color = CF_Cyan, fontWeight = FontWeight.Bold)) {
                append("> user: ")
            }
            withStyle(SpanStyle(color = CF_GreenSoft.copy(alpha = 0.85f), fontStyle = androidx.compose.ui.text.font.FontStyle.Italic)) {
                append("…escuchando voz…")
            }
        },
        fontFamily = mono,
        fontSize = 12.sp,
    )
}

@Composable
private fun ThinkingLine() {
    var dots by remember { mutableStateOf("") }
    LaunchedEffect(Unit) {
        val seq = listOf("", ".", "..", "...")
        var i = 0
        while (true) {
            dots = seq[i % seq.size]
            i++
            delay(350)
        }
    }
    Text(
        buildAnnotatedString {
            withStyle(SpanStyle(color = CF_Purple, fontWeight = FontWeight.Bold)) {
                append("> cirilo: ")
            }
            withStyle(SpanStyle(color = CF_Dim)) {
                append("compilando bananas$dots")
            }
        },
        fontFamily = mono,
        fontSize = 12.sp,
    )
}

// ─── Waveform animado ────────────────────────────────────────────
@Composable
private fun Waveform(active: Boolean, modifier: Modifier = Modifier) {
    val infinite = rememberInfiniteTransition(label = "wave")
    val phase by infinite.animateFloat(
        initialValue = 0f,
        targetValue = (2 * Math.PI).toFloat(),
        animationSpec = infiniteRepeatable(
            animation = tween(1200, easing = LinearEasing),
            repeatMode = RepeatMode.Restart,
        ),
        label = "phase",
    )

    Canvas(modifier = modifier) {
        val barCount = 36
        val barWidth = (size.width / barCount) - 2.dp.toPx()
        val maxHeight = size.height
        for (i in 0 until barCount) {
            val color = if (i % 2 == 0) CF_Purple else CF_Cyan
            val h = if (active) {
                (4.dp.toPx() + abs(sin(i * 0.5f + phase)) * (maxHeight - 4.dp.toPx()))
            } else 3.dp.toPx()
            val x = i * (barWidth + 2.dp.toPx())
            val y = (maxHeight - h) / 2f
            drawRect(
                color = color.copy(alpha = 0.85f),
                topLeft = Offset(x, y),
                size = Size(barWidth, h),
            )
        }
    }
}

// ─── Staged attachment chip (sobre el input) ─────────────────────
@Composable
private fun StagedChip(staged: StagedAttachment, onClear: () -> Unit) {
    Row(
        modifier = Modifier
            .fillMaxWidth()
            .padding(horizontal = 14.dp, vertical = 6.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Row(
            verticalAlignment = Alignment.CenterVertically,
            horizontalArrangement = Arrangement.spacedBy(8.dp),
            modifier = Modifier
                .clip(RoundedCornerShape(12.dp))
                .background(CF_Purple.copy(alpha = 0.12f))
                .border(1.dp, CF_Purple.copy(alpha = 0.5f), RoundedCornerShape(12.dp))
                .padding(start = 6.dp, end = 4.dp, top = 4.dp, bottom = 4.dp),
        ) {
            when (staged) {
                is StagedAttachment.ImageEdit -> {
                    AsyncImage(
                        model = staged.uri,
                        contentDescription = null,
                        contentScale = ContentScale.Crop,
                        modifier = Modifier
                            .size(36.dp)
                            .clip(RoundedCornerShape(8.dp)),
                    )
                    Text(
                        "editar: escribí qué cambiar",
                        color = CF_Green,
                        fontFamily = mono,
                        fontSize = 11.sp,
                    )
                }
                is StagedAttachment.Image -> {
                    AsyncImage(
                        model = staged.uri,
                        contentDescription = null,
                        contentScale = ContentScale.Crop,
                        modifier = Modifier
                            .size(36.dp)
                            .clip(RoundedCornerShape(8.dp)),
                    )
                    Text(
                        "imagen lista",
                        color = CF_Text,
                        fontFamily = mono,
                        fontSize = 11.sp,
                    )
                }
                is StagedAttachment.Document -> {
                    Icon(
                        Icons.Default.Description,
                        contentDescription = null,
                        tint = CF_Cyan,
                        modifier = Modifier.size(18.dp),
                    )
                    val pagesLabel = if (staged.pageCount == 1) "1 pág" else "${staged.pageCount} págs"
                    Text(
                        "${staged.filename} · $pagesLabel${if (staged.truncated) " · trunc" else ""}",
                        color = CF_Text,
                        fontFamily = mono,
                        fontSize = 11.sp,
                    )
                }
            }
            Box(
                modifier = Modifier
                    .size(28.dp)
                    .clip(CircleShape)
                    .clickable(onClick = onClear),
                contentAlignment = Alignment.Center,
            ) {
                Icon(
                    Icons.Default.Close,
                    contentDescription = "quitar",
                    tint = CF_Dim,
                    modifier = Modifier.size(14.dp),
                )
            }
        }
    }
}

// ─── Bottom sheet de attach ──────────────────────────────────────
@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun AttachSheet(
    onDismiss: () -> Unit,
    onPickImage: () -> Unit,
    onEditImage: () -> Unit,
    onPickDocument: () -> Unit,
) {
    val sheetState = rememberModalBottomSheetState(skipPartiallyExpanded = true)
    ModalBottomSheet(
        onDismissRequest = onDismiss,
        sheetState = sheetState,
        containerColor = Color(0xFF0A0418),
        dragHandle = {
            Box(
                modifier = Modifier
                    .padding(vertical = 10.dp)
                    .size(width = 36.dp, height = 4.dp)
                    .clip(RoundedCornerShape(2.dp))
                    .background(CF_Purple.copy(alpha = 0.5f))
            )
        },
    ) {
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .padding(horizontal = 20.dp, vertical = 8.dp),
        ) {
            Text(
                "// attach.source",
                color = CF_Dim,
                fontFamily = mono,
                fontSize = 11.sp,
                letterSpacing = 1.5.sp,
            )
            Spacer(Modifier.height(14.dp))

            AttachOption(
                label = "❯ analyze.image",
                description = "elegí una imagen y Cirilo la analiza",
                icon = Icons.Default.IconImage,
                accent = CF_Cyan,
                onClick = onPickImage,
            )
            Spacer(Modifier.height(10.dp))
            AttachOption(
                label = "❯ edit.image",
                description = "elegí una foto y escribí qué cambiar",
                icon = Icons.Default.AutoFixHigh,
                accent = CF_Green,
                onClick = onEditImage,
            )
            // IE0: consentimiento con aviso de una línea.
            Text(
                "la foto se envía a OpenAI para editarla · el resultado se guarda 7 días",
                color = CF_Dim, fontFamily = mono, fontSize = 10.sp,
                modifier = Modifier.padding(start = 4.dp, top = 4.dp),
            )
            Spacer(Modifier.height(10.dp))
            AttachOption(
                label = "❯ read.document",
                description = ".txt · .md · .pdf (máx ${com.salvadorva.asistente.util.DocumentExtractor.MAX_PAGES} págs)",
                icon = Icons.Default.Description,
                accent = CF_Purple,
                onClick = onPickDocument,
            )
            Spacer(Modifier.height(24.dp))
        }
    }
}

@Composable
private fun AttachOption(
    label: String,
    description: String,
    icon: androidx.compose.ui.graphics.vector.ImageVector,
    accent: Color,
    onClick: () -> Unit,
) {
    Row(
        modifier = Modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(12.dp))
            .background(accent.copy(alpha = 0.06f))
            .border(1.dp, accent.copy(alpha = 0.45f), RoundedCornerShape(12.dp))
            .clickable(onClick = onClick)
            .padding(horizontal = 14.dp, vertical = 14.dp),
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(14.dp),
    ) {
        Box(
            modifier = Modifier
                .size(40.dp)
                .clip(CircleShape)
                .background(accent.copy(alpha = 0.15f))
                .border(1.dp, accent.copy(alpha = 0.5f), CircleShape),
            contentAlignment = Alignment.Center,
        ) {
            Icon(icon, contentDescription = null, tint = accent, modifier = Modifier.size(20.dp))
        }
        Column(modifier = Modifier.weight(1f)) {
            Text(label, color = accent, fontFamily = mono, fontSize = 14.sp, fontWeight = FontWeight.Bold)
            Spacer(Modifier.height(2.dp))
            Text(description, color = CF_Dim, fontFamily = mono, fontSize = 11.sp)
        }
    }
}

// ─── Bottom bar: input texto + mic + attach + send ───────────────
@Composable
private fun BottomActionBar(
    inputText: String,
    onInputChange: (String) -> Unit,
    onSend: () -> Unit,
    onMicTap: () -> Unit,
    onAttachTap: () -> Unit,
    onConversationToggle: () -> Unit,
    status: ChatStatus,
    hasStaged: Boolean,
    conversationMode: Boolean,
) {
    val listening = status == ChatStatus.Listening
    val canSend = status == ChatStatus.Ready && (inputText.isNotBlank() || hasStaged)

    Row(
        modifier = Modifier
            .fillMaxWidth()
            .padding(horizontal = 14.dp, vertical = 14.dp),
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(8.dp),
    ) {
        ConversationModeButton(active = conversationMode, onClick = onConversationToggle)
        // En modo conversación el mic/attach quedan en pausa: el loop maneja la escucha.
        if (!conversationMode) {
            MicButton(listening = listening, onClick = onMicTap)
            AttachButton(enabled = status == ChatStatus.Ready, onClick = onAttachTap)
        }

        Box(
            modifier = Modifier
                .weight(1f)
                .clip(RoundedCornerShape(12.dp))
                .background(CF_Purple.copy(alpha = 0.06f))
                .border(1.dp, CF_Purple.copy(alpha = 0.4f), RoundedCornerShape(12.dp))
                .padding(horizontal = 12.dp, vertical = 10.dp),
        ) {
            Row(verticalAlignment = Alignment.CenterVertically) {
                Text("❯ ", color = CF_Purple, fontFamily = mono, fontSize = 16.sp)
                BasicTextField(
                    value = inputText,
                    onValueChange = onInputChange,
                    enabled = status == ChatStatus.Ready,
                    singleLine = false,
                    maxLines = 3,
                    textStyle = androidx.compose.ui.text.TextStyle(
                        color = CF_Text,
                        fontFamily = mono,
                        fontSize = 13.sp,
                    ),
                    keyboardOptions = KeyboardOptions(imeAction = ImeAction.Send),
                    keyboardActions = KeyboardActions(onSend = { onSend() }),
                    cursorBrush = Brush.verticalGradient(listOf(CF_Cyan, CF_Purple)),
                    decorationBox = { inner ->
                        if (inputText.isEmpty()) {
                            Text(
                                if (hasStaged) "pregunta sobre el adjunto…" else "escribe…",
                                color = CF_Dim,
                                fontFamily = mono,
                                fontSize = 13.sp,
                            )
                        }
                        inner()
                    },
                    modifier = Modifier.weight(1f),
                )
            }
        }

        Box(
            modifier = Modifier
                .size(52.dp)
                .clip(RoundedCornerShape(12.dp))
                .background(
                    Brush.linearGradient(
                        when (status) {
                            ChatStatus.Thinking -> listOf(CF_Purple, CF_Pink)
                            ChatStatus.Speaking -> listOf(CF_Cyan, CF_Purple)
                            ChatStatus.Listening -> listOf(CF_Pink, CF_Purple)
                            ChatStatus.Ready -> listOf(CF_Purple, CF_Cyan)
                        }
                    )
                )
                .clickable(enabled = canSend) { onSend() },
            contentAlignment = Alignment.Center,
        ) {
            when (status) {
                ChatStatus.Thinking -> Text("⏳", fontSize = 18.sp)
                ChatStatus.Speaking -> Icon(Icons.Default.VolumeUp, null, tint = Color.White, modifier = Modifier.size(20.dp))
                ChatStatus.Listening -> Icon(Icons.Default.Mic, null, tint = Color.White, modifier = Modifier.size(20.dp))
                ChatStatus.Ready -> Icon(Icons.Default.Send, null, tint = Color.White, modifier = Modifier.size(20.dp))
            }
        }
    }
}

@Composable
private fun AttachButton(enabled: Boolean, onClick: () -> Unit) {
    val borderColor = if (enabled) CF_Cyan.copy(alpha = 0.4f) else CF_Dim.copy(alpha = 0.2f)
    val iconTint = if (enabled) CF_Cyan else CF_Dim.copy(alpha = 0.4f)
    Box(
        modifier = Modifier
            .size(44.dp)
            .clip(CircleShape)
            .background(CF_Cyan.copy(alpha = if (enabled) 0.08f else 0.03f))
            .border(1.dp, borderColor, CircleShape)
            .clickable(enabled = enabled, onClick = onClick),
        contentAlignment = Alignment.Center,
    ) {
        Icon(Icons.Default.AttachFile, null, tint = iconTint, modifier = Modifier.size(20.dp))
    }
}

@Composable
private fun ConversationModeButton(active: Boolean, onClick: () -> Unit) {
    val infinite = rememberInfiniteTransition(label = "conv")
    val scale by infinite.animateFloat(
        initialValue = 1f,
        targetValue = if (active) 1.08f else 1f,
        animationSpec = infiniteRepeatable(
            animation = tween(900, easing = FastOutSlowInEasing),
            repeatMode = RepeatMode.Reverse,
        ),
        label = "convScale",
    )
    val bg = if (active)
        Brush.radialGradient(listOf(CF_Green, CF_Cyan))
    else
        Brush.radialGradient(listOf(CF_Green.copy(alpha = 0.08f), CF_Green.copy(alpha = 0.08f)))
    val borderColor = if (active) CF_Green else CF_Green.copy(alpha = 0.4f)
    val iconTint = if (active) Color.White else CF_Green

    Box(
        modifier = Modifier
            .size(44.dp)
            .graphicsLayer {
                if (active) {
                    scaleX = scale
                    scaleY = scale
                }
            }
            .clip(CircleShape)
            .background(bg)
            .border(1.dp, borderColor, CircleShape)
            .clickable(onClick = onClick),
        contentAlignment = Alignment.Center,
    ) {
        Icon(Icons.Default.RecordVoiceOver, null, tint = iconTint, modifier = Modifier.size(20.dp))
    }
}

@Composable
private fun ConversationBanner() {
    Row(
        modifier = Modifier
            .fillMaxWidth()
            .padding(horizontal = 14.dp, vertical = 4.dp)
            .clip(RoundedCornerShape(10.dp))
            .background(CF_Green.copy(alpha = 0.08f))
            .border(1.dp, CF_Green.copy(alpha = 0.4f), RoundedCornerShape(10.dp))
            .padding(horizontal = 12.dp, vertical = 8.dp),
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(8.dp),
    ) {
        Icon(Icons.Default.RecordVoiceOver, null, tint = CF_Green, modifier = Modifier.size(16.dp))
        Text(
            buildAnnotatedString {
                withStyle(SpanStyle(color = CF_Green, fontWeight = FontWeight.Bold)) {
                    append("◉ modo conversación · ")
                }
                withStyle(SpanStyle(color = CF_Dim)) {
                    append("decí \"Cirilo, pará\" para detener")
                }
            },
            fontFamily = mono,
            fontSize = 11.sp,
        )
    }
}

@Composable
private fun MicButton(listening: Boolean, onClick: () -> Unit) {
    val infinite = rememberInfiniteTransition(label = "mic")
    val scale by infinite.animateFloat(
        initialValue = 1f,
        targetValue = if (listening) 1.08f else 1f,
        animationSpec = infiniteRepeatable(
            animation = tween(700, easing = FastOutSlowInEasing),
            repeatMode = RepeatMode.Reverse,
        ),
        label = "scale",
    )
    val bg = if (listening)
        Brush.radialGradient(listOf(CF_Pink, CF_Purple))
    else
        Brush.radialGradient(listOf(CF_Cyan.copy(alpha = 0.08f), CF_Cyan.copy(alpha = 0.08f)))

    val borderColor = if (listening) CF_Pink else CF_Cyan.copy(alpha = 0.4f)
    val iconTint = if (listening) Color.White else CF_Cyan

    Box(
        modifier = Modifier
            .size(44.dp)
            .graphicsLayer {
                if (listening) {
                    scaleX = scale
                    scaleY = scale
                }
            }
            .clip(CircleShape)
            .background(bg)
            .border(1.dp, borderColor, CircleShape)
            .clickable(onClick = onClick),
        contentAlignment = Alignment.Center,
    ) {
        Icon(Icons.Default.Mic, null, tint = iconTint, modifier = Modifier.size(20.dp))
    }
}
