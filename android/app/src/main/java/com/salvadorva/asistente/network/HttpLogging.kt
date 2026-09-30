package com.salvadorva.asistente.network

import okhttp3.Interceptor
import okhttp3.logging.HttpLoggingInterceptor

/**
 * Logging HTTP sin filtrar secretos a logcat (contrato Android v1.1, §8.3).
 *
 *  - Release: no hay interceptor (equivale a nivel NONE).
 *  - Debug: como máximo HEADERS en todas las rutas, con `Authorization`,
 *    `Idempotency-Key` y cookies redactados. Nunca se registran cuerpos: ahí viajan
 *    la contraseña y el bearer del login, el token FCM y la `installation_id` del
 *    registro y del reclamo, y el contexto y la siguiente acción de los recordatorios.
 *
 * [BODYLESS_PATH_PREFIXES] fija las rutas que se quedan en HEADERS aunque algún día
 * se suba el nivel por defecto para depurar otra cosa.
 */
object HttpLogging {

    private val BODYLESS_PATH_PREFIXES = listOf(
        "/api/mobile/login",
        "/api/mobile/device-token", // incluye /api/mobile/device-token/claim
        "/api/mobile/contextual-reminders",
    )

    /** §8.3: en debug, como máximo HEADERS. No subir sin revisar el contrato. */
    private val DEBUG_DEFAULT_LEVEL = HttpLoggingInterceptor.Level.HEADERS

    private val REDACTED_HEADERS = listOf("Authorization", "Idempotency-Key", "Cookie", "Set-Cookie")

    fun hidesBody(encodedPath: String): Boolean = BODYLESS_PATH_PREFIXES.any { encodedPath.startsWith(it) }

    fun levelFor(encodedPath: String): HttpLoggingInterceptor.Level =
        if (hidesBody(encodedPath)) HttpLoggingInterceptor.Level.HEADERS else DEBUG_DEFAULT_LEVEL

    fun interceptor(
        debug: Boolean,
        logger: HttpLoggingInterceptor.Logger = HttpLoggingInterceptor.Logger.DEFAULT,
    ): Interceptor? {
        if (!debug) return null
        val byLevel = HttpLoggingInterceptor.Level.entries.associateWith { level ->
            HttpLoggingInterceptor(logger).apply {
                this.level = level
                REDACTED_HEADERS.forEach(::redactHeader)
            }
        }
        return Interceptor { chain -> byLevel.getValue(levelFor(chain.request().url.encodedPath)).intercept(chain) }
    }
}
