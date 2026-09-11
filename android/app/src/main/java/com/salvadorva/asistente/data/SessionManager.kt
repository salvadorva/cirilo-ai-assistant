package com.salvadorva.asistente.data

import android.content.Context
import androidx.datastore.core.DataStore
import androidx.datastore.preferences.core.Preferences
import androidx.datastore.preferences.core.booleanPreferencesKey
import androidx.datastore.preferences.core.edit
import androidx.datastore.preferences.core.stringPreferencesKey
import androidx.datastore.preferences.preferencesDataStore
import kotlinx.coroutines.flow.Flow
import kotlinx.coroutines.flow.map

val Context.dataStore: DataStore<Preferences> by preferencesDataStore(name = "session")

class SessionManager(private val context: Context) {

    companion object {
        val TOKEN_KEY              = stringPreferencesKey("auth_token")
        val NAME_KEY               = stringPreferencesKey("user_name")
        val EMAIL_KEY              = stringPreferencesKey("user_email")
        val ROLE_KEY               = stringPreferencesKey("user_role")
        val USE_OPENAI_VOICE_KEY   = booleanPreferencesKey("use_openai_voice")
        val OPENAI_VOICE_KEY       = stringPreferencesKey("openai_voice")
        val PRIVATE_MODE_KEY       = booleanPreferencesKey("private_mode")

        const val DEFAULT_OPENAI_VOICE = "echo"
    }

    val token: Flow<String?> = context.dataStore.data.map { it[TOKEN_KEY] }
    val userName: Flow<String?> = context.dataStore.data.map { it[NAME_KEY] }
    val userRole: Flow<String?> = context.dataStore.data.map { it[ROLE_KEY] }

    /** Si true, las respuestas habladas usan TTS de OpenAI; si false, TTS nativo Android. */
    val useOpenAiVoice: Flow<Boolean> = context.dataStore.data.map {
        it[USE_OPENAI_VOICE_KEY] ?: false
    }

    /** Voz de OpenAI seleccionada cuando useOpenAiVoice está activo. */
    val openAiVoice: Flow<String> = context.dataStore.data.map {
        it[OPENAI_VOICE_KEY] ?: DEFAULT_OPENAI_VOICE
    }

    /** Modo privado: los mensajes de enfoque NO se reproducen solos al llegar (solo notificación). */
    val privateMode: Flow<Boolean> = context.dataStore.data.map {
        it[PRIVATE_MODE_KEY] ?: false
    }

    suspend fun saveSession(token: String, name: String, email: String, role: String) {
        context.dataStore.edit { prefs ->
            prefs[TOKEN_KEY] = token
            prefs[NAME_KEY]  = name
            prefs[EMAIL_KEY] = email
            prefs[ROLE_KEY]  = role
        }
    }

    suspend fun setUseOpenAiVoice(enabled: Boolean) {
        context.dataStore.edit { it[USE_OPENAI_VOICE_KEY] = enabled }
    }

    suspend fun setOpenAiVoice(voice: String) {
        context.dataStore.edit { it[OPENAI_VOICE_KEY] = voice }
    }

    suspend fun setPrivateMode(enabled: Boolean) {
        context.dataStore.edit { it[PRIVATE_MODE_KEY] = enabled }
    }

    suspend fun clearSession() {
        // Limpia solo credenciales; preserva preferencias de voz al cerrar sesión
        context.dataStore.edit { prefs ->
            prefs.remove(TOKEN_KEY)
            prefs.remove(NAME_KEY)
            prefs.remove(EMAIL_KEY)
            prefs.remove(ROLE_KEY)
        }
    }
}
