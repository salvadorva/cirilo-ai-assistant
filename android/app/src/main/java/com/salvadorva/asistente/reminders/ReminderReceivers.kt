package com.salvadorva.asistente.reminders

import android.content.BroadcastReceiver
import android.content.Context
import android.content.Intent

/**
 * Hecho / Cancelar desde la notificación. Solo persiste la intención y agenda la
 * sincronización: la red va a WorkManager y el aviso queda «pendiente de sincronizar».
 */
class ReminderActionReceiver : BroadcastReceiver() {

    override fun onReceive(context: Context, intent: Intent) {
        if (intent.action != ACTION) return
        val reminderId = intent.getStringExtra(EXTRA_REMINDER_ID) ?: return
        val version = intent.getIntExtra(EXTRA_VERSION, -1).takeIf { it >= 1 } ?: return
        val action = runCatching { ReminderAction.valueOf(intent.getStringExtra(EXTRA_ACTION).orEmpty()) }.getOrNull()
            ?.takeIf { it != ReminderAction.SNOOZE } ?: return
        Reminders.engine(context).requestAction(reminderId, version, action)
    }

    companion object {
        const val ACTION = "com.salvadorva.asistente.CONTEXTUAL_REMINDER_ACTION"
        const val EXTRA_REMINDER_ID = "reminder_id"
        const val EXTRA_VERSION = "version"
        const val EXTRA_ACTION = "action"
    }
}

/** Tras reiniciar, la bandeja está vacía: repone avisos vigentes (sin sonar) y reanuda la cola. */
class ReminderBootReceiver : BroadcastReceiver() {

    override fun onReceive(context: Context, intent: Intent) {
        if (intent.action != Intent.ACTION_BOOT_COMPLETED && intent.action != Intent.ACTION_MY_PACKAGE_REPLACED) return
        if (!Reminders.hasSession(context)) return
        val engine = Reminders.engine(context)
        engine.restoreAfterReboot()
        Reminders.scheduleSync(context)
        // Actualizar la app también exige volver a registrar (app_version).
        Reminders.scheduleRegistration(context)
    }
}
