package com.salvadorva.asistente.ui.login

import androidx.compose.animation.core.*
import androidx.compose.foundation.Canvas
import androidx.compose.foundation.Image
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.interaction.MutableInteractionSource
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.BasicTextField
import androidx.compose.foundation.text.KeyboardActions
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Visibility
import androidx.compose.material.icons.filled.VisibilityOff
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.draw.rotate
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.geometry.Size
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.SolidColor
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.platform.LocalFocusManager
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.text.SpanStyle
import androidx.compose.ui.text.TextStyle
import androidx.compose.ui.text.buildAnnotatedString
import androidx.compose.ui.text.font.FontFamily
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.ImeAction
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.text.input.PasswordVisualTransformation
import androidx.compose.ui.text.input.VisualTransformation
import androidx.compose.ui.text.withStyle
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.salvadorva.asistente.R
import com.salvadorva.asistente.ui.theme.*
import kotlinx.coroutines.flow.collectLatest

private val mono = FontFamily.Monospace
private val sans = FontFamily.SansSerif

@Composable
fun LoginScreen(viewModel: LoginViewModel, onLoginSuccess: () -> Unit) {
    val state by viewModel.state.collectAsState()
    var email by remember { mutableStateOf("") }
    var password by remember { mutableStateOf("") }
    var passwordVisible by remember { mutableStateOf(false) }
    var rememberMe by remember { mutableStateOf(true) }
    val focusManager = LocalFocusManager.current

    LaunchedEffect(Unit) {
        viewModel.state.collectLatest {
            if (it is LoginState.Success) onLoginSuccess()
        }
    }

    Box(
        modifier = Modifier
            .fillMaxSize()
            .background(
                Brush.radialGradient(
                    colors = listOf(CF_BgGrad1, CF_BgGrad2, CF_Bg),
                    center = Offset(500f, 0f),
                    radius = 1800f,
                )
            )
    ) {
        GridFloor()
        AmbientGlow()

        Column(
            modifier = Modifier
                .fillMaxSize()
                .verticalScroll(rememberScrollState())
                .padding(horizontal = 18.dp),
            horizontalAlignment = Alignment.CenterHorizontally,
        ) {
            TopBar()

            Spacer(Modifier.height(20.dp))

            HeroAvatar()

            Spacer(Modifier.height(18.dp))

            HeroTitle()

            Spacer(Modifier.height(24.dp))

            TerminalLoginCard(
                state = state,
                email = email,
                onEmailChange = { email = it },
                password = password,
                onPasswordChange = { password = it },
                passwordVisible = passwordVisible,
                onTogglePassword = { passwordVisible = !passwordVisible },
                rememberMe = rememberMe,
                onToggleRemember = { rememberMe = !rememberMe },
                onLogin = {
                    focusManager.clearFocus()
                    viewModel.login(email, password)
                },
            )

            Spacer(Modifier.height(18.dp))

            Spacer(Modifier.weight(1f))
            Spacer(Modifier.height(28.dp))

            Footer()
        }
    }
}

// ─── Top bar terminal ────────────────────────────────────────────
@Composable
private fun TopBar() {
    Row(
        modifier = Modifier
            .fillMaxWidth()
            .padding(top = 12.dp),
        horizontalArrangement = Arrangement.SpaceBetween,
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Text(
            "v1.0.0",
            color = CF_Dim,
            fontFamily = mono,
            fontSize = 11.sp,
        )
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
            "● secure",
            color = CF_Green,
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

        // Líneas horizontales
        var y = floorTop
        while (y < height) {
            val progress = (y - floorTop) / floorHeight
            val alpha = (progress.coerceIn(0f, 1f)) * 0.45f
            drawLine(
                color = CF_GridCyan.copy(alpha = alpha),
                start = Offset(-width * 0.2f, y),
                end = Offset(width * 1.2f, y),
                strokeWidth = 1.dp.toPx(),
            )
            y += gridSize
        }
        // Líneas verticales convergentes
        val centerX = width / 2f
        var i = -10
        while (i <= 10) {
            val baseX = centerX + i * gridSize
            drawLine(
                color = CF_GridPurple.copy(alpha = 0.35f),
                start = Offset(centerX + i * gridSize * 0.2f, floorTop),
                end = Offset(baseX, height),
                strokeWidth = 1.dp.toPx(),
            )
            i++
        }
    }
}

