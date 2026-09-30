package com.salvadorva.asistente.reminders

import android.content.Context
import android.content.SharedPreferences

/**
 * Almacén clave-valor síncrono y persistente. La deduplicación del push ocurre
 * dentro de onMessageReceived, así que necesita escritura síncrona (commit) que
 * sobreviva a que el proceso muera o el teléfono se reinicie.
 */
interface KeyValueStore {
    fun get(key: String): String?
    fun put(key: String, value: String)
    fun remove(key: String)
    fun keys(prefix: String): Set<String>
}

class SharedPreferencesStore(private val prefs: SharedPreferences) : KeyValueStore {

    constructor(context: Context, name: String) :
        this(context.applicationContext.getSharedPreferences(name, Context.MODE_PRIVATE))

    override fun get(key: String): String? = prefs.getString(key, null)

    override fun put(key: String, value: String) {
        prefs.edit().putString(key, value).commit()
    }

    override fun remove(key: String) {
        prefs.edit().remove(key).commit()
    }

    override fun keys(prefix: String): Set<String> = prefs.all.keys.filterTo(mutableSetOf()) { it.startsWith(prefix) }
}

class InMemoryStore : KeyValueStore {
    private val map = linkedMapOf<String, String>()
    @Synchronized override fun get(key: String) = map[key]
    @Synchronized override fun put(key: String, value: String) { map[key] = value }
    @Synchronized override fun remove(key: String) { map.remove(key) }
    @Synchronized override fun keys(prefix: String) = map.keys.filterTo(mutableSetOf()) { it.startsWith(prefix) }
}

/**
 * Reloj corregido con la hora del servidor. Si el reloj del teléfono está mal,
 * la decisión «ya caducó» se tomaría mal; el desfase se aprende de la cabecera
 * `Date` de las respuestas del backend y persiste entre arranques.
 */
class ReminderClock(
    private val store: KeyValueStore,
    private val deviceNow: () -> Long = System::currentTimeMillis,
) {
    fun now(): Long = deviceNow() + offsetMillis()

    fun offsetMillis(): Long = store.get(KEY_OFFSET)?.toLongOrNull() ?: 0L

    /** Solo corrige desfases relevantes: la cabecera Date tiene resolución de 1 s. */
    fun learnServerTime(serverMillis: Long) {
        val offset = serverMillis - deviceNow()
        if (kotlin.math.abs(offset) < MIN_SIGNIFICANT_OFFSET_MS) {
            if (store.get(KEY_OFFSET) != null) store.remove(KEY_OFFSET)
        } else {
            store.put(KEY_OFFSET, offset.toString())
        }
    }

    companion object {
        private const val KEY_OFFSET = "clock.server_offset_ms"
        const val MIN_SIGNIFICANT_OFFSET_MS = 30_000L
    }
}
