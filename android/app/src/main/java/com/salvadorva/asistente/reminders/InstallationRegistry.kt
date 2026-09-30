package com.salvadorva.asistente.reminders

import com.google.gson.Gson
import java.io.IOException
import java.util.UUID

/**
 * Identidad de instalación y registro de capacidad (sección 1 del contrato).
 *
 * `installation_id` es un UUID generado una vez y persistido: no es el token FCM
 * ni el ID de Android, y rotar el token actualiza la misma instalación.
 */
class InstallationRegistry(
    private val store: KeyValueStore,
    private val api: ContextualReminderApi,
    private val now: () -> Long = System::currentTimeMillis,
    private val gson: Gson = Gson(),
    private val newId: () -> String = { UUID.randomUUID().toString() },
) {

    enum class Result { REGISTERED, CONFLICT, SESSION_EXPIRED, REJECTED, RETRY }

    @Synchronized
    fun installationId(): String =
        store.get(KEY_INSTALLATION_ID)?.takeIf { ID_PATTERN.matches(it) }
            ?: newId().also { store.put(KEY_INSTALLATION_ID, it) }

    /** Solo lectura: no crea el ID (útil para recibos antes de registrar). */
    fun existingInstallationId(): String? = store.get(KEY_INSTALLATION_ID)

    /** `contextual_reminders_ready` de la última respuesta; sin eso, la app no muestra funciones contextuales. */
    val isReady: Boolean get() = store.get(KEY_READY) == "true"

    /** La instalación o el token pertenecen a otra cuenta (409 installation_conflict). */
    val hasConflict: Boolean get() = store.get(KEY_CONFLICT) == "true"

    /**
     * Se anuncia `contextual_reminders_v1` solo si el aviso puede mostrarse
     * (permiso y canal habilitados). Si no, se omite el campo y el backend deja de
     * enviarlos: mejor un `device_not_ready` explícito para Hermes que un aviso perdido.
     */
    fun buildRequest(fcmToken: String, canDisplay: Boolean, appVersion: String?) = DeviceRegistrationRequest(
        token = fcmToken,
        installation_id = installationId(),
        capabilities = if (canDisplay) listOf(CAPABILITY_CONTEXTUAL_REMINDERS_V1) else null,
        app_version = appVersion?.take(40),
    )

    /**
     * Registrar al iniciar sesión, al rotar el token, al actualizar la app, si cambió el
     * permiso o si la instalación aún no está lista: un backend anterior pudo responder 200
     * sin `contextual_reminders_ready` y la huella igual no debe impedir el nuevo registro.
     */
    fun needsRegistration(fcmToken: String, canDisplay: Boolean, appVersion: String?): Boolean =
        hasConflict || !isReady || store.get(KEY_FINGERPRINT) != fingerprint(buildRequest(fcmToken, canDisplay, appVersion))

    suspend fun register(fcmToken: String, canDisplay: Boolean, appVersion: String?): Result {
        val request = buildRequest(fcmToken, canDisplay, appVersion)
        val response = try {
            api.registerDevice(request)
        } catch (_: IOException) {
            return Result.RETRY
        }
        return when {
            response.isSuccessful -> {
                recordRegistered(request, response.body()?.contextual_reminders_ready == true)
                Result.REGISTERED
            }
            response.code() == 409 -> {
                // No reintentar en bucle: otra cuenta debe cerrar sesión (DELETE) antes.
                store.put(KEY_CONFLICT, "true")
                store.put(KEY_READY, "false")
                store.remove(KEY_FINGERPRINT)
                Result.CONFLICT
            }
            response.code() == 401 -> Result.SESSION_EXPIRED
            response.code() == 422 -> Result.REJECTED // petición mal formada: reintentar no la arregla
            else -> Result.RETRY
        }
    }

    /**
     * Reclamo explícito (contrato v1.1, §8.1). Solo debe llamarse tras un 409
     * installation_conflict y una confirmación del usuario ([InstallationClaimFlow]).
     * Envía el mismo cuerpo que el registro: `installation_id` + token FCM vigente
     * como prueba de posesión.
     */
    suspend fun claim(fcmToken: String, canDisplay: Boolean, appVersion: String?): ClaimResult {
        claimRetryAfterSeconds()?.let { return ClaimResult.RateLimited(it) } // no gastar intentos del límite
        val request = buildRequest(fcmToken, canDisplay, appVersion)
        val response = try {
            api.claimInstallation(request)
        } catch (_: IOException) {
            return ClaimResult.Failed(code = null, retryable = true)
        }
        val code = errorCode(response)
        return when {
            response.isSuccessful -> {
                val body = response.body()
                recordRegistered(request, body?.contextual_reminders_ready == true)
                store.remove(KEY_CLAIM_BLOCKED_UNTIL)
                ClaimResult.Claimed(alreadyOwned = body?.already_owned == true)
            }
            response.code() == 401 -> ClaimResult.SessionExpired
            // El token FCM no coincide (p. ej. rotó con la sesión cerrada): solo soporte puede liberar.
            response.code() == 403 -> ClaimResult.PossessionNotProven
            // Ya no existe: no hay conflicto que reclamar, toca el registro normal.
            response.code() == 404 -> {
                store.remove(KEY_CONFLICT)
                ClaimResult.NotFound
            }
            response.code() == 429 -> {
                val seconds = response.headers()["Retry-After"]?.trim()?.toLongOrNull()?.coerceAtLeast(1)
                    ?: DEFAULT_CLAIM_RETRY_AFTER_S
                store.put(KEY_CLAIM_BLOCKED_UNTIL, (now() + seconds * 1000).toString())
                ClaimResult.RateLimited(seconds)
            }
            response.code() == 422 -> ClaimResult.Failed(code ?: "validation_failed", retryable = false)
            else -> ClaimResult.Failed(code, retryable = true) // 5xx
        }
    }

    /** Segundos que faltan del último `Retry-After` del reclamo, o null si ya se puede intentar. */
    fun claimRetryAfterSeconds(): Long? {
        val until = store.get(KEY_CLAIM_BLOCKED_UNTIL)?.toLongOrNull() ?: return null
        val remaining = until - now()
        return if (remaining > 0) (remaining + 999) / 1000 else null
    }

    private fun recordRegistered(request: DeviceRegistrationRequest, ready: Boolean) {
        store.put(KEY_READY, ready.toString())
        store.remove(KEY_CONFLICT)
        store.put(KEY_FINGERPRINT, fingerprint(request))
    }

    private fun errorCode(response: retrofit2.Response<*>): String? =
        if (response.isSuccessful) null
        else runCatching { gson.fromJson(response.errorBody()?.charStream(), ReminderApiError::class.java)?.code }.getOrNull()

    /**
     * Al cerrar sesión, antes de invalidar el bearer: libera la instalación para
     * que otra cuenta pueda registrarla. Devuelve false si no se pudo confirmar.
     */
    suspend fun unregister(fcmToken: String): Boolean {
        val ok = try {
            api.removeDevice(DeviceTokenRemoveRequest(fcmToken)).isSuccessful
        } catch (_: IOException) {
            false
        }
        store.remove(KEY_FINGERPRINT)
        store.put(KEY_READY, "false")
        return ok
    }

    private fun fingerprint(r: DeviceRegistrationRequest) =
        listOf(r.token, r.installation_id, r.capabilities?.joinToString(",").orEmpty(), r.app_version.orEmpty())
            .joinToString("|").hashCode().toString()

    companion object {
        private const val KEY_INSTALLATION_ID = "installation.id"
        private const val KEY_FINGERPRINT = "installation.registered_fingerprint"
        private const val KEY_READY = "installation.contextual_ready"
        private const val KEY_CONFLICT = "installation.conflict"
        private const val KEY_CLAIM_BLOCKED_UNTIL = "installation.claim_blocked_until"
        private const val DEFAULT_CLAIM_RETRY_AFTER_S = 3600L
        private val ID_PATTERN = Regex("^[A-Za-z0-9-]{8,100}$")
    }
}
