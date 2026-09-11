package com.salvadorva.asistente.ui.settings

import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ExitToApp
import androidx.compose.material3.HorizontalDivider
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Switch
import androidx.compose.material3.SwitchDefaults
import androidx.compose.material3.Text
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.SpanStyle
import androidx.compose.ui.text.buildAnnotatedString
import androidx.compose.ui.text.font.FontFamily
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.withStyle
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.lifecycle.viewmodel.compose.viewModel
import com.salvadorva.asistente.ui.theme.*

private val mono = FontFamily.Monospace

@Composable
fun SettingsScreen(
    userName: String,
    userRole: String,
    onLogout: () -> Unit,
    viewModel: SettingsViewModel = viewModel(),
) {
    val state by viewModel.state.collectAsState()

    Column(
        modifier = Modifier
            .fillMaxSize()
            .background(MaterialTheme.colorScheme.background)
            .verticalScroll(rememberScrollState())
            .padding(horizontal = 16.dp, vertical = 18.dp),
        verticalArrangement = Arrangement.spacedBy(18.dp),
    ) {
        SectionLabel("// user.info")
        UserCard(name = userName, role = userRole)

        SectionLabel("// voice.config")
        VoiceConfigCard(
            useOpenAi = state.useOpenAiVoice,
            selectedVoice = state.openAiVoice,
            onToggleOpenAi = viewModel::setUseOpenAiVoice,
            onSelectVoice = viewModel::setOpenAiVoice,
        )

        SectionLabel("// focus.config")
        FocusConfigCard(
            privateMode = state.privateMode,
            onTogglePrivateMode = viewModel::setPrivateMode,
        )

        Spacer(Modifier.height(8.dp))

        LogoutButton(onClick = onLogout)
    }
}

@Composable
private fun SectionLabel(text: String) {
    Text(
        text,
        color = CF_Dim,
        fontFamily = mono,
        fontSize = 11.sp,
        letterSpacing = 1.5.sp,
        modifier = Modifier.padding(start = 4.dp),
    )
}

@Composable
private fun UserCard(name: String, role: String) {
    Row(
        modifier = Modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(12.dp))
            .background(CF_Cyan.copy(alpha = 0.06f))
            .border(1.dp, CF_Cyan.copy(alpha = 0.4f), RoundedCornerShape(12.dp))
            .padding(14.dp),
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(14.dp),
    ) {
        Box(
            modifier = Modifier
                .size(48.dp)
                .clip(CircleShape)
                .background(Brush.linearGradient(listOf(CF_Cyan, CF_Purple))),
            contentAlignment = Alignment.Center,
        ) {
            Text(
                name.filter { it.isLetter() }.take(2).uppercase().ifEmpty { "?" },
                color = Color.White,
                fontWeight = FontWeight.Bold,
                fontFamily = mono,
                fontSize = 15.sp,
            )
        }
        Column {
            Text(
                "$ user: $name",
                color = CF_Text,
                fontFamily = mono,
                fontSize = 13.sp,
                fontWeight = FontWeight.SemiBold,
            )
            Text(
                "$ role: $role",
                color = CF_Dim,
                fontFamily = mono,
                fontSize = 11.sp,
            )
        }
    }
}

@Composable
private fun VoiceConfigCard(
    useOpenAi: Boolean,
    selectedVoice: String,
    onToggleOpenAi: (Boolean) -> Unit,
    onSelectVoice: (String) -> Unit,
) {
    Column(
        modifier = Modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(12.dp))
            .background(CF_Purple.copy(alpha = 0.06f))
            .border(1.dp, CF_Purple.copy(alpha = 0.45f), RoundedCornerShape(12.dp)),
    ) {
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .padding(horizontal = 14.dp, vertical = 12.dp),
            verticalAlignment = Alignment.CenterVertically,
        ) {
            Column(modifier = Modifier.weight(1f)) {
                Text(
                    buildAnnotatedString {
                        withStyle(SpanStyle(color = CF_Purple, fontWeight = FontWeight.Bold)) {
                            append("❯ ")
                        }
                        withStyle(SpanStyle(color = CF_Text, fontWeight = FontWeight.SemiBold)) {
                            append("openai.voice")
                        }
                    },
                    fontFamily = mono,
                    fontSize = 14.sp,
                )
                Spacer(Modifier.height(2.dp))
                Text(
                    if (useOpenAi) "voz OpenAI activada"
                    else "TTS nativo Android (voz del sistema)",
                    color = CF_Dim,
                    fontFamily = mono,
                    fontSize = 11.sp,
                )
            }
            Switch(
                checked = useOpenAi,
                onCheckedChange = onToggleOpenAi,
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

        if (useOpenAi) {
            HorizontalDivider(color = CF_Purple.copy(alpha = 0.25f), thickness = 1.dp)
            Column(modifier = Modifier.padding(vertical = 6.dp)) {
                VoiceRadioRow(
                    label = "echo",
                    description = "masculina · tono firme",
                    selected = selectedVoice == "echo",
                    onClick = { onSelectVoice("echo") },
                )
                VoiceRadioRow(
                    label = "nova",
                    description = "femenina · clara y energética",
                    selected = selectedVoice == "nova",
                    onClick = { onSelectVoice("nova") },
                )
            }
        }
    }
}

