package com.salvadorva.asistente.network

import okhttp3.OkHttpClient
import okhttp3.logging.HttpLoggingInterceptor
import retrofit2.Retrofit
import retrofit2.converter.gson.GsonConverterFactory

object ApiClient {

    // Configura aquí la URL de tu backend (ver README)
    const val BASE_URL = "https://your-backend.example.com/"

    private var token: String? = null

    fun setToken(t: String?) {
        token = t
    }

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
            .addInterceptor(HttpLoggingInterceptor().apply {
                level = HttpLoggingInterceptor.Level.BODY
            })
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
    val deviceApi: DeviceApi by lazy { retrofit.create(DeviceApi::class.java) }
    val chatApi: ChatApi by lazy { retrofit.create(ChatApi::class.java) }
    val agendaApi: AgendaApi by lazy { retrofit.create(AgendaApi::class.java) }
    val focusSlotApi: FocusSlotApi by lazy { retrofit.create(FocusSlotApi::class.java) }
}
