package com.salvadorva.asistente.network

import com.salvadorva.asistente.network.models.ImageEditResponse
import okhttp3.MultipartBody
import okhttp3.RequestBody
import okhttp3.ResponseBody
import retrofit2.Response
import retrofit2.http.GET
import retrofit2.http.Header
import retrofit2.http.Multipart
import retrofit2.http.POST
import retrofit2.http.Part
import retrofit2.http.Path
import retrofit2.http.Streaming

/** IE1: «Editar imagen». La clave de idempotencia evita cobrar dos veces si se reintenta. */
interface ImageEditApi {
    @Multipart
    @POST("api/mobile/images/edit")
    suspend fun edit(
        @Header("Idempotency-Key") idempotencyKey: String,
        @Part image: MultipartBody.Part,
        @Part("instruction") instruction: RequestBody,
        @Part("conversation_id") conversationId: RequestBody?,
    ): Response<ImageEditResponse>

    /** Resultado privado (solo su dueño, 7 días). */
    @Streaming
    @GET("api/mobile/images/edits/{id}")
    suspend fun result(@Path("id") id: String): Response<ResponseBody>
}
