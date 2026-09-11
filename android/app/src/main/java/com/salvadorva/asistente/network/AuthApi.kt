package com.salvadorva.asistente.network

import com.salvadorva.asistente.network.models.LoginRequest
import com.salvadorva.asistente.network.models.LoginResponse
import com.salvadorva.asistente.network.models.MeResponse
import retrofit2.Response
import retrofit2.http.Body
import retrofit2.http.GET
import retrofit2.http.POST

interface AuthApi {
    @POST("api/mobile/login")
    suspend fun login(@Body request: LoginRequest): Response<LoginResponse>

    @GET("api/mobile/me")
    suspend fun me(): Response<MeResponse>

    @POST("api/mobile/logout")
    suspend fun logout(): Response<Unit>
}
