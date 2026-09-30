package com.salvadorva.asistente.ui.main

import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ExitToApp
import androidx.compose.material.icons.filled.CalendarMonth
import androidx.compose.material.icons.filled.Forum
import androidx.compose.material.icons.filled.Settings
import androidx.compose.material.icons.filled.WbSunny
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.salvadorva.asistente.navigation.DeepLink
import com.salvadorva.asistente.ui.agenda.AgendaScreen
import com.salvadorva.asistente.ui.chat.ChatScreen
import com.salvadorva.asistente.ui.reminders.ContextualReminderDialog
import com.salvadorva.asistente.ui.settings.SettingsScreen
import com.salvadorva.asistente.ui.today.TodayScreen
import com.salvadorva.asistente.ui.theme.CIR_Primary
import com.salvadorva.asistente.ui.theme.CIR_PrimaryLight

private data class TabItem(val key: String, val icon: ImageVector, val label: String)

private val tabs = listOf(
    TabItem("Hoy",     Icons.Default.WbSunny,        "Hoy"),
    TabItem("Chat",    Icons.Default.Forum,          "Chat"),
    TabItem("Agenda",  Icons.Default.CalendarMonth,  "Agenda"),
    TabItem("Ajustes", Icons.Default.Settings,       "Ajustes"),
)

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun MainScreen(
    userName: String,
    userRole: String,
    pendingDeepLink: DeepLink? = null,
    onDeepLinkHandled: () -> Unit = {},
    onLogout: () -> Unit,
) {
    // F6: la app abre en «Hoy»; los deep links siguen llevando a su pestaña.
    var selectedKey by remember { mutableStateOf("Hoy") }
    var agendaDeepLinkEventId by remember { mutableStateOf<Int?>(null) }
    var focusDeepLink by remember { mutableStateOf<DeepLink.Focus?>(null) }
    var reminderDeepLink by remember { mutableStateOf<DeepLink.ContextualReminder?>(null) }

    LaunchedEffect(pendingDeepLink) {
        when (pendingDeepLink) {
            is DeepLink.AgendaEvent -> {
                agendaDeepLinkEventId = pendingDeepLink.eventId
                selectedKey = "Agenda"
                onDeepLinkHandled()
            }
            is DeepLink.Focus -> {
                focusDeepLink = pendingDeepLink
                selectedKey = "Chat"
                onDeepLinkHandled()
            }
            is DeepLink.ContextualReminder -> {
                // Se muestra sobre la pestaña actual; el detalle se pide ya autenticado.
                reminderDeepLink = pendingDeepLink
                onDeepLinkHandled()
            }
            is DeepLink.ChatOpen,
            DeepLink.Chat -> {
                selectedKey = "Chat"
                onDeepLinkHandled()
            }
            null -> {}
        }
    }

    reminderDeepLink?.let { link ->
        ContextualReminderDialog(
            reminderId = link.reminderId,
            openSnooze = link.openSnooze,
            onDismiss = { reminderDeepLink = null },
        )
    }

    Scaffold(
        topBar = {
            TopAppBar(
                title = {
                    Column {
                        Text(
                            text = tabs.first { it.key == selectedKey }.label,
                            fontWeight = FontWeight.Bold,
                            fontSize = 18.sp,
                        )
                        Text(
                            text = "Hola, $userName",
                            fontSize = 12.sp,
                            color = MaterialTheme.colorScheme.onSurfaceVariant,
                        )
                    }
                },
                colors = TopAppBarDefaults.topAppBarColors(
                    containerColor = MaterialTheme.colorScheme.surface,
                ),
            )
        },
        bottomBar = {
            BottomTabBar(
                tabs = tabs,
                selected = selectedKey,
                onSelect = { selectedKey = it },
            )
        },
        containerColor = MaterialTheme.colorScheme.background,
    ) { padding ->
        Box(
            modifier = Modifier
                .padding(padding)
                .fillMaxSize()
        ) {
            when (selectedKey) {
                "Hoy"     -> TodayScreen(onOpenChat = { selectedKey = "Chat" })
                "Chat"    -> ChatScreen(
                    focusMessage = focusDeepLink,
                    onFocusConsumed = { focusDeepLink = null },
                )
                "Agenda"  -> AgendaScreen(
                    deepLinkEventId = agendaDeepLinkEventId,
                    onDeepLinkConsumed = { agendaDeepLinkEventId = null },
                )
                "Ajustes" -> SettingsScreen(
                    userName = userName,
                    userRole = userRole,
                    onLogout = onLogout,
                )
            }
        }
    }
}

@Composable
private fun BottomTabBar(
    tabs: List<TabItem>,
    selected: String,
    onSelect: (String) -> Unit,
) {
    Box(
        modifier = Modifier
            .fillMaxWidth()
            .padding(horizontal = 14.dp, vertical = 10.dp),
    ) {
        Card(
            modifier = Modifier
                .fillMaxWidth()
                .height(60.dp),
            shape = RoundedCornerShape(22.dp),
            colors = CardDefaults.cardColors(
                containerColor = MaterialTheme.colorScheme.surface.copy(alpha = 0.95f),
            ),
            elevation = CardDefaults.cardElevation(defaultElevation = 10.dp),
        ) {
            Row(
                modifier = Modifier
                    .fillMaxSize()
                    .padding(6.dp),
                horizontalArrangement = Arrangement.spacedBy(4.dp),
            ) {
                tabs.forEach { tab ->
                    val isActive = selected == tab.key
                    Box(
                        modifier = Modifier
                            .weight(1f)
                            .fillMaxHeight()
                            .clip(RoundedCornerShape(14.dp))
                            .background(
                                if (isActive)
                                    Brush.linearGradient(listOf(CIR_Primary, CIR_PrimaryLight))
                                else
                                    Brush.linearGradient(listOf(Color.Transparent, Color.Transparent))
                            )
                            .clickable { onSelect(tab.key) },
                        contentAlignment = Alignment.Center,
                    ) {
                        if (isActive) {
                            Row(
                                verticalAlignment = Alignment.CenterVertically,
                                horizontalArrangement = Arrangement.spacedBy(5.dp),
                            ) {
                                Icon(
                                    tab.icon, null,
                                    tint = Color.White,
                                    modifier = Modifier.size(18.dp),
                                )
                                Text(
                                    tab.label,
                                    color = Color.White,
                                    fontSize = 12.sp,
                                    fontWeight = FontWeight.SemiBold,
                                )
                            }
                        } else {
                            Icon(
                                tab.icon,
                                contentDescription = tab.label,
                                tint = MaterialTheme.colorScheme.onSurfaceVariant,
                                modifier = Modifier.size(22.dp),
                            )
                        }
                    }
                }
            }
        }
    }
}

