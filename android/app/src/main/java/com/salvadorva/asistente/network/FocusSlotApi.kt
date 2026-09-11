package com.salvadorva.asistente.network

import com.salvadorva.asistente.network.models.AgendaDeleteResponse
import com.salvadorva.asistente.network.models.FocusSlot
import com.salvadorva.asistente.network.models.FocusSlotRequest
import com.salvadorva.asistente.network.models.FocusSlotsResponse
import com.salvadorva.asistente.network.models.PreviewAudioRequest
import com.salvadorva.asistente.network.models.PreviewAudioResponse
import retrofit2.Response
import retrofit2.http.Body
import retrofit2.http.DELETE
import retrofit2.http.GET
import retrofit2.http.POST
import retrofit2.http.PUT
import retrofit2.http.Path

interface FocusSlotApi {

    @GET("api/mobile/focus-slots")
    suspend fun list(): Response<FocusSlotsResponse>

    @POST("api/mobile/focus-slots")
    suspend fun create(@Body body: FocusSlotRequest): Response<FocusSlot>

    @POST("api/mobile/focus-slots/preview-audio")
    suspend fun previewAudio(@Body body: PreviewAudioRequest): Response<PreviewAudioResponse>

    @PUT("api/mobile/focus-slots/{id}")
    suspend fun update(@Path("id") id: Int, @Body body: FocusSlotRequest): Response<FocusSlot>

    @DELETE("api/mobile/focus-slots/{id}")
    suspend fun delete(@Path("id") id: Int): Response<AgendaDeleteResponse>
}
