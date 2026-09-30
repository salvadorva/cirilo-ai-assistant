package com.salvadorva.asistente.reminders

import com.google.gson.Gson

/**
 * Registro persistente por ocurrencia (sección 3 del contrato):
 *  - deduplicación por (occurrence_id, version): un reintento del backend o un
 *    push desordenado no vuelve a mostrar ni a sonar;
 *  - cualquier versión menor que la última conocida se descarta;
 *  - lo necesario para retirar, reponer tras reinicio o reconciliar el aviso.
 *
 * Distingue dos versiones: `pushVersion` (último push procesado, para deduplicar)
 * y `version` (la última que confirmó el servidor, para `expected_version`).
 * Posponer sube la versión en el servidor y después llega un push con esa misma
 * versión: ese push es nuevo y debe mostrarse.
 *
 * Nunca guarda contexto: solo lo que ya trae el push (texto genérico).
 */
class ReminderLedger(private val store: KeyValueStore, private val gson: Gson = Gson()) {

    enum class Status {
        /** Aviso visible con acciones. */
        SHOWN,
        /** Hecho/Cancelar tocado; esperando confirmación del servidor. */
        PENDING_SYNC,
        /** Pospuesto (o programado a futuro): oculto hasta el próximo push. */
        WAITING,
        /** Cerrado en el servidor, caducado o descartado: no se muestra más. */
        CLOSED,
    }

    data class Entry(
        val reminderId: String,
        val occurrenceId: String,
        val pushVersion: Int,
        val version: Int,
        val scheduledAtMillis: Long,
        val expiresAtMillis: Long,
        val title: String,
        val body: String,
        val status: Status,
    ) {
        fun isDue(now: Long) = now >= scheduledAtMillis - DUE_TOLERANCE_MS && now < expiresAtMillis
    }

    sealed class Decision {
        /** Primera vez que se ve esta versión: mostrar. `replaces` = sustituye un aviso visible (sin volver a sonar). */
        data class Show(val entry: Entry, val replaces: Boolean) : Decision()
        object Duplicate : Decision()
        object Stale : Decision()
        object Expired : Decision()
    }

    /**
     * Decide y registra en un solo paso (atómico dentro del proceso) para que dos
     * entregas simultáneas del mismo push no pasen ambas el filtro.
     */
    @Synchronized
    fun admit(push: ReminderPush, now: Long): Decision {
        prune(now)
        val existing = entry(push.occurrenceId)
        if (existing != null) {
            if (push.version == existing.pushVersion) return Decision.Duplicate
            if (push.version < existing.pushVersion || push.version < existing.version) return Decision.Stale
            if (push.version == existing.version && existing.status == Status.CLOSED) return Decision.Stale
        }
        val expired = now >= push.expiresAtMillis
        val entry = Entry(
            reminderId = push.reminderId,
            occurrenceId = push.occurrenceId,
            pushVersion = push.version,
            version = push.version,
            scheduledAtMillis = push.scheduledAtMillis,
            expiresAtMillis = push.expiresAtMillis,
            title = push.title,
            body = push.body,
            status = if (expired) Status.CLOSED else Status.SHOWN,
        )
        // Se registra también la versión caducada: un duplicado posterior se descarta sin reevaluar.
        save(entry)
        if (expired) return Decision.Expired
        val replaces = existing != null && (existing.status == Status.SHOWN || existing.status == Status.PENDING_SYNC)
        return Decision.Show(entry, replaces)
    }

    fun entry(occurrenceId: String): Entry? =
        store.get(key(occurrenceId))?.let { runCatching { gson.fromJson(it, Entry::class.java) }.getOrNull() }

    fun findByReminderId(reminderId: String): Entry? =
        entry(reminderId) ?: all().firstOrNull { it.reminderId == reminderId }

    @Synchronized
    fun setStatus(occurrenceId: String, status: Status): Entry? =
        entry(occurrenceId)?.copy(status = status)?.also(::save)

    /**
     * Aplica el estado que confirma el servidor (respuesta de acción, detalle,
     * listado o `current` de un 409). Nunca retrocede de versión.
     */
    @Synchronized
    fun applyServerState(detail: ReminderDetail, now: Long): Entry? {
        val existing = entry(detail.occurrence_id ?: detail.id) ?: return null
        if (detail.version < existing.version) return existing
        val merged = existing.copy(
            version = detail.version,
            scheduledAtMillis = ReminderPushParser.parseUtc(detail.scheduled_at) ?: existing.scheduledAtMillis,
            expiresAtMillis = ReminderPushParser.parseUtc(detail.expires_at) ?: existing.expiresAtMillis,
        )
        val status = when {
            !detail.isPending || now >= merged.expiresAtMillis -> Status.CLOSED
            !merged.isDue(now) -> Status.WAITING
            existing.status == Status.PENDING_SYNC && detail.version == existing.version -> Status.PENDING_SYNC
            existing.status == Status.WAITING -> Status.WAITING // lo vuelve a mostrar el próximo push
            else -> Status.SHOWN
        }
        return merged.copy(status = status).also(::save)
    }

    /** Avisos que deberían seguir visibles (o pendientes de sincronizar) a esta hora. */
    fun visible(now: Long): List<Entry> =
        all().filter { (it.status == Status.SHOWN || it.status == Status.PENDING_SYNC) && now < it.expiresAtMillis }

    fun all(): List<Entry> = store.keys(PREFIX).mapNotNull { k ->
        store.get(k)?.let { runCatching { gson.fromJson(it, Entry::class.java) }.getOrNull() }
    }

    /**
     * Borra todo, deduplicación incluida: el estado era de la cuenta anterior
     * (reclamo de instalación, §8.1, o cierre de sesión).
     */
    @Synchronized
    fun clear() {
        store.keys(PREFIX).forEach(store::remove)
    }

    private fun prune(now: Long) {
        all().filter { now - it.expiresAtMillis > RETENTION_AFTER_EXPIRY_MS }
            .forEach { store.remove(key(it.occurrenceId)) }
    }

    private fun save(entry: Entry) = store.put(key(entry.occurrenceId), gson.toJson(entry))

    private fun key(occurrenceId: String) = PREFIX + occurrenceId

    companion object {
        private const val PREFIX = "occ."
        /** Tras caducar ya nada se muestra; se conserva unos días solo para descartar duplicados tardíos. */
        const val RETENTION_AFTER_EXPIRY_MS = 7L * 24 * 60 * 60 * 1000
        /** Un push puede llegar segundos antes de `scheduled_at` si los relojes difieren. */
        private const val DUE_TOLERANCE_MS = 2L * 60 * 1000
    }
}
