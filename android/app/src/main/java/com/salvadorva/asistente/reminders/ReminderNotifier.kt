package com.salvadorva.asistente.reminders

import android.app.Notification
import android.app.NotificationChannel
import android.app.NotificationManager
import android.app.PendingIntent
import android.content.Context
import android.content.Intent
import android.os.Build
import androidx.core.app.NotificationCompat
import androidx.core.app.NotificationManagerCompat
import com.salvadorva.asistente.MainActivity
import com.salvadorva.asistente.R
import com.salvadorva.asistente.navigation.putContextualReminderExtras

/**
 * Avisos de recordatorios contextuales (sección 3.3–3.5 y 4 del contrato).
 *
 *  - Canal propio, separado del de enfoque. Sin sonido forzado ni saltar No molestar.
 *  - Solo texto genérico; en pantalla bloqueada, visibilidad privada con versión pública genérica.
 *  - Un aviso por ocurrencia (tag derivado de occurrence_id): una versión mayor lo reemplaza.
 *  - Acciones con intents explícitos ligados a (id, versión).
 */
class ReminderNotifier(context: Context) : ReminderNotifications {

    private val context = context.applicationContext
    private val manager = this.context.getSystemService(Context.NOTIFICATION_SERVICE) as NotificationManager
    private val clock by lazy { Reminders.clock(this.context) }

    fun ensureChannel() {
        if (Build.VERSION.SDK_INT < Build.VERSION_CODES.O) return
        if (manager.getNotificationChannel(CHANNEL_ID) != null) return
        manager.createNotificationChannel(
            NotificationChannel(CHANNEL_ID, CHANNEL_NAME, NotificationManager.IMPORTANCE_HIGH).apply {
                description = "Avisos puntuales que acordaste con Cirilo. El detalle solo se ve dentro de la app."
                lockscreenVisibility = Notification.VISIBILITY_PRIVATE
                setBypassDnd(false)
            }
        )
    }

    override fun canDisplay(): Boolean {
        ensureChannel()
        if (!NotificationManagerCompat.from(context).areNotificationsEnabled()) return false
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            val channel = manager.getNotificationChannel(CHANNEL_ID) ?: return false
            if (channel.importance == NotificationManager.IMPORTANCE_NONE) return false
        }
        return true
    }

    override fun show(entry: ReminderLedger.Entry, alert: Boolean) {
        val builder = base(entry, entry.body)
            .setSilent(!alert)
            .addAction(0, "Hecho", actionIntent(entry, ReminderAction.COMPLETE))
            .addAction(0, "Posponer", openIntent(entry, snooze = true))
            .addAction(0, "Cancelar", actionIntent(entry, ReminderAction.CANCEL))
        notify(entry, builder.build())
    }

    override fun showPendingSync(entry: ReminderLedger.Entry, message: String) {
        // Sin acciones: la intención ya quedó en cola. Nunca se muestra como confirmada.
        notify(entry, base(entry, message).setSilent(true).build())
    }

    override fun cancel(occurrenceId: String) = manager.cancel(tag(occurrenceId), NOTIFICATION_ID)

    private fun notify(entry: ReminderLedger.Entry, notification: Notification) {
        if (!canDisplay()) return
        try {
            manager.notify(tag(entry.occurrenceId), NOTIFICATION_ID, notification)
        } catch (_: SecurityException) {
            // POST_NOTIFICATIONS revocado entre la comprobación y el notify.
        }
    }

    private fun base(entry: ReminderLedger.Entry, text: String): NotificationCompat.Builder {
        ensureChannel()
        val remaining = entry.expiresAtMillis - clock.now()
        val publicVersion = NotificationCompat.Builder(context, CHANNEL_ID)
            .setSmallIcon(R.mipmap.ic_launcher)
            .setContentTitle(GENERIC_TITLE)
            .setContentText(GENERIC_BODY)
            .build()
        return NotificationCompat.Builder(context, CHANNEL_ID)
            .setSmallIcon(R.mipmap.ic_launcher)
            .setContentTitle(entry.title)
            .setContentText(text)
            .setCategory(NotificationCompat.CATEGORY_REMINDER)
            .setPriority(NotificationCompat.PRIORITY_HIGH)
            .setVisibility(NotificationCompat.VISIBILITY_PRIVATE)
            .setPublicVersion(publicVersion)
            .setWhen(entry.scheduledAtMillis)
            .setShowWhen(true)
            .setOnlyAlertOnce(true)
            .setAutoCancel(false)
            // Retirar el aviso al caducar aunque la app no vuelva a abrirse.
            .setTimeoutAfter(remaining.coerceAtLeast(1L))
            .setContentIntent(openIntent(entry, snooze = false))
    }

    /** Hecho / Cancelar: broadcast explícito a ReminderActionReceiver con (id, versión). */
    private fun actionIntent(entry: ReminderLedger.Entry, action: ReminderAction): PendingIntent {
        val intent = Intent(context, ReminderActionReceiver::class.java).apply {
            this.action = ReminderActionReceiver.ACTION
            putExtra(ReminderActionReceiver.EXTRA_REMINDER_ID, entry.reminderId)
            putExtra(ReminderActionReceiver.EXTRA_VERSION, entry.version)
            putExtra(ReminderActionReceiver.EXTRA_ACTION, action.name)
        }
        return PendingIntent.getBroadcast(
            context, requestCode(entry, action.name), intent,
            PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE
        )
    }

    /** Tocar el aviso o «Posponer»: abre la app; el detalle y las opciones se piden autenticado. */
    private fun openIntent(entry: ReminderLedger.Entry, snooze: Boolean): PendingIntent {
        val intent = Intent(context, MainActivity::class.java).apply {
            flags = Intent.FLAG_ACTIVITY_CLEAR_TOP or Intent.FLAG_ACTIVITY_SINGLE_TOP
            putContextualReminderExtras(entry.reminderId, snooze)
        }
        return PendingIntent.getActivity(
            context, requestCode(entry, if (snooze) "SNOOZE" else "OPEN"), intent,
            PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE
        )
    }

    private fun requestCode(entry: ReminderLedger.Entry, suffix: String) = "${entry.occurrenceId}:$suffix".hashCode()

    private fun tag(occurrenceId: String) = "$TAG_PREFIX$occurrenceId"

    companion object {
        const val CHANNEL_ID = "contextual_reminders"
        const val CHANNEL_NAME = "Recordatorios acordados"
        private const val TAG_PREFIX = "contextual_reminder:"
        private const val NOTIFICATION_ID = 1
        private const val GENERIC_TITLE = "Cirilo"
        private const val GENERIC_BODY = "Tienes un recordatorio"
    }
}
