package com.salvadorva.asistente.network

import okhttp3.MediaType.Companion.toMediaType
import okhttp3.OkHttpClient
import okhttp3.Request
import okhttp3.RequestBody.Companion.toRequestBody
import okhttp3.mockwebserver.MockResponse
import okhttp3.mockwebserver.MockWebServer
import okhttp3.logging.HttpLoggingInterceptor
import org.junit.After
import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Test

/**
 * Contrato v1.1, §8.3: release sin logging; debug como máximo HEADERS con Authorization
 * redactado; nunca cuerpos ni token FCM, installation_id, Idempotency-Key o contexto.
 */
class HttpLoggingTest {

    private val server = MockWebServer().apply { start() }
    private val lines = mutableListOf<String>()
    private val log get() = lines.joinToString("\n")

    private val bearer = "1|secreto-sanctum-bearer"

    @After fun tearDown() = server.shutdown()

    private fun call(
        path: String,
        requestBody: String?,
        responseBody: String,
        debug: Boolean = true,
        extraHeaders: Map<String, String> = emptyMap(),
    ) {
        val builder = OkHttpClient.Builder().addInterceptor { chain ->
            chain.proceed(chain.request().newBuilder().addHeader("Authorization", "Bearer $bearer")
                .apply { extraHeaders.forEach { (k, v) -> addHeader(k, v) } }.build())
        }
        HttpLogging.interceptor(debug) { lines += it }?.let(builder::addInterceptor)
        server.enqueue(MockResponse().setBody(responseBody).addHeader("Content-Type", "application/json"))
        val request = Request.Builder().url(server.url(path)).apply {
            if (requestBody != null) post(requestBody.toRequestBody("application/json".toMediaType()))
        }.build()
        builder.build().newCall(request).execute().close()
    }

    @Test
    fun `release no instala interceptor`() {
        assertNull(HttpLogging.interceptor(debug = false))
        call("/api/mobile/chat", """{"prompt":"hola"}""", """{"reply":"hola"}""", debug = false)
        assertTrue(lines.isEmpty())
    }

    @Test
    fun `debug en rutas normales llega como maximo a HEADERS y redacta el bearer`() {
        call("/api/mobile/chat", """{"prompt":"pregunta-privada"}""", """{"reply":"respuesta-privada"}""")
        assertFalse(log.contains(bearer))
        assertTrue(log.contains("Authorization: ██"))
        assertTrue(log.contains("/api/mobile/chat")) // sigue siendo depurable: método, URL, código
        listOf("pregunta-privada", "respuesta-privada").forEach { assertFalse(it, log.contains(it)) }
    }

    @Test
    fun `debug redacta Idempotency-Key`() {
        call(
            "/api/mobile/contextual-reminders/7d1e2c9a-3b4f-4a5e-9c61-2f8d1e0a9b7c/complete",
            """{"expected_version":1}""", """{"state":"completed"}""",
            extraHeaders = mapOf("Idempotency-Key" to "clave-idempotente-secreta-123"),
        )
        assertFalse(log.contains("clave-idempotente-secreta-123"))
        assertTrue(log.contains("Idempotency-Key: ██"))
    }

    @Test
    fun `debug no registra token FCM ni installation_id del reclamo`() {
        assertTrue(HttpLogging.hidesBody("/api/mobile/device-token/claim"))
        call(
            "/api/mobile/device-token/claim",
            """{"token":"fcm-token-privado","installation_id":"0f8c2b1e-7a4d-4a5e-9b61-3c2d1e0f9a8b"}""",
            """{"claimed":true,"installation_id":"0f8c2b1e-7a4d-4a5e-9b61-3c2d1e0f9a8b"}""",
        )
        assertTrue(log.contains("/api/mobile/device-token/claim"))
        listOf("fcm-token-privado", "0f8c2b1e-7a4d-4a5e-9b61-3c2d1e0f9a8b", bearer).forEach { assertFalse(it, log.contains(it)) } // gitleaks:allow (valor ficticio de prueba)
    }

    @Test
    fun `ninguna ruta registra cuerpos en debug`() {
        listOf("/api/mobile/chat", "/api/mobile/agenda", "/api/mobile/login", "/api/mobile/device-token",
            "/api/mobile/device-token/claim", "/api/mobile/contextual-reminders").forEach {
            assertEquals(it, HttpLoggingInterceptor.Level.HEADERS, HttpLogging.levelFor(it))
        }
    }

    @Test
    fun `debug no registra el contexto del detalle de un recordatorio`() {
        call(
            "/api/mobile/contextual-reminders/7d1e2c9a-3b4f-4a5e-9c61-2f8d1e0a9b7c",
            null,
            """{"title":"Enviar propuesta a Ana","context":"Acordamos cerrar la propuesta","next_action":"Abrir el borrador"}""",
        )
        assertTrue(log.contains("/api/mobile/contextual-reminders/"))
        listOf("Enviar propuesta", "Acordamos", "Abrir el borrador", bearer).forEach { assertFalse(it, log.contains(it)) }
    }

    @Test
    fun `debug no registra contrasena ni el bearer que devuelve el login`() {
        call("/api/mobile/login", """{"email":"a@b.c","password":"clave-real"}""", """{"token":"2|bearer-nuevo","user":{}}""")
        listOf("clave-real", "2|bearer-nuevo").forEach { assertFalse(it, log.contains(it)) }
    }

    @Test
    fun `debug no registra el token FCM ni la installation_id del registro`() {
        call("/api/mobile/device-token", """{"token":"fcm-token-privado","installation_id":"inst-privada-123"}""", """{"message":"ok"}""")
        listOf("fcm-token-privado", "inst-privada-123").forEach { assertFalse(it, log.contains(it)) } // gitleaks:allow (valor ficticio de prueba)
    }
}