// ─── Ambient purple glow detrás del avatar ───────────────────────
@Composable
private fun AmbientGlow() {
    Canvas(modifier = Modifier.fillMaxSize()) {
        drawCircle(
            brush = Brush.radialGradient(
                colors = listOf(CF_Purple.copy(alpha = 0.28f), Color.Transparent),
                center = Offset(size.width / 2f, size.height * 0.18f),
                radius = 360.dp.toPx(),
            ),
            center = Offset(size.width / 2f, size.height * 0.18f),
            radius = 360.dp.toPx(),
        )
    }
}

// ─── Hero avatar 168dp con anillo conic + HUD ticks ──────────────
@Composable
private fun HeroAvatar() {
    val infinite = rememberInfiniteTransition(label = "hero")
    val rotation by infinite.animateFloat(
        initialValue = 0f,
        targetValue = 360f,
        animationSpec = infiniteRepeatable(
            animation = tween(9000, easing = LinearEasing),
            repeatMode = RepeatMode.Restart,
        ),
        label = "rot",
    )

    Box(
        modifier = Modifier.size(192.dp),
        contentAlignment = Alignment.Center,
    ) {
        // Conic gradient ring rotating
        Box(
            modifier = Modifier
                .size(168.dp)
                .rotate(rotation)
                .clip(CircleShape)
                .background(
                    Brush.sweepGradient(
                        colors = listOf(
                            CF_Cyan,
                            CF_Purple,
                            CF_Pink,
                            CF_Green,
                            CF_Cyan,
                        )
                    )
                )
        )
        // Inner background circle (creates ring effect with 2dp padding)
        Box(
            modifier = Modifier
                .size(164.dp)
                .clip(CircleShape)
                .background(CF_Bg),
        )
        // Avatar
        Image(
            painter = painterResource(id = R.drawable.cirilo_face),
            contentDescription = "Cirilo",
            contentScale = ContentScale.Crop,
            modifier = Modifier
                .size(164.dp)
                .clip(CircleShape),
        )
        // HUD ticks
        Canvas(modifier = Modifier.size(192.dp)) {
            val center = Offset(size.width / 2f, size.height / 2f)
            val radius = 92.dp.toPx()
            val tickH = 12.dp.toPx()
            val tickW = 4.dp.toPx()
            for (deg in listOf(0, 90, 180, 270)) {
                val rad = Math.toRadians(deg.toDouble())
                val cx = center.x + (kotlin.math.sin(rad) * radius).toFloat()
                val cy = center.y - (kotlin.math.cos(rad) * radius).toFloat()
                drawRect(
                    color = CF_Cyan,
                    topLeft = Offset(cx - tickW / 2f, cy - tickH / 2f),
                    size = Size(tickW, tickH),
                )
            }
        }
    }
}

// ─── Título "Hola, soy Cirilo" + subtítulo ───────────────────────
@Composable
private fun HeroTitle() {
    Column(horizontalAlignment = Alignment.CenterHorizontally) {
        Text(
            buildAnnotatedString {
                withStyle(SpanStyle(
                    brush = Brush.linearGradient(listOf(Color.White, Color(0xFFC4B5FD))),
                    fontWeight = FontWeight.Bold,
                )) {
                    append("Hola, soy Cirilo")
                }
            },
            fontFamily = sans,
            fontSize = 28.sp,
            letterSpacing = (-0.6).sp,
        )
        Spacer(Modifier.height(6.dp))
        Text(
            "// tu gorila hacker de confianza 🦍",
            color = CF_Cyan,
            fontFamily = mono,
            fontSize = 11.sp,
            letterSpacing = 2.sp,
        )
    }
}

