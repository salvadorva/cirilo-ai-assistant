package com.salvadorva.asistente.network

import com.salvadorva.asistente.BuildConfig
import com.salvadorva.asistente.reminders.ContextualReminderApi
import okhttp3.OkHttpClient
import retrofit2.Retrofit
import retrofit2.converter.gson.GsonConverterFactory

object ApiClient {

    // Viene de `cirilo.baseUrl` (local.properties); el repo solo trae una URL de ejemplo.
    val BASE_URL: String = BuildConfig.BASE_URL

    @Volatile private var token: String? = null

    fun setToken(t: String?) {
        token = t
    }

    fun hasToken(): Boolean = token != null

    private val client by lazy {
        OkHttpClient.Builder()
            .connectTimeout(30, java.util.concurrent.TimeUnit.SECONDS)
            .writeTimeout(60, java.util.concurrent.TimeUnit.SECONDS)
            .readTimeout(120, java.util.concurrent.TimeUnit.SECONDS)
            .addInterceptor { chain ->
                val request = chain.request().newBuilder()
                    .addHeader("Accept", "application/json")
                    .apply { token?.let { addHeader("Authorization", "Bearer $it") } }
                    .build()
                chain.proceed(request)
            }
            // Después del de auth para ver (redactada) la cabecera real. En release no existe.
            .apply { HttpLogging.interceptor(BuildConfig.DEBUG)?.let(::addInterceptor) }
            .build()
    }

    private val retrofit by lazy {
        Retrofit.Builder()
            .baseUrl(BASE_URL)
            .client(client)
            .addConverterFactory(GsonConverterFactory.create())
            .build()
    }

    val authApi: AuthApi by lazy { retrofit.create(AuthApi::class.java) }
    val chatApi: ChatApi by lazy { retrofit.create(ChatApi::class.java) }
    val agendaApi: AgendaApi by lazy { retrofit.create(AgendaApi::class.java) }
    val focusSlotApi: FocusSlotApi by lazy { retrofit.create(FocusSlotApi::class.java) }
    val contextualReminderApi: ContextualReminderApi by lazy { retrofit.create(ContextualReminderApi::class.java) }
}
