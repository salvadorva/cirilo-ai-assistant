package com.salvadorva.asistente.reminders

import android.content.Context
import android.content.pm.PackageManager
import androidx.work.BackoffPolicy
import androidx.work.Constraints
import androidx.work.CoroutineWorker
import androidx.work.ExistingWorkPolicy
import androidx.work.NetworkType
import androidx.work.OneTimeWorkRequestBuilder
import androidx.work.WorkManager
import androidx.work.WorkerParameters
import androidx.work.workDataOf
import com.google.firebase.messaging.FirebaseMessaging
import com.salvadorva.asistente.data.SessionManager
import com.salvadorva.asistente.network.ApiClient
import kotlinx.coroutines.flow.first
import kotlinx.coroutines.tasks.await
import kotlinx.coroutines.flow.Flow
import kotlinx.coroutines.flow.filter
import kotlinx.coroutines.flow.map
import kotlinx.coroutines.flow.take
import java.util.UUID
import java.util.concurrent.TimeUnit

/**
 * Punto de entrada Android de los recordatorios contextuales: arma el motor con
 * almacenamiento persistente, notificador y WorkManager.
 */
object Reminders {

    private const val PREFS = "contextual_reminders"
    private const val SYNC_WORK = "contextual_reminders_sync"
    private const val REGISTRATION_WORK = "device_registration"

    @Volatile private var storeInstance: KeyValueStore? = null

    private fun store(context: Context): KeyValueStore =
        storeInstance ?: synchronized(this) {
            storeInstance ?: SharedPreferencesStore(context, PREFS).also { storeInstance = it }
        }

    fun clock(context: Context) = ReminderClock(store(context))
    fun ledger(context: Context) = ReminderLedger(store(context))
    fun outbox(context: Context) = ReminderOutbox(store(context))
    fun notifier(context: Context) = ReminderNotifier(context)
    fun registry(context: Context) = InstallationRegistry(store(context), ApiClient.contextualReminderApi)

    /** Reclamo de la instalación ante 409 installation_conflict (contrato v1.1, §8.1). */
    fun claimFlow(context: Context): InstallationClaimFlow {
        val registry = registry(context)
        val claimer = ApiInstallationClaimer(
            registry = registry,
            currentFcmToken = { runCatching { FirebaseMessaging.getInstance().token.await() }.getOrNull() },
            canDisplay = { notifier(context).canDisplay() },
            appVersion = { appVersion(context) },
        )
        return InstallationClaimFlow(
            registry, claimer,
            clearLocalState = { clearAccountState(context) },
            reRegister = { scheduleRegistration(context, force = true) },
        )
    }

    /**
     * Olvida todo lo de la cuenta que ya no usa este teléfono: avisos visibles,
     * deduplicación, acciones y recibos pendientes. El detalle no se cachea en disco
     * (solo vive en memoria mientras el diálogo está abierto).
     */
    fun clearAccountState(context: Context) {
        val notifier = notifier(context)
        val ledger = ledger(context)
        ledger.all().forEach { notifier.cancel(it.occurrenceId) }
        ledger.clear()
        outbox(context).clear()
        WorkManager.getInstance(context).cancelUniqueWork(SYNC_WORK)
    }

    fun engine(context: Context): ReminderEngine {
        val registry = registry(context)
        return ReminderEngine(
            api = ApiClient.contextualReminderApi,
            ledger = ledger(context),
            outbox = outbox(context),
            notifications = notifier(context),
            clock = clock(context),
            scheduler = { scheduleSync(context) },
            installationId = { registry.existingInstallationId() },
        )
    }

    fun appVersion(context: Context): String? = try {
        context.packageManager.getPackageInfo(context.packageName, 0).versionName
    } catch (_: PackageManager.NameNotFoundException) {
        null
    }

    /**
     * En procesos arrancados por FCM o WorkManager, ApiClient no tiene el bearer
     * (lo pone la UI al abrir). Lo toma de la sesión guardada.
     */
    suspend fun ensureAuth(context: Context): Boolean {
        if (ApiClient.hasToken()) return true
        val token = SessionManager(context.applicationContext).token.first() ?: return false
        ApiClient.setToken(token)
        return true
    }

