package com.salvadorva.asistente.reminders

import java.text.ParseException
import java.text.SimpleDateFormat
import java.util.Locale
import java.util.TimeZone

/**
 * Push data-only `contextual_reminder` v1 (sección 2 del contrato). Todos los
 * valores llegan como strings. `title`/`body` son genéricos y son lo único que
 * puede verse en pantalla bloqueada; el push nunca trae contexto.
 */
data class ReminderPush(
    val reminderId: String,
    val occurrenceId: String,
    val version: Int,
    val scheduledAtMillis: Long,
    val expiresAtMillis: Long,
    val title: String,
    val body: String,
)

sealed class PushParseResult {
    data class Valid(val push: ReminderPush) : PushParseResult()
    /** Otro `type`: no es de este receptor. */
    object NotContextual : PushParseResult()
    /** `schema_version` desconocida: se ignora sin fallar. */
    data class UnknownSchema(val schemaVersion: String?) : PushParseResult()
    data class Invalid(val reason: String) : PushParseResult()
}

object ReminderPushParser {

    const val TYPE = "contextual_reminder"
    const val SCHEMA_VERSION = "1"

    private const val DEFAULT_TITLE = "Cirilo"
    private const val DEFAULT_BODY = "Tienes un recordatorio acordado"

    fun parse(data: Map<String, String>): PushParseResult {
        if (data["type"] != TYPE) return PushParseResult.NotContextual
        val schema = data["schema_version"]
        if (schema != SCHEMA_VERSION) return PushParseResult.UnknownSchema(schema)

        val reminderId = data["reminder_id"]?.takeIf { ID_PATTERN.matches(it) }
            ?: return PushParseResult.Invalid("reminder_id")
        val occurrenceId = data["occurrence_id"]?.takeIf { ID_PATTERN.matches(it) }
            ?: return PushParseResult.Invalid("occurrence_id")
        val version = data["version"]?.toIntOrNull()?.takeIf { it >= 1 }
            ?: return PushParseResult.Invalid("version")
        val scheduledAt = parseUtc(data["scheduled_at"]) ?: return PushParseResult.Invalid("scheduled_at")
        val expiresAt = parseUtc(data["expires_at"]) ?: return PushParseResult.Invalid("expires_at")
        if (expiresAt <= scheduledAt) return PushParseResult.Invalid("expires_at")

        // audio_ready se ignora a propósito: piloto solo texto, nunca se reproduce ni descarga audio.
        return PushParseResult.Valid(
            ReminderPush(
                reminderId = reminderId,
                occurrenceId = occurrenceId,
                version = version,
                scheduledAtMillis = scheduledAt,
                expiresAtMillis = expiresAt,
                title = data["title"]?.takeIf { it.isNotBlank() }?.take(MAX_TEXT) ?: DEFAULT_TITLE,
                body = data["body"]?.takeIf { it.isNotBlank() }?.take(MAX_TEXT) ?: DEFAULT_BODY,
            )
        )
    }

    private const val MAX_TEXT = 120
    private val ID_PATTERN = Regex("^[A-Za-z0-9-]{8,100}$")

    /** Formato del backend: `2026-09-21T14:30:00Z` (UTC, sin fracciones). */
    fun parseUtc(value: String?): Long? {
        if (value.isNullOrBlank()) return null
        val format = SimpleDateFormat("yyyy-MM-dd'T'HH:mm:ss'Z'", Locale.US).apply {
            timeZone = TimeZone.getTimeZone("UTC")
            isLenient = false
        }
        return try {
            format.parse(value)?.time
        } catch (_: ParseException) {
            null
        }
    }
}
