package com.salvadorva.asistente.network

import retrofit2.http.Body
import retrofit2.http.HTTP
import retrofit2.http.POST

data class FcmTokenRequest(val token: String, val platform: String = "android")

interface DeviceApi {
    @POST("api/mobile/device-token")
    suspend fun registerToken(@Body body: FcmTokenRequest): retrofit2.Response<Unit>

    @HTTP(method = "DELETE", path = "api/mobile/device-token", hasBody = true)
    suspend fun removeToken(@Body body: FcmTokenRequest): retrofit2.Response<Unit>
}