// ─── Card terminal con email + password ──────────────────────────
@Composable
private fun TerminalLoginCard(
    state: LoginState,
    email: String,
    onEmailChange: (String) -> Unit,
    password: String,
    onPasswordChange: (String) -> Unit,
    passwordVisible: Boolean,
    onTogglePassword: () -> Unit,
    rememberMe: Boolean,
    onToggleRemember: () -> Unit,
    onLogin: () -> Unit,
) {
    Column(
        modifier = Modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(12.dp))
            .background(CF_Panel)
            .border(1.dp, CF_Border, RoundedCornerShape(12.dp))
    ) {
        // Header
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .padding(horizontal = 12.dp, vertical = 8.dp),
            horizontalArrangement = Arrangement.SpaceBetween,
            verticalAlignment = Alignment.CenterVertically,
        ) {
            Text(
                "// auth.session.init",
                color = CF_Dim,
                fontFamily = mono,
                fontSize = 10.sp,
            )
            Text(
                "● ready",
                color = CF_Green,
                fontFamily = mono,
                fontSize = 10.sp,
            )
        }
        HorizontalDivider(color = CF_Border, thickness = 1.dp)

        // Email + password
        Column(modifier = Modifier.padding(horizontal = 14.dp, vertical = 16.dp)) {

            TerminalField(
                label = "$ user.email",
                value = email,
                onValueChange = onEmailChange,
                prompt = "❯",
                promptColor = CF_Cyan,
                bgTint = CF_Cyan.copy(alpha = 0.06f),
                keyboardType = KeyboardType.Email,
                imeAction = ImeAction.Next,
                placeholder = "humano@cirilo.app",
            )

            Spacer(Modifier.height(14.dp))

            TerminalField(
                label = "$ user.passwd",
                value = password,
                onValueChange = onPasswordChange,
                prompt = "❯",
                promptColor = CF_Purple,
                bgTint = CF_Purple.copy(alpha = 0.06f),
                keyboardType = KeyboardType.Password,
                imeAction = ImeAction.Done,
                keyboardActions = KeyboardActions(onDone = { onLogin() }),
                visualTransformation = if (passwordVisible) VisualTransformation.None
                                       else PasswordVisualTransformation(),
                trailingIcon = {
                    IconButton(onClick = onTogglePassword, modifier = Modifier.size(24.dp)) {
                        Icon(
                            if (passwordVisible) Icons.Default.VisibilityOff
                            else Icons.Default.Visibility,
                            contentDescription = null,
                            tint = CF_Dim,
                            modifier = Modifier.size(16.dp),
                        )
                    }
                },
                placeholder = "••••••••••",
            )

            Spacer(Modifier.height(14.dp))

            // Remember + forgot
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically,
            ) {
                Row(
                    verticalAlignment = Alignment.CenterVertically,
                    horizontalArrangement = Arrangement.spacedBy(8.dp),
                    modifier = Modifier.clickable(
                        interactionSource = remember { MutableInteractionSource() },
                        indication = null,
                        onClick = onToggleRemember,
                    ),
                ) {
                    Box(
                        modifier = Modifier
                            .size(16.dp)
                            .clip(RoundedCornerShape(3.dp))
                            .background(
                                if (rememberMe)
                                    Brush.linearGradient(listOf(CF_Cyan, CF_Purple))
                                else
                                    SolidColor(Color.Transparent)
                            )
                            .border(
                                1.dp,
                                if (rememberMe) Color.Transparent else CF_Border,
                                RoundedCornerShape(3.dp),
                            ),
                        contentAlignment = Alignment.Center,
                    ) {
                        if (rememberMe) {
                            Text(
                                "✓",
                                color = Color.White,
                                fontSize = 11.sp,
                                fontWeight = FontWeight.Bold,
                            )
                        }
                    }
                    Text(
                        "remember = true",
                        color = CF_Text,
                        fontFamily = mono,
                        fontSize = 11.sp,
                    )
                }
                Text(
                    "./forgot.sh",
                    color = CF_Pink,
                    fontFamily = mono,
                    fontSize = 11.sp,
                )
            }

            // Error message
            if (state is LoginState.Error) {
                Spacer(Modifier.height(12.dp))
                Text(
                    text = "// error: ${state.message}",
                    color = CF_Pink,
                    fontFamily = mono,
                    fontSize = 11.sp,
                )
            }
        }

        // Primary CTA
        Box(modifier = Modifier.padding(horizontal = 14.dp).padding(bottom = 14.dp)) {
            Box(
                modifier = Modifier
                    .fillMaxWidth()
                    .height(52.dp)
                    .clip(RoundedCornerShape(10.dp))
                    .background(
                        if (state !is LoginState.Loading)
                            Brush.linearGradient(listOf(CF_Purple, CF_Cyan))
                        else
                            Brush.linearGradient(listOf(CF_Purple.copy(alpha = 0.4f), CF_Cyan.copy(alpha = 0.4f)))
                    )
                    .clickable(enabled = state !is LoginState.Loading, onClick = onLogin),
                contentAlignment = Alignment.Center,
            ) {
                if (state is LoginState.Loading) {
                    CircularProgressIndicator(
                        modifier = Modifier.size(22.dp),
                        color = Color.White,
                        strokeWidth = 2.dp,
                    )
                } else {
                    Text(
                        "❯ INICIAR SESIÓN",
                        color = Color.White,
                        fontFamily = mono,
                        fontSize = 13.sp,
                        fontWeight = FontWeight.Bold,
                        letterSpacing = 2.5.sp,
                    )
                }
            }
        }
    }
}

