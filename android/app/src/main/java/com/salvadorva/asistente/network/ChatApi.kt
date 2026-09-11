package com.salvadorva.asistente.network

import com.salvadorva.asistente.network.models.ChatRequest
import com.salvadorva.asistente.network.models.ChatResponse
import okhttp3.MultipartBody
import okhttp3.RequestBody
import retrofit2.Response
import retrofit2.http.Body
import retrofit2.http.Multipart
import retrofit2.http.POST
import retrofit2.http.Part

interface ChatApi {
    @POST("api/mobile/chat")
    suspend fun chat(@Body request: ChatRequest): Response<ChatResponse>

    /**
     * Chat con imagen adjunta. El backend usa GPT-4o Vision y persiste el
     * análisis como mensaje del assistant en la conversación.
     */
    @Multipart
    @POST("api/mobile/chat/image")
    suspend fun chatWithImage(
        @Part image: MultipartBody.Part,
        @Part("prompt") prompt: RequestBody?,
        @Part("conversation_id") conversationId: RequestBody?,
    ): Response<ChatResponse>
}
