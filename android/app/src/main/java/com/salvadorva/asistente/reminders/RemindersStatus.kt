package com.salvadorva.asistente.reminders

/** Estado del teléfono como destino de recordatorios acordados, tal como lo ve Ajustes. */
enum class RemindersStatus(val text: String) {
    CONFLICT("conflicto: este teléfono está vinculado a otra cuenta"),
    BLOCKED("bloqueado: permiso de notificaciones o canal «Recordatorios acordados» desactivado"),
    PENDING_SERVER("registro pendiente con el servidor: este teléfono aún no está listo"),
    READY("listo: este teléfono recibe recordatorios acordados"),
}

/** Única acción que ofrece la tarjeta; NONE = la tarjeta no tiene nada que tocar. */
enum class RemindersAction { NONE, CLAIM, RETRY_REGISTRATION }

data class RemindersStatusView(val status: RemindersStatus, val action: RemindersAction)

/**
 * Decide el estado y la acción de la tarjeta. El conflicto va primero (sin resolverlo
 * nada más funciona); luego el permiso/canal (sin él no tiene sentido registrar);
 * luego si el servidor ya marcó la instalación como lista.
 */
fun remindersStatusOf(
    hasConflict: Boolean,
    canDisplay: Boolean,
    isReady: Boolean,
    canOfferClaim: Boolean,
): RemindersStatusView = when {
    hasConflict -> RemindersStatusView(
        RemindersStatus.CONFLICT,
        if (canOfferClaim) RemindersAction.CLAIM else RemindersAction.NONE,
    )
    !canDisplay -> RemindersStatusView(RemindersStatus.BLOCKED, RemindersAction.NONE)
    !isReady -> RemindersStatusView(RemindersStatus.PENDING_SERVER, RemindersAction.RETRY_REGISTRATION)
    else -> RemindersStatusView(RemindersStatus.READY, RemindersAction.NONE)
}

/** Mensaje tras tocar «Reintentar registro»: nunca afirma que quedó listo sin la respuesta. */
const val REGISTRATION_REQUESTED_MESSAGE =
    "Registro solicitado: pendiente con el servidor. Se completa en segundo plano cuando haya red."

/** Mensaje cuando termina el trabajo de registro, según el estado que quedó guardado. */
fun registrationFinishedMessage(status: RemindersStatus): String = when (status) {
    RemindersStatus.READY -> "Registro confirmado: este teléfono está listo."
    RemindersStatus.PENDING_SERVER ->
        "El servidor respondió, pero este teléfono aún no está habilitado. Puedes reintentar más tarde."
    RemindersStatus.CONFLICT -> "El servidor indica que este teléfono está vinculado a otra cuenta."
    RemindersStatus.BLOCKED -> "Activa las notificaciones de Cirilo para completar el registro."
}