@Composable
private fun TerminalField(
    label: String,
    value: String,
    onValueChange: (String) -> Unit,
    prompt: String,
    promptColor: Color,
    bgTint: Color,
    keyboardType: KeyboardType,
    imeAction: ImeAction,
    keyboardActions: KeyboardActions = KeyboardActions.Default,
    visualTransformation: VisualTransformation = VisualTransformation.None,
    trailingIcon: (@Composable () -> Unit)? = null,
    placeholder: String,
) {
    Column {
        Text(
            text = label,
            color = CF_Dim,
            fontFamily = mono,
            fontSize = 10.sp,
            letterSpacing = 2.sp,
        )
        Spacer(Modifier.height(4.dp))
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .clip(RoundedCornerShape(8.dp))
                .background(bgTint)
                .border(1.dp, CF_Border, RoundedCornerShape(8.dp))
                .padding(horizontal = 14.dp, vertical = 12.dp),
            verticalAlignment = Alignment.CenterVertically,
            horizontalArrangement = Arrangement.spacedBy(10.dp),
        ) {
            Text(
                prompt,
                color = promptColor,
                fontFamily = mono,
                fontSize = 14.sp,
                fontWeight = FontWeight.Bold,
            )
            Box(modifier = Modifier.weight(1f)) {
                BasicTextField(
                    value = value,
                    onValueChange = onValueChange,
                    singleLine = true,
                    visualTransformation = visualTransformation,
                    keyboardOptions = KeyboardOptions(
                        keyboardType = keyboardType,
                        imeAction = imeAction,
                    ),
                    keyboardActions = keyboardActions,
                    textStyle = TextStyle(
                        color = if (visualTransformation == VisualTransformation.None) CF_GreenSoft else CF_Text,
                        fontFamily = mono,
                        fontSize = 13.sp,
                    ),
                    cursorBrush = SolidColor(promptColor),
                    decorationBox = { inner ->
                        if (value.isEmpty()) {
                            Text(
                                placeholder,
                                color = CF_Dim.copy(alpha = 0.6f),
                                fontFamily = mono,
                                fontSize = 13.sp,
                            )
                        }
                        inner()
                    },
                )
            }
            trailingIcon?.invoke()
        }
    }
}

// ─── Footer ─────────────────────────────────────────────────────
@Composable
private fun Footer() {
    Column(
        horizontalAlignment = Alignment.CenterHorizontally,
        modifier = Modifier.padding(bottom = 24.dp),
    ) {
        Row(
            horizontalArrangement = Arrangement.Center,
            verticalAlignment = Alignment.CenterVertically,
        ) {
            Text(
                "¿nuevo aquí? ",
                color = CF_Dim,
                fontFamily = mono,
                fontSize = 12.sp,
            )
            Text(
                "$ ./contacta-admin.sh",
                color = CF_Cyan,
                fontFamily = mono,
                fontSize = 12.sp,
            )
        }
    }
}
