package com.salvadorva.asistente.util

import java.text.SimpleDateFormat
import java.util.Calendar
import java.util.Date
import java.util.Locale
import java.util.TimeZone

/**
 * Helpers para mostrar fechas ISO 8601 del backend en la zona horaria local
 * del dispositivo. El backend manda strings tipo "2026-05-21T11:14:00+00:00"
 * o "2026-05-21T11:14:00Z" (UTC); convertimos a la TZ del device antes de
 * formatear.
 */

private fun parseIso(iso: String): Date? {
    if (iso.isBlank()) return null
    val candidates = listOf(
        "yyyy-MM-dd'T'HH:mm:ssXXX",   // 2026-05-21T11:14:00+00:00
        "yyyy-MM-dd'T'HH:mm:ssX",     // 2026-05-21T11:14:00Z
        "yyyy-MM-dd'T'HH:mm:ss.SSSXXX", // con milisegundos
        "yyyy-MM-dd'T'HH:mm:ss.SSSX",
        "yyyy-MM-dd HH:mm:ss"         // fallback sin TZ
    )
    for (pattern in candidates) {
        try {
            val sdf = SimpleDateFormat(pattern, Locale.US)
            if (pattern == "yyyy-MM-dd HH:mm:ss") {
                sdf.timeZone = TimeZone.getTimeZone("UTC")
            }
            return sdf.parse(iso)
        } catch (_: Exception) { /* try next */ }
    }
    return null
}

private fun localFormatter(pattern: String): SimpleDateFormat =
    SimpleDateFormat(pattern, Locale.getDefault()).apply {
        timeZone = TimeZone.getDefault()
    }

/** "yyyy-MM-dd" en hora local. Devuelve el ISO original si no se pudo parsear. */
fun formatLocalDate(iso: String): String {
    val date = parseIso(iso) ?: return iso.take(10)
    return localFormatter("yyyy-MM-dd").format(date)
}

/** "yyyy-MM-dd · HH:mm" en hora local. Devuelve el ISO original si no se pudo parsear. */
fun formatLocalDateTime(iso: String): String {
    val date = parseIso(iso) ?: return iso
    return localFormatter("yyyy-MM-dd · HH:mm").format(date)
}

/** "HH:mm" en hora local. Útil para listas compactas. */
fun formatLocalTime(iso: String): String {
    val date = parseIso(iso) ?: return iso
    return localFormatter("HH:mm").format(date)
}

// ── Helpers para la Agenda ───────────────────────────────────────

/** Parsea un ISO 8601 del backend a Date (o null si no se pudo). */
fun isoToDate(iso: String?): Date? = iso?.let { parseIso(it) }

/** Convierte epoch millis (instante) a ISO 8601 con offset local del device:
 *  "2026-06-20T15:00:00-06:00". Es el formato que espera el backend. */
fun toIso8601(millis: Long): String =
    localFormatter("yyyy-MM-dd'T'HH:mm:ssXXX").format(Date(millis))

/** Formatea un epoch millis con un patrón arbitrario en hora local. */
fun formatMillis(millis: Long, pattern: String): String =
    localFormatter(pattern).format(Date(millis))

/**
 * Etiqueta amigable del día de un evento: "HOY", "MAÑANA" o "vie 20 jun".
 * Devuelve "" si no se pudo parsear.
 */
fun friendlyDayLabel(iso: String?): String {
    val date = isoToDate(iso) ?: return ""
    val keyFmt = localFormatter("yyyy-MM-dd")
    val target = keyFmt.format(date)
    val today = keyFmt.format(Date())
    val tomorrow = keyFmt.format(Date(System.currentTimeMillis() + 86_400_000L))
    return when (target) {
        today -> "HOY"
        tomorrow -> "MAÑANA"
        else -> localFormatter("EEE d MMM").format(date)
            .replaceFirstChar { it.uppercase() }
    }
}

/** Clave de agrupación por día en hora local: "yyyy-MM-dd". */
fun dayKey(iso: String?): String = isoToDate(iso)?.let {
    localFormatter("yyyy-MM-dd").format(it)
} ?: ""

/** Etiqueta legible de un recordatorio en minutos antes del evento. */
fun reminderLabel(minutes: Int): String = when {
    minutes <= 0 -> "sin recordatorio"
    minutes < 60 -> "$minutes min antes"
    minutes < 1440 -> {
        val h = minutes / 60
        if (h == 1) "1 h antes" else "$h h antes"
    }
    else -> {
        val d = minutes / 1440
        if (d == 1) "1 día antes" else "$d días antes"
    }
}

/**
 * Combina la fecha elegida en un DatePicker (millis en UTC, medianoche) con
 * una hora/minuto del TimePicker, produciendo epoch millis en hora local.
 * El DatePicker de Material3 entrega la fecha como medianoche UTC, así que
 * extraemos y/m/d en UTC y reconstruimos en la zona local.
 */
fun combineDateTime(dateUtcMillis: Long, hour: Int, minute: Int): Long {
    val utc = Calendar.getInstance(TimeZone.getTimeZone("UTC")).apply {
        timeInMillis = dateUtcMillis
    }
    val local = Calendar.getInstance().apply {
        clear()
        set(
            utc.get(Calendar.YEAR),
            utc.get(Calendar.MONTH),
            utc.get(Calendar.DAY_OF_MONTH),
            hour,
            minute,
            0,
        )
    }
    return local.timeInMillis
}

/** Extrae (year, month, day, hour, minute) en hora local de un epoch millis. */
fun localParts(millis: Long): IntArray {
    val c = Calendar.getInstance().apply { timeInMillis = millis }
    return intArrayOf(
        c.get(Calendar.YEAR),
        c.get(Calendar.MONTH),
        c.get(Calendar.DAY_OF_MONTH),
        c.get(Calendar.HOUR_OF_DAY),
        c.get(Calendar.MINUTE),
    )
}

/** Convierte una fecha local (y/m/d a medianoche local) a millis UTC para
 *  inicializar el DatePicker de Material3. */
fun localDateToUtcMillis(millis: Long): Long {
    val c = Calendar.getInstance().apply { timeInMillis = millis }
    val utc = Calendar.getInstance(TimeZone.getTimeZone("UTC")).apply {
        clear()
        set(c.get(Calendar.YEAR), c.get(Calendar.MONTH), c.get(Calendar.DAY_OF_MONTH), 0, 0, 0)
    }
    return utc.timeInMillis
}
