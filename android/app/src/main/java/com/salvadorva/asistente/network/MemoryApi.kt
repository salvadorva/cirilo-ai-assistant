package com.salvadorva.asistente.network

import com.salvadorva.asistente.network.models.MemoryResponse
import com.salvadorva.asistente.network.models.MemorySettingsRequest
import com.salvadorva.asistente.network.models.MemoryValueRequest
import okhttp3.ResponseBody
import retrofit2.Response
import retrofit2.http.Body
import retrofit2.http.DELETE
import retrofit2.http.GET
import retrofit2.http.PATCH
import retrofit2.http.PUT
import retrofit2.http.Path

/** F4-06: ver, editar, olvidar y apagar el aprendizaje. Lo olvidado no se vuelve a aprender solo. */
interface MemoryApi {
    @GET("api/mobile/memory")
    suspend fun list(): Response<MemoryResponse>

    @PATCH("api/mobile/memory/{id}")
    suspend fun update(@Path("id") id: Int, @Body body: MemoryValueRequest): Response<ResponseBody>

    @DELETE("api/mobile/memory/{id}")
    suspend fun forget(@Path("id") id: Int): Response<ResponseBody>

    @DELETE("api/mobile/memory")
    suspend fun forgetAll(): Response<ResponseBody>

    @PUT("api/mobile/memory/settings")
    suspend fun settings(@Body body: MemorySettingsRequest): Response<ResponseBody>
}