    fun hasSession(context: Context): Boolean =
        kotlinx.coroutines.runBlocking { SessionManager(context.applicationContext).token.first() != null }

    fun scheduleSync(context: Context) {
        val request = OneTimeWorkRequestBuilder<ReminderSyncWorker>()
            .setConstraints(Constraints.Builder().setRequiredNetworkType(NetworkType.CONNECTED).build())
            .setBackoffCriteria(BackoffPolicy.EXPONENTIAL, 30, TimeUnit.SECONDS)
            .build()
        WorkManager.getInstance(context).enqueueUniqueWork(SYNC_WORK, ExistingWorkPolicy.APPEND_OR_REPLACE, request)
    }

    /**
     * Registro de instalación en segundo plano (onNewToken, inicio de sesión, arranque,
     * «Reintentar registro» en Ajustes). Devuelve el id del trabajo para seguirlo.
     */
    fun scheduleRegistration(context: Context, fcmToken: String? = null, force: Boolean = false): UUID {
        val request = OneTimeWorkRequestBuilder<DeviceRegistrationWorker>()
            .setConstraints(Constraints.Builder().setRequiredNetworkType(NetworkType.CONNECTED).build())
            .setBackoffCriteria(BackoffPolicy.EXPONENTIAL, 30, TimeUnit.SECONDS)
            .setInputData(workDataOf(DeviceRegistrationWorker.KEY_TOKEN to fcmToken, DeviceRegistrationWorker.KEY_FORCE to force))
            .build()
        WorkManager.getInstance(context).enqueueUniqueWork(REGISTRATION_WORK, ExistingWorkPolicy.REPLACE, request)
        return request.id
    }

    /** Emite una vez cuando el trabajo de registro `id` termina (éxito, fallo o cancelado). */
    fun registrationFinished(context: Context, id: UUID): Flow<Unit> =
        WorkManager.getInstance(context).getWorkInfoByIdFlow(id)
            .filter { it?.state?.isFinished == true }
            .take(1)
            .map { }

    /**
     * Cerrar sesión: liberar la instalación (DELETE, requiere el bearer todavía válido)
     * y olvidar avisos y peticiones de la cuenta que sale.
     */
    suspend fun onLogout(context: Context) {
        val token = runCatching { FirebaseMessaging.getInstance().token.await() }.getOrNull()
        if (token != null) registry(context).unregister(token)
        clearAccountState(context)
    }
}

class ReminderSyncWorker(context: Context, params: WorkerParameters) : CoroutineWorker(context, params) {
    override suspend fun doWork(): Result {
        // Sin sesión se conserva la cola: se reenvía con la misma clave al volver a entrar.
        if (!Reminders.ensureAuth(applicationContext)) return Result.success()
        return when (Reminders.engine(applicationContext).sync()) {
            ReminderEngine.SyncResult.DONE, ReminderEngine.SyncResult.SESSION_EXPIRED -> Result.success()
            ReminderEngine.SyncResult.RETRY -> Result.retry()
        }
    }
}

class DeviceRegistrationWorker(context: Context, params: WorkerParameters) : CoroutineWorker(context, params) {
    override suspend fun doWork(): Result {
        if (!Reminders.ensureAuth(applicationContext)) return Result.success()
        val fcmToken = inputData.getString(KEY_TOKEN)
            ?: runCatching { FirebaseMessaging.getInstance().token.await() }.getOrNull()
            ?: return Result.retry()
        val registry = Reminders.registry(applicationContext)
        val canDisplay = Reminders.notifier(applicationContext).canDisplay()
        val appVersion = Reminders.appVersion(applicationContext)
        val force = inputData.getBoolean(KEY_FORCE, false)
        if (!force && !registry.needsRegistration(fcmToken, canDisplay, appVersion)) return Result.success()
        return when (registry.register(fcmToken, canDisplay, appVersion)) {
            InstallationRegistry.Result.RETRY -> Result.retry()
            else -> Result.success()
        }
    }

    companion object {
        const val KEY_TOKEN = "fcm_token"
        const val KEY_FORCE = "force"
    }
}
