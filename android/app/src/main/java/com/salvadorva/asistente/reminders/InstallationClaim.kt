package com.salvadorva.asistente.reminders

/**
 * Reclamo de una instalación en conflicto (contrato Android v1.1, §8.1):
 * `POST /api/mobile/device-token/claim`. El dueño autenticado toma la instalación
 * que quedó ligada a otra cuenta (p. ej. cerró sesión sin red y el DELETE no llegó).
 *
 * Reglas del contrato que aplica [InstallationClaimFlow]:
 *  - solo tras un 409 installation_conflict y una confirmación consciente del usuario;
 *    nunca en segundo plano ni automáticamente;
 *  - antes de reclamar se borra el estado local de la cuenta anterior;
 *  - tras el éxito se vuelve a registrar y desaparece el estado de conflicto.
 */
interface InstallationClaimer {
    val isAvailable: Boolean
    suspend fun claim(): ClaimResult
}

sealed class ClaimResult {
    /** 200. `alreadyOwned` = ya era de esta cuenta: el reclamo es idempotente. */
    data class Claimed(val alreadyOwned: Boolean) : ClaimResult()
    /** No se cumplen las condiciones (sin conflicto o sin confirmación): no se llamó al backend. */
    object NotOffered : ClaimResult()
    object SessionExpired : ClaimResult()
    /** 403 possession_not_proven: el token FCM rotó; solo soporte puede liberar la instalación. */
    object PossessionNotProven : ClaimResult()
    /** 404 installation_not_found: no hay nada que reclamar; se usa el registro normal. */
    object NotFound : ClaimResult()
    /** 429 rate_limited: esperar `retryAfterSeconds` antes de volver a intentarlo. */
    data class RateLimited(val retryAfterSeconds: Long) : ClaimResult()
    /** `retryable` = error transitorio (red, 5xx). `code` = código estable del backend, si lo hay. */
    data class Failed(val code: String?, val retryable: Boolean) : ClaimResult()
}

/** Llamada real: prueba de posesión con la `installation_id` persistida y el token FCM vigente. */
class ApiInstallationClaimer(
    private val registry: InstallationRegistry,
    private val currentFcmToken: suspend () -> String?,
    private val canDisplay: () -> Boolean,
    private val appVersion: () -> String?,
) : InstallationClaimer {
    override val isAvailable = true

    override suspend fun claim(): ClaimResult {
        val token = currentFcmToken() ?: return ClaimResult.Failed(code = null, retryable = true)
        return registry.claim(token, canDisplay(), appVersion())
    }
}

class InstallationClaimFlow(
    private val registry: InstallationRegistry,
    private val claimer: InstallationClaimer,
    /** Avisos, deduplicación, acciones y recibos pendientes de la cuenta anterior. */
    private val clearLocalState: () -> Unit,
    private val reRegister: () -> Unit,
) {
    fun canOffer(): Boolean = registry.hasConflict && claimer.isAvailable

    /** `confirmedByUser` debe venir de la acción del usuario en el diálogo de confirmación. */
    suspend fun claim(confirmedByUser: Boolean): ClaimResult {
        if (!confirmedByUser || !canOffer()) return ClaimResult.NotOffered
        // Si el backend pidió esperar, no se borra nada ni se gasta un intento.
        registry.claimRetryAfterSeconds()?.let { return ClaimResult.RateLimited(it) }
        clearLocalState()
        val result = claimer.claim()
        if (result is ClaimResult.Claimed || result == ClaimResult.NotFound) reRegister()
        return result
    }
}