@Composable
private fun FocusConfigCard(
    privateMode: Boolean,
    onTogglePrivateMode: (Boolean) -> Unit,
) {
    Row(
        modifier = Modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(12.dp))
            .background(CF_Green.copy(alpha = 0.06f))
            .border(1.dp, CF_Green.copy(alpha = 0.45f), RoundedCornerShape(12.dp))
            .padding(horizontal = 14.dp, vertical = 12.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Column(modifier = Modifier.weight(1f)) {
            Text(
                buildAnnotatedString {
                    withStyle(SpanStyle(color = CF_Green, fontWeight = FontWeight.Bold)) {
                        append("❯ ")
                    }
                    withStyle(SpanStyle(color = CF_Text, fontWeight = FontWeight.SemiBold)) {
                        append("modo.privado")
                    }
                },
                fontFamily = mono,
                fontSize = 14.sp,
            )
            Spacer(Modifier.height(2.dp))
            Text(
                if (privateMode) "los mensajes de enfoque NO se reproducen solos"
                else "los mensajes de enfoque se reproducen al llegar",
                color = CF_Dim,
                fontFamily = mono,
                fontSize = 11.sp,
            )
        }
        Switch(
            checked = privateMode,
            onCheckedChange = onTogglePrivateMode,
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
private fun VoiceRadioRow(
    label: String,
    description: String,
    selected: Boolean,
    onClick: () -> Unit,
) {
    Row(
        modifier = Modifier
            .fillMaxWidth()
            .clickable(onClick = onClick)
            .padding(horizontal = 14.dp, vertical = 10.dp),
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(12.dp),
    ) {
        Box(
            modifier = Modifier
                .size(18.dp)
                .clip(CircleShape)
                .border(
                    width = 1.5.dp,
                    color = if (selected) CF_Cyan else CF_Dim.copy(alpha = 0.5f),
                    shape = CircleShape,
                ),
            contentAlignment = Alignment.Center,
        ) {
            if (selected) {
                Box(
                    modifier = Modifier
                        .size(9.dp)
                        .clip(CircleShape)
                        .background(CF_Cyan)
                )
            }
        }
        Column(modifier = Modifier.weight(1f)) {
            Text(
                "$ $label",
                color = if (selected) CF_Cyan else CF_Text,
                fontFamily = mono,
                fontSize = 13.sp,
                fontWeight = if (selected) FontWeight.SemiBold else FontWeight.Normal,
            )
            Text(
                description,
                color = CF_Dim,
                fontFamily = mono,
                fontSize = 10.5.sp,
            )
        }
    }
}

@Composable
private fun LogoutButton(onClick: () -> Unit) {
    Row(
        modifier = Modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(12.dp))
            .background(CF_Pink.copy(alpha = 0.08f))
            .border(1.dp, CF_Pink.copy(alpha = 0.5f), RoundedCornerShape(12.dp))
            .clickable(onClick = onClick)
            .padding(14.dp),
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(12.dp),
    ) {
        Icon(
            Icons.AutoMirrored.Filled.ExitToApp,
            contentDescription = null,
            tint = CF_Pink,
            modifier = Modifier.size(20.dp),
        )
        Text(
            "❯ logout.session",
            color = CF_Pink,
            fontFamily = mono,
            fontSize = 13.sp,
            fontWeight = FontWeight.SemiBold,
        )
    }
}
