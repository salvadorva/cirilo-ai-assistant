package com.salvadorva.asistente.ui.theme

import android.app.Activity
import androidx.compose.foundation.isSystemInDarkTheme
import androidx.compose.material3.*
import androidx.compose.runtime.Composable
import androidx.compose.runtime.SideEffect
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.toArgb
import androidx.compose.ui.platform.LocalView
import androidx.core.view.WindowCompat

private val LightColorScheme = lightColorScheme(
    primary              = CIR_Primary,
    onPrimary            = Color.White,
    primaryContainer     = CIR_PrimaryContainer,
    onPrimaryContainer   = CIR_OnPrimaryContainer,
    secondary            = CIR_PrimaryLight,
    onSecondary          = Color.White,
    secondaryContainer   = CIR_Surface2,
    onSecondaryContainer = CIR_Primary,
    tertiary             = CIR_Accent,
    onTertiary           = Color.White,
    tertiaryContainer    = CIR_AccentContainer,
    onTertiaryContainer  = Color(0xFF7B2E00),
    background           = CIR_BgLight,
    onBackground         = CIR_TextPrimary,
    surface              = CIR_Surface,
    onSurface            = CIR_TextPrimary,
    surfaceVariant       = CIR_Surface3,
    onSurfaceVariant     = CIR_TextSecondary,
    outline              = CIR_BorderStrong,
    outlineVariant       = CIR_Border,
    error                = CIR_Danger,
    onError              = Color.White,
    errorContainer       = CIR_DangerContainer,
    onErrorContainer     = Color(0xFF7F1D1D),
)

private val DarkColorScheme = darkColorScheme(
    primary              = CIR_DarkPrimary,
    onPrimary            = CIR_DarkBg,
    primaryContainer     = CIR_DarkPrimaryContainer,
    onPrimaryContainer   = CIR_DarkPrimary,
    secondary            = Color(0xFFD2AFF0),
    onSecondary          = CIR_DarkBg,
    secondaryContainer   = Color(0xFF1F1530),
    onSecondaryContainer = Color(0xFFD2AFF0),
    tertiary             = CIR_DarkAccent,
    onTertiary           = Color(0xFF3A2114),
    tertiaryContainer    = Color(0xFF3A2114),
    onTertiaryContainer  = Color(0xFFFF9355),
    background           = CIR_DarkBg,
    onBackground         = CIR_DarkTextPrimary,
    surface              = CIR_DarkSurface,
    onSurface            = CIR_DarkTextPrimary,
    surfaceVariant       = CIR_DarkSurface3,
    onSurfaceVariant     = CIR_DarkTextSecondary,
    outline              = Color(0xFF3A2D55),
    outlineVariant       = CIR_DarkBorder,
    error                = CIR_DarkDanger,
    onError              = Color(0xFF3A171B),
    errorContainer       = Color(0xFF3A171B),
    onErrorContainer     = CIR_DarkDanger,
)

@Composable
fun AsistenteTheme(
    darkTheme: Boolean = isSystemInDarkTheme(),
    content: @Composable () -> Unit
) {
    val colorScheme = if (darkTheme) DarkColorScheme else LightColorScheme
    val view = LocalView.current
    if (!view.isInEditMode) {
        SideEffect {
            val window = (view.context as Activity).window
            window.statusBarColor = colorScheme.background.toArgb()
            WindowCompat.getInsetsController(window, view).isAppearanceLightStatusBars = !darkTheme
        }
    }
    MaterialTheme(
        colorScheme = colorScheme,
        typography  = Typography,
        content     = content
    )
}
